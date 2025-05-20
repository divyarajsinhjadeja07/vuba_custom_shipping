<?php
include_once('../includes/connection.php');

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0777, true);
}

// Get webhook data
$webhook_content = file_get_contents('php://input');
$webhook_data = json_decode($webhook_content, true);

// Log the incoming webhook data
//file_put_contents($logs_dir . '/webhook_product_delete.log', date('Y-m-d H:i:s') . ': ' . $webhook_content . PHP_EOL, FILE_APPEND);

// Get API version for debugging
$api_version = isset($_SERVER['HTTP_X_SHOPIFY_API_VERSION']) ? $_SERVER['HTTP_X_SHOPIFY_API_VERSION'] : 'unknown';
//file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Delete webhook API Version: ' . $api_version . PHP_EOL, FILE_APPEND);

// Check if webhook data is valid
if (!$webhook_data || !isset($webhook_data['id'])) {
    http_response_code(400);
    //file_put_contents($logs_dir . '/webhook_error.log', date('Y-m-d H:i:s') . ': Invalid webhook data for delete' . PHP_EOL, FILE_APPEND);
    exit;
}

// Get shop info from headers
$shop_url = isset($_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN']) ? $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] : '';

// Additional debugging
//file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Processing delete for shop: ' . $shop_url . PHP_EOL, FILE_APPEND);

// Verify webhook is from a valid shop
$shop_query = "SELECT id FROM shops WHERE shop_url = '{$shop_url}'";
$shop_result = $conn->query($shop_query);

if (!$shop_result || $shop_result->num_rows == 0) {
    http_response_code(401);
    //file_put_contents($logs_dir . '/webhook_error.log', date('Y-m-d H:i:s') . ': Unknown shop for delete: ' . $shop_url . PHP_EOL, FILE_APPEND);
    exit;
}

$shop_row = $shop_result->fetch_assoc();
$shop_id = $shop_row['id'];

// Get product ID
$product_id = $webhook_data['id'];
//file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Deleting product ID: ' . $product_id . PHP_EOL, FILE_APPEND);

// Get product details before deletion for better logging
$product_details_query = "SELECT * FROM products WHERE shop_id = '{$shop_id}' AND product_id = '{$product_id}'";
$product_details_result = $conn->query($product_details_query);

$product_details = [];
if ($product_details_result && $product_details_result->num_rows > 0) {
    while ($row = $product_details_result->fetch_assoc()) {
        $product_details[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'sku' => $row['sku']
        ];
    }
    
    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Found ' . count($product_details) . ' variants to delete' . PHP_EOL, FILE_APPEND);
}

// Delete product from database
$query = "DELETE FROM products WHERE shop_id = '{$shop_id}' AND product_id = '{$product_id}'";
//file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': DELETE SQL: ' . $query . PHP_EOL, FILE_APPEND);

$result = $conn->query($query);

// Respond with success
http_response_code(200);
?>