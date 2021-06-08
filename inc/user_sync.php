<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Class GWAPI_User_Sync
 *
 * Helper class for synchronizing users when meta data changes.
 */
class GWAPI_User_Sync
{
    private static $instance;
    private $required_to_look_for = [];
    private $others_to_look_for = [];
    private $groups_to_look_for = [];
    private $users_to_sync = [];

    /**
     * @return GWAPI_User_Sync
     */
    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    protected function __construct()
    {
        // built-in
        $this->required_to_look_for = [
            'number' => get_option('gwapi_user_sync_meta_number')
        ];
        if (get_option('gwapi_user_sync_meta_countrycode')) $this->required_to_look_for['cc'] = get_option('gwapi_user_sync_meta_countrycode');

        // other fields
        $otherFields = explode("\n", get_option('gwapi_user_sync_meta_other_fields'));
        foreach($otherFields as $ofRow) {
            $ofRowA = explode(":", $ofRow);
            if (count($ofRowA) != 2) continue;
            list ($user_key, $recipient_key) = $ofRowA;
            $user_key = trim($user_key);
            $recipient_key = trim($recipient_key);

            $this->others_to_look_for[$recipient_key] = $user_key;
        }

        // fields mapping to groups
        $groupFields = get_option('gwapi_user_sync_group_map') ? : [];
        foreach($groupFields as $group_ID => $user_key) {
            $this->groups_to_look_for[$group_ID] = $user_key;
        }
    }

    private function getAllSyncKeys()
    {
        return array_merge(
            array_values($this->required_to_look_for),
            array_values($this->others_to_look_for),
            array_values($this->groups_to_look_for)
        );
    }

    public function shouldSyncUserMetaField($key)
    {
        return in_array($key, $this->getAllSyncKeys());
    }

    public function syncMe($user_ID)
    {
        if (!$this->users_to_sync) $this->registerShutdownFunction();
        $this->users_to_sync[] = $user_ID;

    }

    private function registerShutdownFunction()
    {
        add_action('shutdown', [$this, 'syncOnShutdown']);
    }

    public function syncOnShutdown()
    {
        $user_IDS = array_unique($this->users_to_sync);

        foreach($user_IDS as $userID) {
            // find the user
            $user = get_user_by('id', $userID);

            // does the user have a recipient?
            $recipient_ID = get_user_meta($userID, '_gwapi_recipient_id', true);
            $recipientPost = get_post($recipient_ID);
            if (!$recipientPost) {
                $recipient_ID = null;
            }

            // user mobile
            $userMobile = preg_replace('/\D+/', '', get_user_meta( $userID, $this->required_to_look_for['number'], true )?:'');
            if (!$userMobile) {
                // if there's a recipient connected, delete it - the user is now in an invalid state
                if ($recipient_ID) {
                    wp_trash_post($recipient_ID);
                }
                continue;
            }

            // base recipient object
            $name = $user->display_name;
            $userCc = $this->required_to_look_for['cc'] ? get_user_meta($userID, $this->required_to_look_for['cc'], true) : null;
            if (!$userCc) $userCc = get_option('gwapi_user_sync_meta_default_countrycode');
            $recObject = [
                'post_title' => $name,
                'post_status' => 'publish',
                'post_type' => 'gwapi-recipient',
                'meta_input' => [
                    'number' => $userMobile,
                    'cc' => $userCc
                ],
                'tax_input' => [
                    'gwapi-recipient-groups' => []
                ]
            ];

            // delete other recipient, if another recipient already contains this mobile + cc!
            $conflictingRecipients = new WP_Query([
                "post_type" => 'gwapi-recipient',
                "meta_query" => [
                    [
                        'number' => $userMobile,
                        'cc' => $userCc
                    ]
                ],
                "post__not_in" => [ $recipient_ID ],
                "fields" => "ids"
            ]);
            foreach($conflictingRecipients as $cfId) {
                wp_trash_post($cfId);
            }

            // recipient ID?
            if ($recipient_ID) {
                $recObject['ID'] = $recipient_ID;
            }

            // other meta keys/values
            foreach($this->others_to_look_for as $recKey => $userKey) {
                $recObject['meta_input'][$recKey] = get_user_meta($userID, $userKey, true);
            }

            // groups
            foreach($this->groups_to_look_for as $groupID => $metaKey) {
                if (!$metaKey) continue;

                $v = get_user_meta($userID, $metaKey, true);
                if ($v && $v != '0' && $v !== 'false') {
                    $recObject['tax_input']['gwapi-recipient-groups'][] = (int)$groupID;
                }
            }

            // insert/update
            $new_recipient_ID = wp_insert_post($recObject);

            // save the recipient ID on the user
            if ($recipient_ID !== $new_recipient_ID) {
                update_user_meta($userID, '_gwapi_recipient_id', $new_recipient_ID);
            }
        }
    }

    /**
     * Synchronize all users.
     */
    public function syncAll()
    {
        header("Content-type: application/json");
        $userQ = [
            'fields' => 'ID',
            'number' => 100,
            'meta_query' => [
                [
                    'key' => $this->required_to_look_for['number'],
                    'compare' => 'EXISTS'
                ]
            ]
        ];

        // initializing - just show status, ie. how many users are applicable for syncing
        if (!isset($_GET['page'])) {
            $q = new WP_User_Query($userQ);
            $total = $q->get_total();
            if (!$total) {
                die(json_encode(['html' => __('There are no users to synchronize at this time, ie. no users which has the meta field for mobile number.', 'gatewayapi'), 'finished' => true]));
            }

            die(json_encode([
                'html' => sprintf(_n("%d user is being synchronized now", "%d users are being synchronized now", $total, 'gatewayapi'), $total),
                'finished' => false
            ]));
        }

        // synchronize 100 at a time
        $userQ['paged'] = $_GET['page'];
        $q = new WP_User_Query($userQ);
        $IDs = $q->get_results();
        $max = $q->get_total();
        if (!$IDs) {
            die(json_encode([
                'html' => sprintf(__('User synchronization is complete. %d users were synchronized.', 'gatewayapi'), $max),
                'finished' => true
            ]));
        }
        foreach($IDs as $ID) {
            $this->syncMe($ID);
        }

        $done = count($IDs) + ($_GET['page']-1) * 100;
        die(json_encode([
            'html' => sprintf( __('%d users of %d synchronized...', 'gatewayapi'), $done, $max ),
            'finished' => false
        ]));
    }
}

/**
 * Automatically synchronize users into recipients.
 */
add_action('update_user_meta', function($meta_ID, $user_ID, $key, $value) {
    $user_sync = GWAPI_User_Sync::getInstance();
    if (!$user_sync->shouldSyncUserMetaField($key)) return;

    $user_sync->syncMe($user_ID);
}, 20, 4);

/**
 * On delete user, also delete the associated recipient.
 */
add_action('delete_user', function($user_ID) {
    if ($recID = get_user_meta($user_ID, '_gwapi_recipient_id', true)) {
        wp_delete_post($recID);
    }
});

add_action('wp_ajax_gwapi_user_sync', function() {
    $user_sync = GWAPI_User_Sync::getInstance();
    $user_sync->syncAll();
});
