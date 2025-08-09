<?php

namespace B2B\CourseSubscriptions;

use DateInterval;
use DateTime;

if (!defined('ABSPATH')) { exit; }

class SubscriptionService
{
    public function grantAccess(int $userId, int $courseId, string $durationKey): void
    {
        $expiresAt = $this->calculateExpiryFromNow($durationKey);
        $this->upsertSubscription($userId, $courseId, $expiresAt);
    }

    public function grantOrExtendAccess(int $userId, int $courseId, string $durationKey): void
    {
        // Extend from existing expiry if in future, else from now
        $base = $this->getExistingExpiryBase($userId, $courseId);
        $expiresAt = $this->calculateExpiryFromBase($durationKey, $base);
        $this->upsertSubscription($userId, $courseId, $expiresAt);
    }

    public function hasAccess(int $userId, int $courseId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'b2b_course_subscriptions';
        $now = current_time('mysql');
        $row = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d AND (expires_at IS NULL OR expires_at > %s) LIMIT 1", $userId, $courseId, $now));
        return !empty($row);
    }

    private function upsertSubscription(int $userId, int $courseId, ?string $expiresAt): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'b2b_course_subscriptions';
        $existingId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d LIMIT 1", $userId, $courseId));

        $data = [
            'user_id' => $userId,
            'course_id' => $courseId,
            'expires_at' => $expiresAt,
        ];
        $formats = ['%d', '%d', '%s'];

        if ($existingId) {
            $wpdb->update($table, $data, ['id' => $existingId], $formats, ['%d']);
        } else {
            $wpdb->insert($table, $data, $formats);
        }
    }

    private function calculateExpiryFromNow(string $durationKey): ?string
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
                return null; // NULL indicates lifetime
            default:
                // Fallback to 3 months
                $now->add(new DateInterval('P3M'));
                return $now->format('Y-m-d H:i:s');
        }
    }

    private function calculateExpiryFromBase(string $durationKey, ?DateTime $base): ?string
    {
        if ($durationKey === 'lifetime') {
            return null;
        }
        $start = $base ?: new DateTime(current_time('mysql'));
        switch ($durationKey) {
            case '3m':
                $start->add(new DateInterval('P3M'));
                break;
            case '6m':
                $start->add(new DateInterval('P6M'));
                break;
            case '1y':
                $start->add(new DateInterval('P1Y'));
                break;
            default:
                $start->add(new DateInterval('P3M'));
        }
        return $start->format('Y-m-d H:i:s');
    }

    private function getExistingExpiryBase(int $userId, int $courseId): ?DateTime
    {
        global $wpdb;
        $table = $wpdb->prefix . 'b2b_course_subscriptions';
        $expiresAt = $wpdb->get_var($wpdb->prepare("SELECT expires_at FROM {$table} WHERE user_id = %d AND course_id = %d LIMIT 1", $userId, $courseId));
        if (!empty($expiresAt)) {
            $current = new DateTime(current_time('mysql'));
            $existing = new DateTime($expiresAt);
            return ($existing > $current) ? $existing : $current;
        }
        return null;
    }
}


