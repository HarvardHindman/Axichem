<?php
/*
Plugin Name: Forecast Sheets by Lion&Lamb
Description: Adds a Forecast tab to the My Account page, where users can fill out the product form.
*/

// Include files 
function include_export_data_file()
{
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-forecast.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-help.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-instructions.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-dashboard.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-individuals.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-totals.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-totals-quarters.php');
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
    
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 50, // Increased limit to show more results
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    // Add search term if provided
    if (!empty($search_term)) {
        $args['s'] = $search_term;
    }
    
    // Add category filter if a category is selected
    if ($category_id > 0) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_id
            )
        );
    }
    
    $query = new WP_Query($args);
    $products = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
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
                
                $products[] = array(
                    'id' => $product_id,
                    'name' => get_the_title(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price_html(),
                    'categories' => implode(', ', $category_names)
                );
            }
        }
        wp_reset_postdata();
    }
    
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
        'posts_per_page' => 10, // Limit to top 10 suggestions for faster response
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
                    'name' => get_the_title(),
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
        if (!isset($product_quantities[$row->product_id])) {
            $product_quantities[$row->product_id] = array();
            $product_names[$row->product_id] = $row->product_name;
        }
        $product_quantities[$row->product_id][$row->in_month] = $row->quantity;
    }
    
    $response = array(
        'quantities' => $product_quantities,
        'product_names' => $product_names
    );
    
    wp_send_json($response);
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
