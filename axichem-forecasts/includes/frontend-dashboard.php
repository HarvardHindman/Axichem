<?php

function remove_account_dashboard_text()
{
    remove_action('woocommerce_before_account_content', 'woocommerce_account_content');
}
add_action('init', 'remove_account_dashboard_text');


function custom_dashboard_content()
{
?>
    <div class="account-dashboard">
        <h5 class="welcome__dashboard">Welcome to your Axichem Dashboard.</h5>
        <p>To start a new forecast, continue an existing forecast, or submit an order, go to <a href="/my-account/forecast-sheet/">Forecast Sheets</a> on the left.</p>
        <div class="dashboard-divider"></div>
        <h5>Get started.</h5>
        <p>Click <a href="/my-account/forecast-sheet/">Forecast Sheets</a> to manage and submit forecasts.</p>
        <div class="dashboard-divider"></div>
        <h5>Need help?</h5>
        <p>Click <a href="/my-account/forecast-instructions">Instructions</a> to learn how to manage and submit forecasts. Click <a href="/my-account/-forecast-help/">Help</a> for Axichem contact details.</p>
    </div>
<?php
}

add_action('woocommerce_account_dashboard', 'custom_dashboard_content');
