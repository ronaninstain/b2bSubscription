<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) {
    exit;
}

class EnrollmentQueue
{
    private SubscriptionService $subscriptionService;
    private WplmsSync $wplmsSync;
    private ProductMapper $productMapper;
    private int $chunkSize = 40;

    public function __construct(SubscriptionService $subscriptionService, WplmsSync $wplmsSync, ProductMapper $productMapper)
    {
        $this->subscriptionService = $subscriptionService;
        $this->wplmsSync = $wplmsSync;
        $this->productMapper = $productMapper;
    }

    public function init(): void
    {
        add_action('b2b_cs_process_enrollment_chunk', [$this, 'processChunk'], 10, 1);
    }

    public function queueCourses(int $userId, array $courseIds, string $durationKey): void
    {
        if (empty($courseIds)) {
            return;
        }

        $chunks = array_chunk($courseIds, $this->chunkSize);
        foreach ($chunks as $index => $chunk) {
            $payload = [
                'user_id' => $userId,
                'course_ids' => array_map('intval', $chunk),
                'duration_key' => $durationKey,
            ];
            Logger::log('Queueing enrollment chunk', [
                'user_id' => $userId,
                'chunk_index' => $index,
                'courses' => count($chunk),
            ]);
            $this->scheduleChunk($payload, $index);
        }

        $this->triggerProcessor();
    }

    public function processChunk(array $payload): void
    {
        $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
        $durationKey = isset($payload['duration_key']) ? (string)$payload['duration_key'] : '3m';
        $courseIds = isset($payload['course_ids']) ? array_map('intval', (array)$payload['course_ids']) : [];

        if (!$userId || empty($courseIds)) {
            Logger::log('Skipping chunk due to missing data', [
                'payload' => $payload,
            ]);
            return;
        }

        Logger::log('Processing enrollment chunk', [
            'user_id' => $userId,
            'courses' => count($courseIds),
            'duration' => $durationKey,
        ]);

        foreach ($courseIds as $courseId) {
            $this->subscriptionService->grantOrExtendAccess($userId, (int)$courseId, $durationKey);
            $this->wplmsSync->enrollUserOnParent($userId, (int)$courseId, $durationKey);
        }

        Logger::log('Processed enrollment chunk', [
            'user_id' => $userId,
            'courses_processed' => count($courseIds),
            'duration' => $durationKey,
        ]);
    }

    private function scheduleChunk(array $payload, int $sequence): void
    {
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('b2b_cs_process_enrollment_chunk', [$payload], 'b2b-course-subscriptions');
        } else {
            $delay = max(5, $sequence * 5);
            wp_schedule_single_event(time() + $delay, 'b2b_cs_process_enrollment_chunk', [$payload]);
        }
    }

    private function triggerProcessor(): void
    {
        if (function_exists('as_enqueue_async_action')) {
            return; // Action Scheduler handles async queue immediately.
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        // Fire wp-cron to process scheduled events ASAP.
        $cronUrl = site_url('wp-cron.php');
        wp_remote_post($cronUrl, [
            'timeout' => 0.01,
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
        ]);
    }
}


