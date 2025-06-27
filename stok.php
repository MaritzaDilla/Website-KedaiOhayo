<?php
session_start();
if (!isset($_SESSION['login'])) {
  header("Location: login.php");
  exit;
}
include 'koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Stok Produk</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      font-size: 20px;
      margin-bottom: 30px;
      text-align: center;
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

    .content {
      flex: 1;
      padding: 30px;
    }
    h1 {
      font-size: 24px;
      margin-bottom: 20px;
    }
    .btn {
      padding: 6px 12px;
      color: white;
      text-decoration: none;
      border-radius: 4px;
      font-size: 14px;
      border: none;
      cursor: pointer;
    }
    .btn-primary { background: #007bff; }
    .btn-danger { background: #dc3545; }
    .btn-warning { background: #ffc107; color: black; }

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
    th {
      background: #007bff;
      color: white;
    }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      font-size: 13px;
      border-radius: 12px;
      font-weight: bold;
      color: black;
    }
    .badge-food { background: #28a745; }
    .badge-drink { background: #17a2b8; }
    .badge-ingredient { background: #6f42c1; }

    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.5);
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      background: white;
      padding: 20px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
    }
    .close {
      float: right;
      font-size: 24px;
      cursor: pointer;
    }
    input, select, textarea {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border-radius: 6px;
      border: 1px solid #ccc;
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>Kedai Ohayo<br><small>Panel Admin</small></h2>
    <a href="admin.php">📆 Reservasi</a>
    <a href="takeaway.php">🥡 Takeaway</a>
    <a href="stok.php" class="active">📦 Stok Produk</a>
    <a href="logout.php">🚪 Logout</a>
  </div>
  <div class="content">
    <h1>Manajemen Stok Produk</h1>
    <button class="btn btn-primary" onclick="openModal()">+ Tambah Produk</button>
    <br><br>
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>Kategori</th>
          <th>Harga</th>
          <th>Stok</th>
          <th>Gambar</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody id="dataProduk">
        <!-- Produk akan dimuat di sini -->
      </tbody>
    </table>
  </div>

  <!-- Modal Tambah/Edit Produk -->
  <div class="modal" id="formModal">
    <div class="modal-content">
      <span class="close" onclick="closeModal()">&times;</span>
      <h2 id="modalTitle">Tambah Produk</h2>
      <form id="productForm">
        <input type="hidden" id="productId">
        <label>Nama Produk</label>
        <input type="text" id="productName" required>
        <label>Kategori</label>
        <select id="productCategory" required>
          <option value="">Pilih</option>
          <option value="Makanan">Makanan</option>
          <option value="Minuman">Minuman</option>
          <option value="Bahan Baku">Bahan Baku</option>
        </select>
        <label>Harga</label>
        <input type="number" id="productPrice" required>
        <label>Stok</label>
        <input type="number" id="productStock" required>
        <label>Upload Gambar</label>
        <input type="file" id="productImage">
        <br>
        <button class="btn btn-primary" type="submit">Simpan</button>
      </form>
    </div>
  </div>

  <script>
    function openModal() {
      document.getElementById('formModal').style.display = 'flex';
    }
    function closeModal() {
      document.getElementById('formModal').style.display = 'none';
      document.getElementById('productForm').reset();
    }

    function loadProducts() {
      fetch('tampil_produk.php')
        .then(res => res.json())
        .then(data => {
          let html = '';
          data.forEach((p, i) => {
            html += `
              <tr>
                <td>${i + 1}</td>
                <td>${p.nama_produk}</td>
                <td><span class='badge badge-${p.kategori.toLowerCase().replace(' ', '-')}' >${p.kategori}</span></td>
                <td>Rp ${parseInt(p.harga).toLocaleString()}</td>
                <td>${p.stok}</td>
                <td>${p.gambar ? `<img src="uploads/${p.gambar}" width="60">` : '-'}</td>
                <td>
                  <button class="btn btn-warning" onclick="editProduct(${p.id})">Edit</button>
                  <button class="btn btn-danger" onclick="hapusProduk(${p.id})">Hapus</button>
                </td>
              </tr>`;
          });
          document.getElementById('dataProduk').innerHTML = html;
        });
    }

    function hapusProduk(id) {
      if (confirm('Yakin ingin menghapus produk ini?')) {
        fetch('hapus_stok.php?id=' + id)
          .then(res => res.json())
          .then(data => {
            if (data.success) loadProducts();
            else alert('Gagal hapus');
          });
      }
    }

    function editProduct(id) {
      fetch('tampil_produk.php?id=' + id)
        .then(res => res.json())
        .then(data => {
          const p = data[0];
          document.getElementById('productId').value = p.id;
          document.getElementById('productName').value = p.nama_produk;
          document.getElementById('productCategory').value = p.kategori;
          document.getElementById('productPrice').value = p.harga;
          document.getElementById('productStock').value = p.stok;
          openModal();
        });
    }

    document.getElementById('productForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      formData.append('id', document.getElementById('productId').value);
      formData.append('nama', document.getElementById('productName').value);
      formData.append('kategori', document.getElementById('productCategory').value);
      formData.append('harga', document.getElementById('productPrice').value);
      formData.append('stok', document.getElementById('productStock').value);

      const fileInput = document.getElementById('productImage');
      if (fileInput.files[0]) {
        formData.append('gambar', fileInput.files[0]);
      }

      fetch('update_produk.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          closeModal();
          loadProducts();
        } else {
          alert('Gagal simpan data');
        }
      });
    });

    window.onload = loadProducts;
  </script>
</body>
</html>
