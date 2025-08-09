<?php

namespace B2B\CourseSubscriptions;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function activate(): void
    {
        self::createSubscriptionsTable();
        // Default options
        add_option('b2b_cs_api_base_url', 'https://course-dashboard.com');
        add_option('b2b_cs_client_id', '');
        add_option('b2b_cs_secret_key', '');
    }

    private static function createSubscriptionsTable(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'b2b_course_subscriptions';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            expires_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_course (user_id, course_id),
            KEY idx_expires (expires_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}


