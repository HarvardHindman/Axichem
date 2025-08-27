<?php

function custom_hide_dashboard_items()
{
    $current_user = wp_get_current_user();
    $user_role = $current_user->roles[0]; // Get the user's role.

    if ($user_role === 'shop_manager') {

        remove_menu_page('edit.php'); // Posts
        remove_menu_page('upload.php'); // Media
        remove_menu_page('edit.php?post_type=page');    //Pages
        remove_menu_page('edit-comments.php');          //Comments
        remove_menu_page('themes.php');                 //Appearance
        remove_menu_page('plugins.php');                //Plugins
        //remove_menu_page('users.php');                  //Users
        remove_menu_page('tools.php');                  //Tools
        remove_menu_page('options-general.php');        //Settings
        remove_menu_page('edit.php?post_type=data_sheets'); // Data Sheets
        remove_menu_page('edit.php?post_type=elementor_library'); // Elementor
        remove_menu_page('woocommerce'); // Woocommerce
        remove_menu_page('edit.php?post_type=product'); // Products
        remove_menu_page('woocommerce-marketing'); // Marketing
        remove_menu_page('wc-admin&path=/analytics/overview'); // Analytics
    }
}
add_action('admin_menu', 'custom_hide_dashboard_items', 71);


// Redirect to Forecasts
function custom_login_redirect($redirect_to, $request, $user)
{
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('shop_manager', $user->roles)) {
            $redirect_to = admin_url('admin.php?page=user-ids');
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);
