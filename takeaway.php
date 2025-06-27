<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
?>

<?php
include 'koneksi.php';

// Filter
$tanggal = $_GET['tanggal'] ?? '';
$metode = $_GET['metode'] ?? '';

// Query dasar
$sql = "SELECT * FROM reservasi WHERE tipe_pesanan = 'Takeaway'";

// Tambah filter jika ada input
if (!empty($tanggal)) {
  $sql .= " AND DATE(tanggal_pesanan) = '$tanggal'";
}
if (!empty($metode) && $metode != 'Semua') {
  $sql .= " AND metode_pembayaran = '$metode'";
}

$sql .= " ORDER BY id DESC";
$data = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Manajemen Pesanan Takeaway</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
    body { display: flex; min-height: 100vh; background: #f4f4f4; }

    .sidebar {
      width: 220px;
      background: #343a40;
      padding: 20px;
      color: white;
    }
    .sidebar h2 { font-size: 20px; margin-bottom: 30px; text-align: center; }
    .sidebar a {
      display: block;
      color: white;
      text-decoration: none;
      background: #495057;
      padding: 12px;
      margin-bottom: 10px;
      border-radius: 6px;
    }
    .sidebar a.active, .sidebar a:hover {
      background: #007bff;
    }

    .content {
      flex: 1;
      padding: 30px;
    }
    h1 { margin-bottom: 20px; }

    form.filter {
      margin-bottom: 20px;
      display: flex;
      gap: 10px;
      align-items: center;
    }
    form input, form select {
      padding: 6px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    table {
      width: 100%;
      background: white;
      border-collapse: collapse;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    th, td {
      padding: 12px;
      border: 1px solid #ddd;
      text-align: center;
    }
    th {
      background: #007bff;
      color: white;
    }

    .btn {
      padding: 6px 10px;
      font-size: 14px;
      text-decoration: none;
      color: white;
      border-radius: 4px;
    }
    .btn-detail { background: #dc3545; }
    .btn-wa { background: #25d366; }
    .btn-pdf { background: #6c757d; margin-top: 4px; display: inline-block; }
    select.status-dropdown {
      padding: 5px;
      border-radius: 4px;
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>Kedai Ohayo</h2>
  <a href="admin.php">📆 Reservasi</a>
  <a class="active" href="takeaway.php">🥡 Takeaway</a>
  <a href="stok.php">📦 Stok Produk</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="content">
  <h1>Manajemen Pesanan Takeaway</h1>

  <form method="GET" class="filter">
    <label>Tanggal: <input type="date" name="tanggal" value="<?= $tanggal ?>"></label>
    <label>Metode Pembayaran:
      <select name="metode">
        <option value="Semua">Semua</option>
        <option value="Tunai" <?= $metode == 'Tunai' ? 'selected' : '' ?>>Tunai</option>
        <option value="Transfer" <?= $metode == 'Transfer' ? 'selected' : '' ?>>Transfer</option>
      </select>
    </label>
    <button type="submit">Filter</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Nama</th>
        <th>Telepon</th>
        <th>Waktu Pengambilan</th>
        <th>Metode Pembayaran</th>
        <th>Total</th>
        <th>Tanggal Pesanan</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php
    while ($r = mysqli_fetch_assoc($data)) {
      echo "<tr>
        <td>{$r['id']}</td>
        <td>{$r['nama_pelanggan']}</td>
        <td>{$r['nomor_hp']}</td>
        <td>Sekarang</td>
        <td>{$r['metode_pembayaran']}</td>
        <td>Rp " . number_format($r['total_pembayaran'], 0, ',', '.') . "</td>
        <td>" . date('d/m/Y H:i', strtotime($r['tanggal_pesanan'])) . "</td>
        <td>
          <a class='btn btn-detail' href='detail_reservasi.php?id={$r['id']}'>Detail</a>
          <a class='btn btn-wa' href='https://wa.me/62" . substr($r['nomor_hp'], 1) . "?text=Pesanan Anda: {$r['order_id']} sedang diproses' target='_blank'>WA</a>
          <a class='btn btn-pdf' href='nota_reservasi.php?id={$r['id']}' target='_blank'>PDF</a><br>
          <form method='post' action='update.php' style='margin-top:4px;'>
            <input type='hidden' name='id' value='{$r['id']}'>
            <select name='status' class='status-dropdown' onchange='this.form.submit()'>
              <option " . ($r['status'] == 'Pending' ? 'selected' : '') . ">Pending</option>
              <option " . ($r['status'] == 'Diproses' ? 'selected' : '') . ">Diproses</option>
              <option " . ($r['status'] == 'Selesai' ? 'selected' : '') . ">Selesai</option>
            </select>
          </form>
        </td>
      </tr>";
    }
    ?>
    </tbody>
  </table>
</div>
</body>
</html>
