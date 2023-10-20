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

                $date_on_sale_to = $wc_product->get_date_on_sale_to();
                $sale_end_date = $date_on_sale_to ? $date_on_sale_to->date('Y-m-d') : null;

                // Get dimensions
                $dimensions = $wc_product->get_dimensions();

                // Get weight
                $weight = $wc_product->get_weight();

                // Get regular price
                $regular_price = $wc_product->get_regular_price();

                // Get sale price
                $sale_price = $wc_product->get_sale_price();

                // Get current price
                $current_price = $wc_product->get_price();

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
                    'dimensions' => $dimensions,
                    'weight' => $weight,
                    'regular_price' => $regular_price,
                    'sale_price' => $sale_price,
                    'current_price' => $current_price,
                    'sale_end_date' => $sale_end_date,  // Add sale end date here
                ];
            }
        }

        return new WP_REST_Response($products, 200);
    }
}