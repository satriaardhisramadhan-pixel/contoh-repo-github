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

$NAMA_TOKO   = "Toko Wenak Wenak";
$NO_WA_TOKO  = "0881026767117";

// AMBIL KODE ATAU ID PESANAN DARI URL ATAU INPUT CARI
$id_pesanan     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$kode_transaksi = isset($_GET['kode']) ? mysqli_real_escape_string($conn, trim($_GET['kode'])) : '';

$pesanan = null;

if ($id_pesanan > 0) {
    $query = mysqli_query($conn, "SELECT * FROM pesanan WHERE id_pesanan = $id_pesanan");
    $pesanan = mysqli_fetch_assoc($query);
} elseif (!empty($kode_transaksi)) {
    $query = mysqli_query($conn, "SELECT * FROM pesanan WHERE kode_transaksi = '$kode_transaksi'");
    $pesanan = mysqli_fetch_assoc($query);
}

// ENDPOINT AJAX UNTUK AUTO-UPDATE STATUS LIVE
if (isset($_GET['ajax_check']) && $pesanan) {
    header('Content-Type: application/json');
    echo json_encode([
        'status'          => $pesanan['status_pesanan'],
        'kode_transaksi'  => $pesanan['kode_transaksi'],
        'updated_at'      => date('H:i:s')
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota & Lacak Status Pesanan - <?= $NAMA_TOKO; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background-color: #F8F5F2; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        .header-bar { background-color: #6B3E26; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .header-bar h2 { margin: 0; font-size: 1.3rem; }
        .btn-home { color: white; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.15); padding: 6px 16px; border-radius: 20px; }

        .container-nota { max-width: 750px; margin: 2rem auto; padding: 0 1rem; }
        .card-nota { background: white; border-radius: 16px; padding: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid #EAE3DF; }
        
        /* FORM CARI STATUS PESANAN */
        .search-box { background: #FFF8F3; border: 1px solid #FCD3B2; padding: 1.2rem; border-radius: 12px; margin-bottom: 1.5rem; text-align: center; }
        .search-box input { padding: 10px 14px; border: 1px solid #ccc; border-radius: 8px; width: 60%; font-size: 0.95rem; }
        .search-box button { padding: 10px 18px; background: #E87522; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }

        /* ALERT PESAN INFO SCREENSHOT */
        .alert-screenshot { background: #FFF3CD; border: 1px solid #FFEEBA; color: #856404; padding: 10px 14px; border-radius: 8px; font-size: 0.88rem; margin-top: 10px; text-align: center; line-height: 1.4; }

        /* STEPPER PROGRESS STATUS COD */
        .stepper-wrapper { display: flex; justify-content: space-between; margin: 2rem 0; position: relative; }
        .stepper-wrapper::before { content: ''; position: absolute; top: 20px; left: 10%; right: 10%; height: 4px; background: #E0E0E0; z-index: 1; }
        .stepper-progress { position: absolute; top: 20px; left: 10%; height: 4px; background: #2E7D32; z-index: 1; transition: width 0.4s ease; }
        
        .step-item { position: relative; z-index: 2; background: white; text-align: center; width: 22%; }
        .step-icon { width: 44px; height: 44px; border-radius: 50%; background: #F0F0F0; color: #666; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: 1.2rem; border: 3px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .step-item.active .step-icon { background: #E8F5E9; color: #2E7D32; border-color: #2E7D32; }
        .step-item.current .step-icon { background: #FFF3E0; color: #E87522; border-color: #E87522; animation: pulse 1.5s infinite; }
        .step-label { font-size: 0.82rem; font-weight: bold; color: #777; line-height: 1.3; }
        .step-item.active .step-label { color: #2E7D32; }
        .step-item.current .step-label { color: #E87522; }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(232, 117, 34, 0.5); }
            70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(232, 117, 34, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(232, 117, 34, 0); }
        }

        .badge-live { display: inline-flex; align-items: center; gap: 6px; background: #E8F5E9; color: #2E7D32; padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 0.85rem; border: 1px solid #A5D6A7; }
        .pulse-dot { width: 8px; height: 8px; background: #2E7D32; border-radius: 50%; display: inline-block; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0.3; } }

        .table-nota { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.95rem; }
        .table-nota th, .table-nota td { padding: 10px 12px; border-bottom: 1px solid #EEE; text-align: left; }
        .table-nota th { background: #FFF8F3; color: #6B3E26; }

        .btn-wa { display: block; width: 100%; text-align: center; background: #25D366; color: white; padding: 12px; border-radius: 10px; font-weight: bold; text-decoration: none; margin-top: 1.5rem; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.3); }
        .btn-wa:hover { background: #1EBE5D; }
    </style>
</head>
<body>

    <div class="header-bar">
        <h2>🍳 <?= $NAMA_TOKO; ?></h2>
        <a href="index.php" class="btn-home">🏠 Beranda Katalog</a>
    </div>

    <div class="container-nota">

        <!-- FORM CARI KODE TRANSAKSI -->
        <div class="search-box">
            <h4 style="margin: 0 0 8px 0; color: #6B3E26;">🔍 Lacak Status Pesanan COD Saya</h4>
            <form action="nota_pelanggan.php" method="GET" style="display: flex; gap: 8px; justify-content: center;">
                <input type="text" name="kode" value="<?= htmlspecialchars($kode_transaksi); ?>" placeholder="Masukkan Kode Transaksi (Contoh: TRX-2026...)" required>
                <button type="submit">Lacak Status</button>
            </form>
            <div class="alert-screenshot">
                💡 <strong>Perhatian:</strong> Jangan lupa screenshot atau salin kode pembayaran Anda agar Anda dapat melihat status atau lokasi pesanan Anda telah sampai pada tahap apa.
            </div>
        </div>

        <?php if ($pesanan) : ?>
            <?php
                $st = strtolower(trim($pesanan['status_pesanan']));
                
                // MAPPING PROGRESS STATUS
                $step = 1; // Default: Menunggu Konfirmasi
                $progress_width = "0%";

                if (strpos($st, 'masak') !== false || strpos($st, 'proses') !== false) {
                    $step = 2;
                    $progress_width = "33%";
                } elseif (strpos($st, 'kirim') !== false || strpos($st, 'jalan') !== false || strpos($st, 'siap') !== false) {
                    $step = 3;
                    $progress_width = "66%";
                } elseif (strpos($st, 'selesai') !== false) {
                    $step = 4;
                    $progress_width = "100%";
                }

                $metode = $pesanan['metode_pembayaran'] ?? 'COD / Tunai';
                $is_cod = (strpos(strtolower($metode), 'tunai') !== false || strpos(strtolower($metode), 'cash') !== false || strpos(strtolower($metode), 'cod') !== false);
                $icon_step3  = $is_cod ? '🛵' : '🛍️';
                $label_step3 = $is_cod ? 'Dalam Pengiriman (COD)' : 'Siap Diambil / Dikirim';
            ?>

            <div class="card-nota">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px dashed #EAE3DF; padding-bottom: 12px; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin:0; color:#6B3E26;">🧾 Nota & Status Pesanan</h3>
                        <small style="color:#777;">Kode TRX: <strong><?= htmlspecialchars($pesanan['kode_transaksi']); ?></strong></small>
                    </div>
                    <div>
                        <span class="badge-live">
                            <span class="pulse-dot"></span> Real-time Live Tracking
                        </span>
                    </div>
                </div>

                <!-- ALUR STEPPER STATUS VISUAL DENGAN SIMBOL SESUAI KALIMAT STATUS -->
                <h4 style="text-align: center; color: #6B3E26; margin-bottom: 0.5rem;">
                    Status Pesanan: <span id="text_status_live" style="color: #E87522; font-weight: 800;"><?= htmlspecialchars($pesanan['status_pesanan']); ?></span>
                </h4>

                <div class="stepper-wrapper">
                    <div class="stepper-progress" id="progress_bar_line" style="width: <?= $progress_width; ?>;"></div>

                    <div class="step-item <?= $step >= 1 ? ($step == 1 ? 'current' : 'active') : ''; ?>" id="step_1">
                        <div class="step-icon">⏳</div>
                        <div class="step-label">Menunggu Konfirmasi</div>
                    </div>

                    <div class="step-item <?= $step >= 2 ? ($step == 2 ? 'current' : 'active') : ''; ?>" id="step_2">
                        <div class="step-icon">🍳</div>
                        <div class="step-label">Sedang Dimasak</div>
                    </div>

                    <div class="step-item <?= $step >= 3 ? ($step == 3 ? 'current' : 'active') : ''; ?>" id="step_3">
                        <div class="step-icon"><?= $icon_step3; ?></div>
                        <div class="step-label"><?= $label_step3; ?></div>
                    </div>

                    <div class="step-item <?= $step >= 4 ? ($step == 4 ? 'current' : 'active') : ''; ?>" id="step_4">
                        <div class="step-icon">✅</div>
                        <div class="step-label">Pesanan Selesai</div>
                    </div>
                </div>

                <!-- DETAIL RINCIAN PESANAN PEMBELI -->
                <h4 style="color:#6B3E26; margin-top: 1.5rem; margin-bottom: 0.5rem;">📋 Detail Pelanggan & Pengiriman</h4>
                <table class="table-nota">
                    <tr>
                        <td style="width: 35%; color: #666;">Nama Pemesan:</td>
                        <td><strong><?= htmlspecialchars($pesanan['nama_pemesan']); ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color: #666;">Nomor WhatsApp:</td>
                        <td><?= htmlspecialchars($pesanan['no_telepon']); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #666;">Jenis Layanan:</td>
                        <td><strong style="color: #E87522;"><?= htmlspecialchars($pesanan['jenis_layanan']); ?></strong></td>
                    </tr>
                    <?php if (!empty($pesanan['alamat_lengkap'])) : ?>
                        <tr>
                            <td style="color: #666;">Alamat Pengiriman (COD):</td>
                            <td><?= htmlspecialchars($pesanan['alamat_lengkap']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($pesanan['nomor_meja'])) : ?>
                        <tr>
                            <td style="color: #666;">Nomor Meja:</td>
                            <td><strong><?= htmlspecialchars($pesanan['nomor_meja']); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="color: #666;">Metode Pembayaran:</td>
                        <td><strong><?= htmlspecialchars($pesanan['metode_pembayaran']); ?></strong></td>
                    </tr>
                    <tr>
                        <td style="color: #666;">Total Tagihan:</td>
                        <td><strong style="color: #E87522; font-size: 1.1rem;">Rp <?= number_format($pesanan['total_harga'], 0, ',', '.'); ?></strong></td>
                    </tr>
                    <?php if ($is_cod && $pesanan['uang_bayar'] > 0) : ?>
                        <tr>
                            <td style="color: #666;">Uang Disiapkan:</td>
                            <td>Rp <?= number_format($pesanan['uang_bayar'], 0, ',', '.'); ?></td>
                        </tr>
                        <tr>
                            <td style="color: #666;">Kembalian COD:</td>
                            <td><strong style="color: #2E7D32;">Rp <?= number_format($pesanan['uang_kembali'], 0, ',', '.'); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <?php
                    $pesan_wa = "Halo " . $NAMA_TOKO . ", saya ingin menanyakan status pesanan COD saya dengan Kode: " . $pesanan['kode_transaksi'] . " atas nama " . $pesanan['nama_pemesan'];
                    $url_wa   = "https://api.whatsapp.com/send?phone=" . preg_replace('/[^0-9]/', '', $NO_WA_TOKO) . "&text=" . urlencode($pesan_wa);
                ?>
                <a href="<?= $url_wa; ?>" target="_blank" class="btn-wa">
                    💬 Hubungi Kasir via WhatsApp
                </a>
            </div>

            <!-- JAVASCRIPT LIVE TRACKING AUTO UPDATE SETIAP 5 DETIK -->
            <script>
                const idPesananCurrent = <?= $pesanan['id_pesanan']; ?>;

                function updateStatusLive() {
                    fetch('nota_pelanggan.php?id=' + idPesananCurrent + '&ajax_check=1')
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.status) {
                                const statusTxt = data.status;
                                document.getElementById('text_status_live').innerText = statusTxt;

                                const stLower = statusTxt.toLowerCase();
                                let step = 1;
                                let width = "0%";

                                if (stLower.includes('masak') || stLower.includes('proses')) {
                                    step = 2;
                                    width = "33%";
                                } else if (stLower.includes('kirim') || stLower.includes('jalan') || stLower.includes('siap')) {
                                    step = 3;
                                    width = "66%";
                                } else if (stLower.includes('selesai')) {
                                    step = 4;
                                    width = "100%";
                                }

                                document.getElementById('progress_bar_line').style.width = width;

                                for (let i = 1; i <= 4; i++) {
                                    const el = document.getElementById('step_' + i);
                                    if (el) {
                                        el.className = 'step-item';
                                        if (i < step) {
                                            el.classList.add('active');
                                        } else if (i === step) {
                                            el.classList.add('current');
                                        }
                                    }
                                }
                            }
                        })
                        .catch(err => console.log('Checking status update...'));
                }

                // Cek Perubahan Status Otomatis Setiap 5 Detik
                setInterval(updateStatusLive, 5000);
            </script>

        <?php elseif (!empty($kode_transaksi)) : ?>
            <div style="background: #FFEBEE; color: #C83A2E; padding: 1.5rem; border-radius: 12px; text-align: center; font-weight: bold;">
                ❌ Pesanan dengan Kode Transaksi "<?= htmlspecialchars($kode_transaksi); ?>" tidak ditemukan. Silakan periksa kembali kode Anda.
            </div>
        <?php else : ?>
            <div class="card-nota" style="text-align: center;">
                <h3>🔎 Lacak Pesanan COD Anda</h3>
                <p style="color: #666;">Masukkan Kode Transaksi yang Anda dapatkan saat melakukan checkout (Contoh: TRX-2026...)</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>