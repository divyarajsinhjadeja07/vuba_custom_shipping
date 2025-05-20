<?php
$raw_input = file_get_contents("php://input");
$input = json_decode($raw_input, true);
// file_put_contents("log.txt", "Raw Data" . $raw_input . "\n" , FILE_APPEND);

$items = $input['rate']['items']; // Array of products
$total_weight = 0;


// Loop through all items
foreach ($items as $item) {
    $item_weight = $item['grams'];
    $quantity = $item['quantity'];
    $item_total_weight = $item_weight * $quantity;
    $total_weight += $item_total_weight;

    // Log each item details
    // file_put_contents("log.txt", "Item weight: $item_weight grams, Quantity: $quantity, Item total weight: $total_weight grams\n", FILE_APPEND);
}

$total_weight_kg = $total_weight / 1000;
// file_put_contents("log.txt", "Total weight: $total_weight_kg kg\n", FILE_APPEND);
// exit;

// Box shipping logic
if ($total_weight_kg < 100) {

    $box_capacity_kg = 25;
    $box_price = 7.99;

    $num_boxes = ceil($total_weight_kg / $box_capacity_kg);
    // file_put_contents("log.txt", "Number of boxes: $num_boxes\n", FILE_APPEND);
    $shipping_cost = $num_boxes * $box_price;

    // Respond with rate
    $response = [
        "rates" => [
            [
                "service_name" => "Box Shipping",
                "service_code" => "BOX_SHIPPING",
                "total_price" => (int)($shipping_cost * 100), // convert £ to pence
                "currency" => "GBP",
                "description" => "$num_boxes box(es) at £7.99/box"
            ]
        ]
    ];

    // Log and return
    //file_put_contents("log.txt", "Box shipping applied. Total weight: $total_weight_kg kg, Boxes: $num_boxes, Cost: £$shipping_cost\n", FILE_APPEND);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}else {
    //file_put_contents("log.txt", "Raw Data" . $raw_input . "\n" , FILE_APPEND);
       // Pallet shipping logic
       $total_weight_kg = $total_weight / 1000;
       $pallet_units = 0;
       $individual_shipping_cost = 0;
   
       foreach ($items as $item) {
           $quantity = $item['quantity'];
           $properties = $item['properties'] ?? [];
   
           //file_put_contents("log.txt", "Quantity: $quantity\n" . "properties: $properties\n", FILE_APPEND);
   
           $excluded = isset($properties['excluded_from_shipping_calculator']) && strtolower($properties['excluded_from_shipping_calculator']) === 'true';
   
           if ($excluded) {
            //file_put_contents("log.txt", "Item excluded from shipping calculator\n", FILE_APPEND);
               $individual_cost = isset($properties['individual_shipping_cost']) ? (float)$properties['individual_shipping_cost'] : 0;
               $individual_shipping_cost += $individual_cost * $quantity;
           } else {
               $pallet_amount = isset($properties['pallet_amount']) ? (float)$properties['pallet_amount'] : 0;
               $pallet_units += $pallet_amount * $quantity;
               //file_put_contents("log.txt", "Pallet amount: $pallet_amount, Quantity: $quantity, Total pallet units: $pallet_units\n", FILE_APPEND);
           }
       }
   
       $pallets_required = ceil($pallet_units);
   
       // Read from Google Sheet CSV
       $csv_url = "https://docs.google.com/spreadsheets/d/1qhKbZCcvS-oonlTjyOLwl4QAynB9LzIA/export?format=csv";
       $postcode_prices = [];
       if (($handle = fopen($csv_url, "r")) !== false) {
           while (($data = fgetcsv($handle, 1000, ",")) !== false) {
               if (count($data) >= 2) {
                   $postcode = strtoupper(trim($data[0]));
                   $price = floatval($data[1]);
                   $postcode_prices[$postcode] = $price;
               }
           }
           fclose($handle);
       }
   
       // Get postcode prefix
       $destination_postcode = strtoupper($input['rate']['destination']['postal_code']);
       $postcode_prefix = substr($destination_postcode, 0, 4);
       $base_price = $postcode_prices[$postcode_prefix] ?? 99.99;
   
       $pallet_shipping_cost = $pallets_required * $base_price;
       $total_shipping = $pallet_shipping_cost + $individual_shipping_cost;
   
       $response = [
           "rates" => [
               [
                   "service_name" => "Pallet Shipping",
                   "service_code" => "PALLET_SHIPPING",
                   "total_price" => (int)($total_shipping * 100), // Convert to pence
                   "currency" => "GBP",
                   "description" => "$pallets_required pallet(s) at £$base_price + individual items"
               ]
           ]
       ];
   
       header('Content-Type: application/json');
       echo json_encode($response);
       exit;
}
?> 