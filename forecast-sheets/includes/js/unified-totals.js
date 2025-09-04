jQuery(document).ready(function($) {
    // Initialize DataTable
    var dataTable = $('#unified-totals-table').DataTable({
        responsive: true,
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        columnDefs: [
            { responsivePriority: 1, targets: [0, 1] } // Make product name and ID highest priority columns
        ]
    });

    // Filter Type Change Handler
    $('#filter-type').on('change', function() {
        var filterType = $(this).val();
        
        // Hide all filter containers
        $('.filter-container').hide();
        
        // Show the relevant filter container
        $('#' + filterType + '-filter-container').show();
        
        // Submit the form to refresh the page with the new filter type
        $('#unified-filter-form').submit();
    });

    // Year Change Handler
    $('#year-filter').on('change', function() {
        $('#unified-filter-form').submit();
    });

    // Quarter Change Handler
    $('#quarter-filter').on('change', function() {
        $('#unified-filter-form').submit();
    });

    // Month Change Handler
    $('#month-filter').on('change', function() {
        $('#unified-filter-form').submit();
    });

    // Export functionality
    $('#export-button').on('click', function(e) {
        e.preventDefault();
        
        // Get filter values
        var filterType = $('#filter-type').val();
        var filterValue;
        
        if (filterType === 'year') {
            filterValue = $('#year-filter').val();
        } else if (filterType === 'quarter') {
            filterValue = $('#quarter-filter').val();
        } else if (filterType === 'month') {
            filterValue = $('#month-filter').val();
        }
        
        // Submit the export form
        $('#export-form input[name="export_filter_type"]').val(filterType);
        $('#export-form input[name="export_filter_value"]').val(filterValue);
        $('#export-form').submit();
    });

    // CSV Export functionality
    $('#export-csv-button').on('click', function(e) {
        e.preventDefault();
        
        // Get filter values
        var filterType = $('#filter-type').val();
        var filterValue;
        
        if (filterType === 'year') {
            filterValue = $('#year-filter').val();
        } else if (filterType === 'quarter') {
            filterValue = $('#quarter-filter').val();
        } else if (filterType === 'month') {
            filterValue = $('#month-filter').val();
        }
        
        // Submit the CSV export form
        $('#export-csv-form input[name="export_filter_type"]').val(filterType);
        $('#export-csv-form input[name="export_filter_value"]').val(filterValue);
        $('#export-csv-form').submit();
    });
});
