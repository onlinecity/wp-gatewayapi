<?php /** @noinspection SpellCheckingInspection */


namespace OnlineCity\GatewayAPI;

use OnlineCity\GatewayAPI\Trigger;
use UnexpectedValueException;


class Notification
{

    /**
     * Post
     *
     * @var \WP_Post
     */
    protected $post;

    public function __construct($post) {
        $this->post = $post;
        $post_meta = get_post_meta($post->ID);
        $this->fill($post_meta);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     *
     * @return $this
     */
    public function fill(array $attributes) {
        foreach ($attributes as $key => $value) {
            try {
                $this->{$key} = $value;
            } catch (UnexpectedValueException $exception) {
                die($exception->getMessage());
            }
        }

        return $this;
    }

    public function getPost() {
        return $this->post;
    }

    public function getPostId() {
        return $this->post->ID;
    }

    public function registerAction() {
        $trigger = $this->getTrigger();

        if ($trigger instanceof Trigger) {
            $action = $trigger->getAction();
            add_action($action, [$this, 'prepare'], 10, 3);
        }
    }

    /**
     * @return \OnlineCity\GatewayAPI\Trigger|null
     */
    public function getTrigger() {
        $trigger_id = current($this->triggers);
        return _gwapi_get_trigger_by_id($trigger_id);
    }

    public function prepare($post_id, $post, $post_before) {
        if (wp_is_post_revision($post_id)) {
            return;
        }

        if ($this->suppressedPostType($post)  || $post->post_status !== 'publish') {
            return;
        }

        update_post_meta($post_id, 'api_status', 'about_to_send');
        $this->send($post_id, $post);
    }

    public function send($post_id, $post) {
        $notification_id = $this->post->ID;
        if (get_post_meta($post_id, 'api_status', true) !== 'about_to_send') {
            return;
        }

        update_post_meta($post_id, 'api_status', 'sending');

        $sender = get_post_meta($notification_id, 'sender', true);
        $message = get_post_meta($notification_id, 'message', true);
        $destaddr = get_post_meta($notification_id, 'destaddr', true) ?: 'MOBILE';


        // don't send invalid sms
        if ($errors = _gwapi_validate_sms([
          'sender'   => $sender,
          'message'  => $message,
          'destaddr' => $destaddr,
        ])
        ) {
            update_post_meta($post_id, 'api_status', 'bail');
            update_post_meta($post_id, 'api_error', __('Validation of the SMS failed prior to sending with the following errors:', 'gatewayapi')."\n- ".implode("\n- ", $errors));
            return;
        }

        // missing secret etc.?
        if (!get_option('gwapi_key') || !get_option('gwapi_secret')) {
            update_post_meta($post_id, 'api_status', 'bail');
            $no_api_error = strtr(__("You have not entered your OAuth key and secret yet. Go to :link to complete the setup.", 'gatewayapi'),
              [':link' => '<a href="options-general.php?page=gatewayapi">'.__('GatewayAPI Settings', 'gatewayapi').'</a>']);
            update_post_meta($post_id, 'api_error', $no_api_error);
            return;
        }

        // Extract all tags
        $allTags = _gwapi_extract_tags_from_message($message);

        // Prepare the recipients
        $recipients = $this->recipients();

        if (empty($recipients)) {
            update_post_meta($post_id, 'api_status', 'bail');
            update_post_meta($post_id, 'api_error', 'No recipients added.');
            return;
        }


        gwapi_send_sms($message, $recipients);
    }

    /**
     * @return array
     */
    private function recipients() {
        return $this->extractRecipients();
    }

    private function extractRecipients() {
        $recipient_type = get_post_meta($this->post->ID, 'recipient_type', true);

        if ($recipient_type === 'recipient') {
            $recipient_id = get_post_meta($this->post->ID, 'recipient_id', true);
            return gwapi_notification_get_recipients_by_id($recipient_id);
        }

        if ($recipient_type === 'recipientGroup') {

            $groups = get_post_meta($this->post->ID, 'recipient_groups', true);
            $recipientsQ = [
              "post_type" => "gwapi-recipient",
              "fields" => "ids",
              "posts_per_page" => -1
            ];

            $recipientsQ["tax_query"] = [
              [
                'taxonomy' => 'gwapi-recipient-groups',
                'field' => 'term_id',
                'terms' => $groups
              ]
            ];

            $ids = (new \WP_Query($recipientsQ))->posts;
            return gwapi_notification_get_recipients_by_id($ids);

        }
        if ($recipient_type === 'role') {
            $roles = get_post_meta($this->post->ID, 'roles', true);

            $args = array(
              'role_in'    => $roles,
              'fields'  => ['ID'],
              'order'   => 'ASC'
            );
            $users = get_users( $args );
            return gwapi_notification_get_recipients_by_id($users);
        }

        return [];
    }

    private function suppressedPostType($post) {
        $suppressed = ['gwapi-notification', 'gwapi-recipient'];

        return in_array($post->post_type, $suppressed, true);

    }


}