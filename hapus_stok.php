<?php
header('Content-Type: application/json');
require 'koneksi.php';

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID produk tidak ditemukan']);
    exit;
}

$id = intval($_GET['id']);

try {
    // Ambil informasi gambar sebelum menghapus
    $stmt = $conn->prepare("SELECT gambar FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    // Hapus produk dari database
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        // Jika ada gambar, hapus file gambarnya
        if ($product && $product['gambar']) {
            $gambarPath = 'uploads/' . $product['gambar'];
            if (file_exists($gambarPath)) {
                unlink($gambarPath);
            }
        }
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Gagal menghapus produk');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
