<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) { exit; }

class Assets
{
    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'registerFrontendAssets']);
    }

    public function registerFrontendAssets(): void
    {
        wp_register_script(
            'b2b-cs-frontend',
            B2B_COURSE_SUBSCRIPTIONS_URL . 'public/js/frontend.js',
            ['jquery'],
            B2B_COURSE_SUBSCRIPTIONS_VERSION,
            true
        );
        wp_register_style(
            'b2b-cs-frontend',
            B2B_COURSE_SUBSCRIPTIONS_URL . 'public/css/frontend.css',
            [],
            B2B_COURSE_SUBSCRIPTIONS_VERSION
        );
    }
}


