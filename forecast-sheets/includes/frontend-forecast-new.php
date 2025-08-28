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
        'forecast-help'         => __('Forecast Help', 'woocommerce'),
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
    $selectedYear = '2024';   // Default to the current year (e.g., '2024')

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

                // Loop through each month for this product
                foreach ($month_quantities as $month => $quantity) {
                    $month = sanitize_text_field($month);
                    $quantity = intval($quantity);

                    // Skip processing if quantity is 0
                    if ($quantity <= 0) {
                        continue;
                    }

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
        $selectedMonth = isset($_POST['selected_email_month']) ? sanitize_text_field($_POST['selected_email_month']) : '01';

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

            // First, save all the data
            foreach ($product_quantities as $product_id => $month_quantities) {
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : '';
                $product_id = sanitize_text_field($product_id);
                $product_name = sanitize_text_field($product_name);

                // Loop through each month for this product
                foreach ($month_quantities as $month => $quantity) {
                    $month = sanitize_text_field($month);
                    $quantity = intval($quantity);

                    // Skip processing if quantity is 0
                    if ($quantity <= 0) {
                        continue;
                    }

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

            // Gather the saved data for the selected month for email
            $data_query = $wpdb->prepare(
                "SELECT * FROM $user_table_name WHERE user_id = %d AND in_year = %s AND in_month = %s AND quantity > 0 ORDER BY product_name",
                $user_id,
                $selectedYear,
                $selectedMonth
            );

            $data = $wpdb->get_results($data_query);

            // Define an array to map month numbers to month names
            $monthNames = [
                '01' => 'January',
                '02' => 'February',
                '03' => 'March',
                '04' => 'April',
                '05' => 'May',
                '06' => 'June',
                '07' => 'July',
                '08' => 'August',
                '09' => 'September',
                '10' => 'October',
                '11' => 'November',
                '12' => 'December',
            ];

            // Create an email message with the saved data and selected month
            $admin_subject = 'Customer has saved a Forecast Sheet';

            // Start the HTML table
            $email_message = '<html><body>';
            $email_message .= '<h2>A Customer has sent an order: ' . $user_name . ', for period: ' . $monthNames[$selectedMonth] . ' ' . $selectedYear . '</h2>';
            if (!empty($poNumber)) {
                $email_message .= '<h4>PO Number: ' . $poNumber . '</h4>';
            };
            $email_message .= '<table border="1">';
            $email_message .= '<tr><th>Product ID</th><th>Product Name</th><th>Quantity</th></tr>';

            foreach ($data as $row) {
                $email_message .= '<tr>';
                $email_message .= '<td class="product__id">' . $row->product_id . '</td>';
                $email_message .= '<td>' . $row->product_name . '</td>';
                $email_message .= '<td>' . $row->quantity . '</td>';
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
            
            echo '<p class="sheet-saved">Forecast has been saved and sent to Axichem.</p>';
        }
    }

    // 3. Page Content
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'order' => 'ASC',
        'orderby' => 'title'
    );
    $query = new WP_Query($args);

?>
    <div class="forecast_header">
        <h3>Forecast Sheet</h3>
        <div>
            <select id="year" name="selectedYear">
                <option value="2024" selected>2024</option>
                <option value="2025">2025</option>
                <option value="2026">2026</option>
            </select>
        </div>
    </div>

    <div id="loadingMessage" style="display: none;">
        Fetching data...
    </div>

    <div class="forecast_body axichem-form">
        <form id="ForecastForm" method="post" action="">
            <table id="myTable" class="display responsive" style="width:100%">
                <thead>
                    <tr>
                        <th class="product__id desktop" style="text-align:center">Product ID</th>
                        <th data-priority="1">Product Name</th>
                        <th data-priority="2" style="text-align:center">Jan</th>
                        <th data-priority="2" style="text-align:center">Feb</th>
                        <th data-priority="2" style="text-align:center">Mar</th>
                        <th data-priority="2" style="text-align:center">Apr</th>
                        <th data-priority="2" style="text-align:center">May</th>
                        <th data-priority="2" style="text-align:center">Jun</th>
                        <th data-priority="2" style="text-align:center">Jul</th>
                        <th data-priority="2" style="text-align:center">Aug</th>
                        <th data-priority="2" style="text-align:center">Sep</th>
                        <th data-priority="2" style="text-align:center">Oct</th>
                        <th data-priority="2" style="text-align:center">Nov</th>
                        <th data-priority="2" style="text-align:center">Dec</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($query->have_posts()) {
                        $query->the_post();
                        $product_id = get_the_ID();
                    ?>
                        <tr product-id="<?php echo $product_id; ?>">
                            <td class="product__id" style="text-align:center"><?php echo $product_id; ?></td>
                            <td class="product__name"><?php the_title(); ?></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][01]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][02]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][03]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][04]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][05]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][06]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][07]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][08]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][09]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][10]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][11]" value="0" min="0"></td>
                            <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[<?php echo $product_id; ?>][12]" value="0" min="0"></td>
                        </tr>
                    <?php }
                    ?>
                </tbody>
            </table>
            <div class="forecast-buttons">
                <input type="hidden" name="selectedYear" value="<?php echo esc_attr($selectedYear); ?>">
                <input id="saveButton" type="submit" name="save_form" value="Save"></input>
                <button id="export-button">Download Spreadsheet</button>
            </div>
            <hr class="forecast-divider" />
            <p style="margin:0; font-weight:400">Ready to order? Select a month, add a PO number if required (if submitting without a PO leave this blank), then click 'Send to Axichem'.<br>Your Axichem rep will be in touch to confirm your order.</p>
            <div class="forecast-buttons">
                <select name="selected_email_month">
                    <option value="01">January</option>
                    <option value="02">February</option>
                    <option value="03">March</option>
                    <option value="04">April</option>
                    <option value="05">May</option>
                    <option value="06">June</option>
                    <option value="07">July</option>
                    <option value="08">August</option>
                    <option value="09">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
                <input type="text" name="poNumber" value="" placeholder="PO Number (optional)">
                <input class="forecast-buttons__submit" type="submit" name="submit_form" value="Send to Axichem">
            </div>
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
                        const headers = ['Product ID', 'Product Name', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                        const table = document.getElementById('myTable');
                        const rows = Array.from(table.querySelectorAll('tr'));

                        // Get username
                        const userName = document.querySelector('.ast-username strong').innerHTML;

                        // Get the selected year element
                        const select = document.getElementById('year');
                        const selectedOption = select.options[select.selectedIndex];
                        const selectedYearName = selectedOption.text;

                        // Filter rows to include only those with at least one quantity > 0
                        const filteredRows = rows.filter(row => {
                            const quantityInputs = row.querySelectorAll('td input[type="number"]');
                            if (quantityInputs && quantityInputs.length > 0) {
                                let hasValue = false;
                                quantityInputs.forEach(input => {
                                    const quantityValue = parseInt(input.value, 10);
                                    if (!isNaN(quantityValue) && quantityValue > 0) {
                                        hasValue = true;
                                    }
                                });
                                return hasValue;
                            }
                            return false;
                        });

                        // Extract filtered table data and format as CSV
                        const data = [];

                        // Add the headers as the first row
                        data.push(headers.join(','));

                        // Add the data rows
                        filteredRows.forEach(row => {
                            const columns = Array.from(row.querySelectorAll('td'));
                            const productId = columns[0].textContent;
                            const productName = columns[1].textContent;
                            const quantities = [];
                            
                            // Get all quantity inputs (12 months)
                            for (let i = 2; i < columns.length; i++) {
                                const input = columns[i].querySelector('input[type="number"]');
                                quantities.push(input ? input.value : '0');
                            }
                            
                            data.push([productId, productName, ...quantities].join(','));
                        });

                        // Create a Blob containing the CSV data
                        const blob = new Blob([data.join('\n')], {
                            type: 'text/csv'
                        });

                        // Create a download link
                        const a = document.createElement('a');
                        a.href = window.URL.createObjectURL(blob);
                        a.download = 'Annual_Forecast_' + selectedYearName + '_' + userName + '.csv';

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

                // Add an event listener to detect changes in the select input
                jQuery(document).on('change', '#year', function() {
                    // Update year when changing
                    var selectedYear = jQuery('#year').val();
                    jQuery('input[name="selectedYear"]').val(selectedYear);
                    jQuery(".sheet-saved").hide();
                });
            });
        </script>
    </div>
<?php
}
add_action('woocommerce_account_forecast-sheet_endpoint', 'custom_account_tab_content');

// New function to populate all product quantities for all months in a year
function populate_all_product_quantities()
{
    global $wpdb;

    $user_id = get_current_user_id();
    $selectedYear = sanitize_text_field($_POST['selectedYear']);
    $user_table_name = $wpdb->prefix . 'forecast_sheets';

    // Fetch data from the database for the current user and year (all months)
    $data_query = $wpdb->prepare(
        "SELECT product_id, in_month, quantity FROM $user_table_name WHERE user_id = %d AND in_year = %s",
        $user_id,
        $selectedYear
    );

    $data = $wpdb->get_results($data_query);

    // Create a nested associative array to store product quantities for all months
    $product_quantities = array();

    foreach ($data as $row) {
        $product_id = $row->product_id;
        $month = $row->in_month;
        $quantity = $row->quantity;
        
        // Initialize the product array if it doesn't exist
        if (!isset($product_quantities[$product_id])) {
            $product_quantities[$product_id] = array();
        }
        
        // Store the quantity for this product and month
        $product_quantities[$product_id][$month] = $quantity;
    }

    // Return the product quantities as a JSON object
    wp_send_json($product_quantities);
}

// Hook this function to an AJAX action
add_action('wp_ajax_populate_all_product_quantities', 'populate_all_product_quantities');
add_action('wp_ajax_nopriv_populate_all_product_quantities', 'populate_all_product_quantities');

// Keep the original populate_product_quantities function for backward compatibility
function populate_product_quantities()
{
    global $wpdb;

    $user_id = get_current_user_id();
    $selectedMonth = sanitize_text_field($_POST['selectedMonth']);
    $selectedYear = sanitize_text_field($_POST['selectedYear']);
    $user_table_name = $wpdb->prefix . 'forecast_sheets';

    // Fetch data from the database for the current user, month, and year
    $data_query = $wpdb->prepare(
        "SELECT product_id, quantity FROM $user_table_name WHERE user_id = %d AND in_month = %s AND in_year = %s",
        $user_id,
        $selectedMonth,
        $selectedYear
    );

    $data = $wpdb->get_results($data_query);

    // Create an associative array to store product quantities
    $product_quantities = array();

    foreach ($data as $row) {
        $product_id = $row->product_id;
        $quantity = $row->quantity;
        $product_quantities[$product_id] = $quantity;
    }

    // Return the product quantities as a JSON object
    wp_send_json($product_quantities);
}

// Hook this function to an AJAX action
add_action('wp_ajax_populate_product_quantities', 'populate_product_quantities');
add_action('wp_ajax_nopriv_populate_product_quantities', 'populate_product_quantities');
