<?php

$select = "SELECT * FROM shops WHERE shop_url = '". $parameter['shop'] ."'";

$selectQuery = mysqli_query($conn,$select);

if(mysqli_num_rows($selectQuery) < 1){
    header("location: install.php?shop=" . $_GET['shop']);
    exit();
}

$shopData = mysqli_fetch_assoc($selectQuery);

$shopify->set_url($parameter['shop']);
$shopify->set_access_token($shopData['access_token']);
$shop = $shopify->rest_api('/admin/api/2024-07/shop.json' , array() , 'GET');

$response = json_decode($shop['body'],true);

if(array_key_exists('errors',$response)){
    header("location: install.php?shop=" . $_GET['shop']);
    exit();
}


?>