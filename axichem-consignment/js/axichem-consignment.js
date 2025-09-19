/**
 * Axichem Consignment JavaScript
 */
jQuery(document).ready(function($) {
    // Initialize DataTables on the consignment stock table if it exists
    if ($('#consignment-stock-table').length) {
        // Check if DataTable is already initialized on this table
        if (!$.fn.dataTable.isDataTable('#consignment-stock-table')) {
            $('#consignment-stock-table').DataTable({
                responsive: true,
                order: [[1, 'asc']],
                language: {
                    search: "Search stock:",
                    lengthMenu: "Show _MENU_ products",
                    info: "Showing _START_ to _END_ of _TOTAL_ products",
                    infoEmpty: "No products available",
                    infoFiltered: "(filtered from _MAX_ total products)"
                },
                dom: '<"top"lf>rt<"bottom"ip><"clear">',
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
            });
        }
    }
    
    // Admin page JavaScript
    if ($('.edit-consignment').length) {
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
        
        // Product search functionality
        $('#product_search').on('keyup', function() {
            var search_term = $(this).val();
            
            if (search_term.length < 3) {
                $('#product_search_results').hide();
                return;
            }
            
            $('#product_search_results').html('<div style="padding: 10px; text-align: center;">Loading...</div>').show();
            
            $.ajax({
                url: axichem_consignment_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'consignment_product_search',
                    security: axichem_consignment_params.nonce,
                    search_term: search_term
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        $.each(response.data, function(index, product) {
                            html += '<div class="product-result" data-id="' + product.sku + '" data-name="' + product.name + '">';
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
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#product_search, #product_search_results').length) {
                $('#product_search_results').hide();
            }
        });
    }
});