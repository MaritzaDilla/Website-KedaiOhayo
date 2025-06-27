<?php
require_once 'koneksi.php';
session_start();

// Terima data JSON dari request
$json = file_get_contents('php://input');
$orderData = json_decode($json, true);

// Proses bukti pembayaran jika ada
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/payment_proofs/';
    
    // Buat direktori jika belum ada
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate nama file unik
    $fileName = uniqid() . '_' . basename($_FILES['payment_proof']['name']);
    $targetPath = $uploadDir . $fileName;
    
    // Upload file
    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
        $orderData['payment_proof'] = $targetPath;
    }
}

// Simpan data pesanan ke dalam session
$_SESSION['order_data'] = $orderData;

// Pastikan data reservasi tersedia untuk nota_pesanan.php
// Jika tidak ada data reservasi di session, gunakan data kosong
if (!isset($_SESSION['takeaway_details'])) {
    $_SESSION['takeaway_details'] = [
        'nama' => 'Pelanggan',
        'nomor_hp' => '-',
        'tanggal' => date('Y-m-d'),
    ];
}
// Kirim response
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>