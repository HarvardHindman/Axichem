<?php
/*
Plugin Name: Forecast Sheets by Lion&Lamb
Description: Adds a Forecast tab to the My Account page, where users can fill out the product form.
*/

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'forecast_sheets_plugin_activate');
register_deactivation_hook(__FILE__, 'forecast_sheets_plugin_deactivate');

// Activation function
function forecast_sheets_plugin_activate() {
    // Flush rewrite rules to make sure our endpoints work
    flush_rewrite_rules();
}

// Deactivation function
function forecast_sheets_plugin_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Include files 
function include_export_data_file()
{
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-forecast.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-help.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-instructions.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-dashboard.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-individuals-fixed.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-totals-unified.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-message.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-shopmanager.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php');
}
add_action('plugins_loaded', 'include_export_data_file');


// Enqueue Style & Scripts
function enqueue_custom_plugin_styles()
{
    wp_enqueue_script('jquery');
    wp_enqueue_style('axichem-forecast-css', plugin_dir_url(__FILE__) . '/includes/css/lionandlamb-axichem.css', array(), '1.1.0');
    wp_enqueue_style('dataTable-css', plugin_dir_url(__FILE__) . '/includes/css/datatables.min.css');
    wp_enqueue_style('dataTableResponsive-css', plugin_dir_url(__FILE__) . '/includes/css/responsive.dataTables.min.css');
    wp_enqueue_style('datatables-fix-css', plugin_dir_url(__FILE__) . '/includes/css/datatables-fix.css');
    wp_enqueue_style('select-fix-css', plugin_dir_url(__FILE__) . '/includes/css/select-fix.css');
    wp_enqueue_style('alignment-fix-css', plugin_dir_url(__FILE__) . '/includes/css/alignment-fix.css', array(), '1.0.4');
    wp_enqueue_script('dataTable-js', plugin_dir_url(__FILE__) . '/includes/js/datatables.min.js');
    wp_enqueue_script('dataTableResponsive-js', plugin_dir_url(__FILE__) . '/includes/js/dataTables.responsive.min.js');
    wp_enqueue_script('axichem-forecast-js', plugin_dir_url(__FILE__) . '/includes/js/lionandlamb-axichem-fixed.js', array('jquery'), '1.0.4', true);
    wp_enqueue_script('unified-totals-js', plugin_dir_url(__FILE__) . '/includes/js/unified-totals.js', array('jquery', 'dataTable-js'), '1.0.0', true);
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
    $selectedYear = sanitize_text_field($_POST['selectedYear']);
    
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
        // Ensure product ID is treated as a string key for consistency
        $product_id = (string) $row->product_id;
        
        if (!isset($product_quantities[$product_id])) {
            $product_quantities[$product_id] = array();
            $product_names[$product_id] = $row->product_name;
        }
        
        // ALWAYS ensure month is properly zero-padded with sprintf
        $month = sprintf('%02d', (int)$row->in_month);
        
        // Log individual month data
        error_log("Product: $product_id, Month: {$row->in_month} formatted to: $month, Quantity: {$row->quantity}");
        
        // Store the quantity as an integer
        $product_quantities[$product_id][$month] = (int) $row->quantity;
    }
    
    $response = array(
        'quantities' => $product_quantities,
        'product_names' => $product_names
    );
    
    // Debug log the response structure
    error_log('Sending response with ' . count($product_quantities) . ' products');
    if (count($product_quantities) > 0) {
        error_log('First product ID: ' . array_key_first($product_quantities));
        $first_product = reset($product_quantities);
        error_log('First product months: ' . implode(',', array_keys($first_product)));
        
        // Log the month data for first product
        foreach ($first_product as $month => $qty) {
            error_log("Month key: $month, Quantity: $qty");
        }
    }
    
    // Log entire response for debugging
    error_log('Full response: ' . json_encode($response));
    
    // Send the response in a structured format
    wp_send_json($response);
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

// AJAX handler for saving forecast data
function save_forecast_data() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        wp_die();
    }
    
    $user_name = wp_get_current_user()->user_login;
    $product_quantities = isset($_POST['product_quantity']) ? $_POST['product_quantity'] : array();
    $selectedYear = isset($_POST['selectedYear']) ? sanitize_text_field($_POST['selectedYear']) : '';
    $isSaveOnly = isset($_POST['save_form']);
    $isSubmitToAxichem = isset($_POST['submit_form']);
    $poNumber = isset($_POST['poNumber']) ? sanitize_text_field($_POST['poNumber']) : '';
    
    if (empty($product_quantities) || !is_array($product_quantities)) {
        wp_send_json_error('No product data provided');
        wp_die();
    }
    
    // Log the product data received
    error_log('Received product quantities for saving: ' . json_encode(array_keys($product_quantities)));
    
    global $wpdb;
    $user_table_name = $wpdb->prefix . 'forecast_sheets';
    $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';
    
    // Create tables if they don't exist
    if ($wpdb->get_var("SHOW TABLES LIKE '$user_table_name'") !== $user_table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $user_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL,
            user_name varchar(255) NOT NULL,
            product_id varchar(255) NOT NULL,
            product_name varchar(255) NOT NULL,
            quantity int NOT NULL,
            in_year mediumint(9) NOT NULL,
            in_month mediumint(9) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '$total_table_name'") !== $total_table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $total_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id varchar(255) NOT NULL,
            product_name varchar(255) NOT NULL,
            total_quantity int NOT NULL,
            in_year mediumint(9) NOT NULL,
            in_month mediumint(9) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Save the product quantities
    foreach ($product_quantities as $product_id => $month_quantities) {
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : '';
        $product_id = sanitize_text_field($product_id);
        $product_name = sanitize_text_field($product_name);

        foreach ($month_quantities as $month => $quantity) {
            $quantity = intval($quantity);
            
            // Ensure month is consistently formatted - convert string month to int and then back to string with leading zero
            $month_numeric = intval($month);
            $month_formatted = sprintf('%02d', $month_numeric);
            
            error_log("Saving product: $product_id, Original month: $month, Formatted month: $month_formatted, Quantity: $quantity");
            
            $existing_user_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $user_table_name WHERE user_id = %d AND product_id = %s AND in_month = %s AND in_year = %s",
                    $user_id,
                    $product_id,
                    $month_formatted,
                    $selectedYear
                )
            );

            if ($existing_user_row) {
                $wpdb->update(
                    $user_table_name,
                    array(
                        'quantity' => $quantity,
                    ),
                    array('id' => $existing_user_row->id)
                );
            } else {
                $wpdb->insert(
                    $user_table_name,
                    array(
                        'user_id' => $user_id,
                        'user_name' => $user_name,
                        'product_id' => $product_id,
                        'product_name' => $product_name,
                        'quantity' => $quantity,
                        'in_year' => $selectedYear,
                        'in_month' => $month_formatted
                    )
                );
            }

            // Update the totals table
            $existing_total_row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $total_table_name WHERE product_id = %s AND in_month = %s AND in_year = %s",
                    $product_id,
                    $month_formatted,
                    $selectedYear
                )
            );

            // Calculate the total quantity for this month and year
            $total_quantity = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(quantity) FROM $user_table_name WHERE product_id = %s AND in_month = %s AND in_year = %s",
                    $product_id,
                    $month_formatted,
                    $selectedYear
                )
            );

            if ($existing_total_row) {
                // If the total row exists for this month and year, update the total quantity and product name
                $wpdb->update(
                    $total_table_name,
                    array(
                        'product_name' => $product_name,
                        'total_quantity' => $total_quantity,
                        'in_year' => $selectedYear,
                        'in_month' => $month_formatted
                    ),
                    array(
                        'product_id' => $product_id,
                        'in_year' => $selectedYear,
                        'in_month' => $month_formatted
                    )
                );
            } else {
                // If the total row doesn't exist for this month and year, insert new data
                $wpdb->insert(
                    $total_table_name,
                    array(
                        'product_id' => $product_id,
                        'product_name' => $product_name,
                        'total_quantity' => $total_quantity,
                        'in_year' => $selectedYear,
                        'in_month' => $month_formatted
                    )
                );
            }
        }
    }
    
    // If this is a submission to Axichem, send an email
    if ($isSubmitToAxichem) {
        // Gather the saved data for the email
        $saved_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $user_table_name WHERE user_id = %d AND in_year = %s ORDER BY product_name",
                $user_id,
                $selectedYear
            )
        );

        // Organize data by product for the email
        $products_data = array();
        foreach ($saved_data as $row) {
            if (!isset($products_data[$row->product_id])) {
                $products_data[$row->product_id] = array(
                    'name' => $row->product_name,
                    'months' => array()
                );
            }
            // Ensure month is properly formatted
            $month_formatted = sprintf('%02d', $row->in_month);
            $products_data[$row->product_id]['months'][$month_formatted] = $row->quantity;
        }

        // Create an email message with the saved data
        $admin_subject = 'Customer has saved a Forecast Sheet';

        // Start the HTML table
        $email_message = '<html><body>';
        $email_message .= '<h2>A Customer has sent an order: ' . $user_name . ', for year: ' . $selectedYear . '</h2>';
        if (!empty($poNumber)) {
            $email_message .= '<h4>PO Number: ' . $poNumber . '</h4>';
        };
        
        // Create a table with months as columns
        $email_message .= '<table border="1">';
        $email_message .= '<tr><th>Product ID</th><th>Product Name</th><th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th><th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th><th>Total</th></tr>';

        foreach ($products_data as $product_id => $product_data) {
            $email_message .= '<tr>';
            $email_message .= '<td class="product__id">' . $product_id . '</td>';
            $email_message .= '<td>' . $product_data['name'] . '</td>';
            
            // Initialize total for this product
            $product_total = 0;
            
            // Add quantities for each month
            for ($m = 1; $m <= 12; $m++) {
                $month = sprintf('%02d', $m);
                $qty = isset($product_data['months'][$month]) ? $product_data['months'][$month] : 0;
                $email_message .= '<td>' . $qty . '</td>';
                $product_total += $qty; // Add to product total
            }
            
            // Add the total column
            $email_message .= '<td style="font-weight:bold; background-color:#f9f9f9;">' . $product_total . '</td>';
            
            $email_message .= '</tr>';
        }

        // End the HTML table and email message
        $email_message .= '</table>';
        $email_message .= '</body></html>';

        // Send email to the admin
        $admin_emails = array(
            get_option('admin_email'), // Admin's email
            'orders@axichem.com.au',
        );
        wp_mail($admin_emails, $admin_subject, $email_message, array('Content-Type: text/html'));
    }
    
    $response_message = $isSubmitToAxichem ? 'Forecast has been saved and sent to Axichem.' : 'Forecast has been saved.';
    wp_send_json_success(array('message' => $response_message));
    wp_die();
}
add_action('wp_ajax_save_forecast_data', 'save_forecast_data');