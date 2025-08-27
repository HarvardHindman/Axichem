<?php

// Check if WordPress is loaded
if (!function_exists('add_action')) {
    exit;
}

// Add the export page callback
function forecasts_per_quarter()
{
    global $wpdb;

    // Default to Q1 if no quarter is selected
    $selectedQuarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'Q1';

    // Default to 2023 if no year is selected
    $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

    // Define quarters and their corresponding months
    $quarters = [
        'Q1' => ['01', '02', '03'],
        'Q2' => ['04', '05', '06'],
        'Q3' => ['07', '08', '09'],
        'Q4' => ['10', '11', '12'],
    ];

    // Determine the selected months for the quarter
    $selectedMonths = isset($quarters[$selectedQuarter]) ? $quarters[$selectedQuarter] : [];

    // Query to retrieve data specific to the selected quarter and year
    $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';

    // Create a placeholder for the IN clause based on the selected months
    $placeholders = implode(', ', array_fill(0, count($selectedMonths), '%s'));

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
    ", array_merge($selectedMonths, [$selectedYear]));

    $results = $wpdb->get_results($query);

    // Display content for Forecast Totals Per Quarters
?>
    <div class="wrap">
        <h2>Forecast Totals Per Quarters</h2>
        Select a quarter <select id="quarter" name="selectQuarter">
            <?php
            // Generate options for each quarter
            $quarterOptions = [
                'Q1' => 'Q1 (Jan - Mar)',
                'Q2' => 'Q2 (Apr - Jun)',
                'Q3' => 'Q3 (Jul - Sep)',
                'Q4' => 'Q4 (Oct - Dec)',
            ];

            foreach ($quarterOptions as $quarterCode => $quarterName) {
                $selected = ($quarterCode === $selectedQuarter) ? 'selected' : '';
                echo "<option value='$quarterCode' $selected>$quarterName</option>";
            }
            ?>
        </select>
        <select id="year" name="selectYear">
            <option value="2024" selected>2024</option>
            <option value="2025">2025</option>
            <option value="2026">2026</option>
        </select>

        <h2><?php echo $quarterOptions[$selectedQuarter]; ?>'s Forecasts for year <?php echo $selectedYear; ?></h2>
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
                    <input type="hidden" name="export_quarter" value="' . $selectedQuarter . '">
                    <input type="submit" name="export_button" class="button button-primary" value="Email Totals">
                    <input type="submit" name="export_csv_button" class="button button-primary" value="Download Spreadsheet">
                </form>';
        } else {
            echo '<p class="notice notice-error">No Forecasts found for this period.</p>';
        }

        echo '</div>';
        ?>
        <script>
            jQuery(document).ready(function($) {
                // Function to update the URL with quarter and year parameters
                function updateURL() {
                    var selectedQuarter = $('#quarter').val();
                    var selectedYear = $('#year').val();
                    window.location.href = '?page=export-forecast-quarter&quarter=' + selectedQuarter + '&year=' + selectedYear;
                }

                // Add change event listener to the quarter and year dropdowns
                $('#quarter, #year').on('change', function() {
                    updateURL();
                });

                // After page load, reselect the year and quarter based on the URL parameters
                var urlParams = new URLSearchParams(window.location.search);
                var selectedQuarterFromURL = urlParams.get('quarter');
                var selectedYearFromURL = urlParams.get('year');
                if (selectedQuarterFromURL) {
                    $('#quarter').val(selectedQuarterFromURL);
                }
                if (selectedYearFromURL) {
                    $('#year').val(selectedYearFromURL);
                }
            });
        </script>
    </div>
<?php
}


// Add a menu item to the admin dashboard
function dashboard_quarter_forecasts()
{
    add_submenu_page(
        'user-ids', // Parent menu page slug
        'Totals Per Quarter',
        'Totals Per Quarter',
        'read',
        'export-forecast-quarter',
        'forecasts_per_quarter'
    );
}
add_action('admin_menu', 'dashboard_quarter_forecasts');

// Add a notice if the message parameter is present in the URL
function custom_dashboard_success_notice2()
{
    if (isset($_GET['message']) && $_GET['message'] === 'success2') {
        echo '<div class="updated"><p>Forecast sent successfully!</p></div>';
    }
}
add_action('admin_notices', 'custom_dashboard_success_notice2');


// SEND EMAIL WHEN CLICKING EXPORT
if (isset($_POST['export_button']) && !isset($_GET['message'])) {
    // Check if the 'export_quarter' value is set in the form data
    if (isset($_POST['export_quarter'])) {
        global $wpdb;
        $selectedQuarter = sanitize_text_field($_POST['export_quarter']);
        $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

        // Define quarters and their corresponding months
        $quarters = [
            'Q1' => ['01', '02', '03'],
            'Q2' => ['04', '05', '06'],
            'Q3' => ['07', '08', '09'],
            'Q4' => ['10', '11', '12'],
        ];

        // Determine the selected months for the quarter
        $selectedMonths = isset($quarters[$selectedQuarter]) ? $quarters[$selectedQuarter] : [];

        // Query to retrieve data specific to the selected quarter and year
        $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';

        // Create a placeholder for the IN clause based on the selected months
        $placeholders = implode(', ', array_fill(0, count($selectedMonths), '%s'));

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
        ", array_merge($selectedMonths, [$selectedYear]));

        $results = $wpdb->get_results($query);

        // Define the email subject and content
        $quarterOptions = [
            'Q1' => 'Q1 (Jan - Mar)',
            'Q2' => 'Q2 (Apr - Jun)',
            'Q3' => 'Q3 (Jul - Sep)',
            'Q4' => 'Q4 (Oct - Dec)',
        ];

        $selectedQuarterName = $quarterOptions[$selectedQuarter];

        $message = '<h2 style="margin-bottom:20px">Forecast Totals for period: ' . $selectedQuarterName . ', ' . $selectedYear . '</h2>';
        $message .= '<table border="1">
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Total Quantity</th>
                    </tr>';

        foreach ($results as $result) {
            $message .= '<tr>
                        <td>' . $result->product_id . '</td>
                        <td>' . esc_html($result->product_name) . '</td>
                        <td>' . esc_html($result->total_quantity) . '</td>
                    </tr>';
        }

        $message .= '</table>';

        // Send the email to the admin
        $admin_emails = array(
            get_option('admin_email'), // Admin's email
            'orders@axichem.com.au',
        );
        $subject = 'Forecast Totals for ' . $selectedQuarterName . ', ' . $selectedYear;
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($admin_emails, $subject, $message, $headers);

        // Redirect back to the page with a success message
        $redirect_url = add_query_arg('message', 'success2', admin_url('admin.php?page=export-forecast-quarter'));
        wp_safe_redirect($redirect_url);
        exit;
    }
}



// EXPORT AS CSV
if (isset($_POST['export_csv_button'])) {
    if (isset($_POST['export_quarter'])) {
        global $wpdb;
        $selectedQuarter = sanitize_text_field($_POST['export_quarter']);
        $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

        // Define quarters and their corresponding months
        $quarters = [
            'Q1' => ['01', '02', '03'],
            'Q2' => ['04', '05', '06'],
            'Q3' => ['07', '08', '09'],
            'Q4' => ['10', '11', '12'],
        ];

        // Determine the selected months for the quarter
        $selectedMonths = isset($quarters[$selectedQuarter]) ? $quarters[$selectedQuarter] : [];

        // Query to retrieve data specific to the selected quarter and year
        $total_table_name = $wpdb->prefix . 'forecast_sheets_totals';

        // Create a placeholder for the IN clause based on the selected months
        $placeholders = implode(', ', array_fill(0, count($selectedMonths), '%s'));

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
        ", array_merge($selectedMonths, [$selectedYear]));

        $results = $wpdb->get_results($query);

        // Define the CSV file name
        $quarterOptions = [
            'Q1' => 'Q1 (Jan - Mar)',
            'Q2' => 'Q2 (Apr - Jun)',
            'Q3' => 'Q3 (Jul - Sep)',
            'Q4' => 'Q4 (Oct - Dec)',
        ];

        $selectedQuarterName = $quarterOptions[$selectedQuarter];
        $csv_file = 'Forecast_totals_' . $selectedQuarterName . '-' . $selectedYear . '.csv';

        // Create a file pointer
        $fp = fopen($csv_file, 'w');

        // Write the header row
        fputcsv($fp, array('Product ID', 'Product Name', 'Total Quantity'));

        // Write the data rows
        foreach ($results as $result) {
            fputcsv($fp, array($result->product_id, esc_html($result->product_name), $result->total_quantity));
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
