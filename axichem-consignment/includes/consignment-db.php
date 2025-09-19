<?php
// Database setup for consignment stock

function create_consignment_stock_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'consignment_stock';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        product_id varchar(100) NOT NULL,
        product_name varchar(255) NOT NULL,
        stock_quantity int(11) NOT NULL DEFAULT 0,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_product (user_id, product_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Get consignment stock for a specific user
function get_user_consignment_stock($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'consignment_stock';
    
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d ORDER BY product_name ASC",
        $user_id
    );
    
    return $wpdb->get_results($query);
}

// Get all consignment stock
function get_all_consignment_stock() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'consignment_stock';
    
    return $wpdb->get_results(
        "SELECT cs.*, u.display_name as user_name 
         FROM $table_name cs
         LEFT JOIN {$wpdb->users} u ON cs.user_id = u.ID
         ORDER BY u.display_name, cs.product_name"
    );
}

// Add or update consignment stock
function add_update_consignment_stock($user_id, $product_id, $product_name, $stock_quantity) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'consignment_stock';
    
    // Check if record already exists
    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND product_id = %s",
            $user_id,
            $product_id
        )
    );
    
    if ($existing) {
        // Update existing record
        return $wpdb->update(
            $table_name,
            array(
                'product_name' => $product_name,
                'stock_quantity' => $stock_quantity,
                'last_updated' => current_time('mysql')
            ),
            array(
                'user_id' => $user_id,
                'product_id' => $product_id
            ),
            array('%s', '%d', '%s'),
            array('%d', '%s')
        );
    } else {
        // Insert new record
        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'product_id' => $product_id,
                'product_name' => $product_name,
                'stock_quantity' => $stock_quantity,
                'last_updated' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }
}

// Delete consignment stock item
function delete_consignment_stock_item($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'consignment_stock';
    
    return $wpdb->delete(
        $table_name,
        array('id' => $id),
        array('%d')
    );
}

// Function to drop the consignment stock table on plugin uninstall
function drop_consignment_stock_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'consignment_stock';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}