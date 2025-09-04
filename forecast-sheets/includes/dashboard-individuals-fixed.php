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
    $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : date('Y');

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
        
        <div class="filter-controls" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="user-ids">
                
                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                    <div>
                        <label for="year"><strong>Select Year:</strong></label>
                        <select id="year" name="year">
                            <?php
                            // Generate options for years
                            for ($y = 2024; $y <= 2026; $y++) {
                                $selected = ($y == $selectedYear) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="button button-primary">Apply Filter</button>
                    </div>
                </div>
            </form>
        </div>

        <h2>Forecasts for year <?php echo $selectedYear; ?></h2>

        <div id="dataContainer">
            <div id="loadingMessage" style="display: none;">
                Fetching data...
            </div>
        </div>
        <?php
        if (!empty($results)) {
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
            
            // Default filter settings
            $current_year = date('Y');
            $current_month = date('m');
            $current_quarter = 'Q' . ceil(intval($current_month) / 3);
            
            // Get filter values from GET parameters
            $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'year';
            $selected_year = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : $current_year;
            $selected_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : $current_month;
            $selected_quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : $current_quarter;

            // Get business name
            $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);
            $user = get_user_by('ID', $user_id);

            echo '<div class="wrap">';
            echo '<h1 class="wp-heading-inline">Forecast for customer: ' . esc_html($business) . '</h1>';
            echo '<hr class="wp-header-end" />';
            
            // Filter controls
            ?>
            <div class="filter-controls" style="margin: 20px 0; padding: 15px; background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="user-specific-data">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                    
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                        <div>
                            <label for="filter_type"><strong>View by:</strong></label>
                            <select id="filter_type" name="filter_type">
                                <option value="year" <?php selected($filter_type, 'year'); ?>>Year</option>
                                <option value="quarter" <?php selected($filter_type, 'quarter'); ?>>Quarter</option>
                                <option value="month" <?php selected($filter_type, 'month'); ?>>Month</option>
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
            <?php

            // Define quarters and their corresponding months
            $quarters = [
                'Q1' => ['01', '02', '03'],
                'Q2' => ['04', '05', '06'],
                'Q3' => ['07', '08', '09'],
                'Q4' => ['10', '11', '12'],
            ];
            
            // Use the global get_month_name function from dashboard-totals-unified.php
            
            // Build the query based on filter type
            $table_name = $wpdb->prefix . "forecast_sheets";
            $results = [];
            $period_label = '';
            
            if ($filter_type == 'month') {
                // Month view query
                $query = $wpdb->prepare("
                    SELECT
                        user_id,
                        user_name,
                        product_id,
                        product_name,
                        quantity
                    FROM $table_name
                    WHERE user_id = %s
                    AND in_year = %s
                    AND in_month = %s
                    AND quantity > 0
                    ORDER BY product_name ASC
                ", $user_id, $selected_year, $selected_month);
                
                $results = $wpdb->get_results($query);
                $period_label = get_month_name($selected_month) . " " . $selected_year;
                
                // Display month view
                if (!empty($results)) {
                    echo '<h2>Forecast for: ' . esc_html($period_label) . '</h2>';
                    
                    // Export buttons
                    echo '<div class="tablenav top">';
                    echo '<div class="alignleft actions">';
                    echo '<form method="post" action="" style="display:inline-block; margin-right: 10px;">';
                    echo '<input type="hidden" name="export_month" value="' . esc_attr($selected_month) . '">';
                    echo '<input type="hidden" name="export_year" value="' . esc_attr($selected_year) . '">';
                    echo '<input type="hidden" name="export_user_id" value="' . esc_attr($user_id) . '">';
                    echo '<input type="hidden" name="export_filter_type" value="month">';
                    echo '<input type="submit" name="export_button" class="button" value="Email Forecast">';
                    echo '</form>';
                    
                    echo '<form method="post" action="" style="display:inline-block;">';
                    echo '<input type="hidden" name="export_month" value="' . esc_attr($selected_month) . '">';
                    echo '<input type="hidden" name="export_year" value="' . esc_attr($selected_year) . '">';
                    echo '<input type="hidden" name="export_user_id" value="' . esc_attr($user_id) . '">';
                    echo '<input type="hidden" name="export_filter_type" value="month">';
                    echo '<input type="submit" name="export_csv_button" class="button" value="Download Spreadsheet">';
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
                        echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center; font-weight: bold; background-color: #f9f9f9;">' . esc_html($row->quantity) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo "<p class='notice notice-error'>No forecasts found for this month.</p>";
                }
            }
            elseif ($filter_type == 'quarter') {
                // Quarter view query
                $selected_months = isset($quarters[$selected_quarter]) ? $quarters[$selected_quarter] : [];
                
                // Create a placeholder for the IN clause
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
                    WHERE user_id = %s
                    AND in_year = %s
                    AND in_month IN ($placeholders)
                    AND quantity > 0
                    ORDER BY product_name ASC, in_month ASC
                ", array_merge([$user_id, $selected_year], $selected_months));
                
                $results = $wpdb->get_results($query);
                $period_label = $selected_quarter . " " . $selected_year;
                
                // Process results for quarter view
                if (!empty($results)) {
                    // Organize data by product and month within the quarter
                    $products = array();
                    foreach ($results as $row) {
                        $product_id = $row->product_id;
                        // Ensure month is consistently formatted
                        $month = sprintf('%02d', (int)$row->in_month);
                        
                        if (!isset($products[$product_id])) {
                            $products[$product_id] = array(
                                'name' => $row->product_name,
                                'months' => array()
                            );
                        }
                        
                        $products[$product_id]['months'][$month] = $row->quantity;
                    }
                    
                    echo '<h2>Forecast for: ' . esc_html($period_label) . '</h2>';
                    
                    // Export buttons
                    echo '<div class="tablenav top">';
                    echo '<div class="alignleft actions">';
                    echo '<form method="post" action="" style="display:inline-block; margin-right: 10px;">';
                    echo '<input type="hidden" name="export_quarter" value="' . esc_attr($selected_quarter) . '">';
                    echo '<input type="hidden" name="export_year" value="' . esc_attr($selected_year) . '">';
                    echo '<input type="hidden" name="export_user_id" value="' . esc_attr($user_id) . '">';
                    echo '<input type="hidden" name="export_filter_type" value="quarter">';
                    echo '<input type="submit" name="export_button" class="button" value="Email Forecast">';
                    echo '</form>';
                    
                    echo '<form method="post" action="" style="display:inline-block;">';
                    echo '<input type="hidden" name="export_quarter" value="' . esc_attr($selected_quarter) . '">';
                    echo '<input type="hidden" name="export_year" value="' . esc_attr($selected_year) . '">';
                    echo '<input type="hidden" name="export_user_id" value="' . esc_attr($user_id) . '">';
                    echo '<input type="hidden" name="export_filter_type" value="quarter">';
                    echo '<input type="submit" name="export_csv_button" class="button" value="Download Spreadsheet">';
                    echo '</form>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<table class="wp-list-table widefat fixed striped table-view-list pages" style="border-collapse: collapse; width: 100%;">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px;">Product ID</th>';
                    echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px;">Product Name</th>';
                    
                    // Headers for months in this quarter
                    $month_headers = [
                        'Q1' => ['Jan', 'Feb', 'Mar'],
                        'Q2' => ['Apr', 'May', 'Jun'],
                        'Q3' => ['Jul', 'Aug', 'Sep'],
                        'Q4' => ['Oct', 'Nov', 'Dec']
                    ];
                    
                    foreach ($month_headers[$selected_quarter] as $month_header) {
                        echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">' . $month_header . '</th>';
                    }
                    
                    echo '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ddd; padding: 8px; text-align: center;">Total</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    foreach ($products as $product_id => $product) {
                        echo '<tr>';
                        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($product_id) . '</td>';
                        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($product['name']) . '</td>';
                        
                        $total = 0;
                        // Display data for each month in the quarter
                        $quarter_months = $quarters[$selected_quarter];
                        foreach ($quarter_months as $month) {
                            $quantity = isset($product['months'][$month]) ? $product['months'][$month] : 0;
                            $total += $quantity;
                            echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center;">' . esc_html($quantity) . '</td>';
                        }
                        
                        echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: center; font-weight: bold; background-color: #f9f9f9;">' . esc_html($total) . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                } else {
                    echo "<p class='notice notice-error'>No forecasts found for this quarter.</p>";
                }
            }
            else { // Year view (default)
                // Query to retrieve data for all months for the selected user and year
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
                ", $user_id, $selected_year);

                $results = $wpdb->get_results($query);
                $period_label = "Year " . $selected_year;

                if (!empty($results)) {
                    // Organize data by product and month
                    $products = array();
                    foreach ($results as $row) {
                        $product_id = $row->product_id;
                        // Ensure month is consistently formatted
                        $month = sprintf('%02d', (int)$row->in_month);
                        
                        if (!isset($products[$product_id])) {
                            $products[$product_id] = array(
                                'name' => $row->product_name,
                                'months' => array()
                            );
                        }
                        
                        $products[$product_id]['months'][$month] = $row->quantity;
                    }
                    
                    echo '<h2>Forecast for: ' . esc_html($period_label) . '</h2>';
                    
                    // Export buttons
                    echo '<div class="tablenav top">';
                    echo '<div class="alignleft actions">';
                    echo '<form method="post" action="" style="display:inline-block; margin-right: 10px;">';
                    echo '<input type="hidden" name="export_year" value="' . esc_attr($selected_year) . '">';
                    echo '<input type="hidden" name="export_user_id" value="' . esc_attr($user_id) . '">';
                    echo '<input type="hidden" name="export_filter_type" value="year">';
                    echo '<input type="submit" name="export_button" class="button" value="Email Forecast">';
                    echo '</form>';
                    
                    echo '<form method="post" action="" style="display:inline-block;">';
                    echo '<input type="hidden" name="export_year" value="' . esc_attr($selected_year) . '">';
                    echo '<input type="hidden" name="export_user_id" value="' . esc_attr($user_id) . '">';
                    echo '<input type="hidden" name="export_filter_type" value="year">';
                    echo '<input type="submit" name="export_csv_button" class="button" value="Download Spreadsheet">';
                    echo '</form>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<table class="wp-list-table widefat fixed striped table-view-list pages" style="border-collapse: collapse; width: 100%;">';
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
                } else {
                    echo "<p class='notice notice-error'>The customer hasn't completed any forecasts for this year yet.</p>";
                }
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
        $selected_year = isset($_POST['export_year']) ? sanitize_text_field($_POST['export_year']) : date('Y');
        
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
            $selected_month = sanitize_text_field($_POST['export_month']);
            
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
            ", $user_id, $selected_year, $selected_month);
            
            $results = $wpdb->get_results($query);
            
            // Prepare the email message
            $subject = 'Forecast for ' . $business . ' - ' . $months[$selected_month] . ' ' . $selected_year;
            $message .= '<h2 style="margin-bottom:20px">Forecast for: ' . $business . ' - ' . $months[$selected_month] . ' ' . $selected_year . '</h2>';
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
            $selected_quarter = sanitize_text_field($_POST['export_quarter']);
            $selected_months = isset($quarters[$selected_quarter]) ? $quarters[$selected_quarter] : [];
            
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
            ", array_merge([$user_id, $selected_year], $selected_months));
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                // Ensure month is consistently formatted
                $month = sprintf('%02d', (int)$row->in_month);
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Prepare the email message
            $subject = 'Forecast for ' . $business . ' - ' . $selected_quarter . ' ' . $selected_year;
            $message .= '<h2 style="margin-bottom:20px">Forecast for: ' . $business . ' - ' . $selected_quarter . ' ' . $selected_year . '</h2>';
            $message .= '<table border="1">
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>';
            
            // Add month headers for this quarter
            foreach ($month_headers[$selected_quarter] as $month_header) {
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
            ", $user_id, $selected_year);
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product and month
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                // Ensure month is consistently formatted
                $month = sprintf('%02d', (int)$row->in_month);
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Prepare the email message
            $subject = 'Forecast for ' . $business . ' - Year: ' . $selected_year;
            $message .= '<h2 style="margin-bottom:20px">Forecast for: ' . $business . ' - Year: ' . $selected_year . '</h2>';
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
        $current_query_params['message'] = 'success';

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
        $selected_year = isset($_POST['export_year']) ? sanitize_text_field($_POST['export_year']) : date('Y');
        
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
            $selected_month = sanitize_text_field($_POST['export_month']);
            
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
            ", $user_id, $selected_year, $selected_month);
            
            $results = $wpdb->get_results($query);
            
            // Define the CSV file path and name
            $csv_file = 'Forecast_' . $business . '-' . $months[$selected_month] . '-' . $selected_year . '.csv';
            
            // Create a file pointer
            $fp = fopen($csv_file, 'w');
            
            // Write the header row
            fputcsv($fp, array('Product ID', 'Product Name', 'Quantity'));
            
            // Write the data rows
            foreach ($results as $result) {
                fputcsv($fp, array($result->product_id, $result->product_name, $result->quantity));
            }
            
        } elseif ($filter_type == 'quarter') {
            $selected_quarter = sanitize_text_field($_POST['export_quarter']);
            $selected_months = isset($quarters[$selected_quarter]) ? $quarters[$selected_quarter] : [];
            
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
            ", array_merge([$user_id, $selected_year], $selected_months));
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                // Ensure month is consistently formatted
                $month = sprintf('%02d', (int)$row->in_month);
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Define the CSV file path and name
            $csv_file = 'Forecast_' . $business . '-' . $selected_quarter . '-' . $selected_year . '.csv';
            
            // Create a file pointer
            $fp = fopen($csv_file, 'w');
            
            // Prepare CSV headers
            $headers = array('Product ID', 'Product Name');
            foreach ($month_headers[$selected_quarter] as $month_header) {
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
            ", $user_id, $selected_year);
            
            $results = $wpdb->get_results($query);
            
            // Organize data by product and month
            $products = array();
            foreach ($results as $row) {
                $product_id = $row->product_id;
                // Ensure month is consistently formatted
                $month = sprintf('%02d', (int)$row->in_month);
                
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'name' => $row->product_name,
                        'months' => array()
                    );
                }
                
                $products[$product_id]['months'][$month] = $row->quantity;
            }
            
            // Define the CSV file path and name
            $csv_file = 'Forecast_' . $business . '-' . $selected_year . '.csv';
            
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

    // Display success message
    function individual_forecast_success_notice() {
        if (isset($_GET['message']) && $_GET['message'] === 'success' && isset($_GET['page']) && $_GET['page'] === 'user-specific-data') {
            echo '<div class="updated"><p>Forecast data sent successfully!</p></div>';
        }
    }
    add_action('admin_notices', 'individual_forecast_success_notice');
