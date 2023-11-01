<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Product_Log {

    private const UNIQUE_ACCESS_KEY    = '123e4567-e89b-12d3-a456-426655440000';

    public function __construct() {
        add_filter('rest_pre_serve_request', [$this, 'allow_my_custom_header']);
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }

    public function allow_my_custom_header($value) {
        // Check if the request URL matches your custom route
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/wp-json/modena-logger_api/') !== false) {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Headers: X_UNIQUE_ACCESS_KEY, Origin, X-Requested-With, Content-Type, Accept");
        }
        return $value;
    }

    public function register_api_routes() {
        register_rest_route('modena-logger_api/v1', '/products/', [
            'methods' => 'GET',
            'callback' => [$this, 'fetch_products'],
            'permission_callback' => [$this, 'check_custom_header']  // Add this line
        ]);
        register_rest_route('modena-logger_api/v1', '/create_order/', [
            'methods' => 'POST',
            'callback' => [$this, 'create_order'],
            'permission_callback' => [$this, 'check_custom_header']  // Add this line
        ]);
    }

    public function check_custom_header($request) {
        $headers = $request->get_headers();
        $provided_access_key = isset($headers['X_UNIQUE_ACCESS_KEY']) ? $headers['X_UNIQUE_ACCESS_KEY'][0] : null;

        // Check the header
        if ($provided_access_key === self::UNIQUE_ACCESS_KEY) {
            return true;
        } else {
            return new WP_Error('rest_forbidden', 'You are not allowed to do that.', array('status' => 403));
        }
    }

    public function fetch_products(WP_REST_Request $request) {


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

                $wc_product = wc_get_product($product_id);

                $date_on_sale_to = $wc_product->get_date_on_sale_to();
                $sale_end_date = $date_on_sale_to ? $date_on_sale_to->date('Y-m-d') : null;
                $dimensions = $wc_product->get_dimensions();
                $weight = $wc_product->get_weight();
                $regular_price = $wc_product->get_regular_price();
                $sale_price = $wc_product->get_sale_price();
                $current_price = $wc_product->get_price();
                $description = $wc_product->get_description();
                $quantity_available = $wc_product->get_stock_quantity();
                $thumbnail_id = get_post_thumbnail_id($product_id);
                $thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'thumbnail-size', true);

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

    public function create_order(WP_REST_Request $request) {


        $params = $request->get_json_params();

        $address = array(
            'first_name' => $params['first_name'],
            'last_name'  => $params['last_name'],
            'company'    => '',
            'email'      => $params['email'],
            'phone'      => $params['phone'],
            'address_1'  => $params['address_1'],
            'address_2'  => '',
            'city'       => $params['city'],
            'state'      => $params['state'],
            'postcode'   => $params['postcode'],
            'country'    => $params['country']
        );

        $order = wc_create_order();

        foreach ($params['products'] as $product) {
            $order->add_product(wc_get_product($product['id']), $product['quantity']);
        }

        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');

        $order->calculate_totals();

        if ($order->save()) {
            return new WP_REST_Response(['status' => 'success', 'order_id' => $order->get_id()], 200);
        } else {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Order could not be created'], 500);
        }
    }
}