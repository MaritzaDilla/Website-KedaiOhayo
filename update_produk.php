<?php
header('Content-Type: application/json');
require 'koneksi.php';

$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Ambil input
$id = $_POST['id'] ?? null;
$nama = $_POST['nama'];
$kategori = $_POST['kategori'];
$harga = $_POST['harga'];
$stok = $_POST['stok'];
$deskripsi = $_POST['deskripsi'] ?? '';

try {
    // Handle upload gambar
    $gambar = null;
    if (!empty($_FILES['gambar']['name'])) {
        $fileExt = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('produk_', true) . '.' . strtolower($fileExt);
        $targetPath = $uploadDir . $fileName;

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($fileExt), $allowedTypes)) {
            throw new Exception('Format file tidak didukung. Gunakan JPG, PNG, atau GIF.');
        }
        if ($_FILES['gambar']['size'] > 2097152) {
            throw new Exception('Ukuran file terlalu besar. Maksimal 2MB.');
        }
        if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $targetPath)) {
            throw new Exception('Gagal mengupload gambar.');
        }
        $gambar = $fileName;
    }

    if ($id) {
        // ==== MODE UPDATE ====
        if ($gambar) {
            // Hapus gambar lama jika ada
            $stmt = $conn->prepare("SELECT gambar FROM products WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $old = $res->fetch_assoc();
            if ($old && $old['gambar'] && file_exists($uploadDir . $old['gambar'])) {
                unlink($uploadDir . $old['gambar']);
            }

            // Update dengan gambar baru
            $stmt = $conn->prepare("UPDATE products SET nama_produk=?, kategori=?, harga=?, stok=?, deskripsi=?, gambar=? WHERE id=?");
            $stmt->bind_param('ssiissi', $nama, $kategori, $harga, $stok, $deskripsi, $gambar, $id);
        } else {
            // Update tanpa gambar
            $stmt = $conn->prepare("UPDATE products SET nama_produk=?, kategori=?, harga=?, stok=?, deskripsi=? WHERE id=?");
            $stmt->bind_param('ssiisi', $nama, $kategori, $harga, $stok, $deskripsi, $id);
        }
    } else {
        // ==== MODE TAMBAH ====

        // Generate prefix dan nomor urut kode
        switch($kategori) {
            case 'Makanan': $prefix = 'M'; break;
            case 'Minuman': $prefix = 'D'; break;
            case 'Bahan Baku': $prefix = 'B'; break;
            default: $prefix = 'P';
        }

        $query = $conn->prepare("SELECT MAX(CAST(SUBSTRING(kode_produk, 2) AS UNSIGNED)) AS max_num FROM products WHERE kategori = ?");
        $query->bind_param('s', $kategori);
        $query->execute();
        $result = $query->get_result()->fetch_assoc();
        $next_num = ($result['max_num'] ?? 0) + 1;

        $kode_produk = $prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);

        // Simpan data baru
        $stmt = $conn->prepare("INSERT INTO products (kode_produk, nama_produk, kategori, harga, stok, deskripsi, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssiiss', $kode_produk, $nama, $kategori, $harga, $stok, $deskripsi, $gambar);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Gagal menyimpan ke database: ' . $stmt->error);
    }

} catch (Exception $e) {
    // Rollback gambar jika gagal
    if (isset($targetPath) && file_exists($targetPath)) {
        unlink($targetPath);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
