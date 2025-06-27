<?php
session_start();

// Koneksi ke database
$host = 'localhost';
$dbname = 'ohayofix';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

$notif = '';
$notifType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'] ?? null;
    $reservation_type = $_POST['reservation_type'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $guests = $_POST['guests'];
    $special_requests = $_POST['special_requests'] ?? null;
    $status = 'pending';

    // Validasi tanggal (server-side)
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $reservationDate = new DateTime($date);
    
    if ($reservationDate < $today) {
        echo "<div style='text-align: center; padding: 15px; margin: 20px auto; max-width: 600px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "Anda tidak dapat memilih tanggal yang sudah lewat.";
        echo "</div>";
    } else {
        // Validasi waktu
        $timeParts = explode(':', $time);
        $timeMinutes = intval($timeParts[0]) * 60 + intval($timeParts[1]);
        if ($timeMinutes < 660 || $timeMinutes > 1260) {
            echo "<div style='text-align: center; padding: 15px; margin: 20px auto; max-width: 600px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;'>";
            echo "Waktu reservasi harus antara jam 11 siang sampai jam 9 malam.";
            echo "</div>";
        } else {
            try {
                $sql = "INSERT INTO form_reservasi (name, phone, email, reservation_type, date, time, guests, special_requests, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $phone, $email, $reservation_type, $date, $time, $guests, $special_requests, $status]);

                $_SESSION['reservation_details'] = [
                    'nama' => $name,
                    'nomor_hp' => $phone,
                    'tanggal' => $date,
                    'waktu' => $time,
                    'jumlah_orang' => $guests
                ];

                echo "<div style='text-align: center; padding: 15px; margin: 20px auto; max-width: 600px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 5px;'>";
                echo "Reservasi berhasil dikirim!";
                echo "</div>";
                echo "<script>setTimeout(function() { window.location.href = 'menu_reservasi.php'; }, 3000);</script>";
                exit;
            } catch(PDOException $e) {
                echo "<div style='text-align: center; padding: 15px; margin: 20px auto; max-width: 600px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px;'>";
                echo "Terjadi kesalahan: " . $e->getMessage();
                echo "</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Form Reservasi - Kedai Ohayo</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #F5F5DC 0%, #FAEBD7 50%, #FDF5E6 100%);
      background-image: 
        radial-gradient(circle at 25% 25%, rgba(128, 0, 0, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 75% 75%, rgba(165, 42, 42, 0.06) 0%, transparent 50%);
      min-height: 100vh;
      padding: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }
    
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image: 
        linear-gradient(45deg, transparent 40%, rgba(128, 0, 0, 0.03) 50%, transparent 60%),
        linear-gradient(-45deg, transparent 40%, rgba(139, 69, 19, 0.03) 50%, transparent 60%);
      pointer-events: none;
      z-index: 0;
    }
    
    .container {
      width: 100%;
      max-width: 700px;
      background: rgba(255, 253, 250, 0.95);
      backdrop-filter: blur(15px);
      border-radius: 25px;
      box-shadow: 
        0 25px 50px rgba(128, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.9);
      overflow: hidden;
      border: 3px solid #800000;
      position: relative;
      z-index: 1;
    }
    
    .elegant-border {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      border: 6px solid transparent;
      border-image: linear-gradient(45deg, #800000, #A0522D, #DEB887, #A0522D, #800000) 1;
      border-radius: 25px;
      pointer-events: none;
    }
    
    .corner-ornament {
      position: absolute;
      width: 30px;
      height: 30px;
      background: linear-gradient(45deg, #800000, #A52A2A);
      clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
      opacity: 0.4;
    }
    
    .corner-ornament.top-left {
      top: 15px;
      left: 15px;
      transform: rotate(45deg);
    }
    
    .corner-ornament.top-right {
      top: 15px;
      right: 15px;
      transform: rotate(135deg);
    }
    
    .corner-ornament.bottom-left {
      bottom: 15px;
      left: 15px;
      transform: rotate(-45deg);
    }
    
    .corner-ornament.bottom-right {
      bottom: 15px;
      right: 15px;
      transform: rotate(-135deg);
    }
    
    .header {
      background: linear-gradient(135deg, #800000 0%, #A52A2A 35%, #B22222 70%, #CD5C5C 100%);
      color: #FFF8DC;
      padding: 45px 35px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    
    .header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><defs><pattern id="damask" width="60" height="60" patternUnits="userSpaceOnUse"><rect width="60" height="60" fill="none"/><circle cx="30" cy="30" r="1.5" fill="white" opacity="0.15"/><path d="M30,15 Q35,20 30,25 Q25,20 30,15 M30,35 Q35,40 30,45 Q25,40 30,35" fill="none" stroke="white" stroke-width="0.5" opacity="0.2"/></pattern></defs><rect width="200" height="200" fill="url(%23damask)"/></svg>');
      opacity: 0.6;
    }
    
    .header h1 {
      font-size: 2.8rem;
      margin-bottom: 12px;
      text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.4);
      position: relative;
      z-index: 1;
      font-weight: bold;
      letter-spacing: 2px;
    }
    
    .header p {
      font-size: 1.2rem;
      opacity: 0.95;
      position: relative;
      z-index: 1;
      color: #FFF8DC;
      font-weight: 500;
      font-style: italic;
    }
    
    .logo-accent {
      display: inline-block;
      background: linear-gradient(45deg, #DEB887, #F5DEB3);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-shadow: none;
    }
    
    .form-container {
      padding: 45px 35px;
      background: linear-gradient(135deg, #FFF8DC 0%, #FAEBD7 50%, #F5F5DC 100%);
      position: relative;
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 28px;
    }
    
    .form-group {
      position: relative;
    }
    
    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 10px;
      color: #4A4A4A;
      font-size: 1.05rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .form-group label i {
      color: #800000;
      font-size: 18px;
    }
    
    .input-wrapper {
      position: relative;
    }
    
    .input-wrapper i {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: #A52A2A;
      z-index: 1;
      font-size: 16px;
    }
    
    input, select, textarea {
      width: 100%;
      padding: 18px 18px 18px 50px;
      font-size: 16px;
      border: 2px solid #DEB887;
      border-radius: 15px;
      background: #FFFEF7;
      transition: all 0.4s ease;
      font-family: inherit;
      color: #4A4A4A;
      box-shadow: inset 0 2px 4px rgba(139, 69, 19, 0.05);
    }
    
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #800000;
      box-shadow: 
        0 0 0 4px rgba(128, 0, 0, 0.15),
        0 6px 20px rgba(165, 42, 42, 0.2),
        inset 0 2px 4px rgba(139, 69, 19, 0.1);
      transform: translateY(-3px);
      background: #FFFFFF;
    }
    
    input.invalid {
      border-color: #B22222;
      background-color: #FFF0F0;
      box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.2);
    }
    
    input.valid {
      border-color: #228B22;
      background-color: #F0FFF0;
      box-shadow: 0 0 0 3px rgba(34, 139, 34, 0.2);
    }
    
    .error-message {
      color: #B22222;
      font-size: 14px;
      margin-top: 6px;
      display: none;
      animation: slideIn 0.3s ease;
      font-weight: 500;
    }
    
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .static-field {
      background: linear-gradient(135deg, #F5F5DC, #FAEBD7);
      border: 2px solid #DEB887;
      padding: 18px;
      border-radius: 15px;
      color: #4A4A4A;
      font-weight: 600;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      box-shadow: inset 0 3px 6px rgba(139, 69, 19, 0.08);
    }
    
    .static-field i {
      color: #A52A2A;
      font-size: 18px;
    }
    
    .button-group {
      display: flex;
      gap: 18px;
      margin-top: 35px;
    }
    
    .btn {
      flex: 1;
      padding: 18px 30px;
      border: none;
      border-radius: 15px;
      font-size: 16px;
      font-weight: 600;
      text-decoration: none;
      text-align: center;
      cursor: pointer;
      transition: all 0.4s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-transform: uppercase;
      letter-spacing: 1px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #800000, #A52A2A, #B22222);
      color: #FFF8DC;
      border: 2px solid #4A0000;
      box-shadow: 
        0 6px 20px rgba(128, 0, 0, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }
    
    .btn-primary:hover {
      transform: translateY(-4px);
      box-shadow: 
        0 8px 25px rgba(128, 0, 0, 0.6),
        inset 0 1px 0 rgba(255, 255, 255, 0.3);
      background: linear-gradient(135deg, #A52A2A, #B22222, #CD5C5C);
    }
    
    .btn-secondary {
      background: linear-gradient(135deg, #F5DEB3, #DEB887);
      color: #4A4A4A;
      border: 2px solid #D2B48C;
    }
    
    .btn-secondary:hover {
      background: linear-gradient(135deg, #DEB887, #D2B48C);
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(139, 69, 19, 0.3);
      color: #2F2F2F;
    }
    
    .notification {
      position: fixed;
      top: 25px;
      right: 25px;
      padding: 18px 25px;
      border-radius: 15px;
      color: white;
      font-weight: 600;
      z-index: 1000;
      animation: slideInRight 0.5s ease;
      border: 2px solid;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }
    
    .notification.success {
      background: linear-gradient(135deg, #228B22, #32CD32);
      border-color: #006400;
    }
    
    .notification.error {
      background: linear-gradient(135deg, #B22222, #DC143C);
      border-color: #8B0000;
    }
    
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    
    .time-info {
      background: linear-gradient(135deg, #FFF8DC, #F5F5DC);
      border: 2px solid #DEB887;
      border-radius: 12px;
      padding: 15px;
      margin-top: 10px;
      font-size: 14px;
      color: #4A4A4A;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: inset 0 2px 4px rgba(139, 69, 19, 0.06);
    }
    
    .time-info i {
      color: #A52A2A;
    }
    
    .guests-counter {
      display: flex;
      align-items: center;
      gap: 20px;
      justify-content: center;
      padding: 15px;
      background: linear-gradient(135deg, #FFF8DC, #F5F5DC);
      border-radius: 15px;
      border: 2px solid #DEB887;
      box-shadow: inset 0 3px 6px rgba(139, 69, 19, 0.08);
    }
    
    .counter-btn {
      background: linear-gradient(135deg, #800000, #A52A2A);
      color: #FFF8DC;
      border: 2px solid #4A0000;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: bold;
      font-size: 18px;
      box-shadow: 0 4px 12px rgba(128, 0, 0, 0.3);
    }
    
    .counter-btn:hover {
      background: linear-gradient(135deg, #A52A2A, #B22222);
      transform: scale(1.15);
      box-shadow: 0 6px 18px rgba(128, 0, 0, 0.5);
    }
    
    .counter-display {
      font-size: 2rem;
      font-weight: bold;
      color: #4A4A4A;
      min-width: 70px;
      text-align: center;
      background: #FFFEF7;
      padding: 12px;
      border-radius: 12px;
      border: 2px solid #DEB887;
      box-shadow: inset 0 2px 4px rgba(139, 69, 19, 0.1);
    }
    
    /* Responsive Design */
    @media (min-width: 768px) {
      .form-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .form-group.full-width {
        grid-column: 1 / -1;
      }
      
      .button-group {
        grid-column: 1 / -1;
        justify-content: flex-end;
      }
      
      .btn {
        flex: none;
        min-width: 170px;
      }
    }
    
    @media (max-width: 480px) {
      body {
        padding: 15px;
      }
      
      .header {
        padding: 35px 25px;
      }
      
      .header h1 {
        font-size: 2.2rem;
      }
      
      .form-container {
        padding: 35px 25px;
      }
      
      .button-group {
        flex-direction: column;
      }
    }
    
    /* Custom Flatpickr styling */
    .flatpickr-calendar {
      border: 2px solid #800000 !important;
      border-radius: 15px !important;
      box-shadow: 0 12px 35px rgba(128, 0, 0, 0.3) !important;
      background: #FFF8DC !important;
    }
    
    .flatpickr-day.selected {
      background: linear-gradient(135deg, #800000, #A52A2A) !important;
      border-color: #4A0000 !important;
    }
    
    .flatpickr-day:hover {
      background: #F5DEB3 !important;
      border-color: #DEB887 !important;
    }
    
    .flatpickr-months {
      background: #800000 !important;
    }
    
    .flatpickr-current-month .flatpickr-monthDropdown-months,
    .flatpickr-current-month input.cur-year {
      color: #FFF8DC !important;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="corner-ornament top-left"></div>
    <div class="corner-ornament top-right"></div>
    <div class="corner-ornament bottom-left"></div>
    <div class="corner-ornament bottom-right"></div>
    
    <div class="header">
      <h1><i class="fas fa-utensils"></i> <span class="logo-accent">KEDAI</span> OHAYO</h1>
      <p>🌸 Reservasi elegan, cita rasa istimewa 🍜</p>
    </div>
    
    <div class="form-container">
      <form id="reservationForm" method="POST">
        <div class="form-grid">
          <div class="form-group full-width">
            <label for="name"><i class="fas fa-user"></i> Nama Lengkap</label>
            <div class="input-wrapper">
              <i class="fas fa-user"></i>
              <input type="text" id="name" name="name" required placeholder="Masukkan nama lengkap Anda" />
            </div>
          </div>

          <div class="form-group">
            <label for="phone"><i class="fas fa-phone"></i> Nomor Telepon</label>
            <div class="input-wrapper">
              <i class="fas fa-phone"></i>
              <input type="tel" id="phone" name="phone" required pattern="[0-9]{10,13}" oninput="validatePhone(this)" placeholder="08xxxxxxxxxx"/>
            </div>
            <span class="error-message" id="phone-error"></span>
          </div>

          <div class="form-group">
            <label for="email"><i class="fas fa-envelope"></i> Email (Opsional)</label>
            <div class="input-wrapper">
              <i class="fas fa-envelope"></i>
              <input type="email" id="email" name="email" oninput="validateEmail(this)" placeholder="nama@email.com"/>
            </div>
            <span class="error-message" id="email-error"></span>
          </div>

          <div class="form-group full-width">
            <label><i class="fas fa-utensils"></i> Metode Reservasi</label>
            <div class="static-field">
              <i class="fas fa-utensils"></i>
              <span>🍽️ Dine-in (Makan di tempat)</span>
            </div>
            <input type="hidden" name="reservation_type" value="dine_in" />
          </div>

          <div class="form-group">
            <label for="date"><i class="fas fa-calendar-alt"></i> Tanggal Reservasi</label>
            <div class="input-wrapper">
              <i class="fas fa-calendar-alt"></i>
              <input type="date" id="date" name="date" required />
            </div>
          </div>

          <div class="form-group">
            <label for="time"><i class="fas fa-clock"></i> Waktu Reservasi</label>
            <div class="input-wrapper">
              <i class="fas fa-clock"></i>
              <input type="time" id="time" name="time" required onchange="validateTime(this.value)" min="11:00" max="21:00" />
            </div>
            <div class="time-info">
              <i class="fas fa-info-circle"></i>
              <span>🕐 Kedai buka: 11:00 - 21:00 WIB</span>
            </div>
            <small id="timeMessage" style="display: none; color: #B22222; margin-top: 8px; font-weight: 500;"></small>
          </div>

          <div class="form-group full-width">
            <label for="guests"><i class="fas fa-users"></i> Jumlah Tamu</label>
            <div class="guests-counter">
              <button type="button" class="counter-btn" onclick="changeGuests(-1)">
                <i class="fas fa-minus"></i>
              </button>
              <div class="counter-display" id="guestDisplay">1</div>
              <button type="button" class="counter-btn" onclick="changeGuests(1)">
                <i class="fas fa-plus"></i>
              </button>
              <input type="hidden" id="guests" name="guests" value="1" min="1" max="20" />
            </div>
          </div>

          <!-- <div class="form-group full-width">
            <label for="special_requests"><i class="fas fa-comment"></i> Permintaan Khusus (Opsional)</label>
            <div class="input-wrapper">
              <i class="fas fa-comment"></i>
              <textarea id="special_requests" name="special_requests" rows="3" placeholder="Contoh: Meja dekat jendela, alergi makanan tertentu, dll."></textarea>
            </div>
          </div> -->

          <div class="button-group full-width">
            <a href="index.php" class="btn btn-secondary">
              <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button type="button" class="btn btn-primary" onclick="validateAndConfirm()">
              <i class="fas fa-paper-plane"></i> Kirim Reservasi
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    // Deklarasikan array liburLebaran terlebih dahulu sebelum digunakan
    const liburLebaran = [
      "2024-04-10", "2024-04-11", "2024-04-12",
      "2026-03-01", "2026-03-02", "2026-03-03",
      "2027-02-19", "2027-02-20", "2027-02-21",
      "2028-02-07", "2028-08-09"
    ];
    
    // Set minimum date (tomorrow) when page loads
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.getElementById('date');
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        // Inisialisasi Flatpickr
        flatpickr(dateInput, {
            minDate: tomorrow,
            dateFormat: "Y-m-d",
            disable: [
                function(date) {
                    return date.getDay() === 1; // Nonaktifkan hari Senin
                },
                ...liburLebaran.map(dateStr => new Date(dateStr))
            ],
            onChange: function(selectedDates, dateStr, instance) {
                if (liburLebaran.includes(dateStr)) {
                    let tahunLebaran = "";
                    if (dateStr.startsWith("2024")) {
                        tahunLebaran = "2024";
                    } else if (dateStr.startsWith("2026")) {
                        tahunLebaran = "2026";
                    } else if (dateStr.startsWith("2027")) {
                        tahunLebaran = "2027";
                    } else if (dateStr.startsWith("2028")) {
                        tahunLebaran = "2028";
                    }
                    
                    showNotification(`🏮 Maaf, kedai tutup pada tanggal tersebut karena libur Lebaran Idul Fitri ${tahunLebaran}. Silakan pilih tanggal lain.`, 'error');
                    instance.clear();
                }
            }
        });
    });

    function validatePhone(input) {
      const phoneRegex = /^[0-9]{10,13}$/;
      const errorElement = document.getElementById('phone-error');

      if (input.value.trim() === '') {
        input.classList.add('invalid');
        input.classList.remove('valid');
        errorElement.style.display = 'block';
        errorElement.textContent = 'Nomor telepon tidak boleh kosong';
        return false;
      }
      if (!phoneRegex.test(input.value)) {
        input.classList.add('invalid');
        input.classList.remove('valid');
        errorElement.style.display = 'block';
        errorElement.textContent = 'Nomor telepon harus 10-13 digit angka';
        return false;
      }

      input.classList.remove('invalid');
      input.classList.add('valid');
      errorElement.style.display = 'none';
      return true;
    }

    function validateEmail(input) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const errorElement = document.getElementById('email-error');
      
      if (input.value.trim() === '') {
        input.classList.remove('invalid');
        input.classList.remove('valid');
        errorElement.style.display = 'none';
        return true;
      }

      if (!emailRegex.test(input.value)) {
        input.classList.add('invalid');
        input.classList.remove('valid');
        errorElement.style.display = 'block';
        errorElement.textContent = 'Format email tidak valid';
        return false;
      }

      input.classList.remove('invalid');
      input.classList.add('valid');
      errorElement.style.display = 'none';
      return true;
    }

    function validateTime(value) {
      const timeMessage = document.getElementById('timeMessage');
      if (!value) {
        timeMessage.style.display = 'none';
        return false;
      }
      const [hours, minutes] = value.split(':').map(Number);
      const totalMinutes = hours * 60 + minutes;
      const buka = 11 * 60;
      const tutup = 21 * 60;

      if (totalMinutes < buka || totalMinutes > tutup) {
        timeMessage.textContent = "⏰ Kedai hanya buka jam 11 siang - 9 malam";
        timeMessage.style.display = 'block';
        return false;
      } else {
        timeMessage.style.display = 'none';
        return true;
      }
    }

    function changeGuests(delta) {
      const guestInput = document.getElementById('guests');
      const guestDisplay = document.getElementById('guestDisplay');
      let currentValue = parseInt(guestInput.value);
      
      currentValue += delta;
      
      if (currentValue < 1) currentValue = 1;
      if (currentValue > 20) currentValue = 20;
      
      guestInput.value = currentValue;
      guestDisplay.textContent = currentValue;
    }

    function showNotification(message, type) {
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.remove();
      }, 5000);
    }

    function validateAndConfirm() {
      const form = document.getElementById('reservationForm');
      const requiredFields = ['name', 'phone', 'date', 'time', 'guests'];
      let isValid = true;
      let firstEmptyField = null;

      requiredFields.forEach((id) => {
        const input = document.getElementById(id);
        if (!input.value.trim()) {
          isValid = false;
          if (!firstEmptyField) firstEmptyField = input;
        }
      });

      if (!isValid) {
        showNotification("📝 Mohon isi data yang lengkap dan benar.", 'error');
        if (firstEmptyField) firstEmptyField.focus();
        return;
      }

      const phoneValid = validatePhone(document.getElementById('phone'));
      const emailInput = document.getElementById('email');
      const emailValid = emailInput.value.trim() === '' || validateEmail(emailInput);
      if (!phoneValid || !emailValid) return;

      const timeValid = validateTime(document.getElementById('time').value);
      if (!timeValid) {
        showNotification("⏰ Waktu reservasi harus antara jam 11 siang sampai jam 9 malam.", 'error');
        document.getElementById('time').focus();
        return;
      }

      // Validate date is not in the past (client-side)
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const selectedDate = new Date(document.getElementById('date').value);
      
      if (selectedDate < today) {
        showNotification("📅 Anda tidak dapat memilih tanggal yang sudah lewat.", 'error');
        document.getElementById('date').focus();
        return;
      }

      if (confirm("🤔 Apakah Anda yakin ingin mengirim reservasi?")) {
        showNotification("✅ Reservasi sukses, lanjut pesan menu! 🍜", 'success');
        setTimeout(() => {
          form.submit();
        }, 2000);
      }
    }

    // Add smooth animations for form interactions
    document.querySelectorAll('input, select, textarea').forEach(element => {
      element.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
      });
      
      element.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
      });
    });
  </script>
</body>
</html>