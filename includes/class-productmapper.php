<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) { exit; }

class ProductMapper
{
    /**
     * Return product ID mapped to external course ID via `ptc_items` table.
     */
    public function getProductIdByCourseId(int $courseId): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . $this->getMappingTable();
        $courseCol = $this->getCourseColumn();
        $productCol = $this->getProductColumn();
        $productId = $wpdb->get_var($wpdb->prepare("SELECT {$productCol} FROM {$table} WHERE {$courseCol} = %d LIMIT 1", $courseId));
        if ($productId) {
            return (int)$productId;
        }
        return null;
    }

    /**
     * Return all mapped external course IDs for this client/site.
     */
    public function getAllMappedCourseIds(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . $this->getMappingTable();
        $courseCol = $this->getCourseColumn();
        $ids = $wpdb->get_col("SELECT DISTINCT {$courseCol} FROM {$table}");
        return array_map('intval', $ids ?: []);
    }

    /**
     * Reverse lookup: course_id by product_id.
     */
    public function getCourseIdByProductId(int $productId): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . $this->getMappingTable();
        $courseCol = $this->getCourseColumn();
        $productCol = $this->getProductColumn();
        $courseId = $wpdb->get_var($wpdb->prepare("SELECT {$courseCol} FROM {$table} WHERE {$productCol} = %d LIMIT 1", $productId));
        return $courseId ? (int)$courseId : null;
    }

    private function getMappingTable(): string
    {
        $table = (string) get_option('b2b_cs_ptc_table', 'ptc_items');
        return $table;
    }

    private function getCourseColumn(): string
    {
        $col = (string) get_option('b2b_cs_ptc_course_column', 'course_id');
        return $col;
    }

    private function getProductColumn(): string
    {
        $col = (string) get_option('b2b_cs_ptc_product_column', 'product_id');
        return $col;
    }
}


