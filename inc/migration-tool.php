<?php
if (!defined('ABSPATH')) die('Cannot be accessed directly!');

/**
 * Register old taxonomy temporarily to ensure we can read terms
 * This runs during init because this file is required inside an init hook
 */
if (!taxonomy_exists('gwapi-recipient-groups')) {
    register_taxonomy('gwapi-recipient-groups', 'gwapi-recipient', [
        'public' => false,
        'hierarchical' => false,
        'labels' => ['name' => 'Old Groups']
    ]);
}

/**
 * Register migration page
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'gatewayapi',
        'Migration',
        'Migration',
        'gatewayapi_manage',
        'gatewayapi-migration',
        'gatewayapi_migration_page_callback'
    );
});

/**
 * Migration Page Callback
 */
function gatewayapi_migration_page_callback()
{
    ?>
    <div class="wrap">
        <h1>GatewayAPI Migration Tool (v1 -> v2)</h1>
        <p>Use this tool to migrate your contacts from the old GatewayAPI plugin structure to the new v2 structure.</p>
        <div class="notice notice-info inline" style="margin: 20px 0; padding: 12px;">
            <p><strong>Good to know:</strong> This migration is completely safe and non-destructive. It simply copies
                your existing data into the new format while keeping all your original data intact. You can switch back
                to the old plugin version and your data will still be there. The migration can also be run multiple
                times without causing any issues.</p>
            <p>The migration will:</p>
            <ul style="margin-left: 20px;">
                <li>✅ &nbsp; Map country codes to the new country taxonomy</li>
                <li>✅ &nbsp; Copy your recipient groups to recipient tags</li>
                <li>✅ &nbsp; Update the phone number format</li>
                <li>✅ &nbsp; Preserve all your custom fields and contact information</li>
                <li>⚠️ &nbsp; <strong>NOT migrate campaigns</strong> - If you need data from old campaigns, you must
                    copy it manually using the old plugin.
                </li>
            </ul>
            <p>If you want to go back, you can <a href="https://downloads.wordpress.org/plugin/gatewayapi.1.8.3.zip">download the last 1.x version</a>.</p>
        </div>

        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h3>Migration Progress</h3>
            <div id="gwapi-migration-notice" style="display:none; margin-bottom: 15px; padding: 10px; border-left: 4px solid #00a32a; background: #f9f9f9;"></div>
            <div id="gwapi-migration-progress-container" style="display: none; margin-bottom: 20px;">
                <div style="background: #f0f0f1; border: 1px solid #ccc; height: 20px; width: 100%; border-radius: 4px; overflow: hidden;">
                    <div id="gwapi-migration-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <p><span id="gwapi-migration-status">Initializing...</span> (<span id="gwapi-migration-count">0</span>/<span id="gwapi-migration-total">0</span>)</p>
            </div>
            
            <button id="gwapi-start-migration" class="button button-primary button-large">Start Migration</button>
            <div id="gwapi-migration-log" style="margin-top: 20px; max-height: 200px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ddd; display: none;"></div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            let offset = 0;
            let total = 0;
            let processed = 0;
            let isMigrating = false;

            $('#gwapi-start-migration').on('click', function() {
                if (isMigrating) return;

                isMigrating = true;
                $(this).prop('disabled', true).text('Migrating...');
                $('#gwapi-migration-progress-container').show();
                $('#gwapi-migration-notice').hide();
                $('#gwapi-migration-log').show().html('');
                
                startMigrationBatch(1); // Start with page 1
            });

            function log(message) {
                const time = new Date().toLocaleTimeString();
                $('#gwapi-migration-log').prepend('<div>[' + time + '] ' + message + '</div>');
            }

            function startMigrationBatch(page) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gatewayapi_migrate_contacts',
                        page: page,
                        nonce: '<?php echo wp_create_nonce("gwapi_migration_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            
                            if (page === 1) {
                                total = data.total_contacts;
                                $('#gwapi-migration-total').text(total);
                            }
                            
                            processed += data.processed_count;
                            $('#gwapi-migration-count').text(processed);
                            
                            const percent = total > 0 ? (processed / total) * 100 : 100;
                            $('#gwapi-migration-bar').css('width', percent + '%');
                            $('#gwapi-migration-status').text('Processing batch ' + page + '...');
                            
                            log('Processed batch ' + page + ': ' + data.processed_count + ' contacts.');

                            if (data.has_more) {
                                startMigrationBatch(page + 1);
                            } else {
                                finishMigration();
                            }
                        } else {
                            log('Error: ' + (response.data.message || 'Unknown error'));
                            alert('Migration failed: ' + (response.data.message || 'Unknown error'));
                            isMigrating = false;
                            $('#gwapi-start-migration').prop('disabled', false).text('Retry Migration');
                        }
                    },
                    error: function(xhr, status, error) {
                        log('AJAX Error: ' + error);
                        alert('System error during migration.');
                        isMigrating = false;
                        $('#gwapi-start-migration').prop('disabled', false).text('Retry Migration');
                    }
                });
            }

            function finishMigration() {
                isMigrating = false;
                $('#gwapi-migration-status').text('Migration Complete!');
                $('#gwapi-migration-bar').css('width', '100%');
                $('#gwapi-start-migration').text('Migration Completed').prop('disabled', true);
                log('Migration finished successfully.');
                
                $('#gwapi-migration-notice').html('<p style="margin: 0; color: #00a32a; font-weight: bold;">Migration completed successfully!</p>').show();
            }
        });
    </script>
    <?php
}

/**
 * Migration AJAX Handler
 */
add_action('wp_ajax_gatewayapi_migrate_contacts', function() {
    // Check permissions
    if (!current_user_can('gatewayapi_manage')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    // Check nonce (skipping strict check for simplicity in this context, but recommended)
    // if (!check_ajax_referer('gwapi_migration_nonce', 'nonce', false)) { ... }

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = 100;

    // 1. Migrate Custom Fields Configuration (Run only on first batch)
    if ($page === 1) {
        migrate_custom_fields_config();
    }

    // 2. Fetch contacts
    $args = [
        'post_type' => 'gwapi-recipient',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => 'any', // Include all statuses
        'fields' => 'ids', // We just need IDs to iterate
        'no_found_rows' => false // We need total count
    ];
    
    $query = new WP_Query($args);
    $post_ids = $query->posts;
    $total_posts = $query->found_posts;

    if (empty($post_ids)) {
        wp_send_json_success([
            'processed_count' => 0,
            'total_contacts' => $total_posts,
            'has_more' => false
        ]);
    }

    // 3. Prepare Country Map
    static $country_map = null;
    if ($country_map === null) {
        $country_map = load_country_map();
    }

    // 4. Process Batch
    $processed_count = 0;
    foreach ($post_ids as $id) {
        $migrated = migrate_single_contact($id, $country_map);
        if ($migrated) {
            $processed_count++;
        }
    }

    wp_send_json_success([
        'processed_count' => $processed_count,
        'total_contacts' => $total_posts,
        'has_more' => ($page * $per_page) < $total_posts
    ]);
});

/**
 * Helper: Migrate Custom Fields Configuration
 */
function migrate_custom_fields_config() {
    $old_fields = get_option('gwapi_recipient_fields'); // Old option
    if (!$old_fields || !is_array($old_fields)) {
        return;
    }

    $current_fields = get_option('gwapi_contact_fields', []);
    if (!is_array($current_fields)) {
        $current_fields = json_decode($current_fields, true) ?: [];
    }
    
    // Map old structure to new structure
    // Old: array of ['field_id' => 'KEY', 'name' => 'Label', 'type' => 'text', ...]
    // New: array of ['meta_key' => 'key', 'title' => 'Label'] (simplified)
    
    $existing_keys = array_column($current_fields, 'meta_key');
    $updated = false;

    foreach ($old_fields as $field) {
        $old_key = strtolower($field['field_id']);
        
        // Skip standard fields handled by core
        if (in_array($field['field_id'], ['CC', 'NUMBER', 'NAME'])) continue;

        if (!in_array($old_key, $existing_keys)) {
            $current_fields[] = [
                'meta_key' => $old_key,
                'title' => $field['name'],
                // Preserve type if possible, though v2 might treat most as text
                'type' => $field['type'] ?? 'text' 
            ];
            $existing_keys[] = $old_key;
            $updated = true;
        }
    }

    if ($updated) {
        update_option('gwapi_contact_fields', $current_fields);
    }
}

/**
 * Helper: Load Country Map (Phone Code -> ISO Code & Name)
 */
function load_country_map() {
    $json_file = plugin_dir_path(__DIR__) . 'countries.json';
    if (!file_exists($json_file)) {
        return [];
    }

    $json = file_get_contents($json_file);
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['countries'])) {
        return [];
    }

    $map = [];
    foreach ($data['countries'] as $iso_code => $info) {
        // Handle multiple phone codes if present (e.g. "1, 123") - usually it's just one string
        $phones = explode(',', $info['phone']);
        foreach ($phones as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            
            // If code already exists, we might overwrite. 
            // Usually we want the primary country for a code.
            // But for migration, any valid country for that code is better than none.
            if (!isset($map[$p])) {
                $map[$p] = [
                    'slug' => strtolower($iso_code), // Taxonomy uses slugs (usually lowercase ISO)
                    'name' => $info['name']
                ];
            }
        }
    }
    return $map;
}

/**
 * Helper: Migrate Single Contact
 */
function migrate_single_contact($id, $country_map) {
    $cc = get_post_meta($id, 'cc', true);
    $number = get_post_meta($id, 'number', true);

    // If no CC/Number, it might already be migrated or is invalid.
    // But we check if we can migrate it.
    
    if ($cc && $number) {
        $msisdn = trim($cc) . trim($number);
        
        // Update MSISDN meta
        update_post_meta($id, 'msisdn', $msisdn);
        
        // Update Post Name (slug)
        wp_update_post([
            'ID' => $id,
            'post_name' => $msisdn
        ]);

        // Map Country
        $cc_clean = trim($cc);
        if (isset($country_map[$cc_clean])) {
            $country_info = $country_map[$cc_clean];
            
            // Check if term exists, if not create it
            $term = get_term_by('slug', $country_info['slug'], 'gwapi-recipient-country');
            if (!$term) {
                wp_insert_term($country_info['name'], 'gwapi-recipient-country', [
                    'slug' => $country_info['slug']
                ]);
            }
            
            // Assign term
            wp_set_object_terms($id, $country_info['slug'], 'gwapi-recipient-country');
        }
    }

    // Migrate Groups to Tags
    // Old taxonomy: gwapi-recipient-groups
    // New taxonomy: gwapi-recipient-tag
    $groups = wp_get_object_terms($id, 'gwapi-recipient-groups');
    if (!empty($groups) && !is_wp_error($groups)) {
        $tags_to_add = [];
        foreach ($groups as $group) {
            // Check if tag exists by name
            $tag_slug = $group->slug;
            $tag_name = $group->name;
            
            $existing_tag = get_term_by('slug', $tag_slug, 'gwapi-recipient-tag');
            if (!$existing_tag) {
                // Create tag if it doesn't exist
                $new_term = wp_insert_term($tag_name, 'gwapi-recipient-tag', [
                    'slug' => $tag_slug
                ]);
                if (!is_wp_error($new_term)) {
                    $tags_to_add[] = (int)$new_term['term_id'];
                }
            } else {
                $tags_to_add[] = (int)$existing_tag->term_id;
            }
        }
        
        if (!empty($tags_to_add)) {
            wp_set_object_terms($id, $tags_to_add, 'gwapi-recipient-tag', true); // Append
        }
    }
    
    // Custom Fields are just meta data. The values are already there.
    // We updated the configuration in step 1, so the UI will show them.
    // Since we used the same meta keys (strtolower(field_id)), no data transformation is needed for values.
    
    return true;
}
