<?php

namespace B2B\CourseSubscriptions;

if (!defined('ABSPATH')) { exit; }

class Shortcodes
{
    public function init(): void
    {
        add_shortcode('b2b_courses', [$this, 'renderCourses']);
    }

    public function renderCourses($atts = []): string
    {
        $clientId = esc_attr(get_option('b2b_cs_client_id', ''));
        $secretKey = esc_attr(get_option('b2b_cs_secret_key', ''));
        $apiBase = rtrim(get_option('b2b_cs_api_base_url', 'https://course-dashboard.com'), '/');
        $mapper = new ProductMapper();
        $courseIds = $mapper->getAllMappedCourseIds();

        ob_start();
        ?>
        <div id="taf-title-course"><h1>All Courses</h1></div>
        <div class="b2b-cs-courses">
            <div class="dropdown">
                <button id="drpbtn" class="dropbtn"><span id="drpdwntxt">All Courses</span></button>
                <div id="catDropdown" class="dropdown-content"></div>
            </div>
            <div id="loader" style="display:none;align-items:center;justify-content:center;">Loading...</div>
            <div id="show-courses-container" style="display:grid"></div>
            <div id="course-dir-count-bottom"></div>
            <div id="course-dir-pag-bottom" class="pagination"></div>
        </div>
        <script>
            window.B2BCS = {
                clientID: '<?php echo esc_js($clientId); ?>',
                secretKey: '<?php echo esc_js($secretKey); ?>',
                apiBase: '<?php echo esc_js($apiBase); ?>',
                ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                courseIds: <?php echo wp_json_encode($courseIds); ?>
            };
        </script>
        <?php
        wp_enqueue_script('b2b-cs-frontend');
        wp_enqueue_style('b2b-cs-frontend');
        return ob_get_clean();
    }
}


