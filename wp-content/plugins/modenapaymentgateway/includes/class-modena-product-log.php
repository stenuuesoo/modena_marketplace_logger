<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Product_Log {
    public function __construct() {
        $this->initialize_product_log();
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }

    public function initialize_product_log() {
        error_log("yes");
    }

    public function register_api_routes() {
        register_rest_route('modena-logger_api/v1', '/products/', [
            'methods' => 'GET',
            'callback' => [$this, 'fetch_products'],
        ]);
    }

    public function fetch_products() {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 10,
        ];

        $query = new WP_Query($args);

        $products = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();

                // Get WooCommerce product object
                $wc_product = wc_get_product($product_id);

                // Get product description
                $description = $wc_product->get_description();

                // Get available quantity (stock)
                $quantity_available = $wc_product->get_stock_quantity();

                // Get product image URL
                $thumbnail_id = get_post_thumbnail_id($product_id);
                $thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'thumbnail-size', true);

                // Create the product data array
                $products[] = [
                    'id' => $product_id,
                    'title' => get_the_title(),
                    'content' => get_the_content(),
                    'description' => $description,
                    'quantity_available' => $quantity_available,
                    'image_url' => $thumbnail_url ? $thumbnail_url[0] : '',
                    // Add more attributes here
                ];
            }
        }

        return new WP_REST_Response($products, 200);
    }

}