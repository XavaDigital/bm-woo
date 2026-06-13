<?php
/**
 * Hourly job that writes the REAL stock status to the database so external tools and
 * exports stay consistent with the dynamic layer.
 *
 * - Closes (sets outofstock) products whose cutoff has passed, tagging them with
 *   _wcbd_closed so we can identify our own changes.
 * - Re-opens (sets instock) ONLY products we previously closed once their cutoff
 *   no longer applies. Genuinely out-of-stock inventory is never touched.
 * - Skips stock-managed products entirely; for those the dynamic layer enforces the
 *   cutoff and the real quantity is left alone.
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Cron {

    const HOOK   = 'wcbd_sync_stock';
    const MARKER = '_wcbd_closed';

    public function __construct() {
        add_action(self::HOOK, array($this, 'sync'));
    }

    /**
     * Term ids of every category that has a cutoff date set.
     *
     * @return int[]
     */
    protected function categories_with_cutoff() {
        $terms = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'fields'     => 'ids',
            'meta_query' => array(
                array(
                    'key'     => '_wcbd_cutoff',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        ));

        return is_wp_error($terms) ? array() : array_map('intval', $terms);
    }

    public function sync() {
        $product_ids = array();

        // Products in categories that have a cutoff.
        $term_ids = $this->categories_with_cutoff();
        if (!empty($term_ids)) {
            $product_ids = get_posts(array(
                'post_type'      => array('product'),
                'post_status'    => 'publish',
                'numberposts'    => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'tax_query'      => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $term_ids,
                    ),
                ),
            ));
        }

        // Products with their own product-level cutoff.
        $own = get_posts(array(
            'post_type'     => array('product'),
            'post_status'   => 'publish',
            'numberposts'   => -1,
            'fields'        => 'ids',
            'no_found_rows' => true,
            'meta_query'    => array(
                array(
                    'key'     => '_wcbd_cutoff',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        ));

        $product_ids = array_unique(array_merge($product_ids, $own));
        if (empty($product_ids)) {
            return;
        }

        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }

            // Leave stock-managed products to the dynamic layer; don't touch their quantities.
            if ($product->managing_stock()) {
                continue;
            }

            $closed = WCBD_Resolver::is_closed($product);
            $marker = get_post_meta($id, self::MARKER, true);

            if ($closed) {
                if ($product->get_stock_status() !== 'outofstock') {
                    $product->set_stock_status('outofstock');
                    $product->save();
                }
                update_post_meta($id, self::MARKER, '1');
            } elseif ($marker === '1') {
                // We closed it before and the cutoff no longer applies -> reopen.
                $product->set_stock_status('instock');
                $product->save();
                delete_post_meta($id, self::MARKER);
            }
        }
    }
}
