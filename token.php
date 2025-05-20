<?php

include 'includes/connection.php';

$api_key = '8546eda96454feae7ae54daf520ff0cb';
$secret_Key = '37a0be986d813560585a3851895233c8';
$parameters = $_GET;
$hmac = $parameters['hmac'];
$shop_url = $parameters['shop'];
$parameters = array_diff_key($parameters , array('hmac' => ''));
ksort($parameters);

$new_hmac = hash_hmac('sha256',http_build_query($parameters) , $secret_Key);

if( hash_equals($hmac,$new_hmac)){
    $access_token_endpont = 'https://' . $shop_url . '/admin/oauth/access_token';

    $var = array(
        "client_id" => $api_key,
        "client_secret" => $secret_Key,
        "code" => $parameters['code']
    );
    
    $ch = curl_init();
    curl_setopt($ch , CURLOPT_URL , $access_token_endpont);
    curl_setopt($ch , CURLOPT_RETURNTRANSFER , true);
    curl_setopt($ch , CURLOPT_POST , count($var));
    curl_setopt($ch , CURLOPT_POSTFIELDS , http_build_query($var));
    $response = curl_exec($ch);

    curl_close($ch);

    $response = json_decode($response,true);
    print_r ($response);

    $insert = "INSERT INTO `shops`(`shop_url`, `access_token`, `install_date`) VALUES ('". $shop_url ."','". $response['access_token'] ."', NOW()) ON DUPLICATE KEY UPDATE access_token= '". $response['access_token'] ."' ";

    $insertQuery = mysqli_query($conn,$insert);
    
    if($insertQuery){
        // header('location: https://' . $shop_url . '/admin/apps');
        // exit;
        echo "<script>top.window.location = 'https://" . $shop_url . "/admin/apps'</script>";
        die;
    }

}else{
    echo "your app is hacked";
}

?>