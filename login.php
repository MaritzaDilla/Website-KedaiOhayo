<?php
session_start();
include 'koneksi.php';

// Jika sudah login, langsung alihkan
if (isset($_SESSION['login'])) {
  header("Location: admin.php");
  exit;
}

// Proses login saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  // Ganti dengan username & password yang kamu tentukan
  if ($username === 'admin' && $password === 'admin') {
    $_SESSION['login'] = true;
    header("Location: admin.php");
    exit;
  } else {
    $error = "Username atau Password salah!";
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Login Admin</title>
  <style>
    body {
      background: #f1f1f1;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .login-box {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      width: 320px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
      color: #007bff;
    }
    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 10px;
      margin: 10px 0;
      border-radius: 6px;
      border: 1px solid #ccc;
    }
    button {
      width: 100%;
      padding: 10px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
    }
    .error {
      color: red;
      text-align: center;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <h2>Login Admin</h2>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" required />
      <input type="password" name="password" placeholder="Password" required />
      <button type="submit">Login</button>
    </form>
    <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
  </div>
</body>
</html>
