<?php
include_once('includes/connection.php');

// Get shop details
$result = $conn->query("SELECT id, shop_url, access_token FROM shops WHERE id = 1 LIMIT 1");

$shop = $result->fetch_assoc();

if (!$shop) {
    die("No shop found. Please install the app first.");
}

$shop_id = $shop['id'];
$shop_url = $shop['shop_url'];
$access_token = $shop['access_token'];

echo "Starting product sync for shop: {$shop_url}<br>";

// Initialize variables for pagination
$page_info = null;
$has_next_page = true;
$processed_count = 0;
$limit = 250; // Maximum products allowed by Shopify API per request

while ($has_next_page) {
    // Construct the API URL with status=any to get all products (including draft)
    $url = "https://{$shop_url}/admin/api/2023-10/products.json?limit={$limit}";
    if ($page_info) {
        $url .= "&page_info=" . urlencode($page_info);
    }
    
    echo "Requesting page of products: " . htmlspecialchars($url) . "<br>";
    flush(); // Flush output buffer to show progress
    
    // Make the API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Get headers for pagination
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: {$access_token}"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    // Split response into headers and body
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    curl_close($ch);
    
    // Check if response is valid
    if (!$body || $http_code >= 400) {
        echo "Error fetching products: HTTP code {$http_code}<br>";
        echo "Response: " . htmlspecialchars($body) . "<br>";
        break;
    }
    
    $data = json_decode($body, true);
    
    if (!isset($data['products']) || !is_array($data['products'])) {
        echo "Error fetching products: Invalid response format<br>";
        echo "Response: " . htmlspecialchars(substr($body, 0, 500)) . "...<br>";
        break;
    }
    
    $products_count = count($data['products']);
    echo "Found {$products_count} products in this batch<br>";
    flush();
    
    if ($products_count == 0) {
        echo "No more products to sync.<br>";
        break;
    }
    
    // Process each product
    foreach ($data['products'] as $product) {
        $product_id = $product['id'];
        $title = htmlspecialchars($product['title']);
        echo "Processing product: {$title} (ID: {$product_id})<br>";
        flush();
        
        // Get product metafields
        $metafields_url = "https://{$shop_url}/admin/api/2023-10/products/{$product_id}/metafields.json";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $metafields_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Shopify-Access-Token: {$access_token}"
        ]);
        
        $metafields_response = curl_exec($ch);
        $metafields_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($metafields_http_code >= 400) {
            echo "Error fetching metafields for product {$product_id}: HTTP code {$metafields_http_code}<br>";
            continue;
        }
        
        $metafields_data = json_decode($metafields_response, true);
        
        // Extract metafield values
        $pallet_amount = 0;
        $excluded_from_shipping = 0;
        $individual_shipping_cost = 0;
        
        if (isset($metafields_data['metafields']) && is_array($metafields_data['metafields'])) {
            foreach ($metafields_data['metafields'] as $metafield) {
                if ($metafield['namespace'] === 'shipping') {
                    if ($metafield['key'] === 'pallet_amount') {
                        $pallet_amount = floatval($metafield['value']);
                    } else if ($metafield['key'] === 'excluded_from_shipping_calculator') {
                        $excluded_from_shipping = $metafield['value'] === true || $metafield['value'] === 'true' ? 1 : 0;
                    } else if ($metafield['key'] === 'individual_shipping_cost') {
                        $individual_shipping_cost = floatval($metafield['value']);
                    }
                }
            }
        }
        
        // Process each variant
        foreach ($product['variants'] as $variant) {
            $variant_id = $variant['id'];
            $variant_title = $conn->real_escape_string($product['title'] . (count($product['variants']) > 1 ? ' - ' . $variant['title'] : ''));
            $sku = $conn->real_escape_string($variant['sku'] ?: ('NSKU-' . $variant_id));
            $weight = floatval($variant['weight'] ?: 0);
            $weight_unit = $conn->real_escape_string(isset($variant['weight_unit']) ? $variant['weight_unit'] : 'kg');
            
            // Insert or update product in database using separate connection to avoid max_input_vars issue
            // This uses a direct connection to avoid form submission limits
            $query = "INSERT INTO products 
                      (product_id, shop_id, title, sku, weight, weight_unit, pallet_amount, excluded_from_shipping_calculator, individual_shipping_cost) 
                      VALUES ({$product_id}, {$shop_id}, '{$variant_title}', '{$sku}', {$weight}, '{$weight_unit}', {$pallet_amount}, {$excluded_from_shipping}, {$individual_shipping_cost})
                      ON DUPLICATE KEY UPDATE 
                      title = '{$variant_title}', 
                      weight = {$weight},
                      weight_unit = '{$weight_unit}',
                      pallet_amount = {$pallet_amount}, 
                      excluded_from_shipping_calculator = {$excluded_from_shipping}, 
                      individual_shipping_cost = {$individual_shipping_cost}";
            
            if (!$conn->query($query)) {
                echo "Error syncing variant {$variant_id}: " . $conn->error . "<br>";
            } else {
                $processed_count++;
            }
        }
        
        echo "→ Synced " . count($product['variants']) . " variants for product {$title}<br>";
        flush();
    }
    
    // Check for Link header to handle pagination
    $page_info = null;
    $has_next_page = false;
    
    if (preg_match('/<([^>]*)>; rel="next"/', $headers, $matches)) {
        // Extract the page_info parameter from the next link
        $next_link = $matches[1];
        if (preg_match('/page_info=([^&]+)/', $next_link, $page_matches)) {
            $page_info = $page_matches[1];
            $has_next_page = true;
            echo "Found next page, continuing pagination...<br>";
        }
    }
    
    echo "Processed {$processed_count} product variants so far...<br>";
    flush(); // Ensure output is displayed
    
    // Add a delay to avoid API rate limits
    sleep(2);
}

echo "<strong>Completed syncing products. Total processed: {$processed_count}</strong><br>";
?>