<?php /** @noinspection SpellCheckingInspection */

namespace OnlineCity\GatewayAPI;

if (!defined('ABSPATH')) die('Cannot be accessed directly!');

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

  public function __construct($post)
  {
    $this->post = $post;
    $post_meta = get_post_meta($post->ID);
    $this->fill($post_meta);
  }

  /**
   * Fill the model with an array of attributes.
   *
   * @param array $attributes
   *
   * @return $this
   */
  public function fill(array $attributes)
  {
    foreach ($attributes as $key => $value) {
      try {
        $this->{$key} = $value;
      } catch (UnexpectedValueException $exception) {
        die($exception->getMessage());
      }
    }

    return $this;
  }

  public function getPost()
  {
    return $this->post;
  }

  public function getPostId()
  {
    return $this->post->ID;
  }

  public function registerAction()
  {
    $trigger = $this->getTrigger();

    if ($trigger instanceof Trigger) {
      switch($trigger->getAction()) {
        case 'transition_post_status':
          add_action($trigger->getAction(), [$this, 'notifyPostStatusChange'], 99, 3);
          break;

        case 'wp_after_insert_post':
          add_action($trigger->getAction(), [$this, 'notifyNewPost'], 99, 2);
          break;

        case 'post_updated':
          add_action($trigger->getAction(), [$this, 'notifyPostUpdated'], 99, 1);
          break;

        case 'created_term':
          add_action($trigger->getAction(), [$this, 'notifyTermCreated'], 99, 1);
          break;

        case 'edited_term':
          add_action($trigger->getAction(), [$this, 'notifyTermUpdated'], 99, 1);
          break;

        case 'delete_term':
          add_action($trigger->getAction(), [$this, 'notifyTermDeleted'], 99, 5);
          break;

        case 'wp_login':
          add_action($trigger->getAction(), [$this, 'notifyUserLoggedInSuccess'], 99, 2);
          add_action('wp_login_2fa', [$this, 'notifyUserLoggedInSuccess'], 99, 2);
          break;

        case 'wp_logout':
          add_action($trigger->getAction(), [$this, 'notifyUserLoggedOut'], 99, 1);
          break;

        case 'user_register':
          add_action($trigger->getAction(), [$this, 'notifyUserRegistered'], 99, 1);
          break;

        case 'profile_update':
          add_action($trigger->getAction(), [$this, 'notifyUserProfileUpdate'], 99, 2);
          break;

        case 'deleted_user':
          add_action($trigger->getAction(), [$this, 'notifyUserDeleted'], 99, 3);
          break;

        case 'retrieve_password_key':
          add_action($trigger->getAction(), [$this, 'notifyUserPasswordResetStart'], 99, 2);
          break;

        case 'password_reset':
          add_action($trigger->getAction(), [$this, 'notifyUserPasswordResetDone'], 99, 2);
          break;

        case 'wp_login_failed':
          add_action($trigger->getAction(), [$this, 'notifyLoginFailed'], 99, 2);
          break;

        case 'set_user_role':
          add_action($trigger->getAction(), [$this, 'notifyUserRoleChanged'], 99, 2);
          break;

        case 'transition_comment_status_approved':
          add_action('transition_comment_status', [$this, 'notifyCommentStatusApproved'], 99, 3);
          add_action('comment_post', function($comment_ID, $status) {
            if ($status) $this->notifyCommentStatusApproved('approved', null, get_comment($comment_ID));
          }, 99, 2);
          break;

        case 'spammed_comment':
          add_action($trigger->getAction(), [$this, 'notifyCommentSpammed'], 99, 2);
          break;

        case 'trashed_comment':
          add_action($trigger->getAction(), [$this, 'notifyCommentTrashed'], 99, 2);
          break;

        case 'activated_plugin':
          add_action($trigger->getAction(), [$this, 'notifyActivatedPlugin'], 99, 2);
          break;

        case 'deactivated_plugin':
          add_action($trigger->getAction(), [$this, 'notifyDeactivatedPlugin'], 99, 2);
          break;

        case 'deleted_plugin':
          add_action($trigger->getAction(), [$this, 'notifyDeletedPlugin'], 99, 2);
          break;
      }
    }
  }

  /**
   * @return \OnlineCity\GatewayAPI\Trigger|null
   */
  public function getTrigger()
  {
    $trigger_id = current($this->triggers);
    return gatewayapi__get_trigger_by_id($trigger_id);
  }

  public function notifyPostStatusChange($new_status, $old_status, $post_id)
  {
    if (wp_doing_ajax()) return;

    $post = get_post($post_id);

    if ($new_status === $old_status) return;

    // wrong new status?
    if ($this->getTrigger()->getId() === 'post/drafted' && $new_status !== 'draft') return;
    if ($this->getTrigger()->getId() === 'post/published' && $new_status !== 'publish') return;
    if ($this->getTrigger()->getId() === 'post/pending' && $new_status !== 'pending') return;
    if ($this->getTrigger()->getId() === 'post/scheduled' && $new_status !== 'future') return;
    if ($this->getTrigger()->getId() === 'post/trashed' && $new_status !== 'trash') return;

    if (wp_is_post_revision($post_id)) return;
    if ($this->suppressedPostType($post)) return;

    $this->send();
  }

  public function notifyNewPost($post, $isUpdate) {
    if (!$isUpdate) $this->send();
  }

  public function notifyPostUpdated($post) {
    $this->send();
  }

  public function notifyTermCreated($term_id) {
    $this->send();
  }

  public function notifyTermUpdated($term_id) {
    $this->send();
  }

  public function notifyTermDeleted($term, $tt_id, $taxonomy, $deleted_term, $object_ids) {
    $this->send();
  }

  public function notifyUserLoggedInSuccess($user_login, $user) {
    $this->send();
  }

  public function notifyUserLoggedOut($user_id) {
    $this->send();
  }

  public function notifyUserRegistered($user_id) {
    $this->send();
  }

  public function notifyUserProfileUpdate($user_id, $old_user_data) {
    $this->send();
  }

  public function notifyUserDeleted($id, $reassign, $user) {
    $this->send();
  }

  public function notifyUserPasswordResetStart($user, $new_pass) {
    $this->send();
  }

  public function notifyUserPasswordResetDone($user, $new_pass) {
    $this->send();
  }

  public function notifyLoginFailed($username, $error) {
    $this->send();
  }

  public function notifyUserRoleChanged($user_id, $role, $old_roles) {
    $this->send();
  }

  public function notifyCommentStatusApproved($new_status, $old_status, $comment) {
    if ($new_status === 'approved') $this->send();
  }

  public function notifyCommentSpammed($comment_ID, $comment) {
    $this->send();
  }

  public function notifyCommentTrashed($comment_ID, $comment) {
    $this->send();
  }

  public function notifyActivatedPlugin($plugin, $isNetworkWide) {
    $this->send();
  }

  public function notifyDeactivatedPlugin($plugin, $isNetworkWide) {
    $this->send();
  }

  public function notifyDeletedPlugin($plugin, $deleteSuccess) {
    $this->send();
  }

  public function send()
  {
    $notification_id = $this->post->ID;
    $sender = get_post_meta($notification_id, 'sender', true);
    $message = get_post_meta($notification_id, 'message', true);
    $destaddr = get_post_meta($notification_id, 'destaddr', true) ?: 'MOBILE';


    // don't send invalid sms
    if ($errors = gatewayapi__validate_sms([
      'sender' => $sender,
      'message' => $message,
      'destaddr' => $destaddr,
    ])
    ) { return; }

    // missing secret etc.?
    if (!get_option('gwapi_key') || !get_option('gwapi_secret')) { return; }

    // Extract all tags
    $allTags = gatewayapi__extract_tags_from_message($message);

    // Prepare the recipients
    $recipients = $this->recipients();

    gatewayapi_send_sms($message, $recipients);
  }

  /**
   * @return array
   */
  private function recipients()
  {
    return $this->extractRecipients();
  }

  private function extractRecipients()
  {
    $recipient_type = get_post_meta($this->post->ID, 'recipient_type', true);

    if ($recipient_type === 'recipient') {
      $recipient_id = get_post_meta($this->post->ID, 'recipient_id', true);
      return gatewayapi__notification_get_recipients_by_id($recipient_id);
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
      return gatewayapi__notification_get_recipients_by_id($ids);

    }
    if ($recipient_type === 'role') {
      $roles = get_post_meta($this->post->ID, 'roles', true);

      $args = array(
        'role_in' => $roles,
        'fields' => ['ID'],
        'order' => 'ASC'
      );
      $users = get_users($args);
      return gatewayapi__notification_get_recipients_by_id($users);
    }

    return [];
  }

  private function suppressedPostType($post)
  {
    $suppressed = ['gwapi-notification', 'gwapi-recipient'];

    return in_array($post->post_type, $suppressed, true);

  }


}
