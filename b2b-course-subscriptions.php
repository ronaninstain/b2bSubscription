<?php

/**
 * Plugin Name: B2B Course Subscriptions
 * Description: WooCommerce-based subscriptions for external courses from Course Dashboard. Maps courses to products via `ptc_items`, provides Ajax price lookup, and creates time-bound access for learners.
 * Version: 1.1.0
 * Author: Shoive Hossain
 * Author URI: https://github.com/ronaninstain/
 * License: GPLv2 or later
 * Text Domain: b2b-course-subscriptions
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('B2B_COURSE_SUBSCRIPTIONS_VERSION', '1.0.0');
define('B2B_COURSE_SUBSCRIPTIONS_FILE', __FILE__);
define('B2B_COURSE_SUBSCRIPTIONS_DIR', plugin_dir_path(__FILE__));
define('B2B_COURSE_SUBSCRIPTIONS_URL', plugin_dir_url(__FILE__));

// Simple PSR-4-like autoloader for our plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'B2B\\CourseSubscriptions\\';
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('\\', '-', $relative_class)) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, function () {
    B2B\CourseSubscriptions\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
    B2B\CourseSubscriptions\Deactivator::deactivate();
});

add_action('plugins_loaded', function () {
    // Ensure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>B2B Course Subscriptions requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    (new B2B\CourseSubscriptions\Plugin())->init();
});
