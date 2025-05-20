<?php
include 'includes/connection.php';
include 'logic/calculate_shipping.php';

// Parse incoming request
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);
$shipping_address = $data['rate']['destination'];
$cart_items = $data['rate']['items'];

$postcode = $shipping_address['postal_code'];

$total_weight = 0;
$excluded_cost = 0;
$products = []; // Array to store product details for pallet calculation

foreach ($cart_items as $item) {
    $sku = $item['sku'];
    $quantity = $item['quantity'];

    // Fetch product details from the database
    $result = $conn->query("SELECT * FROM products WHERE sku = '$sku' LIMIT 1");

    if ($result->num_rows === 0) continue;

    $product = $result->fetch_assoc();

    if ($product['excluded_from_shipping_calculator']) {
        // Calculate excluded cost
        $excluded_cost += $product['individual_shipping_cost'] * $quantity;
        file_put_contents("log.txt", "Excluded Product: " . $product['title'] . " - Cost: " . $excluded_cost . "\n", FILE_APPEND);
    } else {
        // Calculate total weight for non-excluded products
        $total_weight += $product['weight'] * $quantity;

        // Add product details for pallet calculation
        $products[] = [
            'pallet_per_unit' => $product['pallet_amount'],
            'quantity' => $quantity
        ];

        file_put_contents("log.txt", "Product: " . $product['title'] . " - Weight: " . $product['weight'] . " - Pallets: " . $product['pallet_amount'] . "\n", FILE_APPEND);
    }
}

// Calculate shipping cost for non-excluded products
$non_excluded_shipping_cost = 0;
if ($total_weight > 0) {
    $non_excluded_shipping_cost = calculate_shipping($conn, $total_weight, $products, $postcode, 0);
}

// Final shipping cost = excluded cost + non-excluded shipping cost
$shipping_cost = $excluded_cost + $non_excluded_shipping_cost;

// Log the calculated shipping cost
file_put_contents("log.txt", "Excluded Cost: " . $excluded_cost . "\n", FILE_APPEND);
file_put_contents("log.txt", "Non-Excluded Shipping Cost: " . $non_excluded_shipping_cost . "\n", FILE_APPEND);
file_put_contents("log.txt", "Final Shipping Cost (before sending to Shopify): " . $shipping_cost . "\n", FILE_APPEND);

// Return shipping rates to Shopify
$response = [
    "rates" => [[
        "service_name" => "Custom Shipping",
        "service_code" => "CUSTOM_SHIP",
        "total_price" => round($shipping_cost * 100), // in cents
        "currency" => "GBP",
        "description" => "Calculated based on your order weight and location"
    ]]
];
// Log the response being sent to Shopify
file_put_contents("log.txt", "Response to Shopify: " . json_encode($response) . "\n", FILE_APPEND);

header('Content-Type: application/json');
echo json_encode($response);
?>