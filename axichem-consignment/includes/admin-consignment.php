<?php
// Admin Consignment Stock Management

// Add Consignment Stock page to admin menu
function add_consignment_stock_admin_menu() {
    add_menu_page(
        'Consignment Stock', 
        'Consignment Stock', 
        'manage_options', 
        'consignment-stock', 
        'consignment_stock_admin_page', 
        'dashicons-archive', 
        30
    );
}
add_action('admin_menu', 'add_consignment_stock_admin_menu');

// Enqueue admin scripts and styles
function consignment_stock_admin_scripts($hook) {
    // Only load on our page
    if ($hook !== 'toplevel_page_consignment-stock') {
        return;
    }
    
    wp_enqueue_script('jquery');
    
    // Localize the script with admin-ajax URL and security nonce
    wp_localize_script('jquery', 'consignment_vars', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('axichem-consignment-nonce')
    ));
}
add_action('admin_enqueue_scripts', 'consignment_stock_admin_scripts');

// AJAX handler for product search
function consignment_product_search() {
    // Verify nonce
    check_ajax_referer('axichem-consignment-nonce', 'security');
    
    $search_term = sanitize_text_field($_POST['search_term']);
    
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        's' => $search_term
    );
    
    $query = new WP_Query($args);
    $products = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            
            if ($product) {
                $products[] = array(
                    'id' => $product_id,
                    'name' => get_the_title(),
                    'sku' => $product->get_sku() ? $product->get_sku() : $product_id
                );
            }
        }
        wp_reset_postdata();
    }
    
    wp_send_json_success($products);
}
add_action('wp_ajax_consignment_product_search', 'consignment_product_search');

// Consignment Stock admin page content
function consignment_stock_admin_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle form submissions
    if (isset($_POST['add_consignment_stock'])) {
        // Verify nonce
        check_admin_referer('consignment_stock_action', 'consignment_stock_nonce');
        
        $user_id = intval($_POST['user_id']);
        $product_id = sanitize_text_field($_POST['product_id']);
        $product_name = sanitize_text_field($_POST['product_name']);
        $stock_quantity = intval($_POST['stock_quantity']);
        
        // Get user display name for the message
        $user_info = get_userdata($user_id);
        $user_display = $user_info ? $user_info->display_name : "User ID: $user_id";
        
        // Add or update stock
        $result = add_update_consignment_stock($user_id, $product_id, $product_name, $stock_quantity);
        
        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>Consignment stock updated successfully for <strong>' . esc_html($user_display) . '</strong>!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error updating consignment stock.</p></div>';
        }
    }
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        // Verify nonce
        check_admin_referer('delete_consignment_stock');
        
        $id = intval($_GET['id']);
        
        $result = delete_consignment_stock_item($id);
        
        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>Consignment stock item deleted successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error deleting consignment stock item.</p></div>';
        }
    }
    
    // Get all consignment stock records
    $filter_user_id = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
    
    if ($filter_user_id > 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'consignment_stock';
        
        $consignment_items = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*, u.display_name as user_name 
             FROM $table_name cs
             LEFT JOIN {$wpdb->users} u ON cs.user_id = u.ID
             WHERE cs.user_id = %d
             ORDER BY cs.product_name",
            $filter_user_id
        ));
    } else {
        $consignment_items = get_all_consignment_stock();
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="consignment-admin-container" style="display: flex; margin-top: 20px;">
            <!-- Left column - Form -->
            <div style="flex: 1; margin-right: 20px; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>Add/Update Consignment Stock</h2>
                <form method="post" action="<?php echo add_query_arg(array('filter_user' => $filter_user_id), admin_url('admin.php?page=consignment-stock')); ?>">
                    <?php wp_nonce_field('consignment_stock_action', 'consignment_stock_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="user_id">Select User</label></th>
                            <td>
                                <select name="user_id" id="user_id" required>
                                    <option value="">-- Select User --</option>
                                    <?php
                                    // Get all users regardless of role
                                    $users = get_users(array());
                                    foreach ($users as $user) {
                                        $selected = ($filter_user_id == $user->ID) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($user->ID) . '" ' . $selected . '>' . 
                                             esc_html($user->display_name) . ' - ' . 
                                             esc_html($user->user_email) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="product_search">Search Product</label></th>
                            <td>
                                <input type="text" id="product_search" placeholder="Start typing to search products..." style="width: 100%;">
                                <div id="product_search_results" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; margin-top: 5px;"></div>
                                <input type="hidden" name="product_id" id="product_id" required>
                                <input type="hidden" name="product_name" id="product_name" required>
                                <div id="selected_product_display" style="margin-top: 10px; padding: 8px; background-color: #f9f9f9; border-left: 3px solid #0073aa; display: none;">
                                    <strong>Selected Product:</strong> <span id="product_display_text"></span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="stock_quantity">Stock Quantity</label></th>
                            <td>
                                <input type="number" name="stock_quantity" id="stock_quantity" min="0" value="0" required>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="add_consignment_stock" class="button button-primary" value="Save Consignment Stock">
                    </p>
                </form>
            </div>
            
            <!-- Right column - Current stock list -->
            <div style="flex: 2; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2>Current Consignment Stock</h2>
                    
                    <form method="get" action="" style="display: flex; align-items: center;">
                        <input type="hidden" name="page" value="consignment-stock">
                        <label for="filter_user" style="margin-right: 10px; font-weight: bold;">Filter by User:</label>
                        <select name="filter_user" id="filter_user" style="margin-right: 10px; min-width: 250px;">
                            <option value="0">All Users</option>
                            <?php
                            $users = get_users(array());
                            foreach ($users as $user) {
                                echo '<option value="' . esc_attr($user->ID) . '" ' . 
                                    selected($filter_user_id, $user->ID, false) . '>' . 
                                    esc_html($user->display_name) . ' - ' . 
                                    esc_html($user->user_email) . '</option>';
                            }
                            ?>
                        </select>
                        <input type="submit" class="button button-primary" value="Apply Filter">
                        <?php if ($filter_user_id > 0): ?>
                            <a href="<?php echo admin_url('admin.php?page=consignment-stock'); ?>" 
                               class="button" style="margin-left: 5px;">Reset Filter</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if ($filter_user_id > 0): 
                    $user_info = get_userdata($filter_user_id);
                    if ($user_info): ?>
                    <div style="background-color: #f0f6fc; border-left: 4px solid #2271b1; padding: 10px 15px; margin-bottom: 15px;">
                        <p style="margin: 0;"><strong>Currently filtering:</strong> Showing consignment stock for <?php echo esc_html($user_info->display_name); ?> (<?php echo esc_html($user_info->user_email); ?>)</p>
                    </div>
                <?php endif; endif; ?>
                
                <?php if (empty($consignment_items)) : ?>
                    <p>No consignment stock has been added yet.</p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Stock Quantity</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consignment_items as $item) : ?>
                                <tr>
                                    <td><?php echo esc_html($item->user_name); ?></td>
                                    <td><?php echo esc_html($item->product_id); ?></td>
                                    <td><?php echo esc_html($item->product_name); ?></td>
                                    <td><?php echo esc_html($item->stock_quantity); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($item->last_updated)); ?></td>
                                    <td>
                                        <a href="#" class="edit-consignment" 
                                           data-id="<?php echo esc_attr($item->id); ?>"
                                           data-user-id="<?php echo esc_attr($item->user_id); ?>"
                                           data-product-id="<?php echo esc_attr($item->product_id); ?>"
                                           data-product-name="<?php echo esc_attr($item->product_name); ?>"
                                           data-stock-quantity="<?php echo esc_attr($item->stock_quantity); ?>">
                                            Edit
                                        </a> | 
                                        <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $item->id, 'filter_user' => $filter_user_id)), 'delete_consignment_stock'); ?>" 
                                           class="delete-consignment" 
                                           onclick="return confirm('Are you sure you want to delete this consignment stock item?');">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Product search functionality
        $('#product_search').on('keyup', function() {
            var search_term = $(this).val();
            
            if (search_term.length < 3) {
                $('#product_search_results').hide();
                return;
            }
            
            $('#product_search_results').html('<div style="padding: 10px; text-align: center;">Loading...</div>').show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'consignment_product_search',
                    security: consignment_vars.security,
                    search_term: search_term
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        $.each(response.data, function(index, product) {
                            html += '<div class="product-result" data-id="' + product.sku + '" data-name="' + product.name + '" style="padding: 8px; cursor: pointer; border-bottom: 1px solid #eee;">';
                            html += '<strong>' + product.name + '</strong><br>';
                            html += '<small>SKU: ' + (product.sku || 'N/A') + '</small>';
                            html += '</div>';
                        });
                        
                        $('#product_search_results').html(html).show();
                    } else {
                        $('#product_search_results').html('<div style="padding: 8px;">No products found</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#product_search_results').html('<div style="padding: 8px;">Error searching for products</div>').show();
                    console.error('Error searching for products:', error);
                }
            });
        });
        
        // Handle product selection
        $(document).on('click', '.product-result', function() {
            var productId = $(this).data('id');
            var productName = $(this).data('name');
            
            // Set the values in the hidden fields
            $('#product_id').val(productId);
            $('#product_name').val(productName);
            
            // Update the product display
            $('#product_display_text').text(productName + ' (SKU: ' + productId + ')');
            $('#selected_product_display').show();
            
            // Visual feedback in the search field
            $('#product_search').val('').focus();
            $('#product_search_results').hide();
        });
        
        // Handle edit links
        $('.edit-consignment').on('click', function(e) {
            e.preventDefault();
            
            var userId = $(this).data('user-id');
            var productId = $(this).data('product-id');
            var productName = $(this).data('product-name');
            var stockQuantity = $(this).data('stock-quantity');
            
            // Set form fields
            $('#user_id').val(userId);
            $('#product_id').val(productId);
            $('#product_name').val(productName);
            $('#stock_quantity').val(stockQuantity);
            
            // Update the product display
            $('#product_display_text').text(productName + ' (SKU: ' + productId + ')');
            $('#selected_product_display').show();
            
            // Clear the search field
            $('#product_search').val('');
            
            // Scroll to form
            $('html, body').animate({
                scrollTop: $("form").offset().top - 50
            }, 500);
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#product_search, #product_search_results').length) {
                $('#product_search_results').hide();
            }
        });
    });
    </script>
    <?php
}