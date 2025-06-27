<?php
header('Content-Type: application/json');
require 'koneksi.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : null;

$query = "SELECT * FROM products";
$params = [];
$types = '';

if ($id) {
    $query .= " WHERE id = ?";
    $params[] = $id;
    $types .= 'i';
} else if (!empty($search)) {
    $query .= " WHERE nama_produk LIKE ? OR kategori LIKE ? OR deskripsi LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $types .= 'sss';
}

if (!$id && empty($search)) {
    $query .= " ORDER BY id DESC"; // Tambahkan ini untuk urutkan berdasarkan ID terbaru
}

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);

$stmt->close();
$conn->close();
?>
