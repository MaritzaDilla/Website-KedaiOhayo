<?php
require_once 'koneksi.php';
session_start();
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Pastikan data pesanan ada di session
if (!isset($_SESSION['order_data'])) {
    header('Location: menu_reservasi.php');
    exit('Data pesanan tidak ditemukan');
}

$order = $_SESSION['order_data'];

// Generate order ID jika belum ada
if (!isset($order['order_id'])) {
    $order_id = 'RSV' . date('Ymd') . substr(strtoupper(uniqid()), -4);
    $order['order_id'] = $order_id;
    $_SESSION['order_data'] = $order;
}

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header('Location: menu_reservasi.php');
    exit('Order ID tidak valid');
}

$order_id = htmlspecialchars($_GET['order_id']);

$whatsappNumber = '+6285246354469';
$orderId = $order['order_id'];
$customerName = $order['customer']['nama'] ?? 'Pelanggan';
$defaultMessage = "Halo, saya telah melakukan reservasi dengan order id $orderId";
$whatsappLink = 'https://wa.me/' . $whatsappNumber . '?text=' . urlencode($defaultMessage);

// Pastikan struktur items benar
$items = [];
if (isset($order['cart']) && is_array($order['cart'])) {
    foreach ($order['cart'] as $itemName => $itemData) {
        if (isset($itemData['quantity']) && isset($itemData['price'])) {
            $items[] = [
                'nama_produk' => $itemName,
                'kuantitas' => $itemData['quantity'],
                'harga' => $itemData['price'],
                'total' => $itemData['price'] * $itemData['quantity']
            ];
        }
    }
}

// Hitung total pesanan
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['total'];
}
$pajak = $subtotal * 0.1;
$totalPembayaran = $subtotal + $pajak;
$dp = $totalPembayaran * 0.5;

// Jika ada DP yang sudah diinput manual, gunakan nilai tersebut
if (isset($order['customer']['dp']) && is_numeric($order['customer']['dp'])) {
    $dp = (float)$order['customer']['dp'];
}

// Ambil data reservasi dari session jika tersedia
$reservationDetails = $_SESSION['reservation_details'] ?? null;

// Fungsi untuk menyimpan pesanan ke database
function saveOrderToDatabase($conn, $order, $items, $subtotal, $pajak, $dp, $totalPembayaran) {
    $order_id = $order['order_id'];
    $nama_pelanggan = $order['customer']['nama'];
    $nomor_hp = $order['customer']['nomor_hp'];
    $metode_pembayaran = $order['customer']['payment_method'];
    $tipe_pesanan = $order['customer']['order_type'];
    $catatan = $order['customer']['notes'] ?? '';
    $tanggal_pesanan = date('Y-m-d H:i:s');
    $dp = (float)$dp;
    $pajak = (float)$pajak;
    $totalPembayaran = (float)$totalPembayaran;
    $jumlah_orang = $order['customer']['jumlah_orang'] ?? 1;
    $bukti_pembayaran = $order['payment_proof'] ?? null;
    $status = 'Menunggu Konfirmasi';
    
    // Mulai transaksi
    $conn->begin_transaction();

    try {
        // Cek apakah order_id sudah ada
        $checkStmt = $conn->prepare("SELECT order_id FROM reservasi WHERE order_id = ?");
        $checkStmt->bind_param("s", $order_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $checkStmt->close();
            $conn->rollback();
            return ['success' => false, 'message' => 'Pesanan sudah pernah disimpan sebelumnya'];
        }
        $checkStmt->close();
        
        // Simpan ke tabel reservasi
        $stmt = $conn->prepare("INSERT INTO reservasi (
            order_id, nama_pelanggan, nomor_hp, tanggal_pesanan, 
            jumlah_orang, metode_pembayaran, tipe_pesanan, catatan, subtotal, 
            pajak, dp, total_pembayaran, status, bukti_pembayaran
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "ssssisssddddss", 
            $order_id, $nama_pelanggan, $nomor_hp, $tanggal_pesanan,
            $jumlah_orang, $metode_pembayaran, $tipe_pesanan, $catatan, $subtotal,
            $pajak, $dp, $totalPembayaran, $status, $bukti_pembayaran
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan data pesanan: " . $stmt->error);
        }
        
        $stmt->close();

        // Simpan item pesanan ke tabel reservasi_items
        foreach ($items as $item) {
            $stmt = $conn->prepare("INSERT INTO reservasi_items (
                order_id, nama_produk, kuantitas, harga, total
            ) VALUES (?, ?, ?, ?, ?)");
            
            $stmt->bind_param(
                "ssidd", 
                $order_id, $item['nama_produk'], $item['kuantitas'], $item['harga'], $item['total']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal menyimpan item pesanan: " . $stmt->error);
            }
            
            $stmt->close();
        }

        // Commit transaksi
        $conn->commit();
        return ['success' => true, 'message' => 'Pesanan berhasil disimpan'];
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        error_log("Error saving order: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Proses konfirmasi pesanan jika ada request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    // Coba simpan ke database
    $result = saveOrderToDatabase($conn, $order, $items, $subtotal, $pajak, $dp, $totalPembayaran);
    
    if ($result['success']) {
        // Hapus session setelah berhasil disimpan
        unset($_SESSION['order_data']);
        unset($_SESSION['cart']);
        
        // Kirim response JSON
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Pesanan - Kedai Ohayo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #e63946;
            --secondary: #a8dadc;
            --accent: #f4a261;
            --dark: #1d3557;
            --light: #f1faee;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        
        .receipt-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .receipt {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .receipt-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px dashed var(--gray);
            position: relative;
        }
        
        .payment-reminder {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px 15px;
            margin: 15px 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #495057;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .payment-reminder i {
            color: #28a745;
            font-size: 16px;
        }

        .payment-reminder strong {
            color: #dc3545;
            font-weight: 600;
        }
        
        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .logo-container img {
            max-width: 120px;
            height: auto;
        }
        
        .receipt-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .receipt-subtitle {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .receipt-date {
            font-size: 14px;
            color: var(--gray);
        }
        
        .stamp {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 700;
            transform: rotate(15deg);
            font-size: 18px;
        }
        
        .receipt-body {
            padding: 20px 0;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .menu-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .menu-table th, .menu-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .menu-table th {
            font-weight: 600;
            color: var(--dark);
            background-color: #f9f9f9;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .total-section {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 16px;
            color: var(--dark);
        }
        
        .grand-total {
            font-weight: 700;
            font-size: 18px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #eee;
            color: var(--primary);
        }
        
        .receipt-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 2px dashed var(--gray);
        }
        
        .order-id {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
            background-color: var(--secondary);
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
        }
        
        .thank-you {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .notification-bar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1050;
            display: none;
            align-items: center;
            max-width: 90%;
            width: max-content;
            animation: slideUp 0.5s ease-out;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-close {
            background: none;
            border: none;
            cursor: pointer;
            margin-left: 15px;
            font-size: 16px;
        }
        
        @keyframes slideUp {
            from {
                bottom: -50px;
                opacity: 0;
            }
            to {
                bottom: 20px;
                opacity: 1;
            }
        }
        
        .contact-info {
            font-size: 14px;
            color: var(--gray);
            line-height: 1.5;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            font-size: 16px;
        }
        
        .btn-print {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-back {
            background-color: var(--dark);
            color: white;
        }
        
        .btn-cancel {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-whatsapp {
            background-color: #25D366;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .payment-proof img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
            position: relative;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s ease;
        }
        
        .close:hover,
        .close:focus {
            color: #dc3545;
        }
        
        .modal h2 {
            color: #dc3545;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.1);
        }
        
        .form-control[readonly] {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .form-help {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
        }
        
        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-modal-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-modal-submit {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-modal:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .btn-modal:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .success-notification {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-notification {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .receipt {
                box-shadow: none;
                padding: 15px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .receipt-container {
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .receipt {
                padding: 20px;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 20px;
            }
            
            .notification-bar {
                width: 90%;
                text-align: center;
                flex-direction: column;
                padding: 10px;
            }
            
            .notification-content {
                flex-direction: column;
                gap: 5px;
            }
            
            .notification-close {
                margin-top: 5px;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt">
            <div class="receipt-header">
                <div class="logo-container">
                    <img src="img/logoohayo.png" alt="Kedai Ohayo">
                </div>
                <h1 class="receipt-title">Kedai Ohayo</h1>
                <p class="receipt-subtitle">Nota Pesanan</p>
                <div class="order-id">Order ID: <?php echo htmlspecialchars($order['order_id']); ?></div>
                <span class="receipt-date"><?php echo date('d/m/Y H:i'); ?></span>
                <div class="stamp no-print">PAID</div>
            </div>
            
            <div class="receipt-body">
                <div class="section">
                    <h2 class="section-title"><i class="fas fa-user-tag"></i> Detail Pesanan</h2>
                    <div class="detail-grid">
                        <?php if ($reservationDetails): ?>
                        <!-- Tampilkan data dari reservasi -->
                        <div class="detail-item">
                            <div class="detail-label">Nama</div>
                            <div class="detail-value"><?php echo htmlspecialchars($reservationDetails['nama']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">No. HP</div>
                            <div class="detail-value"><?php echo htmlspecialchars($reservationDetails['nomor_hp']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tanggal Pesanan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($reservationDetails['tanggal']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Jam Pesanan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($reservationDetails['waktu']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Jumlah Orang</div>
                            <div class="detail-value"><?php echo htmlspecialchars($reservationDetails['jumlah_orang']); ?></div>
                        </div>
                        <?php else: ?>
                        <!-- Tampilkan data dari order -->
                        <div class="detail-item">
                            <div class="detail-label">Nama</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer']['nama'] ?? ''); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">No. HP</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer']['nomor_hp'] ?? ''); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tanggal Pesanan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer']['tanggal'] ?? date('Y-m-d')); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Jam Pesanan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer']['jam'] ?? date('H:i')); ?></div>
                        </div>
                    
                        <?php endif; ?>
                        <div class="detail-item">
                            <div class="detail-label">Metode Pembayaran</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer']['payment_method'] ?? 'Tunai'); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tipe Pesanan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer']['order_type'] ?? 'Dine-In'); ?></div>
                        </div>
                        <?php if (!empty($order['customer']['notes'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Catatan</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer']['notes']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="section">
                    <h2 class="section-title"><i class="fas fa-utensils"></i> Detail Menu</h2>
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th class="text-center">Kuantitas</th>
                                <th class="text-center">Harga Satuan</th>
                                <th class="text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nama_produk']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($item['kuantitas']) . ' ×'; ?></td>
                                <td class="text-center">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                <td class="text-right">Rp <?php echo number_format($item['total'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="total-section">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Pajak (10%):</span>
                            <span>Rp <?php echo number_format($pajak, 0, ',', '.'); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Total:</span>
                            <span>Rp <?php echo number_format($totalPembayaran, 0, ',', '.'); ?></span>
                        </div>
                        <?php if ($dp > 0): ?>
                        <div class="total-row">
                            <span>DP (50% Down Payment):</span>
                            <span>- Rp <?php echo number_format($dp, 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="total-row grand-total">
                            <span>Sisa Pembayaran:</span>
                            <span>Rp <?php echo number_format($totalPembayaran - $dp, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Tampilkan bukti pembayaran jika ada -->
                <?php if (!empty($order['payment_proof'])): ?>
                <div class="section">
                    <h2 class="section-title"><i class="fas fa-receipt"></i> Bukti Pembayaran</h2>
                    <div class="payment-proof">
                        <img src="<?php echo htmlspecialchars($order['payment_proof']); ?>" alt="Bukti Pembayaran">
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (($totalPembayaran - $dp) > 0): ?>
            <div class="payment-reminder">
                <i class="fas fa-money-bill-wave"></i>
                <span>Silakan lakukan pembayaran sisa sebesar <strong>Rp <?php echo number_format($totalPembayaran - $dp, 0, ',', '.'); ?></strong> di kedai</span>
            </div>
            <?php endif; ?>
            
            <div class="receipt-footer">
                <div class="thank-you">Terima kasih atas pesanan Anda!</div>
                <div class="contact-info">
                    Jl. Contoh No. 123, Kota Contoh<br>
                    Telp: 0812-3456-7890 | Email: hello@kedaiohayo.com
                </div>
            </div>
        </div>
        
        <div class="action-buttons no-print">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Nota
            </button>
           
            <!-- Tombol Konfirmasi WhatsApp -->
            <button id="whatsappBtn" class="btn btn-whatsapp" onclick="saveAndOpenWhatsApp()">
                <i class="fab fa-whatsapp"></i> Konfirmasi via WhatsApp
            </button>
        </div>
    </div>

    </div>
</div>

    <!-- Notification Bar -->
    <div id="notificationBar" class="notification-bar">
        <div class="notification-content">
            <i id="notificationIcon" class="fas fa-check-circle"></i>
            <span id="notificationText">Pesan notifikasi</span>
        </div>
        <button id="closeNotification" class="notification-close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <script>
         let isOrderSaved = false;
        
        // Fungsi untuk menampilkan notifikasi
        function showNotification(message, type = 'success') {
            const notificationBar = document.getElementById('notificationBar');
            const notificationText = document.getElementById('notificationText');
            const notificationIcon = document.getElementById('notificationIcon');
            
            // Set message
            notificationText.textContent = message;
            
            // Reset classes
            notificationBar.className = 'notification-bar';
            
            // Set type
            if (type === 'success') {
                notificationBar.classList.add('success-notification');
                notificationIcon.className = 'fas fa-check-circle';
            } else if (type === 'error') {
                notificationBar.classList.add('error-notification');
                notificationIcon.className = 'fas fa-exclamation-circle';
            }
            
            // Show notification
            notificationBar.style.display = 'flex';
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }
        
        // Fungsi untuk menyembunyikan notifikasi
        function hideNotification() {
            document.getElementById('notificationBar').style.display = 'none';
        }
        
        // Event listener untuk tombol close notifikasi
        document.getElementById('closeNotification').addEventListener('click', hideNotification);
        
        // Fungsi untuk menyimpan data ke database dan membuka WhatsApp
        function saveAndOpenWhatsApp() {
            const whatsappBtn = document.getElementById('whatsappBtn');
            const originalHTML = whatsappBtn.innerHTML;
            
            // Disable button dan ubah text
            whatsappBtn.disabled = true;
            whatsappBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            
            // Kirim data via AJAX untuk disimpan ke database
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'confirm_order=true'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isOrderSaved = true;
                    
                    // Data berhasil disimpan, buka WhatsApp di tab baru
                    window.open('<?php echo $whatsappLink; ?>', '_blank');
                    
                    // Tampilkan notifikasi sukses
                    showNotification('Pesanan berhasil disimpan! Anda akan diarahkan ke halaman utama.', 'success');
                    
                    // Disable semua tombol action kecuali print
                    document.querySelector('.btn-cancel').disabled = true;
                    document.querySelector('.btn-cancel').innerHTML = '<i class="fas fa-times"></i> Pesanan Dikonfirmasi';
                    
                    // Update tombol WhatsApp
                    whatsappBtn.innerHTML = '<i class="fab fa-whatsapp"></i> Pesanan Dikonfirmasi';
                    
                    // Redirect ke halaman utama setelah 3 detik
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 3000);
                } else {
                    showNotification('Gagal menyimpan pesanan: ' + data.message, 'error');
                    
                    // Kembalikan tombol ke kondisi semula
                    whatsappBtn.disabled = false;
                    whatsappBtn.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan sistem', 'error');
                
                // Kembalikan tombol ke kondisi semula
                whatsappBtn.disabled = false;
                whatsappBtn.innerHTML = originalHTML;
            });
        }

        // Fungsi untuk menampilkan form pembatalan
        function showCancelForm() {
            if (isOrderSaved) {
                showNotification('Pesanan sudah dikonfirmasi dan tidak dapat dibatalkan', 'error');
                return;
            }
            
            if (confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
                document.getElementById('cancelModal').style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            }
        }
        
        // Fungsi untuk menutup form pembatalan
        function closeCancelForm() {
            document.getElementById('cancelModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
            
            // Reset form
            document.getElementById('cancelForm').reset();
            document.getElementById('otherReasonContainer').style.display = 'none';
            document.getElementById('other_reason').removeAttribute('required');
            
            // Reset submit button
            const submitBtn = document.getElementById('cancelSubmitBtn');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Konfirmasi Pembatalan';
        }
        
        // Event listener untuk menutup modal saat klik di luar
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if (event.target === modal) {
                closeCancelForm();
            }
        }
        
        // Event listener untuk select alasan pembatalan
        document.getElementById('cancel_reason').addEventListener('change', function() {
            const otherReasonContainer = document.getElementById('otherReasonContainer');
            const otherReasonField = document.getElementById('other_reason');
            
            if (this.value === 'Lainnya') {
                otherReasonContainer.style.display = 'block';
                otherReasonField.setAttribute('required', 'required');
            } else {
                otherReasonContainer.style.display = 'none';
                otherReasonField.removeAttribute('required');
                otherReasonField.value = '';
            }
        });
        
        // Event listener untuk form pembatalan
        document.getElementById('cancelForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('cancelSubmitBtn');
            const cancelReason = document.getElementById('cancel_reason').value;
            const otherReason = document.getElementById('other_reason').value;
            const refundAmount = parseFloat(document.getElementById('refund_amount').value);
            const maxRefund = parseFloat(<?php echo $dp; ?>);
            
            // Validations
            if (!cancelReason) {
                showNotification('Silakan pilih alasan pembatalan', 'error');
                return false;
            }
            
            if (cancelReason === 'Lainnya' && !otherReason.trim()) {
                showNotification('Silakan isi alasan pembatalan', 'error');
                document.getElementById('other_reason').focus();
                return false;
            }
            
            if (refundAmount > maxRefund) {
                showNotification('Jumlah refund tidak boleh melebihi DP yang sudah dibayarkan', 'error');
                return false;
            }
            
            const confirmMessage = `Apakah Anda yakin ingin membatalkan pesanan ini?\n\nDetail pembatalan:\n- Order ID: <?php echo $order['order_id']; ?>\n- Alasan: ${cancelReason === 'Lainnya' ? otherReason : cancelReason}\n- Refund: Rp <?php echo number_format($dp, 0, ',', '.'); ?>\n\nAksi ini tidak dapat dibatalkan.`;
            
            if (!confirm(confirmMessage)) {
                return false;
            }
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Memproses...';
            
            // Submit form via AJAX
            const formData = new FormData(this);
            
            fetch('cancel_reserv.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeCancelForm();
                    
                    // Redirect setelah 3 detik
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 3000);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan sistem', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Konfirmasi Pembatalan';
            });
        });
        
        // Prevent double submission pada form
        let isSubmitting = false;
        
        // Override default form submission untuk semua form
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                
                // Reset setelah 3 detik
                setTimeout(() => {
                    isSubmitting = false;
                }, 3000);
            });
        });
        
        // Auto-hide notification setelah 5 detik
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification-bar[style*="display: flex"]');
            notifications.forEach(notification => {
                notification.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>