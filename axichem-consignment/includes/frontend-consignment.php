<?php
// Frontend Consignment Stock Tab

// Add Consignment Stock tab to My Account page
function add_consignment_stock_tab($items)
{
    $items['consignment-stock'] = 'Consignment Stock';
    return $items;
}
add_filter('woocommerce_account_menu_items', 'add_consignment_stock_tab', 10, 1);

// Reorder account menu to put Consignment Stock tab after Forecast Sheets (if exists)
// and before Orders (if exists)
function reorder_consignment_stock_tab($items)
{
    // If forecast-sheet exists, we want to position after it
    if (isset($items['forecast-sheet'])) {
        $new_items = array();
        
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            
            // After forecast-sheet tab, add our consignment stock tab
            if ($key === 'forecast-sheet') {
                // Remove to avoid duplication if it already exists elsewhere
                if (isset($items['consignment-stock'])) {
                    unset($items['consignment-stock']);
                }
                $new_items['consignment-stock'] = 'Consignment Stock';
            }
        }
        
        return $new_items;
    }
    
    // If orders tab exists, position before it
    if (isset($items['axichem-orders'])) {
        $new_items = array();
        
        foreach ($items as $key => $label) {
            // Before orders tab, add our consignment stock tab
            if ($key === 'axichem-orders') {
                // Remove to avoid duplication if it already exists elsewhere
                if (isset($items['consignment-stock'])) {
                    unset($items['consignment-stock']);
                }
                $new_items['consignment-stock'] = 'Consignment Stock';
            }
            
            $new_items[$key] = $label;
        }
        
        return $new_items;
    }
    
    // If neither exists, keep original order
    return $items;
}
// Use priority 15 to position between forecast sheets (10) and orders (30)
add_filter('woocommerce_account_menu_items', 'reorder_consignment_stock_tab', 15);

// Define Consignment Stock endpoint
function consignment_stock_endpoint()
{
    add_rewrite_endpoint('consignment-stock', EP_ROOT | EP_PAGES);
    
    // Flush rewrite rules on the first run
    if (get_option('consignment_stock_flush_rewrite_rules', false) === false) {
        flush_rewrite_rules();
        update_option('consignment_stock_flush_rewrite_rules', true);
    }
}
add_action('init', 'consignment_stock_endpoint');

// Content for the Consignment Stock tab
function consignment_stock_tab_content()
{
    // Simple test message that will show even if there's an error with the database query
    echo '<div style="background-color: #f0f0f0; padding: 10px; margin-bottom: 20px; border-left: 4px solid #0073aa;">
        This is a test message from the consignment stock tab content function.
    </div>';
    
    // Get current user ID
    $user_id = get_current_user_id();
    
    // Query consignment stock from database
    $consignment_items = get_user_consignment_stock($user_id);
    
    // Display consignment stock
    ?>
    <div class="consignment-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background-color: #f9fbff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <h3 style="margin: 0; font-size: 24px; color: #333; font-weight: 600;">My Consignment Stock</h3>
    </div>
    
    <div class="consignment-body axichem-form">
        <?php if (empty($consignment_items)) : ?>
            <p>You don't have any consignment stock assigned to your account yet. Please contact Axichem for more information.</p>
        <?php else : ?>
            <table id="consignment-stock-table" class="display responsive" style="width:100%">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Available Stock</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consignment_items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->product_id); ?></td>
                            <td><?php echo esc_html($item->product_name); ?></td>
                            <td><?php echo esc_html($item->stock_quantity); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($item->last_updated)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
add_action('woocommerce_account_consignment-stock_endpoint', 'consignment_stock_tab_content');