<?php
/*
Plugin Name: Forecast Sheets by Lion&Lamb
Description: Adds a Forecast tab to the My Account page, where users can fill out the product form.
*/

// Include files 
function include_export_data_file()
{
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-forecast.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-help.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-instructions.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/frontend-dashboard.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-individuals.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-totals.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-totals-quarters.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-message.php');
    include_once(plugin_dir_path(__FILE__) . 'includes/dashboard-shopmanager.php');
}
add_action('plugins_loaded', 'include_export_data_file');


// Enqueue Style & Scripts
function enqueue_custom_plugin_styles()
{
    wp_enqueue_script('jquery');
    wp_enqueue_style('axichem-forecast-css', plugin_dir_url(__FILE__) . '/includes/css/lionandlamb-axichem.css');
    wp_enqueue_style('dataTable-css', plugin_dir_url(__FILE__) . '/includes/css/datatables.min.css');
    wp_enqueue_style('dataTableResponsive-css', plugin_dir_url(__FILE__) . '/includes/css/responsive.dataTables.min.css');
    wp_enqueue_script('dataTable-js', plugin_dir_url(__FILE__) . '/includes/js/datatables.min.js');
    wp_enqueue_script('dataTableResponsive-js', plugin_dir_url(__FILE__) . '/includes/js/dataTables.responsive.min.js');
    wp_enqueue_script('axichem-forecast-js', plugin_dir_url(__FILE__) . '/includes/js/lionandlamb-axichem.js');
    wp_localize_script('axichem-forecast-js', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_plugin_styles');