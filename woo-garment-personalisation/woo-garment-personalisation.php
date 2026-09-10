<?php
/**
 * Plugin Name: WooCommerce Garment Name & Number
 * Plugin URI: https://xavadigital.com
 * Description: Adds optional Name and Number personalisation fields to garments. The fields are rendered as extra rows inside the WooCommerce variation table and styled to match the variation dropdowns exactly. Values are saved as normal order-item meta, so they show in the cart, checkout, emails, admin, packing slips and exports.
 * Version: 1.0.0
 * Author: Xava Digital
 * Author URI: https://xavadigital.com
 * License: GPL v2 or later
 * Text Domain: woo-garment-personalisation
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WGNN_VERSION', '1.0.0');
define('WGNN_FILE', __FILE__);

class WGNN_Plugin {

    const OPTION      = 'wgnn_options';
    const GROUP       = 'wgnn_group';
    const META_PREFIX = '_wgnn_';         // product meta: _wgnn_name / _wgnn_number = '' (default) | off | optional | required
    const CART_KEY    = 'wgnn_fields';    // cart item data key
    const ITEM_META   = '_wgnn';          // hidden order-item meta (source of truth)
    const NONCE       = 'wgnn_admin';

    /** @var bool front-end rows already printed for this request */
    protected $rendered = false;

    public function __construct() {
        // Front end.
        add_action('woocommerce_after_variations_table', array($this, 'render_variable'));
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_fallback'));
        add_filter('woocommerce_product_supports', array($this, 'product_supports'), 10, 3);

        // Cart / order.
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 2);
        add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'create_order_line_item'), 10, 3);
        add_filter('woocommerce_order_again_cart_item_data', array($this, 'order_again_cart_item_data'), 10, 2);
        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hidden_order_itemmeta'));

        // Admin.
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('woocommerce_screen_ids', array($this, 'screen_ids'));
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'product_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_field'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'), 10, 2);
        add_action('wp_ajax_wgnn_save_item', array($this, 'ajax_save_item'));

        add_action('before_woocommerce_init', function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WGNN_FILE, true);
            }
        });
    }

    /* ------------------------------------------------------------------ Settings */

    public static function defaults() {
        return array(
            'position'           => 'after',        // after | before the attribute rows
            'optional_suffix'    => '(optional)',

            'name_enabled'       => 'yes',
            'name_label'         => 'Name',
            'name_placeholder'   => '',
            'name_maxlength'     => 20,
            'name_default'       => 'off',          // off | optional | required (used unless the product overrides)
            'name_uppercase'     => 'no',

            'number_enabled'     => 'yes',
            'number_label'       => 'Number',
            'number_placeholder' => '',
            'number_maxlength'   => 3,
            'number_default'     => 'off',
        );
    }

    public function get($key) {
        $opts     = get_option(self::OPTION, array());
        $defaults = self::defaults();
        if (is_array($opts) && array_key_exists($key, $opts)) {
            return $opts[$key];
        }
        return isset($defaults[$key]) ? $defaults[$key] : '';
    }

    /**
     * Enabled field definitions, keyed by 'name' / 'number'.
     */
    public function fields() {
        static $fields = null;
        if ($fields !== null) {
            return $fields;
        }
        $fields = array();
        foreach (array('name' => 'text', 'number' => 'number') as $key => $type) {
            if ($this->get($key . '_enabled') !== 'yes') {
                continue;
            }
            $label = trim((string) $this->get($key . '_label'));
            if ($label === '') {
                $label = ucfirst($key);
            }
            $fields[$key] = array(
                'key'         => $key,
                'type'        => $type,
                'label'       => $label,
                'placeholder' => (string) $this->get($key . '_placeholder'),
                'maxlength'   => max(1, (int) $this->get($key . '_maxlength')),
                'default'     => self::mode($this->get($key . '_default')),
                'uppercase'   => $key === 'name' && $this->get('name_uppercase') === 'yes',
            );
        }
        return $fields;
    }

    /**
     * Normalise a mode value to off | optional | required.
     */
    public static function mode($value, $fallback = 'off') {
        return in_array($value, array('off', 'optional', 'required'), true) ? $value : $fallback;
    }

    public static function mode_labels() {
        return array(
            'off'      => __('Off', 'woo-garment-personalisation'),
            'optional' => __('Optional', 'woo-garment-personalisation'),
            'required' => __('Required', 'woo-garment-personalisation'),
        );
    }

    /**
     * Effective mode of one field for a product: the product's own setting, else the global default.
     */
    public function mode_for($product_id, $key) {
        $own = get_post_meta($product_id, self::META_PREFIX . $key, true);
        if (in_array($own, array('off', 'optional', 'required'), true)) {
            return $own;
        }
        $fields = $this->fields();
        return isset($fields[$key]) ? $fields[$key]['default'] : 'off';
    }

    /**
     * The field definitions that apply to this product (subset of fields()), each with
     * 'required' resolved for that product. Empty = nothing to show.
     */
    public function fields_for($product) {
        if (!($product instanceof WC_Product)) {
            $product = wc_get_product($product);
        }
        $all = $this->fields();
        if (!$product || empty($all)) {
            return array();
        }
        $parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

        $enabled = array();
        foreach ($all as $key => $def) {
            $mode = $this->mode_for($parent_id, $key);
            if ($mode === 'off') {
                continue;
            }
            $def['required'] = ($mode === 'required');
            $enabled[$key]   = $def;
        }
        return $enabled;
    }

    /**
     * Should any field be shown for this product?
     */
    public function is_enabled_for($product) {
        return !empty($this->fields_for($product));
    }

    /* ----------------------------------------------------------- Values helpers */

    /**
     * Pull field values out of a request-like array and sanitise them.
     *
     * @param array  $source e.g. $_POST
     * @param string $prefix input-name prefix ('wgnn_' on the front end, '' in admin)
     * @param array  $fields field definitions to read (defaults to all enabled fields)
     */
    public function collect_values(array $source, $prefix = 'wgnn_', $fields = null) {
        $values = array();
        $fields = is_array($fields) ? $fields : $this->fields();
        foreach ($fields as $key => $def) {
            $raw = isset($source[$prefix . $key]) ? wp_unslash($source[$prefix . $key]) : '';
            $values[$key] = $this->sanitize_value($raw, $def);
        }
        return $values;
    }

    public function sanitize_value($raw, array $def) {
        $value = sanitize_text_field((string) $raw);
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if (!empty($def['uppercase']) && $value !== '') {
            $value = function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
        }
        return $value;
    }

    /**
     * @return string[] error messages (empty when valid)
     */
    public function validate_values(array $values, $fields = null) {
        $errors = array();
        $fields = is_array($fields) ? $fields : $this->fields();
        foreach ($fields as $key => $def) {
            $value = isset($values[$key]) ? $values[$key] : '';
            if ($value === '') {
                if (!empty($def['required'])) {
                    /* translators: %s field label */
                    $errors[] = sprintf(__('Please enter a %s.', 'woo-garment-personalisation'), $def['label']);
                }
                continue;
            }
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($length > $def['maxlength']) {
                $errors[] = sprintf(
                    /* translators: 1: field label, 2: max length */
                    _n('%1$s must be %2$d character or fewer.', '%1$s must be %2$d characters or fewer.', $def['maxlength'], 'woo-garment-personalisation'),
                    $def['label'],
                    $def['maxlength']
                );
            }
            if ($def['type'] === 'number' && !preg_match('/^[0-9]+$/', $value)) {
                /* translators: %s field label */
                $errors[] = sprintf(__('%s must contain digits only.', 'woo-garment-personalisation'), $def['label']);
            } elseif ($def['type'] === 'text' && !preg_match("/^[\p{L}\p{N} .'\-]+$/u", $value)) {
                /* translators: %s field label */
                $errors[] = sprintf(__('%s may only contain letters, numbers, spaces, apostrophes, hyphens and full stops.', 'woo-garment-personalisation'), $def['label']);
            }
        }
        return $errors;
    }

    /* ---------------------------------------------------------------- Front end */

    /**
     * Variable products: hook fires immediately after </table class="variations">.
     */
    public function render_variable() {
        global $product;
        if ($product instanceof WC_Product && $product->is_type('variable')) {
            $this->render($product);
        }
    }

    /**
     * Everything else (simple products), plus a safety net for variable products
     * whose theme template lacks the woocommerce_after_variations_table hook.
     */
    public function render_fallback() {
        global $product;
        if ($product instanceof WC_Product) {
            $this->render($product);
        }
    }

    protected function render(WC_Product $product) {
        $fields = $this->fields_for($product);
        if ($this->rendered || empty($fields)) {
            return;
        }
        $this->rendered = true;

        $values   = !empty($_POST) ? $this->collect_values($_POST, 'wgnn_', $fields) : array(); // re-fill after a failed add
        $suffix   = trim((string) $this->get('optional_suffix'));
        $position = $this->get('position') === 'before' ? 'before' : 'after';

        wp_enqueue_script('wgnn-frontend', plugins_url('assets/js/frontend.js', WGNN_FILE), array(), WGNN_VERSION, true);
        $this->print_css();

        echo '<table class="variations wgnn-variations" cellspacing="0" role="presentation" data-wgnn-merge="1" data-wgnn-position="' . esc_attr($position) . '"><tbody>';
        foreach ($fields as $key => $def) {
            $id    = 'wgnn_' . $key;
            $value = isset($values[$key]) ? $values[$key] : '';
            $attrs = array(
                'type'           => 'text',
                'id'             => $id,
                'name'           => $id,
                'class'          => 'wgnn-input wgnn-input--' . $key,
                'value'          => $value,
                'maxlength'      => $def['maxlength'],
                'placeholder'    => $def['placeholder'],
                'autocomplete'   => 'off',
                'autocapitalize' => $def['uppercase'] ? 'characters' : 'off',
            );
            if ($def['type'] === 'number') {
                $attrs['inputmode'] = 'numeric';
                $attrs['pattern']   = '[0-9]*';
            }
            if ($def['required']) {
                $attrs['required'] = 'required';
            }
            $attr_html = '';
            foreach ($attrs as $k => $v) {
                if ($v === '' && $k !== 'value') {
                    continue;
                }
                $attr_html .= ' ' . $k . '="' . esc_attr($v) . '"';
            }

            echo '<tr class="wgnn-row wgnn-row--' . esc_attr($key) . '">';
            echo '<th class="label"><label for="' . esc_attr($id) . '">' . esc_html($def['label']);
            if (!$def['required'] && $suffix !== '') {
                echo ' <span class="wgnn-optional">' . esc_html($suffix) . '</span>';
            }
            echo '</label></th>';
            echo '<td class="value"><input' . $attr_html . ' /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    protected function print_css() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
        <style id="wgnn-css">
        table.variations td.value input.wgnn-input{max-width:100%;box-sizing:border-box;}
        table.wgnn-variations{margin-top:0;}
        .wgnn-optional{font-weight:400;font-size:.85em;opacity:.7;}
        </style>
        <?php
    }

    /**
     * Archive "add to cart" buttons bypass the product page, so when a field is
     * required, send the customer to the product page instead of adding via AJAX.
     */
    public function product_supports($supports, $feature, $product) {
        if ($feature === 'ajax_add_to_cart' && $supports) {
            foreach ($this->fields_for($product) as $def) {
                if ($def['required']) {
                    return false;
                }
            }
        }
        return $supports;
    }

    /* --------------------------------------------------------------- Cart flow */

    public function validate_add_to_cart($passed, $product_id, $quantity) {
        $fields = $this->fields_for($product_id);
        if (empty($fields)) {
            return $passed;
        }
        $errors = $this->validate_values($this->collect_values($_POST, 'wgnn_', $fields), $fields);
        foreach ($errors as $error) {
            wc_add_notice($error, 'error');
        }
        return empty($errors) ? $passed : false;
    }

    public function add_cart_item_data($cart_item_data, $product_id) {
        $fields = $this->fields_for($product_id);
        if (empty($fields)) {
            return $cart_item_data;
        }
        $values = array_filter($this->collect_values($_POST, 'wgnn_', $fields), 'strlen');
        if (!empty($values)) {
            $cart_item_data[self::CART_KEY] = $values;
        }
        return $cart_item_data;
    }

    public function get_item_data($item_data, $cart_item) {
        if (empty($cart_item[self::CART_KEY]) || !is_array($cart_item[self::CART_KEY])) {
            return $item_data;
        }
        $fields = $this->fields();
        foreach ($cart_item[self::CART_KEY] as $key => $value) {
            if ($value === '') {
                continue;
            }
            $label = isset($fields[$key]) ? $fields[$key]['label'] : ucfirst($key);
            $item_data[] = array(
                'key'   => $label,
                'value' => wc_clean($value),
            );
        }
        return $item_data;
    }

    public function create_order_line_item($item, $cart_item_key, $values) {
        if (empty($values[self::CART_KEY]) || !is_array($values[self::CART_KEY])) {
            return;
        }
        $this->apply_to_item($item, $values[self::CART_KEY]);
    }

    public function order_again_cart_item_data($cart_item_data, $item) {
        $stored = array_filter($this->read_item_values($item), 'strlen');
        if (!empty($stored)) {
            $cart_item_data[self::CART_KEY] = $stored;
        }
        return $cart_item_data;
    }

    public function hidden_order_itemmeta($keys) {
        $keys[] = self::ITEM_META;
        return $keys;
    }

    /* ---------------------------------------------------------- Order item meta */

    /**
     * Write values to an order item: a hidden array (key => label/value) plus one
     * visible meta entry per field, keyed by the field label so it shows everywhere.
     */
    public function apply_to_item(WC_Order_Item $item, array $values) {
        $fields = $this->fields();
        $stored = $item->get_meta(self::ITEM_META, true);
        $stored = is_array($stored) ? $stored : array();

        foreach (array_unique(array_merge(array_keys($fields), array_keys($stored))) as $key) {
            $label = isset($fields[$key]) ? $fields[$key]['label'] : (isset($stored[$key]['label']) ? $stored[$key]['label'] : ucfirst($key));
            $value = isset($values[$key]) ? (string) $values[$key] : '';

            // Drop a stale visible entry if the label has changed since the order was placed.
            if (isset($stored[$key]['label']) && $stored[$key]['label'] !== $label) {
                $item->delete_meta_data($stored[$key]['label']);
            }

            if ($value === '') {
                $item->delete_meta_data($label);
                unset($stored[$key]);
            } else {
                $item->update_meta_data($label, $value);
                $stored[$key] = array('label' => $label, 'value' => $value);
            }
        }

        if (empty($stored)) {
            $item->delete_meta_data(self::ITEM_META);
        } else {
            $item->update_meta_data(self::ITEM_META, $stored);
        }
    }

    /**
     * Current values on an order item (key => value). Prefers the visible meta so
     * edits made through WooCommerce's own item editor are respected.
     */
    public function read_item_values(WC_Order_Item $item) {
        $stored = $item->get_meta(self::ITEM_META, true);
        $values = array();
        if (is_array($stored)) {
            foreach ($stored as $key => $row) {
                $label   = isset($row['label']) ? $row['label'] : '';
                $visible = $label !== '' ? $item->get_meta($label, true) : '';
                $values[$key] = $visible !== '' ? (string) $visible : (isset($row['value']) ? (string) $row['value'] : '');
            }
        }
        return $values;
    }

    /* ------------------------------------------------------------ Admin: order */

    public function add_order_meta_box($post_type, $post_or_order = null) {
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
        if ($post_type !== 'shop_order' && $post_type !== $screen) {
            return;
        }
        $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;
        if (!($order instanceof WC_Order) || empty($this->relevant_items($order))) {
            return;
        }
        add_meta_box(
            'wgnn_order_items',
            __('Name & Number', 'woo-garment-personalisation'),
            array($this, 'render_order_meta_box'),
            $post_type,
            'normal',
            'default'
        );
    }

    /**
     * Line items that either have personalisation data or belong to an enabled product.
     */
    protected function relevant_items(WC_Order $order) {
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $stored   = $item->get_meta(self::ITEM_META, true);
            $has_data = is_array($stored) && !empty($stored);
            $product  = $item->get_product();
            if ($has_data || ($product && $this->is_enabled_for($product))) {
                $items[$item_id] = $item;
            }
        }
        return $items;
    }

    public function render_order_meta_box($post_or_order) {
        $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;
        if (!($order instanceof WC_Order)) {
            return;
        }
        $fields = $this->fields();
        ?>
        <p class="description"><?php esc_html_e('Edit the customer\'s name/number for each garment. Changes are saved immediately and recorded as an order note.', 'woo-garment-personalisation'); ?></p>
        <table class="widefat striped wgnn-admin-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Item', 'woo-garment-personalisation'); ?></th>
                    <?php foreach ($fields as $def) : ?>
                        <th><?php echo esc_html($def['label']); ?></th>
                    <?php endforeach; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->relevant_items($order) as $item_id => $item) :
                $values = $this->read_item_values($item); ?>
                <tr data-item-id="<?php echo esc_attr($item_id); ?>">
                    <td><strong><?php echo esc_html($item->get_name()); ?></strong> &times; <?php echo esc_html($item->get_quantity()); ?></td>
                    <?php foreach ($fields as $key => $def) : ?>
                        <td>
                            <input type="text"
                                   class="wgnn-admin-input"
                                   data-key="<?php echo esc_attr($key); ?>"
                                   value="<?php echo esc_attr(isset($values[$key]) ? $values[$key] : ''); ?>"
                                   maxlength="<?php echo esc_attr($def['maxlength']); ?>"
                                   style="width:100%" />
                        </td>
                    <?php endforeach; ?>
                    <td style="white-space:nowrap">
                        <button type="button" class="button wgnn-save"><?php esc_html_e('Save', 'woo-garment-personalisation'); ?></button>
                        <span class="wgnn-status" aria-live="polite"></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        (function ($) {
            var nonce = <?php echo wp_json_encode(wp_create_nonce(self::NONCE)); ?>;
            var orderId = <?php echo (int) $order->get_id(); ?>;
            $(document).on('click', '.wgnn-admin-table .wgnn-save', function () {
                var $btn = $(this), $row = $btn.closest('tr'), $status = $row.find('.wgnn-status');
                var values = {};
                $row.find('.wgnn-admin-input').each(function () { values[$(this).data('key')] = $(this).val(); });
                $btn.prop('disabled', true);
                $status.text('…').css('color', '');
                $.post(ajaxurl, { action: 'wgnn_save_item', nonce: nonce, order_id: orderId, item_id: $row.data('item-id'), values: values })
                    .done(function (res) {
                        $status.text(res.data && res.data.message ? res.data.message : (res.success ? 'Saved' : 'Error'))
                               .css('color', res.success ? '#008a20' : '#d63638');
                        if (res.success && res.data && res.data.values) {
                            $.each(res.data.values, function (k, v) { $row.find('.wgnn-admin-input[data-key="' + k + '"]').val(v); });
                        }
                    })
                    .fail(function () { $status.text('Request failed').css('color', '#d63638'); })
                    .always(function () { $btn.prop('disabled', false); });
            });
        })(jQuery);
        </script>
        <?php
    }

    public function ajax_save_item() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-garment-personalisation')));
        }
        $order = wc_get_order(isset($_POST['order_id']) ? absint($_POST['order_id']) : 0);
        $item  = $order ? $order->get_item(isset($_POST['item_id']) ? absint($_POST['item_id']) : 0) : false;
        if (!$order || !$item) {
            wp_send_json_error(array('message' => __('Order item not found.', 'woo-garment-personalisation')));
        }

        $source = isset($_POST['values']) && is_array($_POST['values']) ? $_POST['values'] : array();
        $values = $this->collect_values($source, '');
        $errors = $this->validate_values($values);
        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode(' ', $errors)));
        }

        $old = $this->read_item_values($item);
        $this->apply_to_item($item, $values);
        $item->save();

        $changes = array();
        foreach ($this->fields() as $key => $def) {
            $before = isset($old[$key]) ? $old[$key] : '';
            $after  = isset($values[$key]) ? $values[$key] : '';
            if ($before !== $after) {
                $changes[] = sprintf('%s: "%s" → "%s"', $def['label'], $before, $after);
            }
        }
        if (!empty($changes)) {
            $order->add_order_note(sprintf(
                /* translators: 1: item name, 2: list of changes */
                __('Name & Number updated on "%1$s" — %2$s', 'woo-garment-personalisation'),
                $item->get_name(),
                implode('; ', $changes)
            ));
        }

        wp_send_json_success(array(
            'message' => empty($changes) ? __('No changes', 'woo-garment-personalisation') : __('Saved', 'woo-garment-personalisation'),
            'values'  => $values,
        ));
    }

    /* ---------------------------------------------------------- Admin: product */

    public function product_field() {
        global $post;
        $product_id = $post instanceof WP_Post ? $post->ID : get_the_ID();
        $all        = $this->fields();
        $labels     = self::mode_labels();

        echo '<div class="options_group wgnn-product-options">';
        foreach (array('name' => __('Name field', 'woo-garment-personalisation'), 'number' => __('Number field', 'woo-garment-personalisation')) as $key => $label) {
            if (!isset($all[$key])) {
                continue; // switched off globally
            }
            $current = get_post_meta($product_id, self::META_PREFIX . $key, true);
            $options = array(
                /* translators: %s: the global default mode */
                '' => sprintf(__('Default (%s)', 'woo-garment-personalisation'), $labels[$all[$key]['default']]),
            ) + $labels;
            woocommerce_wp_select(array(
                'id'          => self::META_PREFIX . $key,
                'label'       => $label,
                'value'       => self::mode($current, ''),
                'options'     => $options,
                'desc_tip'    => true,
                'description' => __('Off: not shown. Optional: shown, customer may leave it blank. Required: customer must fill it in. Labels and lengths are under WooCommerce → Name & Number.', 'woo-garment-personalisation'),
            ));
        }
        echo '</div>';
    }

    public function save_product_field($post_id) {
        foreach (array('name', 'number') as $key) {
            $meta  = self::META_PREFIX . $key;
            $value = isset($_POST[$meta]) ? sanitize_text_field(wp_unslash($_POST[$meta])) : '';
            if (in_array($value, array('off', 'optional', 'required'), true)) {
                update_post_meta($post_id, $meta, $value);
            } elseif (isset($_POST[$meta])) {
                delete_post_meta($post_id, $meta); // back to default
            }
        }
    }

    /* --------------------------------------------------------- Admin: settings */

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Name & Number', 'woo-garment-personalisation'),
            __('Name & Number', 'woo-garment-personalisation'),
            'manage_woocommerce',
            'wgnn-settings',
            array($this, 'render_settings_page')
        );
    }

    public function screen_ids($ids) {
        $ids[] = 'woocommerce_page_wgnn-settings';
        return $ids;
    }

    public function register_settings() {
        register_setting(self::GROUP, self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default'           => array(),
        ));
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        $yesno = function ($key) use ($input) {
            return !empty($input[$key]) ? 'yes' : 'no';
        };
        $text = function ($key) use ($input) {
            return isset($input[$key]) ? sanitize_text_field(wp_unslash($input[$key])) : '';
        };
        $int = function ($key, $default) use ($input) {
            $v = isset($input[$key]) ? (int) $input[$key] : 0;
            return $v > 0 ? min($v, 100) : $default;
        };
        return array(
            'position'           => (isset($input['position']) && $input['position'] === 'before') ? 'before' : 'after',
            'optional_suffix'    => $text('optional_suffix'),

            'name_enabled'       => $yesno('name_enabled'),
            'name_label'         => $text('name_label'),
            'name_placeholder'   => $text('name_placeholder'),
            'name_maxlength'     => $int('name_maxlength', 20),
            'name_default'       => self::mode(isset($input['name_default']) ? $input['name_default'] : ''),
            'name_uppercase'     => $yesno('name_uppercase'),

            'number_enabled'     => $yesno('number_enabled'),
            'number_label'       => $text('number_label'),
            'number_placeholder' => $text('number_placeholder'),
            'number_maxlength'   => $int('number_maxlength', 3),
            'number_default'     => self::mode(isset($input['number_default']) ? $input['number_default'] : ''),
        );
    }

    public function render_settings_page() {
        $opt      = self::OPTION;

        $checkbox = function ($key, $label, $desc = '') use ($opt) {
            echo '<label><input type="checkbox" name="' . esc_attr($opt) . '[' . esc_attr($key) . ']" value="1" ' . checked($this->get($key), 'yes', false) . ' /> ' . esc_html($label) . '</label>';
            if ($desc) {
                echo '<p class="description">' . esc_html($desc) . '</p>';
            }
        };
        $textfield = function ($key, $type = 'text', $class = 'regular-text') use ($opt) {
            echo '<input type="' . esc_attr($type) . '" class="' . esc_attr($class) . '" name="' . esc_attr($opt) . '[' . esc_attr($key) . ']" value="' . esc_attr($this->get($key)) . '" ' . ($type === 'number' ? 'min="1" max="100"' : '') . ' />';
        };
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Name & Number personalisation', 'woo-garment-personalisation'); ?></h1>
            <p><?php esc_html_e('Adds Name and Number fields to the product page, inside the variation selector table, styled like the size/colour dropdowns. Values appear on the cart, checkout, emails, the order screen, packing slips and order exports.', 'woo-garment-personalisation'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <p><?php esc_html_e('Each product chooses Off, Optional or Required for the Name and Number fields separately (Product data → Inventory). The "Default for products" setting below applies to products that have not made a choice.', 'woo-garment-personalisation'); ?></p>

                <h2><?php esc_html_e('Layout', 'woo-garment-personalisation'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Row position', 'woo-garment-personalisation'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr($opt); ?>[position]">
                                <option value="after" <?php selected($this->get('position'), 'after'); ?>><?php esc_html_e('Below the size / colour rows', 'woo-garment-personalisation'); ?></option>
                                <option value="before" <?php selected($this->get('position'), 'before'); ?>><?php esc_html_e('Above the size / colour rows', 'woo-garment-personalisation'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wgnn_optional_suffix"><?php esc_html_e('"Optional" hint', 'woo-garment-personalisation'); ?></label></th>
                        <td>
                            <?php $textfield('optional_suffix'); ?>
                            <p class="description"><?php esc_html_e('Small text shown after the label of non-required fields. Leave blank to hide.', 'woo-garment-personalisation'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php foreach (array('name' => __('Name field', 'woo-garment-personalisation'), 'number' => __('Number field', 'woo-garment-personalisation')) as $key => $heading) : ?>
                    <h2><?php echo esc_html($heading); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enabled', 'woo-garment-personalisation'); ?></th>
                            <td><?php $checkbox($key . '_enabled', __('This field is available to products', 'woo-garment-personalisation')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Default for products', 'woo-garment-personalisation'); ?></th>
                            <td>
                                <select name="<?php echo esc_attr($opt); ?>[<?php echo esc_attr($key); ?>_default]">
                                    <?php foreach (self::mode_labels() as $mode => $mode_label) : ?>
                                        <option value="<?php echo esc_attr($mode); ?>" <?php selected($this->get($key . '_default'), $mode); ?>><?php echo esc_html($mode_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Used by products set to "Default". Off = only products that switch it on individually.', 'woo-garment-personalisation'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Label', 'woo-garment-personalisation'); ?></th>
                            <td>
                                <?php $textfield($key . '_label'); ?>
                                <p class="description"><?php esc_html_e('Also used as the column/meta name on orders, packing slips and exports.', 'woo-garment-personalisation'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Placeholder', 'woo-garment-personalisation'); ?></th>
                            <td><?php $textfield($key . '_placeholder'); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Maximum length', 'woo-garment-personalisation'); ?></th>
                            <td><?php $textfield($key . '_maxlength', 'number', 'small-text'); ?></td>
                        </tr>
                        <?php if ($key === 'name') : ?>
                            <tr>
                                <th scope="row"><?php esc_html_e('Uppercase', 'woo-garment-personalisation'); ?></th>
                                <td><?php $checkbox('name_uppercase', __('Convert the name to UPPERCASE when saving', 'woo-garment-personalisation')); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__('WooCommerce Garment Name & Number requires WooCommerce to be active.', 'woo-garment-personalisation')
                . '</p></div>';
        });
        return;
    }
    $GLOBALS['wgnn_plugin'] = new WGNN_Plugin();
});
