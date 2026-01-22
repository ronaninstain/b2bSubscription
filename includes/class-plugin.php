<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private Settings $settings;
    private Ajax $ajax;
    private ProductMapper $productMapper;
    private SubscriptionService $subscriptionService;
    private EnrollmentQueue $enrollmentQueue;
    private Shortcodes $shortcodes;
    private Assets $assets;
    private WooIntegration $wooIntegration;

    public function init(): void
    {
        $this->settings = new Settings();
        $this->ajax = new Ajax();
        $this->productMapper = new ProductMapper();
        $this->subscriptionService = new SubscriptionService();
        $wplmsSync = new WplmsSync($this->productMapper);
        $this->enrollmentQueue = new EnrollmentQueue($this->subscriptionService, $wplmsSync, $this->productMapper);
        $this->shortcodes = new Shortcodes();
        $this->assets = new Assets();
        $this->wooIntegration = new WooIntegration($this->subscriptionService, $this->productMapper, $this->enrollmentQueue, $wplmsSync);

        $this->settings->init();
        $this->ajax->init();
        $this->enrollmentQueue->init();
        $this->shortcodes->init();
        $this->assets->init();
        $this->wooIntegration->init();
    }
}


