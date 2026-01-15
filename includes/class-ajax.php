<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) { exit; }

class Ajax
{
    public function init(): void
    {
        add_action('wp_ajax_get_course_price', [$this, 'handleGetCoursePrice']);
        add_action('wp_ajax_nopriv_get_course_price', [$this, 'handleGetCoursePrice']);
        add_action('wp_ajax_b2b_cs_enroll_course', [$this, 'handleEnrollCourse']);
    }

    public function handleGetCoursePrice(): void
    {
        $courseId = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        if ($courseId <= 0) {
            wp_send_json(['success' => false, 'message' => 'Invalid course id']);
        }

        $mapper = new ProductMapper();
        $productId = $mapper->getProductIdByCourseId($courseId);
        if (!$productId) {
            wp_send_json(['success' => false, 'message' => 'Course not mapped']);
        }

        $product = wc_get_product($productId);
        if (!$product) {
            wp_send_json(['success' => false, 'message' => 'Product not found']);
        }

        $price = $product->get_price();
        $productUrl = get_permalink($productId);

        wp_send_json([
            'success' => true,
            'data' => [
                'price' => wc_price($price),
                'product_url' => $productUrl,
                'product_id' => $productId,
            ],
        ]);
    }

    public function handleEnrollCourse(): void
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to enroll in courses.']);
            return;
        }

        // Verify nonce
        if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'b2b_cs_enroll_nonce')) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.']);
            return;
        }

        $userId = get_current_user_id();
        $courseId = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

        if ($courseId <= 0) {
            wp_send_json_error(['message' => 'Invalid course ID.']);
            return;
        }

        // Check if user has active subscription pass
        $subscriptionService = new SubscriptionService();
        if (!$subscriptionService->hasActiveSubscriptionPass($userId)) {
            wp_send_json_error(['message' => 'You need an active subscription to enroll in courses.']);
            return;
        }

        // Get the duration from subscription (default to 3m if not found)
        $duration = $this->getSubscriptionDuration($userId);
        
        // Grant access to the course
        $subscriptionService->grantOrExtendAccess($userId, $courseId, $duration);

        // Sync with parent site if enabled
        $wplmsSync = new WplmsSync(new ProductMapper());
        if ($wplmsSync->isEnabled()) {
            $wplmsSync->enrollUserOnParent($userId, $courseId, $duration);
        }

        wp_send_json_success([
            'message' => 'Successfully enrolled in the course!',
            'course_id' => $courseId,
        ]);
    }

    private function getSubscriptionDuration(int $userId): string
    {
        // Try to get duration from most recent subscription order
        $orders = wc_get_orders([
            'customer_id' => $userId,
            'status' => ['completed', 'processing'],
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (!empty($orders)) {
            $order = $orders[0];
            foreach ($order->get_items() as $item) {
                $productId = $item->get_product_id();
                $duration = $item->get_meta('_b2b_cs_duration');
                if (!empty($duration)) {
                    return $duration;
                }
                $predefined = get_post_meta($productId, '_b2b_cs_duration_key', true);
                if (!empty($predefined)) {
                    return $predefined;
                }
            }
        }

        // Check WooCommerce Subscriptions
        if (class_exists('WC_Subscriptions')) {
            $subscriptions = wcs_get_users_subscriptions($userId);
            foreach ($subscriptions as $subscription) {
                if (!in_array($subscription->get_status(), ['active', 'on-hold'], true)) {
                    continue;
                }
                foreach ($subscription->get_items() as $item) {
                    $productId = $item->get_product_id();
                    $predefined = get_post_meta($productId, '_b2b_cs_duration_key', true);
                    if (!empty($predefined)) {
                        return $predefined;
                    }
                }
            }
        }

        return '3m'; // Default duration
    }
}


