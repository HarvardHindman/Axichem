<?php

// Check if WordPress is loaded
if (!function_exists('add_action')) {
    exit;
}

// Add the unified totals dashboard page
function unified_forecast_totals_dashboard()
{
    global $wpdb;
    
    // Default filter settings
    $current_year = date('Y');
    $current_month = date('m');
    $current_quarter = 'Q' . ceil(intval($current_month) / 3);
    
    // Get filter values from GET parameters
    $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'month';
    $selected_year = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : $current_year;
    $selected_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : $current_month;
    $selected_quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : $current_quarter;

    // Set up the table name
    $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';

    // Define quarters and their corresponding months
    $quarters = [
        'Q1' => ['01', '02', '03'],
        'Q2' => ['04', '05', '06'],
        'Q3' => ['07', '08', '09'],
        'Q4' => ['10', '11', '12'],
    ];

    // Prepare the query based on filter type
    $results = [];
    if ($filter_type == 'month') {
        // Month view query
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_month = %s
            AND in_year = %s
            ORDER BY product_name ASC
        ", $selected_month, $selected_year);
        
        $results = $wpdb->get_results($query);
        $period_label = get_month_name($selected_month) . " $selected_year";
    } 
    elseif ($filter_type == 'quarter') {
        // Quarter view query
        $selected_months = isset($quarters[$selected_quarter]) ? $quarters[$selected_quarter] : [];
        
        // Create a placeholder for the IN clause based on the selected months
        $placeholders = implode(', ', array_fill(0, count($selected_months), '%s'));
        
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                SUM(total_quantity) AS total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_month IN ($placeholders)
            AND in_year = %s
            GROUP BY product_id, product_name
            ORDER BY product_name ASC
        ", array_merge($selected_months, [$selected_year]));
        
        $results = $wpdb->get_results($query);
        $period_label = "$selected_quarter $selected_year";
    }
    elseif ($filter_type == 'year') {
        // Year view query
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                SUM(total_quantity) AS total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_year = %s
            GROUP BY product_id, product_name
            ORDER BY product_name ASC
        ", $selected_year);
        
        $results = $wpdb->get_results($query);
        $period_label = "Year $selected_year";
    }

    // Display the dashboard
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Forecast Totals Dashboard</h1>
        <hr class="wp-header-end" />
        
        <div class="filter-controls" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="forecast-totals">
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                    <div>
                        <label for="filter_type"><strong>View by:</strong></label>
                        <select id="filter_type" name="filter_type">
                            <option value="month" <?php selected($filter_type, 'month'); ?>>Month</option>
                            <option value="quarter" <?php selected($filter_type, 'quarter'); ?>>Quarter</option>
                            <option value="year" <?php selected($filter_type, 'year'); ?>>Year</option>
                        </select>
                    </div>
                    
                    <div id="month-filter" style="<?php echo $filter_type !== 'month' ? 'display:none;' : ''; ?>">
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

                            foreach ($months as $month_code => $month_name) {
                                $selected = ($month_code === $selected_month) ? 'selected' : '';
                                echo "<option value='$month_code' $selected>$month_name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div id="quarter-filter" style="<?php echo $filter_type !== 'quarter' ? 'display:none;' : ''; ?>">
                        <label for="quarter"><strong>Quarter:</strong></label>
                        <select id="quarter" name="quarter">
                            <?php
                            $quarter_options = [
                                'Q1' => 'Q1 (Jan - Mar)',
                                'Q2' => 'Q2 (Apr - Jun)',
                                'Q3' => 'Q3 (Jul - Sep)',
                                'Q4' => 'Q4 (Oct - Dec)',
                            ];

                            foreach ($quarter_options as $quarter_code => $quarter_name) {
                                $selected = ($quarter_code === $selected_quarter) ? 'selected' : '';
                                echo "<option value='$quarter_code' $selected>$quarter_name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="year"><strong>Year:</strong></label>
                        <select id="year" name="year">
                            <?php
                            for ($y = 2024; $y <= 2026; $y++) {
                                $selected = ($y == $selected_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
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

        <h2>Forecast Totals for <?php echo $period_label; ?></h2>
        
        <?php
        if (!empty($results)) {
            echo '<div class="tablenav top">';
            echo '<div class="alignleft actions">';
            echo '<form method="post" action="" style="display:inline-block; margin-right: 10px;">';
            echo '<input type="hidden" name="filter_type" value="' . esc_attr($filter_type) . '">';
            echo '<input type="hidden" name="selected_year" value="' . esc_attr($selected_year) . '">';
            
            if ($filter_type == 'month') {
                echo '<input type="hidden" name="selected_month" value="' . esc_attr($selected_month) . '">';
            } elseif ($filter_type == 'quarter') {
                echo '<input type="hidden" name="selected_quarter" value="' . esc_attr($selected_quarter) . '">';
            }
            
            echo '<input type="submit" name="export_email" class="button" value="Email Totals">';
            echo '</form>';
            
            echo '<form method="post" action="" style="display:inline-block;">';
            echo '<input type="hidden" name="filter_type" value="' . esc_attr($filter_type) . '">';
            echo '<input type="hidden" name="selected_year" value="' . esc_attr($selected_year) . '">';
            
            if ($filter_type == 'month') {
                echo '<input type="hidden" name="selected_month" value="' . esc_attr($selected_month) . '">';
            } elseif ($filter_type == 'quarter') {
                echo '<input type="hidden" name="selected_quarter" value="' . esc_attr($selected_quarter) . '">';
            }
            
            echo '<input type="submit" name="export_csv" class="button" value="Download Spreadsheet">';
            echo '</form>';
            echo '</div>';
            echo '</div>';
            
            echo '<table class="wp-list-table widefat fixed striped table-view-list pages" style="border-collapse: collapse; width: 100%;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px;">Product ID</th>';
            echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px;">Product Name</th>';
            echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Quantity</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($results as $row) {
                echo '<tr>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($row->product_id) . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($row->product_name) . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center; font-weight: bold; background-color: #f9f9f9;">' . esc_html($row->total_quantity) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="notice notice-error">No Forecasts found for this period.</p>';
        }
        ?>
        
        <script>
            jQuery(document).ready(function($) {
                // Show/hide appropriate filter based on selection
                $('#filter_type').on('change', function() {
                    var filterType = $(this).val();
                    
                    if (filterType === 'month') {
                        $('#month-filter').show();
                        $('#quarter-filter').hide();
                    } else if (filterType === 'quarter') {
                        $('#month-filter').hide();
                        $('#quarter-filter').show();
                    } else {
                        $('#month-filter').hide();
                        $('#quarter-filter').hide();
                    }
                });
            });
        </script>
    </div>
    <?php
}

// Helper function to get month name
function get_month_name($month_code) {
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
    
    return isset($months[$month_code]) ? $months[$month_code] : '';
}

// Add menu item for the unified dashboard
function add_unified_forecast_totals_menu() {
    add_submenu_page(
        'user-ids', // Parent menu page slug
        'Forecast Totals',
        'Forecast Totals',
        'read',
        'forecast-totals', // Changed slug to be more intuitive
        'unified_forecast_totals_dashboard'
    );
}
add_action('admin_menu', 'add_unified_forecast_totals_menu');

// Process exports
if (isset($_POST['export_email'])) {
    global $wpdb;
    
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'month';
    $selected_year = isset($_POST['selected_year']) ? sanitize_text_field($_POST['selected_year']) : date('Y');
    
    // Get results based on filter type
    $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';
    $results = [];
    $period_label = '';
    
    if ($filter_type == 'month') {
        $selected_month = isset($_POST['selected_month']) ? sanitize_text_field($_POST['selected_month']) : date('m');
        
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_month = %s
            AND in_year = %s
            ORDER BY product_name ASC
        ", $selected_month, $selected_year);
        
        $results = $wpdb->get_results($query);
        $period_label = get_month_name($selected_month) . " $selected_year";
    } 
    elseif ($filter_type == 'quarter') {
        $selected_quarter = isset($_POST['selected_quarter']) ? sanitize_text_field($_POST['selected_quarter']) : 'Q1';
        
        // Define quarters and their corresponding months
        $quarters = [
            'Q1' => ['01', '02', '03'],
            'Q2' => ['04', '05', '06'],
            'Q3' => ['07', '08', '09'],
            'Q4' => ['10', '11', '12'],
        ];
        
        $selected_months = isset($quarters[$selected_quarter]) ? $quarters[$selected_quarter] : [];
        $placeholders = implode(', ', array_fill(0, count($selected_months), '%s'));
        
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                SUM(total_quantity) AS total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_month IN ($placeholders)
            AND in_year = %s
            GROUP BY product_id, product_name
            ORDER BY product_name ASC
        ", array_merge($selected_months, [$selected_year]));
        
        $results = $wpdb->get_results($query);
        $period_label = "$selected_quarter $selected_year";
    }
    elseif ($filter_type == 'year') {
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                SUM(total_quantity) AS total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_year = %s
            GROUP BY product_id, product_name
            ORDER BY product_name ASC
        ", $selected_year);
        
        $results = $wpdb->get_results($query);
        $period_label = "Year $selected_year";
    }
    
    // Prepare and send email
    $message = '<h2 style="margin-bottom:20px">Forecast Totals for period: ' . $period_label . '</h2>';
    $message .= '<table border="1">
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Total Quantity</th>
                </tr>';
                
    foreach ($results as $result) {
        $message .= '<tr>
                    <td>' . $result->product_id . '</td>
                    <td>' . $result->product_name . '</td>
                    <td>' . $result->total_quantity . '</td>
                </tr>';
    }
    $message .= '</table>';

    // Send the email
    $admin_emails = array(
        get_option('admin_email'),
        'orders@axichem.com.au',
    );
    $subject = 'Forecast Totals for ' . $period_label;
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($admin_emails, $subject, $message, $headers);

    // Redirect back with success message
    $redirect_url = add_query_arg(
        array(
            'page' => 'forecast-totals',
            'filter_type' => $filter_type,
            'year' => $selected_year,
            $filter_type === 'month' ? 'month' : ($filter_type === 'quarter' ? 'quarter' : ''),
            $filter_type === 'month' ? $selected_month : ($filter_type === 'quarter' ? $selected_quarter : ''),
            'message' => 'success',
        ),
        admin_url('admin.php')
    );
    wp_safe_redirect($redirect_url);
    exit;
}

// CSV Export
if (isset($_POST['export_csv'])) {
    global $wpdb;
    
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'month';
    $selected_year = isset($_POST['selected_year']) ? sanitize_text_field($_POST['selected_year']) : date('Y');
    
    // Get results based on filter type
    $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';
    $results = [];
    $period_label = '';
    
    if ($filter_type == 'month') {
        $selected_month = isset($_POST['selected_month']) ? sanitize_text_field($_POST['selected_month']) : date('m');
        
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_month = %s
            AND in_year = %s
            ORDER BY product_name ASC
        ", $selected_month, $selected_year);
        
        $results = $wpdb->get_results($query);
        $period_label = get_month_name($selected_month) . " $selected_year";
    } 
    elseif ($filter_type == 'quarter') {
        $selected_quarter = isset($_POST['selected_quarter']) ? sanitize_text_field($_POST['selected_quarter']) : 'Q1';
        
        // Define quarters and their corresponding months
        $quarters = [
            'Q1' => ['01', '02', '03'],
            'Q2' => ['04', '05', '06'],
            'Q3' => ['07', '08', '09'],
            'Q4' => ['10', '11', '12'],
        ];
        
        $selected_months = isset($quarters[$selected_quarter]) ? $quarters[$selected_quarter] : [];
        $placeholders = implode(', ', array_fill(0, count($selected_months), '%s'));
        
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                SUM(total_quantity) AS total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_month IN ($placeholders)
            AND in_year = %s
            GROUP BY product_id, product_name
            ORDER BY product_name ASC
        ", array_merge($selected_months, [$selected_year]));
        
        $results = $wpdb->get_results($query);
        $period_label = "$selected_quarter $selected_year";
    }
    elseif ($filter_type == 'year') {
        $query = $wpdb->prepare("
            SELECT
                product_id,
                product_name,
                SUM(total_quantity) AS total_quantity
            FROM $total_table_name
            WHERE total_quantity > 0
            AND in_year = %s
            GROUP BY product_id, product_name
            ORDER BY product_name ASC
        ", $selected_year);
        
        $results = $wpdb->get_results($query);
        $period_label = "Year $selected_year";
    }
    
    // Create CSV file
    $csv_file = 'Forecast_totals_' . str_replace(' ', '_', $period_label) . '.csv';
    
    // Create a file pointer
    $fp = fopen($csv_file, 'w');
    
    // Write the header row
    fputcsv($fp, array('Product ID', 'Product Name', 'Total Quantity'));
    
    // Write the data rows
    foreach ($results as $result) {
        fputcsv($fp, array($result->product_id, $result->product_name, $result->total_quantity));
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

// Display success message
function unified_forecast_totals_success_notice() {
    if (isset($_GET['message']) && $_GET['message'] === 'success' && isset($_GET['page']) && $_GET['page'] === 'forecast-totals') {
        echo '<div class="updated"><p>Forecast data sent successfully!</p></div>';
    }
}
add_action('admin_notices', 'unified_forecast_totals_success_notice');
