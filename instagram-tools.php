<?php
/**
 * Plugin Name:       Instagram Tools
 * Description:       افزونه‌ای برای فروش محصولات ووکامرس با دریافت آیدی اینستاگرام.
 * Version:           3.0.0 (Final Architecture)
 * Author:            Mustafa Solimani
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// تعریف ثابت‌های مسیر
define('IT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IT_PLUGIN_URL', plugin_dir_url(__FILE__));

// 1. بارگذاری فایل‌های جانبی
require_once IT_PLUGIN_DIR . 'includes/admin-page.php';
require_once IT_PLUGIN_DIR . 'includes/shortcode-form.php';
require_once IT_PLUGIN_DIR . 'includes/form-handler.php';

// 2. ثبت تمام هوک‌های افزونه با استراتژی جدید و ضد تداخل
add_action('admin_menu', 'it_add_admin_menu');
add_action('init', 'it_register_shortcode');
add_action('wp_enqueue_scripts', 'it_enqueue_scripts');
add_action('admin_post_nopriv_handle_instagram_form', 'it_handle_form_submission');
add_action('admin_post_handle_instagram_form', 'it_handle_form_submission');
add_action('wp_ajax_get_products_by_category', 'it_get_products_by_category');
add_action('wp_ajax_nopriv_get_products_by_category', 'it_get_products_by_category');
add_action('woocommerce_admin_order_data_after_billing_address', 'it_display_instagram_id_in_admin_order', 10, 1);
add_filter('template_include', 'it_no_footer_template_for_shortcode', 99);
add_filter('wc_get_template', 'it_custom_view_order_template', 10, 5);
add_action('woocommerce_product_query', 'it_exclude_from_shop_and_archives'); // هوک جدید و امن برای فروشگاه
add_action('pre_get_posts', 'it_exclude_from_search_results'); // هوک pre_get_posts فقط برای جستجو
add_filter('woocommerce_related_products_args', 'it_exclude_from_related_products', 10, 2);
add_action('template_redirect', 'it_redirect_hidden_product_pages');


// 3. تعریف تمام توابع افزونه

function it_enqueue_scripts() {
    global $post;
    $load_css = (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'instagram_tools_form')) || is_wc_endpoint_url('view-order');
    if ($load_css) {
        wp_enqueue_style('it-frontend-css', IT_PLUGIN_URL . 'assets/css/frontend.css', array(), '3.0.0');
    }
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'instagram_tools_form')) {
        wp_enqueue_script('it-frontend-js', IT_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), '3.0.0', true);
        wp_localize_script('it-frontend-js', 'it_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('it_get_products_nonce')
        ));
    }
}

// توابع it_display_instagram_id_in_admin_order, it_no_footer_template_for_shortcode, it_custom_view_order_template بدون تغییر باقی می‌مانند
function it_display_instagram_id_in_admin_order($order) { if ($instagram_id = $order->get_meta('_billing_instagram_id')) { echo '<p><strong>آیدی اینستاگرام:</strong> ' . esc_html($instagram_id) . '</p>'; } }
function it_no_footer_template_for_shortcode($template) { global $post; $load_no_footer = false; if (is_singular() && isset($post->post_content) && has_shortcode($post->post_content, 'instagram_tools_form')) { $load_no_footer = true; } if (is_wc_endpoint_url('view-order')) { $order_id = get_query_var('view-order'); if ($order_id && ($order = wc_get_order($order_id)) && $order->get_meta('_billing_instagram_id')) { $load_no_footer = true; } } if ($load_no_footer) { $new_template = IT_PLUGIN_DIR . 'templates/template-no-footer.php'; if (file_exists($new_template)) { return $new_template; } } return $template; }
function it_custom_view_order_template($located, $template_name, $args, $template_path, $default_path) { if ('myaccount/view-order.php' !== $template_name) { return $located; } $order_id = isset($args['order_id']) ? $args['order_id'] : get_query_var('view-order'); if ($order_id && ($order = wc_get_order($order_id)) && $order->get_meta('_billing_instagram_id')) { $custom_template = IT_PLUGIN_DIR . 'templates/view-instagram-order.php'; if (file_exists($custom_template)) { return $custom_template; } } return $located; }


// --- توابع بخش خصوصی‌سازی محصولات (بازنویسی شده با استراتژی جدید) ---

function it_get_hidden_product_ids() {
    static $hidden_ids = null;
    if ($hidden_ids !== null) { return $hidden_ids; }
    $hidden_category_ids = get_option('it_selected_categories', array());
    if (empty($hidden_category_ids)) { $hidden_ids = array(); return $hidden_ids; }
    global $wpdb;
    $sanitized_cat_ids = array_map('intval', $hidden_category_ids);
    $cat_ids_string = implode(',', $sanitized_cat_ids);
    $query = "SELECT DISTINCT p.ID FROM {$wpdb->posts} AS p INNER JOIN {$wpdb->term_relationships} AS tr ON p.ID = tr.object_id INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE p.post_type = 'product' AND p.post_status = 'publish' AND tt.taxonomy = 'product_cat' AND tt.term_id IN ({$cat_ids_string})";
    $results = $wpdb->get_col($query);
    $hidden_ids = array_map('intval', $results);
    return $hidden_ids;
}

// 1. تابع جدید برای مخفی کردن از صفحات فروشگاه و دسته‌بندی‌ها
function it_exclude_from_shop_and_archives($query) {
    $hidden_product_ids = it_get_hidden_product_ids();
    if (!empty($hidden_product_ids)) {
        $query->set('post__not_in', array_merge($query->get('post__not_in', array()), $hidden_product_ids));
    }
}

// 2. تابع جدید برای مخفی کردن فقط از نتایج جستجو
function it_exclude_from_search_results($query) {
    if (is_admin() || !$query->is_search() || !$query->is_main_query()) {
        return;
    }
    $hidden_product_ids = it_get_hidden_product_ids();
    if (!empty($hidden_product_ids)) {
        $query->set('post__not_in', array_merge($query->get('post__not_in', array()), $hidden_product_ids));
    }
}

// 3. تابع مخفی کردن از محصولات مرتبط (بدون تغییر)
function it_exclude_from_related_products($args, $product_id) {
    $hidden_product_ids = it_get_hidden_product_ids();
    if (!empty($hidden_product_ids)) {
        $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], $hidden_product_ids);
    }
    return $args;
}

// 4. تابع هدایت صفحات تکی (بدون تغییر)
function it_redirect_hidden_product_pages() {
    if (!is_singular('product')) {
        return;
    }
    $hidden_product_ids = it_get_hidden_product_ids();
    if (in_array(get_the_ID(), $hidden_product_ids)) {
        $redirect_url = home_url('/social-media-services/');
        wp_safe_redirect($redirect_url, 301);
        exit();
    }
}

/**
 * فراخوانی استایل‌های سفارشی برای صفحه تنظیمات افزونه در پیشخوان
 */
add_action( 'admin_enqueue_scripts', 'it_enqueue_admin_styles' );
function it_enqueue_admin_styles( $hook ) {
    // این کد تضمین می‌کند که استایل ما فقط در صفحه تنظیمات خودمان لود شود و با جای دیگری تداخل نکند.
    // 'toplevel_page_instagram-tools-settings' هوک مخصوص صفحه ماست.
    if ( 'toplevel_page_instagram-tools-settings' != $hook ) {
        return;
    }
    wp_enqueue_style( 'it-admin-style', IT_PLUGIN_URL . 'assets/css/admin.css', array(), '1.0.0' );
}

/**
 * افزودن کلاس سفارشی به تگ body برای پس‌زمینه گرادینت
 */
add_filter( 'body_class', 'it_add_gradient_body_class' );
function it_add_gradient_body_class( $classes ) {
    
    // شرایطی که می‌خواهیم پس‌زمینه گرادینت داشته باشیم
    $is_form_page = ( is_singular() && has_shortcode( get_post()->post_content, 'instagram_tools_form' ) );
    $is_view_order_page = false;
    if ( is_wc_endpoint_url('view-order') ) {
        $order_id = get_query_var('view-order');
        if ( $order_id && ($order = wc_get_order($order_id)) && $order->get_meta('_billing_instagram_id') ) {
            $is_view_order_page = true;
        }
    }

    // اگر یکی از شرایط برقرار بود، کلاس را اضافه کن
    if ( $is_form_page || $is_view_order_page ) {
        $classes[] = 'it-gradient-background';
    }

    return $classes;
}