<?php
/**
 * این فایل مسئول ثبت شورت‌کد و نمایش فرم به کاربر است.
 * نسخه نهایی: فرم خرید در داخل قاب آیفون نمایش داده می‌شود.
 */

if (!defined('ABSPATH')) {
    exit;
}

function it_register_shortcode() {
    add_shortcode('instagram_tools_form', 'it_render_form');
}
add_action('init', 'it_register_shortcode');

function it_render_form() {
    
    // کد PHP برای نمایش پاپ‌آپ (بدون تغییر)
    if (isset($_GET['it_status']) && $_GET['it_status'] === 'success' && isset($_GET['order_id'])) {
        $order_id = intval($_GET['order_id']);
        $order = wc_get_order($order_id);
        if ($order) {
            $view_order_url = $order->get_view_order_url();
            $script_handle = 'it-show-success-popup-script';
            wp_register_script($script_handle, '', array('jquery'), false, true);
            wp_enqueue_script($script_handle);
            wp_add_inline_script($script_handle, "
                jQuery(document).ready(function($) {
                    $('#it-success-popup-overlay').css('display', 'flex').hide().fadeIn(300);
                    setTimeout(function() {
                        window.location.href = '" . esc_url_raw($view_order_url) . "';
                    }, 3000);
                });
            ");
        }
    }
    
    ob_start();
    
    $selected_cat_ids = get_option('it_selected_categories', array());
    if (empty($selected_cat_ids)) {
        return '<p>هیچ دسته‌بندی برای نمایش تنظیم نشده است. لطفاً با مدیر سایت تماس بگیرید.</p>';
    }

    $categories = get_terms(array(
        'taxonomy'   => 'product_cat',
        'include'    => $selected_cat_ids,
        'hide_empty' => false,
    ));
    
    ?>
    <div class="iphone-mockup">
        <div class="iphone-frame">
            <div class="iphone-screen">
                <div class="dynamic-island"></div>
                <div class="iphone-content">

                    <form id="it-multi-step-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <input type="hidden" name="action" value="handle_instagram_form">
                        <?php wp_nonce_field('handle_instagram_form_nonce'); ?>

                        <div id="it-step-1" class="it-step">
                            <h2>مرحله ۱: انتخاب دسته‌بندی</h2>
                            <select name="category_id" id="it-category-select" required>
                                <option value="">یک دسته‌بندی را انتخاب کنید...</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="it-step-2" class="it-step" style="display:none;">
                            <h2>مرحله ۲: انتخاب محصول</h2>
                            <select name="product_id" id="it-product-select" required>
                                <option value="">لطفاً صبر کنید...</option>
                            </select>
                        </div>

                        <div id="it-step-3" class="it-step" style="display:none;">
                            <h2>مرحله ۳: توضیحات محصول</h2>
                            <div id="it-product-description" class="product-short-description"></div>
                            <label for="it-instagram-id-input">آیدی اینستاگرام خود را وارد کنید:</label>
                            <input type="text" id="it-instagram-id-input" name="instagram_id" placeholder="@username" required>
                        </div>

                        <div id="it-step-4" class="it-step" style="display:none;">
                            <h2>مرحله ۴: تعداد</h2>
                            <input type="number" name="quantity" min="1" required placeholder="تعداد مورد نظر">
                            <button type="button" id="it-show-invoice-btn" class="ig-action-button">نمایش پیش‌فاکتور</button>
                        </div>

                        <div id="it-step-5" class="it-step" style="display:none;">
                            <h2>مرحله ۵: پیش‌فاکتور</h2>
                            <div id="it-invoice-details"></div>
                            <button type="submit" name="submit_order" class="back-to-orders-button">پرداخت نهایی</button>
                        </div>
                    </form>

                </div></div></div></div><div id="it-success-popup-overlay" style="display: none;">
        <div id="it-success-popup-box">
            <div class="popup-icon">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/><path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg>
            </div>
            <h3 class="popup-title">سفارش موفق!</h3>
            <p class="popup-message">سفارش شما با موفقیت ثبت شد. تا چند لحظه دیگر به صفحه جزئیات سفارش هدایت می‌شوید.</p>
            <div class="popup-timer-bar"><div class="popup-timer-progress"></div></div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

// تابع ایجکس (بدون تغییر)
function it_get_products_by_category() {
    check_ajax_referer('it_get_products_nonce', 'nonce');
    $cat_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    if ($cat_id === 0) {
        wp_send_json_error('دسته بندی نامعتبر است.');
    }
    $products = wc_get_products(array('category' => array(get_term($cat_id)->slug), 'status' => 'publish', 'limit' => -1));
    $product_data = array();
    foreach ($products as $product) {
        $product_data[] = array('id' => $product->get_id(), 'name' => $product->get_name(), 'price' => $product->get_price(), 'short_description' => nl2br($product->get_short_description()));
    }
    wp_send_json_success($product_data);
}
add_action('wp_ajax_get_products_by_category', 'it_get_products_by_category');
add_action('wp_ajax_nopriv_get_products_by_category', 'it_get_products_by_category');
?>