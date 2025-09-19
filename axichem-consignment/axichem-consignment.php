<?php
/**
 * Plugin Name: Axichem Consignment
 * Description: Consignment stock management system for Axichem
 * Version: 1.0
 * Author: Axichem
 * Text Domain: axichem-consignment
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AXICHEM_CONSIGNMENT_VERSION', '1.0');
define('AXICHEM_CONSIGNMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AXICHEM_CONSIGNMENT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once AXICHEM_CONSIGNMENT_PLUGIN_DIR . 'includes/consignment-db.php';
require_once AXICHEM_CONSIGNMENT_PLUGIN_DIR . 'includes/frontend-consignment.php';
require_once AXICHEM_CONSIGNMENT_PLUGIN_DIR . 'includes/admin-consignment.php';

// Create database tables on plugin activation
register_activation_hook(__FILE__, 'axichem_consignment_activate');

function axichem_consignment_activate() {
    // Create database tables
    create_consignment_stock_table();
    
    // Add the endpoint
    add_rewrite_endpoint('consignment-stock', EP_ROOT | EP_PAGES);
    
    // Clear the permalinks
    flush_rewrite_rules();
    
    // Set option to false so endpoint function knows to flush rules again
    update_option('consignment_stock_flush_rewrite_rules', false);
}

// Clean up on plugin deactivation
register_deactivation_hook(__FILE__, 'axichem_consignment_deactivate');

function axichem_consignment_deactivate() {
    // Clear the permalinks to remove our endpoint
    flush_rewrite_rules();
}

// Enqueue CSS and JS files
function axichem_consignment_enqueue_scripts() {
    // CSS
    wp_enqueue_style('axichem-consignment-style', AXICHEM_CONSIGNMENT_PLUGIN_URL . 'css/axichem-consignment.css', array(), AXICHEM_CONSIGNMENT_VERSION);
    
    // DataTables CSS
    wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css', array(), '1.11.5');
    
    // DataTables Fix CSS - Load this after the original DataTables CSS to override styles
    wp_enqueue_style('datatables-fix-css', AXICHEM_CONSIGNMENT_PLUGIN_URL . 'css/datatables-fix.css', array('datatables-css'), AXICHEM_CONSIGNMENT_VERSION);
    
    // Additional select fix CSS - load last to ensure it takes precedence
    wp_enqueue_style('select-fix-css', AXICHEM_CONSIGNMENT_PLUGIN_URL . 'css/select-fix.css', array('datatables-css', 'datatables-fix-css'), AXICHEM_CONSIGNMENT_VERSION);
    
    // JavaScript
    wp_enqueue_script('jquery');
    wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js', array('jquery'), '1.11.5', true);
    wp_enqueue_script('axichem-consignment-js', AXICHEM_CONSIGNMENT_PLUGIN_URL . 'js/axichem-consignment.js', array('jquery', 'datatables-js'), AXICHEM_CONSIGNMENT_VERSION, true);
    
    // Pass AJAX URL to script
    wp_localize_script('axichem-consignment-js', 'axichem_consignment_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('axichem-consignment-nonce')
    ));
}
add_action('wp_enqueue_scripts', 'axichem_consignment_enqueue_scripts');
add_action('admin_enqueue_scripts', 'axichem_consignment_enqueue_scripts');

// Create CSS directory and file if they don't exist
if (!file_exists(AXICHEM_CONSIGNMENT_PLUGIN_DIR . 'css')) {
    mkdir(AXICHEM_CONSIGNMENT_PLUGIN_DIR . 'css', 0755, true);
}

// Create JS directory and file if they don't exist
if (!file_exists(AXICHEM_CONSIGNMENT_PLUGIN_DIR . 'js')) {
    mkdir(AXICHEM_CONSIGNMENT_PLUGIN_DIR . 'js', 0755, true);
}