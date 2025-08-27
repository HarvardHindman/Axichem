<?php

// Check if WordPress is loaded
if (!function_exists('add_action')) {
    exit;
}

// Add the export page callback
function custom_export_dashboard_button_page()
{
    global $wpdb;

    // Default to January if no month is selected
    $selectedMonth = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '01';

    // Default to 2023 if no year is selected
    $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

    // Query to retrieve unique user IDs based on the selected month
    $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';
    // Query to retrieve data specific to the selected user_id and month
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
", $selectedMonth, $selectedYear);

    $results = $wpdb->get_results($query);

?>
    <div class="wrap">
        <h2>Forecast Totals Per Month</h2>
        Select a month <select id="month" name="selectMonth">
            <?php
            // Generate options for each month
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

            foreach ($months as $monthCode => $monthName) {
                $selected = ($monthCode === $selectedMonth) ? 'selected' : '';
                echo "<option value='$monthCode' $selected>$monthName</option>";
            }
            ?>
        </select>
        <select id="year" name="selectYear">
            <option value="2024" selected>2024</option>
            <option value="2025">2025</option>
            <option value="2026">2026</option>
        </select>

        <h2><?php echo $months[$selectedMonth]; ?>'s Forecasts for year <?php echo $selectedYear; ?></h2>
        <div id="dataContainer">
            <div id="loadingMessage" style="display: none;">
                Fetching data...
            </div>
        </div>
        <?php
        if (!empty($results)) {
            echo '<ul>';
            echo '<table class="wp-list-table widefat fixed striped table-view-list pages" style="margin-bottom:10px">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Product ID</th>';
            echo '<th>Product Name</th>';
            echo '<th>Quantity</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($results as $row) {
                echo '<tr>';
                echo '<td>' . esc_html($row->product_id) . '</td>';
                echo '<td>' . esc_html($row->product_name) . '</td>';
                echo '<td>' . esc_html($row->total_quantity) . '</td>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '<form method="post" action="">
                    <input type="hidden" name="export_month_total" value="' . $selectedMonth . '">
                    <input type="submit" name="export_button" class="button button-primary" value="Email Totals">
                    <input type="submit" name="export_csv_button_total" class="button button-primary" value="Download Spreadsheet">
                </form>';
        } else {
            echo '<p class="notice notice-error">No Forecasts found for this period.</p>';
        }

        echo '</div>';
        ?>
        <script>
            jQuery(document).ready(function($) {
                // Function to update the URL with month and year parameters
                function updateURL() {
                    var selectedMonth = $('#month').val();
                    var selectedYear = $('#year').val();
                    window.location.href = '?page=export-forecast-data&month=' + selectedMonth + '&year=' + selectedYear;
                }

                // Add change event listener to the month and year dropdowns
                $('#month, #year').on('change', function() {
                    updateURL();
                });

                // After page load, reselect the YEAR based on the URL parameter
                var urlParams = new URLSearchParams(window.location.search);
                var selectedYearFromURL = urlParams.get('year');
                if (selectedYearFromURL) {
                    $('#year').val(selectedYearFromURL);
                }

                // After page load, reselect the MONTH based on the URL parameter
                var urlParams2 = new URLSearchParams(window.location.search);
                var selectedMonthFromURL = urlParams2.get('month');
                if (selectedMonthFromURL) {
                    $('#month').val(selectedMonthFromURL);
                }
            });
        </script>
    </div>
<?php
}

// Add a menu item to the admin dashboard
function custom_dashboard_button_menu()
{
    add_submenu_page(
        'user-ids', // Parent menu page slug
        'Totals Per Month',
        'Totals Per Month',
        'read',
        'export-forecast-data',
        'custom_export_dashboard_button_page'
    );
}
add_action('admin_menu', 'custom_dashboard_button_menu');

// Add a notice if the message parameter is present in the URL
function custom_dashboard_success_notice()
{
    if (isset($_GET['message']) && $_GET['message'] === 'success1') {
        echo '<div class="updated"><p>Forecast sent successfully!</p></div>';
    }
}
add_action('admin_notices', 'custom_dashboard_success_notice');



// SEND EMAIL WHEN CLICKING EXPORT
if (isset($_POST['export_button']) && !isset($_GET['message'])) {
    // Check if the 'export_month' value is set in the form data
    if (isset($_POST['export_month_total'])) {
        global $wpdb;
        $selectedMonth = sanitize_text_field($_POST['export_month_total']);

        // Default to 2023 if no year is selected
        $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

        // Query to retrieve unique user IDs based on the selected month
        $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';
        // Query to retrieve data specific to the selected user_id and month
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
        ", $selectedMonth, $selectedYear);

        $results = $wpdb->get_results($query);

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

        // Get the month name based on the selected month number
        $selectedMonthName = $monthNames[$selectedMonth];

        // Initialize the $message variable
        $message = '';

        // Prepare the email message
        $message .= '<h2 style="margin-bottom:20px">Forecast Totals for period: ' . $selectedMonthName . ', ' . $selectedYear . '</h2>';
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

        // Send the email to the admin
        $admin_emails = array(
            get_option('admin_email'), // Admin's email
            'orders@axichem.com.au',
        );
        $subject = 'Forecast Totals for ' . $selectedMonthName . ', ' . $selectedYear;
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_emails, $subject, $message, $headers);

        // Redirect back to the page with a success message
        $redirect_url = add_query_arg('message', 'success1', admin_url('admin.php?page=export-forecast-data'));
        wp_safe_redirect($redirect_url);
        exit;
    }
}

// EXPORT AS CSV
if (isset($_POST['export_csv_button_total'])) {
    if (isset($_POST['export_month_total'])) {
        global $wpdb;
        $selectedMonth = sanitize_text_field($_POST['export_month_total']);

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

        // Get the month name based on the selected month number
        $selectedMonthName = $monthNames[$selectedMonth];

        // Default to 2023 if no year is selected
        $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

        // Query to retrieve unique user IDs based on the selected month
        $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';
        // Query to retrieve data specific to the selected user_id and month
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
        ", $selectedMonth, $selectedYear);

        $results = $wpdb->get_results($query);

        // Define the CSV file path and name
        $csv_file = 'Forecast_totals_' . $selectedMonthName . '-' . $selectedYear . '.csv';

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
}
