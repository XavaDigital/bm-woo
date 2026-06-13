<?php
/**
 * Admin "Waitlist" screen: view signups, filter by product, and notify customers
 * (back-in-stock email) or delete entries via bulk actions.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WCBD_Waitlist_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'waitlist_entry',
            'plural'   => 'waitlist_entries',
            'ajax'     => false,
        ));
    }

    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox" />',
            'product'    => __('Product', 'wc-backorder-dates'),
            'name'       => __('Name', 'wc-backorder-dates'),
            'email'      => __('Email', 'wc-backorder-dates'),
            'consent'    => __('Consent', 'wc-backorder-dates'),
            'status'     => __('Status', 'wc-backorder-dates'),
            'created_at' => __('Signed up', 'wc-backorder-dates'),
        );
    }

    public function get_sortable_columns() {
        return array(
            'created_at' => array('created_at', true),
            'status'     => array('status', false),
            'email'      => array('email', false),
        );
    }

    protected function get_bulk_actions() {
        return array(
            'notify' => __('Notify (back in stock) & mark notified', 'wc-backorder-dates'),
            'delete' => __('Delete', 'wc-backorder-dates'),
        );
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="entry[]" value="%d" />', $item['id']);
    }

    public function column_product($item) {
        $product = wc_get_product($item['product_id']);
        $name    = $product ? $product->get_name() : ('#' . $item['product_id']);
        $edit    = get_edit_post_link($item['product_id']);
        return $edit ? '<a href="' . esc_url($edit) . '">' . esc_html($name) . '</a>' : esc_html($name);
    }

    public function column_consent($item) {
        return $item['consent'] ? '✓' : '—';
    }

    public function column_status($item) {
        $label = $item['status'] === 'notified' ? __('Notified', 'wc-backorder-dates') : __('Pending', 'wc-backorder-dates');
        return esc_html($label);
    }

    public function column_created_at($item) {
        $ts = strtotime($item['created_at']);
        return $ts ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $ts)) : esc_html($item['created_at']);
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
    }

    /** Product filter dropdown above the table. */
    protected function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        global $wpdb;
        $table = WCBD_Waitlist::table_name();
        $product_ids = $wpdb->get_col("SELECT DISTINCT product_id FROM {$table} ORDER BY product_id");
        if (empty($product_ids)) {
            return;
        }
        $current = isset($_REQUEST['product_id']) ? absint($_REQUEST['product_id']) : 0;
        echo '<div class="alignleft actions">';
        echo '<select name="product_id">';
        echo '<option value="0">' . esc_html__('All products', 'wc-backorder-dates') . '</option>';
        foreach ($product_ids as $pid) {
            $product = wc_get_product($pid);
            $label   = $product ? $product->get_name() : ('#' . $pid);
            printf('<option value="%d"%s>%s</option>', $pid, selected($current, $pid, false), esc_html($label));
        }
        echo '</select>';
        submit_button(__('Filter', 'wc-backorder-dates'), '', 'filter_action', false);
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;
        $table = WCBD_Waitlist::table_name();

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());

        $per_page = 30;
        $paged    = max(1, (int) $this->get_pagenum());
        $offset   = ($paged - 1) * $per_page;

        $where = '1=1';
        $params = array();
        $product_id = isset($_REQUEST['product_id']) ? absint($_REQUEST['product_id']) : 0;
        if ($product_id) {
            $where   .= ' AND product_id = %d';
            $params[] = $product_id;
        }

        $allowed_orderby = array('created_at', 'status', 'email');
        $orderby = isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], $allowed_orderby, true) ? $_REQUEST['orderby'] : 'created_at';
        $order   = (isset($_REQUEST['order']) && strtolower($_REQUEST['order']) === 'asc') ? 'ASC' : 'DESC';

        $total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        $total = (int) ($params ? $wpdb->get_var($wpdb->prepare($total_sql, $params)) : $wpdb->get_var($total_sql));

        $data_sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_params = array_merge($params, array($per_page, $offset));
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, $query_params), ARRAY_A);

        $this->items = $rows ? $rows : array();

        $this->set_pagination_args(array(
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ));
    }
}

class WCBD_Waitlist_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'), 20);
    }

    public function add_menu() {
        $hook = add_submenu_page(
            'woocommerce',
            __('Waitlist', 'wc-backorder-dates'),
            __('Waitlist', 'wc-backorder-dates'),
            'manage_woocommerce',
            'wcbd-waitlist',
            array($this, 'render_page')
        );
        // Process bulk actions before any output is sent (so the redirect works).
        if ($hook) {
            add_action('load-' . $hook, array($this, 'handle_actions'));
        }
    }

    /**
     * Process bulk actions before rendering, then redirect to avoid resubmission.
     */
    protected function handle_actions() {
        if (empty($_REQUEST['action']) && empty($_REQUEST['action2'])) {
            return;
        }
        $action = !empty($_REQUEST['action']) && $_REQUEST['action'] !== '-1' ? $_REQUEST['action'] : ($_REQUEST['action2'] ?? '');
        if (!in_array($action, array('notify', 'delete'), true)) {
            return;
        }

        check_admin_referer('bulk-waitlist_entries');

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $ids = isset($_REQUEST['entry']) ? array_map('absint', (array) $_REQUEST['entry']) : array();
        $notified = 0;
        $deleted  = 0;
        if ($ids) {
            if ($action === 'notify') {
                $notified = WCBD_Waitlist::notify_entries($ids);
            } elseif ($action === 'delete') {
                $deleted = WCBD_Waitlist::delete_entries($ids);
            }
        }

        $redirect = add_query_arg(
            array(
                'page'          => 'wcbd-waitlist',
                'wcbd_notified' => $notified,
                'wcbd_deleted'  => $deleted,
            ),
            admin_url('admin.php')
        );
        if (!empty($_REQUEST['product_id'])) {
            $redirect = add_query_arg('product_id', absint($_REQUEST['product_id']), $redirect);
        }
        wp_safe_redirect($redirect);
        exit;
    }

    public function render_page() {
        $table = new WCBD_Waitlist_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Product Waitlist', 'wc-backorder-dates'); ?></h1>

            <?php
            if (isset($_GET['wcbd_notified']) && (int) $_GET['wcbd_notified'] > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>'
                    . sprintf(esc_html__('%d customer(s) notified.', 'wc-backorder-dates'), (int) $_GET['wcbd_notified'])
                    . '</p></div>';
            }
            if (isset($_GET['wcbd_deleted']) && (int) $_GET['wcbd_deleted'] > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>'
                    . sprintf(esc_html__('%d entry(ies) deleted.', 'wc-backorder-dates'), (int) $_GET['wcbd_deleted'])
                    . '</p></div>';
            }
            ?>

            <form method="get">
                <input type="hidden" name="page" value="wcbd-waitlist" />
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }
}
