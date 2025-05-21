<?php

$existing_services = $shopify->rest_api('/admin/api/2024-07/carrier_services.json', [], 'GET');
$existing_services = json_decode($existing_services['body'], true);

$carrier_service_name = 'Custom Shipping Calculator'; 
 
$already_registered = false;
$carrier_service_id = null;

if (isset($existing_services['carrier_services'])) {
    foreach ($existing_services['carrier_services'] as $service) {
        if ($service['name'] === $carrier_service_name) {
            $already_registered = true;
            $carrier_service_id = $service['id'];
            break;
        }
    }
}


if (!$already_registered) {
    echo "Registering new carrier service...\n";

    $webhook_url = "https://0800-103-241-45-213.ngrok-free.app/vuba_custom_shipping/carrier_service_callback.php";

    $carrier_service = [
        "carrier_service" => [
            "name" => $carrier_service_name,
            "callback_url" => $webhook_url,
            "service_discovery" => true,
            "format" => "json"
        ]
    ];
 
    $response = $shopify->rest_api('/admin/api/2024-07/carrier_services.json', $carrier_service, 'POST');
    $response = json_decode($response['body'], true);
    print_r($response);

    if (isset($response['carrier_service'])) {
        echo "Carrier Service successfully registered! ✅";
    } else {
        echo "Failed to register Carrier Service. ❌";
    }

}else{
    echo "Carrier Service already registered! ✅ (ID: $carrier_service_id)";
}