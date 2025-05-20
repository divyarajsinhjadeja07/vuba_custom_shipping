<?php
include 'includes/connection.php';

// Create products table
$products_table = "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    shop_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    sku VARCHAR(100) NOT NULL,
    weight DECIMAL(10,2) DEFAULT 0,
    weight_unit VARCHAR(10) DEFAULT 'kg',
    pallet_amount DECIMAL(10,2) DEFAULT 0,
    excluded_from_shipping_calculator BOOLEAN DEFAULT FALSE,
    individual_shipping_cost DECIMAL(10,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (shop_id, sku)
)";

if ($conn->query($products_table)) {
    echo "Products table created successfully<br>";
} else {
    echo "Error creating products table: " . $conn->error . "<br>";
}

?>