/**
 * Axichem Orders JavaScript
 */
jQuery(document).ready(function($) {
    // Initialize DataTables for frontend orders
    if ($('#axichem-orders-table').length) {
        $('#axichem-orders-table').DataTable({
            responsive: true,
            order: [[1, 'desc']],
            columnDefs: [
                { orderable: false, targets: [2, 3, 4, 5] }
            ],
            language: {
                search: "Search orders:",
                lengthMenu: "Show _MENU_ orders",
                info: "Showing _START_ to _END_ of _TOTAL_ orders",
                infoEmpty: "No orders available",
                infoFiltered: "(filtered from _MAX_ total orders)"
            },
            dom: '<"top"lf>rt<"bottom"ip><"clear">',
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
    }
    
    // Admin Page Specific JavaScript
    
    // Always add event handlers for the Add Order form buttons
    // These need to work even when there are no orders
    $('#show-add-order-form').on('click', function(e) {
        e.preventDefault();
        $('#add-order-form').slideDown();
        $(this).hide();
    });
    
    $('#cancel-add-order').on('click', function(e) {
        e.preventDefault();
        $('#add-order-form').slideUp();
        $('#show-add-order-form').show();
    });
    
    // Initialize DataTable only if the table exists
    if ($('#admin-orders-table').length) {
        // Initialize DataTable with improved layout
        $('#admin-orders-table').DataTable({
            responsive: true,
            order: [[1, 'desc']],
            columnDefs: [
                { orderable: false, targets: [2, 3, 4, 6] }
            ],
            language: {
                search: "Search orders:",
                lengthMenu: "Show _MENU_ orders",
                info: "Showing _START_ to _END_ of _TOTAL_ orders",
                infoEmpty: "No orders available",
                infoFiltered: "(filtered from _MAX_ total orders)"
            },
            dom: '<"top"lf>rt<"bottom"ip><"clear">',
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Format currency input
        $('#amount').on('blur', function() {
            var value = $(this).val();
            if (value) {
                value = parseFloat(value.replace(/[^\d.-]/g, ''));
                if (!isNaN(value)) {
                    $(this).val(value.toFixed(2));
                }
            }
        });
    }
});
