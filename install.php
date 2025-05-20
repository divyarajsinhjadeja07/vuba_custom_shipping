<?php

$_API_KEY = '8546eda96454feae7ae54daf520ff0cb';
$_NGROK_URL = 'https://8f54-103-241-45-213.ngrok-free.app';
$shop = $_GET['shop'];
$scopes = 'read_products,read_shipping,write_shipping,read_customers';
$redirect_uri = $_NGROK_URL . '/vuba_custom_shipping/token.php';
$nonce = bin2hex( random_bytes( 12 ) ) ;
$access_mode = 'per-user';

    $aouth_url = 'https://' . $shop . '/admin/oauth/authorize?client_id=' . $_API_KEY . '&scope=' . $scopes . '&redirect_uri=' . urlencode($redirect_uri) . '&state=' . $nonce . '&grant_options[]=' . $access_mode;
    // echo $aouth_url;

    header('location: ' . $aouth_url);
    exit();

?>