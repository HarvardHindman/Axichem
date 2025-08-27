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


    // Default to January if no month is selected
    $selectedMonth = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '01';

    // Default to 2023 if no year is selected
    $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

    // Query to retrieve unique user IDs based on the selected month
    $table_name = $wpdb->prefix . "forecast_sheets"; // Construct table name
    $query = $wpdb->prepare("
        SELECT DISTINCT user_id, user_name
        FROM $table_name
        WHERE in_month = %s
        AND in_year = %s
    ", $selectedMonth, $selectedYear);

    $results = $wpdb->get_results($query);

?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Forecast Sheets per Customer</h1>
        <hr class="wp-header-end" />
        Select a date <select id="month" name="selectMonth">
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
            echo '<table class="wp-list-table widefat fixed striped table-view-list pages">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Customer</th>';
            echo '<th>Forecast</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($results as $row) {
                $user_id = esc_html($row->user_id);
                $user = get_user_by('ID', $user_id);
                $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);
                echo '<tr>';
                echo '<td>' . esc_html($business) . ' - ' . esc_html($user->first_name) . ' ' . esc_html($user->last_name) . '</td>';
                echo '<td><a class="button button-primary" href="?page=user-specific-data&user_id=' . $user_id . '&month=' . $selectedMonth . '&year=' . $selectedYear . '">View Forecast</a></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
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
                    window.location.href = '?page=user-ids&month=' + selectedMonth + '&year=' + selectedYear;
                }

                // Add change event listener to the month and year dropdowns
                $('#month, #year').on('change', function() {
                    updateURL();
                });

                // After page load, reselect the month and year based on the URL parameter
                var urlParams = new URLSearchParams(window.location.search);
                var selectedYearFromURL = urlParams.get('year');
                var selectedMonthFromURL = urlParams.get('month');
                if (selectedYearFromURL) {
                    $('#year').val(selectedYearFromURL);
                }
                if (selectedMonthFromURL) {
                    $('#month').val(selectedMonthFromURL);
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
            $selectedMonth = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '01';
            $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

            // Get the name of the selected month
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
            $selectedMonthName = $months[$selectedMonth];

            // Query to retrieve user name for the selected user_id and month
            $table_name = $wpdb->prefix . "forecast_sheets"; // Construct table name
            $user_query = $wpdb->prepare("
            SELECT user_name
            FROM $table_name
            WHERE user_id = %s
            AND in_year = %s
            AND in_month = %s
            LIMIT 1
        ", $user_id, $selectedYear, $selectedMonth);

            $user_name = $wpdb->get_var($user_query);

            // Query to retrieve data specific to the selected user_id and month
            $query = $wpdb->prepare("
            SELECT
                user_id,
                user_name,
                product_id,
                product_name,
                quantity
            FROM $table_name
            WHERE user_id = %s
            AND in_month = %s
            AND in_year = %s
            AND quantity > 0
            ORDER BY product_name ASC
        ", $user_id, $selectedMonth, $selectedYear);

            $results = $wpdb->get_results($query);
            $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);

            echo '<div class="wrap">';
            echo '<h2>' . esc_html($selectedMonthName) . ' | Forecast for customer: ' . esc_html($business) . '</h2>';
        ?>
            Select a date <select id="month" name="selectMonth">
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
            <script>
                jQuery(document).ready(function($) {
                    // Function to update the URL with month and year parameters
                    function updateURL() {
                        var selectedMonth = $('#month').val();
                        var selectedYear = $('#year').val();
                        var urlParams = new URLSearchParams(window.location.search);
                        var userId = urlParams.get('user_id');
                        window.location.href = '?page=user-specific-data&user_id=' + userId + '&month=' + selectedMonth + '&year=' + selectedYear;
                    }

                    // Add change event listener to the month and year dropdowns
                    $('#month, #year').on('change', function() {
                        updateURL();
                    });

                    // After page load, reselect the year based on the URL parameter
                    var urlParams = new URLSearchParams(window.location.search);
                    var selectedYearFromURL = urlParams.get('year');
                    if (selectedYearFromURL) {
                        $('#year').val(selectedYearFromURL);
                    }
                });
            </script>
    <?php

            if (!empty($results)) {
                echo '<table class="widefat" style="margin: 20px 0;">';
                echo '<thead>';
                echo '<tr>';
                echo '<th>Customer</th>';
                echo '<th>Product ID</th>';
                echo '<th>Product Name</th>';
                echo '<th>Quantity</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                foreach ($results as $row) {
                    $user_id = $row->user_id;
                    $user = get_user_by('ID', $user_id);
                    echo '<tr>';
                    echo '<td>' . esc_html($business) . ' - ' . esc_html($user->first_name) . ' ' . esc_html($user->last_name) . '</td>';
                    echo '<td>' . esc_html($row->product_id) . '</td>';
                    echo '<td>' . esc_html($row->product_name) . '</td>';
                    echo '<td>' . esc_html($row->quantity) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody>';
                echo '</table>';
                echo '<form method="post" action="">
                    <input type="hidden" name="export_month" value="' . $selectedMonth . '">
                    <input type="submit" name="export_button" class="button button-primary" value="Email Forecast">
                    <input type="submit" name="export_csv_button" class="button button-primary" value="Download Spreadsheet">
                </form>';
            } else {
                echo "<p class='notice notice-error'>The customer hasn't completed this forecast yet.</p>";
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
        // Check if the 'export_month' value is set in the form data
        if (isset($_POST['export_month'])) {
            global $wpdb;
            $selectedMonth = sanitize_text_field($_POST['export_month']);

            // Get the user_id from the URL parameter
            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; // Default to 0 if not provided

            // Default to 2023 if no year is selected
            $selectedYear = isset($_GET['year']) ? sanitize_text_field($_GET['year']) : '2024';

            // Query to retrieve unique user IDs based on the selected month
            $table_name = $wpdb->prefix . 'forecast_sheets';
            // Query to retrieve data specific to the selected user_id and month
            $query = $wpdb->prepare("
            SELECT
                user_id,
                user_name,
                product_id,
                product_name,
                quantity
            FROM $table_name
            WHERE quantity > 0
            AND in_month = %s
            AND in_year = %s
            AND user_id = %d
            ORDER BY product_name ASC
        ", $selectedMonth, $selectedYear, $user_id);

            $results = $wpdb->get_results($query);

            // Get Username from current user id
            $user_query = $wpdb->prepare("SELECT user_name FROM $table_name WHERE user_id = %d LIMIT 1", $user_id);
            $user_result = $wpdb->get_row($user_query);
            $user_name = $user_result ? $user_result->user_name : 'Unknown';
            $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);

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
            $message .= '<h2 style="margin-bottom:20px">Forecast for: ' . $business . ' ' . $selectedMonthName . ', ' . $selectedYear . '</h2>';
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
                        <td>' . $result->quantity . '</td>
                    </tr>';
            }
            $message .= '</table>';

            // Send the email to the admin
            $admin_emails = array(
                get_option('admin_email'), // Admin's email
                'orders@axichem.com.au',
            );
            $subject = 'Forecast for ' . $business . ' ' . $selectedMonthName . ', ' . $selectedYear;
            // $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($admin_emails, $subject, $message, array('Content-Type: text/html'));

            // Redirect back to the page with a success message
            $current_query_params = $_GET; // Get the current query parameters

            // Add the 'message' parameter
            $current_query_params['message'] = 'success1';

            // Generate the new URL with current parameters and 'message' parameter
            $redirect_url = add_query_arg($current_query_params, admin_url('admin.php?page=user-specific-data'));
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    // EXPORT AS CSV
    if (isset($_POST['export_csv_button'])) {
        if (isset($_POST['export_month'])) {
            global $wpdb;
            $selectedMonth = sanitize_text_field($_POST['export_month']);

            // Get the user_id from the URL parameter
            $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; // Default to 0 if not provided

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
            $table_name = $wpdb->prefix . 'forecast_sheets';
            // Query to retrieve data specific to the selected user_id and month
            $query = $wpdb->prepare("
            SELECT
                user_id,
                user_name,
                product_id,
                product_name,
                quantity
            FROM $table_name
            WHERE quantity > 0
            AND in_month = %s
            AND in_year = %s
            AND user_id = %d
            ORDER BY product_name ASC
        ", $selectedMonth, $selectedYear, $user_id);

            $results = $wpdb->get_results($query);

            // Get Username from current user id
            $user_query = $wpdb->prepare("SELECT user_name FROM $table_name WHERE user_id = %d LIMIT 1", $user_id);
            $user_result = $wpdb->get_row($user_query);
            $user_name = $user_result ? $user_result->user_name : 'Unknown';
            $business = get_user_meta($user_id, 'user_registration_input_box_1696381917', true);

            // Define the CSV file path and name
            $csv_file = 'Forecast_' . $business . '-' . $selectedMonthName . '-' . $selectedYear . '.csv';

            // Create a file pointer
            $fp = fopen($csv_file, 'w');

            // Write the header row
            fputcsv($fp, array('Product ID', 'Product Name', 'Total Quantity'));

            // Write the data rows
            foreach ($results as $result) {
                fputcsv($fp, array($result->product_id, $result->product_name, $result->quantity));
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
