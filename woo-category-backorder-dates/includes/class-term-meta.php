<?php
/**
 * Adds the date / label / cutoff and per-category message override fields to the
 * product category (product_cat) add & edit screens, and saves them.
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Term_Meta {

    /** Date fields stored as Y-m-d. */
    protected $date_fields = array(
        '_wcbd_date'   => null,
        '_wcbd_cutoff' => null,
    );

    /** Free-text / message fields. */
    protected $text_fields = array(
        '_wcbd_label'        => null,
        '_wcbd_msg_card'     => null,
        '_wcbd_msg_product'  => null,
        '_wcbd_msg_cart'     => null,
        '_wcbd_msg_checkout' => null,
        '_wcbd_msg_closed'   => null,
    );

    public function __construct() {
        add_action('product_cat_add_form_fields', array($this, 'render_add_fields'));
        add_action('product_cat_edit_form_fields', array($this, 'render_edit_fields'), 10, 2);
        add_action('created_product_cat', array($this, 'save'));
        add_action('edited_product_cat', array($this, 'save'));
    }

    /**
     * Add-category screen (stacked div layout).
     */
    public function render_add_fields() {
        wp_nonce_field('wcbd_save_term', 'wcbd_term_nonce');
        ?>
        <h2><?php esc_html_e('Back-order / pre-order', 'wc-backorder-dates'); ?></h2>

        <div class="form-field">
            <label for="_wcbd_label"><?php esc_html_e('Delivery label (shown to customers)', 'wc-backorder-dates'); ?></label>
            <input type="text" name="_wcbd_label" id="_wcbd_label" value="" />
            <p><?php esc_html_e('Free text, e.g. "mid-July 2026". Leave blank if this category has no back-order date.', 'wc-backorder-dates'); ?></p>
        </div>

        <div class="form-field">
            <label for="_wcbd_date"><?php esc_html_e('Delivery date (internal)', 'wc-backorder-dates'); ?></label>
            <input type="date" name="_wcbd_date" id="_wcbd_date" value="" />
            <p><?php esc_html_e('Used only to pick the earliest batch when a product is in several dated categories.', 'wc-backorder-dates'); ?></p>
        </div>

        <div class="form-field">
            <label for="_wcbd_cutoff"><?php esc_html_e('Order-by / stock cutoff date', 'wc-backorder-dates'); ?></label>
            <input type="date" name="_wcbd_cutoff" id="_wcbd_cutoff" value="" />
            <p><?php esc_html_e('After this date the products go out of stock automatically. Leave blank for no cutoff.', 'wc-backorder-dates'); ?></p>
        </div>

        <?php $this->render_overrides(array()); ?>
        <?php
    }

    /**
     * Edit-category screen (table-row layout).
     */
    public function render_edit_fields($term) {
        $v = array();
        foreach (array_merge($this->date_fields, $this->text_fields) as $key => $_) {
            $v[$key] = get_term_meta($term->term_id, $key, true);
        }
        wp_nonce_field('wcbd_save_term', 'wcbd_term_nonce');
        ?>
        <tr class="form-field">
            <th colspan="2"><h2><?php esc_html_e('Back-order / pre-order', 'wc-backorder-dates'); ?></h2></th>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="_wcbd_label"><?php esc_html_e('Delivery label (shown to customers)', 'wc-backorder-dates'); ?></label></th>
            <td>
                <input type="text" name="_wcbd_label" id="_wcbd_label" value="<?php echo esc_attr($v['_wcbd_label']); ?>" />
                <p class="description"><?php esc_html_e('Free text, e.g. "mid-July 2026". Leave blank if this category has no back-order date.', 'wc-backorder-dates'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="_wcbd_date"><?php esc_html_e('Delivery date (internal)', 'wc-backorder-dates'); ?></label></th>
            <td>
                <input type="date" name="_wcbd_date" id="_wcbd_date" value="<?php echo esc_attr($v['_wcbd_date']); ?>" />
                <p class="description"><?php esc_html_e('Used only to pick the earliest batch when a product is in several dated categories.', 'wc-backorder-dates'); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="_wcbd_cutoff"><?php esc_html_e('Order-by / stock cutoff date', 'wc-backorder-dates'); ?></label></th>
            <td>
                <input type="date" name="_wcbd_cutoff" id="_wcbd_cutoff" value="<?php echo esc_attr($v['_wcbd_cutoff']); ?>" />
                <p class="description"><?php esc_html_e('After this date the products go out of stock automatically. Leave blank for no cutoff.', 'wc-backorder-dates'); ?></p>
            </td>
        </tr>
        <?php $this->render_overrides($v, true); ?>
        <?php
    }

    /**
     * Optional per-category message overrides (blank = use the global default).
     */
    protected function render_overrides($v, $as_rows = false) {
        $overrides = array(
            '_wcbd_msg_card'     => __('Card message override', 'wc-backorder-dates'),
            '_wcbd_msg_product'  => __('Product page message override', 'wc-backorder-dates'),
            '_wcbd_msg_cart'     => __('Cart / order note override', 'wc-backorder-dates'),
            '_wcbd_msg_checkout' => __('Checkout line override', 'wc-backorder-dates'),
            '_wcbd_msg_closed'   => __('Ordering closed message override', 'wc-backorder-dates'),
        );

        foreach ($overrides as $key => $label) {
            $val  = isset($v[$key]) ? $v[$key] : '';
            $hint = __('Leave blank to use the global default. Placeholders: {date} {category} {cutoff} {product}', 'wc-backorder-dates');

            if ($as_rows) {
                ?>
                <tr class="form-field">
                    <th scope="row"><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                    <td>
                        <textarea name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" rows="2" cols="50"><?php echo esc_textarea($val); ?></textarea>
                        <p class="description"><?php echo esc_html($hint); ?></p>
                    </td>
                </tr>
                <?php
            } else {
                ?>
                <div class="form-field">
                    <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                    <textarea name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" rows="2" cols="50"></textarea>
                    <p><?php echo esc_html($hint); ?></p>
                </div>
                <?php
            }
        }
    }

    /**
     * Persist the fields.
     */
    public function save($term_id) {
        if (!isset($_POST['wcbd_term_nonce']) || !wp_verify_nonce(sanitize_key($_POST['wcbd_term_nonce']), 'wcbd_save_term')) {
            return;
        }
        if (!current_user_can('manage_product_terms')) {
            return;
        }

        foreach (array_keys($this->date_fields) as $key) {
            $raw = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
            // Keep only valid Y-m-d strings; anything else clears the field.
            if ($raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $raw = '';
            }
            update_term_meta($term_id, $key, $raw);
        }

        // Label is plain text; message overrides may contain limited HTML.
        $label = isset($_POST['_wcbd_label']) ? sanitize_text_field(wp_unslash($_POST['_wcbd_label'])) : '';
        update_term_meta($term_id, '_wcbd_label', $label);

        foreach (array('_wcbd_msg_card', '_wcbd_msg_product', '_wcbd_msg_cart', '_wcbd_msg_checkout', '_wcbd_msg_closed') as $key) {
            $val = isset($_POST[$key]) ? wp_kses_post(wp_unslash($_POST[$key])) : '';
            update_term_meta($term_id, $key, trim($val));
        }
    }
}
