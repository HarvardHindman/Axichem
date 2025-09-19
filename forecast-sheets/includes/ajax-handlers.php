<?php
// AJAX handler for product search
function forecast_product_search_callback() {
    // Check nonce
    check_ajax_referer('product_search_nonce', 'security');
    
    $search_term = sanitize_text_field($_POST['search_term']);
    
    if (strlen($search_term) < 3) {
        wp_send_json_error('Search term too short');
        return;
    }
    
    // Query WooCommerce products
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        's'              => $search_term,
    );
    
    $products_query = new WP_Query($args);
    $products = array();
    
    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);
            
            if (!$product) {
                continue;
            }
            
            $products[] = array(
                'id'   => $product_id,
                'name' => $product->get_name(),
                'sku'  => $product->get_sku(),
            );
        }
        wp_reset_postdata();
    }
    
    wp_send_json_success($products);
}
add_action('wp_ajax_search_products', 'forecast_product_search_callback');