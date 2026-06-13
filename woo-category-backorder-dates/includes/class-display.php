<?php
/**
 * Public shortcodes:
 *
 *   [backorder_date context="card|product" align="" color="" class="" tag="span" product_id="" template=""]
 *     Pre-order / back-order delivery message while the ordering window is open.
 *     Outputs nothing if the product has no dated category or is already closed.
 *
 *   [stock_message show_instock="no" align="" color="" class="" tag="span" product_id="" template=""]
 *     Out-of-stock / ordering-closed message for any out-of-stock product.
 *
 * Styling attributes (so it works even inside Elementor's style-less Shortcode widget):
 *   align = left|center|right   color = any CSS colour (e.g. #b35a00 or rebeccapurple)
 *   class = extra CSS class(es) tag   = span|div|p (wrapper element)
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Display {

    public function __construct() {
        add_shortcode('backorder_date', array($this, 'shortcode_backorder_date'));
        add_shortcode('stock_message', array($this, 'shortcode_stock_message'));
    }

    /**
     * Resolve the product to operate on: explicit id, the loop's global $product,
     * or the queried product on a single product page.
     *
     * @return WC_Product|null
     */
    protected function get_product($id = 0) {
        if ($id) {
            $p = wc_get_product((int) $id);
            return $p ? $p : null;
        }

        global $product;
        if ($product instanceof WC_Product) {
            return $product;
        }

        if (function_exists('is_product') && is_product()) {
            $p = wc_get_product(get_queried_object_id());
            if ($p) {
                return $p;
            }
        }

        $gid = get_the_ID();
        if ($gid) {
            $p = wc_get_product($gid);
            if ($p) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Wrap rendered text in a styleable element built from the shortcode attributes.
     */
    protected function wrap($content, $base_class, $atts) {
        if (trim($content) === '') {
            return '';
        }

        $tag = isset($atts['tag']) ? strtolower($atts['tag']) : 'span';
        if (!in_array($tag, array('span', 'div', 'p'), true)) {
            $tag = 'span';
        }

        $classes = $base_class;
        if (!empty($atts['class'])) {
            $classes .= ' ' . sanitize_html_class_list($atts['class']);
        }

        $styles = array();

        if (!empty($atts['align'])) {
            $align = strtolower($atts['align']);
            if (in_array($align, array('left', 'center', 'right'), true)) {
                $styles[] = 'text-align:' . $align;
                // An inline element ignores text-align on itself, so promote to block.
                if ($tag === 'span') {
                    $styles[] = 'display:block';
                }
            }
        }

        if (!empty($atts['color'])) {
            $color = $this->sanitize_color($atts['color']);
            if ($color !== '') {
                $styles[] = 'color:' . $color;
            }
        }

        $style_attr = $styles ? ' style="' . esc_attr(implode(';', $styles)) . '"' : '';

        return '<' . $tag . ' class="' . esc_attr($classes) . '"' . $style_attr . '>'
            . wp_kses_post($content)
            . '</' . $tag . '>';
    }

    /**
     * Accept hex colours and simple named/rgb colours, stripping anything unsafe.
     */
    protected function sanitize_color($value) {
        $value = trim($value);
        $hex   = sanitize_hex_color($value);
        if ($hex) {
            return $hex;
        }
        // Allow letters, digits and rgb()/hsl() punctuation only.
        if (preg_match('/^[a-zA-Z0-9#(),.%\s]+$/', $value)) {
            return $value;
        }
        return '';
    }

    public function shortcode_backorder_date($atts) {
        $atts = shortcode_atts(array(
            'context'    => 'card',
            'product_id' => 0,
            'template'   => '',
            'align'      => '',
            'color'      => '',
            'class'      => '',
            'tag'        => 'span',
        ), $atts, 'backorder_date');

        $product = $this->get_product($atts['product_id']);
        if (!$product) {
            return '';
        }

        // Closed products are no longer "back-order" — that's the stock_message shortcode's job.
        if (WCBD_Resolver::is_closed($product)) {
            return '';
        }

        $cat = WCBD_Resolver::get_governing_batch($product);
        if (!$cat) {
            return '';
        }

        $context  = ($atts['context'] === 'product') ? 'product' : 'card';
        $template = $atts['template'] !== '' ? $atts['template'] : WCBD_Resolver::message_for($context, $cat, $product);
        $message  = WCBD_Resolver::format($template, $cat, $product);

        return $this->wrap($message, 'wcbd-message wcbd-message--' . $context, $atts);
    }

    public function shortcode_stock_message($atts) {
        $atts = shortcode_atts(array(
            'show_instock' => 'no',
            'product_id'   => 0,
            'template'     => '',
            'align'        => '',
            'color'        => '',
            'class'        => '',
            'tag'          => 'span',
        ), $atts, 'stock_message');

        $product = $this->get_product($atts['product_id']);
        if (!$product) {
            return '';
        }

        // is_in_stock() reflects our dynamic cutoff filters as well as real inventory.
        if (!$product->is_in_stock()) {
            $closed_cat = WCBD_Resolver::get_closed_category($product);

            if ($atts['template'] !== '') {
                $template = $atts['template'];
            } elseif ($closed_cat) {
                $template = WCBD_Resolver::message_for('closed', $closed_cat, $product);
            } else {
                $template = WCBD_Settings::get('msg_out_of_stock');
            }

            $message = WCBD_Resolver::format($template, $closed_cat, $product);
            return $this->wrap($message, 'wcbd-stock-message wcbd-stock-message--out', $atts);
        }

        if (strtolower($atts['show_instock']) === 'yes') {
            $template = $atts['template'] !== '' ? $atts['template'] : WCBD_Settings::get('msg_in_stock');
            $message  = WCBD_Resolver::format($template, null, $product);
            return $this->wrap($message, 'wcbd-stock-message wcbd-stock-message--in', $atts);
        }

        return '';
    }
}

/**
 * Sanitize a space-separated list of CSS classes (WordPress only ships the single-class version).
 */
if (!function_exists('sanitize_html_class_list')) {
    function sanitize_html_class_list($classes) {
        $parts = preg_split('/\s+/', trim((string) $classes));
        $parts = array_map('sanitize_html_class', $parts);
        return trim(implode(' ', array_filter($parts)));
    }
}
