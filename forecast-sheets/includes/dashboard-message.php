<?php
// Add a menu item to the admin dashboard
function message_customers_button_menu()
{
    add_submenu_page(
        'user-ids', // Parent menu page slug
        'Message Customers',
        'Message Customers',
        'read',
        'message-customers',
        'message_customers_page'
    );
}
add_action('admin_menu', 'message_customers_button_menu');

// Add a notice if the message parameter is present in the URL
function message_customers_success_notice()
{
    if (isset($_GET['message']) && $_GET['message'] === 'success') {
        echo '<div class="updated"><p>Forecast sent successfully!</p></div>';
    }
}
add_action('admin_notices', 'message_customers_success_notice');

function message_customers_page()
{
    // Check if the form has been submitted
    if (isset($_POST['save_message'])) {
        // Get the entered value for the custom field
        $message = stripslashes(sanitize_text_field($_POST['message_customers']));

        // Update the message as a WordPress option
        update_option('message_customers', $message);
        echo '<div class="updated"><p>Message updated successfully!</p></div>';
		
		// Send email to customers
		$customers = get_users(array('role' => 'customer'));
		$domain_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

		// Loop through customers and send them an email.
		foreach ($customers as $customer) {
			$to = $customer->user_email;
			$subject = 'Axichem - New message!';
			$message = "Dear customer, there's a new message for you on your Axichem's Dashboard.";
			$message .= '<br/><br/>';
			$message .= 'You will find it at: ' . $domain_url . '/my-account/';
			$headers = array('Content-Type: text/html; charset=UTF-8');
	        wp_mail($to, $subject, $message, $headers);
		}
    }

    // Retrieve the saved message from the WordPress database
    $message_customers = get_option('message_customers');
?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Message to Customers</h1>
        <p>Add a message that will be visible on your customers' dashboard.</p>
        <form method="post" action="">
            <div><textarea id="message_customers" name="message_customers" rows="10" cols="100"><?php echo esc_textarea($message_customers); ?></textarea></div>
            <div><input type="submit" name="save_message" class="button button-primary" value="Save Message"></div>
        </form>
    </div>
<?php
}
