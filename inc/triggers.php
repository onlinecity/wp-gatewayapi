<?php

use OnlineCity\GatewayAPI\Trigger;

function _gwapi_get_triggers()
{

  $triggers = [
    [
      'id' => 'post/added',
      'action' => 'post_added',
      'name' => __('Post added', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post added - Fires when Post is added to database. Useful when adding posts programatically or for 3rd party integration', 'gatewayapi')
      ],
    [
      'id' => 'post/drafted',
      'action' => 'post_drafted',
      'name' => __('Post saved', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post saved as a draft - Fires when Post is saved as a draft', 'gatewayapi')
    ],
    [
      'id' => 'post/published',
      'action' => 'post_published',
      'name' => __('Post published', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post published - Fires when Post is published', 'gatewayapi')
    ],
    [
      'id' => 'post/updated',
      'action' => 'post_updated',
      'name' => __('Post updated', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post updated - Fires when Post is updated', 'gatewayapi')
    ],
    [
      'id' => 'post/pending',
      'action' => 'post_pending',
      'name' => __('Post sent', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post sent for review - Fires when Post is sent for review', 'gatewayapi')
    ],
    [
      'id' => 'post/scheduled',
      'action' => 'post_scheduled',
      'name' => __('Post scheduled', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post scheduled - Fires when Post is scheduled', 'gatewayapi')
    ],
    [
      'id' => 'post/trashed',
      'action' => 'post_trashed',
      'name' => __('Post trashed', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post trashed - Fires when Post is moved to trash', 'gatewayapi')
    ],
    [
      'id' => 'post/approved',
      'action' => 'post_approved',
      'name' => __('Post approved', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post approved - Fires when Post is approved', 'gatewayapi')
    ],
    [
      'id' => 'taxonomy/created',
      'action' => 'taxonomy_created',
      'name' => __('Taxonomy created', 'gatewayapi'),
      'group' => __('Taxonomy', 'gatewayapi'),
      'description' => __('Taxonomy term created - Fires when Taxonomy is created', 'gatewayapi')
    ],
    [
      'id' => 'taxonomy/updated',
      'action' => 'taxonomy_updated',
      'name' => __('Taxonomy updated', 'gatewayapi'),
      'group' => __('Taxonomy', 'gatewayapi'),
      'description' => __('Taxonomy term updated - Fires when Taxonomy is updated', 'gatewayapi')
    ],
    [
      'id' => 'taxonomy/deleted',
      'action' => 'taxonomy_deleted',
      'name' => __('Taxonomy deleted', 'gatewayapi'),
      'group' => __('Taxonomy', 'gatewayapi'),
      'description' => __('Taxonomy term deleted - Fires when Taxonomy is deleted', 'gatewayapi')
    ],
    [
      'id' => 'user/login',
      'action' => 'user_login',
      'name' => __('User login', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User login - Fires when user log into WordPress', 'gatewayapi')
    ],
    [
      'id' => 'user/logout',
      'action' => 'user_logout',
      'name' => __('User logout', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User logout - Fires when user log out from WordPress', 'gatewayapi')
    ],
    [
      'id' => 'user/registered',
      'action' => 'user_registered',
      'name' => __('User registration', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User registration - Fires when user registers new account', 'gatewayapi')
    ],
    [
      'id' => 'user/profile_updated',
      'action' => 'user_profile_updated',
      'name' => __('User profile updated', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User profile updated - Fires when user updates his profile', 'gatewayapi')
    ],
    [
      'id' => 'user/deleted',
      'action' => 'user_deleted',
      'name' => __('User deleted', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User deleted - Fires when user account is deleted', 'gatewayapi')
    ],
    [
      'id' => 'user/password_changed',
      'action' => 'user_password_changed',
      'name' => __('User password changed', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User password changed - Fires when user changed his password', 'gatewayapi')
    ],
    [
      'id' => 'user/password_reset_request',
      'action' => 'user_password_reset_request',
      'name' => __('User password reset request', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User password reset request - Fires when user requests password change', 'gatewayapi')
    ],
    [
      'id' => 'user/login_failed',
      'action' => 'user_login_failed',
      'name' => __('User login failed', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User login failed - Fires when user login failed', 'gatewayapi')
    ],
    [
      'id' => 'user/role_changed',
      'action' => 'user_role_changed',
      'name' => __('User role changed', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User role changed - Fires when user role changes', 'gatewayapi')
    ],
    [
      'id' => 'comment/published',
      'action' => 'comment_published',
      'name' => __('Comment published', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment published - Fires when new Comment is published on the website. Includes comment replies.', 'gatewayapi')
    ],
    [
      'id' => 'comment/added',
      'action' => 'comment_added',
      'name' => __('Comment added', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment added - Fires when new Comment is added to database and awaits moderation or is published. Includes comment replies.', 'gatewayapi')
    ],
    [
      'id' => 'comment/replied',
      'action' => 'comment_replied',
      'name' => __('Comment replied', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment replied - Fires when Comment is replied and the reply is approved', 'gatewayapi')
    ],
    [
      'id' => 'comment/approved',
      'action' => 'comment_approved',
      'name' => __('Comment approved', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment approved - Fires when Comment is approved', 'gatewayapi')
    ],
    [
      'id' => 'comment/unapproved',
      'action' => 'comment_unapproved',
      'name' => __('Comment unapproved', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment unapproved - Fires when Comment is marked as unapproved', 'gatewayapi')
    ],
    [
      'id' => 'comment/spammed',
      'action' => 'comment_spammed',
      'name' => __('Comment spammed', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment spammed - Fires when Comment is marked as spam', 'gatewayapi')
    ],
    [
      'id' => 'comment/trashed',
      'action' => 'comment_trashed',
      'name' => __('Comment trashed', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment trashed - Fires when Comment is trashed', 'gatewayapi')
    ],
    [
      'id' => 'wordpress/updates_available',
      'action' => 'wordpress_updates_available',
      'name' => __('Updates available', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Available updates - Fires periodically when new updates are available', 'gatewayapi')
    ],
    [
      'id' => 'plugin/activated',
      'action' => 'plugin_activated',
      'name' => __('Plugin activated', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Plugin activated - Fires when plugin is activated', 'gatewayapi')
    ],
    [
      'id' => 'plugin/deactivated',
      'action' => 'plugin_deactivated',
      'name' => __('Plugin deactivated', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Plugin deactivated - Fires when plugin is deactivated', 'gatewayapi')
    ],
    [
      'id' => 'plugin/updated',
      'action' => 'plugin_updated',
      'name' => __('Plugin updated', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Plugin updated - Fires when plugin is updated', 'gatewayapi')
    ],
    [
      'id' => 'plugin/installed',
      'action' => 'plugin_installed',
      'name' => __('Plugin installed', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Plugin installed - Fires when plugin is installed', 'gatewayapi')
    ],
    [
      'id' => 'plugin/removed',
      'action' => 'plugin_removed',
      'name' => __('Plugin removed', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Plugin removed - Fires when plugin is deleted', 'gatewayapi')
    ],
  ];

  $return = [];

  foreach ($triggers as $trigger) {
    $return[] = new OnlineCity\GatewayAPI\Trigger($trigger);
  }

  return $return;

}


/**
 * Gets all registered triggers in a grouped array
 *
 * @return array grouped triggers
 * @since  5.0.0
 */
function _gwapi_get_triggers_grouped()
{

  $return = [];

  foreach (_gwapi_get_triggers() as $trigger) {

    if (!isset($return[$trigger->getGroup()])) {
      $return[$trigger->getGroup()] = [];
    }

    $return[$trigger->getGroup()][$trigger->getName()] = $trigger;
  }
  return $return;
}

/**
 * Return Trigger otherwise null
 *
 * @return Trigger|null
 * @since  5.0.0
 */
function _gwapi_get_trigger_by_id($id)
{

  foreach (_gwapi_get_triggers() as $trigger) {

    if ($trigger->getId() === $id) {
      return $trigger;
    }
  }
  return null;
}
