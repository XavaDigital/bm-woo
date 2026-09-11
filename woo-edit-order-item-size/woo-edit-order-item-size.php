<?php
/**
 * Plugin Name: WooCommerce Edit Order Item Size
 * Plugin URI: https://xavadigital.com
 * Description: Edit the product, variation (size, colour, etc.), Name & Number personalisation and Extra Product Options of an ordered garment directly from the WooCommerce order screen.
 * Version: 2.0.0
 * Author: Xava Digital
 * Author URI: https://xavadigital.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-edit-order-item-size
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 11.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('WEOIS_VERSION', '2.0.0');
define('WEOIS_FILE', __FILE__);

class WooEditOrderItemSize {

    const NONCE = 'woo-edit-item-size';

    public function __construct() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        add_action('woocommerce_before_order_itemmeta', array($this, 'add_edit_button'), 10, 3);

        add_action('wp_ajax_get_edit_form', array($this, 'ajax_get_edit_form'));
        add_action('wp_ajax_save_order_item_variation', array($this, 'ajax_save_variation'));
        add_action('wp_ajax_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_load_product_variations', array($this, 'ajax_load_product_variations'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Tidy the order-item meta display (hide technical keys, de-duplicate legacy entries).
        add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'remove_duplicate_meta_display'), 10, 2);

        add_action('before_woocommerce_init', function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WEOIS_FILE, true);
            }
        });
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('WooCommerce Edit Order Item Size requires WooCommerce to be installed and active.', 'woo-edit-order-item-size')
            . '</p></div>';
    }

    /* ------------------------------------------------------------------ Helpers */

    /**
     * The Name & Number plugin instance, if active.
     *
     * @return WGNN_Plugin|null
     */
    private function wgnn() {
        return (isset($GLOBALS['wgnn_plugin']) && class_exists('WGNN_Plugin')) ? $GLOBALS['wgnn_plugin'] : null;
    }

    /**
     * Load an order item by ID (works with and without HPOS).
     *
     * @return WC_Order_Item_Product|false
     */
    private function get_item($item_id) {
        $item = WC_Order_Factory::get_order_item($item_id);
        return ($item instanceof WC_Order_Item_Product) ? $item : false;
    }

    /**
     * Variation attribute definitions of a variable product, keyed by the meta key
     * WooCommerce itself uses on order items ('pa_size' for global attributes,
     * 'colour' for custom ones).
     *
     * Each entry: name (attribute name as WooCommerce reports it), taxonomy ('' for custom),
     * label (human readable), form_key ('attribute_<meta_key>'), options (slug => display).
     */
    private function get_attribute_defs(WC_Product $parent) {
        $defs = array();
        foreach ($parent->get_variation_attributes() as $name => $options) {
            $is_tax   = taxonomy_exists($name);
            $meta_key = $is_tax ? $name : sanitize_title($name);
            $opts     = array();
            foreach ((array) $options as $option) {
                $display = $option;
                if ($is_tax) {
                    $term = get_term_by('slug', $option, $name);
                    if ($term && !is_wp_error($term)) {
                        $display = $term->name;
                    }
                }
                $opts[(string) $option] = (string) $display;
            }
            $defs[$meta_key] = array(
                'name'     => $name,
                'taxonomy' => $is_tax ? $name : '',
                'label'    => wc_attribute_label($name, $parent),
                'form_key' => 'attribute_' . $meta_key,
                'options'  => $opts,
            );
        }
        return $defs;
    }

    /**
     * Convert a stored value (slug, term name, or option text in any case) to the
     * option key used in the dropdown. Unknown values are returned unchanged.
     */
    private function normalize_option($raw, array $def) {
        $raw = is_scalar($raw) ? trim((string) $raw) : '';
        if ($raw === '') {
            return '';
        }
        if (isset($def['options'][$raw])) {
            return $raw;
        }
        foreach ($def['options'] as $slug => $display) {
            if (strcasecmp($display, $raw) === 0 || strcasecmp($slug, $raw) === 0 || sanitize_title($raw) === sanitize_title($slug)) {
                return $slug;
            }
        }
        if ($def['taxonomy']) {
            $term = get_term_by('name', $raw, $def['taxonomy']);
            if ($term && !is_wp_error($term)) {
                return $term->slug;
            }
        }
        return $raw;
    }

    /**
     * Display text for an option key.
     */
    private function option_display($value, array $def) {
        if ($value === '') {
            return '';
        }
        if (isset($def['options'][$value])) {
            return $def['options'][$value];
        }
        if ($def['taxonomy']) {
            $term = get_term_by('slug', $value, $def['taxonomy']);
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }
        return $value;
    }

    /**
     * Current attribute values of an order item (meta_key => option key), read from the
     * order item's own meta first (this is where the customer's choice for "Any ..."
     * attributes lives), then from the variation product as a fallback.
     */
    private function get_item_attribute_values(WC_Order_Item_Product $item, array $defs) {
        $meta = array();
        foreach ($item->get_meta_data() as $m) {
            if (!is_array($m->value)) {
                $meta[$m->key] = (string) $m->value;
            }
        }

        $values = array();
        foreach ($defs as $meta_key => $def) {
            $raw = '';
            // Native key, 'attribute_' key (cart format), label key (legacy format of this
            // plugin), raw attribute name.
            foreach (array($meta_key, 'attribute_' . $meta_key, $def['label'], $def['name']) as $k) {
                if (isset($meta[$k]) && $meta[$k] !== '') {
                    $raw = $meta[$k];
                    break;
                }
            }
            $values[$meta_key] = $this->normalize_option($raw, $def);
        }

        $variation = $item->get_product();
        if ($variation && $variation->is_type('variation')) {
            foreach ($variation->get_variation_attributes() as $k => $v) {
                $mk = str_replace('attribute_', '', $k);
                if ($v !== '' && isset($values[$mk]) && $values[$mk] === '') {
                    $values[$mk] = $this->normalize_option($v, $defs[$mk]);
                }
            }
        }

        return $values;
    }

    /**
     * All meta keys that represent variation attributes for the given products
     * (native keys, cart-format keys, and the legacy label keys this plugin used to write).
     */
    private function attribute_meta_keys(array $defs_list) {
        $keys = array();
        foreach ($defs_list as $defs) {
            foreach ($defs as $meta_key => $def) {
                $keys[] = $meta_key;
                $keys[] = 'attribute_' . $meta_key;
                $keys[] = $def['label'];
                $keys[] = $def['name'];
            }
        }
        return array_unique($keys);
    }

    /**
     * Find the variation of $parent that matches the selected values. Attributes the
     * variation leaves as "Any" match anything. Prefers the most specific match.
     *
     * @return int variation ID, or 0
     */
    private function find_matching_variation(WC_Product $parent, array $selected) {
        $best_id    = 0;
        $best_score = -1;

        foreach ($parent->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            if (!$variation || !$variation->is_type('variation')) {
                continue;
            }
            $score = 0;
            $match = true;
            foreach ($variation->get_variation_attributes() as $k => $v) {
                $mk = str_replace('attribute_', '', $k);
                if ($v === '' || $v === null) {
                    continue; // "Any"
                }
                $want = isset($selected[$mk]) ? $selected[$mk] : '';
                if (sanitize_title($v) !== sanitize_title($want)) {
                    $match = false;
                    break;
                }
                $score++;
            }
            if ($match && $score > $best_score) {
                $best_score = $score;
                $best_id    = $child_id;
            }
        }

        return $best_id;
    }

    /* ---------------------------------------------------------------- Order UI */

    public function add_edit_button($item_id, $item, $product) {
        if (!$product || !($item instanceof WC_Order_Item_Product)) {
            return;
        }
        $wgnn      = $this->wgnn();
        $has_wgnn  = $wgnn && ($wgnn->is_enabled_for($product) || $wgnn->read_item_values($item));
        if (!$product->is_type('variation') && !$has_wgnn) {
            return;
        }
        ?>
        <button type="button" class="button edit-item-variation" data-item-id="<?php echo esc_attr($item_id); ?>" style="font-size: 11px; padding: 2px 8px; height: auto;">
            <?php esc_html_e('Edit Product', 'woo-edit-order-item-size'); ?>
        </button>
        <?php
    }

    /**
     * Modal contents.
     */
    public function ajax_get_edit_form() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-edit-order-item-size')));
        }

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $item    = $item_id ? $this->get_item($item_id) : false;
        $product = $item ? $item->get_product() : false;
        if (!$item || !$product) {
            wp_send_json_error(array('message' => __('Order item or product not found', 'woo-edit-order-item-size')));
        }

        $parent_id      = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $parent_product = wc_get_product($parent_id);
        $is_variable    = $parent_product && $parent_product->is_type('variable');
        $defs           = $is_variable ? $this->get_attribute_defs($parent_product) : array();
        $current        = $is_variable ? $this->get_item_attribute_values($item, $defs) : array();

        ob_start();
        ?>
        <?php if ($is_variable) : ?>
            <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #ddd;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php esc_html_e('Product:', 'woo-edit-order-item-size'); ?></label>
                <select class="product-selector" data-item-id="<?php echo esc_attr($item_id); ?>" data-current-product-id="<?php echo esc_attr($parent_id); ?>" style="min-width: 300px; padding: 8px;">
                    <option value="<?php echo esc_attr($parent_id); ?>" selected><?php echo esc_html($parent_product->get_name()); ?> (ID: <?php echo (int) $parent_id; ?>)</option>
                </select>
                <p class="description" style="margin-top: 5px;"><?php esc_html_e('Start typing to search for a different product', 'woo-edit-order-item-size'); ?></p>
            </div>

            <div class="variation-attributes-container">
                <?php echo $this->render_attribute_fields($defs, $current); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </div>
        <?php else : ?>
            <p><strong><?php echo esc_html($item->get_name()); ?></strong></p>
        <?php endif; ?>

        <?php $this->render_wgnn_fields($item, $product); ?>
        <?php $this->render_epo_fields($item); ?>

        <div style="margin-top: 20px;">
            <button type="button" class="button button-primary save-variation-changes" data-item-id="<?php echo esc_attr($item_id); ?>"><?php esc_html_e('Save Changes', 'woo-edit-order-item-size'); ?></button>
            <button type="button" class="button cancel-variation-edit" data-item-id="<?php echo esc_attr($item_id); ?>" style="margin-left: 10px;"><?php esc_html_e('Cancel', 'woo-edit-order-item-size'); ?></button>
            <span class="spinner" style="float: none; margin: 0 10px;"></span>
            <span class="save-message" style="color: #46b450; display: none;"></span>
        </div>
        <?php
        wp_send_json_success(array('html' => ob_get_clean(), 'item_id' => $item_id));
    }

    /**
     * Attribute dropdowns HTML. A stored value that is not one of the product's options
     * is kept as an extra choice so nothing is silently lost.
     */
    private function render_attribute_fields(array $defs, array $current) {
        ob_start();
        echo '<h4 style="margin-top: 0;">' . esc_html__('Variation Attributes', 'woo-edit-order-item-size') . '</h4>';
        if (empty($defs)) {
            echo '<p>' . esc_html__('This product has no variation attributes.', 'woo-edit-order-item-size') . '</p>';
        }
        foreach ($defs as $meta_key => $def) {
            $value   = isset($current[$meta_key]) ? $current[$meta_key] : '';
            $options = $def['options'];
            if ($value !== '' && !isset($options[$value])) {
                $options = array($value => sprintf(__('%s (not one of the product options)', 'woo-edit-order-item-size'), $this->option_display($value, $def))) + $options;
            }
            ?>
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($def['label']); ?>:</label>
                <select class="variation-attribute" data-attribute="<?php echo esc_attr($def['form_key']); ?>" style="min-width: 200px;">
                    <?php if ($value === '') : ?>
                        <option value="" selected><?php esc_html_e('— Choose —', 'woo-edit-order-item-size'); ?></option>
                    <?php endif; ?>
                    <?php foreach ($options as $slug => $display) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($value, (string) $slug); ?>><?php echo esc_html($display); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
        }
        return ob_get_clean();
    }

    private function render_wgnn_fields(WC_Order_Item_Product $item, WC_Product $product) {
        $wgnn = $this->wgnn();
        if (!$wgnn) {
            return;
        }
        $fields = $wgnn->fields();
        $values = $wgnn->read_item_values($item);
        if (empty($fields) || (!$wgnn->is_enabled_for($product) && empty($values))) {
            return;
        }
        ?>
        <div class="wgnn-fields-container" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4 style="margin-top: 0;"><?php esc_html_e('Name & Number', 'woo-edit-order-item-size'); ?></h4>
            <?php foreach ($fields as $key => $def) : ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($def['label']); ?>:</label>
                    <input type="text" class="wgnn-field" data-key="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr(isset($values[$key]) ? $values[$key] : ''); ?>" maxlength="<?php echo esc_attr($def['maxlength']); ?>" style="min-width: 200px;">
                </div>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e('Leave a field blank to remove it from the item.', 'woo-edit-order-item-size'); ?></p>
        </div>
        <?php
    }

    private function render_epo_fields(WC_Order_Item_Product $item) {
        $epo = $item->get_meta('_tmcartepo_data', true);
        if (empty($epo) || !is_array($epo)) {
            return;
        }
        ?>
        <div class="tm-epo-fields-container" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4 style="margin-top: 0;"><?php esc_html_e('Extra Product Options', 'woo-edit-order-item-size'); ?></h4>
            <?php foreach ($epo as $index => $field) :
                if (!is_array($field) || !isset($field['name'])) {
                    continue;
                } ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($field['name']); ?>:</label>
                    <input type="text" class="tm-epo-field" data-field-index="<?php echo esc_attr($index); ?>" data-field-name="<?php echo esc_attr($field['name']); ?>" value="<?php echo esc_attr(isset($field['value']) ? $field['value'] : ''); ?>" style="min-width: 200px;">
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------- Save */

    public function ajax_save_variation() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-edit-order-item-size')));
        }

        $item_id        = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $new_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $posted_attrs   = isset($_POST['attributes']) && is_array($_POST['attributes']) ? wc_clean(wp_unslash($_POST['attributes'])) : array();
        $posted_wgnn    = isset($_POST['wgnn_fields']) && is_array($_POST['wgnn_fields']) ? $_POST['wgnn_fields'] : null; // sanitised by the WGNN plugin
        $posted_epo     = isset($_POST['tm_epo_fields']) && is_array($_POST['tm_epo_fields']) ? $_POST['tm_epo_fields'] : array();

        $item    = $item_id ? $this->get_item($item_id) : false;
        $product = $item ? $item->get_product() : false;
        $order   = $item ? $item->get_order() : false;
        if (!$item || !$product || !$order) {
            wp_send_json_error(array('message' => __('Order item or product not found', 'woo-edit-order-item-size')));
        }

        $note_lines = array();

        /* ---- 1. Work out the target product / variation (validate before touching anything) */

        $old_parent_id     = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $old_parent        = wc_get_product($old_parent_id);
        $old_variation_id  = $product->is_type('variation') ? $product->get_id() : 0;
        $handle_variation  = $old_parent && $old_parent->is_type('variable');

        $new_parent = $old_parent;
        $product_changed = false;
        if ($handle_variation && $new_product_id && $new_product_id !== $old_parent_id) {
            $new_parent = wc_get_product($new_product_id);
            if (!$new_parent || !$new_parent->is_type('variable')) {
                wp_send_json_error(array('message' => __('Invalid variable product', 'woo-edit-order-item-size')));
            }
            $product_changed = true;
        }

        $new_variation = null;
        $selected      = array();
        $old_defs      = $handle_variation ? $this->get_attribute_defs($old_parent) : array();
        $new_defs      = $handle_variation ? $this->get_attribute_defs($new_parent) : array();
        $old_values    = $handle_variation ? $this->get_item_attribute_values($item, $old_defs) : array();

        if ($handle_variation) {
            foreach ($new_defs as $meta_key => $def) {
                $value = isset($posted_attrs[$def['form_key']]) ? (string) $posted_attrs[$def['form_key']] : '';
                if ($value === '') {
                    wp_send_json_error(array('message' => sprintf(__('Please choose a value for %s.', 'woo-edit-order-item-size'), $def['label'])));
                }
                $selected[$meta_key] = $value;
            }

            $variation_id = $this->find_matching_variation($new_parent, $selected);
            if (!$variation_id) {
                wp_send_json_error(array('message' => __('No variation of this product matches the selected options.', 'woo-edit-order-item-size')));
            }
            $new_variation = wc_get_product($variation_id);
            if (!$new_variation) {
                wp_send_json_error(array('message' => __('Variation not found', 'woo-edit-order-item-size')));
            }
        }

        /* ---- 2. Validate Name & Number */

        $wgnn        = $this->wgnn();
        $wgnn_values = null;
        if ($wgnn && is_array($posted_wgnn)) {
            $wgnn_values = $wgnn->collect_values($posted_wgnn, '');
            $errors      = $wgnn->validate_values($wgnn_values);
            if (!empty($errors)) {
                wp_send_json_error(array('message' => implode(' ', $errors)));
            }
        }

        /* ---- 3. Apply variation change (WooCommerce API only, native meta format) */

        if ($handle_variation) {
            foreach ($item->get_meta_data() as $m) {
                if (in_array($m->key, $this->attribute_meta_keys(array($old_defs, $new_defs)), true)
                    || strpos($m->key, 'attribute_') === 0
                    || strpos($m->key, 'pa_') === 0) {
                    $item->delete_meta_data($m->key);
                }
            }

            $item->set_product_id($new_parent->get_id());
            $item->set_variation_id($new_variation->get_id());
            $item->set_name($new_variation->get_name());

            $new_display = array();
            foreach ($new_defs as $meta_key => $def) {
                $item->add_meta_data($meta_key, $selected[$meta_key], true);
                $new_display[$meta_key] = $this->option_display($selected[$meta_key], $def);
            }

            $user = wp_get_current_user();
            $who  = $user->display_name ? $user->display_name : $user->user_login;
            if ($product_changed) {
                $note_lines[] = sprintf(__('Product changed from "%1$s" to "%2$s" (by %3$s)', 'woo-edit-order-item-size'), $old_parent->get_name(), $new_parent->get_name(), $who);
            } else {
                $note_lines[] = sprintf(__('Product variation changed for item: %1$s (by %2$s)', 'woo-edit-order-item-size'), $new_parent->get_name(), $who);
            }
            foreach ($new_defs as $meta_key => $def) {
                $before = isset($old_defs[$meta_key]) && isset($old_values[$meta_key]) ? $this->option_display($old_values[$meta_key], $old_defs[$meta_key]) : '';
                $after  = $new_display[$meta_key];
                if ($before !== $after) {
                    $note_lines[] = sprintf('%s: %s → %s', $def['label'], $before !== '' ? $before : __('(none)', 'woo-edit-order-item-size'), $after);
                }
            }
            if ($old_variation_id !== $new_variation->get_id()) {
                $note_lines[] = sprintf(__('Variation #%1$d → #%2$d', 'woo-edit-order-item-size'), $old_variation_id, $new_variation->get_id());
            }
        }

        /* ---- 4. Apply Name & Number */

        if ($wgnn && is_array($wgnn_values)) {
            $old_nn = $wgnn->read_item_values($item);
            $wgnn->apply_to_item($item, $wgnn_values);
            foreach ($wgnn->fields() as $key => $def) {
                $before = isset($old_nn[$key]) ? $old_nn[$key] : '';
                $after  = isset($wgnn_values[$key]) ? $wgnn_values[$key] : '';
                if ($before !== $after) {
                    $note_lines[] = sprintf('%s: %s → %s', $def['label'], $before !== '' ? $before : __('(none)', 'woo-edit-order-item-size'), $after !== '' ? $after : __('(none)', 'woo-edit-order-item-size'));
                }
            }
        }

        /* ---- 5. Apply Extra Product Options (TM EPO) values */

        $epo_data = $item->get_meta('_tmcartepo_data', true);
        if (!empty($posted_epo) && is_array($epo_data)) {
            $changed = false;
            foreach ($posted_epo as $update) {
                if (!is_array($update) || !isset($update['index'])) {
                    continue;
                }
                $index = intval($update['index']);
                $value = isset($update['value']) ? sanitize_text_field(wp_unslash($update['value'])) : '';
                if (!isset($epo_data[$index]) || !is_array($epo_data[$index])) {
                    continue;
                }
                $before = isset($epo_data[$index]['value']) ? (string) $epo_data[$index]['value'] : '';
                if ($before !== $value) {
                    $epo_data[$index]['value'] = $value;
                    $changed = true;
                    $note_lines[] = sprintf('%s: %s → %s', isset($epo_data[$index]['name']) ? $epo_data[$index]['name'] : $index, $before !== '' ? $before : __('(empty)', 'woo-edit-order-item-size'), $value !== '' ? $value : __('(empty)', 'woo-edit-order-item-size'));
                }
            }
            if ($changed) {
                $item->update_meta_data('_tmcartepo_data', $epo_data);
            }
        }

        /* ---- 6. Save */

        $item->save();

        if (!empty($note_lines)) {
            $order->add_order_note(implode("\n", $note_lines));
        }
        $order->calculate_totals();
        $order->save();

        wp_send_json_success(array(
            'message'   => __('Item updated successfully', 'woo-edit-order-item-size'),
            'item_name' => $item->get_name(),
        ));
    }

    /* --------------------------------------------------------- Product switch */

    public function ajax_search_products() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-edit-order-item-size')));
        }

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        if ($term === '') {
            wp_send_json(array());
        }

        $products = wc_get_products(array(
            'type'   => 'variable',
            'status' => 'publish',
            's'      => $term,
            'limit'  => 20,
        ));

        $results = array();
        foreach ($products as $p) {
            $results[] = array('id' => $p->get_id(), 'text' => $p->get_name() . ' (ID: ' . $p->get_id() . ')');
        }
        wp_send_json($results);
    }

    /**
     * Attribute dropdowns for a newly selected product, pre-filled from the order item
     * where the attribute is shared (e.g. both products use the same size attribute).
     */
    public function ajax_load_product_variations() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-edit-order-item-size')));
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $item_id    = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $product    = $product_id ? wc_get_product($product_id) : false;
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(array('message' => __('Invalid variable product', 'woo-edit-order-item-size')));
        }

        $defs    = $this->get_attribute_defs($product);
        $current = array();
        $item    = $item_id ? $this->get_item($item_id) : false;
        if ($item) {
            $current = $this->get_item_attribute_values($item, $defs);
            foreach ($current as $k => $v) {
                if ($v !== '' && !isset($defs[$k]['options'][$v])) {
                    $current[$k] = ''; // value from the old product that this product does not offer
                }
            }
        }

        wp_send_json_success(array(
            'html'         => $this->render_attribute_fields($defs, $current),
            'product_name' => $product->get_name(),
        ));
    }

    /* ------------------------------------------------------------- Assets */

    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('post.php', 'woocommerce_page_wc-orders'), true)) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $order_screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
        if (!$screen || !in_array($screen->id, array('shop_order', $order_screen), true)) {
            return;
        }

        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);

        wp_enqueue_script('weois-admin', plugins_url('assets/admin.js', WEOIS_FILE), array('jquery', 'select2'), WEOIS_VERSION, true);
        wp_localize_script('weois-admin', 'weoisData', array(
            'nonce'  => wp_create_nonce(self::NONCE),
            'i18n'   => array(
                'error'      => __('An error occurred. Please try again.', 'woo-edit-order-item-size'),
                'loading'    => __('Loading…', 'woo-edit-order-item-size'),
                'reloading'  => __('Reloading…', 'woo-edit-order-item-size'),
                'search'     => __('Search for a product…', 'woo-edit-order-item-size'),
                'title'      => __('Edit Product', 'woo-edit-order-item-size'),
            ),
        ));

        wp_add_inline_style('wp-admin', '
            .edit-item-variation{background:#2271b1;color:#fff;border:none;cursor:pointer}
            .edit-item-variation:hover{background:#135e96}
            .variation-attribute,.wgnn-field,.tm-epo-field{padding:5px 10px;font-size:14px}
        ');
    }

    /* ------------------------------------------------------- Meta display */

    /**
     * Hide technical 'attribute_*' keys and collapse duplicate labels (legacy orders may
     * carry both a native key and a label key for the same attribute).
     */
    public function remove_duplicate_meta_display($formatted_meta, $item) {
        $seen    = array();
        $cleaned = array();

        foreach ($formatted_meta as $meta_id => $meta) {
            $key = $meta->key;
            if (strpos($key, 'attribute_') === 0) {
                continue;
            }
            $label = isset($meta->display_key) ? $meta->display_key : $key;
            if (isset($seen[$label])) {
                continue;
            }
            $seen[$label]      = true;
            $cleaned[$meta_id] = $meta;
        }

        return $cleaned;
    }
}

add_action('plugins_loaded', function () {
    new WooEditOrderItemSize();
}, 20); // after the Name & Number plugin registers itself
