<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) { exit; }

class Logger
{
    public static function log(string $message, array $context = []): void
    {
        if (!get_option('b2b_cs_debug', false)) {
            return;
        }
        $prefix = '[B2B CS] ';
        if (!empty($context)) {
            $message .= ' | ' . wp_json_encode($context);
        }
        error_log($prefix . $message);
    }
}


