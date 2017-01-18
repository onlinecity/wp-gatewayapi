<?php

add_action('init', function () {

    if (!get_option('gwapi_enable_ui')) return;

    $args = array(
        'labels' => array(
            'name' => __('Inbox', 'gwapi'),
            'singular_name' => __('Inbox', 'gwapi'),
            'menu_name' => __('Inbox', 'gwapi'),
            'edit' => __('Received SMS', 'gwapi'),
            'edit_item' => __('Received SMS', 'gwapi'),
            'search_items' => __('Search Inbox', 'gwapi'),
            'not_found' => __('No SMS\'es found', 'gwapi'),
            'not_found_in_trash' => __('No SMS\'es found in trash', 'gwapi'),
        ),
        'hierarchical' => false,
        'supports' => false,
        'public' => false,
        'show_ui' => get_option('gwapi_enable_ui'),
        'show_in_menu' => 'edit.php?post_type=gwapi-sms',
        'menu_position' => 10,
        'show_in_nav_menus' => true,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'query_var' => false,
        'map_meta_cap' => true,
        'delete_with_user' => false,
        'capability_type' => 'post'
    );

    register_post_type('gwapi-receive-sms', $args);

    add_action('current_screen', function ($current_screen) {
        if ($current_screen->post_type === 'gwapi-receive-sms') {

            // add support for searching meta data
            bit_admin_add_search_column('gwapi-receive-sms', 'id');
            bit_admin_add_search_column('gwapi-receive-sms', 'msisdn');
            bit_admin_add_search_column('gwapi-receive-sms', 'receiver');
            bit_admin_add_search_column('gwapi-receive-sms', 'sender');
            bit_admin_add_search_column('gwapi-receive-sms', 'message');

            // I18N: Rename texts
            add_filter('gettext', function ($translated_text, $text, $domain) {
                if ($domain != 'default') return $translated_text;
                if ($text === 'Edit') return __('View');
                if ($text == 'Publish') return __('Received', 'gwapi');
                if ($text == 'Published') return __('Received', 'gwapi');
                if ($text == 'Published on: <b>%1$s</b>') return __('Received on: <b>%1$s</b>', 'gwapi');
                return $translated_text;
            }, 20, 3);
        }
    });

});


/**
 * Remove QuickEdit from the post rows
 */
add_filter('post_row_actions', function ($actions, $post) {
    global $current_screen;
    if ($current_screen->post_type != 'gwapi-receive-sms') return $actions;
    unset($actions['inline']);
    unset($actions['inline hide-if-no-js']);
    return $actions;
}, 10, 2);


/**
 * Define which columns we'll need.
 */
add_filter('manage_gwapi-receive-sms_posts_columns', function ($columns) {
    unset($columns['title']);
    $date_text = $columns['date'];
    unset($columns['date']);
    return array_merge($columns, [
        'msisdn' => __('Mobile number', 'gwapi'),
        'receiver' => __('Receiver', 'gwapi'),
        'message' => __('Message', 'gwapi'),
        'date' => $date_text
    ]);
});

/**
 * Print the content for our custom columns.
 */
add_action('manage_posts_custom_column', function ($column, $id) {
    if (get_post_type($id) != 'gwapi-receive-sms') return;
    switch ($column) {
        case 'msisdn':
            echo esc_html(get_post_meta($id, 'msisdn', true));
            break;
        case 'receiver':
            echo esc_html(get_post_meta($id, 'receiver', true));
            break;
        case 'message':
            $msg = get_post_meta($id, 'message', true) ?: '-';
            echo esc_html(mb_strlen($msg) > 50 ? mb_substr($msg, 0, 50) . '...' : $msg);
            break;
    }
}, 10, 2);

/**
 * Handler that receive smses from gatewayapi
 * We expect to receive a json payload with the smses
 * https://gatewayapi.com/docs/rest.html#mo-sms-receiving-sms-es
 *
 * Known properties:
 *
 * 'id', // (integer) – The ID of the MO SMS
 * 'msisdn', // (integer) – The MSISDN of the mobile device who sent the SMS.
 * 'receiver', // (integer) – The short code on which the SMS was received.
 * 'message', // (string) – The body of the SMS, incl. keyword.
 * 'senttime', // (integer) – The UNIX Timestamp when the SMS was sent.
 * 'webhook_label', // (string) – Label of the webhook who matched the SMS.
 * 'sender', // (string) – If SMS was sent with a text based sender, then this field is set. Optional.
 * 'mcc', // (integer) – MCC, mobile country code. Optional.
 * 'mnc', // (integer) – MNC, mobile network code. Optional.
 * 'validity_period', // (integer) – How long the SMS is valid. Optional.
 * 'encoding', // (string) – Encoding of the received SMS. Optional.
 * 'udh', // (string) – User data header of the received SMS. Optional.
 * 'payload', // (string) – Binary payload of the received SMS. Optional.
 */
function _gwapi_receive_sms_json_handler()
{
    if (!(isset($_GET['token']) && $_GET['token'] === _gwapi_receive_sms_token())) {
        header('Content-type: application/json', true, 400);
        die(json_encode(['success' => false, 'error' => 'Invalid token']));
    }
    $sms = json_decode(file_get_contents('php://input'), true);
    $ID = wp_insert_post(array(
        'post_name' => $sms['id'],
        'post_status' => 'publish',
        'post_type' => 'gwapi-receive-sms',
        'post_category' => 'gwapi',
        'meta_input' => $sms
    ));

    header('Content-type: application/json');
    echo json_encode(['success' => true]);

    // try to get data pushed to gatewayapi now, in case handling of incoming SMS somehow fails
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    @ob_flush();
    @flush();

    // handle incoming SMS
    do_action('gwapi_sms_received', $ID);
}

add_action('wp_ajax_priv_gwapi_receive_sms', '_gwapi_receive_sms_json_handler');
add_action('wp_ajax_nopriv_gwapi_receive_sms', '_gwapi_receive_sms_json_handler');

add_action('parse_request', function ($wp) {
    global $current_screen;
    if ($current_screen->post_type != 'gwapi-receive-sms') return;
    if (!isset($_POST['gwapi_receive_sms_export_format'])) return;
    switch ($_POST['gwapi_receive_sms_export_format']) {
        case 'xlsx':
            $format = 'xlsx';
            $filename = 'inbox.xlsx';
            break;
        default:
            $format = 'csv';
            $filename = 'inbox.csv';
            break;
    }
    $metas = [
        'id',
        'msisdn',
        'receiver',
        'message',
        'senttime',
        'webhook_label',
        'sender',
        'mcc',
        'mnc',
        'validity_period',
        'encoding',
        'udh',
        'payload'
    ];
    $args = $wp->query_vars;
    $args['posts_per_page'] = -1;
    $args['fields'] = 'ids';
    unset($args['paged']);

    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Type: application/octet-stream');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    header('Content-Transfer-Encoding: binary');


    $q = new WP_Query($args);
    switch ($format) {
        case 'xlsx':
            $writer = new \XLSXWriter();
            $headers = [];
            foreach ($metas as $meta) {
                $headers[$meta] = 'string';
            }
            $writer->writeSheetHeader('Sheet1', $headers);
            foreach($q->posts as $ID) {
                $metadata = get_post_meta($ID);
                $columns = [];
                foreach ($metas as $meta) {
                    $columns[] = isset($metadata[$meta]) ? $metadata[$meta][0] : '';
                }
                $writer->writeSheetRow('Sheet1', $columns);
            }
            echo $writer->writeToString();
            break;
        default:
            $out = fopen('php://output', 'w');
            fputcsv($out, $metas);

            foreach($q->posts as $ID) {
                $metadata = get_post_meta($ID);
                $row = [];
                foreach ($metas as $meta) {
                    $value = isset($metadata[$meta]) ? $metadata[$meta][0] : '';
                    $row[] = $value;
                }
                fputcsv($out, $row);
            }

            fclose($out);
            break;
    }
    wp_reset_query();
    die();
});

add_action('admin_footer', function () {
    global $query_string;
    global $current_screen;
    if ($current_screen->post_type != 'gwapi-receive-sms') return;
    ?>
    <form id="gwapiReceiveSmsExportForm" method="post" action="edit.php?<?php echo $query_string; ?>">
        <input type="hidden" name="gwapi_receive_sms_export_format" value="">
    </form>
    <?php
});


/**
 * Trigger additional actions when an SMS is received.
 */
add_action('gwapi_sms_received', function ($post_ID) {

    list($keyword) = explode(' ', trim(get_post_meta($post_ID, 'message', true)), 2);

    $actions = new WP_Query([
        "post_type" => "gwapi-receive-action",
        "posts_per_page" => -1,
        "orderby" => "menu_order",
        "order" => "ASC",
        "meta_query" => [
            [
                'key' => 'receiver',
                'value' => get_post_meta($post_ID, 'receiver', true)
            ],
            [
                'key' => 'keyword',
                'value' => $keyword
            ]
        ]
    ]);

    while ($actions->have_posts()) {
        $actions->the_post();
        do_action('gwapi_received_action_' . get_post_meta(get_the_ID(), 'action', true), [$post_ID, get_the_ID()]);
    }

}, 5);