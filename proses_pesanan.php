<?php
// ====== CONFIG / DATABASE ======
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

$NO_WA_KASIR = "62881026767117";

// ====== CEK DAN BUAT TABEL / KOLOM DIBUTUHKAN ======
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pesanan (
    id_pesanan INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(50) NOT NULL,
    nama_pemesan VARCHAR(100) NOT NULL,
    no_telepon VARCHAR(30) NOT NULL,
    jenis_layanan VARCHAR(50) NOT NULL,
    alamat_lengkap TEXT,
    nomor_meja VARCHAR(50),
    metode_pembayaran VARCHAR(50) NOT NULL,
    total_harga DECIMAL(12,2) NOT NULL,
    uang_bayar DECIMAL(12,2) DEFAULT 0,
    uang_kembali DECIMAL(12,2) DEFAULT 0,
    status_pesanan VARCHAR(50) DEFAULT 'Menunggu Konfirmasi',
    tanggal_pesan DATETIME DEFAULT CURRENT_TIMESTAMP
)");

@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN jenis_layanan VARCHAR(50) DEFAULT 'Delivery'");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN alamat_lengkap TEXT");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN nomor_meja VARCHAR(50)");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN uang_bayar DECIMAL(12,2) DEFAULT 0");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN uang_kembali DECIMAL(12,2) DEFAULT 0");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS detail_pesanan (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_pesanan INT NOT NULL,
    id_menu INT DEFAULT 0,
    nama_menu VARCHAR(100) NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    jumlah INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(50) NOT NULL,
    nama_pemesan VARCHAR(100) NOT NULL,
    no_telepon VARCHAR(30) NOT NULL,
    jenis_layanan VARCHAR(50) NOT NULL,
    alamat_lengkap TEXT,
    nomor_meja VARCHAR(50),
    metode_pembayaran VARCHAR(50) NOT NULL,
    total_harga DECIMAL(12,2) NOT NULL,
    uang_bayar DECIMAL(12,2) DEFAULT 0,
    uang_kembali DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Menunggu Konfirmasi',
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP
)");

@mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN jenis_layanan VARCHAR(50) DEFAULT 'Delivery'");
@mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN alamat_lengkap TEXT");
@mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN nomor_meja VARCHAR(50)");
@mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN uang_bayar DECIMAL(12,2) DEFAULT 0");
@mysqli_query($conn, "ALTER TABLE transaksi ADD COLUMN uang_kembali DECIMAL(12,2) DEFAULT 0");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_menu INT DEFAULT 0,
    nama_menu VARCHAR(100) NOT NULL,
    harga DECIMAL(12,2) NOT NULL,
    jumlah INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL
)");

// ====== PROSES DATA PEMESANAN ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama_pemesan      = mysqli_real_escape_string($conn, trim($_POST['nama_pemesan'] ?? ''));
    $no_telepon        = mysqli_real_escape_string($conn, trim($_POST['no_telepon'] ?? ''));
    $jenis_layanan     = mysqli_real_escape_string($conn, trim($_POST['jenis_layanan'] ?? 'Delivery'));
    $metode_pembayaran = mysqli_real_escape_string($conn, trim($_POST['metode_pembayaran'] ?? 'Tunai'));
    $total_pembayaran  = (float)($_POST['total_pembayaran'] ?? 0);
    $uang_bayar        = (float)($_POST['uang_bayar'] ?? $total_pembayaran);
    $uang_kembali      = (float)($_POST['uang_kembali'] ?? 0);
    $cart_json         = $_POST['cart_json'] ?? '[]';

    $alamat_jalan   = trim($_POST['alamat_jalan'] ?? '');
    $rt_rw          = trim($_POST['rt_rw'] ?? '');
    $kel_kec        = trim($_POST['kelurahan_kecamatan'] ?? '');
    $kota           = trim($_POST['kota_kabupaten'] ?? '');
    $catatan_pat    = trim($_POST['catatan_patokan'] ?? '');
    $nomor_meja     = mysqli_real_escape_string($conn, trim($_POST['nomor_meja'] ?? ''));

    $alamat_gabungan = "";
    if (!empty($alamat_jalan)) {
        $alamat_gabungan = $alamat_jalan;
        if (!empty($rt_rw)) { $alamat_gabungan .= ", " . $rt_rw; }
        if (!empty($kel_kec)) { $alamat_gabungan .= ", " . $kel_kec; }
        if (!empty($kota)) { $alamat_gabungan .= ", " . $kota; }
        if (!empty($catatan_pat)) { $alamat_gabungan .= " (Patokan: " . $catatan_pat . ")"; }
    } else {
        $alamat_gabungan = !empty($nomor_meja) ? "Dine-in (Meja " . $nomor_meja . ")" : 'Ambil di Toko (Jl. Pattimura)';
    }

    $alamat_db  = mysqli_real_escape_string($conn, $alamat_gabungan);
    $cart_items = json_decode($cart_json, true);

    if (empty($nama_pemesan) || empty($no_telepon) || empty($cart_items)) {
        echo "<script>
                alert('Data pesanan tidak lengkap!');
                window.location.href = 'index.php';
              </script>";
        exit;
    }

    $kode_transaksi   = 'TRX-' . date('Ymd') . '-' . rand(1000, 9999);
    $tanggal_sekarang = date('Y-m-d H:i:s');
    $status_default   = 'Menunggu Konfirmasi';

    // 1. Simpan ke Tabel pesanan
    $q_pesanan = "INSERT INTO pesanan (kode_transaksi, nama_pemesan, no_telepon, jenis_layanan, alamat_lengkap, nomor_meja, metode_pembayaran, total_harga, uang_bayar, uang_kembali, status_pesanan, tanggal_pesan) 
                  VALUES ('$kode_transaksi', '$nama_pemesan', '$no_telepon', '$jenis_layanan', '$alamat_db', '$nomor_meja', '$metode_pembayaran', $total_pembayaran, $uang_bayar, $uang_kembali, '$status_default', '$tanggal_sekarang')";
    mysqli_query($conn, $q_pesanan);
    $id_pesanan_baru = mysqli_insert_id($conn);

    // 2. Simpan ke Tabel transaksi
    $q_transaksi = "INSERT INTO transaksi (kode_transaksi, nama_pemesan, no_telepon, jenis_layanan, alamat_lengkap, nomor_meja, metode_pembayaran, total_harga, uang_bayar, uang_kembali, status, tanggal) 
                    VALUES ('$kode_transaksi', '$nama_pemesan', '$no_telepon', '$jenis_layanan', '$alamat_db', '$nomor_meja', '$metode_pembayaran', $total_pembayaran, $uang_bayar, $uang_kembali, '$status_default', '$tanggal_sekarang')";
    mysqli_query($conn, $q_transaksi);
    $id_transaksi_baru = mysqli_insert_id($conn);

    // 3. Simpan Detail Item
    $rincian_wa = "";
    foreach ($cart_items as $item) {
        $id_menu  = (int)($item['id'] ?? 0);
        $nama_m   = mysqli_real_escape_string($conn, $item['nama'] ?? '');
        $harga_m  = (float)($item['harga'] ?? 0);
        $qty_m    = (int)($item['qty'] ?? 1);
        $porsi_m  = $item['porsi'] ?? 'Normal';
        $subtotal = $harga_m * $qty_m;

        if ($id_pesanan_baru > 0) {
            mysqli_query($conn, "INSERT INTO detail_pesanan (id_pesanan, id_menu, nama_menu, harga, jumlah, subtotal) 
                                 VALUES ($id_pesanan_baru, $id_menu, '$nama_m', $harga_m, $qty_m, $subtotal)");
        }
        if ($id_transaksi_baru > 0) {
            mysqli_query($conn, "INSERT INTO detail_transaksi (id_transaksi, id_menu, nama_menu, harga, jumlah, subtotal) 
                                 VALUES ($id_transaksi_baru, $id_menu, '$nama_m', $harga_m, $qty_m, $subtotal)");
        }

        $rincian_wa .= "- " . ($item['nama'] ?? 'Menu') . " (" . $porsi_m . ") x " . $qty_m . " = Rp " . number_format($subtotal, 0, ',', '.') . "\n";
    }

    // ====== FORMAT PESAN WA ======
    $pesan_wa  = "Hallo *Toko Wenak Wenak* (Jl. Pattimura),\nSaya mau konfirmasi pesanan baru:\n\n";
    $pesan_wa .= "🆔 *Kode Transaksi:* " . $kode_transaksi . "\n";
    $pesan_wa .= "👤 *Nama Pemesan:* " . $nama_pemesan . "\n";
    $pesan_wa .= "📱 *No. WhatsApp:* " . $no_telepon . "\n";
    $pesan_wa .= "🚚 *Layanan:* " . $jenis_layanan . "\n";
    $pesan_wa .= "📍 *Alamat Pengiriman:* " . $alamat_gabungan . "\n\n";
    $pesan_wa .= "📋 *RINCIAN MENU PESANAN:*\n" . $rincian_wa . "\n";
    $pesan_wa .= "💰 *TOTAL HARGA:* Rp " . number_format($total_pembayaran, 0, ',', '.') . "\n";
    $pesan_wa .= "💳 *Metode Pembayaran:* " . $metode_pembayaran . "\n";

    if (strpos(strtolower($metode_pembayaran), 'tunai') !== false) {
        $pesan_wa .= "💵 *Uang Dibayarkan:* Rp " . number_format($uang_bayar, 0, ',', '.') . "\n";
        $pesan_wa .= "💵 *Uang Kembalian:* Rp " . number_format($uang_kembali, 0, ',', '.') . "\n\n";
    } else {
        $pesan_wa .= "\n";
    }

    $pesan_wa .= "Mohon diproses ya Kak. Terima kasih!";

    $url_wa = "https://wa.me/" . $NO_WA_KASIR . "?text=" . urlencode($pesan_wa);
    $id_redirect = ($id_pesanan_baru > 0) ? $id_pesanan_baru : $kode_transaksi;

    echo "<!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <title>Mengalihkan ke WhatsApp Toko...</title>
    </head>
    <body>
        <script>
            localStorage.removeItem('cart_wenak');
            window.open('" . $url_wa . "', '_blank');
            window.location.href = 'nota_pelanggan.php?id=" . $id_redirect . "';
        </script>
    </body>
    </html>";
    exit;

} else {
    header("Location: index.php");
    exit;
}
?>