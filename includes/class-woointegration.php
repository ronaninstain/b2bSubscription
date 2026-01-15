<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) { exit; }

class WooIntegration
{
    private SubscriptionService $subscriptionService;
    private ProductMapper $productMapper;
    private EnrollmentQueue $enrollmentQueue;
    private WplmsSync $wplmsSync;

    public function __construct(SubscriptionService $subscriptionService, ProductMapper $productMapper, EnrollmentQueue $enrollmentQueue, WplmsSync $wplmsSync)
    {
        $this->subscriptionService = $subscriptionService;
        $this->productMapper = $productMapper;
        $this->enrollmentQueue = $enrollmentQueue;
        $this->wplmsSync = $wplmsSync;
    }

    public function init(): void
    {
        add_action('woocommerce_order_status_completed', [$this, 'handleOrderCompleted'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'handleOrderCompleted'], 10, 1);
        add_action('woocommerce_payment_complete', [$this, 'handleOrderCompleted'], 10, 1);
        add_action('woocommerce_order_status_changed', [$this, 'handleOrderStatusChanged'], 10, 4);
        // If WooCommerce Subscriptions is active, also extend on renewal
        add_action('woocommerce_subscription_renewal_payment_complete', [$this, 'handleSubscriptionRenewal'], 10, 1);

        // Add duration selection on product page (simple products mapped to courses)
        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderDurationSelector']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'captureDurationOnAddToCart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'displayDurationInCart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'storeDurationInOrderItem'], 10, 4);

        // Product settings: duration key per product
        add_action('woocommerce_product_options_general_product_data', [$this, 'addProductDurationField']);
        add_action('woocommerce_process_product_meta', [$this, 'saveProductDurationField']);

        // Add filter to modify add to cart button for subscription users
        add_filter('woocommerce_product_single_add_to_cart_text', [$this, 'modifyAddToCartButtonText'], 10, 2);
        add_action('wp_footer', [$this, 'addEnrollButtonScript']);
    }

    public function renderDurationSelector(): void
    {
        global $product;
        if (!$product || !$product->get_id()) { return; }

        $courseId = $this->reverseLookupCourseId($product->get_id());
        $predefinedDuration = get_post_meta($product->get_id(), '_b2b_cs_duration_key', true);
        $isSubscriptionType = $this->isWooSubscriptionProduct($product->get_id());
        if (!$courseId && !$this->isSubscriptionPassProduct($product->get_id())) { return; }
        if (!empty($predefinedDuration) || $isSubscriptionType) { return; } // duration is fixed or derives from subscription settings
        ?>
        <div class="b2b-cs-duration">
            <label for="b2b_cs_duration"><strong><?php echo esc_html__('Choose access duration', 'b2b-course-subscriptions'); ?></strong></label>
            <select id="b2b_cs_duration" name="b2b_cs_duration">
                <option value="3m">3 months</option>
                <option value="6m">6 months</option>
                <option value="1y">1 year</option>
                <option value="lifetime">Lifetime</option>
            </select>
        </div>
        <?php
    }

    public function captureDurationOnAddToCart(array $cartItemData, int $productId, int $variationId): array
    {
        if (isset($_POST['b2b_cs_duration'])) {
            $cartItemData['b2b_cs_duration'] = sanitize_text_field(wp_unslash($_POST['b2b_cs_duration']));
        } else {
            $derived = $this->deriveDurationKeyFromProduct($productId);
            if (!empty($derived)) { $cartItemData['b2b_cs_duration'] = $derived; }
        }
        return $cartItemData;
    }

    public function displayDurationInCart(array $itemData, array $cartItem): array
    {
        if (isset($cartItem['b2b_cs_duration'])) {
            $label = $this->humanizeDuration($cartItem['b2b_cs_duration']);
            $itemData[] = [
                'key' => __('Access Duration', 'b2b-course-subscriptions'),
                'value' => $label,
            ];
        }
        return $itemData;
    }

    public function storeDurationInOrderItem($item, $cartItemKey, $values, $order): void
    {
        if (isset($values['b2b_cs_duration'])) {
            $item->add_meta_data('_b2b_cs_duration', $values['b2b_cs_duration'], true);
        }
    }

    public function handleOrderCompleted($orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order) { return; }

        foreach ($order->get_items() as $item) {
            $productId = $item->get_product_id();
            $duration = $item->get_meta('_b2b_cs_duration');
            if (empty($duration)) { $duration = $this->deriveDurationKeyFromProduct($productId); }
            if (empty($duration)) { $duration = '3m'; }

            $userId = (int) $order->get_user_id();
            if (!$userId) {
                // Attempt to resolve/create user from billing email
                $email = $order->get_billing_email();
                if ($email) {
                    $user = get_user_by('email', $email);
                    if ($user) {
                        $userId = (int) $user->ID;
                    } else {
                        $username_base = sanitize_user(current(explode('@', $email)));
                        $username = $username_base;
                        $i = 1;
                        while (username_exists($username)) { $username = $username_base . $i++; }
                        $new_id = wp_create_user($username, wp_generate_password(24), $email);
                        if (!is_wp_error($new_id)) {
                            $userId = (int) $new_id;
                        }
                    }
                }
            }
            Logger::log('Granting access', [ 'order_id' => $orderId, 'product_id' => $productId, 'user_id' => $userId, 'duration' => $duration ]);
            if (!$userId) { continue; }

            if ($this->isSubscriptionPassProduct($productId)) {
                // Don't auto-enroll users in all courses when they purchase subscription pass
                // Instead, they can manually enroll in courses they want via the Enroll button
                // This gives students the choice to enroll in courses they're interested in
                Logger::log('Subscription pass purchased - user can now manually enroll in courses', [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'duration' => $duration,
                ]);
            } else {
                // Grant access to the single mapped course for this product
                $courseId = $this->reverseLookupCourseId($productId);
                if ($courseId) {
                    $this->subscriptionService->grantOrExtendAccess($userId, (int)$courseId, (string)$duration);
                    $this->wplmsSync->enrollUserOnParent($userId, (int)$courseId, (string)$duration);
                }
            }
        }
    }

    public function handleOrderStatusChanged($orderId, $oldStatus, $newStatus, $order): void
    {
        // Fallback: when transitioning into processing/completed after capture
        if (in_array($newStatus, ['processing', 'completed'], true)) {
            $this->handleOrderCompleted($orderId);
        }
    }

    /**
     * Extends access when a subscription renewal payment completes (Woo Subscriptions).
     */
    public function handleSubscriptionRenewal($subscription): void
    {
        if (!$subscription || !is_object($subscription)) { return; }
        if (!method_exists($subscription, 'get_items')) { return; }
        $userId = (int) (method_exists($subscription, 'get_user_id') ? $subscription->get_user_id() : 0);
        if (!$userId) { return; }

        foreach ($subscription->get_items() as $item) {
            if (!is_object($item) || !method_exists($item, 'get_product_id')) { continue; }
            $productId = (int) $item->get_product_id();
            $duration = $this->deriveDurationKeyFromProduct($productId);
            if (empty($duration)) { $duration = '3m'; }

            if ($this->isSubscriptionPassProduct($productId)) {
                // Don't auto-enroll on subscription renewal either
                // Users can manually enroll in courses they want
                Logger::log('Subscription pass renewed - user can continue to manually enroll in courses', [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'duration' => $duration,
                ]);
            } else {
                $courseId = $this->reverseLookupCourseId($productId);
                if ($courseId) {
                    $this->subscriptionService->grantOrExtendAccess($userId, (int)$courseId, (string)$duration);
                    $this->wplmsSync->enrollUserOnParent($userId, (int)$courseId, (string)$duration);
                }
            }
        }
    }

    private function reverseLookupCourseId(int $productId): ?int
    {
        return $this->productMapper->getCourseIdByProductId($productId);
    }

    private function humanizeDuration(string $key): string
    {
        switch ($key) {
            case '3m': return '3 months';
            case '6m': return '6 months';
            case '1y': return '1 year';
            case 'lifetime': return 'Lifetime';
            default: return $key;
        }
    }

    public function addProductDurationField(): void
    {
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_b2b_cs_duration_key',
            'label' => __('B2B Access Duration Key', 'b2b-course-subscriptions'),
            'description' => __('Optional fixed duration for this product: 3m, 6m, 1y, lifetime. If set, the dropdown on product page is hidden.', 'b2b-course-subscriptions'),
            'desc_tip' => true,
        ]);
        woocommerce_wp_checkbox([
            'id' => '_b2b_cs_is_pass',
            'label' => __('B2B All-Courses Pass', 'b2b-course-subscriptions'),
            'description' => __('If enabled, this product grants access to all mapped courses for the duration.', 'b2b-course-subscriptions'),
        ]);
        echo '</div>';
    }

    public function saveProductDurationField(int $postId): void
    {
        $val = isset($_POST['_b2b_cs_duration_key']) ? sanitize_text_field(wp_unslash($_POST['_b2b_cs_duration_key'])) : '';
        if (!empty($val)) {
            update_post_meta($postId, '_b2b_cs_duration_key', $val);
        } else {
            delete_post_meta($postId, '_b2b_cs_duration_key');
        }
        $isPass = isset($_POST['_b2b_cs_is_pass']) ? 'yes' : 'no';
        update_post_meta($postId, '_b2b_cs_is_pass', $isPass);
    }

    private function getSubscriptionProductIds(): array
    {
        $raw = (string) get_option('b2b_cs_subscription_product_ids', '');
        if (empty($raw)) { return []; }
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        $ids = [];
        foreach ($parts as $p) {
            if (is_numeric($p)) { $ids[] = (int) $p; }
        }
        return $ids;
    }

    private function isSubscriptionPassProduct(int $productId): bool
    {
        $flag = get_post_meta($productId, '_b2b_cs_is_pass', true);
        if ($flag === 'yes' || in_array($productId, $this->getSubscriptionProductIds(), true)) {
            return true;
        }
        // Heuristic: if product is not mapped to a single course, treat it as a pass
        $mappedCourse = $this->reverseLookupCourseId($productId);
        return empty($mappedCourse);
    }

    private function isWooSubscriptionProduct(int $productId): bool
    {
        // WooCommerce Subscriptions stores these metas on products
        $period = get_post_meta($productId, '_subscription_period', true);
        $interval = get_post_meta($productId, '_subscription_period_interval', true);
        return !empty($period) && !empty($interval);
    }

    private function deriveDurationKeyFromProduct(int $productId): string
    {
        // Priority: explicit duration meta
        $predefined = get_post_meta($productId, '_b2b_cs_duration_key', true);
        if (!empty($predefined)) { return $predefined; }

        // If Woo Subscriptions product, infer
        $period = get_post_meta($productId, '_subscription_period', true); // day|week|month|year
        $interval = (int) get_post_meta($productId, '_subscription_period_interval', true); // e.g. 3, 6, 1
        if (!empty($period) && $interval > 0) {
            if ($period === 'month' && $interval === 3) return '3m';
            if ($period === 'month' && $interval === 6) return '6m';
            if ($period === 'year'  && $interval === 1) return '1y';
        }
        return '';
    }

    /**
     * Modify add to cart button text for subscription users
     */
    public function modifyAddToCartButtonText(string $text, $product): string
    {
        if (!is_user_logged_in()) {
            return $text;
        }

        $userId = get_current_user_id();
        if (!$this->subscriptionService->hasActiveSubscriptionPass($userId)) {
            return $text;
        }

        // Check if this product is mapped to a course
        $courseId = $this->reverseLookupCourseId($product->get_id());
        if (!$courseId) {
            return $text;
        }

        // Check if user already has access to this course
        if ($this->subscriptionService->hasAccess($userId, $courseId)) {
            return __('Already Enrolled', 'b2b-course-subscriptions');
        }

        return __('Enroll', 'b2b-course-subscriptions');
    }

    /**
     * Add JavaScript to handle enroll button functionality
     */
    public function addEnrollButtonScript(): void
    {
        if (!is_product()) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        // Get product object - try global first, then get from post ID
        global $product;
        if (!$product || !is_object($product) || !method_exists($product, 'get_id')) {
            $productId = get_the_ID();
            if (!$productId) {
                return;
            }
            $product = wc_get_product($productId);
            if (!$product || !is_object($product)) {
                return;
            }
        }

        $productId = $product->get_id();
        if (!$productId) {
            return;
        }

        $userId = get_current_user_id();
        if (!$this->subscriptionService->hasActiveSubscriptionPass($userId)) {
            return;
        }

        $courseId = $this->reverseLookupCourseId($productId);
        if (!$courseId) {
            return;
        }

        // Check if user already has access
        if ($this->subscriptionService->hasAccess($userId, $courseId)) {
            return;
        }

        $ajaxUrl = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('b2b_cs_enroll_nonce');
        ?>
        <script type="text/javascript">
        window.b2bCsEnrollNonce = '<?php echo esc_js($nonce); ?>';
        window.b2bCsAjaxUrl = '<?php echo esc_url($ajaxUrl); ?>';
        (function($) {
            $(document).ready(function() {
                // Find the "Take This Course" or "Enroll" button and modify it
                var $enrollButton = $('a.course-link, .single_add_to_cart_button, button.single_add_to_cart_button');
                
                // Also check for custom button classes
                if ($enrollButton.length === 0) {
                    $enrollButton = $('a[href*="add-to-cart"]');
                }

                if ($enrollButton.length > 0) {
                    var courseId = <?php echo intval($courseId); ?>;
                    var originalHref = $enrollButton.attr('href');
                    var originalText = $enrollButton.text().trim();
                    
                    // Only modify if it says "Take This Course" or "Enroll"
                    if (originalText.toLowerCase().includes('take this course') || 
                        originalText.toLowerCase().includes('enroll') ||
                        originalHref && originalHref.includes('add-to-cart')) {
                        
                        $enrollButton
                            .text('<?php echo esc_js(__('Enroll', 'b2b-course-subscriptions')); ?>')
                            .attr('href', '#')
                            .addClass('b2b-cs-enroll-btn')
                            .off('click')
                            .on('click', function(e) {
                                e.preventDefault();
                                
                                var $btn = $(this);
                                var originalBtnText = $btn.text();
                                
                                // Disable button and show loading
                                $btn.prop('disabled', true).text('<?php echo esc_js(__('Enrolling...', 'b2b-course-subscriptions')); ?>');
                                
                                $.ajax({
                                    url: '<?php echo esc_url($ajaxUrl); ?>',
                                    type: 'POST',
                                    data: {
                                        action: 'b2b_cs_enroll_course',
                                        course_id: courseId,
                                        _ajax_nonce: window.b2bCsEnrollNonce || '<?php echo esc_js($nonce); ?>'
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            $btn.text('<?php echo esc_js(__('Enrolled!', 'b2b-course-subscriptions')); ?>')
                                                .removeClass('b2b-cs-enroll-btn')
                                                .addClass('b2b-cs-enrolled')
                                                .prop('disabled', false);
                                            
                                            // Show success message
                                            if (response.data && response.data.message) {
                                                alert(response.data.message);
                                            }
                                            
                                            // Optionally redirect or reload
                                            setTimeout(function() {
                                                location.reload();
                                            }, 1500);
                                        } else {
                                            $btn.prop('disabled', false).text(originalBtnText);
                                            alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to enroll. Please try again.', 'b2b-course-subscriptions')); ?>');
                                        }
                                    },
                                    error: function() {
                                        $btn.prop('disabled', false).text(originalBtnText);
                                        alert('<?php echo esc_js(__('An error occurred. Please try again.', 'b2b-course-subscriptions')); ?>');
                                    }
                                });
                            });
                    }
                }
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Helper function to check if user has active subscription (for use in templates)
     */
    public static function userHasActiveSubscription(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $subscriptionService = new SubscriptionService();
        return $subscriptionService->hasActiveSubscriptionPass(get_current_user_id());
    }

    /**
     * Helper function to get course ID from product ID (for use in templates)
     */
    public static function getCourseIdFromProduct(int $productId): ?int
    {
        $mapper = new ProductMapper();
        return $mapper->getCourseIdByProductId($productId);
    }
}


