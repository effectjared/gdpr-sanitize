<?php

if (!class_exists('WP_CLI')) {
    return;
}

/**
 * Deletes data that may be regulated by the GDPR including non-admin WP users, Comments, Gravity Forms entries and WooCommerce orders.
 *
 * @when before_wp_load
 */
class GdprSanitize {

    public function __invoke( $args ) {

        if (function_exists('env')) {
            if (env('ENVIRONMENT') === 'production') {
                WP_CLI::error('Sorry I will not run if your .env file is set to production.' );
                return;
            }
        } else {

            WP_CLI::confirm('Are you running this command on staging or local?', []);

        }

        WP_CLI::confirm( 'Are you sure want to delete all non-admin WordPress users, Gravity Form entries (if installed) and WooCommerce orders (if installed)?', [] );

        WP_CLI::warning( 'Any email notifications using wp_mail() will be sent to :blackhole: during this process' );

        add_filter('wp_mail', function($args) {
             $args['to'] = ':blackhole:';
             return $args;
        }, 99999);

        if (!is_multisite()) {
            $this->delete();
        } else {
            $mainSite = get_current_blog_id();
            $sites = get_sites(['number' => 9999999]);

            foreach ($sites as $site) {
                WP_CLI::warning( 'Starting deletion for site ' . $site->domain );

                switch_to_blog( $site->blog_id );
                $this->delete();

            }

            WP_CLI::log('---- Start Non-Admin Network User Sanitization');
            switch_to_blog( $mainSite );
            $this->deleteNetworkUsers();
            WP_CLI::log('---- End Non-Admin Network User Sanitization');

        }

    }

    function delete()
    {
        WP_CLI::log('---- Start Non-Admin User Sanitization');
        $this->deleteUsers();
        WP_CLI::log('---- End Non-Admin User Sanitization');


        WP_CLI::log('---- Start Comment Sanitization');
        $this->deleteComments();
        WP_CLI::log('---- End Comment Sanitization');

        WP_CLI::log('---- Start Gravity Forms Sanitization');
        $this->deleteGravityFormsEntries();
        WP_CLI::log('---- End Gravity Forms Sanitization');


        WP_CLI::log('---- Start WooCommerce Sanitization');
        $this->deleteWooCommerceOrders();
        WP_CLI::log('---- End WooCommerce Sanitization');
    }


    function deleteUsers()
    {

        $adminUsers = get_users(['role__in' => ['administrator']]);

        if (count($adminUsers) === 0) {
            WP_CLI::error('Can\'t proceed there are no admin users to re-assign content to' );
            return;
        }

        $exemptRoles = apply_filters('gdpr_sanitize_exempt_user_roles', ['administrator']);

        $users = get_users(['role__not_in' => $exemptRoles]);
        if (count($users) === 0) {
            WP_CLI::success( 'All non-admin users already deleted' );
            return;
        }

        $deletedUsers = 0;

        foreach ($users as $user) {
            if (wp_delete_user($user->ID, $adminUsers[0]->ID)) {
                $deletedUsers++;
            }
        }

        if ($deletedUsers === count($users)) {
            WP_CLI::success(sprintf('Deleted %d non-admin users', $deletedUsers));
        } else {
            WP_CLI::error(sprintf('Deleted %d of %d non-admin users', $deletedUsers, count($users)));
        }

    }

    function deleteComments()
    {

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}commentmeta" );
        $wpdb->query("DELETE FROM {$wpdb->prefix}comments" );

        WP_CLI::success( 'Comments deleted' );


    }

    function deleteNetworkUsers()
    {

        global $wpdb;
        $users = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}users as users WHERE users.ID NOT IN (SELECT user_id FROM {$wpdb->prefix}usermeta WHERE {$wpdb->prefix}usermeta.meta_key LIKE '%_capabilities' AND {$wpdb->prefix}usermeta.meta_value  LIKE '%administrator%')", OBJECT );

        if (count($users) === 0) {
            WP_CLI::success( 'All non-admin network users already deleted' );
            return;
        }

        $deletedUsers = 0;

        foreach ($users as $user) {
            if (wpmu_delete_user($user->ID)) {
                $deletedUsers++;
            }
        }

        if ($deletedUsers === count($users)) {
            WP_CLI::success(sprintf('Deleted %d non-admin network users', $deletedUsers));
        } else {
            WP_CLI::error(sprintf('Deleted %d of %d non-admin network users', $deletedUsers, count($users)));
        }

    }

    function deleteGravityFormsEntries()
    {

        if (! is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
            WP_CLI::warning( 'Gravity Forms is not installed or not activated' );
            return;
        }

        $forms = array_merge( \GFAPI::get_forms(false, true),  \GFAPI::get_forms());

        WP_CLI::log(sprintf('%d forms to delete entries from', count($forms)));

        foreach ( $forms as $form ) {
            GFFormsModel::delete_leads_by_form( $form['id'] );
        }

        WP_CLI::success(sprintf('Deleted All Gravity Forms Entries'));

    }

    function deleteWooCommerceOrders()
    {
        if (! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            WP_CLI::warning( 'WooCommerce is not installed or not activated' );
            return;
        }

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta" );
        $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_order_items" );
      //  $wpdb->query("DELETE FROM {$wpdb->prefix}commentmeta WHERE comment_id IN (SELECT comment_ID FROM {$wpdb->prefix}comments WHERE comment_type = 'order_note')" ); comments have already been deleted...
      //  $wpdb->query("DELETE FROM {$wpdb->prefix}comments WHERE comment_type = 'order_note'" );  comments have already been deleted...
        $wpdb->query("DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ( SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'shop_order')" );
        $wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_type = 'shop_order'" );

        WP_CLI::success( 'WooCommerce Orders deleted' );


    }

}
WP_CLI::add_command('gdpr-sanitize', 'GdprSanitize');
