<?php

// Add Forecast tab to My Account page
function add_custom_account_tab($items)
{
    $items['forecast-sheet'] = 'Forecast Sheets';
    return $items;
}
add_filter('woocommerce_account_menu_items', 'add_custom_account_tab', 10, 1);

// Define Forecasts Sheets endpoint
function forecast_endpoint()
{
    add_rewrite_endpoint('forecast-sheet', EP_ROOT | EP_PAGES);
}
add_action('init', 'forecast_endpoint');

// // Reorder account items
function reorder_account_menu($items)
{
    return array(
        'dashboard'          => __('Dashboard', 'woocommerce'),
        'forecast-sheet'     => __('Forecast Sheets', 'woocommerce'),
        'price-list'         => __('Price List', 'woocommerce'),
        'forecast-instructions' => __('Instructions', 'woocommerce'),
        'forecast-help'      => __('Forecast Help', 'woocommerce'),
        'edit-account'       => __('Edit Account', 'woocommerce'),
        'edit-address'       => __('Addresses', 'woocommerce'),
        'customer-logout'    => __('Logout', 'woocommerce'),
    );
}
add_filter('woocommerce_account_menu_items', 'reorder_account_menu');

// Display Global Customer Message
function customerMessage()
{
    // Retrieve the saved message from the WordPress database
    $message_customers = get_option('message_customers');
    if (is_user_logged_in() && !empty($message_customers)) {
        echo '<div class="customers-message"><h4>Message from Axichem</h4><p>' . esc_html($message_customers) . '</p><div class="closeMessage"><svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 384 512"><!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg></div></div>';
    }
}
add_shortcode('customerMessage', 'customerMessage');

// Display content for Forecast tab 
function custom_account_tab_content()
{
    $selectedYear = date('Y');   // Default to the current year

    // 1. Save the form
    if (isset($_POST['save_form'])) {
        $user_id = get_current_user_id();
        $user_name = wp_get_current_user()->user_login;
        $product_quantities = $_POST['product_quantity'];

        if (is_array($product_quantities)) {
            global $wpdb;
            $selectedYear = sanitize_text_field($_POST['selectedYear']);

            $user_table_name = $wpdb->prefix . 'forecast_sheets';
            $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';

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

            foreach ($product_quantities as $product_id => $month_quantities) {
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : '';
                $product_id = sanitize_text_field($product_id);
                $product_name = sanitize_text_field($product_name);

                foreach ($month_quantities as $month => $quantity) {
                    $quantity = intval($quantity);
                    
                    $existing_user_row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM $user_table_name WHERE user_id = %d AND product_id = %s AND in_month = %s AND in_year = %s",
                            $user_id,
                            $product_id,
                            $month,
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
                                'in_month' => $month
                            )
                        );
                    }

                    // Update the totals table
                    $existing_total_row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM $total_table_name WHERE product_id = %s AND in_month = %s AND in_year = %s",
                            $product_id,
                            $month,
                            $selectedYear
                        )
                    );

                    // Calculate the total quantity for this month and year
                    $total_quantity = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT SUM(quantity) FROM $user_table_name WHERE product_id = %s AND in_month = %s AND in_year = %s",
                            $product_id,
                            $month,
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
                                'in_month' => $month
                            ),
                            array(
                                'product_id' => $product_id,
                                'in_year' => $selectedYear,
                                'in_month' => $month
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
                                'in_month' => $month
                            )
                        );
                    }
                }
            }

            echo '<p class="sheet-saved">Forecast has been saved.</p>';
        }
    }



    // 2. Send to Axichem
    if (isset($_POST['submit_form'])) {
        $user_id = get_current_user_id();
        $user_name = wp_get_current_user()->user_login;
        $product_quantities = $_POST['product_quantity'];

        if (is_array($product_quantities)) {
            global $wpdb;
            $selectedYear = sanitize_text_field($_POST['selectedYear']);
            $poNumber = sanitize_text_field($_POST['poNumber']);

            $user_table_name = $wpdb->prefix . 'forecast_sheets';
            $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';

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

            foreach ($product_quantities as $product_id => $month_quantities) {
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : '';
                $product_id = sanitize_text_field($product_id);
                $product_name = sanitize_text_field($product_name);

                foreach ($month_quantities as $month => $quantity) {
                    $quantity = intval($quantity);
                    
                    $existing_user_row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM $user_table_name WHERE user_id = %d AND product_id = %s AND in_month = %s AND in_year = %s",
                            $user_id,
                            $product_id,
                            $month,
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
                                'in_month' => $month
                            )
                        );
                    }

                    // Update the totals table
                    $existing_total_row = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM $total_table_name WHERE product_id = %s AND in_month = %s AND in_year = %s",
                            $product_id,
                            $month,
                            $selectedYear
                        )
                    );

                    // Calculate the total quantity for this month and year
                    $total_quantity = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT SUM(quantity) FROM $user_table_name WHERE product_id = %s AND in_month = %s AND in_year = %s",
                            $product_id,
                            $month,
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
                                'in_month' => $month
                            ),
                            array(
                                'product_id' => $product_id,
                                'in_year' => $selectedYear,
                                'in_month' => $month
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
                                'in_month' => $month
                            )
                        );
                    }
                }
            }

            echo '<p class="sheet-saved">Forecast has been saved and sent to Axichem.</p>';
        }

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
            $products_data[$row->product_id]['months'][$row->in_month] = $row->quantity;
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

    // 3. Page Content
    
    // No longer loading all products automatically
    // Products will be added by the user via the product search

?>
    <div class="forecast_header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background-color: #f9fbff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <h3 style="margin: 0; font-size: 24px; color: #333; font-weight: 600;">Forecast Sheet</h3>
        <div style="display: flex; align-items: center;">
            <label for="year" style="margin-right: 10px; font-weight: 500; color: #555;">Select Year:</label>
            <select id="year" name="selectedYear" style="padding: 8px 12px; border-radius: 6px; border: 1px solid #ddd; background-color: white; min-width: 100px; font-size: 15px;">
                <?php
                $currentYear = date('Y');
                $startYear = 2024; // Start from 2024
                $endYear = $currentYear + 3; // Show current year plus 3 years ahead
                
                for ($year = $startYear; $year <= $endYear; $year++) {
                    $selected = ($year == $selectedYear) ? 'selected' : '';
                    echo "<option value=\"{$year}\" {$selected}>{$year}</option>";
                }
                ?>
            </select>
        </div>
    </div>

    <div id="loadingMessage" style="display: none; text-align: center; padding: 20px; background-color: #f9fbff; border-radius: 8px; margin-bottom: 20px;">
        <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(0,115,170,0.3); border-radius: 50%; border-top-color: #0073aa; animation: spin 1s ease-in-out infinite; margin-right: 10px; vertical-align: middle;"></div>
        <span style="vertical-align: middle; font-weight: 500; color: #555;">Fetching data...</span>
    </div>
    
    <!-- Product Search Section -->
    <div class="product-search-container">
        <h4 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #333;">Add Products to Your Forecast</h4>
        <p style="margin-bottom: 15px; color: #555; font-size: 14px;">Start typing to see product suggestions or click "Browse All Products" to view the complete list.</p>
        <div class="product-search-form" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
            <div style="position: relative; flex-grow: 1; max-width: 600px;">
                <label for="product-search" style="display: block; margin-bottom: 8px; font-weight: 600; color: #444; font-size: 14px;">Search Products</label>
                <div style="display: flex; position: relative;">
                    <input type="text" id="product-search" placeholder="Start typing to search products..." style="width: 100%; padding: 10px 12px; font-size: 14px; border-radius: 6px 0 0 6px; border: 1px solid #ddd; border-right: none;">
                    <button type="button" id="search-button" style="padding: 10px 15px; border-radius: 0 6px 6px 0; background-color: #f5f7f9; border: 1px solid #ddd; color: #333; font-weight: 600;">Search</button>
                </div>
                <!-- Separate container for suggestions dropdown with fixed positioning -->
                <div id="product-suggestions-container" style="position: relative; width: 100%;">
                    <div id="product-suggestions" style="position: absolute; width: 100%; background: white; border: 1px solid #ddd; z-index: 9999; box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: none; max-height: 300px; overflow-y: auto; border-radius: 0 0 6px 6px;"></div>
                </div>
            </div>
            <div>
                <button type="button" id="browse-all-button" style="padding: 10px 15px; background-color: #f5f7f9; border: 1px solid #ddd; border-radius: 6px; font-weight: 600; color: #333;">Browse All Products</button>
            </div>
        </div>
        <div id="search-results" style="margin-top: 15px; display: none; background-color: white; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background-color: #f5f7f9; border-bottom: 1px solid #eaedf0;">
                <h5 style="margin: 0; font-size: 16px; color: #333;">Search Results</h5>
                <button id="close-search-results" type="button" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s;">&times;</button>
            </div>
            <div id="results-container" style="max-height: 300px; overflow-y: auto; padding: 15px;"></div>
        </div>
    </div>    <!-- Custom CSS for table width and responsiveness -->
    <style>
        /* Notification styling */
        .sheet-saved {
            background-color: #e7f7e9;
            border-left: 4px solid #46b450;
            color: #2e7d32;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            animation: fadeInUp 0.4s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Modern styling for the forecast table */
        .forecast_body {
            max-width: 100%;
            overflow-x: auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        
        #myTable {
            min-width: 100%;
            table-layout: auto;
            margin-bottom: 20px;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        #myTable thead {
            background-color: #f5f7f9;
        }
        
        #myTable thead th {
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #333;
            border-bottom: 2px solid #eaedf0;
            position: sticky;
            top: 0;
            background-color: #f5f7f9;
            z-index: 10;
            text-transform: uppercase;
            font-size: 12px;
        }
        
        #myTable tbody tr {
            transition: background-color 0.2s ease;
        }
        
        #myTable tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        
        #myTable tbody tr:hover {
            background-color: #f0f7ff;
        }
        
        #myTable .product__name {
            min-width: 220px;
            max-width: 300px;
            font-weight: 500;
        }
        
        #myTable .product__id {
            width: 80px;
            color: #666;
        }
        
        #myTable .product__qty {
            width: 60px;
            min-width: 60px;
        }
        
        #myTable input.data-quantity {
            width: 100%;
            min-width: 45px;
            text-align: center;
            padding: 8px 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        #myTable input.data-quantity:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
            outline: none;
        }
        
        #myTable .product__total {
            background-color: #f2f9ff;
            font-weight: bold;
            color: #0073aa;
        }
        
        /* Make sure all month columns have equal width */
        #myTable th, #myTable td {
            white-space: nowrap;
            padding: 10px 8px;
            border-bottom: 1px solid #eaedf0;
        }
        
        /* Button styling */
        #myTable .remove-product {
            background: none;
            border: none;
            color: #ff5555;
            cursor: pointer;
            transition: transform 0.2s, color 0.2s;
            opacity: 0.7;
        }
        
        #myTable .remove-product:hover {
            color: #ff0000;
            transform: scale(1.2);
            opacity: 1;
        }
        /* Improve responsive behavior */
        @media screen and (max-width: 1200px) {
            .forecast_body {
                overflow-x: scroll;
            }
            #myTable {
                min-width: 1200px;
            }
        }
        
        /* Style adjustments for the DataTables wrapper */
        .dataTables_wrapper {
            width: 100%;
            overflow-x: auto;
            padding-bottom: 15px;
            border-radius: 8px;
            background-color: #fff;
        }
        
        .dataTables_scrollHead {
            background-color: #f5f7f9;
            border-radius: 8px 8px 0 0;
        }
        
        /* Better styling for number inputs */
        input.data-quantity[type="number"] {
            -moz-appearance: textfield; /* Firefox */
        }
        
        input.data-quantity[type="number"]::-webkit-inner-spin-button,
        input.data-quantity[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        /* Fix for DataTables horizontal scrolling */
        .dataTables_scrollBody {
            overflow-x: visible !important;
            overflow-y: auto !important;
            border-radius: 0 0 8px 8px;
            border: 1px solid #eaedf0;
            border-top: none;
        }
        
        /* Empty state message styling */
        #no-products-message td {
            padding: 40px;
            text-align: center;
            background-color: #f9fbff;
            font-size: 15px;
            color: #555;
            border-radius: 8px;
        }
        
        /* Search container styling */
        .product-search-container {
            background-color: #f9fbff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        /* Product suggestions styling */
        #product-suggestions {
            border-radius: 0 0 4px 4px;
            z-index: 9999;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background-color: white;
            min-width: 250px;
            border: 1px solid #ddd;
        }
        
        .product-suggestion {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .product-suggestion:hover {
            background-color: #f0f7ff;
        }
        
        .product-suggestion:last-child {
            border-bottom: none;
        }
        
        .suggestion-highlighted {
            background-color: #f0f7ff;
            border-left: 3px solid #0073aa;
            padding-left: 7px !important;
        }
        
        .suggestions-header {
            padding: 5px 10px;
            background-color: #f5f5f5;
            font-size: 12px;
            color: #666;
            border-bottom: 1px solid #ddd;
        }
        
        /* Spinner animation for loading indicators */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Button styles */
        button, 
        input[type="button"], 
        input[type="submit"],
        .add-product-button {
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background-color: #0073aa;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        button:hover, 
        input[type="button"]:hover, 
        input[type="submit"]:hover,
        .add-product-button:hover:not([disabled]) {
            background-color: #005d87;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }
        
        button:focus, 
        input[type="button"]:focus, 
        input[type="submit"]:focus,
        .add-product-button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.3);
        }
        
        .add-product-button[disabled] {
            background-color: #ccc;
            cursor: default;
            box-shadow: none;
            transform: none;
        }
        
        /* Button variations */
        #browse-all-button,
        #search-button {
            background-color: #f5f7f9;
            color: #333;
            border: 1px solid #ddd;
        }
        
        #browse-all-button:hover,
        #search-button:hover {
            background-color: #e9ecef;
            color: #0073aa;
        }
        
        #export-button {
            background-color: #46b450;
            color: white;
        }
        
        #export-button:hover {
            background-color: #389e41;
            box-shadow: 0 4px 8px rgba(0, 115, 50, 0.2);
        }
        
        .forecast-buttons__submit {
            background-color: #0073aa;
            color: white;
            font-weight: 600;
        }
    </style>

    <div class="forecast_body axichem-form">
        <form id="ForecastForm">
            <table id="myTable" class="display responsive" style="width:100%">
                <thead>
                    <tr>
                        <th class="product__id desktop" style="text-align:center; min-width:80px;">Product ID</th>
                        <th data-priority="1" style="min-width:250px;">Product Name</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Jan</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Feb</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Mar</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Apr</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">May</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Jun</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Jul</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Aug</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Sep</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Oct</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Nov</th>
                        <th data-priority="2" style="text-align:center; min-width:45px;">Dec</th>
                        <th data-priority="1" style="text-align:center; min-width:70px; background-color: #f9f9f9;">Total</th>
                        <th data-priority="1" style="text-align:center; min-width:30px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Products will be added here dynamically when selected by the user -->
                    <tr id="no-products-message">
                        <td colspan="16" style="text-align:center; padding: 20px;">
                            <div style="padding: 40px 20px; background-color: #f9fbff; border-radius: 8px; box-shadow: inset 0 0 0 1px #eaedf0;">
                              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#0073aa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 15px; opacity: 0.7;">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                              </svg>
                              <div style="font-size: 20px; margin-bottom: 10px; color: #444; font-weight: 500;">No products added yet</div>
                              <div style="font-size: 15px; color: #666; margin-bottom: 20px; max-width: 400px; margin-left: auto; margin-right: auto;">Use the search above to find and add products to your forecast</div>
                              <button type="button" id="browse-products-button" style="padding: 10px 20px; background-color: #0073aa; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s;">Browse Products</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="forecast-buttons">
                <input type="hidden" name="selectedYear" value="<?php echo esc_attr($selectedYear); ?>">
                <button id="saveButton" type="button">Save</button>
                <button id="export-button" type="button">Download Spreadsheet</button>
            </div>
            <hr class="forecast-divider" />
            <p style="margin:0; font-weight:400">Ready to order? Add a PO number if required, if submitting without a PO leave this blank, then click ‘Send to Axichem’.<br>Your Axichem rep will be in touch to confirm your order.</p>
            <div class="forecast-buttons">
                <input type="text" name="poNumber" value="" placeholder="PO Number (optional)">
                <button type="button" class="forecast-buttons__submit">Send to Axichem</button>
            </div>
            <div class="sheet-saved" style="display:none;"></div>
        </form>
        <!-- Export Data with Javascript -->
        <script>
            jQuery(document).ready(function() {
                let isExporting = false;

                // Attach a click event to the export button
                jQuery('#export-button').click(function(event) {
                    if (!isExporting) {
                        isExporting = true;

                        // Create headers for the CSV
                        const headers = ['Product ID', 'Product Name', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'];

                        const table = document.getElementById('myTable');
                        const rows = Array.from(table.querySelectorAll('tbody tr:not(#no-products-message)'));

                        // Get username
                        const userName = document.querySelector('.ast-username strong').innerHTML;

                        // Get the selected year element
                        const select = document.getElementById('year');
                        const selectedYearName = select.options[select.selectedIndex].text;

                        // Filter rows to include only those with at least one quantity > 0
                        const filteredRows = rows.filter(row => {
                            const quantityInputs = row.querySelectorAll('input[type="number"]');
                            let hasQuantity = false;
                            quantityInputs.forEach(input => {
                                const quantityValue = parseInt(input.value, 10);
                                if (!isNaN(quantityValue) && quantityValue > 0) {
                                    hasQuantity = true;
                                }
                            });
                            return hasQuantity;
                        });

                        // Extract filtered table data and format as CSV
                        const data = [];

                        // Add the headers as the first row
                        data.push(headers.join(','));

                        // Add the data rows
                        filteredRows.forEach(row => {
                            const productId = row.querySelector('.product__id').textContent;
                            const productName = row.querySelector('.product__name').textContent;
                            const quantities = Array.from(row.querySelectorAll('input[type="number"]')).map(input => input.value);
                            const total = row.querySelector('.product__total').textContent;
                            
                            data.push([productId, '"' + productName + '"', ...quantities, total].join(','));
                        });

                        // Create a Blob containing the CSV data
                        const blob = new Blob([data.join('\n')], {
                            type: 'text/csv'
                        });

                        // Create a download link
                        const a = document.createElement('a');
                        a.href = window.URL.createObjectURL(blob);
                        a.download = 'Forecast_' + selectedYearName + '_' + userName + '.csv';

                        // Programmatically click the link to trigger the download
                        a.click();

                        // Reset the export flag after a brief delay to allow time for download
                        setTimeout(function() {
                            isExporting = false;
                        }, 1000);

                        // Prevent the default behavior of the export button click to avoid page reload
                        event.preventDefault();
                    }
                });

                // Add an event listener to detect changes in the select input for year
                jQuery(document).on('change', '#year', function() {
                    // Year change is handled by the JS file
                });
            });
        </script>
    </div>
<?php
}
add_action('woocommerce_account_forecast-sheet_endpoint', 'custom_account_tab_content');

// The populate_all_product_quantities function is now defined in the main plugin file
// to avoid function redeclaration issues
