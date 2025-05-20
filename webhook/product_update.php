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
//file_put_contents($logs_dir . '/webhook_product_update.log', date('Y-m-d H:i:s') . ': ' . $webhook_content . PHP_EOL, FILE_APPEND);

// Check if webhook data is valid
if (!$webhook_data || !isset($webhook_data['id'])) {
    http_response_code(400);
    //file_put_contents($logs_dir . '/webhook_error.log', date('Y-m-d H:i:s') . ': Invalid webhook data' . PHP_EOL, FILE_APPEND);
    exit;
}

// Get shop info from headers
$shop_url = isset($_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN']) ? $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] : '';

// Additional debugging
//file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Processing update for shop: ' . $shop_url . PHP_EOL, FILE_APPEND);

// Verify webhook is from a valid shop
$shop_query = "SELECT id, access_token FROM shops WHERE shop_url = '{$shop_url}'";
$shop_result = $conn->query($shop_query);

if (!$shop_result || $shop_result->num_rows == 0) {
    http_response_code(401);
    //file_put_contents($logs_dir . '/webhook_error.log', date('Y-m-d H:i:s') . ': Unknown shop: ' . $shop_url . PHP_EOL, FILE_APPEND);
    exit;
}

$shop_row = $shop_result->fetch_assoc();
$shop_id = $shop_row['id'];
$access_token = $shop_row['access_token'];

// Get product data
$product_id = $webhook_data['id'];
$title = $conn->real_escape_string($webhook_data['title']);

// Log product ID for debugging
//file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Updating product ID: ' . $product_id . PHP_EOL, FILE_APPEND);

// Now get the metafields directly from the API
$pallet_amount = 0;
$excluded_from_shipping = 0;
$individual_shipping_cost = 0;

// Make API call to get product metafields
$metafields_url = "https://{$shop_url}/admin/api/2024-07/products/{$product_id}/metafields.json";
//file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Fetching metafields from: ' . $metafields_url . PHP_EOL, FILE_APPEND);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $metafields_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Shopify-Access-Token: {$access_token}"
]);

$metafields_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    $metafields_data = json_decode($metafields_response, true);
    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Metafields API response code: ' . $http_code . PHP_EOL, FILE_APPEND);
    
    // Extract metafield values
    if (isset($metafields_data['metafields']) && is_array($metafields_data['metafields'])) {
        foreach ($metafields_data['metafields'] as $metafield) {
            if ($metafield['namespace'] === 'shipping') {
                if ($metafield['key'] === 'pallet_amount') {
                    $pallet_amount = floatval($metafield['value']);
                    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Found pallet_amount: ' . $pallet_amount . PHP_EOL, FILE_APPEND);
                } else if ($metafield['key'] === 'excluded_from_shipping_calculator') {
                    $excluded_from_shipping = ($metafield['value'] === true || $metafield['value'] === 'true') ? 1 : 0;
                    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Found excluded_from_shipping: ' . $excluded_from_shipping . PHP_EOL, FILE_APPEND);
                } else if ($metafield['key'] === 'individual_shipping_cost') {
                    $individual_shipping_cost = floatval($metafield['value']);
                    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Found individual_shipping_cost: ' . $individual_shipping_cost . PHP_EOL, FILE_APPEND);
                }
            }
        }
    }
} else {
    //file_put_contents($logs_dir . '/webhook_error.log', date('Y-m-d H:i:s') . ': Failed to fetch metafields: HTTP ' . $http_code . PHP_EOL, FILE_APPEND);
}

// Process product variants
foreach ($webhook_data['variants'] as $variant) {
    // Skip variants without SKU
    if (empty($variant['sku'])) {
        continue;
    }

    $variant_id = $variant['id'];
    $sku = $conn->real_escape_string($variant['sku']);
    
    // Better weight handling - debug first
    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Original weight data: ' . json_encode($variant['weight']) . PHP_EOL, FILE_APPEND);
    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Weight unit: ' . (isset($variant['weight_unit']) ? $variant['weight_unit'] : 'not set') . PHP_EOL, FILE_APPEND);
    
    // More robust weight handling
    $weight = 0;
    if (isset($variant['weight']) && is_numeric($variant['weight'])) {
        $weight = floatval($variant['weight']);
    } else if (isset($variant['weight']) && is_string($variant['weight']) && is_numeric(trim($variant['weight']))) {
        $weight = floatval(trim($variant['weight']));
    }
    
    // Get full variant data to ensure we have everything
    $variant_url = "https://{$shop_url}/admin/api/2024-07/variants/{$variant_id}.json";
    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Fetching variant data from: ' . $variant_url . PHP_EOL, FILE_APPEND);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $variant_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: {$access_token}"
    ]);

    $variant_response = curl_exec($ch);
    $variant_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($variant_http_code >= 200 && $variant_http_code < 300) {
        $variant_data = json_decode($variant_response, true);
        if (isset($variant_data['variant']) && isset($variant_data['variant']['weight'])) {
            $weight = floatval($variant_data['variant']['weight']);
            //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Updated weight from API: ' . $weight . PHP_EOL, FILE_APPEND);
            
            if (isset($variant_data['variant']['weight_unit'])) {
                $weight_unit = $conn->real_escape_string($variant_data['variant']['weight_unit']);
            }
        }
    } else {
        //file_put_contents($logs_dir . '/webhook_error.log', date('Y-m-d H:i:s') . ': Failed to fetch variant data: HTTP ' . $variant_http_code . PHP_EOL, FILE_APPEND);
    }
    
    // Final weight logging
    //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': Final weight value: ' . $weight . ' ' . $weight_unit . PHP_EOL, FILE_APPEND);
    
    $weight_unit = $conn->real_escape_string(isset($variant['weight_unit']) ? $variant['weight_unit'] : 'kg');
    
    // Build variant title
    $variant_title = $title;
    if (count($webhook_data['variants']) > 1 && isset($variant['title']) && $variant['title'] != 'Default Title') {
        $variant_title .= ' - ' . $conn->real_escape_string($variant['title']);
    }
    
    // Check if product exists in database - first by product_id and sku
    $check_query = "SELECT * FROM products WHERE shop_id = '{$shop_id}' AND product_id = '{$product_id}' AND sku = '{$sku}'";
    $check_result = $conn->query($check_query);
    
    // If not found, try just by product_id
    if (!$check_result || $check_result->num_rows == 0) {
        $check_query = "SELECT * FROM products WHERE shop_id = '{$shop_id}' AND product_id = '{$product_id}'";
        $check_result = $conn->query($check_query);
    }
    
    // If still not found, try just by SKU
    if (!$check_result || $check_result->num_rows == 0) {
        $check_query = "SELECT * FROM products WHERE shop_id = '{$shop_id}' AND sku = '{$sku}'";
        $check_result = $conn->query($check_query);
    }
    
    if ($check_result && $check_result->num_rows > 0) {
        // Product exists, update it
        $product = $check_result->fetch_assoc();
        
        $query = "UPDATE products SET 
                 product_id = '{$product_id}',
                 title = '{$variant_title}',
                 sku = '{$sku}',
                 weight = '{$weight}',
                 weight_unit = '{$weight_unit}',
                 pallet_amount = '{$pallet_amount}',
                 excluded_from_shipping_calculator = '{$excluded_from_shipping}',
                 individual_shipping_cost = '{$individual_shipping_cost}'
                 WHERE id = {$product['id']}";
                 
        // Log the SQL for debugging
        //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': UPDATE SQL: ' . $query . PHP_EOL, FILE_APPEND);
    } else {
        // Product doesn't exist, insert it
        $query = "INSERT INTO products 
                 (product_id, shop_id, title, sku, weight, weight_unit, pallet_amount, excluded_from_shipping_calculator, individual_shipping_cost) 
                 VALUES 
                 ('{$product_id}', '{$shop_id}', '{$variant_title}', '{$sku}', '{$weight}', '{$weight_unit}', '{$pallet_amount}', '{$excluded_from_shipping}', '{$individual_shipping_cost}')";
                 
        // Log the SQL for debugging
        //file_put_contents($logs_dir . '/webhook_debug.log', date('Y-m-d H:i:s') . ': INSERT SQL: ' . $query . PHP_EOL, FILE_APPEND);
    }
              
    $result = $conn->query($query);
    
}

// Respond with success
http_response_code(200);
?>