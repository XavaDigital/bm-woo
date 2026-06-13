<?php
/**
 * Global message templates (defaults) under WooCommerce -> Back-Order Dates.
 * Each template may use the placeholders: {date} {category} {cutoff} {product}.
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Settings {

    const OPTION = 'wcbd_options';
    const GROUP  = 'wcbd_options_group';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register'));
    }

    /**
     * Default templates. Used both as the fallback for get() and as field placeholders.
     */
    public static function defaults() {
        return array(
            // Display
            'msg_card'                => 'Expected delivery: {date}',
            'msg_product'             => 'This item is on backorder and is expected to ship around {date}.',
            'msg_cart'                => 'Backorder &mdash; expected {date}',
            'msg_checkout'            => '{product} &mdash; expected {date}',
            'msg_closed'              => 'Ordering for this item has closed.',
            'msg_out_of_stock'        => 'Out of stock',
            'msg_in_stock'            => 'In stock &mdash; ships now',
            // Checkout confirmation
            'checkout_intro'          => 'Some items in your order are on backorder and will not ship immediately:',
            'checkout_checkbox_label' => 'I understand these items are on backorder and will be delivered around the dates shown above.',
            'checkout_error'          => 'Please confirm you understand the back-order items before placing your order.',
            'overlay_heading'         => 'Please confirm your backorder',
            'overlay_confirm_label'   => 'I understand — continue to checkout',
            'overlay_cancel_label'    => 'Return to the store',
            'overlay_cancel_url'      => '',
            // Misc
            'cart_note_label'         => 'Delivery',
        );
    }

    /**
     * Get a single setting, falling back to the default when unset/empty.
     */
    public static function get($key) {
        $opts     = get_option(self::OPTION, array());
        $defaults = self::defaults();

        if (is_array($opts) && isset($opts[$key]) && $opts[$key] !== '') {
            return $opts[$key];
        }
        return isset($defaults[$key]) ? $defaults[$key] : '';
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __('Back-Order Dates', 'wc-backorder-dates'),
            __('Back-Order Dates', 'wc-backorder-dates'),
            'manage_woocommerce',
            'wcbd-settings',
            array($this, 'render_page')
        );
    }

    public function register() {
        register_setting(self::GROUP, self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize'),
            'default'           => array(),
        ));
    }

    public function sanitize($input) {
        $clean = array();
        foreach (array_keys(self::defaults()) as $key) {
            $clean[$key] = isset($input[$key]) ? wp_kses_post(trim($input[$key])) : '';
        }
        return $clean;
    }

    public function render_page() {
        $fields = array(
            __('Display messages', 'wc-backorder-dates') => array(
                'msg_card'         => __('Card message (shortcode context="card")', 'wc-backorder-dates'),
                'msg_product'      => __('Product page message (shortcode context="product")', 'wc-backorder-dates'),
                'msg_cart'         => __('Cart / order note', 'wc-backorder-dates'),
                'msg_checkout'     => __('Checkout confirmation line (per item)', 'wc-backorder-dates'),
                'msg_closed'       => __('Ordering closed message (cutoff passed)', 'wc-backorder-dates'),
                'msg_out_of_stock' => __('Generic out-of-stock message', 'wc-backorder-dates'),
                'msg_in_stock'     => __('In-stock message (shortcode show_instock="yes")', 'wc-backorder-dates'),
            ),
            __('Checkout confirmation', 'wc-backorder-dates') => array(
                'checkout_intro'          => __('Intro text (overlay + checkbox)', 'wc-backorder-dates'),
                'checkout_checkbox_label' => __('Required checkbox label', 'wc-backorder-dates'),
                'checkout_error'          => __('Error shown if the box is not ticked', 'wc-backorder-dates'),
                'overlay_heading'         => __('Blocking overlay heading', 'wc-backorder-dates'),
                'overlay_confirm_label'   => __('Overlay confirm button label', 'wc-backorder-dates'),
                'overlay_cancel_label'    => __('Overlay cancel button label', 'wc-backorder-dates'),
                'overlay_cancel_url'      => __('Cancel button URL (blank = site home)', 'wc-backorder-dates'),
            ),
            __('Labels', 'wc-backorder-dates') => array(
                'cart_note_label' => __('Cart / order note label', 'wc-backorder-dates'),
            ),
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Back-Order Dates', 'wc-backorder-dates'); ?></h1>
            <p>
                <?php esc_html_e('Set the date on each product category under Products &rarr; Categories. These are the global default messages; any category can override them on its own edit screen.', 'wc-backorder-dates'); ?>
                <br>
                <?php
                printf(
                    /* translators: list of placeholders */
                    esc_html__('Available placeholders: %s', 'wc-backorder-dates'),
                    '<code>{date}</code> <code>{category}</code> <code>{cutoff}</code> <code>{product}</code>'
                );
                ?>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <?php foreach ($fields as $section => $section_fields) : ?>
                    <h2><?php echo esc_html($section); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php foreach ($section_fields as $key => $label) : ?>
                            <tr>
                                <th scope="row">
                                    <label for="wcbd_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                                </th>
                                <td>
                                    <input type="text"
                                           class="large-text"
                                           id="wcbd_<?php echo esc_attr($key); ?>"
                                           name="<?php echo esc_attr(self::OPTION); ?>[<?php echo esc_attr($key); ?>]"
                                           value="<?php echo esc_attr(self::get($key)); ?>" />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endforeach; ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
