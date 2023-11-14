<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Product_Log {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }

    public function register_api_routes() {
        register_rest_route('modena-logger_api/v1', '/products/', [
            'methods' => 'GET',
            'callback' => [$this, 'fetch_products'],
        ]);
        register_rest_route('modena-logger_api/v1', '/shipping_methods/', [
            'methods' => 'GET',
            'callback' => [$this, 'fetch_shipping_methods'],
        ]);

        // New route for creating orders
        register_rest_route('modena-logger_api/v1', '/create_order/', [
            'methods' => 'POST',
            'callback' => [$this, 'create_order'],
        ]);
    }

    public function fetch_shipping_methods() {

        $shipping_methods = $this->get_estonia_locale_shipping_methods();
        return new WP_REST_Response($shipping_methods, 200);
    }

    private function get_estonia_locale_shipping_methods() {
        $all_zones = WC_Shipping_Zones::get_zones();
        $estonia_zone_id = null;

        // Search for Estonia in shipping zones
        foreach ($all_zones as $zone_id => $zone) {
            foreach ($zone['zone_locations'] as $location) {
                if ($location->type === 'country' && $location->code === 'EE') {
                    $estonia_zone_id = $zone_id;
                    break;
                }
            }
            if ($estonia_zone_id) {
                break;
            }
        }

        $shipping_methods = [];
        if ($estonia_zone_id !== null) {
            $zone = new WC_Shipping_Zone($estonia_zone_id);
            foreach ($zone->get_shipping_methods() as $method) {
                $shipping_methods[] = array(
                    'title' => $method->get_title(),  // get_title() is a common method
                    'cost' => $method->get_instance_option('cost', 'N/A') // Getting the cost, if available
                );
            }
        }

        return $shipping_methods;
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
                $product_category_ids = $wc_product->get_category_ids();

                $categories = array();
                foreach ($product_category_ids as $category_id) {
                    $term = get_term_by('id', $category_id, 'product_cat');
                    $categories[] = array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'description' => $term->description,
                        'count' => $term->count
                    );
                }

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
                    'categories' => $categories,
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

        $billing_address = array(
            'first_name' => $params['first_name'],
            'last_name'  => $params['last_name'],
            'email'      => $params['email'],
            'company'    => $params['company'],
            'phone'      => $params['phone'],
            'address_1'  => $params['address_1'],
            'address_2'  => $params['address_2'] ?? '',  // Optional field
            'city'       => $params['city'],
            'state'      => $params['state'],
            'postcode'   => $params['postcode'],
            'country'    => $params['country']
        );

        $shipping_address = isset($params['shipping_address']) ? $params['shipping_address'] : $billing_address;

        $order = wc_create_order();

        foreach ($params['products'] as $product) {
            $order->add_product(wc_get_product($product['id']), $product['quantity']);
        }

        // Set addresses
        $order->set_address($billing_address, 'billing');
        $order->set_address($shipping_address, 'shipping');

        // Select shipping method
        if (isset($params['shipping_method'])) {
            $shipping_method_id = $params['shipping_method'];
            $shipping_method = new WC_Shipping_Rate($shipping_method_id);
            $item = new WC_Order_Item_Shipping();
            $item->set_props(array(
                'method_title' => $shipping_method->label,
                'method_id'    => $shipping_method->id,
                'total'        => $shipping_method->cost
            ));
            $order->add_item($item);
        }

        $order->calculate_totals();

        if ($order->save()) {
            return new WP_REST_Response(['status' => 'success', 'order_id' => $order->get_id()], 200);
        } else {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Order could not be created'], 500);
        }
    }
}

/*
 *  EXAMPLE POST REQUEST
 * {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "company": "Doe Enterprises",
    "phone": "1234567890",
    "address_1": "123 Main St",
    "address_2": "Apt 4",
    "city": "Anytown",
    "state": "Anystate",
    "postcode": "12345",
    "country": "EE", // Assuming Estonia based on your shipping method
    "products": [
        {
            "id": 14,
            "quantity": 1
        }
    ],
    "shipping_method": "local_pickup" // Use the ID or a unique identifier of your shipping method
}
 */