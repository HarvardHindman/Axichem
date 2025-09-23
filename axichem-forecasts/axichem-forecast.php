<?php
/**
 * Plugin Name: Axichem Forecast
 * Description: Customer demand forecasting system for Axichem
 * Version: 1.0
 * Author: Axichem
 * Text Domain: axichem-forecast
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AXICHEM_FORECAST_VERSION', '1.0');
define('AXICHEM_FORECAST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AXICHEM_FORECAST_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include files 
function include_export_data_file()
{
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-forecast.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-help.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-instructions.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-dashboard.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-individuals.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-message.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-shopmanager.php');
}
add_action('plugins_loaded', 'include_export_data_file');


// Enqueue Style & Scripts
function enqueue_custom_plugin_styles()
{
    wp_enqueue_script('jquery');
    wp_enqueue_style('axichem-forecast-css', plugin_dir_url(__FILE__) . '/includes/css/lionandlamb-axichem.css');
    wp_enqueue_style('dataTable-css', plugin_dir_url(__FILE__) . '/includes/css/datatables.min.css');
    wp_enqueue_style('dataTableResponsive-css', plugin_dir_url(__FILE__) . '/includes/css/responsive.dataTables.min.css');
    wp_enqueue_style('datatables-fix-css', plugin_dir_url(__FILE__) . '/includes/css/datatables-fix.css');
    wp_enqueue_style('select-fix-css', plugin_dir_url(__FILE__) . '/includes/css/select-fix.css');
    wp_enqueue_style('alignment-fix-css', plugin_dir_url(__FILE__) . '/includes/css/alignment-fix.css', array(), '1.0.5');
    wp_enqueue_script('dataTable-js', plugin_dir_url(__FILE__) . '/includes/js/datatables.min.js');
    wp_enqueue_script('dataTableResponsive-js', plugin_dir_url(__FILE__) . '/includes/js/dataTables.responsive.min.js');
    wp_enqueue_script('axichem-forecast-js', plugin_dir_url(__FILE__) . '/includes/js/lionandlamb-axichem-fixed.js');
    wp_localize_script('axichem-forecast-js', 'ajax_object', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('product_search_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_plugin_styles');

// AJAX handler for product search
function search_products() {
    // Check for nonce for security
    check_ajax_referer('product_search_nonce', 'security');
    
    $search_term = sanitize_text_field($_POST['search_term']);
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    
    // Log the search request for debugging
    error_log('Product search request: ' . $search_term . ', Category: ' . $category_id);
    
    // Advanced search to include multiple search fields (title, SKU, product meta)
    global $wpdb;
    
    // Build the search query parts
    $post_type_query = "post_type = 'product' AND post_status = 'publish'";
    $search_query = '';
    $category_query = '';
    $limit_query = 'LIMIT 100'; // Show up to 100 results
    
    // Add search term if provided
    if (!empty($search_term)) {
        // Escape the search term for safe use in SQL
        $like_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        // Search in post title, post content, SKU meta, and excerpt
        $search_query = $wpdb->prepare(
            "AND (
                p.post_title LIKE %s
                OR p.post_content LIKE %s
                OR p.post_excerpt LIKE %s
                OR (pm.meta_key = '_sku' AND pm.meta_value LIKE %s)
            )",
            $like_term, $like_term, $like_term, $like_term
        );
    }
    
    // Add category filter if a category is selected
    if ($category_id > 0) {
        $category_query = $wpdb->prepare(
            "AND p.ID IN (
                SELECT object_id FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'product_cat' AND tt.term_id = %d
            )",
            $category_id
        );
    }
    
    // Build the full query
    $query = "
        SELECT DISTINCT p.ID, p.post_title 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE {$post_type_query}
        {$search_query}
        {$category_query}
        ORDER BY p.post_title ASC
        {$limit_query}
    ";
    
    // Log the SQL query for debugging (with values replaced)
    error_log('Search query: ' . str_replace(array("\r", "\n", "  "), ' ', $query));
    
    // Run the query
    $results = $wpdb->get_results($query);
    
    // Log the number of results
    error_log('Database search found ' . count($results) . ' results');
    
    $products = array();
    
    if (!empty($results)) {
        foreach ($results as $result) {
            $product_id = $result->ID;
            $product = wc_get_product($product_id);
            
            // Only include products that are available
            if ($product && $product->is_purchasable()) {
                // Get product categories
                $categories = get_the_terms($product_id, 'product_cat');
                $category_names = array();
                
                if (!empty($categories) && !is_wp_error($categories)) {
                    foreach ($categories as $category) {
                        $category_names[] = $category->name;
                    }
                }
                
                // Get product metadata
                $sku = $product->get_sku();
                $price_html = $product->get_price_html();
                $product_name = html_entity_decode($product->get_name());
                
                $products[] = array(
                    'id' => $product_id,
                    'name' => $product_name,
                    'sku' => $sku,
                    'price' => $price_html,
                    'categories' => implode(', ', $category_names),
                    'stock_status' => $product->get_stock_status()
                );
            }
        }
    }
    
    // Log the final number of products found after filtering
    error_log('Final product count after filtering: ' . count($products));
    
    wp_send_json_success($products);
    wp_die();
}
add_action('wp_ajax_search_products', 'search_products');
add_action('wp_ajax_nopriv_search_products', 'search_products');

// AJAX handler for product suggestions
function fetch_product_suggestions() {
    // Check for nonce for security
    check_ajax_referer('product_search_nonce', 'security');
    
    $search_term = sanitize_text_field($_POST['search_term']);
    
    // Log the search term for debugging
    error_log('Product suggestion search for: ' . $search_term);
    
    if (strlen($search_term) < 2) {
        wp_send_json_success(array());
        wp_die();
    }
    
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 20, // Increased from 10 to 20 for more suggestions
        'orderby' => 'title',
        'order' => 'ASC',
        's' => $search_term
    );
    
    $query = new WP_Query($args);
    $suggestions = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            
            // Only include products that are available
            if ($product && $product->is_purchasable()) {
                $suggestions[] = array(
                    'id' => $product_id,
                    'name' => html_entity_decode(get_the_title()), // Decode HTML entities for proper display
                    'sku' => $product->get_sku()
                );
            }
        }
        wp_reset_postdata();
    }
    
    // Log the number of suggestions found
    error_log('Found ' . count($suggestions) . ' product suggestions');
    
    wp_send_json_success($suggestions);
    wp_die();
}
add_action('wp_ajax_fetch_product_suggestions', 'fetch_product_suggestions');
add_action('wp_ajax_nopriv_fetch_product_suggestions', 'fetch_product_suggestions');

// AJAX handler to get saved product quantities for all months
function populate_all_product_quantities() {
    global $wpdb;
    $user_id = get_current_user_id();
    $selectedYear = isset($_POST['selectedYear']) ? sanitize_text_field($_POST['selectedYear']) : date('Y');
    
    error_log('Fetching product quantities for user: ' . $user_id . ', year: ' . $selectedYear);
    
    $user_table_name = $wpdb->prefix . 'forecast_sheets';
    
    $data_query = $wpdb->prepare(
        "SELECT product_id, product_name, in_month, quantity FROM $user_table_name WHERE user_id = %d AND in_year = %s",
        $user_id,
        $selectedYear
    );
    
    $data = $wpdb->get_results($data_query);
    
    error_log('Found ' . count($data) . ' product quantity records');
    
    $product_quantities = array();
    $product_names = array();
    
    foreach ($data as $row) {
        $product_id = $row->product_id;
        
        // Skip invalid product IDs
        if (empty($product_id)) {
            continue;
        }
        
        // Initialize product arrays if needed
        if (!isset($product_quantities[$product_id])) {
            $product_quantities[$product_id] = array();
            
            // Decode HTML entities in product names and handle special characters
            $product_name = html_entity_decode($row->product_name);
            $product_names[$product_id] = $product_name;
            
            // Log product found
            error_log("Product found: ID=$product_id, Name=$product_name");
        }
        
        // Ensure month is properly formatted as two digits
        $month = str_pad($row->in_month, 2, '0', STR_PAD_LEFT);
        
        // Store the quantity (convert to integer to avoid string issues)
        $quantity = intval($row->quantity);
        $product_quantities[$product_id][$month] = $quantity;
        
        // Log month quantities for debugging
        error_log("Product $product_id, Month $month: Quantity $quantity");
    }
    
    // Detailed log of the constructed data structure
    error_log('Constructed product quantities array with ' . count($product_quantities) . ' products');
    
    $response = array(
        'quantities' => $product_quantities,
        'product_names' => $product_names
    );
    
    // Ensure proper JSON encoding with error handling
    $json_response = json_encode($response);
    if ($json_response === false) {
        error_log('JSON encode error: ' . json_last_error_msg());
        
        // Try to sanitize product names to remove problematic characters
        foreach ($product_names as $id => $name) {
            $product_names[$id] = preg_replace('/[^\p{L}\p{N}\s\-_\'"\.,;:!?()]/u', '', $name);
        }
        
        $clean_response = array(
            'quantities' => $product_quantities,
            'product_names' => $product_names
        );
        
        wp_send_json($clean_response);
    } else {
        wp_send_json($response);
    }
    
    wp_die();
}
add_action('wp_ajax_populate_all_product_quantities', 'populate_all_product_quantities');
add_action('wp_ajax_nopriv_populate_all_product_quantities', 'populate_all_product_quantities');

// AJAX handler to get product details by ID
function get_product_details() {
    // Check for nonce for security
    check_ajax_referer('product_search_nonce', 'security');
    
    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);
    
    if ($product) {
        $product_data = array(
            'id' => $product_id,
            'name' => $product->get_name(),
            'sku' => $product->get_sku()
        );
        wp_send_json_success($product_data);
    } else {
        wp_send_json_error('Product not found');
    }
    
    wp_die();
}
add_action('wp_ajax_get_product_details', 'get_product_details');
add_action('wp_ajax_nopriv_get_product_details', 'get_product_details');
