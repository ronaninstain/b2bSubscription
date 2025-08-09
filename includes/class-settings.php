<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    public function init(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerMenu(): void
    {
        // Show under WooCommerce menu instead of Settings
        add_submenu_page(
            'woocommerce',
            __('B2B Course Subscriptions', 'b2b-course-subscriptions'),
            __('B2B Course Subscriptions', 'b2b-course-subscriptions'),
            'manage_woocommerce',
            'b2b-course-subscriptions',
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting('b2b_cs_settings_group', 'b2b_cs_api_base_url');
        register_setting('b2b_cs_settings_group', 'b2b_cs_client_id');
        register_setting('b2b_cs_settings_group', 'b2b_cs_secret_key');
        register_setting('b2b_cs_settings_group', 'b2b_cs_enable_parent_sync');
        register_setting('b2b_cs_settings_group', 'b2b_cs_parent_enroll_endpoint');
        register_setting('b2b_cs_settings_group', 'b2b_cs_subscription_product_ids');
        register_setting('b2b_cs_settings_group', 'b2b_cs_ptc_table');
        register_setting('b2b_cs_settings_group', 'b2b_cs_ptc_course_column');
        register_setting('b2b_cs_settings_group', 'b2b_cs_ptc_product_column');
        register_setting('b2b_cs_settings_group', 'b2b_cs_debug');
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $apiBase = esc_url(get_option('b2b_cs_api_base_url', 'https://course-dashboard.com'));
        $clientId = esc_attr(get_option('b2b_cs_client_id', ''));
        $secretKey = esc_attr(get_option('b2b_cs_secret_key', ''));
        $enableSync = (bool) get_option('b2b_cs_enable_parent_sync', false);
        $enrollPath = esc_attr(get_option('b2b_cs_parent_enroll_endpoint', '/wp-json/custom/v1/enroll-user'));
        $subscriptionProducts = esc_attr(get_option('b2b_cs_subscription_product_ids', ''));
        $ptcTable   = esc_attr(get_option('b2b_cs_ptc_table', 'ptc_items'));
        $ptcCourseC = esc_attr(get_option('b2b_cs_ptc_course_column', 'course_id'));
        $ptcProdC   = esc_attr(get_option('b2b_cs_ptc_product_column', 'product_id'));
        $debug      = (bool) get_option('b2b_cs_debug', false);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('B2B Course Subscriptions', 'b2b-course-subscriptions'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('b2b_cs_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="b2b_cs_api_base_url">API Base URL</label></th>
                        <td><input type="url" class="regular-text" name="b2b_cs_api_base_url" id="b2b_cs_api_base_url" value="<?php echo $apiBase; ?>" placeholder="https://course-dashboard.com" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_client_id">Client ID</label></th>
                        <td><input type="text" class="regular-text" name="b2b_cs_client_id" id="b2b_cs_client_id" value="<?php echo $clientId; ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_secret_key">Secret Key</label></th>
                        <td><input type="text" class="regular-text" name="b2b_cs_secret_key" id="b2b_cs_secret_key" value="<?php echo $secretKey; ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_enable_parent_sync">Enable Parent WPLMS Enrollment Sync</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="b2b_cs_enable_parent_sync" id="b2b_cs_enable_parent_sync" value="1" <?php checked($enableSync, true); ?> />
                                When orders complete, enroll the buyer on the parent WPLMS site.
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_parent_enroll_endpoint">Parent Enrollment Endpoint</label></th>
                        <td><input type="text" class="regular-text" name="b2b_cs_parent_enroll_endpoint" id="b2b_cs_parent_enroll_endpoint" value="<?php echo $enrollPath; ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_subscription_product_ids">Subscription Product IDs</label></th>
                        <td>
                            <input type="text" class="regular-text" name="b2b_cs_subscription_product_ids" id="b2b_cs_subscription_product_ids" value="<?php echo $subscriptionProducts; ?>" placeholder="e.g. 123,456" />
                            <p class="description">Comma-separated product IDs that act as an all-courses subscription pass. On purchase, access is granted to all mapped courses for the chosen duration.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_ptc_table">Mapping Table Name</label></th>
                        <td>
                            <input type="text" class="regular-text" name="b2b_cs_ptc_table" id="b2b_cs_ptc_table" value="<?php echo $ptcTable; ?>" placeholder="ptc_items" />
                            <p class="description">Table under the current DB prefix that maps courses to products.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_ptc_course_column">Course ID Column</label></th>
                        <td><input type="text" class="regular-text" name="b2b_cs_ptc_course_column" id="b2b_cs_ptc_course_column" value="<?php echo $ptcCourseC; ?>" placeholder="course_id" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_ptc_product_column">Product ID Column</label></th>
                        <td><input type="text" class="regular-text" name="b2b_cs_ptc_product_column" id="b2b_cs_ptc_product_column" value="<?php echo $ptcProdC; ?>" placeholder="product_id" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="b2b_cs_debug">Enable Debug Logging</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="b2b_cs_debug" id="b2b_cs_debug" value="1" <?php checked($debug, true); ?> />
                                Log plugin actions to PHP error log (wp-content/debug.log when WP_DEBUG_LOG is enabled).
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}


