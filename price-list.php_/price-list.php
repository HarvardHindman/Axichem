<?php
/**
 * Plugin Name: Price List Tab by Lion & Lamb
 * Description: Adds a Price List tab to the WooCommerce My Account menu.
 * Version: 1.0
 * Author: Lion & Lamb
 */

 // Check if ACF is active
 if (function_exists('have_rows')) {
 	 
	// Reorder account items
	function reorder_account_menuItems($items)
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
	add_filter('woocommerce_account_menu_items', 'reorder_account_menuItems');
 
     // Add content to the new tab
     function price_list_tab_content() {
         echo '<h3>Price List</h3>';
         echo '<div class="price_list_content">';
         
         $current_user = wp_get_current_user();
         $has_price_list = false;
 
         if (have_rows('prices_lists', 'options')) :
             while (have_rows('prices_lists', 'options')) : the_row();
 
                 $allowed_users = get_sub_field('user', 'options');
                 $is_allowed = false;
 
                 if ($allowed_users) {
                     if (is_array($allowed_users)) {
                         $is_allowed = in_array($current_user->ID, wp_list_pluck($allowed_users, 'ID'));
                     } else {
                         $is_allowed = $current_user->ID == $allowed_users->ID;
                     }
                 }
 
                 if ($is_allowed) {
                     $has_price_list = true;
                     $price_list_file = get_sub_field('file', 'options');
                     if ($price_list_file) : ?>
						<p>Click below to download your latest price list.</p>
                         <a class="price_list_button" href="<?php echo esc_url($price_list_file['url']); ?>" target="_blank">
                             <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M64 464l48 0 0 48-48 0c-35.3 0-64-28.7-64-64L0 64C0 28.7 28.7 0 64 0L229.5 0c17 0 33.3 6.7 45.3 18.7l90.5 90.5c12 12 18.7 28.3 18.7 45.3L384 304l-48 0 0-144-80 0c-17.7 0-32-14.3-32-32l0-80L64 48c-8.8 0-16 7.2-16 16l0 384c0 8.8 7.2 16 16 16zM176 352l32 0c30.9 0 56 25.1 56 56s-25.1 56-56 56l-16 0 0 32c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-48 0-80c0-8.8 7.2-16 16-16zm32 80c13.3 0 24-10.7 24-24s-10.7-24-24-24l-16 0 0 48 16 0zm96-80l32 0c26.5 0 48 21.5 48 48l0 64c0 26.5-21.5 48-48 48l-32 0c-8.8 0-16-7.2-16-16l0-128c0-8.8 7.2-16 16-16zm32 128c8.8 0 16-7.2 16-16l0-64c0-8.8-7.2-16-16-16l-16 0 0 96 16 0zm80-112c0-8.8 7.2-16 16-16l48 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-32 0 0 32 32 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-32 0 0 48c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-64 0-64z"/></svg> 
                             Download Price List</a>
                     <?php endif; 
                 } 
             endwhile;
         endif;
 
         if (!$has_price_list) {
             echo 'You have no price list assigned, please check back or get in touch if you would like your price list added.';
         }
         
         echo '</div>';
     }
     add_action('woocommerce_account_price-list_endpoint', 'price_list_tab_content');
 
     // Register the new endpoint
     function add_price_list_endpoint() {
         add_rewrite_endpoint('price-list', EP_ROOT | EP_PAGES);
     }
     add_action('init', 'add_price_list_endpoint');
 } 