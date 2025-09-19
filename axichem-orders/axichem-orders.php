<?php
/**
 * Plugin Name: Axichem Orders
 * Description: Customer order management system for Axichem
 * Version: 1.0
 * Author: Axichem
 * Text Domain: axichem-orders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AXICHEM_ORDERS_VERSION', '1.0');
define('AXICHEM_ORDERS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AXICHEM_ORDERS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once AXICHEM_ORDERS_PLUGIN_DIR . 'includes/frontend-orders.php';
require_once AXICHEM_ORDERS_PLUGIN_DIR . 'includes/admin-orders.php';

// Create database tables on plugin activation
register_activation_hook(__FILE__, 'axichem_orders_activate');

function axichem_orders_activate() {
    // Create database tables
    axichem_orders_create_tables();
    
    // Add the endpoint
    add_rewrite_endpoint('axichem-orders', EP_ROOT | EP_PAGES);
    
    // Clear the permalinks after the custom post type has been registered
    flush_rewrite_rules();
    
    // Set option to false so endpoint function knows to flush rules again
    update_option('axichem_orders_flush_rewrite_rules', false);
}

function axichem_orders_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $orders_table = $wpdb->prefix . 'axichem_orders';
    
    $sql = "CREATE TABLE IF NOT EXISTS $orders_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id varchar(50) NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        date_created datetime NOT NULL,
        product_id varchar(100) NOT NULL,
        product_description varchar(255) NOT NULL,
        quantity int(11) NOT NULL,
        amount decimal(15,2) NOT NULL DEFAULT 0.00,
        status varchar(50) NOT NULL DEFAULT 'Order',
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue CSS and JS files
function axichem_orders_enqueue_scripts() {
    // CSS
    wp_enqueue_style('axichem-orders-style', AXICHEM_ORDERS_PLUGIN_URL . 'css/axichem-orders.css', array(), AXICHEM_ORDERS_VERSION);
    
    // DataTables CSS
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css', array(), '1.11.5');
    
    // JavaScript
    wp_enqueue_script('jquery');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', array('jquery'), '1.11.5', true);
    wp_enqueue_script('axichem-orders-js', AXICHEM_ORDERS_PLUGIN_URL . 'js/axichem-orders.js', array('jquery', 'datatables-js'), AXICHEM_ORDERS_VERSION, true);
    
    // Pass AJAX URL to script
    wp_localize_script('axichem-orders-js', 'axichem_orders_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('axichem-orders-nonce')
    ));
}
add_action('wp_enqueue_scripts', 'axichem_orders_enqueue_scripts');
add_action('admin_enqueue_scripts', 'axichem_orders_enqueue_scripts');

// Create CSS directory and file if they don't exist
if (!file_exists(AXICHEM_ORDERS_PLUGIN_DIR . 'css')) {
    mkdir(AXICHEM_ORDERS_PLUGIN_DIR . 'css', 0755, true);
}

// Create JS directory and file if they don't exist
if (!file_exists(AXICHEM_ORDERS_PLUGIN_DIR . 'js')) {
    mkdir(AXICHEM_ORDERS_PLUGIN_DIR . 'js', 0755, true);
}
