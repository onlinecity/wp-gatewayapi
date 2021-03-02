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

        if ($post->post_type === 'gwapi-notification') {
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

        if (!$recipients) {
            update_post_meta($post_id, 'api_status', 'bail');
            update_post_meta($post_id, 'api_error', 'No recipients added.');
            return;
        }


        gwapi_send_sms($message, $recipients);
    }

    private function recipients() {
        return ['21908089'];
    }


}