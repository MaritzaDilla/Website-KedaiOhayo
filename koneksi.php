<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "ohayofix";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi gagal: ' . mysqli_connect_error()
    ]);
    exit;
}
?>