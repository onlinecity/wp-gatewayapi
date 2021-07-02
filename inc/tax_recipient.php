<?php if (!defined('ABSPATH')) die('Cannot be accessed directly!'); ?>
<?php

add_action('init', function () {

  register_taxonomy('gwapi-recipient-groups', 'gwapi-recipient', [
    'label' => __('Recipient groups', 'gatewayapi'),
    'labels' => [
      'name' => __('Recipient groups', 'gatewayapi'),
      'singular_name' => __('Recipient group', 'gatewayapi'),
      'menu_name' => __('Groups', 'gatewayapi'),
      'all_items' => __('All recipient groups', 'gatewayapi'),
      'edit_item' => __('Edit group', 'gatewayapi'),
      'view_item' => __('View group', 'gatewayapi'),
      'update_item' => __('Update group', 'gatewayapi'),
      'add_new_item' => __('Add new recipient group', 'gatewayapi'),
      'new_item_name' => __('New group', 'gatewayapi'),
      'search_items' => __('Search groups', 'gatewayapi'),
      'popular_items' => __('Popular groups', 'gatewayapi'),
      'add_or_remove_items' => __('Add or remove groups', 'gatewayapi'),
      'choose_from_most_used' => __('Choose from most used groups', 'gatewayapi'),
      'not_found' => __('No groups found', 'gatewayapi')
    ],
    'public' => true,
    'show_ui' => true,
    'hierarchical' => true,
    'rewrite' => array(
      'slug' => 'gwapi-recipient-groups'
    )
  ]);
  bit_add_taxonomy_filter_to_cpt('gwapi-recipient', 'gwapi-recipient-groups');

});

add_filter('gwapi-recipient-groups_row_actions', function ($actions, $tag) {
  // Override recipient groups view action with a link to show group recipients instead of the default view
  $group_view_url = admin_url('edit.php?' . esc_url_raw($tag->taxonomy) . '=' . esc_url_raw($tag->slug) . '&post_type=gwapi-recipient');
  $actions['view'] = "<a href='{$group_view_url}'>" . __('View') . "</a>";
  return $actions;
}, 10, 2);
