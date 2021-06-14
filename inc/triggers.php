<?php

use OnlineCity\GatewayAPI\Trigger;

function _gwapi_get_triggers()
{

  $triggers = [
    [
      'id' => 'post/added',
      'action' => 'wp_after_insert_post',
      'name' => __('Post added', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post added - Fires when Post is added to database. Useful when adding posts programatically or for 3rd party integration', 'gatewayapi')
      ],
    [
      'id' => 'post/drafted',
      'action' => 'transition_post_status',
      'name' => __('Post saved as draft', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post saved as a draft - Fires when Post is saved as a draft', 'gatewayapi')
    ],
    [
      'id' => 'post/published',
      'action' => 'transition_post_status',
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
      'action' => 'transition_post_status',
      'name' => __('Post sent', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post sent for review - Fires when Post is sent for review', 'gatewayapi')
    ],
    [
      'id' => 'post/scheduled',
      'action' => 'transition_post_status',
      'name' => __('Post scheduled', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post scheduled - Fires when Post is scheduled', 'gatewayapi')
    ],
    [
      'id' => 'post/trashed',
      'action' => 'transition_post_status',
      'name' => __('Post trashed', 'gatewayapi'),
      'group' => __('Post', 'gatewayapi'),
      'description' => __('Post trashed - Fires when Post is moved to trash', 'gatewayapi')
    ],
    [
      'id' => 'taxonomy/created',
      'action' => 'created_term',
      'name' => __('Term created', 'gatewayapi'),
      'group' => __('Taxonomy', 'gatewayapi'),
      'description' => __('Taxonomy term created - Fires when Taxonomy is created', 'gatewayapi')
    ],
    [
      'id' => 'taxonomy/updated',
      'action' => 'edited_term',
      'name' => __('Term updated', 'gatewayapi'),
      'group' => __('Taxonomy', 'gatewayapi'),
      'description' => __('Taxonomy term updated - Fires when Taxonomy is updated', 'gatewayapi')
    ],
    [
      'id' => 'taxonomy/deleted',
      'action' => 'delete_term',
      'name' => __('Term deleted', 'gatewayapi'),
      'group' => __('Taxonomy', 'gatewayapi'),
      'description' => __('Taxonomy term deleted - Fires when Taxonomy is deleted', 'gatewayapi')
    ],
    [
      'id' => 'user/login',
      'action' => 'wp_login',
      'name' => __('User login (success)', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User login - Fires when user has succesfully logged in into WordPress', 'gatewayapi')
    ],
    [
      'id' => 'user/logout',
      'action' => 'wp_logout',
      'name' => __('User logout', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User logout - Fires when user log out from WordPress', 'gatewayapi')
    ],
    [
      'id' => 'user/registered',
      'action' => 'user_register',
      'name' => __('User registration', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User registration - Fires when user registers new account', 'gatewayapi')
    ],
    [
      'id' => 'user/profile_updated',
      'action' => 'profile_update',
      'name' => __('User profile updated', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User profile updated - Fires when user updates his profile', 'gatewayapi')
    ],
    [
      'id' => 'user/deleted',
      'action' => 'deleted_user',
      'name' => __('User deleted', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User deleted - Fires when user account is deleted', 'gatewayapi')
    ],
    [
      'id' => 'user/password_reset_request',
      'action' => 'retrieve_password_key',
      'name' => __('User password reset request', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User password reset request - Fires when user uses "Forgot Password"', 'gatewayapi')
    ],
    [
      'id' => 'user/password_reset_done',
      'action' => 'password_reset',
      'name' => __('User password reset complete', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User password reset - Fires when user changed password by using "Forgot Password"', 'gatewayapi')
    ],
    [
      'id' => 'user/login_failed',
      'action' => 'wp_login_failed',
      'name' => __('User login failed', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User login failed - Fires when user login failed', 'gatewayapi')
    ],
    [
      'id' => 'user/role_changed',
      'action' => 'set_user_role',
      'name' => __('User role changed', 'gatewayapi'),
      'group' => __('User', 'gatewayapi'),
      'description' => __('User role changed - Fires when user role changes', 'gatewayapi')
    ],
    [
      'id' => 'comment/published',
      'action' => 'transition_comment_status_approved',
      'name' => __('Comment published/approved', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment published/approved - Fires when new Comment is published on the website. Includes comment replies.', 'gatewayapi')
    ],
    [
      'id' => 'comment/spammed',
      'action' => 'spammed_comment',
      'name' => __('Comment spammed', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment spammed - Fires when Comment is marked as spam', 'gatewayapi')
    ],
    [
      'id' => 'comment/trashed',
      'action' => 'trashed_comment',
      'name' => __('Comment trashed', 'gatewayapi'),
      'group' => __('Comment', 'gatewayapi'),
      'description' => __('Comment trashed - Fires when Comment is trashed', 'gatewayapi')
    ],
    [
      'id' => 'plugin/activated',
      'action' => 'activated_plugin',
      'name' => __('Plugin activated', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Plugin activated - Fires when plugin is activated', 'gatewayapi')
    ],
    [
      'id' => 'plugin/deactivated',
      'action' => 'deactivated_plugin',
      'name' => __('Plugin deactivated', 'gatewayapi'),
      'group' => __('Wordpress', 'gatewayapi'),
      'description' => __('Plugin deactivated - Fires when plugin is deactivated', 'gatewayapi')
    ],
    [
      'id' => 'plugin/removed',
      'action' => 'deleted_plugin',
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
