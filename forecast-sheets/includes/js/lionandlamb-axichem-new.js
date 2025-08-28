jQuery(document).ready(function ($) {
  // Function to show the loading message
  function showLoadingMessage() {
    jQuery("#loadingMessage").show();
  }

  // Function to hide the loading message
  function hideLoadingMessage() {
    jQuery("#loadingMessage").hide();
  }

  // Function to fetch and populate product quantities for all months
  function fetchProductQuantities() {
    showLoadingMessage(); // Show loading message

    var selectedYear = jQuery("#year").val();

    jQuery.ajax({
      url: ajax_object.ajaxurl, // WordPress AJAX URL
      type: "POST",
      data: {
        action: "populate_all_product_quantities",
        selectedYear: selectedYear,
      },
      success: function (data) {
        // Set all product quantities to 0 initially
        jQuery('input[name^="product_quantity["]').val(0);

        // Update product quantities based on the retrieved data
        if (data) {
          jQuery.each(data, function (product_id, monthData) {
            jQuery.each(monthData, function (month, quantity) {
              var inputElement = jQuery(
                'input[name="product_quantity[' + product_id + '][' + month + ']"]'
              );
              if (inputElement.length) {
                inputElement.val(quantity);
              }
            });
          });
        }

        hideLoadingMessage(); // Hide loading message after success
      },
      error: function () {
        hideLoadingMessage(); // Hide loading message on error
      },
    });
  }

  // Call the function to populate product quantities when the page loads
  fetchProductQuantities();

  // Add an event listener to detect changes in the select input for year
  jQuery(document).on("change", "#year", function () {
    fetchProductQuantities(); // Fetch new data when year changes
    // Update year when changing
    var selectedYear = jQuery("#year").val();
    jQuery('input[name="selectedYear"]').val(selectedYear);
    jQuery(".sheet-saved").hide();
  });

  // Tabs Icons 
  var sheetIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 384 512"><!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M64 464c-8.8 0-16-7.2-16-16V64c0-8.8 7.2-16 16-16H224v80c0 17.7 14.3 32 32 32h80V448c0 8.8-7.2 16-16 16H64zM64 0C28.7 0 0 28.7 0 64V448c0 35.3 28.7 64 64 64H320c35.3 0 64-28.7 64-64V154.5c0-17-6.7-33.3-18.7-45.3L274.7 18.7C262.7 6.7 246.5 0 229.5 0H64zm56 256c-13.3 0-24 10.7-24 24s10.7 24 24 24H264c13.3 0 24-10.7 24-24s-10.7-24-24-24H120zm0 96c-13.3 0-24 10.7-24 24s10.7 24 24 24H264c13.3 0 24-10.7 24-24s-10.7-24-24-24H120z"/></svg>';
  var helpIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512"><!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M464 256A208 208 0 1 0 48 256a208 208 0 1 0 416 0zM0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zm169.8-90.7c7.9-22.3 29.1-37.3 52.8-37.3h58.3c34.9 0 63.1 28.3 63.1 63.1c0 22.6-12.1 43.5-31.7 54.8L280 264.4c-.2 13-10.9 23.6-24 23.6c-13.3 0-24-10.7-24-24V250.5c0-8.6 4.6-16.5 12.1-20.8l44.3-25.4c4.7-2.7 7.6-7.7 7.6-13.1c0-8.4-6.8-15.1-15.1-15.1H222.6c-3.4 0-6.4 2.1-7.5 5.3l-.4 1.2c-4.4 12.5-18.2 19-30.6 14.6s-19-18.2-14.6-30.6l.4-1.2zM224 352a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>';
  var instructionIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 576 512"><!--! Font Awesome Free 6.4.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M249.6 471.5c10.8 3.8 22.4-4.1 22.4-15.5V78.6c0-4.2-1.6-8.4-5-11C247.4 52 202.4 32 144 32C93.5 32 46.3 45.3 18.1 56.1C6.8 60.5 0 71.7 0 83.8V454.1c0 11.9 12.8 20.2 24.1 16.5C55.6 460.1 105.5 448 144 448c33.9 0 79 14 105.6 23.5zm76.8 0C353 462 398.1 448 432 448c38.5 0 88.4 12.1 119.9 22.6c11.3 3.8 24.1-4.6 24.1-16.5V83.8c0-12.1-6.8-23.3-18.1-27.6C529.7 45.3 482.5 32 432 32c-58.4 0-103.4 20-123 35.6c-3.3 2.6-5 6.8-5 11V456c0 11.4 11.7 19.3 22.4 15.5z"/></svg>';
  var pricesIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><!--!Font Awesome Free 6.6.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--><path d="M64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-288-128 0c-17.7 0-32-14.3-32-32L224 0 64 0zM256 0l0 128 128 0L256 0zM64 80c0-8.8 7.2-16 16-16l64 0c8.8 0 16 7.2 16 16s-7.2 16-16 16L80 96c-8.8 0-16-7.2-16-16zm0 64c0-8.8 7.2-16 16-16l64 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-64 0c-8.8 0-16-7.2-16-16zm128 72c8.8 0 16 7.2 16 16l0 17.3c8.5 1.2 16.7 3.1 24.1 5.1c8.5 2.3 13.6 11 11.3 19.6s-11 13.6-19.6 11.3c-11.1-3-22-5.2-32.1-5.3c-8.4-.1-17.4 1.8-23.6 5.5c-5.7 3.4-8.1 7.3-8.1 12.8c0 3.7 1.3 6.5 7.3 10.1c6.9 4.1 16.6 7.1 29.2 10.9l.5 .1s0 0 0 0s0 0 0 0c11.3 3.4 25.3 7.6 36.3 14.6c12.1 7.6 22.4 19.7 22.7 38.2c.3 19.3-9.6 33.3-22.9 41.6c-7.7 4.8-16.4 7.6-25.1 9.1l0 17.1c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-17.8c-11.2-2.1-21.7-5.7-30.9-8.9c0 0 0 0 0 0c-2.1-.7-4.2-1.4-6.2-2.1c-8.4-2.8-12.9-11.9-10.1-20.2s11.9-12.9 20.2-10.1c2.5 .8 4.8 1.6 7.1 2.4c0 0 0 0 0 0s0 0 0 0s0 0 0 0c13.6 4.6 24.6 8.4 36.3 8.7c9.1 .3 17.9-1.7 23.7-5.3c5.1-3.2 7.9-7.3 7.8-14c-.1-4.6-1.8-7.8-7.7-11.6c-6.8-4.3-16.5-7.4-29-11.2l-1.6-.5s0 0 0 0c-11-3.3-24.3-7.3-34.8-13.7c-12-7.2-22.6-18.9-22.7-37.3c-.1-19.4 10.8-32.8 23.8-40.5c7.5-4.4 15.8-7.2 24.1-8.7l0-17.3c0-8.8 7.2-16 16-16z"/></svg>';

  jQuery('.woocommerce-MyAccount-navigation-link--forecast-sheet .ahfb-svg-iconset').html(sheetIcon);
  jQuery('.woocommerce-MyAccount-navigation-link--forecast-help .ahfb-svg-iconset').html(helpIcon);
  jQuery('.woocommerce-MyAccount-navigation-link--forecast-instructions .ahfb-svg-iconset').html(instructionIcon);
  jQuery('.woocommerce-MyAccount-navigation-link--price-list .ahfb-svg-iconset').html(pricesIcon);
});

// Load DataTables on AjaxStop
jQuery(document).one("ajaxStop", function ($) {
  jQuery("#myTable").DataTable({
    paging: false,
    scrollCollapse: true,
    scrollY: "50vh",
    order: [1, "asc"],
    autoWidth: true,
    info: false,
    responsive: true,
  });
});

// Add Cookie
document.addEventListener('DOMContentLoaded', function() {
    var messageContainer = document.querySelector('.customers-message');

    // Function to check if the "notice_closed" cookie is set
    function isNoticeClosed() {
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i].trim();
            if (cookie.indexOf('notice_closed=') === 0) {
                return true;
            }
        }
        return false;
    }

    // Check if the "notice_closed" cookie is set
    if (!isNoticeClosed()) {
        // The cookie doesn't exist, so show the message
        messageContainer.style.display = 'block';
    }
    
    // Add an event listener to the element with the "closeMessage" class
    var closeButtons = document.querySelectorAll('.closeMessage');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Set a cookie that expires in 1 day (24 hours)
            var expirationDate = new Date();
            expirationDate.setDate(expirationDate.getDate() + 1);
            document.cookie = 'notice_closed=true; expires=' + expirationDate.toUTCString() + '; path=/; domain=axichem.com.au';

            // Hide the message container
            messageContainer.style.display = 'none';
        });
    });
});
