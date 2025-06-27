<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}
include 'koneksi.php';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin Panel - Kedai Ohayo</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }
    body { display: flex; min-height: 100vh; background: #f8f9fa; }

    .sidebar {
      width: 220px;
      background: #343a40;
      color: white;
      padding: 20px;
    }
    .sidebar h2 {
      font-size: 20px; margin-bottom: 30px; text-align: center;
    }
    .sidebar a {
      display: block;
      padding: 12px 16px;
      margin-bottom: 10px;
      background: #495057;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      transition: 0.3s;
    }
    .sidebar a:hover { background: #17a2b8; }
    .sidebar .active { background: #007bff; }

    .content { flex: 1; padding: 30px; }
    h1 { font-size: 24px; margin-bottom: 20px; }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    th, td {
      padding: 12px;
      border: 1px solid #dee2e6;
      text-align: center;
      vertical-align: middle;
    }
    th { background: #007bff; color: white; }

    .btn {
      padding: 6px 12px;
      color: white;
      text-decoration: none;
      border-radius: 4px;
      font-size: 14px;
    }
    .btn-detail { background: #dc3545; margin-bottom: 6px; }

    .status-form {
      display: flex;
      justify-content: center;
      gap: 6px;
      flex-wrap: wrap;
    }
    .status-select {
      padding: 6px 10px;
      font-size: 14px;
      border: 1px solid #ccc;
      border-radius: 6px;
      background: #f1f1f1;
    }
    .btn-status {
      background: #28a745;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 6px 10px;
      cursor: pointer;
      transition: background 0.3s ease;
      font-weight: bold;
    }
    .btn-status:hover { background: #218838; }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      font-size: 13px;
      border-radius: 12px;
      font-weight: bold;
      color: white;
    }
    .badge-pending { background: #ffc107; color: #212529; }
    .badge-diproses { background: #17a2b8; }
    .badge-selesai { background: #28a745; }

    .aksi-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    img.bukti {
      max-width: 120px;
      max-height: 120px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid #ccc;
      cursor: pointer;
      transition: 0.2s;
    }
    img.bukti:hover { transform: scale(1.05); }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      padding-top: 80px;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.8);
    }
    .modal-content {
      margin: auto;
      display: block;
      max-width: 90%;
      max-height: 80vh;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.5);
    }
    .close {
      position: absolute;
      top: 30px;
      right: 35px;
      color: #fff;
      font-size: 40px;
      font-weight: bold;
      cursor: pointer;
    }
    .close:hover { color: #f00; }
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>Kedai Ohayo<br><small>Panel Admin</small></h2>
    <a href="admin.php" class="active">📆 Reservasi</a>
    <a href="takeaway.php">🥡 Takeaway</a>
    <a href="stok.php">📦 Stok Produk</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="content">
    <h1>Manajemen Data Reservasi</h1>
    <table>
      <thead>
  <tr>
    <th>ID</th>
    <th>Nama</th>
    <th>Nomor HP</th>
    <th>Tanggal Pesan</th>
    <th>Total (DP 50%)</th> <!-- ubah judul kolom -->
    <th>Status</th>
    <th>Bukti Pembayaran</th>
    <th>Aksi</th>
  </tr>
</thead>
<tbody>
  <?php
  $q = mysqli_query($conn, "SELECT * FROM reservasi ORDER BY id DESC");
  while ($r = mysqli_fetch_assoc($q)) {
    $status = $r['status'];
    $badge = match($status) {
      'Pending' => "<span class='badge badge-pending'>Pending</span>",
      'Diproses' => "<span class='badge badge-diproses'>Diproses</span>",
      'Selesai' => "<span class='badge badge-selesai'>Selesai</span>",
      default => $status
    };

    $gambar = $r['bukti_pembayaran'];
    $tampil_gambar = (!empty($gambar))
      ? "<img src='$gambar' class='bukti' onclick=\"showModal(this.src)\">"
      : "<small>Tidak ada</small>";

    echo "<tr>
      <td>{$r['id']}</td>
      <td>{$r['nama_pelanggan']}</td>
      <td>{$r['nomor_hp']}</td>
      <td>{$r['tanggal_pesanan']}</td>
      <td>Rp " . number_format($r['total_pembayaran'] * 0.5, 0, ',', '.') . "</td> <!-- DP 50% -->
      <td>$badge</td>
      <td>$tampil_gambar</td>
      <td>
        <div class='aksi-wrapper'>
          <a class='btn btn-detail' href='detail_reservasi.php?id={$r['id']}'>Detail</a>
          <form action='update_status.php' method='POST' class='status-form'>
            <input type='hidden' name='id' value='{$r['id']}'>
            <select name='status' class='status-select'>
              <option " . ($status == 'Pending' ? 'selected' : '') . ">Pending</option>
              <option " . ($status == 'Diproses' ? 'selected' : '') . ">Diproses</option>
              <option " . ($status == 'Selesai' ? 'selected' : '') . ">Selesai</option>
            </select>
            <button type='submit' class='btn-status'>✔</button>
          </form>
        </div>
      </td>
    </tr>";
  }
  ?>
</tbody>


  <!-- Modal -->
  <div id="myModal" class="modal">
    <span class="close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="imgModal">
  </div>

  <script>
    function showModal(src) {
      document.getElementById("myModal").style.display = "block";
      document.getElementById("imgModal").src = src;
    }

    function closeModal() {
      document.getElementById("myModal").style.display = "none";
    }

    window.onclick = function(event) {
      let modal = document.getElementById("myModal");
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
  </script>

</body>
</html>
