<?php
// Frontend Orders Display

// Add Orders tab to My Account page
function add_axichem_orders_tab($tabs) {
    // Just register our endpoint, we'll handle tab placement in reorder_axichem_account_menu
    $tabs['axichem-orders'] = __('Orders', 'axichem-orders');
    return $tabs;
}
add_filter('woocommerce_account_menu_items', 'add_axichem_orders_tab', 10);

// Reorder account menu to put Orders tab after Forecast Sheets
function reorder_axichem_account_menu($items) {
    // Create a new ordered array with our tab in the right position
    $new_items = array();
    
    // Include our orders tab even if it was removed by another plugin
    if (!isset($items['axichem-orders'])) {
        $items['axichem-orders'] = __('Orders', 'axichem-orders');
    }
    
    // If forecast-sheet exists, we want to position after it
    if (isset($items['forecast-sheet'])) {
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            
            // After forecast-sheet tab, add our orders tab
            if ($key === 'forecast-sheet') {
                $new_items['axichem-orders'] = __('Orders', 'axichem-orders');
                // Remove to avoid duplication if it appears later
                unset($items['axichem-orders']);
            }
        }
        
        return $new_items;
    }
    
    // If forecast-sheet doesn't exist, keep the original order
    return $items;
}
// Use priority 30 to ensure this runs after the forecast-sheets plugin's reordering (at 10)
add_filter('woocommerce_account_menu_items', 'reorder_axichem_account_menu', 30);

// Add endpoint for Orders tab
function add_axichem_orders_endpoint() {
    add_rewrite_endpoint('axichem-orders', EP_ROOT | EP_PAGES);
    
    // Flush rewrite rules on the init hook, but only once
    if (get_option('axichem_orders_flush_rewrite_rules', false) === false) {
        flush_rewrite_rules();
        update_option('axichem_orders_flush_rewrite_rules', true);
    }
}
add_action('init', 'add_axichem_orders_endpoint');

// Orders tab content
function axichem_orders_tab_content() {
    // Simple test message that will show even if there's an error with the database query
    echo '<div style="background-color: #f0f0f0; padding: 10px; margin-bottom: 20px; border-left: 4px solid #0073aa;">
        This is a test message from the orders tab content function.
    </div>';
    
    // Add debugging information
    echo '<!-- Debug info: Starting orders tab content function -->';
    
    // Get current user ID
    $user_id = get_current_user_id();
    echo '<!-- Debug info: User ID: ' . $user_id . ' -->';
    
    // Query orders from database
    global $wpdb;
    $orders_table = $wpdb->prefix . 'axichem_orders';
    
    // Debug the SQL query
    $sql = $wpdb->prepare(
        "SELECT * FROM $orders_table WHERE user_id = %d ORDER BY date_created DESC",
        $user_id
    );
    echo '<!-- Debug info: SQL query: ' . esc_html($sql) . ' -->';
    
    $orders = $wpdb->get_results($sql);
    echo '<!-- Debug info: Number of orders found: ' . count($orders) . ' -->';
    
    // Group orders by order_id
    $grouped_orders = array();
    foreach ($orders as $order) {
        if (!isset($grouped_orders[$order->order_id])) {
            $grouped_orders[$order->order_id] = array(
                'date' => $order->date_created,
                'status' => $order->status,
                'items' => array()
            );
        }
        
        $grouped_orders[$order->order_id]['items'][] = array(
            'id' => $order->id,
            'product_id' => $order->product_id,
            'description' => $order->product_description,
            'quantity' => $order->quantity,
            'amount' => $order->amount
        );
    }
    
    // Display orders
    ?>
    <div class="axichem-orders-container">
        <h2><?php _e('My Orders', 'axichem-orders'); ?></h2>
        
        <?php if (empty($orders)) : ?>
            <p><?php _e('You have no orders yet.', 'axichem-orders'); ?></p>
        <?php else : ?>
            <table id="axichem-orders-table" class="display responsive nowrap">
                <thead>
                    <tr>
                        <th><?php _e('Order ID', 'axichem-orders'); ?></th>
                        <th><?php _e('Date', 'axichem-orders'); ?></th>
                        <th><?php _e('Product', 'axichem-orders'); ?></th>
                        <th><?php _e('Quantity', 'axichem-orders'); ?></th>
                        <th><?php _e('Amount/Item', 'axichem-orders'); ?></th>
                        <th><?php _e('Delivery Estimation', 'axichem-orders'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grouped_orders as $order_id => $order_data) : ?>
                        <?php $first_row = true; ?>
                        <?php foreach ($order_data['items'] as $item) : ?>
                            <tr>
                                <?php if ($first_row) : ?>
                                    <td><?php echo esc_html($order_id); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($order_data['date'])); ?></td>
                                    <?php $first_row = false; ?>
                                <?php else : ?>
                                    <td></td>
                                    <td></td>
                                <?php endif; ?>
                                <td><?php echo esc_html($item['product_id']); ?></td>
                                <td><?php echo esc_html($item['quantity']); ?></td>
                                <td><?php echo ($item['amount'] > 0 ? '$' . number_format($item['amount'], 2) : '-'); ?></td>
                                <td><?php echo esc_html($order_data['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    
    // Additional debug display at the end
    echo '<!-- End of orders tab content function -->';
}

// Important: The action hook name must match the endpoint name exactly
add_action('woocommerce_account_axichem-orders_endpoint', 'axichem_orders_tab_content');
