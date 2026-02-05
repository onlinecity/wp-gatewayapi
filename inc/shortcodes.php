<?php
if (!defined('ABSPATH')) {
	die('Cannot be accessed directly!');
}

/**
 * Helper: Render a form field
 */
function gatewayapi__render_field($field, $value = '')
{
	$id = isset($field['field_id']) ? $field['field_id'] : (isset($field['title']) ? sanitize_title($field['title']) : '');
	$name = isset($field['name']) ? $field['name'] : (isset($field['title']) ? $field['title'] : '');
	$type = isset($field['type']) ? $field['type'] : 'text';
	$required = isset($field['required']) && $field['required'] ? true : false;
	$desc = isset($field['description']) ? $field['description'] : '';
	$readonly = isset($field['readonly']) && $field['readonly'] ? 'readonly' : '';
	$error = isset($field['error']) ? $field['error'] : '';

	// Map types
	if ($type == 'digits') $type = 'tel'; // close enough

	$input_id = 'gwapi_' . esc_attr($id);
	$desc_id = $desc ? $input_id . '_desc' : '';
	$error_id = $error ? $input_id . '_error' : '';

	echo '<div class="gatewayapi-control-wrapper gatewayapi-field-' . esc_attr($id) . '">';
	echo '<label for="' . $input_id . '">' . esc_html($name) . ($required ? ' <span class="required">*</span>' : '') . '</label>';

	$attrs = [
		'name="gatewayapi[' . esc_attr($id) . ']"',
		'id="' . $input_id . '"',
		'class="form-control"',
		$required ? 'required' : '',
		$readonly,
		$error ? 'aria-invalid="true"' : '',
		$error ? 'aria-describedby="' . esc_attr($error_id) . ($desc_id ? ' ' . esc_attr($desc_id) : '') . '"' : ($desc_id ? 'aria-describedby="' . esc_attr($desc_id) . '"' : '')
	];
	$attrs = implode(' ', array_filter($attrs));

	if ($type === 'textarea') {
		echo '<textarea ' . $attrs . '>' . esc_textarea($value) . '</textarea>';
	} elseif ($type === 'select') {
		echo '<select ' . $attrs . '>';
		if (isset($field['options']) && is_array($field['options'])) {
			foreach ($field['options'] as $opt_val => $opt_label) {
				echo '<option value="' . esc_attr($opt_val) . '" ' . selected($value, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
			}
		}
		echo '</select>';
	} else {
		echo '<input type="' . esc_attr($type) . '" ' . $attrs . ' value="' . esc_attr($value) . '" />';
	}

	if ($error) {
		echo '<div id="' . esc_attr($error_id) . '" class="gatewayapi-error">' . esc_html($error) . '</div>';
	}
	if ($desc) {
		echo '<p id="' . esc_attr($desc_id) . '" class="gatewayapi-help-text">' . esc_html($desc) . '</p>';
	}
	echo '</div>';
}

/**
 * Render ReCaptcha
 */
function gatewayapi__render_recaptcha()
{
	$site_key = get_option('gwapi_recaptcha_site_key');
	if (!$site_key) return;

	echo '<div class="gatewayapi-control-wrapper gatewayapi-recaptcha">';
	echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
	echo '</div>';
	wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js');
}

/**
 * Verify ReCaptcha
 */
function gatewayapi__verify_recaptcha()
{
	$secret_key = get_option('gwapi_recaptcha_secret_key');
	if (!$secret_key) return true; // If not configured, skip check (or fail? old plugin skipped if not configured in shortcode, but here we check if submitted)

	$captcha_response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
	if (!$captcha_response) return new WP_Error('recaptcha_missing', 'Please complete the CAPTCHA.');

	$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
	$response = wp_remote_post($verify_url, [
		'body' => [
			'secret' => $secret_key,
			'response' => $captcha_response,
			'remoteip' => $_SERVER['REMOTE_ADDR']
		]
	]);

	if (is_wp_error($response)) {
		return new WP_Error('recaptcha_error', 'Unable to verify CAPTCHA.');
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	if (!$body['success']) {
		return new WP_Error('recaptcha_failed', 'CAPTCHA verification failed. Please try again.');
	}

	return true;
}

/**
 * Helper: Render Tags Selector
 */
function gatewayapi__render_tags_selector($atts, $current_tags = [])
{
	static $fieldCounter = 1;
	$show = isset($atts['edit_groups']) && $atts['edit_groups'];
	if (!$show) return;

	$fieldCounter++;
	$allowed_ids = [];
	if (isset($atts['groups']) && $atts['groups']) {
		$allowed_ids = array_map('intval', explode(',', $atts['groups']));
	}

	$terms = get_terms([
		'taxonomy' => 'gwapi-recipient-tag',
		'hide_empty' => false,
	]);

	if (is_wp_error($terms) || empty($terms)) return;

	echo '<div class="gatewayapi-control-wrapper gatewayapi-tags">';
	echo '<label class="gatewayapi-label">Select Groups</label>';
	echo '<div class="gatewayapi-checkboxes">';

	foreach ($terms as $term) {
		if (!empty($allowed_ids) && !in_array($term->term_id, $allowed_ids)) continue;

		$checked = in_array($term->term_id, $current_tags) ? 'checked' : '';
		$id = 'gwapi_tag_' . $term->term_id;
		echo '<div class="gatewayapi-checkbox">';
		echo '<input type="checkbox" name="gatewayapi[tags][]" value="' . esc_attr($term->term_id) . '" id="' . esc_attr($id) . '_'.$fieldCounter.'" ' . $checked . '> ';
		echo '<label for="' . esc_attr($id) . '_'.$fieldCounter.'">' . esc_html($term->name) . '</label>';
		echo '</div>';
	}

	echo '</div></div>';
}

/**
 * Handle Signup Flow
 */
function gatewayapi__handle_signup($atts)
{
	$submitted_form_id = isset($_POST['gwapi_form_id']) ? sanitize_key($_POST['gwapi_form_id']) : '';
	$current_form_id = isset($atts['form_id']) ? $atts['form_id'] : '';

	$step = 'init';
	if ($submitted_form_id === $current_form_id) {
		$step = isset($_POST['gwapi_step']) ? sanitize_key($_POST['gwapi_step']) : 'init';
	}

	// Step 1: Init - Show Form
	if ($step === 'init') {
		echo '<input type="hidden" name="gwapi_step" value="verify_sms">';

		// Add Name field if enabled
		if (isset($atts['add_name_field']) && $atts['add_name_field']) {
			gatewayapi__render_field(['field_id' => 'name', 'name' => 'Name', 'type' => 'text', 'required' => true]);
		}

		// CC and Number
		$allowed_countries = isset($atts['allowed_countries']) ? explode(',', $atts['allowed_countries']) : [];
		$countries = gatewayapi__get_countries();
		$options = [];
		foreach ($countries as $code => $country) {
			if (!empty($allowed_countries) && !in_array($code, $allowed_countries)) continue;
			$options[$country['phone']] = $country['name'] . ' (+' . $country['phone'] . ')';
		}
		asort($options);

		gatewayapi__render_field(['field_id' => 'cc', 'name' => 'Country Code', 'type' => 'select', 'required' => true, 'options' => $options], get_option('gwapi_default_cc', ''));
		gatewayapi__render_field(['field_id' => 'number', 'name' => 'Phone Number', 'type' => 'tel', 'required' => true]);

		// Custom Fields
		$custom_fields = get_option('gwapi_contact_fields', []);
		if (!is_array($custom_fields)) $custom_fields = []; // Handle if it's not array

		foreach ($custom_fields as $field) {
			// Map the field structure from v2 options
			// Assuming v2 structure: title, type, etc.
			$f = [
				'field_id' => sanitize_title($field['title']),
				'name' => $field['title'],
				'type' => 'text' // default
			];
			gatewayapi__render_field($f);
		}

		// Tags Selector
		gatewayapi__render_tags_selector($atts);

		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			gatewayapi__render_recaptcha();
		}

		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-primary">Sign Up</button>';
		echo '</div>';
	}

	// Step 2: Verify SMS
    elseif ($step === 'verify_sms') {
		// Verify ReCaptcha if enabled
		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			$verify = gatewayapi__verify_recaptcha();
			if (is_wp_error($verify)) {
				echo '<div class="alert alert-danger">' . $verify->get_error_message() . '</div>';
				return;
			}
		}

		$cc = sanitize_text_field($_POST['gatewayapi']['cc']);
		$number = sanitize_text_field($_POST['gatewayapi']['number']);
		$msisdn = gatewayapi__get_msisdn($cc, $number);

		if (!$msisdn) {
			echo '<div class="alert alert-danger">Invalid phone number.</div>';
			return;
		}

		// Rate limit check
		$rate_limit = gatewayapi__check_rate_limit($msisdn);
		if (is_wp_error($rate_limit)) {
			echo '<div class="alert alert-danger">' . $rate_limit->get_error_message() . '</div>';
			return;
		}

		// Check if already exists
		if (gatewayapi__get_recipient_by_phone($cc, $number)) {
			echo '<div class="alert alert-warning">You are already subscribed.</div>';
			return;
		}

		// Generate Code & Save Data to Transient
		$code = rand(100000, 999999);
		$transient_key = 'gwapi_signup_' . $msisdn;

		$user_data = $_POST['gatewayapi'];

		// Handle Groups/Tags
		// 1. Fixed groups from shortcode (hidden)
		$fixed_groups = [];
		if (isset($atts['groups']) && $atts['groups']) {
			$fixed_groups = array_map('intval', explode(',', $atts['groups']));
		}

		// 2. User selected tags
		$selected_tags = [];
		if (isset($_POST['gatewayapi']['tags']) && is_array($_POST['gatewayapi']['tags'])) {
			$selected_tags = array_map('intval', $_POST['gatewayapi']['tags']);
		}

		// Merge - if edit_groups is on, user selection (filtered by whitelist) + fixed?
		// Usually if edit_groups is ON, the 'groups' attribute works as a whitelist for the UI.
		// If edit_groups is OFF, the 'groups' attribute works as forced assignment.

		$final_groups = [];
		if (isset($atts['edit_groups']) && $atts['edit_groups']) {
			// User selection, validated against whitelist if present
			foreach ($selected_tags as $tid) {
				if (empty($fixed_groups) || in_array($tid, $fixed_groups)) {
					$final_groups[] = $tid;
				}
			}
		} else {
			// Forced assignment
			$final_groups = $fixed_groups;
		}

		$user_data['groups'] = array_unique($final_groups);

		set_transient($transient_key, [
			'data' => $user_data,
			'code' => $code
		], 60 * 60); // 1 hour

		// Send SMS
		$msg = sprintf('Your signup code is: %s', $code);
		$res = gatewayapi_send_mobile_message($msg, $msisdn, get_option('gwapi_sender', 'GWAPI'));

		if (is_wp_error($res)) {
			echo '<div class="alert alert-danger">Failed to send SMS code.</div>';
			return;
		}

		echo '<div class="alert alert-info">We have sent a verification code to your mobile.</div>';
		echo '<input type="hidden" name="gwapi_step" value="complete">';
		echo '<input type="hidden" name="gatewayapi[cc]" value="' . esc_attr($cc) . '">';
		echo '<input type="hidden" name="gatewayapi[number]" value="' . esc_attr($number) . '">';
		if (isset($_POST['gatewayapi']['name'])) {
			echo '<input type="hidden" name="gatewayapi[name]" value="' . esc_attr(sanitize_text_field($_POST['gatewayapi']['name'])) . '">';
		}

		// Pass through allowed countries if present to maintain the form on verify/complete steps if needed?
		// Actually the step 'complete' only needs cc, number, and sms_code to verify against transient.
		// We already added 'name' to the hidden fields.

		gatewayapi__render_field(['field_id' => 'sms_code', 'name' => 'Verification Code', 'type' => 'text', 'required' => true]);

		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-primary">Verify & Subscribe</button>';
		echo '</div>';
	}

	// Step 3: Complete
    elseif ($step === 'complete') {
		$cc = sanitize_text_field($_POST['gatewayapi']['cc']);
		$number = sanitize_text_field($_POST['gatewayapi']['number']);
		$code = sanitize_text_field($_POST['gatewayapi']['sms_code']);
		$msisdn = gatewayapi__get_msisdn($cc, $number);

		$transient_key = 'gwapi_signup_' . $msisdn;
		$stored = get_transient($transient_key);

		if (!$stored || $stored['code'] != $code) {
			echo '<div class="alert alert-danger">Invalid or expired code.</div>';
			return;
		}

		// Create Contact
		$data = $stored['data'];
		$post_id = wp_insert_post([
			'post_type' => 'gwapi-recipient',
			'post_title' => isset($data['name']) && !empty($data['name']) ? $data['name'] : $msisdn,
			'post_name' => $msisdn,
			'post_status' => 'publish'
		]);

		if ($post_id) {
			update_post_meta($post_id, 'cc', $data['cc']);
			update_post_meta($post_id, 'number', $data['number']);
			update_post_meta($post_id, 'msisdn', $msisdn);
			if (isset($data['name'])) {
				update_post_meta($post_id, 'name', $data['name']);
			}

			// Save custom fields
			foreach ($data as $k => $v) {
				if ($k !== 'cc' && $k !== 'number' && $k !== 'groups' && $k !== 'tags') {
					update_post_meta($post_id, $k, sanitize_text_field($v));
				}
			}

			// Assign Tags (Groups)
			if (isset($data['groups']) && is_array($data['groups'])) {
				wp_set_object_terms($post_id, array_map('intval', $data['groups']), 'gwapi-recipient-tag');
			} elseif (isset($data['tags']) && is_array($data['tags'])) {
				wp_set_object_terms($post_id, array_map('intval', $data['tags']), 'gwapi-recipient-tag');
			}

			// Set Country
			$countries = gatewayapi__get_countries();
			foreach ($countries as $code => $country) {
				if ($country['phone'] == $data['cc']) {
					$country_name = $country['name'];
					$term = get_term_by('slug', $code, 'gwapi-recipient-country');
					if (!$term) {
						wp_insert_term($country_name, 'gwapi-recipient-country', [
							'slug' => $code
						]);
					}
					wp_set_post_terms($post_id, $code, 'gwapi-recipient-country');
					break;
				}
			}

			delete_transient($transient_key);
			gatewayapi__reset_rate_limit($msisdn);
			echo '<div class="alert alert-success">You have been successfully subscribed!</div>';
		} else {
			echo '<div class="alert alert-danger">Failed to create contact.</div>';
		}
	}
}

/**
 * Handle Update Flow
 */
function gatewayapi__handle_update($atts)
{
	$submitted_form_id = isset($_POST['gwapi_form_id']) ? sanitize_key($_POST['gwapi_form_id']) : '';
	$current_form_id = isset($atts['form_id']) ? $atts['form_id'] : '';

	$step = 'init';
	if ($submitted_form_id === $current_form_id) {
		$step = isset($_POST['gwapi_step']) ? sanitize_key($_POST['gwapi_step']) : 'init';
	}

	// Step 1: Login
	if ($step === 'init') {
		echo '<input type="hidden" name="gwapi_step" value="verify_login">';

		$allowed_countries = isset($atts['allowed_countries']) ? explode(',', $atts['allowed_countries']) : [];
		$countries = gatewayapi__get_countries();
		$options = [];
		foreach ($countries as $code => $country) {
			if (!empty($allowed_countries) && !in_array($code, $allowed_countries)) continue;
			$options[$country['phone']] = $country['name'] . ' (+' . $country['phone'] . ')';
		}
		asort($options);

		gatewayapi__render_field(['field_id' => 'cc', 'name' => 'Country Code', 'type' => 'select', 'required' => true, 'options' => $options], get_option('gwapi_default_cc', ''));
		gatewayapi__render_field(['field_id' => 'number', 'name' => 'Phone Number', 'type' => 'tel', 'required' => true]);

		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			gatewayapi__render_recaptcha();
		}

		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-primary">Log In</button>';
		echo '</div>';
	}

	// Step 2: Verify & Edit
    elseif ($step === 'verify_login') {
		// Verify ReCaptcha
		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			$verify = gatewayapi__verify_recaptcha();
			if (is_wp_error($verify)) {
				echo '<div class="alert alert-danger">' . $verify->get_error_message() . '</div>';
				return;
			}
		}

		$cc = sanitize_text_field($_POST['gatewayapi']['cc']);
		$number = sanitize_text_field($_POST['gatewayapi']['number']);
		$msisdn = gatewayapi__get_msisdn($cc, $number);

		$recipient = gatewayapi__get_recipient_by_phone($cc, $number);
		if (!$recipient) {
			echo '<div class="alert alert-danger">Phone number not found.</div>';
			return;
		}

		// Rate limit check
		$rate_limit = gatewayapi__check_rate_limit($msisdn);
		if (is_wp_error($rate_limit)) {
			echo '<div class="alert alert-danger">' . $rate_limit->get_error_message() . '</div>';
			return;
		}

		// Generate Code
		$code = rand(100000, 999999);
		$transient_key = 'gwapi_update_' . $msisdn;
		set_transient($transient_key, $code, 60 * 60);

		// Send SMS
		$msg = sprintf('Your login code is: %s', $code);
		gatewayapi_send_mobile_message($msg, $msisdn, get_option('gwapi_sender', 'GWAPI'));

		echo '<div class="alert alert-info">We have sent a verification code to your mobile.</div>';
		echo '<input type="hidden" name="gwapi_step" value="show_form">';
		echo '<input type="hidden" name="gatewayapi[cc]" value="' . esc_attr($cc) . '">';
		echo '<input type="hidden" name="gatewayapi[number]" value="' . esc_attr($number) . '">';

		gatewayapi__render_field(['field_id' => 'sms_code', 'name' => 'Verification Code', 'type' => 'text', 'required' => true]);
		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-primary">Verify</button>';
		echo '</div>';
	}

	// Step 3: Show Edit Form
    elseif ($step === 'show_form') {
		$cc = sanitize_text_field($_POST['gatewayapi']['cc']);
		$number = sanitize_text_field($_POST['gatewayapi']['number']);
		$code = sanitize_text_field($_POST['gatewayapi']['sms_code']);
		$msisdn = gatewayapi__get_msisdn($cc, $number);

		$transient_key = 'gwapi_update_' . $msisdn;
		if (get_transient($transient_key) != $code) {
			echo '<div class="alert alert-danger">Invalid or expired code.</div>';
			return;
		}

		$recipient = gatewayapi__get_recipient_by_phone($cc, $number);
		if (!$recipient) {
			echo '<div class="alert alert-danger">Recipient not found.</div>';
			return;
		}

		echo '<input type="hidden" name="gwapi_step" value="save_update">';
		echo '<input type="hidden" name="gatewayapi[cc]" value="' . esc_attr($cc) . '">';
		echo '<input type="hidden" name="gatewayapi[number]" value="' . esc_attr($number) . '">';
		// Pass code through to verify again on save (or rely on session/transient, but safer to re-verify if possible, or just trust transient presence if we extend it)
		// Here we just extend the transient
		set_transient($transient_key, $code, 60 * 60);

		// Render Fields with values
		gatewayapi__render_field(['field_id' => 'cc', 'name' => 'Country Code', 'type' => 'tel', 'readonly' => true], $cc);
		gatewayapi__render_field(['field_id' => 'number', 'name' => 'Phone Number', 'type' => 'tel', 'readonly' => true], $number);

		$custom_fields = get_option('gwapi_contact_fields', []);
		if (is_array($custom_fields)) {
			foreach ($custom_fields as $field) {
				$id = sanitize_title($field['title']);
				$val = get_post_meta($recipient->ID, $id, true);

				$f = [
					'field_id' => $id,
					'name' => $field['title'],
					'type' => 'text'
				];
				gatewayapi__render_field($f, $val);
			}
		}

		// Render Tags
		$current_tags_objs = wp_get_object_terms($recipient->ID, 'gwapi-recipient-tag');
		$current_tags = !is_wp_error($current_tags_objs) ? wp_list_pluck($current_tags_objs, 'term_id') : [];
		gatewayapi__render_tags_selector($atts, $current_tags);

		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-primary">Save Changes</button>';
		echo '</div>';
	}

	// Step 4: Save
    elseif ($step === 'save_update') {
		$cc = sanitize_text_field($_POST['gatewayapi']['cc']);
		$number = sanitize_text_field($_POST['gatewayapi']['number']);
		$msisdn = gatewayapi__get_msisdn($cc, $number);
		$transient_key = 'gwapi_update_' . $msisdn;

		// We check transient existence to ensure flow was followed
		if (!get_transient($transient_key)) {
			echo '<div class="alert alert-danger">Session expired.</div>';
			return;
		}

		$recipient = gatewayapi__get_recipient_by_phone($cc, $number);
		if ($recipient) {
			$data = $_POST['gatewayapi'];
			foreach ($data as $k => $v) {
				if ($k !== 'cc' && $k !== 'number' && $k !== 'sms_code' && $k !== 'tags') {
					update_post_meta($recipient->ID, $k, sanitize_text_field($v));
				}
			}

			// Logic: If edit_groups is enabled, we update tags based on selection + allowed/fixed logic.
			// If not enabled, do we touch tags?
			// If 'groups' attribute is set but edit_groups is OFF, maybe we should ADD the user to those groups?
			// But usually Update profile is about user choices.
			// Let's stick to: Only update tags if edit_groups is ON, OR if we want to force-add groups on update (rare).
			// If edit_groups is ON, we replace the user's tags (within the scope of allowed tags).

			$selected_tags = [];
			if (isset($_POST['gatewayapi']['tags']) && is_array($_POST['gatewayapi']['tags'])) {
				$selected_tags = array_map('intval', $_POST['gatewayapi']['tags']);
			}

			if (isset($atts['edit_groups']) && $atts['edit_groups']) {
				$fixed_groups = [];
				if (isset($atts['groups']) && $atts['groups']) {
					$fixed_groups = array_map('intval', explode(',', $atts['groups']));
				}

				// Current tags
				$current_tags_objs = wp_get_object_terms($recipient->ID, 'gwapi-recipient-tag');
				$current_tags = !is_wp_error($current_tags_objs) ? wp_list_pluck($current_tags_objs, 'term_id') : [];

				// If a whitelist (groups attr) exists, we only touch tags within that whitelist.
				// We remove any tags in whitelist that are NOT selected.
				// We add any tags in whitelist that ARE selected.
				// We leave tags NOT in whitelist alone.

				if (!empty($fixed_groups)) {
					$new_tags = array_diff($current_tags, $fixed_groups); // Keep tags not in whitelist
					foreach ($selected_tags as $tid) {
						if (in_array($tid, $fixed_groups)) {
							$new_tags[] = $tid;
						}
					}
				} else {
					// No whitelist, so user selection is the full truth (replace all tags with selection)
					// Or maybe add? "Select Interest Groups" usually implies these are the groups you are in.
					$new_tags = $selected_tags;
				}

				wp_set_object_terms($recipient->ID, array_map('intval', $new_tags), 'gwapi-recipient-tag');
			}
			// If edit_groups is OFF but groups attr is present, maybe we should force-add?
            elseif (isset($atts['groups']) && $atts['groups']) {
				$groups = explode(',', $atts['groups']);
				wp_set_object_terms($recipient->ID, array_map('intval', $groups), 'gwapi-recipient-tag', true); // Append
			}
			// Fallback for tags attribute if somehow used without edit_groups
            elseif (!empty($selected_tags)) {
				wp_set_object_terms($recipient->ID, array_map('intval', $selected_tags), 'gwapi-recipient-tag', true); // Append
			}

			// Set Country
			$countries = gatewayapi__get_countries();
			foreach ($countries as $code => $country) {
				if ($country['phone'] == $cc) {
					$country_name = $country['name'];
					$term = get_term_by('slug', $code, 'gwapi-recipient-country');
					if (!$term) {
						wp_insert_term($country_name, 'gwapi-recipient-country', [
							'slug' => $code
						]);
					}
					wp_set_post_terms($recipient->ID, $code, 'gwapi-recipient-country');
					break;
				}
			}

			echo '<div class="alert alert-success">Profile updated.</div>';
			delete_transient($transient_key);
			gatewayapi__reset_rate_limit($msisdn);
		}
	}
}

/**
 * Handle Unsubscribe Flow
 */
function gatewayapi__handle_unsubscribe($atts)
{
	$submitted_form_id = isset($_POST['gwapi_form_id']) ? sanitize_key($_POST['gwapi_form_id']) : '';
	$current_form_id = isset($atts['form_id']) ? $atts['form_id'] : '';

	$step = 'init';
	if ($submitted_form_id === $current_form_id) {
		$step = isset($_POST['gwapi_step']) ? sanitize_key($_POST['gwapi_step']) : 'init';
	}

	if ($step === 'init') {
		echo '<input type="hidden" name="gwapi_step" value="verify_sms">';

		$allowed_countries = isset($atts['allowed_countries']) ? explode(',', $atts['allowed_countries']) : [];
		$countries = gatewayapi__get_countries();
		$options = [];
		foreach ($countries as $code => $country) {
			if (!empty($allowed_countries) && !in_array($code, $allowed_countries)) continue;
			$options[$country['phone']] = $country['name'] . ' (+' . $country['phone'] . ')';
		}
		asort($options);

		gatewayapi__render_field(['field_id' => 'cc', 'name' => 'Country Code', 'type' => 'select', 'required' => true, 'options' => $options], get_option('gwapi_default_cc', ''));
		gatewayapi__render_field(['field_id' => 'number', 'name' => 'Phone Number', 'type' => 'tel', 'required' => true]);

		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			gatewayapi__render_recaptcha();
		}
		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-primary">Unsubscribe</button>';
		echo '</div>';
	}

    elseif ($step === 'verify_sms') {
		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			$verify = gatewayapi__verify_recaptcha();
			if (is_wp_error($verify)) {
				echo '<div class="alert alert-danger">' . $verify->get_error_message() . '</div>';
				return;
			}
		}

		$cc = sanitize_text_field($_POST['gatewayapi']['cc']);
		$number = sanitize_text_field($_POST['gatewayapi']['number']);
		$msisdn = gatewayapi__get_msisdn($cc, $number);

		$recipient = gatewayapi__get_recipient_by_phone($cc, $number);
		if (!$recipient) {
			echo '<div class="alert alert-danger">Phone number not found.</div>';
			return;
		}

		// Rate limit check
		$rate_limit = gatewayapi__check_rate_limit($msisdn);
		if (is_wp_error($rate_limit)) {
			echo '<div class="alert alert-danger">' . $rate_limit->get_error_message() . '</div>';
			return;
		}

		$code = rand(100000, 999999);
		$transient_key = 'gwapi_unsub_' . $msisdn;
		set_transient($transient_key, $code, 60 * 60);

		$msg = sprintf('Your unsubscribe code is: %s', $code);
		gatewayapi_send_mobile_message($msg, $msisdn, get_option('gwapi_sender', 'GWAPI'));

		echo '<div class="alert alert-info">We have sent a verification code to your mobile.</div>';
		echo '<input type="hidden" name="gwapi_step" value="confirm">';
		echo '<input type="hidden" name="gatewayapi[cc]" value="' . esc_attr($cc) . '">';
		echo '<input type="hidden" name="gatewayapi[number]" value="' . esc_attr($number) . '">';

		gatewayapi__render_field(['field_id' => 'sms_code', 'name' => 'Verification Code', 'type' => 'text', 'required' => true]);
		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-danger">Confirm Unsubscribe</button>';
		echo '</div>';
	}

    elseif ($step === 'confirm') {
		$cc = sanitize_text_field($_POST['gatewayapi']['cc']);
		$number = sanitize_text_field($_POST['gatewayapi']['number']);
		$code = sanitize_text_field($_POST['gatewayapi']['sms_code']);
		$msisdn = gatewayapi__get_msisdn($cc, $number);

		$transient_key = 'gwapi_unsub_' . $msisdn;
		if (get_transient($transient_key) != $code) {
			echo '<div class="alert alert-danger">Invalid or expired code.</div>';
			return;
		}

		$recipient = gatewayapi__get_recipient_by_phone($cc, $number);
		if ($recipient) {
			wp_trash_post($recipient->ID);
			echo '<div class="alert alert-success">You have been unsubscribed.</div>';
			delete_transient($transient_key);
			gatewayapi__reset_rate_limit($msisdn);
		}
	}
}

/**
 * Main Shortcode Handler
 */
function gatewayapi_shortcode_handler($atts)
{
	static $instance_count = 0;
	$instance_count++;
	$form_id = 'gwapi_form_' . $instance_count;

	$atts = shortcode_atts([
		'action' => 'signup',
		'recaptcha' => 0,
		'embed_css' => 0,
		'groups' => '', // comma separated IDs
		'tags' => '', // alias for groups
		'add_name_field' => 0,
		'edit_groups' => 0,
		'allowed_countries' => ''
	], $atts, 'gatewayapi');

	$atts['form_id'] = $form_id;

	// Alias handling
	if ($atts['tags'] && !$atts['groups']) $atts['groups'] = $atts['tags'];

	ob_start();

	if ($atts['embed_css']) {
		?>
        <style>
            .gatewayapi-shortcode form {
                max-width: 100%;
            }
            .gatewayapi-shortcode .gatewayapi-control-wrapper {
                margin-bottom: 15px;
            }
            .gatewayapi-shortcode label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            .gatewayapi-shortcode .form-control,
            .gatewayapi-shortcode textarea,
            .gatewayapi-shortcode input[type="text"],
            .gatewayapi-shortcode input[type="tel"],
            .gatewayapi-shortcode input[type="email"] {
                width: 100%;
                box-sizing: border-box;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .gatewayapi-shortcode .gatewayapi-help-text {
                font-size: 0.85em;
                color: #666;
                margin-top: 5px;
            }
            .gatewayapi-shortcode .gatewayapi-error {
                color: #d9534f;
                font-size: 0.85em;
                margin-top: 5px;
            }
            .gatewayapi-shortcode .gatewayapi-checkboxes {
                margin-top: 10px;
            }
            .gatewayapi-shortcode .gatewayapi-checkbox {
                margin-bottom: 5px;
            }
            .gatewayapi-shortcode .gatewayapi-checkbox label {
                display: inline;
                font-weight: normal;
                margin-left: 5px;
            }

            @media (min-width: 768px) {
                .gatewayapi-shortcode .gatewayapi-control-wrapper {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: flex-start;
                }
                .gatewayapi-shortcode .gatewayapi-control-wrapper > label {
                    flex: 0 0 200px;
                    margin-bottom: 0;
                    padding-top: 10px;
                }
                .gatewayapi-shortcode .gatewayapi-control-wrapper > .form-control,
                .gatewayapi-shortcode .gatewayapi-control-wrapper > input,
                .gatewayapi-shortcode .gatewayapi-control-wrapper > textarea,
                .gatewayapi-shortcode .gatewayapi-control-wrapper > .g-recaptcha,
                .gatewayapi-shortcode .gatewayapi-control-wrapper > .gatewayapi-checkboxes {
                    flex: 1;
                }
                .gatewayapi-shortcode .gatewayapi-help-text,
                .gatewayapi-shortcode .gatewayapi-error {
                    margin-left: 200px;
                    width: 100%;
                }
            }
        </style>
		<?php
	}

	echo '<div class="gatewayapi-shortcode" id="' . esc_attr($form_id) . '">';
	echo '<form method="post" action="' . esc_url(remove_query_arg('gwapi_step')) . '#' . esc_attr($form_id) . '">';
	echo '<input type="hidden" name="gwapi_form_id" value="' . esc_attr($form_id) . '">';
	echo '<fieldset>';

	$legend = '';
	switch ($atts['action']) {
		case 'signup':
			$legend = 'Sign Up';
			break;
		case 'update':
			$legend = 'Update Profile';
			break;
		case 'unsubscribe':
			$legend = 'Unsubscribe';
			break;
		case 'send_sms':
			$legend = 'Send SMS';
			break;
	}
	if ($legend) {
		echo '<legend>' . esc_html($legend) . '</legend>';
	}

	switch ($atts['action']) {
		case 'signup':
			gatewayapi__handle_signup($atts);
			break;
		case 'update':
			gatewayapi__handle_update($atts);
			break;
		case 'unsubscribe':
			gatewayapi__handle_unsubscribe($atts);
			break;
		case 'send_sms':
			gatewayapi__handle_send_sms($atts);
			break;
		default:
			echo '<div class="alert alert-warning">Invalid action.</div>';
	}

	echo '</fieldset>';
	echo '</form>';
	echo '</div>';
	return ob_get_clean();
}

/**
 * Handle Send SMS Flow
 */
function gatewayapi__handle_send_sms($atts)
{
	$submitted_form_id = isset($_POST['gwapi_form_id']) ? sanitize_key($_POST['gwapi_form_id']) : '';
	$current_form_id = isset($atts['form_id']) ? $atts['form_id'] : '';

	$step = 'init';
	if ($submitted_form_id === $current_form_id) {
		$step = isset($_POST['gwapi_step']) ? sanitize_key($_POST['gwapi_step']) : 'init';
	}

	if ($step === 'init') {
		echo '<input type="hidden" name="gwapi_step" value="send">';

		gatewayapi__render_field([
			'field_id' => 'message',
			'name' => 'Message',
			'type' => 'textarea',
			'required' => true,
			'description' => 'Enter your message here.'
		]);

		gatewayapi__render_tags_selector($atts);

		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			gatewayapi__render_recaptcha();
		}

		echo '<div class="gatewayapi-control-wrapper">';
		echo '<button type="submit" class="btn btn-primary">Send SMS</button>';
		echo '</div>';
	}
    elseif ($step === 'send') {
		if (isset($atts['recaptcha']) && $atts['recaptcha']) {
			$verify = gatewayapi__verify_recaptcha();
			if (is_wp_error($verify)) {
				echo '<div class="alert alert-danger">' . $verify->get_error_message() . '</div>';
				return;
			}
		}

		$message = isset($_POST['gatewayapi']['message']) ? sanitize_textarea_field($_POST['gatewayapi']['message']) : '';
		if (empty($message)) {
			echo '<div class="alert alert-danger">Message cannot be empty.</div>';
			return;
		}

		$target_groups = [];
		// If edit_groups is on, user selection (validated against whitelist)
		if (isset($atts['edit_groups']) && $atts['edit_groups']) {
			$whitelist = [];
			if (isset($atts['groups']) && $atts['groups']) {
				$whitelist = array_map('intval', explode(',', $atts['groups']));
			}

			if (isset($_POST['gatewayapi']['tags']) && is_array($_POST['gatewayapi']['tags'])) {
				$selected = array_map('intval', $_POST['gatewayapi']['tags']);
				foreach ($selected as $tid) {
					if (empty($whitelist) || in_array($tid, $whitelist)) {
						$target_groups[] = $tid;
					}
				}
			}
		} else {
			// Fixed groups
			if (isset($atts['groups']) && $atts['groups']) {
				$target_groups = array_map('intval', explode(',', $atts['groups']));
			}
		}

		if (empty($target_groups)) {
			echo '<div class="alert alert-danger">No recipient groups selected.</div>';
			return;
		}

		// Fetch Recipients
		$args = [
			'post_type' => 'gwapi-recipient',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'tax_query' => [[
				'taxonomy' => 'gwapi-recipient-tag',
				'field' => 'term_id',
				'terms' => $target_groups,
				'operator' => 'IN'
			]]
		];

		$query = new WP_Query($args);
		$ids = $query->posts;

		if (empty($ids)) {
			echo '<div class="alert alert-warning">No recipients found in selected groups.</div>';
			return;
		}

		$recipients = [];
		foreach ($ids as $pid) {
			$msisdn = get_post_meta($pid, 'msisdn', true);
			if ($msisdn) {
				$recipients[] = $msisdn;
			}
		}
		$recipients = array_unique($recipients);

		if (empty($recipients)) {
			echo '<div class="alert alert-warning">No valid phone numbers found.</div>';
			return;
		}

		// Send
		$sender = get_option('gwapi_sender', 'GWAPI');
		$res = gatewayapi_send_sms($message, $recipients, $sender);

		if (is_wp_error($res)) {
			echo '<div class="alert alert-danger">' . $res->get_error_message() . '</div>';
		} else {
			echo '<div class="alert alert-success">SMS sent to ' . count($recipients) . ' recipients.</div>';
		}
	}
}

add_shortcode('gatewayapi', 'gatewayapi_shortcode_handler');
add_shortcode('gwapi', 'gatewayapi_shortcode_handler');

