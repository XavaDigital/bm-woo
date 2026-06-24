<?php
/**
 * Plugin Name: WooCommerce Size Chart Confirmation
 * Plugin URI: https://xavadigital.com
 * Description: Requires customers to confirm they have checked the size chart before adding a variable product to the cart. Adds a required checkbox + message to the product page (inside the add-to-cart form) and validates it server-side.
 * Version: 1.0.0
 * Author: Xava Digital
 * Author URI: https://xavadigital.com
 * License: GPL v2 or later
 * Text Domain: woo-size-chart-confirm
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WSCC_VERSION', '1.0.0');

class WSCC_Plugin {

    const OPTION = 'wscc_options';
    const GROUP  = 'wscc_group';
    const META   = '_wscc_size_chart_url';

    public function __construct() {
        // Front end: render inside the add-to-cart form (full-width, above the
        // quantity/button row) and validate the add.
        add_action('woocommerce_before_single_variation', array($this, 'render_gate'));
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate'), 20, 3);

        // Admin: settings page + per-product size-chart URL field.
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'product_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_field'));
    }

    /* ---------------------------------------------------------------------- Settings */

    public static function defaults() {
        return array(
            'message'        => 'IMPORTANT: Our sizing may differ from your expectations. Please review the size chart before ordering. As these products are made to order, we cannot offer refunds or exchanges for incorrect size choices.',
            'checkbox_label' => 'I have checked the size chart and confirm my size selection.',
            'link_text'      => 'View the size chart',
            'link_url'       => '',
            'error'          => 'Please confirm you have checked the size chart before adding this item to your cart.',
        );
    }

    public function get($key) {
        $opts     = get_option(self::OPTION, array());
        $defaults = self::defaults();
        if (is_array($opts) && isset($opts[$key]) && $opts[$key] !== '') {
            return $opts[$key];
        }
        return isset($defaults[$key]) ? $defaults[$key] : '';
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Size Chart Confirm', 'woo-size-chart-confirm'),
            __('Size Chart Confirm', 'woo-size-chart-confirm'),
            'manage_woocommerce',
            'wscc-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(self::GROUP, self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize'),
            'default'           => array(),
        ));
    }

    public function sanitize($input) {
        return array(
            'message'        => isset($input['message']) ? wp_kses_post(trim($input['message'])) : '',
            'checkbox_label' => isset($input['checkbox_label']) ? wp_kses_post(trim($input['checkbox_label'])) : '',
            'link_text'      => isset($input['link_text']) ? sanitize_text_field($input['link_text']) : '',
            'link_url'       => isset($input['link_url']) ? esc_url_raw(trim($input['link_url'])) : '',
            'error'          => isset($input['error']) ? sanitize_text_field($input['error']) : '',
        );
    }

    public function render_settings_page() {
        $fields = array(
            'message'        => array(__('Confirmation message', 'woo-size-chart-confirm'), 'textarea'),
            'checkbox_label' => array(__('Checkbox label', 'woo-size-chart-confirm'), 'text'),
            'link_text'      => array(__('Size-chart link text', 'woo-size-chart-confirm'), 'text'),
            'link_url'       => array(__('Global fallback size-chart URL', 'woo-size-chart-confirm'), 'text'),
            'error'          => array(__('Error if not confirmed', 'woo-size-chart-confirm'), 'text'),
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Size Chart Confirmation', 'woo-size-chart-confirm'); ?></h1>
            <p>
                <?php esc_html_e('Shown on variable products before add-to-cart. Set each product\'s size-chart URL on its Inventory tab; this page holds the global text and a fallback URL.', 'woo-size-chart-confirm'); ?>
                <br>
                <?php
                printf(
                    /* translators: %s placeholder token */
                    esc_html__('In the message, %s is replaced with the size-chart link.', 'woo-size-chart-confirm'),
                    '<code>{size_chart}</code>'
                );
                ?>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <table class="form-table" role="presentation">
                    <?php foreach ($fields as $key => $field) :
                        list($label, $type) = $field; ?>
                        <tr>
                            <th scope="row"><label for="wscc_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <?php if ($type === 'textarea') : ?>
                                    <textarea class="large-text" rows="3" id="wscc_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($this->get($key)); ?></textarea>
                                <?php else : ?>
                                    <input type="text" class="large-text" id="wscc_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($this->get($key)); ?>" />
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------- Product field */

    public function product_field() {
        echo '<div class="options_group">';
        woocommerce_wp_text_input(array(
            'id'          => self::META,
            'label'       => __('Size chart URL', 'woo-size-chart-confirm'),
            'type'        => 'url',
            'desc_tip'    => true,
            'description' => __('Linked from the size-chart confirmation (variable products). Blank = use the global fallback.', 'woo-size-chart-confirm'),
        ));
        echo '</div>';
    }

    public function save_product_field($post_id) {
        $url = isset($_POST[self::META]) ? esc_url_raw(wp_unslash($_POST[self::META])) : '';
        update_post_meta($post_id, self::META, $url);
    }

    /* ----------------------------------------------------------------- Front end */

    /**
     * Render the confirmation box + required checkbox inside the add-to-cart form,
     * for variable products only.
     */
    public function render_gate() {
        global $product;
        if (!($product instanceof WC_Product) || !$product->is_type('variable')) {
            return;
        }

        $url = (string) get_post_meta($product->get_id(), self::META, true);
        if ($url === '') {
            $url = $this->get('link_url');
        }

        $link_html = '';
        if ($url !== '') {
            $link_html = '<a class="wscc-confirm__link" href="' . esc_url($url) . '" target="_blank" rel="noopener">'
                . esc_html($this->get('link_text')) . '</a>';
        }

        $message = wp_kses_post($this->get('message'));

        echo '<div class="wscc-confirm">';

        if (strpos($message, '{size_chart}') !== false) {
            echo '<p class="wscc-confirm__msg">' . str_replace('{size_chart}', $link_html, $message) . '</p>';
        } else {
            echo '<p class="wscc-confirm__msg">' . $message . '</p>';
            if ($link_html !== '') {
                echo '<p class="wscc-confirm__linkrow">' . $link_html . '</p>';
            }
        }

        echo '<label class="wscc-confirm__check">'
            . '<input type="checkbox" name="wscc_confirm" id="wscc_confirm" value="1" required /> '
            . '<span>' . wp_kses_post($this->get('checkbox_label')) . '</span>'
            . '</label>';

        echo '</div>';

        $this->print_css();
    }

    /**
     * Server-side enforcement (backstop for the required checkbox).
     */
    public function validate($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable') && empty($_POST['wscc_confirm'])) {
            wc_add_notice($this->get('error'), 'error');
            return false;
        }
        return $passed;
    }

    /**
     * Inline CSS (printed once) so styling survives "remove unused CSS" optimisers.
     */
    protected function print_css() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
        <style id="wscc-css">
        .wscc-confirm{display:block;width:100%;flex:0 0 100%;clear:both;box-sizing:border-box;margin:1em 0;padding:1em 1.2em;border:1px solid #e0a96d;border-radius:4px;background:#fff7ed;color:#1f2937;}
        .wscc-confirm__msg{margin:0 0 .6em;font-weight:600;}
        .wscc-confirm__linkrow{margin:0 0 .6em;}
        .wscc-confirm__link{text-decoration:underline;}
        .wscc-confirm__check{display:flex;gap:.5em;align-items:flex-start;font-weight:400;margin:0;}
        .wscc-confirm__check input{margin-top:.2em;}
        </style>
        <?php
    }
}

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('WooCommerce Size Chart Confirmation requires WooCommerce to be active.', 'woo-size-chart-confirm')
                . '</p></div>';
        });
        return;
    }
    new WSCC_Plugin();
});
