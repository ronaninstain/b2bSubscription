<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) { exit; }

class Ajax
{
    public function init(): void
    {
        add_action('wp_ajax_get_course_price', [$this, 'handleGetCoursePrice']);
        add_action('wp_ajax_nopriv_get_course_price', [$this, 'handleGetCoursePrice']);
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
}


