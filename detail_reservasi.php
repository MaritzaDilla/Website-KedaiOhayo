<?php
include 'koneksi.php';

if (!isset($_GET['id'])) {
  echo "ID tidak ditemukan di URL.";
  exit;
}

$id = $_GET['id'];
$q = mysqli_query($conn, "SELECT * FROM reservasi WHERE id = '$id'");
$data = mysqli_fetch_assoc($q);

if (!$data) {
  echo "Data tidak ditemukan untuk ID: $id";
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Detail Reservasi</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f8f9fa;
      margin: 0;
      padding: 40px;
    }
    .container {
      max-width: 850px;
      background: #fff;
      margin: auto;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    h2 {
      color: #343a40;
      border-bottom: 2px solid #dee2e6;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }
    p {
      margin: 8px 0;
      font-size: 16px;
    }
    .section-title {
      margin-top: 30px;
      font-size: 20px;
      color: #007bff;
      border-left: 5px solid #007bff;
      padding-left: 10px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    thead {
      background-color: #007bff;
      color: white;
    }
    th, td {
      padding: 12px;
      border: 1px solid #dee2e6;
      text-align: center;
    }
    tr:nth-child(even) {
      background-color: #f8f9fa;
    }
    .total-row td {
      font-weight: bold;
      background-color: #e2e6ea;
    }
    .highlight {
      font-weight: bold;
      background-color: #fff3cd;
      color: #856404;
    }
    .bukti-container img {
      margin-top: 15px;
      max-width: 100%;
      max-height: 400px;
      border-radius: 10px;
      border: 1px solid #ccc;
    }
    .back-btn {
      margin-top: 30px;
      display: inline-block;
      background: #007bff;
      color: white;
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      transition: 0.3s;
    }
    .back-btn:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Detail Reservasi - <?= $data['order_id']; ?></h2>

    <p><strong>Nama:</strong> <?= $data['nama_pelanggan']; ?></p>
    <p><strong>Nomor HP:</strong> <?= $data['nomor_hp']; ?></p>
    <p><strong>Tanggal Pesan:</strong> <?= $data['tanggal_pesanan']; ?></p>
    <p><strong>Jumlah Orang:</strong> <?= $data['jumlah_orang']; ?></p>
    <p><strong>Metode Pembayaran:</strong> <?= $data['metode_pembayaran']; ?></p>
    <p><strong>Status:</strong> <?= $data['status']; ?></p>
    <p><strong>Catatan:</strong> <?= $data['catatan']; ?></p>

    <div class="section-title">🍽️ Rincian Pesanan</div>
    <table>
      <thead>
        <tr>
          <th>Nama Produk</th>
          <th>Qty</th>
          <th>Harga</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $orderId = $data['order_id'];
        $queryItems = mysqli_query($conn, "SELECT * FROM reservasi_items WHERE order_id = '$orderId'");
        $grandTotal = 0;
        while ($item = mysqli_fetch_assoc($queryItems)) {
          echo "<tr>
                  <td>{$item['nama_produk']}</td>
                  <td>{$item['kuantitas']}</td>
                  <td>Rp " . number_format($item['harga'], 0, ',', '.') . "</td>
                  <td>Rp " . number_format($item['total'], 0, ',', '.') . "</td>
                </tr>";
          $grandTotal += $item['total'];
        }

        $dp = $grandTotal * 0.5;
        $sisa = $grandTotal - $dp;
        ?>
        <tr class="total-row">
          <td colspan="3">Subtotal</td>
          <td>Rp <?= number_format($grandTotal, 0, ',', '.'); ?></td>
        </tr>
        <tr class="highlight">
          <td colspan="3">DP (50%)</td>
          <td>Rp <?= number_format($dp, 0, ',', '.'); ?></td>
        </tr>
        <tr class="highlight">
          <td colspan="3">Sisa Pembayaran</td>
          <td>Rp <?= number_format($sisa, 0, ',', '.'); ?></td>
        </tr>
      </tbody>
    </table>


    <a href="admin.php" class="back-btn">⬅️ Kembali ke Admin</a>
  </div>
</body>
</html>
