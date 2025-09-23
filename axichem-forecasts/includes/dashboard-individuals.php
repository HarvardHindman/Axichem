<?php

function enqueue_custom_styles()
{
    wp_enqueue_style('axichem-forecast-css', plugin_dir_url(__FILE__) . '/css/lionandlamb-axichem.css');
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'enqueue_custom_styles');

// ADD USER IDS PAGE
function display_user_ids_page()
{
    ob_start();
    global $wpdb;

    // Default to current year if no year is selected
    $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

    // Query to retrieve unique user IDs for the selected year
    $table_name = $wpdb->prefix . "forecast_sheets"; // Construct table name
    $query = $wpdb->prepare("
        SELECT DISTINCT user_id, user_name
        FROM $table_name
        WHERE in_year = %s
    ", $selectedYear);

    $results = $wpdb->get_results($query);

?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Forecast Sheets per Customer</h1>
        <hr class="wp-header-end" />
        
        <div style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px;">
            <label for="year"><strong>Select Year:</strong></label>
            <select id="year" name="selectYear">
                <?php
                // Generate options for years
                $years = array('2024', '2025', '2026');
                foreach ($years as $year) {
                    $selected = ($year === $selectedYear) ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
        </div>

        <h2>Forecasts for year <?php echo $selectedYear; ?></h2>

        <div id="dataContainer">
            <div id="loadingMessage" style="display: none;">
                Fetching data...
            </div>
        </div>
        <?php
        if (!empty($results)) {
            echo '<ul>';
            echo '<table class="wp-list-table widefat fixed striped table-view-list pages" style="border-collapse: collapse; width: 100%;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px;">Customer</th>';
            echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; width: 150px; text-align: center;">Forecast</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($results as $row) {
                $user_id = esc_html($row->user_id);
                $user = get_user_by('ID', $user_id);
                $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);
                echo '<tr>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($business) . ' - ' . esc_html($user->first_name) . ' ' . esc_html($user->last_name) . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;"><a class="button button-primary" href="?page=user-specific-data&user_id=' . $user_id . '&year=' . $selectedYear . '">View Forecast</a></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="notice notice-error">No Forecasts found for this year.</p>';
        }

        echo '</div>';
        ?>

        <script>
            jQuery(document).ready(function($) {
                // Function to update the URL with year parameter
                function updateURL() {
                    var selectedYear = $('#year').val();
                    window.location.href = '?page=user-ids&year=' + selectedYear;
                }

                // Add change event listener to the year dropdown
                $('#year').on('change', function() {
                    updateURL();
                });

                // After page load, reselect the year based on the URL parameter
                var urlParams = new URLSearchParams(window.location.search);
                var selectedYearFromURL = urlParams.get('year');
                if (selectedYearFromURL) {
                    $('#year').val(selectedYearFromURL);
                } else {
                    // If no year in URL, default to current year
                    var currentYear = new Date().getFullYear().toString();
                    $('#year').val(currentYear);
                }
            });
        </script>
        <?php
        ob_end_flush();
    }

    // Hook this function to create your custom dashboard page
    add_action('admin_menu', 'add_user_ids_dashboard_page');
    function add_user_ids_dashboard_page()
    {
        add_menu_page(
            'Forecast Sheets',
            'Forecast Sheets',
            'read',
            'user-ids',
            'display_user_ids_page',
            'dashicons-media-spreadsheet',
            25
        );
    }

    // ADD SINGLE USER ID PAGE
    function display_user_specific_data_page()
    {
        ob_start();
        global $wpdb;

        if (isset($_GET['user_id'])) {
            $user_id = sanitize_text_field($_GET['user_id']);
            $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

            // Query to retrieve user name for the selected user_id
            $table_name = $wpdb->prefix . "forecast_sheets"; // Construct table name
            $user_query = $wpdb->prepare("
                SELECT user_name
                FROM $table_name
                WHERE user_id = %s
                AND in_year = %s
                LIMIT 1
            ", $user_id, $selectedYear);

            $user_name = $wpdb->get_var($user_query);

            // Get business name
            $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);
            $user = get_user_by('ID', $user_id);

            echo '<div class="wrap">';
            echo '<h2>Forecast for customer: ' . esc_html($business) . '</h2>';
            
            // Filter controls
            ?>
            <div style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="user-specific-data">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                    
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                        <div>
                            <label for="filter_type"><strong>View by:</strong></label>
                            <select id="filter_type" name="filter_type">
                                <option value="year" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : 'year', 'year'); ?>>Year</option>
                                <option value="quarter" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'quarter'); ?>>Quarter</option>
                                <option value="month" <?php selected(isset($_GET['filter_type']) ? $_GET['filter_type'] : '', 'month'); ?>>Month</option>
                            </select>
                        </div>
                        <div id="month-filter" style="<?php echo (!isset($_GET['filter_type']) || $_GET['filter_type'] !== 'month') ? 'display:none;' : ''; ?>">
                            <label for="month"><strong>Month:</strong></label>
                            <select id="month" name="month">
                                <?php
                                $months = array(
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
                                    '12' => 'December'
                                );
                                $current_month = date('m');
                                $selected_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
                                foreach ($months as $month_code => $month_name) {
                                    $selected = ($month_code === $selected_month) ? 'selected' : '';
                                    echo "<option value='$month_code' $selected>$month_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div id="quarter-filter" style="<?php echo (!isset($_GET['filter_type']) || $_GET['filter_type'] !== 'quarter') ? 'display:none;' : ''; ?>">
                            <label for="quarter"><strong>Quarter:</strong></label>
                            <select id="quarter" name="quarter">
                                <?php
                                $quarter_options = [
                                    'Q1' => 'Q1 (Jan - Mar)',
                                    'Q2' => 'Q2 (Apr - Jun)',
                                    'Q3' => 'Q3 (Jul - Sep)',
                                    'Q4' => 'Q4 (Oct - Dec)',
                                ];
                                $current_month = date('m');
                                $current_quarter = 'Q' . ceil(intval($current_month) / 3);
                                $selected_quarter = isset($_GET['quarter']) ? $_GET['quarter'] : $current_quarter;
                                foreach ($quarter_options as $quarter_code => $quarter_name) {
                                    $selected = ($quarter_code === $selected_quarter) ? 'selected' : '';
                                    echo "<option value='$quarter_code' $selected>$quarter_name</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div id="year-filter">
                            <label for="year"><strong>Year:</strong></label>
                            <select id="year" name="year">
                                <?php
                                $current_year = date('Y');
                                $years = array('2024', '2025', '2026');
                                foreach ($years as $year) {
                                    $selected = ($year === $selectedYear) ? 'selected' : '';
                                    echo "<option value='$year' $selected>$year</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="button button-primary">Apply Filters</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php

            // Only keep the year view logic below
            $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : date('Y');
            $table_name = $wpdb->prefix . "forecast_sheets";
            $query = $wpdb->prepare("
                SELECT
                    user_id,
                    user_name,
                    product_id,
                    product_name,
                    in_month,
                    quantity
                FROM $table_name
                WHERE user_id = %s
                AND in_year = %s
                AND quantity > 0
                ORDER BY product_name ASC, in_month ASC
            ", $user_id, $selectedYear);
            $results = $wpdb->get_results($query);
            $period_label = "Year " . $selectedYear;
            if (!empty($results)) {
                // Organize data by product and month
                $products = array();
                foreach ($results as $row) {
                    $product_id = $row->product_id;
                    $month = $row->in_month;
                    
                    if (!isset($products[$product_id])) {
                        $products[$product_id] = array(
                            'name' => $row->product_name,
                            'months' => array()
                        );
                    }
                    
                    $products[$product_id]['months'][$month] = $row->quantity;
                }
                
                echo '<h3>Forecast for: ' . esc_html($period_label) . '</h3>';
                echo '<table class="widefat" style="margin: 20px 0; border-collapse: collapse; width: 100%;">';
                echo '<thead>';
                echo '<tr>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px;">Product ID</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px;">Product Name</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Jan</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Feb</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Mar</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Apr</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">May</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Jun</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Jul</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Aug</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Sep</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Oct</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Nov</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Dec</th>';
                echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Total</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                foreach ($products as $product_id => $product) {
                    echo '<tr>';
                    echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($product_id) . '</td>';
                    echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($product['name']) . '</td>';
                    
                    $total = 0;
                    for ($m = 1; $m <= 12; $m++) {
                        $month = sprintf('%02d', $m);
                        $quantity = isset($product['months'][$month]) ? $product['months'][$month] : 0;
                        $total += $quantity;
                        echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' . esc_html($quantity) . '</td>';
                    }
                    
                    echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center; font-weight: bold; background-color: #f9f9f9;">' . esc_html($total) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
                echo '<form method="post" action="">
                    <input type="hidden" name="export_year" value="' . $selectedYear . '">
                    <input type="hidden" name="export_user_id" value="' . $user_id . '">
                    <input type="hidden" name="export_filter_type" value="year">
                    <input type="submit" name="export_button" class="button button-primary" value="Email Forecast">
                    <input type="submit" name="export_csv_button" class="button button-primary" value="Download Spreadsheet">
                </form>';
            } else {
                echo "<p class='notice notice-error'>The customer hasn't completed any forecasts for this year yet.</p>";
            }

            echo '</div>';
        }
        ob_end_flush();
    }

    // Hook this function to create the user-specific data page
    function add_user_specific_data_page()
    {
        add_submenu_page(
            'user-ids', // Parent menu page slug
            'User-Specific Data',
            'User-Specific Data',
            'read',
            'user-specific-data',
            'display_user_specific_data_page'
        );
    }
    add_action('admin_menu', 'add_user_specific_data_page');


    // SEND EMAIL WHEN CLICKING EXPORT
    if (isset($_POST['export_button']) && !isset($_GET['message'])) {
        global $wpdb;
        $user_id = intval($_POST['export_user_id']);
        $filter_type = isset($_POST['export_filter_type']) ? sanitize_text_field($_POST['export_filter_type']) : 'year';
        $selectedYear = isset($_POST['export_year']) ? sanitize_text_field($_POST['export_year']) : date('Y');
        
        // Get Username and business name
        $table_name = $wpdb->prefix . 'forecast_sheets';
        $user_query = $wpdb->prepare("SELECT user_name FROM $table_name WHERE user_id = %d LIMIT 1", $user_id);
        $user_result = $wpdb->get_row($user_query);
        $user_name = $user_result ? $user_result->user_name : 'Unknown';
        $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);
        
        // Initialize the message variable
        $message = '';
        $subject = '';
        
        // Define months and quarters
        $months = array(
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
            '12' => 'December'
        );
        
        $quarters = [
            'Q1' => ['01', '02', '03'],
            'Q2' => ['04', '05', '06'],
            'Q3' => ['07', '08', '09'],
            'Q4' => ['10', '11', '12'],
        ];
        
        $month_headers = [
            'Q1' => ['Jan', 'Feb', 'Mar'],
            'Q2' => ['Apr', 'May', 'Jun'],
            'Q3' => ['Jul', 'Aug', 'Sep'],
            'Q4' => ['Oct', 'Nov', 'Dec']
        ];
        
        if ($filter_type == 'month') {
            $selectedMonth = sanitize_text_field($_POST['export_month']);
            
            // Query for specific month
            $query = $wpdb->prepare("
                SELECT
                    user_id,
                    user_name,
                    product_id,
                    product_name,
                    quantity
                FROM $table_name
                WHERE user_id = %d
                AND in_year = %s
                AND in_month = %s
                AND quantity > 0
                ORDER BY product_name ASC
            ", $user_id, $selectedYear, $selectedMonth);
            
            $results = $wpdb->get_results($query);
            
            // Prepare the email message
            $subject = 'Forecast for ' . $business . ' - ' . $months[$selectedMonth] . ' ' . $selectedYear;
            $message .= '<h2 style="margin-bottom:20px">Forecast for: ' . $business . ' - ' . $months[$selectedMonth] . ' ' . $selectedYear . '</h2>';
            $message .= '<table border="1">
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                    </tr>';
                    
            foreach ($results as $result) {
                $message .= '<tr>
                        <td>' . $result->product_id . '</td>
                        <td>' . $result->product_name . '</td>
                        <td>' . $result->quantity . '</td>
                    </tr>';
            }
            
            $message .= '</table>';
            
        } elseif ($filter_type == 'quarter') {
            $selectedQuarter = sanitize_text_field($_POST['export_quarter']);
            $selected_months = isset($quarters[$selectedQuarter]) ? $quarters[$selectedQuarter] : [];
            
            // Create placeholders for the IN clause
            $placeholders = implode(', ', array_fill(0, count($selected_months), '%s'));
            
            $query = $wpdb->prepare("
                SELECT
                    user_id,
                    user_name,
                    product_id,
                    product_name,
                    in_month,
                    quantity
                FROM $table_name
                WHERE user_id = %d
                AND in_year = %s
                AND in_month IN ($placeholders)
                AND quantity > 0
                ORDER BY product_name ASC, in_month ASC
            ", array_merge([$user_id, $selectedYear], $selected_months));
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                $month = $row->in_month;
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Prepare the email message
            $subject = 'Forecast for ' . $business . ' - ' . $selectedQuarter . ' ' . $selectedYear;
            $message .= '<h2 style="margin-bottom:20px">Forecast for: ' . $business . ' - ' . $selectedQuarter . ' ' . $selectedYear . '</h2>';
            $message .= '<table border="1">
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>';
            
            // Add month headers for this quarter
            foreach ($month_headers[$selectedQuarter] as $month_header) {
                $message .= '<th>' . $month_header . '</th>';
            }
            
            $message .= '<th>Total</th></tr>';
            
            foreach ($products as $product_id => $product) {
                $message .= '<tr>
                        <td>' . $product_id . '</td>
                        <td>' . $product['name'] . '</td>';
                
                $total = 0;
                foreach ($selected_months as $month) {
                    $quantity = isset($product['months'][$month]) ? $product['months'][$month] : 0;
                    $total += $quantity;
                    $message .= '<td>' . $quantity . '</td>';
                }
                
                $message .= '<td style="font-weight:bold; background-color:#f9f9f9;">' . $total . '</td>
                        </tr>';
            }
            
            $message .= '</table>';
            
        } else { // Year view (default)
            // Query for all months in the year
            $query = $wpdb->prepare("
                SELECT
                    user_id,
                    user_name,
                    product_id,
                    product_name,
                    in_month,
                    quantity
                FROM $table_name
                WHERE user_id = %d
                AND in_year = %s
                AND quantity > 0
                ORDER BY product_name ASC, in_month ASC
            ", $user_id, $selectedYear);
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product and month
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                $month = $row->in_month;
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Prepare the email message
            $subject = 'Forecast for ' . $business . ' - Year: ' . $selectedYear;
            $message .= '<h2 style="margin-bottom:20px">Forecast for: ' . $business . ' - Year: ' . $selectedYear . '</h2>';
            $message .= '<table border="1">
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Jan</th>
                        <th>Feb</th>
                        <th>Mar</th>
                        <th>Apr</th>
                        <th>May</th>
                        <th>Jun</th>
                        <th>Jul</th>
                        <th>Aug</th>
                        <th>Sep</th>
                        <th>Oct</th>
                        <th>Nov</th>
                        <th>Dec</th>
                        <th>Total</th>
                    </tr>';
                    
            foreach ($products as $product_id => $product) {
                $message .= '<tr>
                        <td>' . $product_id . '</td>
                        <td>' . $product['name'] . '</td>';
                
                $total = 0;
                for ($m = 1; $m <= 12; $m++) {
                    $month = sprintf('%02d', $m);
                    $quantity = isset($product['months'][$month]) ? $product['months'][$month] : 0;
                    $total += $quantity;
                    $message .= '<td>' . $quantity . '</td>';
                }
                
                $message .= '<td style="font-weight:bold; background-color:#f9f9f9;">' . $total . '</td>
                        </tr>';
            }
            
            $message .= '</table>';
        }

        // Send the email to the admin
        $admin_emails = array(
            get_option('admin_email'), // Admin's email
            'orders@axichem.com.au',
        );
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_emails, $subject, $message, $headers);

        // Redirect back to the page with a success message
        $current_query_params = $_GET; // Get the current query parameters

        // Add the 'message' parameter
        $current_query_params['message'] = 'success1';

        // Generate the new URL with current parameters and 'message' parameter
        $redirect_url = add_query_arg($current_query_params, admin_url('admin.php?page=user-specific-data'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    // EXPORT AS CSV
    if (isset($_POST['export_csv_button'])) {
        global $wpdb;
        $user_id = intval($_POST['export_user_id']);
        $filter_type = isset($_POST['export_filter_type']) ? sanitize_text_field($_POST['export_filter_type']) : 'year';
        $selectedYear = isset($_POST['export_year']) ? sanitize_text_field($_POST['export_year']) : date('Y');
        
        // Get Username and business name
        $table_name = $wpdb->prefix . 'forecast_sheets';
        $user_query = $wpdb->prepare("SELECT user_name FROM $table_name WHERE user_id = %d LIMIT 1", $user_id);
        $user_result = $wpdb->get_row($user_query);
        $user_name = $user_result ? $user_result->user_name : 'Unknown';
        $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);
        
        // Define months and quarters
        $months = array(
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
            '12' => 'December'
        );
        
        $quarters = [
            'Q1' => ['01', '02', '03'],
            'Q2' => ['04', '05', '06'],
            'Q3' => ['07', '08', '09'],
            'Q4' => ['10', '11', '12'],
        ];
        
        $month_headers = [
            'Q1' => ['Jan', 'Feb', 'Mar'],
            'Q2' => ['Apr', 'May', 'Jun'],
            'Q3' => ['Jul', 'Aug', 'Sep'],
            'Q4' => ['Oct', 'Nov', 'Dec']
        ];
        
        if ($filter_type == 'month') {
            $selectedMonth = sanitize_text_field($_POST['export_month']);
            
            // Query for specific month
            $query = $wpdb->prepare("
                SELECT
                    user_id,
                    user_name,
                    product_id,
                    product_name,
                    quantity
                FROM $table_name
                WHERE user_id = %d
                AND in_year = %s
                AND in_month = %s
                AND quantity > 0
                ORDER BY product_name ASC
            ", $user_id, $selectedYear, $selectedMonth);
            
            $results = $wpdb->get_results($query);
            
            // Define the CSV file path and name
            $csv_file = 'Forecast_' . $business . '-' . $months[$selectedMonth] . '-' . $selectedYear . '.csv';
            
            // Create a file pointer
            $fp = fopen($csv_file, 'w');
            
            // Write the header row
            fputcsv($fp, array('Product ID', 'Product Name', 'Quantity'));
            
            // Write the data rows
            foreach ($results as $result) {
                fputcsv($fp, array($result->product_id, $result->product_name, $result->quantity));
            }
            
        } elseif ($filter_type == 'quarter') {
            $selectedQuarter = sanitize_text_field($_POST['export_quarter']);
            $selected_months = isset($quarters[$selectedQuarter]) ? $quarters[$selectedQuarter] : [];
            
            // Create placeholders for the IN clause
            $placeholders = implode(', ', array_fill(0, count($selected_months), '%s'));
            
            $query = $wpdb->prepare("
                SELECT
                    user_id,
                    user_name,
                    product_id,
                    product_name,
                    in_month,
                    quantity
                FROM $table_name
                WHERE user_id = %d
                AND in_year = %s
                AND in_month IN ($placeholders)
                AND quantity > 0
                ORDER BY product_name ASC, in_month ASC
            ", array_merge([$user_id, $selectedYear], $selected_months));
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                $month = $row->in_month;
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Define the CSV file path and name
            $csv_file = 'Forecast_' . $business . '-' . $selectedQuarter . '-' . $selectedYear . '.csv';
            
            // Create a file pointer
            $fp = fopen($csv_file, 'w');
            
            // Prepare CSV headers
            $headers = array('Product ID', 'Product Name');
            foreach ($month_headers[$selectedQuarter] as $month_header) {
                $headers[] = $month_header;
            }
            $headers[] = 'Total';
            
            // Write the header row
            fputcsv($fp, $headers);
            
            // Write the data rows
            foreach ($products as $product_id => $product) {
                $row_data = array($product_id, $product['name']);
                
                $total = 0;
                foreach ($selected_months as $month) {
                    $quantity = isset($product['months'][$month]) ? $product['months'][$month] : 0;
                    $total += $quantity;
                    $row_data[] = $quantity;
                }
                
                $row_data[] = $total;
                fputcsv($fp, $row_data);
            }
            
        } else { // Year view (default)
            // Query for all months in the year
            $query = $wpdb->prepare("
                SELECT
                    user_id,
                    user_name,
                    product_id,
                    product_name,
                    in_month,
                    quantity
                FROM $table_name
                WHERE user_id = %d
                AND in_year = %s
                AND quantity > 0
                ORDER BY product_name ASC, in_month ASC
            ", $user_id, $selectedYear);
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product and month
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                $month = $row->in_month;
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Define the CSV file path and name
            $csv_file = 'Forecast_' . $business . '-' . $selectedYear . '.csv';
            
            // Create a file pointer
            $fp = fopen($csv_file, 'w');
            
            // Write the header row
            fputcsv($fp, array('Product ID', 'Product Name', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'));
            
            // Write the data rows
            foreach ($products as $product_id => $product) {
                $row_data = array($product_id, $product['name']);
                
                $total = 0;
                for ($m = 1; $m <= 12; $m++) {
                    $month = sprintf('%02d', $m);
                    $quantity = isset($product['months'][$month]) ? $product['months'][$month] : 0;
                    $total += $quantity;
                    $row_data[] = $quantity;
                }
                
                $row_data[] = $total;
                fputcsv($fp, $row_data);
            }
        }
        
        // Close the file pointer
        fclose($fp);
        
        // Send the CSV file as an attachment
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $csv_file);
        readfile($csv_file);
        
        // Delete the CSV file after sending
        unlink($csv_file);
        exit;
    }
