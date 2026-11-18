<?php
session_start();

// ====== 1. MEMANGGIL KONEKSI DATABASE ======
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} elseif (file_exists('koneksi.php')) {
    require_once 'koneksi.php';
} else {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'wenak_wenak_db';
    $conn = mysqli_connect($host, $user, $pass, $db);
}

if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// ====== 2. OTOMATIS BUAT/MIGRASI TABEL & KOLOM KEAMANAN ======
$sql_table = "CREATE TABLE IF NOT EXISTS admin_user (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    failed_attempts INT DEFAULT 0,
    lockout_until DATETIME NULL,
    penalty_minutes INT DEFAULT 15
)";
mysqli_query($conn, $sql_table);

// Otomatis tambahkan kolom keamanan jika tabel sudah ada sebelumnya
@mysqli_query($conn, "ALTER TABLE admin_user ADD COLUMN failed_attempts INT DEFAULT 0");
@mysqli_query($conn, "ALTER TABLE admin_user ADD COLUMN lockout_until DATETIME NULL");
@mysqli_query($conn, "ALTER TABLE admin_user ADD COLUMN penalty_minutes INT DEFAULT 15");

// Buat akun default jika database masih kosong
$check_user = mysqli_query($conn, "SELECT COUNT(*) as total FROM admin_user WHERE username = 'admin'");
$row_user = mysqli_fetch_assoc($check_user);
if ($row_user['total'] == 0) {
    $pass_admin = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($conn, "INSERT INTO admin_user (username, password, nama, role, failed_attempts, penalty_minutes) VALUES 
        ('admin', '$pass_admin', 'Administrator Utama Toko', 'admin', 0, 15)");
}

// ====== 3. REDIRECT JIKA SUDAH LOGIN ======
if (isset($_SESSION['user_login']) || isset($_SESSION['kasir'])) {
    if (file_exists('admin/dashboard.php')) {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error   = "";
$success = "";

// ====== 4. PROSES VERIFIKASI LOGIN ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id_admin, username, password, nama, role, failed_attempts, lockout_until, penalty_minutes FROM admin_user WHERE username = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res  = $stmt->get_result();
            $user = $res ? $res->fetch_assoc() : null;

            if ($user) {
                $now_timestamp     = time();
                $lockout_timestamp = !empty($user['lockout_until']) ? strtotime($user['lockout_until']) : 0;

                // A. CEK APAKAH AKUN MASIH DALAM MASA JEDA (COOLDOWN)
                if ($lockout_timestamp > $now_timestamp) {
                    $selisih_detik = $lockout_timestamp - $now_timestamp;
                    $menit_sisa    = floor($selisih_detik / 60);
                    $detik_sisa    = $selisih_detik % 60;

                    $error = "⏳ <strong>AKUN DALAM MASA JEDA!</strong><br>Terlalu banyak percobaan salah.<br>Silakan tunggu <strong>$menit_sisa menit $detik_sisa detik</strong> lagi sebelum dapat mencoba login kembali.";
                } else {
                    // B. VERIFIKASI PASSWORD
                    if (password_verify($password, $user['password']) || $password === $user['password']) {
                        // Login Sukses -> Reset seluruh status percobaan & durasi jeda
                        $stmt_reset = $conn->prepare("UPDATE admin_user SET failed_attempts = 0, lockout_until = NULL, penalty_minutes = 15 WHERE id_admin = ?");
                        $stmt_reset->bind_param("i", $user['id_admin']);
                        $stmt_reset->execute();

                        session_regenerate_id(true);
                        $_SESSION['kasir']      = $user['username'];
                        $_SESSION['id_admin']   = $user['id_admin'];
                        $_SESSION['user_login'] = [
                            'username' => $user['username'],
                            'nama'     => $user['nama'] ?? 'Administrator',
                            'role'     => $user['role'] ?? 'admin'
                        ];

                        if (file_exists('admin/dashboard.php')) {
                            header("Location: admin/dashboard.php");
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit;
                    } else {
                        // Password Salah -> Increment Counter Kesalahan
                        $new_attempts = $user['failed_attempts'] + 1;

                        if ($new_attempts >= 5) {
                            // Tentukan durasi penalti (15 menit untuk pertama kali, +2 menit untuk kesalahan berikutnya)
                            $current_penalty = ($user['penalty_minutes'] > 0) ? $user['penalty_minutes'] : 15;
                            
                            if ($user['failed_attempts'] >= 5) {
                                // Tambah 2 menit jika sebelumnya sudah pernah kena jeda dan tetap salah
                                $current_penalty += 2;
                            }

                            $lockout_time = date('Y-m-d H:i:s', $now_timestamp + ($current_penalty * 60));

                            $stmt_lock = $conn->prepare("UPDATE admin_user SET failed_attempts = ?, lockout_until = ?, penalty_minutes = ? WHERE id_admin = ?");
                            $stmt_lock->bind_param("isii", $new_attempts, $lockout_time, $current_penalty, $user['id_admin']);
                            $stmt_lock->execute();

                            $error = "🚫 <strong>BATAS KESALAHAN TERCAPAI (5x)!</strong><br>Login dikunci sementara.<br>Silakan tunggu selama <strong>$current_penalty menit</strong> sebelum mencoba lagi.";
                        } else {
                            // Update jumlah kesalahan
                            $stmt_fail = $conn->prepare("UPDATE admin_user SET failed_attempts = ? WHERE id_admin = ?");
                            $stmt_fail->bind_param("ii", $new_attempts, $user['id_admin']);
                            $stmt_fail->execute();

                            $sisa = 5 - $new_attempts;
                            $error = "⚠️ Password salah! Percobaan ke-<strong>$new_attempts</strong> dari 5. Sisa kesempatan: <strong>$sisa kali lagi</strong>.";
                        }
                    }
                }
            } else {
                $error = "Username tidak terdaftar dalam sistem.";
            }
        } else {
            $error = "Gagal memproses query database.";
        }
    } else {
        $error = "Username dan password wajib diisi.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Portal - Toko Wenak Wenak</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #3b1a14; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
        }
        .box { 
            background: #ffffff; 
            padding: 30px; 
            border-radius: 12px; 
            width: 360px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.3); 
        }
        h2 { 
            margin-top: 0; 
            text-align: center; 
            color: #3b1a14; 
            margin-bottom: 4px; 
        }
        .sub-title { 
            text-align: center; 
            color: #666; 
            font-size: 13px; 
            margin-bottom: 18px; 
        }
        label { 
            font-weight: bold; 
            font-size: 13px; 
            color: #333; 
            display: block; 
            margin-top: 10px; 
        }
        input { 
            width: 100%; 
            padding: 10px; 
            margin: 5px 0 12px; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 14px; 
        }
        button { 
            width: 100%; 
            padding: 11px; 
            background: #e8883a; 
            color: #fff; 
            border: 0; 
            border-radius: 8px; 
            font-weight: bold; 
            font-size: 15px; 
            cursor: pointer; 
            transition: background 0.3s; 
            margin-top: 6px;
        }
        button:hover { 
            background: #d07025; 
        }
        .error { 
            background: #fde2e2; 
            color: #b00020; 
            padding: 10px; 
            border-radius: 8px; 
            margin-bottom: 14px; 
            font-size: 13px; 
            line-height: 1.4;
            border-left: 4px solid #b00020;
        }
        .back-link {
            text-align: center;
            margin-top: 16px;
        }
        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔐 Login Kasir & Admin</h2>
        <div class="sub-title">Portal Operasional Toko Wenak Wenak</div>

        <?php if (!empty($error)): ?>
            <div class="error"><?= $error; ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="proses_login" value="1">

            <label>Username Admin / Kasir</label>
            <input type="text" name="username" placeholder="Masukkan username" required autofocus autocomplete="off">
            
            <label>Password</label>
            <input type="password" name="password" placeholder="Masukkan password" required>
            
            <button type="submit">Masuk Dashboard ➔</button>
        </form>

        <div class="back-link">
            <a href="index.php">⬅ Kembali ke Halaman Utama Katalog</a>
        </div>
    </div>
</body>
</html>