<?php
include_once('includes/connection.php');

$file = __DIR__ . '/CalcuratesSheet.csv';

if (!file_exists($file)) {
    die("CSV file not found.");
}

$handle = fopen($file, "r");

// Read header row: Postcode, 1 pallet, 2 pallets, ...
$headers = fgetcsv($handle);

// Clean column headers (e.g., "1 pallet" => "1")
for ($i = 1; $i < count($headers); $i++) {
    $headers[$i] = intval($headers[$i]);
}

while (($data = fgetcsv($handle)) !== false) {
    $postcode = $data[0];

    $columns = "`postcode`";
    $placeholders = "?";
    $types = "s";
    $values = [$postcode];

    for ($i = 1; $i < count($data); $i++) {
        $column = "`" . $headers[$i] . "`";
        $columns .= ", $column";
        $placeholders .= ", ?";
        $types .= "d";
        $cleanValue = str_replace(',', '', $data[$i]);
        $values[] = floatval($cleanValue);
    }

    $sql = "INSERT INTO pallet_rates ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
}

fclose($handle);
echo "Pallet rate import (column-wise) complete.\n";

?>