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
    private Shortcodes $shortcodes;
    private Assets $assets;
    private WooIntegration $wooIntegration;

    public function init(): void
    {
        $this->settings = new Settings();
        $this->ajax = new Ajax();
        $this->productMapper = new ProductMapper();
        $this->subscriptionService = new SubscriptionService();
        $this->shortcodes = new Shortcodes();
        $this->assets = new Assets();
        $wplmsSync = new WplmsSync($this->productMapper);
        $this->wooIntegration = new WooIntegration($this->subscriptionService, $this->productMapper);

        $this->settings->init();
        $this->ajax->init();
        $this->shortcodes->init();
        $this->assets->init();
        $this->wooIntegration->init();
    }
}


