<?php
defined( 'ABSPATH' ) || exit;

class WC_SendSMS_History_List_Table extends WP_List_Table {
    /**
     * Get list columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'id'            => __('ID', 'sendsms-for-woocommerce'),
            'phone'         => __('Phone', 'sendsms-for-woocommerce'),
            'status'         => __('Status', 'sendsms-for-woocommerce'),
            'message'         => __('Answer', 'sendsms-for-woocommerce'),
            'details'         => __('Details', 'sendsms-for-woocommerce'),
            'content'         => __('Content', 'sendsms-for-woocommerce'),
            'type'         => __('Type', 'sendsms-for-woocommerce'),
            'sent_on'         => __('Date', 'sendsms-for-woocommerce'),
        );
    }

    /**
     * Column cb.
     */
    public function column_cb($issue) {
        return sprintf( '<input type="checkbox" name="wc_sendsms_history[]" value="%1$s" />', $issue['id'] );
    }

    /**
     * Return ID column
     */
    public function column_id( $issue ) {
        return esc_html( $issue['id'] );
    }

    /**
     * Return phone column
     */
    public function column_phone( $issue ) {
        return esc_html( $issue['phone'] );
    }

    /**
     * Return message column
     */
    public function column_message( $issue ) {
        return esc_html( $issue['message'] );
    }

    /**
     * Return status column
     */
    public function column_status( $issue ) {
        return esc_html( $issue['status'] );
    }

    /**
     * Return details column
     */
    public function column_details( $issue ) {
        return esc_html( $issue['details'] );
    }

    /**
     * Return content column
     */
    public function column_content( $issue ) {
        return esc_html( $issue['content'] );
    }

    /**
     * Return type column
     */
    public function column_type( $issue ) {
        return esc_html( $issue['type'] );
    }

    /**
     * Return sent_on column
     */
    public function column_sent_on( $issue ) {
        return esc_html( $issue['sent_on'] );
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return array(

        );
    }

    public function get_sortable_columns()
    {
        return array(
            'sent_on' => array('sent_on', true),
            'id' => array('id', true),
            'status' => array('status', true),
            'phone' => array('phone', true),
            'message' => array('message', true),
            'details' => array('details', true),
            'content' => array('content', true),
            'type' => array('type', true)
        );
    }

    /**
     * Prepare table list items.
     */
    public function prepare_items() {
        global $wpdb;

        $per_page = 10;
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $table_name = $wpdb->prefix . 'wcsendsms_history';

        // Column headers
        $this->_column_headers = array($columns, $hidden, $sortable);

        $current_page = $this->get_pagenum();
        if (1 < $current_page) {
            $offset = $per_page * ( $current_page - 1 );
        } else {
            $offset = 0;
        }

        $where = ' WHERE 1 = 1';
        $where_args = array();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list table search
        if (!empty($_REQUEST['s'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list table search
            $like = '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($_REQUEST['s']))) . '%';
            $where .= ' AND (phone LIKE %s OR message LIKE %s OR content LIKE %s OR `type` LIKE %s OR details LIKE %s OR sent_on LIKE %s)';
            $where_args = array($like, $like, $like, $like, $like, $like);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list table ordering
        if (isset($_GET['orderby']) && isset($columns[sanitize_text_field(wp_unslash($_GET['orderby']))])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list table ordering
            $orderBy = sanitize_text_field(wp_unslash($_GET['orderby']));
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list table ordering
            if (isset($_GET['order']) && in_array(strtolower(sanitize_text_field(wp_unslash($_GET['order']))), array('asc', 'desc'), true)) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list table ordering
                $order = sanitize_text_field(wp_unslash($_GET['order']));
            } else {
                $order = 'ASC';
            }
        } else {
            $orderBy = 'id';
            $order = 'DESC';
        }

        // $orderBy and $order are whitelisted above; $table_name uses $wpdb->prefix.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- our plugin's own history table; reads are paginated and use prepared placeholders.
        $select_sql = "SELECT id, phone, status, message, details, content, `type`, sent_on FROM {$wpdb->prefix}wcsendsms_history" . $where . " ORDER BY `$orderBy` $order LIMIT %d OFFSET %d";
        $count_sql  = "SELECT COUNT(id) FROM {$wpdb->prefix}wcsendsms_history" . $where;

        $items = $wpdb->get_results(
            $wpdb->prepare($select_sql, array_merge($where_args, array($per_page, $offset))),
            ARRAY_A
        );
        $count = $where_args
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_args))
            : (int) $wpdb->get_var($count_sql);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        $this->items = $items;

        // Set the pagination
        $this->set_pagination_args(array(
            'total_items' => $count,
            'per_page'    => $per_page,
            'total_pages' => ceil( $count / $per_page )
        ));
    }
}
