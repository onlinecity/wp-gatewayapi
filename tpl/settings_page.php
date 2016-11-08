<div class="wrap">
    <h2><?php _e('GatewayAPI Settings', 'gwapi'); ?></h2>

    <form method="post" action="options.php">
        <?php settings_fields('gwapi'); ?>
        <?php do_settings_sections('gwapi'); ?>


        <h2 class="nav-tab-wrapper">
            <a href="#base" class="nav-tab"><?php _e('General settings', 'gwapi'); ?></a>
            <a href="#recipients-fields" class="nav-tab hidden"><?php _e('Recipient fields', 'gwapi'); ?></a>
            <a href="#user-sync" class="nav-tab hidden"><?php _e('User synchronization', 'gwapi'); ?></a>
            <a href="#build-shortcode" class="nav-tab hidden"><?php _e('Build Shortcode', 'gwapi'); ?></a>
            <a href="#sms-inbox" class="nav-tab hidden"><?php _e('SMS Inbox', 'gwapi'); ?></a>
        </h2>

        <div class="tab-inner">


            <!-- BASE SETTINGS -->
            <div data-tab="base" class="tab hidden" id="baseTab">
                <p>
                    <?php $link = [':link' => '<a href="https://GatewayAPI.com" target="_blank"><strong>GatewayAPI.com</strong></a>']; ?>
                    <?= strtr(__('Please enter your OAuth Key and OAuth Secret below. You find this information by logging into :link and then navigate to <strong>Settings » API Keys</strong>.', 'gwapi'), $link); ?>
                </p>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('OAuth Key', 'gwapi'); ?></th>
                        <td><input type="text" name="gwapi_key" value="<?php echo esc_attr(get_option('gwapi_key')); ?>"
                                size="32"/></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e('OAuth Secret', 'gwapi'); ?></th>
                        <td><input type="text" name="gwapi_secret"
                                value="<?php echo esc_attr(get_option('gwapi_secret')); ?>" size="64"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Enable sending UI', 'gwapi'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="gwapi_enable_ui" <?= get_option('gwapi_enable_ui') ? 'checked' : ''; ?>>
                                <?php _e('Yes, enable the SMS sending UI', 'gwapi'); ?>
                            </label>
                            <p class="help-block">
                                <?php _e('Enabling this adds a new menu for sending SMS\'es and listing sent SMS\'es, as well as managing an address book.', 'gwapi'); ?>

                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Default sender', 'gwapi'); ?></th>
                        <td>
                            <label>
                                <input type="text" maxlength="15" name="gwapi_default_sender"
                                    value="<?= esc_attr(get_option('gwapi_default_sender')); ?>">
                            </label>
                            <p class="help-block">
                                <?php _e('Must consist of either 11 characters or 15 digits.', 'gwapi'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div id="enableCaptcha" <?= !get_option('gwapi_enable_ui') ? 'class="hidden"' : ''; ?>>
                    <h3><?php _e('Captcha for public forms', 'gwapi'); ?></h3>
                    <p><?php _e('reCAPTCHA is a free service from Google, which greatly reduces spam and abuse from your public forms.', 'gwapi'); ?></p>
                    <p><?php _e('If you would like to use reCAPTCHA on the public GatewayAPI forms (signup, unsubscribe etc.), then please enter your site key and secret key below.', 'gwapi'); ?></p>
                    <p><?= strtr(__('<a href="%url%" target="_blank"><strong>Click here</strong></a> to read more about reCAPTCHA and signup.', 'gwapi'), ['%url%' => 'https://www.google.com/recaptcha']); ?></p>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('ReCAPTCHA Site Key', 'gwapi'); ?></th>
                            <td>
                                <label>
                                    <input type="text" size="50"
                                        name="gwapi_recaptcha_site_key" value="<?= esc_attr(get_option('gwapi_recaptcha_site_key')); ?>">
                                </label>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('ReCAPTCHA Secret key', 'gwapi'); ?></th>
                            <td>
                                <label>
                                    <input type="text" size="50"
                                        name="gwapi_recaptcha_secret_key" value="<?= esc_attr(get_option('gwapi_recaptcha_secret_key')); ?>">
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <!-- BASE SETTINGS -->


            <!-- RECIPIENT FIELDS -->
            <div class="tab hidden" data-tab="recipients-fields" id="recipientsTab">
                <?php
                $all_fields = _gwapi_all_recipient_fields();
                $all_fields[] = ['type' => 'template'];
                ?>

                <p>
                    <?php _e('The fields below are available when sending SMS\'es, as well as when the users signs up or updates their recipient.', 'gwapi'); ?>
                </p>
                <p>
                    <?php _e('Use the "Build Shortcode"-tab to get the shortcodes required to embed a signup form, update a recipient, unsubscribe or simply send an SMS.', 'gwapi'); ?>
                </p>


                <div class="recipient-fields">
                    <?php foreach ($all_fields as $af): ?>
                        <div
                            class="recipient-field <?= $af['type'] == 'template' ? 'hidden' : ''; ?>" <?= $af['type'] == 'template' ? 'data-is-template' : '' ?> <?= isset($af['is_builtin']) && $af['is_builtin'] ? 'data-is-builtin' : '' ?>>
                            <?= isset($af['is_builtin']) && $af['is_builtin'] ? '<input type="hidden" name="gwapi_recipient_fields[is_builtin][]" value="1">' : ''; ?>

                            <div class="field-group label-left with-drag with-decoration">
                                <div class="drag-handle has-tooltip"
                                    title="You can re-order the items by dragging this handle."></div>

                                <div class="form-field">
                                    <label class="control-label">
                                        <?php _e('Name', 'gwapi'); ?>
                                        <i class="info has-tooltip"
                                            title="<?= esc_attr(__('Shown in all pulic forms which uses this field.', 'gwapi')) ?>"></i>
                                    </label>
                                    <div class="form-control">
                                        <input required type="text" name="gwapi_recipient_fields[name][]"
                                            value="<?= esc_attr(isset($af['name']) ? $af['name'] : ''); ?>">
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label class="control-label">
                                        <?php _e('Field ID', 'gwapi'); ?>
                                        <i class="info has-tooltip"
                                            title="<?= esc_attr(__('This is used as the tag when sending SMS\'es, in shortcodes and is used as the meta key.', 'gwapi')) ?>"></i>
                                    </label>
                                    <div class="form-control">
                                        <input
                                            required <?= isset($af['is_builtin']) && $af['is_builtin'] ? 'disabled' : ''; ?>
                                            type="text" name="gwapi_recipient_fields[field_id][]"
                                            value="<?= esc_attr(isset($af['field_id']) ? $af['field_id'] : ''); ?>">
                                        <?php if (isset($af['is_builtin']) && $af['is_builtin']): ?>
                                            <input required type="hidden" name="gwapi_recipient_fields[field_id][]"
                                                value="<?= esc_attr(isset($af['field_id']) ? $af['field_id'] : ''); ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label class="control-label">
                                        <?php _e('Description', 'gwapi'); ?>
                                        <i class="info has-tooltip"
                                            title="<?= esc_attr(__('A helpful description of what input the user is expected to put in the field.', 'gwapi')) ?>"></i>
                                    </label>
                                    <div class="form-control">
                                        <input type="text" name="gwapi_recipient_fields[description][]"
                                            value="<?= esc_attr(isset($af['description']) ? $af['description'] : ''); ?>">
                                    </div>
                                </div>

                                <div class="form-field" data-hidden_on="hidden,radio">
                                    <label class="control-label">
                                        <?php _e('Required field?', 'gwapi'); ?>
                                        <i class="info has-tooltip"
                                            title="<?= esc_attr(__('Required fields must be filled when adding recipients. For multiple choice field types, at least one value must be picked.', 'gwapi')) ?>"></i>
                                    </label>
                                    <div class="form-control">
                                        <label>
                                            <input <?= isset($af['is_builtin']) && $af['is_builtin'] ? 'disabled' : ''; ?>
                                                type="checkbox" name="gwapi_recipient_fields[required][]"
                                                value="1" <?= (isset($af['required']) && $af['required']) ? 'checked' : ''; ?>>
                                            <?php _e('Yes, this is a required field.'); ?>

                                            <?php if (isset($af['is_builtin']) && $af['is_builtin']): ?>
                                                <input type="hidden" name="gwapi_recipient_fields[required][]"
                                                    value="<?= esc_attr(isset($af['required']) ? $af['required'] : ''); ?>">
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-field">
                                    <label class="control-label">
                                        <?php _e('Type', 'gwapi'); ?>
                                    </label>
                                    <?php
                                    $all_types = _gwapi_all_recipient_field_types(true);
                                    ?>
                                    <div class="form-control">
                                        <select <?= isset($af['is_builtin']) && $af['is_builtin'] ? 'disabled' : ''; ?>
                                            name="gwapi_recipient_fields[type][]">
                                            <?php foreach ($all_types as $optgroup => $fields): ?>
                                                <optgroup label="<?= esc_attr($optgroup); ?>">
                                                    <?php foreach ($fields as $key => $val): ?>
                                                        <option
                                                            value="<?= $key; ?>" <?= (isset($af['type']) && $af['type'] == $key) ? 'selected' : '' ?>><?= $val; ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>

                                        <?php if (isset($af['is_builtin']) && $af['is_builtin']): ?>
                                            <input type="hidden" name="gwapi_recipient_fields[type][]"
                                                value="<?= esc_attr(isset($af['type']) ? $af['type'] : ''); ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-field hidden" data-visible_on="mobile_cc">
                                    <label class="control-label">
                                        <?php _e('Country limitation', 'gwapi'); ?>
                                        <i class="info has-tooltip"
                                            title="<?= esc_attr(__('If you wish to limit the countries available, please enter the country calling codes below (one per line, no “+”-prefix).', 'gwapi')); ?>"></i>
                                        <br/>
                                        <a href="https://countrycode.org/" target="_blank"><?php _e('List of country calling codes', 'gwapi'); ?></a>
                                    </label>
                                    <div class="form-control">
                                        <textarea name="gwapi_recipient_fields[mobile_cc_countries][]" rows="5"
                                            placeholder="Leave blank to allow all countries"><?= esc_attr(isset($af['mobile_cc_countries']) ? $af['mobile_cc_countries'] : ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-field hidden" data-visible_on="select,radio,checkbox">
                                    <label class="control-label">
                                        <?php _e('Choices', 'gwapi'); ?>
                                        <i class="info has-tooltip"
                                            title="<?= esc_attr(__('Enter a single choice per line. Prefix the line with two exclamation marks (!!), to make an option selected by default.', 'gwapi')) ?>"></i>
                                    </label>
                                    <div class="form-control">
                                        <textarea name="gwapi_recipient_fields[choices][]"
                                            rows="5"><?= esc_attr(isset($af['choices']) ? $af['choices'] : ''); ?></textarea>
                                    </div>
                                </div>

                                <?php if (!isset($af['is_builtin']) || !$af['is_builtin']): ?>
                                    <div class="form-field">
                                        <div class="form-control">
                                            <button type="button" class="button button-danger" data-delete="true"
                                                data-warning="<?= esc_attr(__('Are you sure that you want to delete this field?', 'gwapi')); ?>"><?php _e('Delete this field', 'gwapi'); ?></button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>


                <button class="button" type="button" data-add-btn>+ <?php _e('Add another field', 'gwapi'); ?></button>
                <button class="button" type="button" data-reset-btn
                    data-warning="<?= esc_attr(__('Are you sure that you want to reset to default? All your field configurations above will be lost immediately with no way of recovery.', 'gwapi')); ?>"><?php _e('Reset to default', 'gwapi'); ?></button>
                <br><br>
            </div>
            <!-- RECIPIENT FIELDS -->


            <!-- USER SYNCHRONIZATION -->
            <div class="tab hidden" data-tab="user-sync" id="userSync">
                <p>
                    <?php _e('It is possible to create recipients and keep them up-to-date automatically. As soon as the minimum required information on a user in the WordPress user is present, a recipient will be created automatically.', 'gwapi'); ?>
                </p>
                <p>
                    <?php _e('If any information changes on the WordPress user or the user is deleted, the recipient is updated or deleted as well. In other words, the recipient works as an always up-to-date copy of the relevant users.', 'gwapi'); ?>
                </p>
                <p>
                    <?php _e('Please note: It is <strong>your responsibility</strong> to ensure that the information on a user is valid and that mobile numbers are unique. If a user is not present in the recipients list, then there\'s either missing or invalid information on the user, or the number is already associated with another user.', 'gwapi'); ?>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="gwapi_user_sync_enable" value="1" id="userSyncEnableCb" <?= get_option('gwapi_user_sync_enable') ? 'checked' : ''; ?>> <?php _e('Enable user synchronization', 'gwapi'); ?>
                    </label>
                </p>
                <div id="userSyncEnabled" <?= get_option('gwapi_user_sync_enable') ? '' : 'class="hidden"'; ?>>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('Mobile number', 'gwapi'); ?></th>
                            <td>
                                <input type="text" required name="gwapi_user_sync_meta_number" value="<?php echo esc_attr(get_option('gwapi_user_sync_meta_number')); ?>" size="32"/>
                                <p class="description">
                                    <?php _e('Which user meta key contains the mobile number?', 'gwapi'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Mobile country code', 'gwapi'); ?></th>
                            <td>
                                <input type="text" required name="gwapi_user_sync_meta_countrycode" value="<?php echo esc_attr(get_option('gwapi_user_sync_meta_countrycode')); ?>" size="32"/>
                                <p class="description">
                                    <?php _e('Which user meta key contains the country code for the mobile number?', 'gwapi'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Default country code', 'gwapi'); ?></th>
                            <td>
                                <div style="max-width: 400px">
                                    <select name="gwapi_user_sync_meta_default_countrycode">
                                    </select>
                                </div>
                                <p class="description">
                                    <?php _e('If only the mobile number, but not the country code, is specified on a user, then this value will be assumed.', 'gwapi'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Other fields', 'gwapi'); ?></th>
                            <td>
                                <textarea name="gwapi_user_sync_meta_other_fields" id="" cols="50" rows="6" placeholder="<?= esc_attr(__('Meta Key : Recipient Field Key', 'gwapi')); ?>"><?= esc_html(get_option('gwapi_user_sync_meta_other_fields')); ?></textarea>
                                <p class="description">
                                    <?php _e('If you want to map other fields from the user and to the meta fields on the recipient, then please list them below. Enter a user meta key, add a colon and then enter the corresponding recipient meta field. To add more fields, separate with newline.', 'gwapi'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row"><?php _e('Groups', 'gwapi'); ?></th>
                            <td>
                                <p class="description">
                                    <?php _e('If you wish to automatically subscribe recipients to specific groups, then please tell which groups a user should be assigned to, when a specific user meta key is present. This user meta key just needs to be present and hold any value except "0", "false" or be empty.', 'gwapi'); ?>
                                </p>
                                <table>
                                    <thead>
                                    <tr>
                                        <th width="33%" style="padding: 15px 10px 0 0"><?php _e('Group name', 'gwapi'); ?></th>
                                        <th width="66%" style="padding: 15px 10px 0 0"><?php _e('User meta field', 'gwapi'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $groups = get_terms([
                                        'taxonomy' => 'gwapi-recipient-groups',
                                        'hide_empty' => false
                                    ]);
                                    $gmap = get_option('gwapi_user_sync_group_map') ?: [];
                                    foreach ($groups as $g) {
                                        /** @var $g WP_Term */
                                        ?>
                                        <tr>
                                            <td style="padding: 5px 10px 0 0"><?= $g->name; ?></td>
                                            <td style="padding: 5px 10px 0 0">
                                                <input type="text" name="gwapi_user_sync_group_map[<?= $g->term_id ?>]" value="<?= esc_attr(isset($gmap[$g->term_id]) ? $gmap[$g->term_id] : null) ?>">
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <!-- USER SYNCHRONIZATION -->


            <!-- SHORTCODE GENERATOR -->
            <div class="tab hidden" data-tab="build-shortcode" id="buildShortcodeTab">
                <p>
                    <?php _e('Use the options below to build a shortcode ready for use anywhere you would like.', 'gwapi'); ?>
                </p>
                <p>
                    <?php _e('Note: If any fields below are missing or out-of-date, then please click "Save changes", as this is based on the last saved version.', 'gwapi'); ?>
                </p>

                <div id="shortcodeType">
                    <h2><?php _e('Action of form', 'gwapi'); ?></h2>
                    <p><?php _e('What should happen when the form is successfully submitted?', 'gwapi'); ?></p>
                    <div>
                        <label>
                            <input checked type="radio" name="action" value="signup"> <?php _e('User is signed up (recipient is created)', 'gwapi'); ?>
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="radio" name="action" value="update"> <?php _e('User is updated (existing recipient is updated)', 'gwapi'); ?>
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="radio" name="action" value="unsubscribe"> <?php _e('User is unsubscribed (existing recipient is deleted)', 'gwapi'); ?>
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="radio" name="action" value="send_sms"> <?php _e('Send an SMS', 'gwapi'); ?>
                        </label>
                    </div>
                </div>

                <div id="captcha">
                    <h2><?php _e('Require CAPTCHA', 'gwapi'); ?></h2>
                    <p>
                        <?php _e('Would you like to use Google\'s free reCAPTCHA-service to protect the form against spam bots and abuse?', 'gwapi'); ?>
                    </p>
                    <div>
                        <label>
                            <input type="checkbox" name="recaptcha" value="1"> <?php _e('Yes, I would like to enable reCAPTCHA on this form.', 'gwapi'); ?>
                        </label>
                    </div>
                </div>

                <div id="shortcodeGroups">
                    <h2><?php _e('Groups', 'gwapi'); ?></h2>
                    <p>
                        <?php _e('Which group(s) should be affected? If action is signup or update, the user will be added to the selected groups. With action "Send SMS", this is the groups who will receive the SMS.', 'gwapi'); ?>
                    </p>
                    <select multiple name="groups" size="5" style="width: 100%; height: auto;">
                        <?php foreach (get_terms('gwapi-recipient-groups', ['hide_empty' => false]) as $term): ?>
                            <option value="<?= $term->term_id ?>"><?= esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select multiple groups by holding down CTRL (Windows) / CMD (Mac) while clicking.', 'gwapi'); ?></p>

                    <p><?php _e('Would you also like to show the list of groups (containing only the above selected gorups) and allow the user to pick amongst these groups? All will be selected per default.', 'gwapi'); ?></p>
                    <div>
                        <label>
                            <input type="checkbox" name="editable" value="1"> <?php _e('Yes, show the list of selected groups and allow user to edit.', 'gwapi'); ?>
                        </label>
                    </div>
                </div>
                <h2></h2>

                <div id="shortcodeSendSms" class="hidden">
                    <h2><?php _e('Send SMS', 'gwapi'); ?></h2>
                    <p>
                        <strong style="color: red"><?php _e('Warning:', 'gwapi'); ?></strong> <?php _e('Please consider that embedding this into a public part of your website enables any visitor to send SMS\'es to the selected groups. This is rarely a good idea. Pages utilizing this shortcode should at least be password protected or be limited to specific user roles.', 'gwapi'); ?>
                    </p>
                    <div>
                        <label>
                            <input type="checkbox" name="sender" value="1"> <?php _e('Allow the sender name to be changed.', 'gwapi'); ?>
                        </label>
                    </div>
                </div>

                <h2><?php _e('Your shortcode', 'gwapi'); ?></h2>
                <textarea disabled id="final_shortcode" rows="2" style="width: 100%; font-family: monospace">[gwapi action="signup"]</textarea>
            </div>
            <!-- SHORTCODE GENERATOR -->


            <!-- SMS INBOX -->
            <div class="tab hidden" data-tab="sms-inbox" id="smsInbox">
                <p>
                    <?php _e('Do you want to receive SMS messages for this installation?', 'gwapi'); ?>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="gwapi_sms_inbox_enable" value="1" id="smsInboxEnableCb" <?= get_option('gwapi_sms_inbox_enable') ? 'checked' : ''; ?>> <?php _e('Yes please, I would like to receive SMS messages.', 'gwapi'); ?>
                    </label>
                </p>

                <div id="smsInboxEnabled" <?= get_option('gwapi_sms_inbox_enable') ? '' : 'class="hidden"'; ?>>

                    <hr>

                    <h3><?php _e('Configuration', 'gwapi'); ?> </h3>

                    <table class="form-table">

                        <tr valign="top">
                            <p>
                                <?php _e('Before your installation is capable of receiving SMS messages you must configure GatewayAPI.', 'gwapi'); ?>
                            </p>
                            <p>
                                <?php _e('Please follow these simple steps:', 'gwapi'); ?>
                            </p>
                            <ol>
                                <li>
                                    <?php _e('Visit gatewayapi.com and click Settings > Web Hooks or <a target="_blank" href="https://gatewayapi.com/app/settings/web-hooks/">follow this link</a>.', 'gwapi'); ?>
                                </li>
                                <li>
                                    <?php _e('Click ADD NEW.', 'gwapi'); ?>
                                </li>
                                <li>
                                    <?php _e('Enter a Unique label.', 'gwapi'); ?>
                                </li>
                                <li>
                                    <?php _e('Paste the following URL into the Web hook URL input field.', 'gwapi'); ?>
                                    <div class="description" style="text-decoration:underline">
                                        <input name="gwapi_receive_sms_url" style="width:100%" value="<?php echo esc_attr(admin_url('admin-ajax.php?action=gwapi_receive_sms')); ?>">
                                    </div>
                                </li>
                            </ol>
                        </tr>
                    </table>
                </div>
            </div>
            <!-- SMS INBOX -->
        </div>

        <hr>

        <?php submit_button(); ?>
    </form>
</div>