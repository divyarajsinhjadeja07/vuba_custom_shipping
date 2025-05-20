<?php
function calculate_shipping($conn, $total_weight, $products, $postcode, $excluded_cost) {
    $shipping_cost = 0;

    // Calculate box shipping for weight under 100kg
    if ($total_weight < 100) {
        $box_count = ceil($total_weight / 25); // 25kg per box
        $shipping_cost += $box_count * 7.99;
        //file_put_contents("log.txt", "Calculating box shipping for weight: " . $total_weight . " box count: " . $box_count . " shipping_cost: " . $shipping_cost . "\n" , FILE_APPEND);
    } else {
     // Calculate pallet shipping for weight 100kg or more
     $total_pallets = 0;

     // Calculate total pallets based on product data
     foreach ($products as $product) {
         $pallet_per_unit = $product['pallet_per_unit'];
         $quantity = $product['quantity'];
         $total_pallets += $pallet_per_unit * $quantity;
     }

     $total_pallets = ceil($total_pallets); // Round up to the nearest whole number

     // Reverse match postcode
     $matched_postcode = null;
     for ($i = strlen($postcode); $i > 0; $i--) {
         $partial_postcode = substr($postcode, 0, $i);
         $query = "SELECT `$total_pallets` AS shipping_cost FROM pallet_rates WHERE postcode = '$partial_postcode' LIMIT 1";
         //file_put_contents("log.txt", "Query: " . $query . "\n", FILE_APPEND);

         $result = mysqli_query($conn, $query);
         if ($result && $result->num_rows > 0) {
             $row = $result->fetch_assoc();
             $shipping_cost += $row['shipping_cost'];
             $matched_postcode = $partial_postcode;
             break;
         }
     }

     if (!$matched_postcode) {
         //file_put_contents("log.txt", "No matching postcode found for: " . $postcode . "\n", FILE_APPEND);
     } else {
         //file_put_contents("log.txt", "Matched Postcode: " . $matched_postcode . "\n", FILE_APPEND);
     }
 }

 // Add excluded product shipping cost
 $shipping_cost += $excluded_cost;

 return $shipping_cost;
}
?>