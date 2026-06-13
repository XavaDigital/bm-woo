<?php
/**
 * Back-order confirmation on the (classic / Elementor) checkout.
 *
 * Designed to work WITHOUT JavaScript and WITHOUT the external stylesheet, because the
 * target site delays JS and strips "unused" CSS:
 *
 *   - The real required checkbox (#wcbd_confirm) is rendered inside the form, high up
 *     (woocommerce_checkout_before_customer_details) so AJAX order-review refreshes don't
 *     reset it.
 *   - A confirmation panel is rendered at wp_footer (body level, so position:fixed covers
 *     the whole viewport regardless of Elementor transforms) with its CSS printed INLINE.
 *   - The panel's "I understand" button is a <label for="wcbd_confirm"> — clicking it ticks
 *     the in-form checkbox (labels work across the DOM by id). Pure CSS then hides the panel
 *     via `body:has(#wcbd_confirm:checked)`. No JS required.
 *   - checkout.js is a progressive enhancement only (fallback dismiss for the rare browser
 *     without :has()).
 *   - Server-side validation blocks the order if the checkbox isn't set.
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Checkout {

    /** @var array Back-order items captured during form render, reused at wp_footer. */
    protected $items = array();

    public function __construct() {
        add_action('woocommerce_before_checkout_form', array($this, 'capture_items'), 5);
        add_action('woocommerce_review_order_before_submit', array($this, 'render_checkbox'));
        add_action('wp_footer', array($this, 'render_panel'), 50);
        add_action('woocommerce_checkout_process', array($this, 'validate'));
    }

    /**
     * Captured once the checkout form starts rendering (reliable on Elementor checkouts).
     */
    public function capture_items() {
        $this->items = WCBD_Resolver::get_cart_backorders();
        if (!empty($this->items)) {
            wp_enqueue_script('wcbd-checkout');
        }
    }

    /**
     * One "{product} — {date}" line per back-order item.
     */
    protected function render_item_lines($items) {
        echo '<ul class="wcbd-backorder-list">';
        foreach ($items as $it) {
            $line = WCBD_Resolver::format(
                WCBD_Resolver::message_for('checkout', $it['cat'], $it['product']),
                $it['cat'],
                $it['product']
            );
            echo '<li>' . wp_kses_post($line) . '</li>';
        }
        echo '</ul>';
    }

    /**
     * The real required checkbox, rendered just above the Place Order button.
     *
     * This region re-renders on checkout AJAX (shipping/coupon changes), so we preserve the
     * checked state server-side by reading the posted form data — no JS needed. Items are
     * recomputed here (capture_items doesn't run during the AJAX order-review refresh).
     */
    public function render_checkbox() {
        if (empty(WCBD_Resolver::get_cart_backorders())) {
            return;
        }
        $label   = WCBD_Settings::get('checkout_checkbox_label');
        $checked = $this->is_confirm_posted() ? ' checked="checked"' : '';
        ?>
        <div class="wcbd-checkout-notice" id="wcbd-checkout-notice">
            <p class="form-row wcbd-confirm-row validate-required">
                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="wcbd_confirm" id="wcbd_confirm" value="1"<?php echo $checked; ?> />
                    <span><?php echo wp_kses_post($label); ?></span>
                </label>
            </p>
        </div>
        <?php
    }

    /**
     * Was the confirmation checkbox ticked in the current request? Handles both the normal
     * POST and the serialized post_data sent during the update_order_review AJAX refresh.
     */
    protected function is_confirm_posted() {
        if (isset($_POST['post_data'])) {
            parse_str(wp_unslash($_POST['post_data']), $posted);
            return !empty($posted['wcbd_confirm']);
        }
        return !empty($_POST['wcbd_confirm']);
    }

    /**
     * Full-viewport confirmation panel rendered at body level, with inline CSS.
     */
    public function render_panel() {
        if (empty($this->items)) {
            return;
        }

        $shop_url = WCBD_Settings::get('overlay_cancel_url');
        if ($shop_url === '') {
            $shop_url = home_url('/');
        }

        $heading = WCBD_Settings::get('overlay_heading');
        $intro   = WCBD_Settings::get('checkout_intro');
        $confirm = WCBD_Settings::get('overlay_confirm_label');
        $cancel  = WCBD_Settings::get('overlay_cancel_label');

        $this->print_inline_css();
        ?>
        <div class="wcbd-overlay" id="wcbd-overlay" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr(wp_strip_all_tags($heading)); ?>">
            <div class="wcbd-overlay__dialog">
                <?php if ($heading !== '') : ?>
                    <h2 class="wcbd-overlay__title"><?php echo wp_kses_post($heading); ?></h2>
                <?php endif; ?>
                <?php if ($intro !== '') : ?>
                    <p class="wcbd-overlay__intro"><?php echo wp_kses_post($intro); ?></p>
                <?php endif; ?>
                <?php $this->render_item_lines($this->items); ?>
                <div class="wcbd-overlay__actions">
                    <label for="wcbd_confirm" class="wcbd-overlay__confirm" tabindex="0"><?php echo esc_html($confirm); ?></label>
                    <a class="wcbd-overlay__cancel" href="<?php echo esc_url($shop_url); ?>"><?php echo esc_html($cancel); ?></a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Inline CSS so the panel works even when the external stylesheet is stripped.
     * Pure-CSS dismiss via :has(); JS is only a fallback for browsers without :has().
     */
    protected function print_inline_css() {
        ?>
        <style id="wcbd-overlay-css">
        .wcbd-overlay{position:fixed;inset:0;z-index:999999;display:flex;align-items:center;justify-content:center;padding:1em;background:rgba(0,0,0,.7);}
        .wcbd-overlay__dialog{background:#151515;color:#f1f1f1;width:100%;max-width:520px;max-height:90vh;overflow:auto;border:1px solid #333;border-radius:6px;padding:1.6em;box-shadow:0 10px 40px rgba(0,0,0,.6);}
        .wcbd-overlay__title{margin:0 0 .5em;font-size:1.4em;color:#fff;}
        .wcbd-overlay__intro{margin:0 0 .75em;font-weight:600;color:#f1f1f1;}
        .wcbd-overlay .wcbd-backorder-list{margin:0 0 1.2em;padding-left:1.2em;color:#f1f1f1;}
        .wcbd-overlay .wcbd-backorder-list li{margin:.2em 0;}
        .wcbd-overlay__actions{display:flex;flex-wrap:wrap;gap:.6em;margin-top:.5em;}
        /* Self-contained button styling; specificity + !important to beat theme .button rules. */
        .wcbd-overlay .wcbd-overlay__confirm,.wcbd-overlay .wcbd-overlay__cancel{display:inline-block;padding:.85em 1.5em;border-radius:4px;font-weight:600;font-size:1em;line-height:1.2;text-align:center;text-decoration:none;cursor:pointer;border:2px solid;transition:opacity .15s ease,background-color .15s ease,color .15s ease,border-color .15s ease;}
        .wcbd-overlay .wcbd-overlay__confirm{background:#fff !important;color:#151515 !important;border-color:#fff !important;}
        .wcbd-overlay .wcbd-overlay__confirm:hover,.wcbd-overlay .wcbd-overlay__confirm:focus{opacity:.85;background:#fff !important;color:#151515 !important;}
        .wcbd-overlay .wcbd-overlay__cancel{background:transparent !important;color:#e10600 !important;border-color:#e10600 !important;}
        .wcbd-overlay .wcbd-overlay__cancel:hover,.wcbd-overlay .wcbd-overlay__cancel:focus{background:#e10600 !important;color:#fff !important;border-color:#e10600 !important;}
        /* Pure-CSS dismiss: once the in-form checkbox is ticked, hide the panel. */
        body:has(#wcbd_confirm:checked) .wcbd-overlay{display:none;}
        /* Keep the in-form checkbox available but unobtrusive (the panel drives it). */
        .wcbd-confirm-row{margin:.5em 0;}
        </style>
        <?php
    }

    public function validate() {
        if (empty(WCBD_Resolver::get_cart_backorders())) {
            return;
        }
        if (empty($_POST['wcbd_confirm'])) {
            wc_add_notice(WCBD_Settings::get('checkout_error'), 'error');
        }
    }
}
