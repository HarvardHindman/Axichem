<?php

// Add Tab
function add_instructions_tab($items)
{
    $items['forecast-instructions'] = 'Instructions';
    return $items;
}
add_filter('woocommerce_account_menu_items', 'add_instructions_tab', 10, 1);

// Define endpoint
function instructions_endpoint()
{
    add_rewrite_endpoint('forecast-instructions', EP_ROOT | EP_PAGES);
}
add_action('init', 'instructions_endpoint');

// Tab Content
function instructions_tab_content()
{
?>
    <h3>Instructions</h3>
    <br />
    <div class="forecast__instructions">
        <h5>Start a new monthly forecast:</h5>
        <ol>
            <li>Choose the month and year at the top of the forecast sheet</li>
            <li>Add or remove quantities of products as required</li>
        </ol>

        <h5>Save a monthly forecast:</h5>
        <ol>
            <li>Update forecast sheet as required</li>
            <li>Click ‘Save Forecast’ in the bottom left corner</li>
            <li>Forecasts can be returned to and continued at any time</li>
        </ol>

        <h5>To continue a monthly forecast:</h5>
        <ol>
            <li>Choose the Month and Year of a previously saved forecast sheet</li>
            <li>Add or update product quantities as required</li>
            <li>Click save</li>
			<li>Move on to downloading, or sending a forecast sheet</li>
        </ol>

        <h5>Download a monthly forecast for you records or to help prepare a PO number (if required):</h5>
        <p>Update forecast as required, click ‘Download Spreadsheet’ (remember to Save your forecast before leaving the page or starting a new forecast)</p>

        <h5>Submit an order to Axichem:</h5>
        <p>Once your forecast is ready to convert to an order, add a PO number (if you will be submitting a PO number at a later date, leave this area blank) then click ‘Send to Axichem’. Your Axichem rep will then be in touch to confirm your order.</p>

        <div class="dashboard-divider"></div>
        <h5>Need help?</h5>
        <p>Click <a href="/my-account/forecast-help/">Help</a> on the menu for Axichem contact details.</p>
    </div>
<?php
}
add_action('woocommerce_account_forecast-instructions_endpoint', 'instructions_tab_content');
