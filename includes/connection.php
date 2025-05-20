<?php

$servername = "localhost";
$username = "root";
$password = "admin@123";
$dbname = "vuba_custom_shipping";

$conn = mysqli_connect($servername,$username,$password,$dbname);

if(!$conn){
    die("Error: " . mysqli_connect_error());
}

?>