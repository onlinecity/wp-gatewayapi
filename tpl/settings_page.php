<div class="wrap">
    <h2><?php _e('GatewayAPI Settings', 'gwapi'); ?></h2>

    <form method="post" action="options.php">
        <?php settings_fields('gwapi'); ?>
        <?php do_settings_sections('gwapi'); ?>

        <h2 class="nav-tab-wrapper">
            <a href="#base" class="nav-tab"><?php _e('General settings', 'gwapi'); ?></a>
            <a href="#recipients-fields" class="nav-tab hidden"><?php _e('Recipient fields', 'gwapi'); ?></a>
            <a href="#build-shortcode" class="nav-tab hidden"><?php _e('Build Shortcode', 'gwapi'); ?></a>
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
                                        <?php if ($af['is_builtin'] && $af['is_builtin']): ?>
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

                                        <?php if ($af['is_builtin'] && $af['is_builtin']): ?>
                                            <input type="hidden" name="gwapi_recipient_fields[type][]"
                                                   value="<?= esc_attr(isset($af['type']) ? $af['type'] : ''); ?>">
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-field hidden" data-visible_on="mobile_cc">
                                    <label class="control-label">
                                        Country limitation
                                        <i class="info has-tooltip"
                                           title="<?= esc_attr(__('If you wish to limit the countries available, please enter the country calling codes below (one per line, no “+”-prefix).', 'gwapi')); ?>"></i>
                                        <br/>
                                        <a href="https://countrycode.org/" target="_blank">List of country calling
                                            codes</a>
                                    </label>
                                    <div class="form-control">
                                        <textarea name="gwapi_recipient_fields[mobile_cc_countries][]" rows="5"
                                                  placeholder="Leave blank to allow all countries"><?= esc_attr(isset($af['mobile_cc_countries']) ? $af['mobile_cc_countries'] : ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-field hidden" data-visible_on="select,radio,checkbox">
                                    <label class="control-label">
                                        Choices
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
                                                    data-warning="<?= esc_attr(__('Are you sure that you want to delete this field?', 'gwapi')); ?>"><?php _e('Delete this field'); ?></button>
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


            <!-- SHORTCODE GENERATOR -->
            <div class="tab hidden" data-tab="build-shortcode" id="buildShortcodeTab">
                <p>
                    <?php _e('Use the options below to build a shortcode ready for use anywhere you would like.', 'gwapi'); ?>
                </p>
                <p>
                    <?php _e('Note: If any fields below are missing or out-of-date, then please click "Save changes", as this is based on the last saved version.', 'gwapi'); ?>
                </p>

                <div id="shortcodeType">
                    <h2>Action of form</h2>
                    <p>What should happen when the form is successfully submitted?</p>
                    <div>
                        <label>
                            <input checked type="radio" name="action" value="signup"> User is signed up (recipient is created)
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="radio" name="action" value="update"> User is updated (existing recipient is
                            updated)
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="radio" name="action" value="unsubscribe"> User is unsubscribed (existing
                            recipient
                            is deleted)
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="radio" name="action" value="send_sms"> Send an SMS
                        </label>
                    </div>
                </div>

                <div id="captcha">
                    <h2>Require CAPTCHA</h2>
                    <p>
                        Would you like to use Google's free reCAPTCHA-service to protect the form against spam bots and abuse?
                    </p>
                    <div>
                        <label>
                            <input type="checkbox" name="recaptcha" value="1"> Yes, I would like to enable reCAPTCHA on this form.
                        </label>
                    </div>
                </div>

                <div id="shortcodeGroups">
                    <h2>Groups</h2>
                    <p>
                        Which group(s) should be affected? If action is signup or update, the user will be added to the selected groups. With action "Send SMS", this is the groups who will receive the SMS.
                    </p>
                    <select multiple name="groups" size="5" style="width: 100%; height: auto;">
                        <?php foreach(get_terms('gwapi-recipient-groups', ['hide_empty' => false]) as $term): ?>
                            <option value="<?= $term->term_id ?>"><?= esc_html($term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Select multiple groups by holding down CTRL (Windows) / CMD (Mac) while clicking.</p>

                    <p>Would you also like to show the list of groups (containing only the above selected gorups) and allow the user to pick amongst these groups? All will be selected per default.</p>
                    <div>
                        <label>
                            <input type="checkbox" name="editable" value="1"> Yes, show the list of selected groups and allow user to edit.
                        </label>
                    </div>
                </div>
                <h2></h2>

                <div id="shortcodeSendSms" class="hidden">
                    <h2>Send SMS</h2>
                    <p>
                        Friendly alert: Please consider that embedding this into a public part of your website enables
                        any visitor to send SMS'es to the selected groups. This is usually not intended. Pages utilizing
                        this shortcode should at least be password protected or be limited to specific user roles.
                    </p>
                    <div>
                        <label>
                            <input type="checkbox" name="sender" value="1"> Allow the sender name to be changed.
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="sendtime" value="1"> Allow send-time to be specified.
                        </label>
                    </div>
                </div>

                <h2><?php _e('Your shortcode'); ?></h2>
                <code><?php _e('[gwapi action="signup"]'); ?></code>
            </div>
            <!-- SHORTCODE GENERATOR -->


        </div>


        <hr>

        <?php submit_button(); ?>
    </form>
</div>