<?php
session_start();

// ====== KONEKSI DATABASE ======
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'wenak_wenak_db';
    $conn = mysqli_connect($host, $user, $pass, $db);
}

if (!$conn) {
    die("<div style='padding:20px; font-family:sans-serif; background:#f8d7da; color:#721c24;'>
            <h3>⚠️ Koneksi Database Gagal!</h3>
         </div>");
}

// ====== MIGRASI KOLOM DATABASE OTOMATIS (MENCEGAH EROR UNKNOWN COLUMN) ======
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN jenis_layanan VARCHAR(50) DEFAULT 'Delivery'");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN alamat_lengkap TEXT");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN nomor_meja VARCHAR(50)");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN catatan TEXT");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN uang_bayar DECIMAL(12,2) DEFAULT 0");
@mysqli_query($conn, "ALTER TABLE pesanan ADD COLUMN uang_kembali DECIMAL(12,2) DEFAULT 0");

// ====== PENGATURAN INFORMASI TOKO & PEMBAYARAN ======
$NAMA_TOKO    = "Toko Wenak Wenak";
$ALAMAT_TOKO  = "Jl. Pattimura No. 123, Kediri";
$NO_WA_TOKO   = "0881026767117";

// BARCODE QRIS & REKENING PEMBAYARAN
$QRIS_IMAGE_URL = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=QRIS-TOKO-WENAK-WENAK-KEDIRI";
$NO_REK_BCA     = "8830-1234-5678 (a.n. Toko Wenak Wenak)";
$NO_REK_BRI     = "0112-01-001234-50-8 (a.n. Toko Wenak Wenak)";
$NO_EWALLET     = "0881026767117 (DANA / GoPay / OVO / ShopeePay)";

// ====== PROSES SUBMIT PESANAN ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_pesanan'])) {
    $nama_pemesan     = mysqli_real_escape_string($conn, trim($_POST['nama_pemesan']));
    $no_telepon       = mysqli_real_escape_string($conn, trim($_POST['no_telepon']));
    $jenis_layanan    = mysqli_real_escape_string($conn, $_POST['jenis_layanan'] ?? 'Delivery');
    $alamat_lengkap   = mysqli_real_escape_string($conn, trim($_POST['alamat_lengkap'] ?? ''));
    $nomor_meja       = mysqli_real_escape_string($conn, trim($_POST['nomor_meja'] ?? ''));
    $catatan          = mysqli_real_escape_string($conn, trim($_POST['catatan'] ?? ''));
    $metode_bayar     = mysqli_real_escape_string($conn, $_POST['metode_pembayaran'] ?? 'Tunai');
    $total_harga      = (float)($_POST['total_harga'] ?? 0);
    $uang_bayar       = (float)($_POST['uang_bayar'] ?? $total_harga);
    $uang_kembali     = max(0, $uang_bayar - $total_harga);
    $cart_json        = $_POST['cart_json'] ?? '[]';

    // Generate Kode Transaksi Unik
    $kode_transaksi   = "TRX-" . date('Ymd') . "-" . rand(1000, 9999);
    $tanggal_sekarang = date('Y-m-d H:i:s');
    $status_pesanan   = "Menunggu Konfirmasi";

    // Simpan Ke Database
    $query_insert = "INSERT INTO pesanan 
        (kode_transaksi, nama_pemesan, no_telepon, jenis_layanan, alamat_lengkap, nomor_meja, catatan, total_harga, metode_pembayaran, uang_bayar, uang_kembali, status_pesanan, created_at) 
        VALUES 
        ('$kode_transaksi', '$nama_pemesan', '$no_telepon', '$jenis_layanan', '$alamat_lengkap', '$nomor_meja', '$catatan', $total_harga, '$metode_bayar', $uang_bayar, $uang_kembali, '$status_pesanan', '$tanggal_sekarang')";
    
    if (mysqli_query($conn, $query_insert)) {
        $id_pesanan_baru = mysqli_insert_id($conn);

        // REDIRECT KE HALAMAN NOTA & STATUS PELACAKAN PESANAN
        header("Location: nota_pelanggan.php?id=" . $id_pesanan_baru);
        exit;
    } else {
        $error_msg = "Gagal memproses pesanan: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Checkout & Pembayaran - <?= $NAMA_TOKO; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #F8F5F2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        .header-bar { background-color: #6B3E26; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .header-bar h2 { margin: 0; font-size: 1.3rem; }
        .btn-kembali { color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.15); padding: 6px 16px; border-radius: 20px; }
        
        .checkout-container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .card-form { background: white; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #EAE3DF; }
        .card-form h3 { color: #6B3E26; border-bottom: 2px solid #FCD3B2; padding-bottom: 8px; margin-top: 0; }

        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 6px; color: #444; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 8px; box-sizing: border-box; font-size: 0.95rem; }

        /* BOX PEMBAYARAN TUNAI */
        .box-tunai { background: #E8F5E9; border: 1px solid #C8E6C9; border-radius: 12px; padding: 1.2rem; margin-top: 1rem; }
        
        /* BOX PEMBAYARAN QRIS / BARCODE & TRANSFER */
        .box-ewallet { background: #FFF8F3; border: 2px dashed #E87522; border-radius: 12px; padding: 1.5rem; margin-top: 1rem; text-align: center; display: none; }
        .qris-img { width: 200px; height: 200px; border-radius: 12px; border: 3px solid #6B3E26; margin: 10px auto; display: block; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .rekening-list { text-align: left; background: white; padding: 1rem; border-radius: 8px; margin-top: 10px; border: 1px solid #FCD3B2; font-size: 0.9rem; }

        .btn-submit { width: 100%; background-color: #E87522; color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: bold; font-size: 1.1rem; cursor: pointer; box-shadow: 0 4px 15px rgba(232, 117, 34, 0.3); margin-top: 1.5rem; }
        .btn-submit:hover { background-color: #D06215; }
    </style>
</head>
<body>

    <div class="header-bar">
        <h2>🍳 <?= $NAMA_TOKO; ?></h2>
        <a href="index.php" class="btn-kembali">← Kembali ke Katalog</a>
    </div>

    <div class="checkout-container">
        <div class="card-form">
            <h3>📝 Detail Pesanan & Pembayaran</h3>

            <?php if (isset($error_msg)) : ?>
                <div style="background:#FFEBEE; color:#C83A2E; padding:10px 15px; border-radius:8px; margin-bottom:1rem; font-weight:bold;">
                    ⚠️ <?= $error_msg; ?>
                </div>
            <?php endif; ?>

            <form action="form_pesanan.php" method="POST" id="formOrder">
                <input type="hidden" name="proses_pesanan" value="1">
                <input type="hidden" name="cart_json" id="cartJsonInput">
                <input type="hidden" name="total_harga" id="totalHargaInput" value="0">

                <div class="form-group">
                    <label>Nama Lengkap Pemesan *</label>
                    <input type="text" name="nama_pemesan" class="form-control" placeholder="Masukkan nama Anda" required>
                </div>

                <div class="form-group">
                    <label>Nomor WhatsApp *</label>
                    <input type="text" name="no_telepon" class="form-control" placeholder="Contoh: 0881026767117" required>
                </div>

                <div class="form-group">
                    <label>Pilihan Jenis Layanan *</label>
                    <select name="jenis_layanan" id="jenis_layanan" class="form-control" onchange="toggleLayanan()" required>
                        <option value="Delivery">🛵 Delivery / Antar ke Rumah</option>
                        <option value="Dine-In">🍽️ Makan di Tempat (Dine-In)</option>
                        <option value="Takeaway">🛍️ Ambil Sendiri (Takeaway)</option>
                    </select>
                </div>

                <div class="form-group" id="group_alamat">
                    <label>Alamat Lengkap Pengiriman *</label>
                    <textarea name="alamat_lengkap" class="form-control" rows="3" placeholder="Isikan nama jalan, RT/RW, desa/kelurahan, dan patokan rumah..."></textarea>
                </div>

                <div class="form-group" id="group_meja" style="display:none;">
                    <label>Nomor Meja *</label>
                    <input type="text" name="nomor_meja" class="form-control" placeholder="Contoh: Meja 05">
                </div>

                <div class="form-group">
                    <label>Catatan Pesanan (Opsional)</label>
                    <input type="text" name="catatan" class="form-control" placeholder="Contoh: Pedas sedang, tanpa timun">
                </div>

                <div class="form-group">
                    <label>Pilihan Metode Pembayaran *</label>
                    <select name="metode_pembayaran" id="metode_pembayaran" class="form-control" onchange="toggleMetodePembayaran()" required>
                        <option value="Tunai / Cash / COD">💵 Tunai / Bayar Cash / COD</option>
                        <option value="E-Wallet / Transfer Bank / QRIS">💳 E-Wallet / Transfer Bank / Barcode QRIS</option>
                    </select>
                </div>

                <!-- BOX 1: PEMBAYARAN TUNAI / CASH -->
                <div class="box-tunai" id="box_pembayaran_tunai">
                    <h4 style="margin:0 0 10px 0; color:#2E7D32;">💵 Hitung Pengembalian Uang Tunai</h4>
                    <div class="form-group">
                        <label>Nominal Uang Diserahkan / Dibayarkan (Rp) *</label>
                        <input type="number" name="uang_bayar" id="uang_bayar" class="form-control" placeholder="Contoh: 50000" oninput="hitungKembalian()">
                    </div>
                    <div style="font-weight:bold; color:#2E7D32; font-size:1.05rem;">
                        Uang Kembalian: <span id="text_kembalian">Rp 0</span>
                    </div>
                </div>

                <!-- BOX 2: BARCODE QRIS & TRANSFER BANK -->
                <div class="box-ewallet" id="box_pembayaran_ewallet">
                    <h4 style="margin:0; color:#6B3E26;">📱 Barcode Pembayaran QRIS & Transfer</h4>
                    <p style="font-size:0.88rem; color:#666; margin:6px 0 12px;">Scan Barcode QRIS di bawah ini dengan BCA, BRI, DANA, OVO, GoPay, atau ShopeePay:</p>

                    <!-- TAMPILAN BARCODE QRIS -->
                    <img src="<?= $QRIS_IMAGE_URL; ?>" alt="QRIS Toko Wenak Wenak" class="qris-img">
                    <p style="font-weight:bold; color:#E87522; margin:5px 0;">QRIS Toko Wenak Wenak</p>

                    <div class="rekening-list">
                        <strong>Opsi Transfer Bank & E-Wallet:</strong>
                        <ul style="margin:6px 0 0; padding-left:20px; line-height:1.6;">
                            <li><strong>Bank BCA:</strong> <?= $NO_REK_BCA; ?></li>
                            <li><strong>Bank BRI:</strong> <?= $NO_REK_BRI; ?></li>
                            <li><strong>E-Wallet:</strong> <?= $NO_EWALLET; ?></li>
                        </ul>
                    </div>
                </div>

                <div style="background:#FAFAFA; border:1px solid #EAE3DF; border-radius:12px; padding:1rem; margin-top:1.5rem; text-align:right;">
                    <span style="font-size:1.1rem; font-weight:bold; color:#6B3E26;">Total Tagihan Pesanan: </span>
                    <span id="text_total_tagihan" style="font-size:1.3rem; font-weight:800; color:#E87522;">Rp 0</span>
                </div>

                <button type="submit" class="btn-submit">
                    🚀 Buat Pesanan & Lanjut ke Nota ➔
                </button>
            </form>
        </div>
    </div>

    <script>
        let totalHargaPesanan = 0;

        // Ambil Data Keranjang dari LocalStorage
        function loadCartData() {
            const cartData = localStorage.getItem('cart_wenak');
            if (cartData) {
                document.getElementById('cartJsonInput').value = cartData;
                const cartArray = JSON.parse(cartData);
                totalHargaPesanan = cartArray.reduce((sum, item) => sum + (item.harga * item.qty), 0);
                
                document.getElementById('totalHargaInput').value = totalHargaPesanan;
                document.getElementById('text_total_tagihan').innerText = 'Rp ' + totalHargaPesanan.toLocaleString('id-ID');
                
                // Isi default uang bayar
                const inputUangBayar = document.getElementById('uang_bayar');
                if (inputUangBayar && !inputUangBayar.value) {
                    inputUangBayar.value = totalHargaPesanan;
                }
                hitungKembalian();
            }
        }

        // Tampilkan/Sembunyikan Alamat atau Meja
        function toggleLayanan() {
            const jenis = document.getElementById('jenis_layanan').value;
            const groupAlamat = document.getElementById('group_alamat');
            const groupMeja   = document.getElementById('group_meja');

            if (jenis === 'Dine-In') {
                groupAlamat.style.display = 'none';
                groupMeja.style.display   = 'block';
            } else {
                groupAlamat.style.display = 'block';
                groupMeja.style.display   = 'none';
            }
        }

        // Tampilkan/Sembunyikan Metode Pembayaran (Tunai vs QRIS Barcode)
        function toggleMetodePembayaran() {
            const metode = document.getElementById('metode_pembayaran').value;
            const boxTunai   = document.getElementById('box_pembayaran_tunai');
            const boxEwallet = document.getElementById('box_pembayaran_ewallet');

            if (metode.includes('E-Wallet') || metode.includes('QRIS') || metode.includes('Transfer')) {
                boxTunai.style.display   = 'none';
                boxEwallet.style.display = 'block';
            } else {
                boxTunai.style.display   = 'block';
                boxEwallet.style.display = 'none';
            }
        }

        // Hitung Kembalian Uang Tunai
        function hitungKembalian() {
            const bayar = parseFloat(document.getElementById('uang_bayar').value) || 0;
            const kembalian = Math.max(0, bayar - totalHargaPesanan);
            document.getElementById('text_kembalian').innerText = 'Rp ' + kembalian.toLocaleString('id-ID');
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadCartData();
            toggleLayanan();
            toggleMetodePembayaran();
        });
    </script>
</body>
</html>