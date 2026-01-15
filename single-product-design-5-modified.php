<?php
$user = get_current_user();

if (is_user_logged_in()) {
}
// Get the course ID from the query string
// $course_id = get_query_var('course_id');
$product_id = get_the_ID();

global $wpdb;
$table_name = $wpdb->prefix . 'ptc_items';
$course_id = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT course_id FROM $table_name WHERE product_id = %d",
        $product_id
    )
);
// Define the source site's API endpoint
$api_url = 'https://course-dashboard.com/wp-json/custom-api/v1/courses/' . $course_id;

$client_id = get_option('client_id'); // Update as necessary
$secret_key = get_option('secret_key'); // Update as necessary

// Add the Authorization header
$args = array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $client_id . ':' . $secret_key,
    ),
);

// Fetch data from the API with the Authorization header
$response = wp_remote_get($api_url, $args);

if (is_wp_error($response)) {
    echo '<p>Unable to retrieve course at this time.</p>';
    get_footer();
    exit;
}

$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);
$course = $data;
// Check if course data is empty
if (empty($course)) {
    echo '<p>Course not found.</p>';
} else {
    ?>
    <div class="ds-5-single-hero">
        <div class="container">
            <div class="hero-area">
                <div class="course-img-wrapper">
                    <?php
                    if ($course['thumbnail']) {
                        ?>
                        <img src="<?php echo esc_url($course['thumbnail']); ?>" alt="course-img" />
                        <?php
                    } else {
                        ?>
                        <img src="https://dummyimage.com/870x520/ecdcdc/333030.png" alt="" class="course-img" />
                        <?php
                    }
                    ?>

                </div>
                <div class="course-text-wrapper">
                    <h2 class="course-title"><?php echo esc_html($course['title']); ?></h2>
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'ptc_items';
                    $product_id = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT product_id FROM $table_name WHERE course_id = %d",
                            $course_id
                        )
                    );

                    if ($product_id) {
                        // Get the product object
                        $product = wc_get_product($product_id);
                        if ($product) {
                            // Get the sale price and regular price
                            $sale_price = $product->get_sale_price();
                            $regular_price = $product->get_regular_price();

                            // Output the new price structure
                            echo '<div class="price-container">';
                            if ($sale_price) {
                                // Display sale price and crossed-out regular price
                                echo '<del>' . get_woocommerce_currency_symbol() . esc_html($regular_price) . '</del>';
                                echo ' <span>' . get_woocommerce_currency_symbol() . esc_html($sale_price) . '</span>';
                            } else {
                                // Display regular price only
                                echo '<span>' . get_woocommerce_currency_symbol() . esc_html($regular_price) . '</span>';
                            }
                            echo '</div>';
                        } else {
                            echo 'Product not found.';
                        }
                    } else {
                        echo 'No product associated with this course.';
                    }
                    
                    // Check if user has active subscription and show appropriate button
                    $show_enroll_button = false;
                    $is_already_enrolled = false;
                    
                    if (is_user_logged_in()) {
                        // Use the B2B plugin helper functions
                        if (class_exists('\B2B\CourseSubscriptions\WooIntegration')) {
                            $show_enroll_button = \B2B\CourseSubscriptions\WooIntegration::userHasActiveSubscription();
                            
                            if ($show_enroll_button && class_exists('\B2B\CourseSubscriptions\SubscriptionService')) {
                                $subscriptionService = new \B2B\CourseSubscriptions\SubscriptionService();
                                $is_already_enrolled = $subscriptionService->hasAccess(get_current_user_id(), $course_id);
                            }
                        }
                    }
                    
                    // Display the appropriate button
                    if ($show_enroll_button && !$is_already_enrolled) {
                        // Show Enroll button for subscription users
                        ?>
                        <a href="#" class="course-link b2b-cs-enroll-btn" data-course-id="<?php echo esc_attr($course_id); ?>">
                            <?php echo esc_html__('Enroll', 'b2b-course-subscriptions'); ?>
                        </a>
                        <?php
                    } elseif ($show_enroll_button && $is_already_enrolled) {
                        // Show Already Enrolled message
                        ?>
                        <span class="course-link b2b-cs-enrolled" style="opacity: 0.7; cursor: default;">
                            <?php echo esc_html__('Already Enrolled', 'b2b-course-subscriptions'); ?>
                        </span>
                        <?php
                    } else {
                        // Show regular "Take This Course" button for non-subscription users
                        ?>
                        <a href="<?php echo get_site_url(); ?>/cart/?add-to-cart=<?php echo $product_id; ?>"
                            class="course-link">Take This Course</a>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="ds-5-course-content">
        <div class="container">
            <div class="a2n_course__contents">
                <?php echo wp_kses_post($course['content']); ?>
            </div>
        </div>
    </div>
    <div class="a2n_award__area">
        <div class="container">
            <div class="a2n-awards_wrapper">
                <div class="e-the-actual-award-des">
                    <p>
                        We guarantee that all our online courses will meet or exceed your
                        expectations. If you are not fully satisfied with a course - for
                        any reason at all - simply request a full refund. We guarantee no
                        hassles. That's our promise to you.<br /><br />
                        Go ahead and order with confidence!
                    </p>
                </div>
                <div class="e-the-actual-award-img">
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/design_5/imgs/single-course/money_back.png"
                        alt="money_back" />
                </div>
            </div>
        </div>
    </div>
    <div class="a2n_video__area">
        <div class="a2n_vdo_container">
            <h3 class="vdo_subTitle">
                Easy to Access <br />
                Let's Navigate Together
            </h3>
            <div class="a2n_vdo_wrapper">
                <video class="a2n-video"
                    src="<?php echo get_template_directory_uri(); ?>/assets/design_5/imgs/single-course/Updated-Navigation-Guidance-Video.mp4"
                    controls="" controlslist="nodownload"></video>
            </div>
        </div>
    </div>
    <div class="a2n_courseCurriculum__area">
        <div class="course_curriculum_wrapper">
            <h3 class="heading">Course Curriculum</h3>
            <div class="course_curriculum accordion">
                <table class="table">
                    <tbody>
                        <?php
                        if (!empty($course['curriculum']) && is_array($course['curriculum'])) {
                            foreach ($course['curriculum'] as $sectionTitle => $items) {
                                // Render section header
                                echo '<tr class="course_section">';
                                echo '<td colspan="4">' . esc_html($sectionTitle) . '</td>';
                                echo '</tr>';

                                // Validate items in the section
                                if (!empty($items) && is_array($items)) {
                                    foreach ($items as $lesson) {
                                        // var_dump($lesson);
                                        // die();
                                        $title = $lesson['title'] ?? 'Lesson Title Unavailable';
                                        $duration = isset($lesson['duration']) ? '<i class="fa-regular fa-clock"></i> ' . $lesson['duration'] : '';
                                        $icon = $lesson['icon'] ?? 'fa-play-circle'; // Default icon
                    
                                        echo '<tr class="course_lesson">';
                                        echo '<td class="curriculum-icon"><i class="fa ' . esc_attr($icon) . '"></i></td>';
                                        echo '<td>' . htmlspecialchars($lesson) . '</td>';
                                        echo '<td></td>'; // Empty column for flexibility
                                        echo '<td><span class="time">' . esc_html($duration) . '</span></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    // Handle sections with no lessons
                                    echo '<tr><td colspan="4">No lessons available in this section.</td></tr>';
                                }
                            }
                        } else {
                            // Handle empty or invalid curriculum data
                            echo '<tr><td colspan="4">No curriculum data available.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="the-std-feedback-div">
            <a href="#">Feedback Form</a>
        </div>
    </div>
    <?php
}
