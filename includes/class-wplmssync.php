<?php

namespace B2B\CourseSubscriptions;

use DateInterval;
use DateTime;

if (!defined('ABSPATH')) { exit; }

class WplmsSync
{
    private $productMapper;

    public function __construct($productMapper)
    {
        $this->productMapper = $productMapper;
    }

    public function isEnabled()
    {
        return (bool) get_option('b2b_cs_enable_parent_sync', false);
    }

    public function enrollUserOnParent($userId, $courseId, $durationKey)
    {
        if (!$this->isEnabled()) { return; }

        $apiBase = rtrim(get_option('b2b_cs_api_base_url', ''), '/');
        $clientId = get_option('b2b_cs_client_id', '');
        $secretKey = get_option('b2b_cs_secret_key', '');
        $endpointPath = ltrim(get_option('b2b_cs_parent_enroll_endpoint', '/wp-json/custom/v1/enroll-user'), '/');

        if (empty($apiBase) || empty($clientId) || empty($secretKey)) {
            return; // not configured
        }

        $user = get_user_by('id', $userId);
        if (!$user) { return; }

        $expiresAt = $this->calculateExpiry($durationKey);

        // Primary: our bridge endpoint
        $url = $apiBase . '/' . $endpointPath;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $clientId . ':' . $secretKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'email' => $user->user_email,
                'username' => $user->user_login,
                'course_id' => $courseId,
                'expires_at' => $expiresAt, // null for lifetime
                'duration_key' => $durationKey,
            ]),
            'timeout' => 20,
            'method'  => 'POST',
        ];

        $response = wp_remote_post($url, $args);
        $code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return; // success
        }

        // Fallback: client's working endpoint custom-api/v1/assign-course
        $assignUrl = $apiBase . '/wp-json/custom-api/v1/assign-course';
        $durationDays = $this->durationKeyToDays($durationKey);
        $productId = $this->productMapper ? $this->productMapper->getProductIdByCourseId((int)$courseId) : null;
        $fallbackArgs = [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode([
                'email'         => $user->user_email,
                'course_id'     => (int) $courseId,
                'product_id'    => (int) $productId,
                'client_id'     => (int) $clientId,
                'secret_key'    => (string) $secretKey,
                'duration_days' => (int) $durationDays,
            ]),
            'timeout' => 20,
            'method'  => 'POST',
        ];

        $response2 = wp_remote_post($assignUrl, $fallbackArgs);
        if (is_wp_error($response2)) {
            error_log('[B2B CS] Fallback assign-course HTTP error: ' . $response2->get_error_message());
        } else {
            $code2 = wp_remote_retrieve_response_code($response2);
            if ($code2 < 200 || $code2 >= 300) {
                error_log('[B2B CS] Fallback assign-course failed with code ' . $code2 . ' for user ' . $userId . ' course ' . $courseId);
            }
        }
    }

    private function durationKeyToDays($durationKey)
    {
        switch ($durationKey) {
            case '3m': return 90; // approx
            case '6m': return 180; // approx
            case '1y': return 365;
            case 'lifetime': return 36500; // ~100 years
        }
        return 90;
    }

    private function calculateExpiry($durationKey)
    {
        $now = new DateTime(current_time('mysql'));
        switch ($durationKey) {
            case '3m':
                $now->add(new DateInterval('P3M'));
                return $now->format('Y-m-d H:i:s');
            case '6m':
                $now->add(new DateInterval('P6M'));
                return $now->format('Y-m-d H:i:s');
            case '1y':
                $now->add(new DateInterval('P1Y'));
                return $now->format('Y-m-d H:i:s');
            case 'lifetime':
                return null;
            default:
                $now->add(new DateInterval('P3M'));
                return $now->format('Y-m-d H:i:s');
        }
    }
}


