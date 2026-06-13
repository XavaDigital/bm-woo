<?php
/**
 * Product waitlist: lets customers sign up to be notified when an out-of-stock product
 * becomes available again.
 *
 * - Storage: custom table {prefix}wcbd_waitlist.
 * - Front end: [waitlist_form] shortcode, shown only when the product is out of stock.
 *   Submits via admin-post.php (no JS required).
 * - On signup: row inserted (de-duped per product+email) and the store admin is emailed.
 * - Notifying customers is manual, from the admin Waitlist screen (see WCBD_Waitlist_Admin).
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCBD_Waitlist {

    const DB_VERSION = '1';

    public function __construct() {
        $this->maybe_install();

        add_shortcode('waitlist_form', array($this, 'shortcode_form'));
        add_action('admin_post_nopriv_wcbd_waitlist', array($this, 'handle_submit'));
        add_action('admin_post_wcbd_waitlist', array($this, 'handle_submit'));
    }

    /** Fully-qualified table name. */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wcbd_waitlist';
    }

    /** Create/upgrade the table. */
    public static function install() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL DEFAULT '',
            email varchar(191) NOT NULL,
            consent tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            notified_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY email (email),
            KEY status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('wcbd_waitlist_db', self::DB_VERSION);
    }

    /** Install on upgrade without requiring reactivation. */
    protected function maybe_install() {
        if (get_option('wcbd_waitlist_db') !== self::DB_VERSION) {
            self::install();
        }
    }

    /**
     * Resolve the product for the shortcode (explicit id, loop product, or queried product).
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
        return $gid ? wc_get_product($gid) : null;
    }

    /**
     * [waitlist_form] — shown only when the product is out of stock.
     */
    public function shortcode_form($atts) {
        $atts = shortcode_atts(array('product_id' => 0), $atts, 'waitlist_form');

        $product = $this->get_product($atts['product_id']);
        if (!$product || $product->is_in_stock()) {
            return '';
        }

        // For variations, sign up against the parent product.
        $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

        $heading = WCBD_Settings::get('waitlist_heading');
        $intro   = WCBD_Settings::get('waitlist_intro');
        $consent = WCBD_Settings::get('waitlist_consent_label');
        $button  = WCBD_Settings::get('waitlist_button');

        ob_start();

        // Result notice after redirect.
        if (isset($_GET['wcbd_waitlist'])) {
            $state = sanitize_key(wp_unslash($_GET['wcbd_waitlist']));
            if ($state === 'success') {
                echo '<p class="wcbd-waitlist__notice wcbd-waitlist__notice--ok">' . wp_kses_post(WCBD_Settings::get('waitlist_success')) . '</p>';
            } elseif ($state === 'dup') {
                echo '<p class="wcbd-waitlist__notice wcbd-waitlist__notice--ok">' . esc_html__('You are already on the waitlist for this product.', 'wc-backorder-dates') . '</p>';
            } elseif ($state === 'error') {
                echo '<p class="wcbd-waitlist__notice wcbd-waitlist__notice--err">' . esc_html__('Sorry, something went wrong. Please check your details and try again.', 'wc-backorder-dates') . '</p>';
            }
        }
        ?>
        <form class="wcbd-waitlist" id="wcbd-waitlist" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php if ($heading !== '') : ?><h3 class="wcbd-waitlist__title"><?php echo wp_kses_post($heading); ?></h3><?php endif; ?>
            <?php if ($intro !== '') : ?><p class="wcbd-waitlist__intro"><?php echo wp_kses_post($intro); ?></p><?php endif; ?>

            <input type="hidden" name="action" value="wcbd_waitlist" />
            <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>" />
            <input type="hidden" name="redirect" value="<?php echo esc_url(get_permalink($product_id)); ?>" />
            <?php wp_nonce_field('wcbd_waitlist', 'wcbd_waitlist_nonce'); ?>

            <p class="wcbd-waitlist__hp" aria-hidden="true">
                <label>Leave this empty <input type="text" name="wcbd_hp" tabindex="-1" autocomplete="off" /></label>
            </p>

            <p class="wcbd-waitlist__row">
                <label for="wcbd_wl_name"><?php esc_html_e('Name', 'wc-backorder-dates'); ?></label>
                <input type="text" id="wcbd_wl_name" name="wcbd_name" required />
            </p>
            <p class="wcbd-waitlist__row">
                <label for="wcbd_wl_email"><?php esc_html_e('Email', 'wc-backorder-dates'); ?></label>
                <input type="email" id="wcbd_wl_email" name="wcbd_email" required />
            </p>
            <p class="wcbd-waitlist__row wcbd-waitlist__consent">
                <label><input type="checkbox" name="wcbd_consent" value="1" required /> <span><?php echo wp_kses_post($consent); ?></span></label>
            </p>

            <p class="wcbd-waitlist__row">
                <button type="submit" class="button wcbd-waitlist__submit"><?php echo esc_html($button); ?></button>
            </p>
        </form>
        <?php
        static $css_done = false;
        if (!$css_done) {
            $css_done = true;
            ?>
            <style id="wcbd-waitlist-css">
            .wcbd-waitlist__hp{position:absolute !important;left:-9999px !important;}
            .wcbd-waitlist__row{margin:0 0 .8em;}
            .wcbd-waitlist__row label{display:block;font-weight:600;margin-bottom:.25em;}
            .wcbd-waitlist__consent label{font-weight:400;display:flex;gap:.5em;align-items:flex-start;}
            .wcbd-waitlist input[type=text],.wcbd-waitlist input[type=email]{width:100%;max-width:420px;}
            .wcbd-waitlist__notice{padding:.6em .8em;border-radius:4px;margin:0 0 1em;}
            .wcbd-waitlist__notice--ok{background:#e7f6e7;color:#15803d;}
            .wcbd-waitlist__notice--err{background:#fdecec;color:#b91c1c;}
            </style>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Handle the front-end form submission.
     */
    public function handle_submit() {
        $redirect = isset($_POST['redirect']) ? esc_url_raw(wp_unslash($_POST['redirect'])) : home_url('/');

        // Nonce.
        if (!isset($_POST['wcbd_waitlist_nonce']) || !wp_verify_nonce(sanitize_key($_POST['wcbd_waitlist_nonce']), 'wcbd_waitlist')) {
            $this->redirect_result($redirect, 'error');
        }

        // Honeypot — silently accept (pretend success) so bots don't retry.
        if (!empty($_POST['wcbd_hp'])) {
            $this->redirect_result($redirect, 'success');
        }

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $name       = isset($_POST['wcbd_name']) ? sanitize_text_field(wp_unslash($_POST['wcbd_name'])) : '';
        $email      = isset($_POST['wcbd_email']) ? sanitize_email(wp_unslash($_POST['wcbd_email'])) : '';
        $consent    = !empty($_POST['wcbd_consent']) ? 1 : 0;

        if (!$product_id || !wc_get_product($product_id) || !is_email($email) || !$consent) {
            $this->redirect_result($redirect, 'error');
        }

        if ($this->exists_pending($product_id, $email)) {
            $this->redirect_result($redirect, 'dup');
        }

        $inserted = $this->insert_entry($product_id, $name, $email, $consent);
        if (!$inserted) {
            $this->redirect_result($redirect, 'error');
        }

        $this->notify_admin_new($product_id, $name, $email);
        $this->redirect_result($redirect, 'success');
    }

    protected function redirect_result($url, $state) {
        $url = add_query_arg('wcbd_waitlist', $state, $url) . '#wcbd-waitlist';
        wp_safe_redirect($url);
        exit;
    }

    protected function exists_pending($product_id, $email) {
        global $wpdb;
        $table = self::table_name();
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE product_id = %d AND email = %s AND status = 'pending' LIMIT 1",
            $product_id,
            $email
        ));
    }

    protected function insert_entry($product_id, $name, $email, $consent) {
        global $wpdb;
        return (bool) $wpdb->insert(
            self::table_name(),
            array(
                'product_id' => $product_id,
                'name'       => $name,
                'email'      => $email,
                'consent'    => $consent,
                'status'     => 'pending',
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Email the store admin that someone joined a waitlist.
     */
    protected function notify_admin_new($product_id, $name, $email) {
        $to = WCBD_Settings::get('waitlist_admin_email');
        if ($to === '') {
            $to = get_option('admin_email');
        }

        $product = wc_get_product($product_id);
        $pname   = $product ? $product->get_name() : ('#' . $product_id);

        $subject = sprintf(
            /* translators: %s product name */
            __('New waitlist signup: %s', 'wc-backorder-dates'),
            $pname
        );

        $body = sprintf(
            /* translators: 1: product, 2: name, 3: email, 4: edit link */
            __("A customer joined the waitlist.\n\nProduct: %1\$s\nName: %2\$s\nEmail: %3\$s\n\nManage the waitlist: %4\$s", 'wc-backorder-dates'),
            $pname,
            $name !== '' ? $name : '—',
            $email,
            admin_url('admin.php?page=wcbd-waitlist')
        );

        wp_mail($to, $subject, $body);
    }

    /**
     * Send the "back in stock" email to the given waitlist entry ids and mark them notified.
     *
     * @return int Number notified.
     */
    public static function notify_entries($ids) {
        global $wpdb;
        $ids = array_filter(array_map('absint', (array) $ids));
        if (empty($ids)) {
            return 0;
        }

        $table        = self::table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id IN ({$placeholders})",
            $ids
        ));

        $subject_tpl = WCBD_Settings::get('waitlist_notify_subject');
        $body_tpl    = WCBD_Settings::get('waitlist_notify_body');
        $count       = 0;

        foreach ($rows as $row) {
            $product = wc_get_product($row->product_id);
            $pname   = $product ? $product->get_name() : ('#' . $row->product_id);
            $url     = $product ? get_permalink($row->product_id) : home_url('/');

            $repl = array(
                '{product}' => $pname,
                '{name}'    => $row->name !== '' ? $row->name : '',
                '{url}'     => $url,
            );
            $subject = strtr($subject_tpl, $repl);
            $body    = strtr($body_tpl, $repl);

            if (wp_mail($row->email, $subject, $body)) {
                $wpdb->update(
                    $table,
                    array('status' => 'notified', 'notified_at' => current_time('mysql')),
                    array('id' => $row->id),
                    array('%s', '%s'),
                    array('%d')
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete waitlist entries by id.
     */
    public static function delete_entries($ids) {
        global $wpdb;
        $ids = array_filter(array_map('absint', (array) $ids));
        if (empty($ids)) {
            return 0;
        }
        $table        = self::table_name();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE id IN ({$placeholders})",
            $ids
        ));
    }
}
