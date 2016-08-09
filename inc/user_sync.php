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
            'number' => get_option('gwapi_user_sync_meta_number'),
            'cc' => get_option('gwapi_user_sync_meta_countrycode')
        ];

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
            if (!$userCc) get_option('gwapi_user_sync_meta_default_countrycode');
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