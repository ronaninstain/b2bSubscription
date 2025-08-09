<?php

/**
 * Plugin Name: WPLMS Child Enrollment Bridge
 * Description: This plugin is used to enroll users in a course from the parent site.
 * Version: 1.0.0
 * Author: Shoive Hossain
 * Author URI: https://github.com/ronaninstain/
 * Text Domain: csd
 * Domain Path: /languages
 * License: GPL2
 */

if (!defined('ABSPATH')) exit;

// Register REST route
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/enroll-user', [
        'methods'  => 'POST',
        'callback' => 'csd_enroll_user_rest',
        'permission_callback' => '__return_true',
    ]);
});

function csd_enroll_user_rest(WP_REST_Request $request)
{
    $auth = $request->get_header('authorization');
    if (!is_string($auth) || stripos($auth, 'Bearer ') !== 0) {
        return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    $token = trim(substr($auth, 7));
    // Expect token in form "clientID:secretKey"
    $expected = get_option('client_id') . ':' . get_option('secret_key'); // reuse your existing parent options
    if (!hash_equals($expected, $token)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Forbidden'], 403);
    }

    $email       = sanitize_email($request->get_param('email'));
    $username    = sanitize_user($request->get_param('username'));
    $course_id   = intval($request->get_param('course_id'));
    $expires_at  = $request->get_param('expires_at'); // null or Y-m-d H:i:s
    if (empty($email) || empty($course_id)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Missing params'], 400);
    }

    $user = get_user_by('email', $email);
    if (!$user) {
        if (empty($username)) {
            $username = sanitize_user(current(explode('@', $email)));
        }
        $user_id = wp_create_user($username, wp_generate_password(20), $email);
        if (is_wp_error($user_id)) {
            return new WP_REST_Response(['success' => false, 'message' => 'User create failed'], 500);
        }
        $user = get_user_by('id', $user_id);
    }

    if (!function_exists('bp_course_add_user_to_course')) {
        return new WP_REST_Response(['success' => false, 'message' => 'WPLMS not available'], 500);
    }

    // Enroll
    $res = bp_course_add_user_to_course($user->ID, $course_id);
    if (!$res) {
        // Could also be already enrolled; continue to set expiry
    }

    // Set expiry if provided (WPLMS stores seconds)
    if (!empty($expires_at)) {
        $ts = strtotime($expires_at);
        if ($ts && function_exists('bp_course_update_user_course_expiry_time')) {
            bp_course_update_user_course_expiry_time($user->ID, $course_id, $ts);
        }
    } else {
        // Lifetime: optional clear expiry
        if (function_exists('bp_course_update_user_course_expiry_time')) {
            bp_course_update_user_course_expiry_time($user->ID, $course_id, 0);
        }
    }

    return new WP_REST_Response(['success' => true], 200);
}
