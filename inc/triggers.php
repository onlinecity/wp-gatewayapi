<?php

use OnlineCity\GatewayAPI\Trigger;

function _gwapi_get_triggers() {

    $triggers = [
      [
        'id'          => 'post/added',
        'action'      => 'post_added',
        'name'        => 'Post added',
        'group'       => 'Post',
        'description' => 'Post added - Fires when Post is added to database. Useful when adding posts programatically or for 3rd party integration',
      ],
      [
        'id'          => 'post/drafted',
        'action'      => 'post_drafted',
        'name'        => 'Post saved',
        'group'       => 'Post',
        'description' => 'Post saved as a draft - Fires when Post is saved as a draft',
      ],
      [
        'id'          => 'post/published',
        'action'      => 'post_published',
        'name'        => 'Post published',
        'group'       => 'Post',
        'description' => 'Post published - Fires when Post is published',
      ],
      [
        'id'          => 'post/updated',
        'action'      => 'post_updated',
        'name'        => 'Post updated',
        'group'       => 'Post',
        'description' => 'Post updated - Fires when Post is updated',
      ],
      [
        'id'          => 'post/pending',
        'action'      => 'post_pending',
        'name'        => 'Post sent',
        'group'       => 'Post',
        'description' => 'Post sent for review - Fires when Post is sent for review',
      ],
      [
        'id'          => 'post/scheduled',
        'action'      => 'post_scheduled',
        'name'        => 'Post scheduled',
        'group'       => 'Post',
        'description' => 'Post scheduled - Fires when Post is scheduled',
      ],
      [
        'id'          => 'post/trashed',
        'action'      => 'post_trashed',
        'name'        => 'Post trashed',
        'group'       => 'Post',
        'description' => 'Post trashed - Fires when Post is moved to trash',
      ],
      [
        'id'          => 'post/approved',
        'action'      => 'post_approved',
        'name'        => 'Post approved',
        'group'       => 'Post',
        'description' => 'Post approved - Fires when Post is approved',
      ],
      [
        'id'          => 'taxonomy/created',
        'action'      => 'taxonomy_created',
        'name'        => 'Taxonomy created',
        'group'       => 'Taxonomy',
        'description' => 'Taxonomy term created - Fires when Taxonomy is created',
      ],
      [
        'id'          => 'taxonomy/updated',
        'action'      => 'taxonomy_updated',
        'name'        => 'Taxonomy updated',
        'group'       => 'Taxonomy',
        'description' => 'Taxonomy term updated - Fires when Taxonomy is updated',
      ],
      [
        'id'          => 'taxonomy/deleted',
        'action'      => 'taxonomy_deleted',
        'name'        => 'Taxonomy deleted',
        'group'       => 'Taxonomy',
        'description' => 'Taxonomy term deleted - Fires when Taxonomy is deleted',
      ],
      [
        'id'          => 'user/login',
        'action'      => 'user_login',
        'name'        => 'User login',
        'group'       => 'User',
        'description' => 'User login - Fires when user log into WordPress',
      ],
      [
        'id'          => 'user/logout',
        'action'      => 'user_logout',
        'name'        => 'User logout',
        'group'       => 'User',
        'description' => 'User logout - Fires when user log out from WordPress',
      ],
      [
        'id'          => 'user/registered',
        'action'      => 'user_registered',
        'name'        => 'User registration',
        'group'       => 'User',
        'description' => 'User registration - Fires when user registers new account',
      ],
      [
        'id'          => 'user/profile_updated',
        'action'      => 'user_profile_updated',
        'name'        => 'User profile updated',
        'group'       => 'User',
        'description' => 'User profile updated - Fires when user updates his profile',
      ],
      [
        'id'          => 'user/deleted',
        'action'      => 'user_deleted',
        'name'        => 'User deleted',
        'group'       => 'User',
        'description' => 'User deleted - Fires when user account is deleted',
      ],
      [
        'id'          => 'user/password_changed',
        'action'      => 'user_password_changed',
        'name'        => 'User password changed',
        'group'       => 'User',
        'description' => 'User password changed - Fires when user changed his password',
      ],
      [
        'id'          => 'user/password_reset_request',
        'action'      => 'user_password_reset_request',
        'name'        => 'User password reset request',
        'group'       => 'User',
        'description' => 'User password reset request - Fires when user requests password change',
      ],
      [
        'id'          => 'user/login_failed',
        'action'      => 'user_login_failed',
        'name'        => 'User login failed',
        'group'       => 'User',
        'description' => 'User login failed - Fires when user login failed',
      ],
      [
        'id'          => 'user/role_changed',
        'action'      => 'user_role_changed',
        'name'        => 'User role changed',
        'group'       => 'User',
        'description' => 'User role changed - Fires when user role changes',
      ],
      [
        'id'          => 'comment/published',
        'action'      => 'comment_published',
        'name'        => 'Comment published',
        'group'       => 'Comment',
        'description' => 'Comment published - Fires when new Comment is published on the website. Includes comment replies.',
      ],
      [
        'id'          => 'comment/added',
        'action'      => 'comment_added',
        'name'        => 'Comment added',
        'group'       => 'Comment',
        'description' => 'Comment added - Fires when new Comment is added to database and awaits moderation or is published. Includes comment replies.',
      ],
      [
        'id'          => 'comment/replied',
        'action'      => 'comment_replied',
        'name'        => 'Comment replied',
        'group'       => 'Comment',
        'description' => 'Comment replied - Fires when Comment is replied and the reply is approved',
      ],
      [
        'id'          => 'comment/approved',
        'action'      => 'comment_approved',
        'name'        => 'Comment approved',
        'group'       => 'Comment',
        'description' => 'Comment approved - Fires when Comment is approved',
      ],
      [
        'id'          => 'comment/unapproved',
        'action'      => 'comment_unapproved',
        'name'        => 'Comment unapproved',
        'group'       => 'Comment',
        'description' => 'Comment unapproved - Fires when Comment is marked as unapproved',
      ],
      [
        'id'          => 'comment/spammed',
        'action'      => 'comment_spammed',
        'name'        => 'Comment spammed',
        'group'       => 'Comment',
        'description' => 'Comment spammed - Fires when Comment is marked as spam',
      ],
      [
        'id'          => 'comment/trashed',
        'action'      => 'comment_trashed',
        'name'        => 'Comment trashed',
        'group'       => 'Comment',
        'description' => 'Comment trashed - Fires when Comment is trashed',
      ],
      [
        'id'          => 'wordpress/updates_available',
        'action'      => 'wordpress_updates_available',
        'name'        => 'Updates available',
        'group'       => 'Wordpress',
        'description' => 'Available updates - Fires periodically when new updates are available',
      ],
      [
        'id'          => 'plugin/activated',
        'action'      => 'plugin_activated',
        'name'        => 'Plugin activated',
        'group'       => 'Wordpress',
        'description' => 'Plugin activated - Fires when plugin is activated',
      ],
      [
        'id'          => 'plugin/deactivated',
        'action'      => 'plugin_deactivated',
        'name'        => 'Plugin deactivated',
        'group'       => 'Wordpress',
        'description' => 'Plugin deactivated - Fires when plugin is deactivated',
      ],
      [
        'id'          => 'plugin/updated',
        'action'      => 'plugin_updated',
        'name'        => 'Plugin updated',
        'group'       => 'Wordpress',
        'description' => 'Plugin updated - Fires when plugin is updated',
      ],
      [
        'id'          => 'plugin/installed',
        'action'      => 'plugin_installed',
        'name'        => 'Plugin installed',
        'group'       => 'Wordpress',
        'description' => 'Plugin installed - Fires when plugin is installed',
      ],
      [
        'id'          => 'plugin/removed',
        'action'      => 'plugin_removed',
        'name'        => 'Plugin removed',
        'group'       => 'Wordpress',
        'description' => 'Plugin removed - Fires when plugin is deleted',
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
function _gwapi_get_triggers_grouped() {

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
function _gwapi_get_trigger_by_id($id) {

    foreach (_gwapi_get_triggers() as $trigger) {

        if ($trigger->getId() === $id) {
            return $trigger;
        }
    }
    return null;
}
