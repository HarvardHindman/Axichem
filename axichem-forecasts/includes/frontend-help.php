<?php

// Add Help Tab
function add_help_tab($items)
{
	$items['forecast-help'] = 'Help';
	return $items;
}
add_filter('woocommerce_account_menu_items', 'add_help_tab', 10, 1);

// Define Help endpoint
function help_endpoint()
{
	add_rewrite_endpoint('forecast-help', EP_ROOT | EP_PAGES);
}
add_action('init', 'help_endpoint');

// Help Tab Content
function help_tab_content()
{
?>
	<h3>Help</h3>
	<br />
	<div class="forecast__help">
		<p>Need a hand? Please get in touch with Axichem via phone or email using the details below.</p>
		<br />
		<div class="help__contact-details">
			<div>
				<svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 384 512">
					<path d="M16 64C16 28.7 44.7 0 80 0H304c35.3 0 64 28.7 64 64V448c0 35.3-28.7 64-64 64H80c-35.3 0-64-28.7-64-64V64zM144 448c0 8.8 7.2 16 16 16h64c8.8 0 16-7.2 16-16s-7.2-16-16-16H160c-8.8 0-16 7.2-16 16zM304 64H80V384H304V64z" />
				</svg> <a href="tel:0755961736"><strong style="color:var( --e-global-color-57ae1e6 )">07 5596 1736</strong></a>
			</div>
			<div>
				<svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512">
					<path d="M64 112c-8.8 0-16 7.2-16 16v22.1L220.5 291.7c20.7 17 50.4 17 71.1 0L464 150.1V128c0-8.8-7.2-16-16-16H64zM48 212.2V384c0 8.8 7.2 16 16 16H448c8.8 0 16-7.2 16-16V212.2L322 328.8c-38.4 31.5-93.7 31.5-132 0L48 212.2zM0 128C0 92.7 28.7 64 64 64H448c35.3 0 64 28.7 64 64V384c0 35.3-28.7 64-64 64H64c-35.3 0-64-28.7-64-64V128z" />
				</svg> <a href="mailto:orders@axichem.com.au"><strong style="color:var( --e-global-color-57ae1e6 )">orders@axichem.com.au</strong></a>
			</div>
		</div>
	</div>
<?php
}
add_action('woocommerce_account_forecast-help_endpoint', 'help_tab_content');
