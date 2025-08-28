jQuery(document).ready(function ($) {
  console.log('Document ready - initializing product search');
  
  // Function to show the loading message
  function showLoadingMessage() {
    jQuery("#loadingMessage").show();
  }

  // Function to hide the loading message
  function hideLoadingMessage() {
    jQuery("#loadingMessage").hide();
  }

  // Function to calculate and update row totals
  function updateRowTotals() {
    jQuery("#myTable tbody tr").each(function() {
      let total = 0;
      jQuery(this).find('input.data-quantity').each(function() {
        total += parseInt(jQuery(this).val()) || 0;
      });
      jQuery(this).find('.product__total').text(total);
    });
  }
  
  // Product search and autocomplete functionality
  function initializeProductSearch() {
    console.log('Initializing product search functionality');
    
    const $searchInput = jQuery('#product-search');
    const $suggestionsContainer = jQuery('#product-suggestions');
    const $suggestionsWrapper = jQuery('#product-suggestions-container');
    let currentSuggestions = [];
    let selectedIndex = -1;
    
    // Function to fetch product suggestions
    function fetchSuggestions(searchTerm) {
      if (searchTerm.length < 2) {
        $suggestionsContainer.html('').hide();
        currentSuggestions = [];
        return;
      }
      
      $suggestionsContainer.html('<div style="padding: 10px; text-align: center;">Loading...</div>').show();
      
      jQuery.ajax({
        url: ajax_object.ajaxurl,
        type: 'POST',
        data: {
          action: 'search_products',
          search_term: searchTerm,
          security: ajax_object.security
        },
        success: function(response) {
          console.log('Search response received');
          if (response.success && response.data.length > 0) {
            // Store the suggestions in memory for keyboard navigation
            currentSuggestions = response.data;
            
            // Build the HTML for suggestions
            let html = '<div style="padding: 5px 10px; background-color: #f5f5f5; font-size: 12px; border-bottom: 1px solid #ddd;">Click or press Tab to select a product</div>';
            
            response.data.forEach(function(product, index) {
              html += `
                <div class="product-item" data-id="${product.id}" data-name="${product.name}" data-index="${index}" 
                     style="padding: 8px 10px; border-bottom: 1px solid #eee; cursor: pointer;">
                  <div style="font-weight: bold;">${product.name}</div>
                  <div style="font-size: 0.9em; color: #666;">SKU: ${product.sku || 'N/A'}</div>
                </div>
              `;
            });
            
            $suggestionsContainer.html(html).show();
            selectedIndex = -1; // Reset selected index
            
            // Add click handler for selecting a product
            jQuery('.product-item').on('click', function() {
              selectProduct(jQuery(this).data('id'), jQuery(this).data('name'));
            });
            
            // Add hover handler to highlight suggestion
            jQuery('.product-item').on('mouseenter', function() {
              // Remove highlight from all items
              jQuery('.product-item').removeClass('selected-suggestion');
              // Add highlight to this item
              jQuery(this).addClass('selected-suggestion');
              selectedIndex = parseInt(jQuery(this).data('index'));
            });
          } else {
            $suggestionsContainer.html('<div style="padding: 10px; text-align: center;">No products found</div>');
            currentSuggestions = [];
          }
        },
        error: function(xhr, status, error) {
          console.error('Error searching for products:', error);
          $suggestionsContainer.html('<div style="padding: 10px; text-align: center;">Error searching for products</div>');
          currentSuggestions = [];
        }
      });
    }
    
    // Function to select a product from suggestions
    function selectProduct(productId, productName) {
      // Set the selected product in the search field
      $searchInput.val(productName);
      
      // Hide suggestions
      $suggestionsContainer.hide();
      
      // Check if product is already in the table
      if (jQuery(`#myTable tr[product-id="${productId}"]`).length === 0) {
        // Add product to table
        addProductToTable(productId, productName);
      } else {
        // Highlight existing product
        const $row = jQuery(`#myTable tr[product-id="${productId}"]`);
        $row.css('background-color', '#ffffd6');
        
        // Scroll to the product row
        if (jQuery.fn.DataTable.isDataTable('#myTable')) {
          const dataTable = jQuery('#myTable').DataTable();
          const rowNode = $row.get(0);
          const rowIndex = dataTable.row(rowNode).index();
          
          // Scroll to the row
          const scrollBody = jQuery(dataTable.table().node()).closest('.dataTables_scrollBody');
          scrollBody.animate({
            scrollTop: $row.position().top - scrollBody.position().top + scrollBody.scrollTop()
          }, 500);
        }
        
        // Reset background after delay
        setTimeout(function() {
          $row.css('background-color', '');
        }, 2000);
      }
      
      // Reset the selected index
      selectedIndex = -1;
    }
    
    // Keyup event for autocomplete suggestions
    $searchInput.on('keyup', function(e) {
      // Skip handling these keys, they'll be handled by keydown
      if (e.key === 'Enter' || e.key === 'Tab' || e.key === 'ArrowUp' || e.key === 'ArrowDown' || e.key === 'Escape') {
        return;
      }
      
      const searchTerm = jQuery(this).val();
      fetchSuggestions(searchTerm);
    });
    
    // Keydown event for keyboard navigation in dropdown
    $searchInput.on('keydown', function(e) {
      // If suggestions are not showing or empty, skip navigation
      if (!$suggestionsContainer.is(':visible') || currentSuggestions.length === 0) {
        return;
      }
      
      switch(e.key) {
        case 'ArrowDown':
          e.preventDefault();
          selectedIndex = Math.min(selectedIndex + 1, currentSuggestions.length - 1);
          highlightSuggestion();
          break;
          
        case 'ArrowUp':
          e.preventDefault();
          selectedIndex = Math.max(selectedIndex - 1, 0);
          highlightSuggestion();
          break;
          
        case 'Enter':
          e.preventDefault();
          if (selectedIndex >= 0) {
            const selected = currentSuggestions[selectedIndex];
            selectProduct(selected.id, selected.name);
          } else if (currentSuggestions.length > 0) {
            // If nothing selected but we have suggestions, select the first one
            const firstSuggestion = currentSuggestions[0];
            selectProduct(firstSuggestion.id, firstSuggestion.name);
          }
          break;
          
        case 'Tab':
          // Only prevent default if we have a selection
          if (selectedIndex >= 0 || currentSuggestions.length > 0) {
            e.preventDefault();
            if (selectedIndex >= 0) {
              const selected = currentSuggestions[selectedIndex];
              selectProduct(selected.id, selected.name);
            } else if (currentSuggestions.length > 0) {
              // If nothing selected but we have suggestions, select the first one
              const firstSuggestion = currentSuggestions[0];
              selectProduct(firstSuggestion.id, firstSuggestion.name);
            }
          }
          break;
          
        case 'Escape':
          e.preventDefault();
          $suggestionsContainer.hide();
          selectedIndex = -1;
          break;
      }
    });
    
    // Function to highlight the currently selected suggestion
    function highlightSuggestion() {
      jQuery('.product-item').removeClass('selected-suggestion');
      if (selectedIndex >= 0) {
        jQuery(`.product-item[data-index="${selectedIndex}"]`).addClass('selected-suggestion');
        
        // Ensure the selected item is visible by scrolling if needed
        const $selectedItem = jQuery(`.product-item[data-index="${selectedIndex}"]`);
        const $container = $suggestionsContainer;
        
        const itemTop = $selectedItem.position().top;
        const itemBottom = itemTop + $selectedItem.outerHeight();
        const containerTop = 0;
        const containerBottom = $container.height();
        
        if (itemTop < containerTop) {
          $container.scrollTop($container.scrollTop() + itemTop - containerTop);
        } else if (itemBottom > containerBottom) {
          $container.scrollTop($container.scrollTop() + itemBottom - containerBottom);
        }
      }
    }
    
    // Add custom styles for suggestion highlighting
    jQuery('<style>')
      .prop('type', 'text/css')
      .html(`
        .product-item.selected-suggestion {
          background-color: #e0f7ff;
          outline: 1px solid #3da8f5;
        }
        #product-suggestions {
          position: absolute;
          width: 100%;
          background: white;
          border: 1px solid #ddd;
          z-index: 9999;
          box-shadow: 0 4px 8px rgba(0,0,0,0.1);
          display: none;
          max-height: 300px;
          overflow-y: auto;
        }
      `)
      .appendTo('head');
    
    // Hide suggestions when clicking outside
    jQuery(document).on('click', function(e) {
      if (!jQuery(e.target).closest('#product-search, #product-suggestions').length) {
        $suggestionsContainer.hide();
        selectedIndex = -1;
      }
    });

    // Product search button
    jQuery('#search-button').on('click', function() {
      searchProducts();
    });

    // Also search when pressing Enter in the search field
    $searchInput.on('keypress', function(e) {
      if (e.which === 13 && !$suggestionsContainer.is(':visible')) {
        e.preventDefault();
        searchProducts();
      }
    });

    // Browse all products button
    jQuery('#browse-all-button').on('click', function() {
      $searchInput.val(''); // Clear search field
      searchProducts(); // Search with empty term to get all products
    });
    
    // Add event listener for the initial Browse Products button
    jQuery(document).on('click', '#browse-products-button', function() {
      // Clear search field and trigger search for all products
      $searchInput.val('');
      searchProducts();
      
      // Scroll to the search section
      jQuery('html, body').animate({
        scrollTop: jQuery('.product-search-container').offset().top - 50
      }, 500);
    });
  }

  // Initialize product search
  initializeProductSearch();

  // Fetch initial forecasting data on page load
  fetchProductQuantities();

  // Function to search for products
  function searchProducts() {
    const searchTerm = jQuery('#product-search').val();
    
    // If search term is too short, require at least 2 characters unless it's empty (browse all)
    if (searchTerm.length > 0 && searchTerm.length < 2) {
      alert('Please enter at least 2 characters to search');
      return;
    }
    
    jQuery('#results-container').html('<div style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 30px; height: 30px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 10px;">Searching...</p></div>');
    jQuery('#search-results').show();
    
    jQuery.ajax({
      url: ajax_object.ajaxurl,
      type: 'POST',
      data: {
        action: 'search_products',
        search_term: searchTerm,
        security: ajax_object.security
      },
      success: function(response) {
        if (response.success && response.data.length > 0) {
          displaySearchResults(response.data);
        } else {
          jQuery('#results-container').html('<p style="text-align: center; padding: 20px;">No products found. Try a different search term.</p>');
        }
      },
      error: function() {
        jQuery('#results-container').html('<p style="text-align: center; padding: 20px; color: #d63638;">Error searching for products. Please try again.</p>');
      }
    });
  }

  // Function to display search results
  function displaySearchResults(products) {
    let html = '<ul style="list-style-type: none; padding: 0;">';
    
    products.forEach(function(product) {
      // Check if product is already in the table
      const isAdded = jQuery(`#myTable tr[product-id="${product.id}"]`).length > 0;
      
      html += `
        <li style="padding: 8px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <strong>${product.name}</strong> 
            <span style="color: #666; font-size: 0.9em;">(SKU: ${product.sku || 'N/A'})</span>
            <div>${product.price}</div>
          </div>
          <button type="button" class="add-product-button" 
            data-id="${product.id}" 
            data-name="${product.name.replace(/"/g, '&quot;')}"
            ${isAdded ? 'disabled' : ''}
            style="padding: 5px 10px;">
            ${isAdded ? 'Added' : 'Add'}
          </button>
        </li>
      `;
    });
    
    html += '</ul>';
    jQuery('#results-container').html(html);
    
    // Add event listeners to the add buttons
    jQuery('.add-product-button').on('click', function() {
      const productId = jQuery(this).data('id');
      const productName = jQuery(this).data('name');
      addProductToTable(productId, productName);
      jQuery(this).text('Added').prop('disabled', true);
    });
  }

  // Function to add a product to the table
  function addProductToTable(productId, productName) {
    console.log('Adding product to table:', productId, productName);
    
    // Remove the "no products" message if it exists
    if (jQuery('#no-products-message').length) {
      jQuery('#no-products-message').remove();
    }
    
    // Create a new row for the product
    const newRow = `
      <tr product-id="${productId}">
        <td class="product__id" style="text-align:center">${productId}</td>
        <td class="product__name">${productName}</td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][01]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][02]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][03]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][04]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][05]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][06]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][07]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][08]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][09]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][10]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][11]" value="0" min="0" style="width:45px;"></td>
        <td class="product__qty" style="text-align:center"><input class="data-quantity" type="number" name="product_quantity[${productId}][12]" value="0" min="0" style="width:45px;"></td>
        <td class="product__total" style="text-align:center; font-weight:bold; background-color:#f9f9f9;">0</td>
        <td class="product__actions" style="text-align:center">
          <button type="button" class="remove-product" data-id="${productId}" style="background: none; border: none; color: red; cursor: pointer;">
            <span style="font-size: 18px;">&times;</span>
          </button>
        </td>
      </tr>
    `;
    
    // Add the new row to the table
    jQuery('#myTable tbody').append(newRow);
    
    // Reinitialize DataTables if needed
    if (jQuery.fn.DataTable.isDataTable('#myTable')) {
      jQuery('#myTable').DataTable().destroy();
      initializeDataTable();
    }
    
    // Add event listener for the quantity inputs in the new row
    jQuery(`#myTable tr[product-id="${productId}"] input.data-quantity`).on('change', function() {
      updateRowTotals();
    });
    
    // Add event listener for the remove button
    jQuery(`#myTable tr[product-id="${productId}"] .remove-product`).on('click', function() {
      removeProductFromTable(productId);
    });
    
    // Update row totals
    updateRowTotals();
    
    // Check for previously saved quantities for this product
    checkForSavedQuantities(productId);
  }
  
  // Function to remove a product from the table
  function removeProductFromTable(productId) {
    // Remove the product row
    jQuery(`#myTable tr[product-id="${productId}"]`).remove();
    
    // If there are no products left, show the "no products" message
    if (jQuery('#myTable tbody tr').length === 0) {
      jQuery('#myTable tbody').html(`
        <tr id="no-products-message">
          <td colspan="16" style="text-align:center; padding: 20px;">
            <div style="padding: 30px 15px;">
              <div style="font-size: 18px; margin-bottom: 10px; color: #666;">No products added yet</div>
              <div style="font-size: 15px; color: #888; margin-bottom: 15px;">Use the search above to find and add products to your forecast</div>
              <button type="button" id="browse-products-button" style="padding: 8px 15px; background-color: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;">Browse Products</button>
            </div>
          </td>
        </tr>
      `);
      
      // Add event listener to the browse button
      jQuery('#browse-products-button').on('click', function() {
        // Clear search field and trigger search for all products
        jQuery('#product-search').val('');
        searchProducts();
        
        // Scroll to the search section
        jQuery('html, body').animate({
          scrollTop: jQuery('.product-search-container').offset().top - 50
        }, 500);
      });
    }
    
    // Enable the add button for this product in the search results if it exists
    jQuery(`.add-product-button[data-id="${productId}"]`).text('Add').prop('disabled', false);
  }
  
  // Function to check for saved quantities for a product
  function checkForSavedQuantities(productId) {
    const selectedYear = jQuery("#year").val();
    console.log('Checking saved quantities for product ID:', productId);
    
    jQuery.ajax({
      url: ajax_object.ajaxurl,
      type: "POST",
      data: {
        action: "populate_all_product_quantities",
        selectedYear: selectedYear
      },
      success: function(response) {
        console.log('Received response for product quantities:', productId);
        
        if (!response) {
          console.log('Empty response received');
          return;
        }
        
        let productData;
        
        // Handle response in either format (string or object)
        if (typeof response === 'string') {
          try {
            console.log('Parsing string response');
            const parsed = JSON.parse(response);
            if (parsed.quantities) {
              productData = parsed.quantities;
            } else {
              productData = parsed;
            }
          } catch (e) {
            console.error('Error parsing response:', e);
            return;
          }
        } else {
          console.log('Using direct response object');
          if (response.quantities) {
            productData = response.quantities;
          } else {
            productData = response;
          }
        }
        
        if (productData && productData[productId]) {
          console.log('Found saved quantities for product:', productId, productData[productId]);
          
          // Direct approach for setting quantities
          for (let m = 1; m <= 12; m++) {
            const month = m < 10 ? '0' + m : '' + m;
            const quantity = productData[productId][month] || 0;
            const inputSelector = `input[name="product_quantity[${productId}][${month}]"]`;
            const $input = jQuery(inputSelector);
            
            if ($input.length) {
              console.log(`Setting ${month} quantity for product ${productId} to ${quantity}`);
              $input.val(quantity);
            }
          }
          
          // Update row totals after setting quantities
          updateRowTotals();
        } else {
          console.log('No saved quantities found for product:', productId);
        }
      },
      error: function(xhr, status, error) {
        console.error('Error checking saved quantities:', error);
      }
    });
  }

  // Function to fetch and populate product quantities for all products in the table
  function fetchProductQuantities() {
    showLoadingMessage(); // Show loading message
    console.log('Fetching product quantities...');

    var selectedYear = jQuery("#year").val();
    console.log('Selected year:', selectedYear);

    jQuery.ajax({
      url: ajax_object.ajaxurl, // WordPress AJAX URL
      type: "POST",
      data: {
        action: "populate_all_product_quantities",
        selectedYear: selectedYear,
      },
      success: function (response) {
        hideLoadingMessage(); // Hide loading message after success
        console.log('Response received:', response);
        
        if (!response) {
          console.log('Empty response received');
          return;
        }
        
        let productData, productNames;
        
        // Handle response in either format (string or object)
        if (typeof response === 'string') {
          try {
            console.log('Parsing string response');
            const parsed = JSON.parse(response);
            if (parsed.quantities && parsed.product_names) {
              productData = parsed.quantities;
              productNames = parsed.product_names;
            } else {
              productData = parsed;
              productNames = {};
            }
          } catch (e) {
            console.error('Error parsing response:', e);
            return;
          }
        } else {
          console.log('Using direct response object');
          if (response.quantities && response.product_names) {
            productData = response.quantities;
            productNames = response.product_names;
          } else {
            productData = response;
            productNames = {};
          }
        }
        
        const productIds = Object.keys(productData);
        console.log('Found product IDs:', productIds);
        
        // If we have saved products but they're not in the table yet, we need to add them
        if (productIds.length > 0) {
          // Remove the "no products" message if we have products
          if (jQuery('#no-products-message').length) {
            console.log('Removing no products message');
            jQuery('#no-products-message').remove();
          }
          
          // First, check which products we need to add to the table
          const existingProductIds = jQuery("#myTable tbody tr[product-id]").map(function() {
            return jQuery(this).attr('product-id');
          }).get();
          console.log('Existing product IDs in table:', existingProductIds);
          
          const missingProductIds = productIds.filter(id => !existingProductIds.includes(id));
          console.log('Missing product IDs to add:', missingProductIds);
          
          // If we have products that aren't in the table, add them
          if (missingProductIds.length > 0) {
            console.log('Adding missing products to table');
            
            // For each missing product
            const promises = missingProductIds.map(function(productId) {
              return new Promise(function(resolve) {
                // If we have the product name from the response, use it
                if (productNames && productNames[productId]) {
                  console.log('Adding product from stored name:', productId, productNames[productId]);
                  // Add the product to the table
                  addProductToTable(productId, productNames[productId]);
                  resolve();
                } else {
                  // Otherwise fetch product details via AJAX
                  console.log('Fetching product details for:', productId);
                  jQuery.ajax({
                    url: ajax_object.ajaxurl,
                    type: 'POST',
                    data: {
                      action: 'get_product_details',
                      product_id: productId,
                      security: ajax_object.security
                    },
                    success: function(response) {
                      console.log('Product details response:', response);
                      if (response.success) {
                        // Add the product to the table
                        console.log('Adding product from API:', response.data.id, response.data.name);
                        addProductToTable(response.data.id, response.data.name);
                      }
                      resolve();
                    },
                    error: function(xhr, status, error) {
                      console.error('Error fetching product details:', error);
                      resolve(); // Resolve even on error to continue with other products
                    }
                  });
                }
              });
            });
            
            // After all products are added, update quantities
            Promise.all(promises).then(function() {
              console.log('All products added, updating quantities');
              updateProductQuantities(productData);
            });
          } else {
            // If all products are already in the table, just update quantities
            updateProductQuantities(productData);
          }
        }
      },
      error: function (xhr, status, error) {
        hideLoadingMessage(); // Hide loading message on error
        console.error('Error fetching product quantities:', xhr.responseText, status, error);
      },
    });
  }
  
  // Function to update product quantities after all products are added to the table
  function updateProductQuantities(productData) {
    console.log('Updating quantities for all products in table');
    
    // For each product in the table, update its quantities
    const tableProductIds = jQuery("#myTable tbody tr[product-id]").map(function() {
      return jQuery(this).attr('product-id');
    }).get();
    
    tableProductIds.forEach(function(productId) {
      if (productData[productId]) {
        console.log('Setting quantities for product:', productId, productData[productId]);
        jQuery.each(productData[productId], function(month, quantity) {
          const inputElement = jQuery(`input[name="product_quantity[${productId}][${month}]"]`);
          if (inputElement.length) {
            inputElement.val(quantity);
          }
        });
      }
    });
    
    // Update row totals after setting all quantities
    updateRowTotals();
  }
  
  // Initialize DataTable
  function initializeDataTable() {
    console.log('Initializing DataTable');
    
    if (!jQuery.fn.DataTable) {
      console.error('DataTables library not loaded');
      return;
    }
    
    try {
      jQuery("#myTable").DataTable({
        paging: false,
        scrollCollapse: true,
        scrollY: "50vh",
        scrollX: true,
        order: [1, "asc"],
        autoWidth: false,
        info: false,
        responsive: {
          details: {
            display: $.fn.dataTable.Responsive.display.childRowImmediate,
            type: 'none',
            target: ''
          }
        },
        columnDefs: [
          { width: "80px", targets: 0 },
          { width: "250px", targets: 1 },
          { width: "60px", targets: "_all" },
          { width: "70px", targets: 14 }, // Total column width
          { width: "30px", targets: 15, orderable: false } // Actions column
        ],
        fixedColumns: {
          leftColumns: 2,
          rightColumns: 2 // Fix the total and actions columns on the right
        },
        initComplete: function() {
          // Initialize row totals after table is fully loaded
          updateRowTotals();
        }
      });
      console.log('DataTable initialized successfully');
    } catch (e) {
      console.error('Error initializing DataTable:', e);
    }
  }

  // Add an event listener to detect changes in quantity inputs
  jQuery(document).on('change', 'input.data-quantity', function() {
    // Update the row total when a quantity changes
    updateRowTotals();
  });

  // Add an event listener to detect changes in the select input for year
  jQuery(document).on("change", "#year", function () {
    fetchProductQuantities(); // Fetch new data when year changes
    // Update year when changing
    var selectedYear = jQuery("#year").val();
    jQuery('input[name="selectedYear"]').val(selectedYear);
    jQuery(".sheet-saved").hide();
  });

  // Add event listener for the initial Browse Products button
  jQuery(document).on('click', '#browse-products-button', function() {
    // Clear search field and trigger search for all products
    jQuery('#product-search').val('');
    searchProducts();
    
    // Scroll to the search section
    jQuery('html, body').animate({
      scrollTop: jQuery('.product-search-container').offset().top - 50
    }, 500);
  });
  
  // Add event listener for the close search results button
  jQuery(document).on('click', '#close-search-results', function() {
    jQuery('#search-results').hide();
  });
  
  // AJAX form submission to prevent browser warning on refresh
  jQuery(document).on('click', '#saveButton, .forecast-buttons__submit', function(e) {
    e.preventDefault();
    
    // Show loading message
    showLoadingMessage();
    
    // Determine which button was clicked
    const isSaveButton = jQuery(this).attr('id') === 'saveButton';
    const isSubmitButton = jQuery(this).hasClass('forecast-buttons__submit');
    
    // Get form data
    const formData = new FormData(document.getElementById('ForecastForm'));
    
    // Add action for AJAX request
    formData.append('action', 'save_forecast_data');
    
    // Add appropriate action based on which button was clicked
    if (isSaveButton) {
      formData.append('save_form', '1');
    } else if (isSubmitButton) {
      formData.append('submit_form', '1');
    }
    
    // Send AJAX request
    jQuery.ajax({
      url: ajax_object.ajaxurl,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response) {
        hideLoadingMessage();
        
        // Show success message
        if (response.success) {
          jQuery(".sheet-saved").text(response.data.message).show();
          
          // Scroll to the success message
          jQuery('html, body').animate({
            scrollTop: jQuery(".sheet-saved").offset().top - 100
          }, 500);
          
          // Hide the message after 5 seconds
          setTimeout(function() {
            jQuery(".sheet-saved").fadeOut();
          }, 5000);
        } else {
          alert('There was an error saving your forecast: ' + response.data);
        }
      },
      error: function(xhr, status, error) {
        hideLoadingMessage();
        console.error('AJAX error:', error);
        alert('There was an error saving your forecast. Please try again.');
      }
    });
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

// Load DataTables on AjaxStop and fetch initial product data
jQuery(document).one("ajaxStop", function () {
  console.log('AjaxStop event triggered - initializing');
  
  // Initialize DataTable
  initializeDataTable();
  
  // Load any previously saved products and quantities
  fetchProductQuantities();
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
    if (messageContainer && !isNoticeClosed()) {
        // The cookie doesn't exist, so show the message
        messageContainer.style.display = 'block';
    }
    
    // Add an event listener to the element with the "closeMessage" class
    var closeButtons = document.querySelectorAll('.closeMessage');
    if (closeButtons) {
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                // Set a cookie that expires in 1 day (24 hours)
                var expirationDate = new Date();
                expirationDate.setDate(expirationDate.getDate() + 1);
                document.cookie = 'notice_closed=true; expires=' + expirationDate.toUTCString() + '; path=/; domain=axichem.com.au';

                // Hide the message container
                if (messageContainer) {
                    messageContainer.style.display = 'none';
                }
            });
        });
    }
});

// Function to show the loading message
function showLoadingMessage() {
  jQuery("#loadingMessage").show();
}

// Function to hide the loading message
function hideLoadingMessage() {
  jQuery("#loadingMessage").hide();
}

// Function to calculate and update row totals
function updateRowTotals() {
  jQuery("#myTable tbody tr").each(function() {
    let total = 0;
    jQuery(this).find('input.data-quantity').each(function() {
      total += parseInt(jQuery(this).val()) || 0;
    });
    jQuery(this).find('.product__total').text(total);
  });
}

// Initialize DataTable
function initializeDataTable() {
  if (!jQuery.fn.DataTable) {
    console.error('DataTables library not loaded');
    return;
  }

  jQuery("#myTable").DataTable({
    paging: false,
    scrollCollapse: true,
    scrollY: "50vh",
    scrollX: true,
    order: [1, "asc"],
    autoWidth: false,
    info: false,
    responsive: {
      details: {
        display: jQuery.fn.dataTable.Responsive.display.childRowImmediate,
        type: 'none',
        target: ''
      }
    },
    columnDefs: [
      { width: "80px", targets: 0 },
      { width: "250px", targets: 1 },
      { width: "60px", targets: "_all" },
      { width: "70px", targets: 14 }, // Total column width
      { width: "30px", targets: 15, orderable: false } // Actions column
    ],
    fixedColumns: {
      leftColumns: 2,
      rightColumns: 2 // Fix the total and actions columns on the right
    },
    initComplete: function() {
      // Initialize row totals after table is fully loaded
      updateRowTotals();
    }
  });
}

  // Function to fetch and populate product quantities for all products
function fetchProductQuantities() {
  showLoadingMessage(); // Show loading message
  console.log('Fetching product quantities...');

  var selectedYear = jQuery("#year").val();
  console.log('Selected year:', selectedYear);

  jQuery.ajax({
    url: ajax_object.ajaxurl,
    type: "POST",
    data: {
      action: "populate_all_product_quantities",
      selectedYear: selectedYear,
    },
    success: function (response) {
      hideLoadingMessage(); // Hide loading message after success
      console.log('Response received:', response);
      
      if (!response) {
        console.log('Empty response received');
        return;
      }
      
      // Debug raw response
      console.log('Raw response type:', typeof response);
      if (typeof response === 'string') {
        console.log('Raw response string:', response.substring(0, 500) + '...');
      } else {
        console.log('Raw response keys:', Object.keys(response));
      }
      
      let productData, productNames;
      
      // Handle response in either format (string or object)
      if (typeof response === 'string') {
        try {
          console.log('Parsing string response');
          const parsed = JSON.parse(response);
          if (parsed.quantities && parsed.product_names) {
            productData = parsed.quantities;
            productNames = parsed.product_names;
          } else {
            productData = parsed;
            productNames = {};
          }
        } catch (e) {
          console.error('Error parsing response:', e);
          return;
        }
      } else {
        console.log('Using direct response object');
        if (response.quantities && response.product_names) {
          productData = response.quantities;
          productNames = response.product_names;
        } else {
          productData = response;
          productNames = {};
        }
      }
      
      // Deep log of the processed data
      console.log('Processed product data:', JSON.stringify(productData));
      
      const productIds = Object.keys(productData);
      console.log('Found product IDs:', productIds);
      
      // If we have saved products but they're not in the table yet, we need to add them
      if (productIds.length > 0) {
        // Remove the "no products" message if we have products
        if (jQuery('#no-products-message').length) {
          console.log('Removing no products message');
          jQuery('#no-products-message').remove();
        }
        
        // First, check which products we need to add to the table
        const existingProductIds = jQuery("#myTable tbody tr[product-id]").map(function() {
          return jQuery(this).attr('product-id');
        }).get();
        console.log('Existing product IDs in table:', existingProductIds);
        
        const missingProductIds = productIds.filter(id => !existingProductIds.includes(id));
        console.log('Missing product IDs to add:', missingProductIds);
        
        // If we have products that aren't in the table, add them
        if (missingProductIds.length > 0) {
          console.log('Adding missing products to table');
          
          // For each missing product
          const promises = missingProductIds.map(function(productId) {
            return new Promise(function(resolve) {
              // If we have the product name from the response, use it
              if (productNames && productNames[productId]) {
                console.log('Adding product from stored name:', productId, productNames[productId]);
                // Add the product to the table
                addProductToTable(productId, productNames[productId]);
                
                // Directly set quantities after adding the product
                setTimeout(function() {
                  if (productData[productId]) {
                    console.log('Setting quantities immediately for product:', productId);
                    for (let m = 1; m <= 12; m++) {
                      const month = m < 10 ? '0' + m : '' + m;
                      const quantity = productData[productId][month] || 0;
                      const inputSelector = `input[name="product_quantity[${productId}][${month}]"]`;
                      jQuery(inputSelector).val(quantity);
                    }
                  }
                }, 100);
                
                resolve();
              } else {
                // Otherwise fetch product details via AJAX
                console.log('Fetching product details for:', productId);
                jQuery.ajax({
                  url: ajax_object.ajaxurl,
                  type: 'POST',
                  data: {
                    action: 'get_product_details',
                    product_id: productId,
                    security: ajax_object.security
                  },
                  success: function(response) {
                    console.log('Product details response:', response);
                    if (response.success) {
                      // Add the product to the table
                      console.log('Adding product from API:', response.data.id, response.data.name);
                      addProductToTable(response.data.id, response.data.name);
                      
                      // Directly set quantities after adding the product
                      setTimeout(function() {
                        if (productData[productId]) {
                          console.log('Setting quantities immediately for product from API:', productId);
                          for (let m = 1; m <= 12; m++) {
                            const month = m < 10 ? '0' + m : '' + m;
                            const quantity = productData[productId][month] || 0;
                            const inputSelector = `input[name="product_quantity[${productId}][${month}]"]`;
                            jQuery(inputSelector).val(quantity);
                          }
                        }
                      }, 100);
                    }
                    resolve();
                  },
                  error: function(xhr, status, error) {
                    console.error('Error fetching product details:', error);
                    resolve(); // Resolve even on error to continue with other products
                  }
                });
              }
            });
          });
          
          // After all products are added, update quantities one more time and totals
          Promise.all(promises).then(function() {
            console.log('All products added, updating quantities and totals');
            // Use a timeout to ensure DOM is updated
            setTimeout(function() {
              setAllProductQuantities(productData);
              updateRowTotals();
            }, 300);
          });
        } else {
          // If all products are already in the table, just update quantities
          setAllProductQuantities(productData);
        }
      }
    },
    error: function (xhr, status, error) {
      hideLoadingMessage(); // Hide loading message on error
      console.error('Error fetching product quantities:', xhr.responseText, status, error);
    },
  });
}// Function to update product quantities after all products are added to the table
function updateProductQuantities(productData) {
  console.log('Updating quantities for all products in table');
  
  // For each product in the table, update its quantities
  const tableProductIds = jQuery("#myTable tbody tr[product-id]").map(function() {
    return jQuery(this).attr('product-id');
  }).get();
  
  tableProductIds.forEach(function(productId) {
    if (productData[productId]) {
      console.log('Setting quantities for product:', productId, productData[productId]);
      jQuery.each(productData[productId], function(month, quantity) {
        const inputElement = jQuery(`input[name="product_quantity[${productId}][${month}]"]`);
        if (inputElement.length) {
          inputElement.val(quantity);
        }
      });
    }
  });
  
  // Update row totals after setting all quantities
  updateRowTotals();
}

// Function to directly set all product quantities with more explicit approach
function setAllProductQuantities(productData) {
  console.log('Setting all product quantities with direct approach');
  
  // For each product ID in the data
  Object.keys(productData).forEach(function(productId) {
    const monthData = productData[productId];
    console.log('Setting quantities for product ID:', productId, monthData);
    
    // For each month 1-12
    for (let m = 1; m <= 12; m++) {
      const month = m < 10 ? '0' + m : '' + m;
      // Get quantity for this month (default to 0 if not found)
      const quantity = monthData[month] || 0;
      // Find the input field
      const inputSelector = `input[name="product_quantity[${productId}][${month}]"]`;
      const $input = jQuery(inputSelector);
      
      if ($input.length) {
        console.log(`Setting ${month} quantity for product ${productId} to ${quantity}`);
        // Set the value
        $input.val(quantity);
      } else {
        console.warn(`Input not found for product ${productId}, month ${month}:`, inputSelector);
      }
    }
  });
  
  // Force an update of row totals
  console.log('Forcing row total update');
  updateRowTotals();
}
