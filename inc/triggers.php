<?php

use OnlineCity\GatewayAPI\Trigger;

function _gwapi_get_triggers() {

    $triggers = [
      [
        'id'          => 'post/post/added',
        'action'      => 'post_added',
        'name'        => 'Post added',
        'group'       => 'Post',
        'description' => 'Post added - Fires when Post (post) is added to database. Useful when adding posts programatically or for 3rd party integration',
      ],
      [
        'id'          => 'post/post/drafted',
        'action'      => 'post_drafted',
        'name'        => 'Post saved',
        'group'       => 'Post',
        'description' => 'Post saved as a draft - Fires when Post (post) is saved as a draft',
      ],
      [
        'id'          => 'post/post/published',
        'action'      => 'post_published',
        'name'        => 'Post published',
        'group'       => 'Post',
        'description' => 'Post published - Fires when Post (post) is published',
      ],
      [
        'id'          => 'post/post/updated',
        'action'      => 'post_updated',
        'name'        => 'Post updated',
        'group'       => 'Post',
        'description' => 'Post updated - Fires when Post (post) is updated',
      ],
      [
        'id'          => 'post/post/pending',
        'action'      => 'post_pending',
        'name'        => 'Post sent',
        'group'       => 'Post',
        'description' => 'Post sent for review - Fires when Post (post) is sent for review',
      ],
      [
        'id'          => 'post/post/scheduled',
        'action'      => 'post_scheduled',
        'name'        => 'Post scheduled',
        'group'       => 'Post',
        'description' => 'Post scheduled - Fires when Post (post) is scheduled',
      ],
      [
        'id'          => 'post/post/trashed',
        'action'      => 'post_trashed',
        'name'        => 'Post trashed',
        'group'       => 'Post',
        'description' => 'Post trashed - Fires when Post (post) is moved to trash',
      ],
      [
        'id'          => 'post/post/approved',
        'action'      => 'post_approved',
        'name'        => 'Post approved',
        'group'       => 'Post',
        'description' => 'Post approved - Fires when Post (post) is approved',
      ],
      [
        'id'          => 'post/page/added',
        'action'      => 'post_added',
        'name'        => 'Page added',
        'group'       => 'Page',
        'description' => 'Page added - Fires when Page (page) is added to database. Useful when adding posts programatically or for 3rd party integration',
      ],
      [
        'id'          => 'post/page/drafted',
        'action'      => 'post_drafted',
        'name'        => 'Page saved',
        'group'       => 'Page',
        'description' => 'Page saved as a draft - Fires when Page (page) is saved as a draft',
      ],
      [
        'id'          => 'post/page/published',
        'action'      => 'post_published',
        'name'        => 'Page published',
        'group'       => 'Page',
        'description' => 'Page published - Fires when Page (page) is published',
      ],
      [
        'id'          => 'post/page/updated',
        'action'      => 'post_updated',
        'name'        => 'Page updated',
        'group'       => 'Page',
        'description' => 'Page updated - Fires when Page (page) is updated',
      ],
      [
        'id'          => 'post/page/pending',
        'action'      => 'post_pending',
        'name'        => 'Page sent',
        'group'       => 'Page',
        'description' => 'Page sent for review - Fires when Page (page) is sent for review',
      ],
      [
        'id'          => 'post/page/scheduled',
        'action'      => 'post_scheduled',
        'name'        => 'Page scheduled',
        'group'       => 'Page',
        'description' => 'Page scheduled - Fires when Page (page) is scheduled',
      ],
      [
        'id'          => 'post/page/trashed',
        'action'      => 'post_trashed',
        'name'        => 'Page trashed',
        'group'       => 'Page',
        'description' => 'Page trashed - Fires when Page (page) is moved to trash',
      ],
      [
        'id'          => 'post/page/approved',
        'action'      => 'post_approved',
        'name'        => 'Page approved',
        'group'       => 'Page',
        'description' => 'Page approved - Fires when Page (page) is approved',
      ],
      [
        'id'          => 'taxonomy/category/created',
        'action'      => 'taxonomy_created',
        'name'        => 'Category created',
        'group'       => 'Category',
        'description' => 'Category term created - Fires when Category (category) is created',
      ],
      [
        'id'          => 'taxonomy/category/updated',
        'action'      => 'taxonomy_updated',
        'name'        => 'Category updated',
        'group'       => 'Category',
        'description' => 'Category term updated - Fires when Category (category) is updated',
      ],
      [
        'id'          => 'taxonomy/category/deleted',
        'action'      => 'taxonomy_deleted',
        'name'        => 'Category deleted',
        'group'       => 'Category',
        'description' => 'Category term deleted - Fires when Category (category) is deleted',
      ],
      [
        'id'          => 'taxonomy/post_tag/created',
        'action'      => 'taxonomy_created',
        'name'        => 'Tag created',
        'group'       => 'Tag',
        'description' => 'Tag term created - Fires when Tag (post_tag) is created',
      ],
      [
        'id'          => 'taxonomy/post_tag/updated',
        'action'      => 'taxonomy_updated',
        'name'        => 'Tag updated',
        'group'       => 'Tag',
        'description' => 'Tag term updated - Fires when Tag (post_tag) is updated',
      ],
      [
        'id'          => 'taxonomy/post_tag/deleted',
        'action'      => 'taxonomy_deleted',
        'name'        => 'Tag deleted',
        'group'       => 'Tag',
        'description' => 'Tag term deleted - Fires when Tag (post_tag) is deleted',
      ],
      [
        'id'          => 'taxonomy/gwapi-recipient-groups/created',
        'action'      => 'taxonomy_created',
        'name'        => 'Recipient group created',
        'group'       => 'Recipient group',
        'description' => 'Recipient group term created - Fires when Recipient group (gwapi-recipient-groups) is created',
      ],
      [
        'id'          => 'taxonomy/gwapi-recipient-groups/updated',
        'action'      => 'taxonomy_updated',
        'name'        => 'Recipient group updated',
        'group'       => 'Recipient group',
        'description' => 'Recipient group term updated - Fires when Recipient group (gwapi-recipient-groups) is updated',
      ],
      [
        'id'          => 'taxonomy/gwapi-recipient-groups/deleted',
        'action'      => 'taxonomy_deleted',
        'name'        => 'Recipient group deleted',
        'group'       => 'Recipient group',
        'description' => 'Recipient group term deleted - Fires when Recipient group (gwapi-recipient-groups) is deleted',
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
      //      [
      //        'id' => 'media/added',
      //       'action' => 'media_added',
      //        'group' => 'Media',
      //        'description' => 'Media added - Fires when new attachment is added'
      //      ],
      //      [
      //        'id' => 'media/updated',
      //       'action' => 'media_updated',
      //        'group' => 'Media',
      //        'description' => 'Media updated - Fires when attachment is updated'
      //      ],
      //      [
      //        'id' => 'media/trashed',
      //       'action' => 'media_trashed',
      //        'group' => 'Media',
      //        'description' => 'Media trashed - Fires when attachment is removed'
      //      ],
      [
        'id'          => 'comment/comment/published',
        'action'      => 'comment_published',
        'name'        => 'Comment published',
        'group'       => 'Comment',
        'description' => 'Comment published - Fires when new Comment is published on the website. Includes comment replies.',
      ],
      [
        'id'          => 'comment/comment/added',
        'action'      => 'comment_added',
        'name'        => 'Comment added',
        'group'       => 'Comment',
        'description' => 'Comment added - Fires when new Comment is added to database and awaits moderation or is published. Includes comment replies.',
      ],
      [
        'id'          => 'comment/comment/replied',
        'action'      => 'comment_replied',
        'name'        => 'Comment replied',
        'group'       => 'Comment',
        'description' => 'Comment replied - Fires when Comment is replied and the reply is approved',
      ],
      [
        'id'          => 'comment/comment/approved',
        'action'      => 'comment_approved',
        'name'        => 'Comment approved',
        'group'       => 'Comment',
        'description' => 'Comment approved - Fires when Comment is approved',
      ],
      [
        'id'          => 'comment/comment/unapproved',
        'action'      => 'comment_unapproved',
        'name'        => 'Comment unapproved',
        'group'       => 'Comment',
        'description' => 'Comment unapproved - Fires when Comment is marked as unapproved',
      ],
      [
        'id'          => 'comment/comment/spammed',
        'action'      => 'comment_spammed',
        'name'        => 'Comment spammed',
        'group'       => 'Comment',
        'description' => 'Comment spammed - Fires when Comment is marked as spam',
      ],
      [
        'id'          => 'comment/comment/trashed',
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
      //      [
      //        'theme/switched',
      //        'Theme switched - Fires when theme is switched'
      //      ],
      //      [
      //        'theme/updated',
      //        'Theme updated - Fires when theme is updated'
      //      ],
      //      [
      //        'theme/installed',
      //        'Theme installed - Fires when theme is installed'
      //      ],
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
