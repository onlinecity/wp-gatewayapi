<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

/**
 * Class for integration with the free Contact Form 7-plugin
 * =========================================================
 *
 * This class adds a few extra field types to Contact Form 7 and, when present in form submission, creates/updates/
 * unsubscribes recipients from the GatewayAPI recipients list.
 */
class GwapiContactForm7
{
  private static $instance;

  private $types = [];

  public static function getInstance()
  {
    if (null === static::$instance) {
      static::$instance = new static();
    }

    return static::$instance;
  }

  private function __construct()
  {
    $this->types = [
      'gw_phone' => '📱 ' . __('phone', 'gatewayapi'),
      'gw_country' => '📱 ' . __('countrycode', 'gatewayapi'),
      'gw_groups' => '📱 ' . __('groups', 'gatewayapi'),
      'gw_smstext' => '📱 ' . __('smstext', 'gatewayapi'),
      'gw_action' => '📱 ' . __('action*', 'gatewayapi')
    ];
  }

  public function initAdmin()
  {
    $this->addTagGenerator();
    $this->addSmsReplyUi();
  }

  public function handleShortcodes()
  {
    $this->addShortcodes();

    // handle send verification code
    add_action('wp_ajax_nopriv_gwapi_send_verify_sms', [$this, 'sendVerifySms']);
    add_action('wp_ajax_gwapi_send_verify_sms', [$this, 'sendVerifySms']);

    add_action('wp_ajax_nopriv_gwapi_verify_sms', [$this, 'verifySms']);
    add_action('wp_ajax_gwapi_verify_sms', [$this, 'verifySms']);

    // when confirming verify codes, don't fall into double-spam trap
    $this->resolveCF7SpamTrap();
  }


  /**
   * Make sure the CF7 spam trap is just being resolved once - especially reCaptcha fails if trying to verify the same
   * token twice, so after first succesful resolve, allow our own multi-use token.
   */
  private function resolveCF7SpamTrap()
  {
    if (!isset($_POST['gwapi_spam_trap_resolve']) || !$_POST['gwapi_spam_trap_resolve']) return;
    $user_token = $_POST['gwapi_spam_trap_resolve'];

    $resolve_token = get_transient('gwapi_spam_trap_resolve_' . gwapi_get_msisdn($_POST['gwapi_country'], $_POST['gwapi_phone']));
    if ($resolve_token !== $user_token) return; // fail regularly, somethings wrong with the token

    add_filter('wpcf7_spam', function () {
      return false;
    }, 20);

  }

  public function addTagGenerator()
  {
    $tag_generator = WPCF7_TagGenerator::get_instance();
    foreach ($this->types as $key => $item) {
      $func = [$this, 'tagGenerate' . substr($key, 3)];
      $tag_generator->add($key, $item, $func);
    }
  }

  private function addSmsReplyUi()
  {
    $_this = $this;
    add_filter('wpcf7_editor_panels', function ($panels) use ($_this) {
      $sms_panel = [
        'title' => __('SMS reply', 'gatewayapi'),
        'callback' => [$this, 'renderSmsReplyUi']
      ];
      $new_panels = [];

      foreach ($panels as $k => $p) {
        $new_panels[$k] = $p;
        if ($k == 'mail-panel') {
          $new_panels['sms-reply-panel'] = $sms_panel;
        }
      }

      return $new_panels;
    }, 10, 1);

    // handle save
    if (isset($_POST['_gwapi_form_settings'])) {
      update_post_meta($_POST['post_ID'], '_gwapi', $_POST['_gwapi_form_settings']);
    }
  }

  private function addShortcodes()
  {
    static $is_called = false;
    if ($is_called) return;
    $is_called = true;

    foreach ($this->types as $key => $name) {
      // validation of field
      add_filter('wpcf7_validate_' . $key, [$this, 'validate' . substr($key, 3)], 10, 2);
      add_filter('wpcf7_validate_' . "$key*", [$this, 'validate' . substr($key, 3)], 10, 2);

      $code = [$key, "$key*"];
      // add shortcode
      $func = [$this, 'handle' . substr($key, 3)];
      wpcf7_add_form_tag($code, $func, true);
    }
  }

  public function renderSmsReplyUi(WPCF7_ContactForm $post)
  {
    $opt = get_post_meta((int)$post->ID(), '_gwapi', true) ?: [];
    ?>
    <div class="contact-form-editor-box-sms-reply" id="gwapi-sms-reply">
      <h2><?php _e('SMS reply', 'gatewayapi'); ?></h2>

      <p>
        <?php _e('Please note that replying via SMS requires that the GatewayAPI phone number and country code fields has been added to the form.', 'gatewayapi'); ?>
      </p>

      <fieldset>
        <legend><?php _e('In the following fields, these tags are available:', 'gatewayapi'); ?><br>
          <?= $post->suggest_mail_tags(); ?>
        </legend>
        <table class="form-table">
          <tbody>
          <tr>
            <th scope="row">
              <label for="gwapi-sms-reply-enable"><?php _e('Enable?', 'gatewayapi'); ?></label>
            </th>
            <td>
              <label><input type="checkbox" name="_gwapi_form_settings[reply-enable]"
                            value="1" <?= isset($opt['reply-enable']) && $opt['reply-enable'] ? 'checked' : ''; ?>><?php _e('Yes, send an auto-reply to the recipients phone number, when the form has been succesfully submitted.', 'gatewayapi'); ?>
              </label>
            </td>
          </tr>
          <tr class="only-show-on-enabled-sms-reply">
            <th scope="row">
              <label for="gwapi-sms-reply-sender"><?php _e('From', 'contact-form-7'); ?></label>
            </th>
            <td>
              <input type="text" id="gwapi-sms-reply-sender" name="_gwapi_form_settings[reply-sender]"
                     class="large-text code" size="70"
                     value="<?= isset($opt['reply-sender']) && $opt['reply-sender'] ? esc_attr($opt['reply-sender']) : ''; ?>"
                     maxlength="15">
              <p class="help-block"><?php _e('Up to 11 character or 15 digits.', 'gatewayapi'); ?></p>
            </td>
          </tr>

          <tr class="only-show-on-enabled-sms-reply">
            <th scope="row">
              <label for="gwapi-sms-reply-body"><?php _e('Message', 'gatewayapi'); ?></label>
            </th>
            <td>
                            <textarea id="gwapi-sms-reply-body" name="_gwapi_form_settings[reply-body]" cols="100"
                                      rows="5"
                                      class="large-text code"><?= isset($opt['reply-sender']) && $opt['reply-body'] ? esc_attr($opt['reply-body']) : ''; ?></textarea>
            </td>
          </tr>

          </tbody>
        </table>
      </fieldset>
    </div>
    <?php
    $this->enqueueContactform7Js();
  }

  private function handleSubmitSignupVerify(WPCF7_ContactForm $wpcf7, WPCF7_Submission $submit)
  {
    $actions_field = $wpcf7->scan_form_tags(['type' => 'gw_action']);
    if (!count($actions_field)) return;

    // must have gw_actions to be relevant at all
    $actions_field = current($actions_field);

    $isSignup = in_array('action:signup', $actions_field['options']);
    if (!$isSignup) $isSignup = $actions_field['name'] == 'action:signup'; // legacy way of detecting that this is a signup!

    if (!$isSignup) return;

    // must contain a verify:yes requirement
    if (!$actions_field['options'] || $actions_field['options'][0] != 'verify:yes') return;

    $phone_field = $wpcf7->scan_form_tags(['type' => 'gw_phone']);
    $country_code_field = $wpcf7->scan_form_tags(['type' => 'gw_country']);

    // must have phone and country code
    if (!$phone_field || !$country_code_field) return;

    $cc = $_POST[$country_code_field['name']] ?? null;
    $local = $_POST[$phone_field['name']] ?? null;
    if (!$cc || !$local || !ctype_digit($cc) || !ctype_digit($local)) die();

    // has the user entered a verification pin code?
    if (!isset($_POST['_gwapi_verify_signup'])) {
      $phone = gwapi_get_msisdn($cc, $local);
      $code = get_transient("gwapi_verify_signup_" . $phone);

      header("Content-type: application/json");
      if (!$code) {
        set_transient('gwapi_verify_signup_' . $phone, $code = rand(100000, 999999), 60 * 5);

        $spam_trap_resolve = wp_generate_password(32, false);
        set_transient('gwapi_spam_trap_resolve_' . $phone, $spam_trap_resolve, 60 * 5);

        gwapi_send_sms(__("Your verification code:", 'gatewayapi') . " " . $code, $phone);
        die(json_encode(['gwapi_verify' => true, 'gwapi_prompt' => __("We have just sent an SMS to your mobile. Please enter the code here in order to verify the phone number.", 'gatewayapi'), 'spam_trap_resolve' => $spam_trap_resolve]));
      } else {
        die(json_encode(['gwapi_verify' => true, 'gwapi_error' => __("You have tried verifying this phone number very recently, but did not complete the required steps. To prevent abuse, please wait 5 minutes before trying again.", 'gatewayapi'), 'spam_trap_resolve' => $spam_trap_resolve]));
      }
    }
  }

  /**
   * Return a list of group IDs from a tag. This is provided in order to have a backwards compatible and simple way of
   * fetching the list of IDs defined in a tag.
   */
  private function getGroupIdsFromTag($tag)
  {
    $group_ids = [];

    $options = is_array($tag) ? (isset($tag['options']) ? $tag['options'] : []) : (isset($tag->options) ? $tag->options : []);
    $values = is_array($tag) ? (isset($tag['values']) ? $tag['values'] : []) : (isset($tag->values) ? $tag->values : []);

    foreach ($options as $opt) {
      if (ctype_digit($opt)) $group_ids[] = (int)$opt; // classic style IDs without quotes
    }

    // proper CF7-style values with quotes
    if ($values) {
      if (is_array($values) && count($values) == 1) $values = $values[0];

      foreach (explode(' ', $values) as $id) {
        $id = (int)$id;
        if ($id) $group_ids[] = $id;
      }
    }

    return $group_ids;
  }

  public function handleSubmit($form)
  {
    $wpcf7 = WPCF7_ContactForm::get_current();
    /** @var $wpcf7 WPCF7_ContactForm */
    $submission = WPCF7_Submission::get_instance();

    // special case: signup + verification SMS
    $this->handleSubmitSignupVerify($wpcf7, $submission);

    // does the form have an sms auto reply?
    $send_sms = get_post_meta($wpcf7->id(), '_gwapi', true) ?: false;
    if ($send_sms && isset($send_sms['reply-enable']) && $send_sms['reply-enable']) {
      $this->sendSubmitSmsReply($wpcf7, $submission, $send_sms);
    }

    if (!$submission) return;

    $country_code_field = current($wpcf7->scan_form_tags(['type' => 'gw_country']));
    $groups_field = current($wpcf7->scan_form_tags(['type' => 'gw_groups']));
    $actions_field = current($wpcf7->scan_form_tags(['type' => 'gw_action']));
    $phone_field = current($wpcf7->scan_form_tags(['type' => 'gw_phone']));

    $all_fields = $wpcf7->scan_form_tags();

    if (!$actions_field) return; // nothing to do

    // get form action
    $action = null;
    foreach ($actions_field['options'] as $o) {
      if (strpos($o, 'action:') === 0) $action = trim(substr($o, 7));
    }
    if (!$action) $action = substr($o['name'], 7);

    $cc = isset($_POST[$country_code_field['name']]) ? $_POST[$country_code_field['name']] : null;
    $local = isset($_POST[$phone_field['name']]) ? $_POST[$phone_field['name']] : null;

    $data = $submission->get_posted_data();

    $curID = null;
    if (in_array($action, ['unsubscribe', 'update'])) {
      $q = new WP_Query(["post_type" => "gwapi-recipient", "meta_query" => [['key' => 'cc', 'value' => $cc], ['key' => 'number', 'value' => $local]]]);
      $curID = $q->post->ID;
      if (!$curID) return; // should never happen, validation would have caught this...
    }

    $insert_data = null;
    if (in_array($action, ['update', 'signup'])) {
      // title/name for recipient
      $title = '';
      $name_fields = ['name', 'full_name'];
      foreach ($name_fields as $nf) {
        if (isset($_POST[$nf])) $title = $_POST[$nf];
      }
      if (!$title) {
        if (isset($_POST['first_name'])) $title = sanitize_text_field($_POST['first_name']);
        if (isset($_POST['last_name'])) $title .= " " . sanitize_text_field($_POST['last_name']);
      }
      if (!$title) $title = '+' . $cc . ' ' . $local;

      // data
      $insert_data = [
        "post_type" => "gwapi-recipient",
        "post_status" => "publish",
        "meta_input" => [
          "cc" => $cc,
          "number" => $local
        ],
        "post_title" => $title
      ];

      // other fields posted?
      foreach ($all_fields as $af) {
        if (substr($af['basetype'], 0, 3) === 'gw_') continue;
        if (!$af['name']) continue;
        $insert_data['meta_input'][$af['name']] = $data[$af['name']];
      }
    }

    switch ($action) {
      case 'update':
        $insert_data['ID'] = $curID;

      case 'signup':
        $curID = wp_insert_post($insert_data);

        // update groups? update groups
        if ($groups_field) {

          // get current groups
          $cur_groups = wp_get_object_terms($curID, 'gwapi-recipient-groups', ['fields' => 'ids']);
          $possible_groups = $this->getGroupIdsFromTag($groups_field);

          // append groups not selectable, but previously selected, in this option
          $append_groups = [];
          foreach ($cur_groups as $gID) {
            if (!in_array($gID, $possible_groups)) $append_groups[] = $gID;
          }

          $groupIDs = isset($_POST[$groups_field['name']]) ? $_POST[$groups_field['name']] : [];
          foreach ($groupIDs as &$gID) {
            $gID = (int)$gID;
          }

          wp_set_object_terms($curID, array_merge($groupIDs, $append_groups), 'gwapi-recipient-groups');
        }

        break;

      case 'sms':
        $smstext_field = current($wpcf7->scan_form_tags(['type' => 'gw_smstext']));
        if (!($smstext_field['name'] ?? null)) break;

        $smstext = $_POST[$smstext_field['name']];

        $args = [
          'post_type' => 'gwapi-sms',
          'post_status' => 'publish',
          'meta_input' => [
            'recipient_groups' => $this->getGroupIdsFromTag($groups_field),
            'message' => $smstext,
            'recipients' => ['groups']
          ]
        ];
        wp_insert_post($args);
        break;
    }

    if ($action == 'unsubscribe') {
      wp_trash_post($curID);
    }
  }

  private function sendSubmitSmsReply(WPCF7_ContactForm $wpcf7, WPCF7_Submission $submission, $sms)
  {
    $country_code_field = current($wpcf7->scan_form_tags(['type' => 'gw_country']));
    $phone_field = current($wpcf7->scan_form_tags(['type' => 'gw_phone']));

    if (!$phone_field || !$country_code_field) return;
    if (!isset($sms['reply-body']) || !trim($sms['reply-body'])) return; // nothing to send

    $body = trim(wpcf7_mail_replace_tags($sms['reply-body']));
    $from = trim(wpcf7_mail_replace_tags($sms['reply-sender'])) ?: null;

    $phone = gwapi_get_msisdn($_POST[$country_code_field['name']], $_POST[$phone_field['name']]);
    gwapi_send_sms($body, $phone, $from);
  }

  public function tagGeneratePhone($contact_form, $args = '')
  {
    $args = wp_parse_args($args, array());
    $type = 'gw_phone';
    $description = "Generate the GatewayAPI field for phone number input.";
    ?>
    <div class="control-box">
      <fieldset>
        <legend><?= esc_html($description) ?></legend>

        <table class="form-table">
          <tbody>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-name'); ?>"><?php _e('Name attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input required type="text" name="name" class="tg-name oneline"
                       id="<?php echo esc_attr($args['content'] . '-name'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-id'); ?>"><?php _e('Id attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="id" class="idvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-id'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-class'); ?>"><?php _e('Class attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="class" class="classvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-class'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-default'); ?>"><?php _e('Default value', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="default" class="defaultvalue oneline option"
                       id="<?php echo esc_attr($args['default'] . '-default'); ?>"></td>
          </tr>
          </tbody>
        </table>
      </fieldset>
    </div>

    <div class="insert-box">
      <input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly"
             onfocus="this.select()"/>

      <div class="submitbox">
        <input type="button" class="button button-primary insert-tag"
               value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>"/>
      </div>

      <br class="clear"/>
    </div>
    <?php
  }

  public function tagGenerateCountry($contact_form, $args = '')
  {
    $args = wp_parse_args($args, array());
    $type = 'gw_country';
    $description = "Generate the GatewayAPI country code selector.";
    ?>
    <div class="control-box">
      <fieldset>
        <legend><?= esc_html(__($description, 'gatewayapi')) ?></legend>

        <table class="form-table">
          <tbody>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-name'); ?>"><?php _e('Name attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input required type="text" name="name" class="tg-name oneline"
                       id="<?php echo esc_attr($args['content'] . '-name'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-groups'); ?>"><?php _e('Limit countries', 'gatewayapi'); ?></label>
            </th>
            <td>
              <input type="text" name="values" class="oneline"
                     id="<?php echo esc_attr($args['content'] . '-onlycc'); ?>">
              <p
                class="description"><?php _e('Enter all calling codes allowed, separated by space. Leave empty to allow all. See the <a href="https://countrycode.org/" target="_blank">list of calling codes</a>.', 'gatewayapi'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-id'); ?>"><?php _e('Id attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="id" class="idvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-id'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-class'); ?>"><?php _e('Class attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="class" class="classvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-class'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-default'); ?>"><?php _e('Default country code', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="default" class="defaultvalue oneline option"
                       id="<?php echo esc_attr($args['default'] . '-default'); ?>"></td>
          </tr>
          </tbody>
        </table>
      </fieldset>
    </div>

    <div class="insert-box">
      <input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly"
             onfocus="this.select()"/>

      <div class="submitbox">
        <input type="button" class="button button-primary insert-tag"
               value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>"/>
      </div>

      <br class="clear"/>
    </div>
    <?php
  }

  /**
   * TAG EDITOR FOR "GROUPS"
   * ========================
   * Ie. what groups to signup/update.
   */
  public function tagGenerateGroups($contact_form, $args = '')
  {
    $args = wp_parse_args($args, array());
    $type = 'gw_groups';
    $description = "Generate the GatewayAPI Groups selection. Select which groups to sign the recipient up to or make it possible for the recipients to select themselves.";
    ?>
    <script>
      (function () {
        if (window.gwapiUpdateGroupsInput) return;
        window.gwapiUpdateGroupsInput = function (scope) {
          var $ = jQuery;

          var ids = [];
          var outer = $(scope).closest('div');
          outer.find('input[type=checkbox]:checked').each(function () {
            ids.push($(this).val())
          });
          outer.find('input[type=text]').val(ids.join(' '));
        };
      })();
    </script>
    <div class="control-box">
      <fieldset>
        <legend><?= esc_html(__($description, 'gatewayapi')) ?></legend>

        <table class="form-table">
          <tbody>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-name'); ?>"><?php _e('Name attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input required type="text" name="name" class="tg-name oneline"
                       id="<?php echo esc_attr($args['content'] . '-name'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-groups'); ?>"><?php _e('Pick groups', 'gatewayapi'); ?></label>
            </th>
            <td>
              <div style="width: 100%; max-height: 100px; overflow: auto;">
                <?php $terms = get_terms('gwapi-recipient-groups', ['hide_empty' => false]); ?>
                <?php foreach ($terms as $t): ?>
                  <label style="display: block; margin-top: 3px; margin-bottom: 3px;"><input
                      onchange="gwapiUpdateGroupsInput(this)" type="checkbox"
                      value="<?= $t->term_id; ?>" class="group_ids"> <?= $t->name; ?></label>
                <?php endforeach; ?>
                <input style="display: none" name="values" class="oneline" type="text"
                       id="<?php echo esc_attr($args['content'] . '-values'); ?>"/>
              </div>
            </td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-allow-select'); ?>"><?php _e('Hidden field', 'gatewayapi'); ?></label>
            </th>
            <td>
              <label>
                <input type="checkbox" name="hidden" class="option"
                       id="<?php echo esc_attr($args['content'] . '-class'); ?>"/>
                <?php _e('Hide this field. Recipients will be subscribed to all of the selected groups.', 'gatewayapi'); ?>
              </label>
            </td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-id'); ?>"><?php _e('Id attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="id" class="idvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-id'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-class'); ?>"><?php _e('Class attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input type="text" name="class" class="classvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-class'); ?>"></td>
          </tr>
          </tbody>
        </table>
      </fieldset>
    </div>

    <div class="insert-box">
      <input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly"
             onfocus="this.select()"/>

      <div class="submitbox">
        <input type="button" class="button button-primary insert-tag"
               value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>"/>
      </div>

      <br class="clear"/>
    </div>
    <?php
  }

  /**
   * TAG EDITOR FOR "ACTIONS"
   * ========================
   * Ie. configure what action to perform on form submit.
   */
  public function tagGenerateAction($contact_form, $args = '')
  {
    $args = wp_parse_args($args, array());
    $type = 'gw_action';
    $description = "Generate the GatewayAPI Action field. This field instructs GatewayAPI on what to do with the submission.";
    ?>
    <div class="control-box">
      <fieldset>
        <legend><?= esc_html(__($description, 'gatewayapi')) ?></legend>

        <table class="form-table">
          <tbody>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-name'); ?>"><?php _e('Name attribute', 'gatewayapi'); ?></label>
            </th>
            <td><input required type="text" name="name" class="tg-name oneline"
                       id="<?php echo esc_attr($args['content'] . '-name'); ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-action'); ?>"><?php _e('Triggered action', 'gatewayapi'); ?></label>
            </th>
            <td>
              <label><input type="radio" checked name="action" class="option" value="signup"
                            id="<?php echo esc_attr($args['content'] . '-action-signup'); ?>"> <?php _e('Signup', 'gatewayapi'); ?>
              </label>
              <p class="description"
                 style="margin-top: 0; margin-bottom: 10px"><?php _e('Sign up a new subscriber. If a recipient with same phone number exists, the signup will fail.', 'gatewayapi'); ?></p>

              <label><input type="radio" name="action" class="option"
                            value="unsubscribe"> <?php _e('Unsubscribe', 'gatewayapi'); ?></label>
              <p class="description"
                 style="margin-top: 0; margin-bottom: 10px"><?php _e('Unsubscribe a subscriber, ie. move the recipient to the trash.', 'gatewayapi'); ?></p>

              <label><input type="radio" name="action" class="option"
                            value="update"> <?php _e('Update', 'gatewayapi'); ?></label>
              <p class="description"
                 style="margin-top: 0;"><?php _e('Update an existing subscriber, ie. a recipient with the given phone number must already exist.', 'gatewayapi'); ?></p>

              <label><input type="radio" name="action" class="option"
                            value="sms"> <?php _e('Send SMS', 'gatewayapi'); ?></label>
              <p class="description"
                 style="margin-top: 0;"><?php _e('Send SMS from the frontend. <strong>Warning:</strong> anyone with access to a page with this on, can send SMS\'es on your behalf!', 'gatewayapi'); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="<?php echo esc_attr($args['content'] . '-verify'); ?>"><?php _e('Require verification', 'gatewayapi'); ?></label>
            </th>
            <td>
              <label>
                <input type="radio" checked name="verify" class="option"
                       id="<?php echo esc_attr($args['content'] . '-verify'); ?>" value="yes"/>
                <?php _e('Yes (recommended)', 'gatewayapi'); ?>
              </label>
              <label>
                <input type="radio" name="verify" class="option"
                       id="<?php echo esc_attr($args['content'] . '-verify'); ?>" value="no"/>
                <?php _e('No', 'gatewayapi'); ?>
              </label>
              <p class="description">
                <?php _e('An SMS will be sent to confirm the ownership of the number with a one-time verification code.', 'gatewayapi'); ?>
                <br/>
                <?php _e('When updating, this triggers another flow: The user will have to enter mobile and country code, verify the number and only then will the rest of the form be presented.', 'gatewayapi'); ?>
              </p>
            </td>
          </tr>
          </tbody>
        </table>
      </fieldset>
    </div>

    <div class="insert-box">
      <input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly"
             onfocus="this.select()"/>

      <div class="submitbox">
        <input type="button" class="button button-primary insert-tag"
               value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>"/>
      </div>

      <br class="clear"/>
    </div>
    <?php
  }

  public function tagGenerateSmstext($contact_form, $args = '')
  {
    $args = wp_parse_args($args, array());
    $type = 'gw_smstext';

    ?>
    <div class="control-box">
      <fieldset>
        <legend><?php _e('SMS text field. This makes it possible to write an SMS message intended for sending.') ?></legend>

        <table class="form-table">
          <tbody>

          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-name'); ?>"><?php echo esc_html(__('Name', 'contact-form-7')); ?></label>
            </th>
            <td><input type="text" name="name" class="tg-name oneline"
                       id="<?php echo esc_attr($args['content'] . '-name'); ?>"/></td>
          </tr>

          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-values'); ?>"><?php echo esc_html(__('Placeholder', 'gatewayapi')); ?></label>
            </th>
            <td><input type="text" name="values" class="oneline"
                       id="<?php echo esc_attr($args['content'] . '-values'); ?>"/>
          </tr>

          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-id'); ?>"><?php echo esc_html(__('Id attribute', 'contact-form-7')); ?></label>
            </th>
            <td><input type="text" name="id" class="idvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-id'); ?>"/></td>
          </tr>

          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-class'); ?>"><?php echo esc_html(__('Class attribute', 'contact-form-7')); ?></label>
            </th>
            <td><input type="text" name="class" class="classvalue oneline option"
                       id="<?php echo esc_attr($args['content'] . '-class'); ?>"/></td>
          </tr>

          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-counter'); ?>"><?php echo esc_html(__('Enable counter', 'gatewayapi')); ?></label>
            </th>
            <td>
              <label><input type="checkbox" name="counter" class="option"
                            checked/> <?php echo esc_html(__('Displays how many SMS\'es this message spans as you type it.', 'gatewayapi')); ?>
              </label></td>
            </td>
          </tr>

          <tr>
            <th scope="row"><label
                for="<?php echo esc_attr($args['content'] . '-tags'); ?>"><?php echo esc_html(__('Link to tags', 'gatewayapi')); ?></label>
            </th>
            <td>
              <label><input type="checkbox" name="tags" class="option"
                            checked/> <?php echo esc_html(__('Links to a table of possible SMS-tags (opens in popup).', 'gatewayapi')); ?>
              </label></td>
            </td>
          </tr>

          </tbody>
        </table>
      </fieldset>
    </div>

    <div class="insert-box">
      <input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly"
             onfocus="this.select()"/>

      <div class="submitbox">
        <input type="button" class="button button-primary insert-tag"
               value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>"/>
      </div>

      <br class="clear"/>

      <p class="description mail-tag"><label
          for="<?php echo esc_attr($args['content'] . '-mailtag'); ?>"><?php echo sprintf(esc_html(__("To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.", 'contact-form-7')), '<strong><span class="mail-tag"></span></strong>'); ?>
          <input type="text" class="mail-tag code hidden" readonly="readonly"
                 id="<?php echo esc_attr($args['content'] . '-mailtag'); ?>"/></label></p>
    </div>
    <?php
  }

  /**
   * Get the default value for a field.
   *
   * @param $opt
   * @param $name
   * @return mixed|null|string
   */
  public function getFieldDefaultValue($opt, $name)
  {
    if ('user_' == substr($opt, 0, 5) && is_user_logged_in()) {
      $primary_props = array('user_login', 'user_email', 'user_url');
      $opt = in_array($opt, $primary_props) ? $opt : substr($opt, 5);

      $user = wp_get_current_user();
      $user_prop = $user->get($opt);

      if (!empty($user_prop)) {
        return $user_prop;
      }

    } elseif ('post_meta' == $opt && in_the_loop()) {
      $val = (string)get_post_meta(get_the_ID(), $name, true);

      if (strlen($val)) {
        return $val;
      }
    } elseif ('get' == $opt && isset($_GET[$name])) {
      $val = $_GET[$name] ?? null;

      if (strlen($val)) {
        return $val;
      }
    } elseif ('post' == $opt && isset($_POST[$name])) {
      $val = isset($_POST[$name]) ? $_POST[$name] : null;

      if (strlen($val)) {
        return $val;
      }
    } else {
      return $opt;
    }
  }

  public function handlePhone($contact_form)
  {
    $classes = ['gwapi-phone', 'wpcf7-form-control-wrap', str_replace(':', '', $contact_form['name'])];
    $group_ids = [];
    $field_id = $contact_form['name'] ? substr($contact_form['name'], 3) : null;
    $default_field = '';

    foreach ($contact_form['options'] as $opt) {
      if (strpos($opt, 'class:') === 0) $classes[] = substr($opt, strpos($opt, ':') + 1);
      if (ctype_digit($opt)) $group_ids[] = (int)$opt;
      if (strpos($opt, 'default:') === 0) $default_field = substr($opt, strpos($opt, ':') + 1);
    }

    ob_start();

    $default = $this->getFieldDefaultValue($default_field, 'gwapi_country');
    ?>
    <span class="<?= implode(' ', $classes); ?>">
			<input type="tel" data-gwapi="phone" name="<?= $contact_form['name']; ?>"
             id="<?= $field_id ? 'id="' . $field_id . '"' : ''; ?>"
             onkeyup="this.value = this.value.replace(/\D+/,'')"
             onchange="this.value = this.value.replace(/\D+/,'')" value="<?= esc_attr($default); ?>">
		</span>
    <?php

    return ob_get_clean();
  }

  public function handleCountry($contact_form)
  {
    $classes = ['gwapi-country', 'wpcf7-form-control-wrap', str_replace(':', '', $contact_form['name'])];
    $field_id = $contact_form['name'] ? substr($contact_form['name'], 3) : null;
    $default_field = '';
    foreach ($contact_form['options'] as $opt) {
      if (strpos($opt, 'class:') === 0) $classes[] = substr($opt, strpos($opt, ':') + 1);
      if (strpos($opt, 'default:') === 0) $default_field = substr($opt, strpos($opt, ':') + 1);
    }
    $country_codes = explode(' ', str_replace(',', ' ', current($contact_form['values'])));

    // list of country codes
    $all_country_codes = json_decode(file_get_contents(_gwapi_dir() . '/lib/countries/countries.min.json'));
    $out_country_codes = [];

    foreach ($all_country_codes->countries as $c) {
      if ($c->phone && (!$country_codes || in_array($c->phone, $country_codes))) $out_country_codes[] = $c;
    }

    // sort alfabetically
    usort($out_country_codes, function ($a, $b) {
      return $a->name === $b->name ? 0 : ($a->name > $b->name ? 1 : -1);
    });

    $default = $this->getFieldDefaultValue($default_field, 'gwapi_country');

    // just a single country code? then hide the field/pre-select this one valid option
    ob_start();
    if (count($country_codes) === 1):
      ?>
      <span class="<?= implode(' ', $classes); ?>">
				<input type="hidden" data-gwapi="country" name="<?= $contact_form['name']; ?>"
               value="<?= current($country_codes); ?>" <?= $field_id ? 'id="' . $field_id . '"' : ''; ?>>
			</span>
    <?php
    else:
      ?>
      <span class="<?= implode(' ', $classes); ?>">
					<select data-gwapi="country"
                  name="<?= $contact_form['name']; ?>" <?= $field_id ? 'id="' . $field_id . '"' : ''; ?> <?= ($default ? 'value="' . esc_attr($default) . '"' : ''); ?>>
						<?php foreach ($out_country_codes as $c): ?>
              <option value="<?= $c->phone; ?>" <?= $default == $c->phone ? 'selected' : '' ?>><?= $c->name ?>
                                (+<?= $c->phone; ?>)</option>
            <?php endforeach; ?>
					</select>
				</span>
    <?php
    endif;

    return ob_get_clean();
  }

  public function handleGroups($contact_form)
  {
    $classes = ['gwapi-groups', 'wpcf7-form-control-wrap', str_replace(':', '', $contact_form['name'])];
    $is_hidden = false;

    foreach ($contact_form['options'] as $opt) {
      if (strpos($opt, 'class:') === 0) $classes[] = substr($opt, strpos($opt, ':') + 1);
      if ($opt === 'hidden') $is_hidden = true;
    }

    $group_ids = $this->getGroupIdsFromTag($contact_form);

    if (!$group_ids) return ''; // nothing to do

    // fetch the groups
    $groups = get_terms('gwapi-recipient-groups', ['hide_empty' => false, 'include' => $group_ids]);

    ob_start();
    if (!$is_hidden):
      ?>
      <div class="<?= implode(' ', $classes); ?>">
        <?php foreach ($groups as $g): ?>
          <label style="margin-bottom: 5px; display: block;"><input type="checkbox" data-gwapi="groups"
                                                                    name="<?= $contact_form['name']; ?>[]"
                                                                    value="<?= $g->term_id; ?>"> <?= esc_html($g->name); ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php
    else:
      foreach ($groups as $g): ?>
        <span class="<?= implode(' ', $classes); ?>">
				<input type="hidden" data-gwapi="groups" name="<?= $contact_form['name']; ?>[]"
               value="<?= $g->term_id; ?>">
			</span>
      <?php endforeach;
    endif;

    return ob_get_clean();
  }

  public function handleSmstext($tag)
  {
    $tag = new WPCF7_FormTag($tag);
    $id = $tag->get_id_option();
    $classes = ['gwapi-smstext', 'wpcf7-form-control-wrap', $tag->name, $tag->get_class_option()];

    $has_counter = in_array('counter', $tag->options);
    $has_tags = in_array('tags', $tag->options);
    $placeholder = current($tag->labels);

    $extras_style = 'float: right; opacity: 0.8; margin-left: 20px; padding-top: 5px; padding-bottom: 5px; font-size: 0.9em;';

    $counterI18N = [
      'character' => __('character', 'gatewayapi'),
      'characters' => __('characters', 'gatewayapi'),
      'sms' => __('SMS', 'gatewayapi'),
      'smses' => __('SMS\'es', 'gatewayapi')
    ];

    ob_start();
    ?>
    <div <?= $id ? 'id="' . $id . '"' : ''; ?>>
            <span class="<?= implode(' ', $classes); ?>">
                <textarea name="<?= $tag->name; ?>" cols="40" rows="10" placeholder="<?= esc_attr($placeholder); ?>"
                          required></textarea>
            </span>

      <?php if ($has_tags): ?>
        <div class="gwapi-tags" style="<?= $extras_style; ?>">
          <a href="#gwapi-tags"
             data-tags="<?= esc_attr(json_encode(gwapi_all_tags())); ?>"><?php _e('Show tags', 'gatewayapi'); ?></a>
        </div>
      <?php endif; ?>

      <?php if ($has_counter): ?>
        <div class="gwapi-counter" data-i18n="<?= esc_attr(json_encode($counterI18N)); ?>"
             style="<?= $extras_style; ?>">...
        </div>
      <?php endif; ?>

      <div style="clear: both; height: 1px; margin-top: -1px;"></div> <!-- clearfix -->
    </div>
    <?php

    wp_enqueue_script('gwapi_integration_contact_form_7_frontend', _gwapi_url() . 'js/integration_contact_form_7_frontend.js', ['jquery'], 1);

    return ob_get_clean();
  }


  /**
   * Validate groups entry.
   *
   * @param WPCF7_Validation $res
   * @param $tag
   *
   * @return WPCF7_Validation
   */
  public function validateGroups(WPCF7_Validation $res, $tag)
  {
    $tag = new WPCF7_FormTag($tag);
    $groupsPossible = $this->getGroupIdsFromTag($tag);
    $groupsPossible = array_unique($groupsPossible);
    $groupsSelected = isset($_POST[$tag->name]) ? array_unique($_POST[$tag->name]) : [];

    if ($this->fieldIsEmptyAndRequired($tag)) {
      $res->invalidate($tag, wpcf7_get_message('invalid_required'));
    }

    // if NOT hidden, then check:
    // are the groups selected within the list of possible groups?
    if (!in_array('hidden', $tag->options)) {
      // iterate the posted groups - the groups posted must all be in the list of valid groups
      foreach ($groupsSelected as $groupID) {
        if (!in_array($groupID, $groupsPossible)) {
          $res->invalidate($tag, __('One of the selected groups is invalid/should not be selectable. This should not happen, but may occur if the editor of this site has changed the settings for this form since you opened this page.', 'gatewayapi'));
        }
      }
    } else { // if IS hidden: ALL groups should have been submitted
      if (count($groupsPossible) != count($groupsSelected)) {
        $res->invalidate($tag, __('One of the selected groups is invalid/should not be selectable. This should not happen, but may occur if the editor of this site has changed the settings for this form since you opened this page.', 'gatewayapi'));
      }
    }

    return $res;
  }

  /**
   * Validate phone number.
   *
   * @param WPCF7_Validation $res
   * @param $tag
   *
   * @return WPCF7_Validation
   */
  public function validatePhone(WPCF7_Validation $res, $tag)
  {
    $cf = WPCF7_ContactForm::get_current();
    /** @var $cf WPCF7_ContactForm */
    $action_field = current($cf->scan_form_tags(['type' => 'gw_action']));
    $cc_field = current($cf->scan_form_tags(['type' => 'gw_country']));
    $local_field = current($cf->scan_form_tags(['type' => 'gw_phone']));

    $tag = new WPCF7_FormTag($tag);
    if ($this->fieldIsEmptyAndRequired($tag)) {
      $res->invalidate($tag, wpcf7_get_message('invalid_required'));
      return $res;
    }
    $name = $tag->name;
    $phone = $_POST[$name] ?? null;
    if (!$phone || !ctype_digit($phone)) {
      $res->invalidate($tag, __('The phone number must consist of digits only.', 'gatewayapi'));
      return $res;
    }

    // invalid: this form has no gwapi action, so nothing should occur
    if (!($action_field['name'] ?? null)) return $res;
    $action = $_POST[$action_field['name']] ?? null;
    if (!$action) return $res;

    $phone_exists = null;
    $cc = isset($_POST[$cc_field['name']]) ? $_POST[$cc_field['name']] : null;
    if ($cc) {
      $q = new WP_Query(["post_type" => "gwapi-recipient", "meta_query" => [['key' => 'cc', 'value' => $cc], ['key' => 'number', 'value' => $phone]]]);
      $phone_exists = $q->have_posts();
    }

    // signup: does the phone number already exist?
    if ($action == 'signup' && $phone_exists === true) {
      $res->invalidate($tag, __('You are already subscribed with this phone number.', 'gatewayapi'));
    }

    // unsubscribe or update: does the phone number already exist?
    if (in_array($action, ['unsubscribe', 'update']) && $phone_exists === false) {
      $res->invalidate($tag, __('You are not subscribed with this phone number.', 'gatewayapi'));
    }

    return $res;
  }

  /**
   * Validate country code.
   *
   * @param WPCF7_Validation $res
   * @param $tag
   *
   * @return WPCF7_Validation
   */
  public function validateCountry(WPCF7_Validation $res, $tag)
  {
    $cf = WPCF7_ContactForm::get_current();
    /** @var $cf WPCF7_ContactForm */
    $cc_field = current($cf->scan_form_tags(['type' => 'gw_country']));

    $tag = new WPCF7_FormTag($tag);

    $cc = isset($_POST[$cc_field['name']]) ? $_POST[$cc_field['name']] : null;

    // do we have a list of country codes to limit from?
    $valid_country_codes = explode(' ', str_replace(',', ' ', $tag->values[0]));

    // no valid country codes? then ALL country codes are valid - load list of valid country codes
    if (!$valid_country_codes) {
      $all_country_codes = json_decode(file_get_contents(_gwapi_dir() . '/lib/countries/countries.min.json'));

      foreach ($all_country_codes->countries as $c) {
        if ($c->phone) $valid_country_codes[] = $c->phone;
      }
    }

    // is the country code entered, within the list of valid country codes?
    if (!in_array($cc, $valid_country_codes)) {
      $res->invalidate($tag, __('The phone country code selected, is not within the list of valid country codes.', 'gatewayapi'));
    }

    return $res;
  }

  /**
   * Validate GatewayAPI Action.
   *
   * @param WPCF7_Validation $res
   * @param $tag
   *
   * @return WPCF7_Validation
   */
  public function validateAction(WPCF7_Validation $res, $tag)
  {
    $cf = WPCF7_ContactForm::get_current();
    /** @var $cf WPCF7_ContactForm */
    $action_field = current($cf->scan_form_tags(['type' => 'gw_action']));
    $cc_field = current($cf->scan_form_tags(['type' => 'gw_country']));
    $local_field = current($cf->scan_form_tags(['type' => 'gw_phone']));

    $phone = isset($_POST[$local_field['name']]) ? $_POST[$local_field['name']] : null;
    $cc = isset($_POST[$cc_field['name']]) ? $_POST[$cc_field['name']] : null;

    $tag = new WPCF7_FormTag($tag);

    // the action selected must be within the list of valid actions
    $action = isset($_POST[$action_field['name']]) ? $_POST[$action_field['name']] : '';

    // update action + verification
    if ($action === 'update' && in_array('verify:yes', $tag->options)) {
      $code = get_transient('gwapi_verify_' . gwapi_get_msisdn($cc, $phone));
      if ($code != $_POST['_gwapi_token']) {
        $res->invalidate($tag, __('It doesn\'t seem that you have verified your number by SMS, or the verification has expired. Note that you must submit the form within 30 minutes after validating.', 'gatewayapi'));
      }
    }

    // signup action + verification
    if ($action === 'signup' && in_array('verify:yes', $tag->options)) {
      $msisdn = gwapi_get_msisdn($cc, $phone);
      $code = get_transient("gwapi_verify_signup_" . $msisdn);
      if (isset($_POST['_gwapi_verify_signup'])) {
        if ($code && $code != preg_replace('/\D+/', '', $_POST['_gwapi_verify_signup'])) {
          $res->invalidate($tag, __("The verification code that you entered, was incorrect.", 'gatewayapi'));
        } else if (!$code) {
          $res->invalidate($tag, __("The verification code has expired. You have just 5 minutes to enter the code. Please try again.", 'gatewayapi'));
        }
      }
    }

    return $res;
  }

  public function validateSmstext(WPCF7_Validation $res, $tag)
  {
    $tag = new WPCF7_FormTag($tag);

    $wpcf7 = WPCF7_ContactForm::get_current();
    /** @var $wpcf7 WPCF7_ContactForm */
    $submission = WPCF7_Submission::get_instance();
    $data = $submission->get_posted_data();

    $action_field = current($wpcf7->scan_form_tags(['type' => 'gw_action']));
    if (!$action_field) return $res; // not relevant, no action in form

    // skip if this is not an SMS action
    if (!in_array('action:sms', $action_field['options'])) {
      return $res;
    }

    // rules:
    // - sms text may not be empty
    // - there must be a groups field
    // - at least one group must be selected
    // - there must be at least one recipient in the selected groups
    if (!isset($data[$tag->name]) || !trim($data[$tag->name])) {
      $res->invalidate($tag, __('The SMS text may not be empty.'));
      return $res;
    }

    $groups_field = current($wpcf7->scan_form_tags(['type' => 'gw_groups']));
    if (!$groups_field) {
      $res->invalidate($tag, __('You must have a groups field in this form (it may be hidden) in order to select recipients.', 'gatewayapi'));
      return $res;
    }

    // if groups field is hidden, take values from the field
    $groups = $this->getGroupIdsFromTag($groups_field);

    if (!$groups) {
      $res->invalidate($tag, __('At least one group must be selected in order to send an SMS.', 'gatewayapi'));
      return $res;
    }

    // cast to int
    foreach ($groups as &$group) {
      $group = (int)$group;
    }

    // are there any recipients in these groups?
    $countQ = new WP_Query([
      "post_type" => "gwapi-recipient",
      "tax_query" => [[
        'taxonomy' => 'gwapi-recipient-groups',
        'terms' => $groups
      ]],
      "posts_per_page" => 1,
      "fields" => "ids"
    ]);
    if (!$countQ->posts) {
      $res->invalidate($tag, __('There are no recipients in the groups you have selected. The SMS cannot be sent to 0 recipients.', 'gatewayapi'));
    }

    return $res;
  }

  public function handleAction($contact_form)
  {
    $classes = ['gwapi-action', 'wpcf7-form-control-wrap', str_replace(':', '', $contact_form['name'])];
    $with_verify = in_array('verify:yes', $contact_form['options']);

    $action = '';
    $possible_actions = array_merge([$contact_form['name']], $contact_form['options']);
    foreach ($possible_actions as $possible_action) {
      if (strpos($possible_action, 'action:') === 0) { // legacy approach!
        $action = substr($possible_action, strpos($possible_action, ':') + 1);
      }
    }

    ob_start();
    ?>
    <span class="<?= implode(' ', $classes); ?>">
			<input type="hidden" <?= $with_verify ? 'data-verify="true"' : ''; ?> data-gwapi="action"
             name="<?= $contact_form['name']; ?>" value="<?= $action; ?>">
		</span>

    <?php
    if (in_array('verify:yes', $contact_form['options'])) {
      $this->enqueueContactform7Js();
      ?>
      <script>
        var gwapi_admin_ajax = <?= json_encode(admin_url('admin-ajax.php')); ?>;
      </script>
      <?php
    }

    return ob_get_clean();
  }

  /**
   * Send a verification SMS for the update/signup/unsubscribe form.
   */
  public function sendVerifySms()
  {
    header("Content-type: application/json");

    $cc = $_POST['cc'] ?? null;
    $number = $_POST['number'] ?? null;
    if (!$cc || !$number || !ctype_digit($cc) || !ctype_digit($number)) die(json_encode(['success' => false, 'message' => 'You must supply both country code and phone number.']));

    $phone = gwapi_get_msisdn($cc, $number);

    // prevent abuse
    $very_close = get_transient('gwapi_notify1_' . $phone);
    $same_day = get_transient('gwapi_notify2_' . $phone) ?: 0;
    if ($very_close) die(json_encode(['success' => false, 'message' => 'You have very recently requested a verification SMS. To prevent abuse, your request has been blocked. Try again in a couple of minutes.']));
    if ($same_day > 2) die(json_encode(['success' => false, 'message' => 'You have requested verification SMS\'es too many times during the last 24 hours. To prevent abuse, your request has been blocked.']));
    set_transient('gwapi_notify1_' . $phone, 1, 60);
    set_transient('gwapi_notify2_' . $phone, $same_day + 1, 60 * 60 * 24);

    // save + send verification SMS
    $code = rand(100000, 999999);
    set_transient('gwapi_verify_' . $phone, $code, 60 * 30);

    gwapi_send_sms(__("Your verification code:", 'gatewayapi') . $code, $phone);

    die(json_encode(['success' => true]));
  }

  /**
   * Verify an SMS code.
   */
  public function verifySms()
  {
    header("Content-type: application/json");

    $cc = $_POST['cc'] ?? null;
    $number = $_POST['number'] ?? null;
    if (!$cc || !$number || !ctype_digit($cc) || !ctype_digit($number)) die(json_encode(['success' => false, 'message' => 'You must supply both country code and phone number.']));

    $postCode = $_POST['code'] ?? null;

    // valid?
    if (!$postCode) {
      die(json_encode(['success' => false, 'message' => __('You must supply both country code and phone number.', 'gatewayapi')]));
    }

    // prevent abuse
    $this_phone = get_transient('gwapi_verify1_' . $cc . $number) ?: 0;
    if ($this_phone > 10) die(json_encode(['success' => false, 'message' => __('Due to too many attempts at verifying SMS-codes within a short period of time, your request has been blocked. Try again later.', 'gatewayapi')]));
    set_transient('gwapi_verify1_' . $cc . $number, $this_phone + 1, 60 * 60 * 4);

    // check if the code is valid
    $code = get_transient('gwapi_verify_' . $cc . $number);
    if (!$code) die(json_encode(['success' => false, 'message' => __('There is no verification going on for this phone number. Perhaps you waited too long? These codes expire after 30 minutes.', 'gatewayapi')]));
    if ($code != $postCode) die(json_encode(['success' => false, 'message' => __('The code is invalid. Please try again.', 'gatewayapi')]));

    // find the recipient, if there is any, and return all information
    $q = new WP_Query(["post_type" => "gwapi-recipient", "meta_query" => [['key' => 'cc', 'value' => $cc], ['key' => 'number', 'value' => $number]]]);
    $recipient = null;
    if ($q->have_posts()) {
      $recipient = [
        'gwapi_phone' => $number,
        'gwapi_country' => $cc,
        'gwapi_groups' => []
      ];
      $terms = wp_get_object_terms($q->post->ID, 'gwapi-recipient-groups');

      foreach ($terms as $t) {
        $recipient['gwapi_groups'][] = $t->term_id;
      }
      foreach (get_post_meta($q->post->ID) as $key => $val) {
        $recipient[$key] = current($val);
      }
    }
    die(json_encode(['success' => true, 'recipient' => $recipient]));
  }

  private function enqueueContactform7Js()
  {
    static $first_call = false;
    if ($first_call) return;
    $first_call = true;

    wp_enqueue_script('gwapi_integration_contact_form_7', _gwapi_url() . 'js/integration_contact_form_7.js', ['jquery'], 2);
    wp_localize_script('gwapi_integration_contact_form_7', 'i18n_gwapi_cf7', [
      'country_and_cc' => __('You must supply both country code and phone number in order to continue.', 'gatewayapi'),
      'verification_sms_sent' => __("We have just sent you an SMS with a verification code. Please enter it below:", 'gatewayapi'),
      'no_code_entered' => __("You did not enter a code. It is not possible for you to continue.", 'gatewayapi'),
      'bad_code' => __("You did not enter the code correctly. Please try again.", 'gatewayapi'),
      'no_code_try_again' => __('Not entering the code will cancel the signup. Clicking OK will allow you to try and enter the code again.', 'gatewayapi')
    ]);
  }

  private function fieldIsEmptyAndRequired($tag)
  {
    $name = $tag->name;
    $empty = !isset($_POST[$name]) || empty($_POST[$name]);
    return $empty && $tag->is_required();
  }
}
