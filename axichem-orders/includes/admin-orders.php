<?php
// Admin Orders Management

// Add Orders Management menu item in admin
function axichem_orders_admin_menu() {
    add_menu_page(
        'Axichem Orders',
        'Axichem Orders',
        'manage_options',
        'axichem-orders',
        'axichem_orders_admin_page',
        'dashicons-cart',
        30
    );
    
    // Add submenu for importing CSV
    add_submenu_page(
        'axichem-orders',
        'Import Orders',
        'Import Orders',
        'manage_options',
        'axichem-orders-import',
        'axichem_orders_import_page'
    );
    
    // Add submenu for flushing rewrite rules
    add_submenu_page(
        'axichem-orders',
        'Fix Permalinks',
        'Fix Permalinks',
        'manage_options',
        'axichem-orders-permalinks',
        'axichem_orders_permalinks_page'
    );
}
add_action('admin_menu', 'axichem_orders_admin_menu');

// Admin page for fixing permalinks
function axichem_orders_permalinks_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $message = '';
    
    // Handle form submission
    if (isset($_POST['flush_permalinks']) && check_admin_referer('axichem_flush_permalinks')) {
        flush_rewrite_rules();
        $message = '<div class="notice notice-success is-dismissible"><p>Permalinks have been flushed successfully. The Orders tab should now work correctly.</p></div>';
    }
    
    // Display the page
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php echo $message; ?>
        
        <p>If the Orders tab in the My Account page is showing a "Page not found" error, use this button to fix the permalinks.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('axichem_flush_permalinks'); ?>
            <p class="submit">
                <input type="submit" name="flush_permalinks" class="button button-primary" value="Fix Permalinks">
            </p>
        </form>
        
        <h3>Alternative Fix</h3>
        <p>You can also try going to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings > Permalinks</a> and clicking "Save Changes" without making any changes.</p>
    </div>
    <?php
}

// Admin page callback for managing orders
function axichem_orders_admin_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get all users including admins and customers
    $users = get_users(array(
        'role__in' => array('customer', 'administrator', 'shop_manager', 'author', 'editor', 'contributor', 'subscriber'),
        'orderby' => 'display_name',
    ));
    
    // Get selected user
    $selected_user_id = isset($_GET['view_user_id']) ? intval($_GET['view_user_id']) : 0;
    
    // Handle form submission for adding a new order
    if (isset($_POST['add_order']) && current_user_can('manage_options')) {
        // Verify nonce
        if (isset($_POST['axichem_orders_nonce']) && wp_verify_nonce($_POST['axichem_orders_nonce'], 'axichem_add_order')) {
            // Process form data
            $user_id = intval($_POST['user_id']);
            $order_id = sanitize_text_field($_POST['order_id']);
            $product_id = sanitize_text_field($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            $amount = floatval(str_replace(',', '', $_POST['amount']));
            $delivery_estimation = sanitize_text_field($_POST['status']);
            
            // Insert into database
            global $wpdb;
            $orders_table = $wpdb->prefix . 'axichem_orders';
            
            $wpdb->insert(
                $orders_table,
                array(
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'date_created' => date('Y-m-d H:i:s'), // Use current date and time
                    'product_id' => $product_id,
                    'product_description' => $product_id, // Use product_id as description
                    'quantity' => $quantity,
                    'amount' => $amount,
                    'status' => $delivery_estimation
                )
            );
            
            echo '<div class="notice notice-success is-dismissible"><p>Order added successfully!</p></div>';
            
            // Redirect to prevent form resubmission
            wp_redirect(admin_url('admin.php?page=axichem-orders&view_user_id=' . $user_id));
            exit;
        }
    }
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && isset($_GET['nonce'])) {
        $id = intval($_GET['id']);
        $nonce = sanitize_text_field($_GET['nonce']);
        
        if (wp_verify_nonce($nonce, 'delete_order_' . $id) && current_user_can('manage_options')) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'axichem_orders';
            
            $wpdb->delete(
                $orders_table,
                array('id' => $id),
                array('%d')
            );
            
            // Redirect to prevent resubmission
            wp_redirect(admin_url('admin.php?page=axichem-orders&view_user_id=' . $selected_user_id . '&deleted=1'));
            exit;
        }
    }
    
    // Display deleted notice
    if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
        echo '<div class="notice notice-success is-dismissible"><p>Order item successfully deleted.</p></div>';
    }
    
    // Display the page
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- User Selection Form -->
        <form method="get" action="">
            <input type="hidden" name="page" value="axichem-orders">
            
            <div class="user-selection-container">
                <label for="view_user_id"><strong>Select Customer:</strong></label>
                <select name="view_user_id" id="view_user_id" class="regular-text">
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($users as $user) : 
                        $selected = ($selected_user_id == $user->ID) ? 'selected' : '';
                    ?>
                        <option value="<?php echo esc_attr($user->ID); ?>" <?php echo $selected; ?>>
                            <?php echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="View Orders">
            </div>
        </form>
        
        <?php if ($selected_user_id > 0) : 
            $user = get_user_by('id', $selected_user_id);
            if ($user) :
                global $wpdb;
                $orders_table = $wpdb->prefix . 'axichem_orders';
                
                $orders = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $orders_table WHERE user_id = %d ORDER BY date_created DESC",
                        $selected_user_id
                    )
                );
                
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
                        'quantity' => $order->quantity,
                        'amount' => $order->amount
                    );
                }
                ?>
                
                <div class="orders-container">
                    <h2>Orders for <?php echo esc_html($user->display_name); ?></h2>
                    
                    <!-- Add Order Button -->
                    <div class="add-order-container">
                        <button id="show-add-order-form" class="button button-primary">Add New Order</button>
                    </div>
                    
                    <!-- Add Order Form (initially hidden) -->
                    <div id="add-order-form" style="display: none; margin-bottom: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd;">
                        <h3>Add New Order</h3>
                        <form method="post" action="">
                            <?php wp_nonce_field('axichem_add_order', 'axichem_orders_nonce'); ?>
                            <input type="hidden" name="user_id" value="<?php echo esc_attr($selected_user_id); ?>">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="order_id">Order ID</label></th>
                                    <td>
                                        <input type="text" name="order_id" id="order_id" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="product_id">Product</label></th>
                                    <td>
                                        <div style="display: inline-block; position: relative;">
                                            <input type="text" name="product_id" id="product-search" class="regular-text" autocomplete="off" placeholder="Start typing product name or SKU..." required>
                                            <div id="product-suggestions-container">
                                                <div id="product-suggestions"></div>
                                            </div>
                                        </div>
                                        <p class="description">Search by product name or SKU</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="quantity">Quantity</label></th>
                                    <td>
                                        <input type="number" name="quantity" id="quantity" class="regular-text" min="1" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="amount">Amount</label></th>
                                    <td>
                                        <input type="text" name="amount" id="amount" class="regular-text" placeholder="0.00">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="status">Delivery Estimation</label></th>
                                    <td>
                                        <input type="text" name="status" id="status" class="regular-text" placeholder="e.g., October 2025, Q4 2025, 15/12/2025" required>
                                        <p class="description">Enter the expected delivery time (month, quarter, or specific date)</p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <input type="submit" name="add_order" class="button button-primary" value="Add Order">
                                <button type="button" id="cancel-add-order" class="button">Cancel</button>
                            </p>
                        </form>
                    </div>
                    
                    <?php if (empty($orders)) : ?>
                        <p>No orders found for this customer.</p>
                        
                        <!-- Add inline JavaScript for when there are no orders -->
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                // Toggle add order form
                                $('#show-add-order-form').click(function(e) {
                                    e.preventDefault();
                                    $('#add-order-form').slideDown();
                                    $(this).hide();
                                });
                                
                                $('#cancel-add-order').click(function(e) {
                                    e.preventDefault();
                                    $('#add-order-form').slideUp();
                                    $('#show-add-order-form').show();
                                });
                            });
                        </script>
                    <?php else : ?>
                        <table id="admin-orders-table" class="display responsive nowrap">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Amount/Item</th>
                                    <th>Delivery Estimation</th>
                                    <th>Actions</th>
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
                                            <td><?php echo ($item['quantity'] > 0 ? '$' . number_format($item['amount'] / $item['quantity'], 2) : '-'); ?></td>
                                            <td><?php echo esc_html($order_data['status']); ?></td>
                                            <td>
                                                <a href="?page=axichem-orders&action=edit&id=<?php echo esc_attr($item['id']); ?>&view_user_id=<?php echo esc_attr($selected_user_id); ?>" class="button button-small">Edit</a>
                                                <a href="?page=axichem-orders&action=delete&id=<?php echo esc_attr($item['id']); ?>&view_user_id=<?php echo esc_attr($selected_user_id); ?>&nonce=<?php echo wp_create_nonce('delete_order_' . $item['id']); ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <script type="text/javascript">
                            jQuery(document).ready(function($) {
                                // Toggle add order form
                                $('#show-add-order-form').click(function(e) {
                                    e.preventDefault();
                                    $('#add-order-form').slideDown();
                                    $(this).hide();
                                });
                                
                                $('#cancel-add-order').click(function(e) {
                                    e.preventDefault();
                                    $('#add-order-form').slideUp();
                                    $('#show-add-order-form').show();
                                });
                            });
                        </script>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    
    // Add AJAX for product search
    add_action('admin_footer', 'axichem_orders_product_search_script');
}

// Product search script for admin footer
function axichem_orders_product_search_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var typingTimer;
        var doneTypingInterval = 300;
        var $input = $('#product-search');
        var $suggestions = $('#product-suggestions');
        var currentFocus = -1;
        
        // Position the suggestions dropdown under the input
        function positionSuggestions() {
            var inputWidth = $input.outerWidth();
            $suggestions.css('width', inputWidth + 'px');
        }
        
        // Call this when showing suggestions
        $(window).on('resize', positionSuggestions);
        
        // Clear suggestions when input is cleared
        $input.on('input', function() {
            if ($(this).val() === '') {
                $suggestions.hide();
                currentFocus = -1;
            }
        });
        
        // Handle keyboard navigation
        $input.on('keydown', function(e) {
            if ($suggestions.is(':visible')) {
                var items = $suggestions.find('.product-suggestion-item');
                
                // Down arrow
                if (e.keyCode === 40) {
                    currentFocus++;
                    addActive(items);
                    e.preventDefault();
                } 
                // Up arrow
                else if (e.keyCode === 38) {
                    currentFocus--;
                    addActive(items);
                    e.preventDefault();
                } 
                // Enter key
                else if (e.keyCode === 13 && currentFocus > -1) {
                    if (items.length) {
                        $(items[currentFocus]).click();
                        e.preventDefault();
                    }
                }
            }
        });
        
        // Handle input typing
        $input.on('keyup', function(e) {
            // Skip for arrow keys, enter
            if ([38, 40, 13].indexOf(e.keyCode) !== -1) return;
            
            clearTimeout(typingTimer);
            var val = $(this).val();
            if (val.length < 2) {
                $suggestions.hide();
                return;
            }
            
            typingTimer = setTimeout(function() {
                $suggestions.html('<div style="padding:12px; text-align:center;"><span style="display:inline-block; width:20px; height:20px; border:2px solid #eee; border-radius:50%; border-top-color:#0073aa; animation:spin 1s linear infinite;"></span> Searching...</div>').show();
                positionSuggestions(); // Position the dropdown
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'axichem_orders_product_search',
                        term: val,
                        nonce: '<?php echo wp_create_nonce('axichem_product_search'); ?>'
                    },
                    success: function(res) {
                        if (res.success && res.data.length > 0) {
                            var html = '';
                            res.data.forEach(function(item) {
                                html += '<div class="product-suggestion-item" data-id="'+item.id+'" data-name="'+item.name+'">'+item.name+' <span style="color:#888; font-size:12px;">('+item.sku+')</span></div>';
                            });
                            $suggestions.html(html).show();
                            positionSuggestions(); // Reposition after content is loaded
                            currentFocus = -1;
                        } else {
                            $suggestions.html('<div style="padding:12px; color:#666; text-align:center;">No products found</div>').show();
                            positionSuggestions();
                        }
                    },
                    error: function() {
                        $suggestions.html('<div style="padding:12px; color:#d63638; text-align:center;">Error searching products</div>').show();
                        positionSuggestions();
                    }
                });
            }, doneTypingInterval);
        });
        
        // Handle suggestion click
        $suggestions.on('click', '.product-suggestion-item', function() {
            var name = $(this).data('name');
            $input.val(name);
            $suggestions.hide();
            currentFocus = -1;
        });
        
        // Close suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#product-suggestions, #product-search').length) {
                $suggestions.hide();
                currentFocus = -1;
            }
        });
        
        // Add active class to suggestion item
        function addActive(items) {
            if (!items) return;
            
            removeActive(items);
            
            if (currentFocus >= items.length) currentFocus = 0;
            if (currentFocus < 0) currentFocus = (items.length - 1);
            
            $(items[currentFocus]).addClass('active').css('background-color', '#f0f7ff');
            $(items[currentFocus]).css('border-left', '3px solid #0073aa');
            $(items[currentFocus]).css('padding-left', '9px');
            
            // Scroll into view if needed
            var container = $suggestions[0];
            var item = items[currentFocus];
            
            // Check if item is not visible
            if (item.offsetTop < container.scrollTop || 
                item.offsetTop + item.offsetHeight > container.scrollTop + container.clientHeight) {
                container.scrollTop = item.offsetTop - container.clientHeight / 2;
            }
        }
        
        // Remove active class from all items
        function removeActive(items) {
            for (var i = 0; i < items.length; i++) {
                $(items[i]).removeClass('active').css('background-color', '');
                $(items[i]).css('border-left', '');
                $(items[i]).css('padding-left', '12px');
            }
        }
        
        // Add spinner animation to CSS
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `)
            .appendTo('head');
    });
    </script>
    <?php
}

// AJAX handler for product search (WooCommerce products)
function axichem_orders_product_search() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'axichem_product_search')) {
        wp_send_json_error();
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error();
    }
    
    $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
    if (empty($term)) {
        wp_send_json_success([]);
    }
    
    if (!function_exists('wc_get_products')) {
        wp_send_json_success([]);
    }
    
    $args = array(
        'limit' => 10,
        'status' => 'publish',
        's' => $term,
        'return' => 'ids',
    );
    
    $products = wc_get_products($args);
    $results = [];
    
    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $results[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
            );
        }
    }
    
    wp_send_json_success($results);
}
add_action('wp_ajax_axichem_orders_product_search', 'axichem_orders_product_search');

// Admin page callback for importing orders from CSV
function axichem_orders_import_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle CSV import
    if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
        // Verify nonce
        if (isset($_POST['axichem_import_nonce']) && wp_verify_nonce($_POST['axichem_import_nonce'], 'axichem_import_csv')) {
            $file = $_FILES['csv_file']['tmp_name'];
            $user_id = intval($_POST['import_user_id']);
            
            if (!empty($file) && $user_id > 0) {
                // Process CSV file
                $imported = axichem_process_csv_import($file, $user_id);
                
                if ($imported > 0) {
                    echo '<div class="notice notice-success is-dismissible"><p>Successfully imported ' . $imported . ' order items.</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>No valid order data found in the CSV file.</p></div>';
                }
            }
        }
    }
    
    // Get all customers
    $users = get_users(array(
        'role__in' => array('customer', 'administrator', 'shop_manager', 'author', 'editor', 'contributor', 'subscriber'),
        'orderby' => 'display_name',
    ));
    
    // Display the page
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <h2>Import Orders from CSV</h2>
        <p>Upload a CSV file with order data to import orders for a customer.</p>
        <p>CSV should have these headers: <code>ID No.,Date,Qty,Item/Acct/Activity,Description,Orig. Curr.,Amount,Status</code></p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('axichem_import_csv', 'axichem_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="import_user_id">Customer</label></th>
                    <td>
                        <select name="import_user_id" id="import_user_id" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($users as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>">
                                    <?php echo esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="csv_file">CSV File</label></th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="import_csv" class="button button-primary" value="Import Orders">
            </p>
        </form>
        
        <h3>CSV Format Example</h3>
        <pre>
ID No.,Date,Qty,Item/Acct/Activity,Description,Orig. Curr.,Amount,Status
00032290,18/06/2025,200,04-GR1000,Growler 450 1000 L,AUD,0.00,Order
00032373,30/06/2025,50,04-ATD1000,Attack Dog 540SL 1000 L,AUD,"212,500.00",Order
        </pre>
    </div>
    <?php
}

// Process CSV import function
function axichem_process_csv_import($file, $user_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'axichem_orders';
    
    $imported_count = 0;
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        $header_row = true;
        $headers = array();
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Skip empty rows
            if (empty($data[0]) && empty($data[1]) && empty($data[2])) {
                continue;
            }
            
            // Process header row
            if ($header_row) {
                $headers = $data;
                $header_row = false;
                continue;
            }
            
            // Check if this is a valid data row
            if (count($data) < 5) {
                continue;
            }
            
            // Map CSV columns to database fields
            $order_id = !empty($data[0]) ? sanitize_text_field($data[0]) : '';
            $date = !empty($data[1]) ? sanitize_text_field($data[1]) : '';
            $quantity = !empty($data[2]) ? intval($data[2]) : 0;
            $product_id = !empty($data[3]) ? sanitize_text_field($data[3]) : '';
            $description = !empty($data[4]) ? sanitize_text_field($data[4]) : '';
            $amount = !empty($data[6]) ? str_replace(array('"', ','), array('', ''), $data[6]) : 0;
            $status = !empty($data[7]) ? sanitize_text_field($data[7]) : 'Order';
            
            // Skip rows with batch numbers or special notes
            if (strpos($product_id, '1BAN') === 0 || strpos($product_id, '1SMC') === 0) {
                continue;
            }
            
            // Format date for database
            if (!empty($date)) {
                $date_obj = DateTime::createFromFormat('d/m/Y', $date);
                if ($date_obj) {
                    $formatted_date = $date_obj->format('Y-m-d H:i:s');
                } else {
                    $formatted_date = date('Y-m-d H:i:s');
                }
            } else {
                $formatted_date = date('Y-m-d H:i:s');
            }
            
            // Insert into database
            $result = $wpdb->insert(
                $orders_table,
                array(
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'date_created' => $formatted_date,
                    'product_id' => $product_id,
                    'product_description' => $product_id, // Use product_id as description
                    'quantity' => $quantity,
                    'amount' => floatval($amount),
                    'status' => $status
                )
            );
            
            if ($result) {
                $imported_count++;
            }
        }
        
        fclose($handle);
    }
    
    return $imported_count;
}
