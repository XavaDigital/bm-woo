<?php
/**
 * Resolves a product to its governing pre-order/back-order batch and closed state.
 *
 * A product's "dated sources" are its own product-level settings PLUS its categories.
 * Two independent jobs reduce over that source list:
 *   1. Label / back-order message  -> the source with the EARLIEST delivery date (product wins ties).
 *   2. Closed (out of stock)?       -> true if ANY source's cutoff has passed;
 *                                      the closed message comes from the EARLIEST passed-cutoff source.
 *
 * Message cascade: product-level override -> governing category override -> global default.
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Resolver {

    /** @var array Per-request cache of dated categories keyed by the category-owning product id. */
    protected static $cache = array();

    /**
     * Variations don't carry their own categories; use the parent for lookups.
     */
    protected static function category_owner_id($product) {
        if ($product instanceof WC_Product && $product->is_type('variation')) {
            return $product->get_parent_id();
        }
        return $product->get_id();
    }

    /**
     * Return the today date string in the site timezone (Y-m-d) for safe string comparison.
     */
    public static function today() {
        return current_time('Y-m-d');
    }

    /**
     * The product's own dated entry (product-level overrides), if any.
     * Read from the category-owner id, so variations inherit the parent product's settings.
     *
     * @return array|null  type, id, name, label, date, cutoff
     */
    public static function get_product_source($product) {
        if (!($product instanceof WC_Product)) {
            return null;
        }
        $id     = self::category_owner_id($product);
        $label  = (string) get_post_meta($id, '_wcbd_label', true);
        $date   = (string) get_post_meta($id, '_wcbd_date', true);
        $cutoff = (string) get_post_meta($id, '_wcbd_cutoff', true);

        if ($label === '' && $date === '' && $cutoff === '') {
            return null;
        }

        return array(
            'type'   => 'product',
            'id'     => (int) $id,
            'name'   => '',
            'label'  => $label,
            'date'   => $date,
            'cutoff' => $cutoff,
        );
    }

    /**
     * "Dated" categories directly assigned to the product (label, delivery date or cutoff set).
     *
     * @return array[] Each: type, id, name, label, date, cutoff
     */
    public static function get_dated_categories($product) {
        if (!($product instanceof WC_Product)) {
            return array();
        }

        $result = array();
        $terms  = get_the_terms(self::category_owner_id($product), 'product_cat');

        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $label  = (string) get_term_meta($term->term_id, '_wcbd_label', true);
                $date   = (string) get_term_meta($term->term_id, '_wcbd_date', true);
                $cutoff = (string) get_term_meta($term->term_id, '_wcbd_cutoff', true);

                if ($label === '' && $date === '' && $cutoff === '') {
                    continue;
                }

                $result[] = array(
                    'type'   => 'category',
                    'id'     => (int) $term->term_id,
                    'name'   => $term->name,
                    'label'  => $label,
                    'date'   => $date,
                    'cutoff' => $cutoff,
                );
            }
        }

        return $result;
    }

    /**
     * All dated sources for a product: its own product-level entry plus its categories.
     * The product source is listed first so it wins ties on equal delivery dates.
     *
     * @return array[]
     */
    public static function get_dated_sources($product) {
        if (!($product instanceof WC_Product)) {
            return array();
        }

        $owner = self::category_owner_id($product);
        if (isset(self::$cache[$owner])) {
            return self::$cache[$owner];
        }

        $sources = array();
        $product_source = self::get_product_source($product);
        if ($product_source) {
            $sources[] = $product_source;
        }
        $sources = array_merge($sources, self::get_dated_categories($product));

        self::$cache[$owner] = $sources;
        return $sources;
    }

    /**
     * The source that governs the displayed back-order label: earliest delivery date wins
     * (product source wins ties). Only sources that actually have a label are considered.
     *
     * @return array|null
     */
    public static function get_governing_batch($product) {
        $best = null;
        foreach (self::get_dated_sources($product) as $src) {
            if ($src['label'] === '') {
                continue;
            }
            if ($best === null) {
                $best = $src;
                continue;
            }
            $cd = $src['date'] !== ''  ? $src['date']  : '9999-12-31';
            $bd = $best['date'] !== '' ? $best['date'] : '9999-12-31';
            if ($cd < $bd) {
                $best = $src;
            }
        }
        return $best;
    }

    /**
     * Is ordering closed? True if ANY source's cutoff date has passed.
     */
    public static function is_closed($product) {
        $today = self::today();
        foreach (self::get_dated_sources($product) as $src) {
            if ($src['cutoff'] !== '' && $today > $src['cutoff']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Among the sources whose cutoff has passed, the one that closed first (earliest cutoff).
     *
     * @return array|null
     */
    public static function get_closed_category($product) {
        $today = self::today();
        $best  = null;
        foreach (self::get_dated_sources($product) as $src) {
            if ($src['cutoff'] !== '' && $today > $src['cutoff']) {
                if ($best === null || $src['cutoff'] < $best['cutoff']) {
                    $best = $src;
                }
            }
        }
        return $best;
    }

    /**
     * Resolve a message template for a context. Cascade: product-level override ->
     * governing category override -> global default in settings.
     *
     * @param string          $context card|product|cart|checkout|closed
     * @param array|null      $source  the governing/closed source
     * @param WC_Product|null $product used for the product-level override lookup
     * @return string
     */
    public static function message_for($context, $source, $product = null) {
        // 1. Product-level override.
        if ($product instanceof WC_Product) {
            $pm = (string) get_post_meta(self::category_owner_id($product), '_wcbd_msg_' . $context, true);
            if ($pm !== '') {
                return $pm;
            }
        }

        // 2. Category override (only when a category governs).
        if (is_array($source) && isset($source['type']) && $source['type'] === 'category' && !empty($source['id'])) {
            $override = (string) get_term_meta($source['id'], '_wcbd_msg_' . $context, true);
            if ($override !== '') {
                return $override;
            }
        }

        // 3. Global default.
        return WCBD_Settings::get('msg_' . $context);
    }

    /**
     * Replace placeholders in a message template.
     *
     * @param string          $template
     * @param array|null      $cat
     * @param WC_Product|null $product
     * @return string
     */
    public static function format($template, $cat, $product = null) {
        $label   = is_array($cat) ? $cat['label'] : '';
        $catname = is_array($cat) ? $cat['name'] : '';
        $cutoff  = (is_array($cat) && !empty($cat['cutoff'])) ? self::format_cutoff($cat['cutoff']) : '';
        $pname   = ($product instanceof WC_Product) ? $product->get_name() : '';

        return strtr($template, array(
            '{date}'     => $label,
            '{label}'    => $label,
            '{category}' => $catname,
            '{cutoff}'   => $cutoff,
            '{product}'  => $pname,
        ));
    }

    /**
     * Format a stored Y-m-d cutoff using the site date format.
     */
    public static function format_cutoff($ymd) {
        $ts = strtotime($ymd);
        return $ts ? date_i18n(wc_date_format(), $ts) : $ymd;
    }

    /**
     * Back-order items currently in the cart, recomputed live from each item's product
     * (so it also covers items added before a cart snapshot existed).
     *
     * @return array[] Each: product (WC_Product), cat (array)
     */
    public static function get_cart_backorders() {
        $out = array();
        if (!function_exists('WC') || !WC()->cart) {
            return $out;
        }
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = isset($cart_item['data']) ? $cart_item['data'] : null;
            if (!($product instanceof WC_Product)) {
                continue;
            }
            if (self::is_closed($product)) {
                continue;
            }
            $cat = self::get_governing_batch($product);
            if ($cat) {
                $out[] = array('product' => $product, 'cat' => $cat);
            }
        }
        return $out;
    }
}
