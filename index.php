<?php
echo "hello world"; die;
include 'includes/connection.php';
include 'includes/shopify.php';

$shopify = new shopify();
$parameter = $_GET;

include 'includes/check_token.php';

//include 'sync_products.php';
echo "<br>";
include 'register_carrier.php';

?>