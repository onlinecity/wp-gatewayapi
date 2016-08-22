<?php
/**
 * SMS Editor
 */
add_action('admin_init', function () {
    add_meta_box('sms-recipients', __('Recipients', 'gwapi'), '_gwapi_sms_recipients', 'gwapi-sms', 'normal', 'default');
    add_meta_box('sms-recipient-groups', __('Recipient groups', 'gwapi'), '_gwapi_sms_recipient_groups', 'gwapi-sms', 'normal', 'default');
    add_meta_box('sms-recipient-manual', __('Enter mobiles manually', 'gwapi'), '_gwapi_sms_recipient_manual', 'gwapi-sms', 'normal', 'default');
    add_meta_box('sms-message', __('Message', 'gwapi'), '_gwapi_sms_message', 'gwapi-sms', 'normal', 'default');

    add_meta_box('sms-status', __('Status', 'gwapi'), '_gwapi_sms_status', 'gwapi-sms', 'side', 'default');
});

/**
 * SMS Editor Block: Recipients (pick sources of recipients)
 */
function _gwapi_sms_recipients(WP_Post $post)
{
    $current_types = get_post_meta($post->ID, 'recipients', true);
    ?>
    <p><?php _e('How would you like to select recipients for this SMS?', 'gwapi'); ?></p>

    <div class="recipient-types"
         data-selected_types="<?= $current_types ? esc_attr(json_encode($current_types)) : ''; ?>">
        <label class="gwapi-checkbox">
            <input type="checkbox" name="gwapi[recipients][]" value="groups" data-group="sms-recipient-groups">
            <?php _e('Use recipient groups', 'gwapi'); ?>
        </label>

        <label class="gwapi-checkbox">
            <input type="checkbox" name="gwapi[recipients][]" value="manual" data-group="sms-recipient-manual">
            <?php _e('Enter mobile numbers manually', 'gwapi'); ?>
        </label>
    </div>

    <p class="description"><?php _e('Only one message will be sent per mobile number per SMS. So don\'t be afraid to mix and match the above.', 'gwapi'); ?></p>
    <?php
}

/**
 * SMS Editor Block: Recipient Groups
 */
function _gwapi_sms_recipient_groups(WP_Post $post)
{
    $groups = get_terms([
        'taxonomy' => 'gwapi-recipient-groups'
    ]);
    $current_groups = get_post_meta($post->ID, 'recipient_groups', true);
    ?>

    <div class="gwapi-row recipient-groups"
         data-selected_groups="<?= $current_groups ? esc_attr(json_encode($current_groups)) : ''; ?>">
        <div class="all-groups col-50">
            <h4><?php _e('All recipient groups', 'gwapi'); ?></h4>

            <div class="inner">
                <?php foreach ($groups as $group): ?>
                    <label class="gwapi-checkbox">
                        <input type="checkbox" name="gwapi[recipient_groups][]" id=""
                               value="<?= $group->term_id; ?>">
                        <?= $group->name; ?>
                        <span class="number"
                              title="<?php esc_attr_e('Recipients in group', 'gwapi') ?>: <?= $group->count; ?>"><?= $group->count; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="selected-groups col-50">
            <h4><?php _e('Selected recipient groups', 'gwapi'); ?></h4>
            <div class="inner"></div>
        </div>
    </div>

    <div class="footer">
        <p class="description"><?php _e('You will be sending to all recipients who are in any of the selected groups.', 'gwapi'); ?></p>
        <p class="description"><?php _e('Only groups with at least one recipient are listed.', 'gwapi'); ?></p>
    </div>

    <?php
}


/**
 * SMS Editor Block: Recipient by manual entering
 */
function _gwapi_sms_recipient_manual(WP_Post $post)
{
    $ID = $post->ID;
    $published = $post->post_status == 'publish';

    // get the manual recipients
    global $wpdb;
    /** @var $wpdb wpdb */
    $recipients_raw = $wpdb->get_results($wpdb->prepare("SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'single_recipient'", $ID));
    $recipients = [];

    foreach ($recipients_raw as $r) {
        $recipients[$r->meta_id] = maybe_unserialize($r->meta_value);
    }
    ?>

    <div class="gwapi-row">
        <div class="col-40">
            <h4><?php _e('Add recipient', 'gwapi'); ?></h4>
            <div class="gwapi-star-errors"></div>

            <div class="field-group">
                <label for="recipient_cc" class="control-label"><?php _e('Country code', 'gwapi') ?></label>
                <select name="gwapi[single_recipient][cc]" id="recipient_cc"></select>
            </div>

            <div class="field-group">
                <label for="recipient_number" class="control-label">
                    <?php _e('Phone number', 'gwapi') ?>
                </label>

                <input type="number" name="gwapi[single_recipient][number]"
                       placeholder="<?= esc_attr(__('Phone number - digits only', 'gwapi')); ?>"
                       id="recipient_number">
            </div>

            <div class="field-group">
                <label for="recipient_name" class="control-label"><?php _e('Name (optional)', 'gwapi'); ?></label>
                <input type="text" name="gwapi[single_recipient][name]" id="recipient_name">
            </div>

            <button type="button" class="button add_single_recipient"><?php _e('Add recipient', 'gwapi'); ?></button>

        </div>
        <div class="col-60">
            <h4><?php _e('Manually added recipients', 'gwapi'); ?></h4>
            <div class="inner">
                <table class="widefat">
                    <thead>
                    <tr>
                        <th width="10%"><a class="delete-btn"></a></th>
                        <th width="30%"><?php _e('Phone number', 'gwapi'); ?></th>
                        <th width="60%"><?php _e('Name', 'gwapi'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr class="empty_row" <?= $recipients ? 'style="display: none;"' : ''; ?>>
                        <td colspan="3">
                            <p class="description" style="text-align: center; margin-top: 10px;"><?php _e('No recipients manually added', 'gwapi'); ?></p>
                        </td>
                    </tr>
                    <?php foreach ($recipients as $ID => $r): ?>
                        <tr data-meta_id="<?= $ID; ?>">
                            <td><a href="#delete" class="delete-btn"></a></td>
                            <td>
                                +<?= $r['cc'] ?>
                                <?= $r['number'] ?>
                            </td>
                            <td>
                                <?= esc_html($r['name']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php
}


/**
 * SMS Editor Block: The messaging area
 */
function _gwapi_sms_message(WP_Post $post)
{
    $ID = $post->ID;
    $published = $post->post_status == 'publish';

    ?>
    <div class="gwapi-star-errors"></div>
    <table width="100%" class="form-table">
        <tbody>
        <tr>
            <th width="25%">
                <?php _e('Sender', 'gwapi'); ?>
            </th>
            <td>
                <input type="text" name="gwapi[sender]" size="15"
                       value="<?= esc_attr(get_post_meta($ID, 'sender', true)); ?>">
                <p class="description"><?php _e('The sender can be either 11 characters or 15 digits in total.', 'gwapi'); ?></p>
            </td>
        </tr>
        <tr>
            <th width="25%" class="vtop-5">
                <?php _e('Type of SMS', 'gwapi'); ?>
            </th>
            <td>
                <?php $destaddr = get_post_meta($ID, 'destaddr', true); ?>
                <label>
                    <input type="radio" name="gwapi[destaddr]"
                           value="MOBILE" <?= ($destaddr == 'MOBILE' || !$destaddr) ? 'checked' : ''; ?>>
                    <?php _e('Regular SMS', 'gwapi'); ?>
                </label>
                <br/>
                <label>
                    <input type="radio" name="gwapi[destaddr]"
                           value="DISPLAY"<?= $destaddr == 'DISPLAY' ? 'checked' : ''; ?>>
                    <abbr
                        title="<?php esc_attr_e('Message is displayed immediately and usually not saved in the normal message inbox. Also knows as a Flash SMS.', 'gwapi'); ?>"><?php _e('Display SMS', 'gwapi'); ?></abbr>
                </label>
            </td>
        </tr>
        <tr>
            <th>
                <?php _e('Message', 'gwapi') ?>
            </th>
            <td>
                <?php
                $counterI18N = [
                    'character' => __('character','gwapi'),
                    'characters' => __('characters','gwapi'),
                    'sms' => __('SMS','gwapi'),
                    'smses' => __('SMS\'es','gwapi')
                ];
                ?>
                <textarea <?= ($published) ? 'readonly' : ''; ?> name="gwapi[message]" rows="10" style="width: 100%"
                                                                 placeholder="<?= esc_attr(__('Enter your SMS message here.', 'gwapi')); ?>" data-counter-i18n="<?= esc_attr(json_encode($counterI18N)); ?>"><?= esc_attr(get_post_meta($ID, 'message', true)); ?></textarea>
                <div>
                    <p><?php _e('Writing one of the following tags (including both %-signs) will result in each recipient receiving a personalized text:', 'gwapi'); ?></p>
                    <ul>
                        <?php foreach(gwapi_all_tags() as $tag=>$description): ?>
                            <li>
                                <strong><?=esc_html($tag);?></strong> - <?= esc_html($description); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </td>
        </tr>
        </tbody>
    </table>
    <?php
}

function _gwapi_sms_status(WP_Post $post) {
    $status = get_post_meta($post->ID, 'api_status', true);
    $ids = get_post_meta($post->ID, 'api_ids', true);
    $error = get_post_meta($post->ID, 'api_error', true);
    ?>
    <p><?php _e('The current status of the SMS is:', 'gwapi'); ?></p>
    <p>
    <?php
    switch($status) {
        case 'about_to_send':
            echo __('About to send', 'gwapi');
            break;

        case 'sending':
            echo __('Sending...', 'gwapi');
            break;

        case 'bail':
            echo '<span style="color: red"><strong>'.__('Failed before sending:', 'gwapi').'</strong><br />'.$error.'</span>';
            break;

        case 'tech_error':
            $error_json = json_decode(json_decode(substr($error, 1, -1)));
            if ($error_json && isset($error_json->message)) {
                $error = $error_json->message;
            }
            echo '<span style="color: red"><strong>'.__('Technical error:', 'gwapi').'</strong><br />'.$error.'</span>';
            break;

        case 'is_sent':
            echo '<span style="color: green; font-weight: bold">'.__('SMS was successfully sent', 'gwapi').'</span><br />ID: '.$ids;
            break;

        default:
            _e('Not sent yet', 'gwapi');
            break;
    }
    ?>
    </p>

    <?php
}


/**
 * Pre-flight an SMS-message.
 */
add_action('wp_ajax_gatewayapi_validate_sms', function () {
    header("Content-type: application/json");

    $data = [];
    parse_str($_POST['form_data'], $data);
    $errors = _gwapi_validate_sms($data['gwapi']);

    if ($errors) {
        die(json_encode(['success' => false, 'failed' => $errors]));
    } else {
        die(json_encode(['success' => true]));
    }
});

/**
 * Save the SMS Meta data.
 */
function _gwapi_sms_edit_save($ID)
{
    static $call_more_than_once;
    if (isset($call_more_than_once) && $call_more_than_once) return;
    $call_more_than_once = true;

    if (!isset($_POST['gwapi']) || !$_POST['gwapi']) return;
    $data = $_POST['gwapi'];

    // sms meta data
    if (isset($data['sender']))   update_post_meta($ID, 'sender', $data['sender']);
    if (isset($data['message']))  update_post_meta($ID, 'message', $data['message']);
    if (isset($data['destaddr'])) update_post_meta($ID, 'destaddr', $data['destaddr']);

    // sources for recipients
    if (!isset($data['recipients'])) $data['recipients'] = [];
    update_post_meta($ID, 'recipients', $data['recipients']);

    // recipient groups
    if (in_array('groups', $data['recipients'])) {
        if (!isset($data['recipient_groups'])) $data['recipient_groups'] = [];
        foreach ($data['recipient_groups'] as &$id) {
            $id = (int)$id;
        }
        update_post_meta($ID, 'recipient_groups', $data['recipient_groups']);
    } else {
        delete_post_meta($ID, 'recipient_groups');
    }
}
add_action('save_post_gwapi-sms', '_gwapi_sms_edit_save');
add_action('publish_gwapi-sms', '_gwapi_sms_edit_save', 9);


/**
 * I18N: Rename "publish" to "send".
 */
add_action('current_screen', function ($current_screen) {
    if ($current_screen->post_type === 'gwapi-sms') {
        add_filter('gettext', function ($translated_text, $text, $domain) {
            if ($domain != 'default') return $translated_text;

            if ($text == 'Publish') return __('Send', 'gwapi');
            if ($text == 'Published on: <b>%1$s</b>') return __('Sent on: <b>%1$s</b>', 'gwapi');
            if ($text == 'Publish <b>immediately</b>') return __('Send <b>immediately</b>', 'gwapi');
            if ($text == 'Udgiv: <b>%1$s</b>') return __('Send on<b>%1$s</b>', 'gwapi');

            return $translated_text;
        }, 20, 3);
    }
});

/**
 * AJAX: Add a single recipient to an SMS.
 */
add_action('wp_ajax_gatewayapi_sms_manual_add_recipient', function () {
    header("Content-type: application/json");

    $post_ID = (int)$_POST['post_ID'];
    $post = get_post($post_ID);

    if (get_post_type($post_ID) != 'gwapi-sms') wp_die("WRONG_TYPE", "Wrong post type.");
    if (get_post_status($post_ID) == 'publish') die(json_encode(['success' => false, 'errors' => ['*' => 'This SMS has already been sent and thus it cannot be modified.']]));

    $cc = $_POST['gwapi']['single_recipient']['cc'];
    $number = $_POST['gwapi']['single_recipient']['number'];
    $name = $_POST['gwapi']['single_recipient']['name'];

    $data = ['cc' => $cc, 'number' => $number];

    $errors = [];
    $errors = _gwapi_validate_recipient_basic($errors, $data, $post);

    if ($errors) {
        die(json_encode(['success' => false, 'errors' => $errors]));
    }

    // is this recipient already added to this sms?
    foreach (get_post_meta($post_ID, 'single_recipient') as $sr) {
        if ($sr['cc'] == $cc && $sr['number'] == $number) {
            die(json_encode(['success' => false, 'errors' => ['*' => 'The same recipient (country code + phone number) has already been added to this SMS.']]));
        }
    };

    // save the recipient on the SMS
    $metaID = add_post_meta($post_ID, 'single_recipient', ['cc' => $cc, 'number' => $number, 'name' => $name]);

    die(json_encode(['success' => true, 'ID' => $metaID]));
});

/**
 * AJAX: Remove single recipient from SMS.
 */
add_action('wp_ajax_gatewayapi_sms_manual_delete_recipient', function () {
    header("Content-type: application/json");

    $post_ID = (int)$_POST['post_ID'];
    $post = get_post($post_ID);

    if (get_post_type($post_ID) != 'gwapi-sms') die(json_encode(['success' => false, 'message' => "Wrong post type."]));
    if (get_post_status($post_ID) == 'publish') die(json_encode(['success' => false, 'message' => 'This SMS has already been sent and thus it cannot be modified.']));

    global $wpdb; /** @var $wpdb wpdb */
    $wpdb->query($q = $wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_id = %d AND meta_key = 'single_recipient';", $post_ID, $_POST['meta_ID']));

    die(json_encode(['success' => true, $q]));
});