<?php
// filepath: /var/www/html/vuba_custom_shipping/update_pallet_rates.php
include_once('includes/connection.php');

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0777, true);
}

// Log function
function log_message($message) {
    global $logs_dir;
    file_put_contents($logs_dir . '/pallet_rates_sync.log', date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);
}

// Get the JSON data from the request
$json_data = file_get_contents('php://input');
log_message("Received data sync request");
log_message("Raw JSON received: " . substr($json_data, 0, 500) . "..."); // Log the first 500 chars of JSON

// Check if data is valid
if (empty($json_data)) {
    log_message("Error: Empty data received");
    http_response_code(400);
    echo "No data received";
    exit;
}

// Decode the JSON data
$data = json_decode($json_data, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Error decoding JSON: " . json_last_error_msg());
    http_response_code(400);
    echo "Invalid JSON data: " . json_last_error_msg();
    exit;
}

log_message("Successfully decoded JSON data with " . count($data) . " records");

// Begin transaction
$conn->begin_transaction();

try {
    // Count what we're going to process for reporting
    $inserted_count = 0;
    $updated_count = 0;
    
    foreach ($data as $item) {
        // Extract the postcode - assuming it's a key in your JSON
        if (!isset($item['Postcode'])) {
            log_message("Warning: Record missing Postcode field, skipping");
            continue;
        }
        
        $postcode = $conn->real_escape_string($item['Postcode']);
        
        // Check if this postcode already exists in the database
        $check_query = "SELECT COUNT(*) as count FROM pallet_rates WHERE postcode = '{$postcode}'";
        $check_result = $conn->query($check_query);
        $exists = false;
        
        if ($check_result && $row = $check_result->fetch_assoc()) {
            $exists = ($row['count'] > 0);
        }
        
        // Build the column and value parts for both INSERT and UPDATE
        $columns = array();
        $values = array();
        $updates = array();
        
        // Map the field names correctly - they have "pallet" or "pallets" in them
        for ($i = 1; $i <= 26; $i++) {
            // Try different possible field name formats
            $fieldName1 = $i . " pallet";  // e.g. "1 pallet"
            $fieldName2 = $i . " pallets"; // e.g. "2 pallets" 
            $dbColumn = "`{$i}`";
            
            if (isset($item[$fieldName1]) && is_numeric($item[$fieldName1])) {
                $value = floatval($item[$fieldName1]);
            } elseif (isset($item[$fieldName2]) && is_numeric($item[$fieldName2])) {
                $value = floatval($item[$fieldName2]);
            } else {
                // Skip fields not present in the import for UPDATE operations
                if ($exists) continue;
                
                // For INSERT operations, we need to provide all values
                $value = 0.00;
            }
            
            $columns[] = $dbColumn;
            $values[] = $value;
            $updates[] = "{$dbColumn} = {$value}";
        }
        
        // Execute the appropriate query based on whether the record exists
        if ($exists) {
            // UPDATE existing record
            $sql = "UPDATE pallet_rates SET " . implode(", ", $updates) . " WHERE postcode = '{$postcode}'";
            
            if (!$conn->query($sql)) {
                throw new Exception("Error updating record for postcode {$postcode}: " . $conn->error);
            }
            $updated_count++;
            log_message("Updated record for postcode: {$postcode}");
        } else {
            // INSERT new record
            $sql = "INSERT INTO pallet_rates (`postcode`, " . implode(", ", $columns) . ")
                    VALUES ('{$postcode}', " . implode(", ", $values) . ")";
            
            if (!$conn->query($sql)) {
                throw new Exception("Error inserting record for postcode {$postcode}: " . $conn->error);
            }
            $inserted_count++;
            log_message("Inserted new record for postcode: {$postcode}");
        }
    }
    
    // Commit the transaction
    $conn->commit();
    log_message("Sync completed: {$inserted_count} records inserted, {$updated_count} records updated");
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Sync completed: {$inserted_count} records inserted, {$updated_count} records updated"
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    log_message("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>