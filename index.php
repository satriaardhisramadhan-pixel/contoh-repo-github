<?php
// ====== SETTING TIMEZONE WIB (BOJONEGORO) ======
date_default_timezone_set('Asia/Jakarta');

// ====== Memanggil Koneksi Database ======
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} else {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'wenak_wenak_db';
    $conn = mysqli_connect($host, $user, $pass, $db);
    if (!$conn) {
        die("<div style='padding:20px; font-family:sans-serif; background:#f8d7da; color:#721c24; border-radius:8px; margin:30px auto; max-width:600px;'>
                <h3>⚠️ Koneksi Database Gagal!</h3>
             </div>");
    }
}

// ====== PENGATURAN INFORMASI & JAM OPERASIONAL TOKO ======
$NAMA_TOKO    = "Toko Wenak Wenak";
$ALAMAT_TOKO  = "Jalan Pattimura, Kabupaten Bojonegoro, Kecamatan Bojonegoro";
$NO_WA_TOKO   = "0881026767117";

$jam_sekarang = (int)date('H');
$jam_buka     = 9;  // Buka Jam 09:00 WIB
$jam_tutup    = 22; // Tutup Jam 22:00 WIB

// Cek apakah toko sedang BUKA atau TUTUP
$is_toko_buka = ($jam_sekarang >= $jam_buka && $jam_sekarang < $jam_tutup);

// ====== AMBIL DATA MENU DARI DATABASE ======
@mysqli_query($conn, "ALTER TABLE menu ADD COLUMN stok VARCHAR(20) DEFAULT 'Tersedia'");
$query_menu  = "SELECT * FROM menu ORDER BY id_menu ASC";
$result_menu = mysqli_query($conn, $query_menu);

$judul_kategori = [
    'makanan'  => '🍱 Makanan Utama (Nasi Goreng)',
    'minuman'  => '🍹 Minuman Segar',
    'tambahan' => '🍢 Topping & Pelengkap'
];

$kelompok = [
    'makanan'  => [],
    'minuman'  => [],
    'tambahan' => []
];

$seen_menu = [];

if ($result_menu && mysqli_num_rows($result_menu) > 0) {
    while ($m = mysqli_fetch_assoc($result_menu)) {
        $nama_raw = strtolower(trim($m['nama_menu']));
        $kat      = !empty($m['kategori']) ? strtolower(trim($m['kategori'])) : '';

        if (empty($kat)) {
            if (strpos($nama_raw, 'teh') !== false || strpos($nama_raw, 'jeruk') !== false || strpos($nama_raw, 'air') !== false || strpos($nama_raw, 'es') !== false) {
                $kat = 'minuman';
            } elseif (strpos($nama_raw, 'topping') !== false || strpos($nama_raw, 'kerupuk') !== false || strpos($nama_raw, 'sosis') !== false || strpos($nama_raw, 'keju') !== false || strpos($nama_raw, 'bakso') !== false || strpos($nama_raw, 'ati') !== false || strpos($nama_raw, 'telur') !== false) {
                $kat = 'tambahan';
            } else {
                $kat = 'makanan';
            }
        }

        $clean_name = str_replace(['goreng', 'renyah', 'parut', 'spesial'], '', $nama_raw);
        $clean_name = preg_replace('/\s+/', ' ', trim($clean_name));
        $porsi      = !empty($m['variasi_porsi']) ? strtolower(trim($m['variasi_porsi'])) : 'normal';

        $key = $clean_name . '_' . $porsi;
        if (in_array($key, $seen_menu)) {
            continue;
        }
        $seen_menu[] = $key;

        $m['variasi_porsi_fix'] = !empty($m['variasi_porsi']) ? $m['variasi_porsi'] : 'Normal';
        $kelompok[$kat][]        = $m;
    }
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

// ====== FUNGSI PENENTU GAMBAR MENU PRESISI ======
function dapatkanGambarMenu($nama_menu, $gambar_db = '') {
    if (!empty($gambar_db) && file_exists($gambar_db)) {
        return $gambar_db;
    }

    $nama = strtolower(trim($nama_menu));

    if (strpos($nama, 'ati') !== false || strpos($nama, 'ampela') !== false) {
        return 'assets/img/menu/Ati_Ampela.svg.jpg';
    } elseif (strpos($nama, 'seafood') !== false || strpos($nama, 'udang') !== false) {
        return 'assets/img/menu/Nasi_Goreng_Seafood.svg.jpg';
    } elseif (strpos($nama, 'sosis') !== false) {
        return 'https://images.unsplash.com/photo-1585325701165-351af916e581?auto=format&fit=crop&w=600&q=80';
    } elseif (strpos($nama, 'mineral') !== false || strpos($nama, 'air') !== false) {
        return 'assets/img/menu/Air mineral.svg.jpeg';
    } elseif (strpos($nama, 'keju') !== false) {
        return 'assets/img/menu/Keju parut.svg.jpeg';
    } elseif (strpos($nama, 'kerupuk') !== false) {
        return 'assets/img/menu/Kerupuk kaleng.svg.jpeg';
    } elseif (strpos($nama, 'kambing') !== false) {
        return 'assets/img/menu/Nasi_Goreng_Kambing.svg.jpeg';
    } elseif (strpos($nama, 'bakso') !== false) {
        return 'assets/img/menu/Topping_Bakso.svg.jpeg';
    } elseif (strpos($nama, 'telur') !== false && strpos($nama, 'nasi') === false) {
        return 'assets/img/menu/Telur.svg.jpg';
    } elseif (strpos($nama, 'teh') !== false) {
        return 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?auto=format&fit=crop&w=600&q=80';
    } elseif (strpos($nama, 'jeruk') !== false) {
        return 'https://images.unsplash.com/photo-1613478223719-2ab802602423?auto=format&fit=crop&w=600&q=80';
    }

    return 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=600&q=80';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $NAMA_TOKO; ?> - Cita Rasa Nasi Goreng Otentik Bojonegoro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .info-bar {
            background-color: #4A2B1A;
            color: #FCD3B2;
            padding: 8px 15px;
            font-size: 0.85rem;
            text-align: center;
            font-weight: 600;
        }
        .hero-about {
            background: linear-gradient(rgba(107,62,38,0.85), rgba(45,37,34,0.9)), url('https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=1200&q=80') center/cover;
            color: white;
            padding: 4rem 1.5rem;
            text-align: center;
        }
        .hero-about h1 {
            font-size: 2.3rem;
            margin-bottom: 0.8rem;
            font-family: 'Montserrat', sans-serif;
            color: #FFD1A4;
        }
        .hero-about p {
            max-width: 750px;
            margin: 0 auto 1.5rem;
            line-height: 1.6;
            font-size: 1.05rem;
        }
        .store-badge {
            display: inline-block;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-top: 10px;
        }
        .badge-open { background-color: #2E7D32; box-shadow: 0 0 10px rgba(46, 125, 50, 0.5); }
        .badge-closed { background-color: #C83A2E; box-shadow: 0 0 10px rgba(200, 58, 46, 0.5); }

        .search-container {
            margin: 2rem auto 1rem;
            max-width: 600px;
        }
        .search-input {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #EAE3DF;
            border-radius: 30px;
            font-size: 1rem;
            outline: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
        }
        .search-input:focus {
            border-color: #E87522;
            box-shadow: 0 4px 15px rgba(232, 117, 34, 0.2);
        }

        .kategori-sec { margin-top: 2.5rem; }
        .kategori-sec h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            color: #6B3E26;
            border-bottom: 2px solid #FCD3B2;
            padding-bottom: 8px;
            margin-bottom: 1.5rem;
        }

        .menu-img-container {
            width: 100%;
            height: 190px;
            position: relative;
            overflow: hidden;
            background-color: #f0f0f0;
        }
        .menu-img-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .menu-badge-overlay {
            position: absolute;
            top: 12px;
            right: 12px;
            background-color: #C83A2E;
            color: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            text-transform: uppercase;
        }

        .menu-card.stok-habis {
            filter: grayscale(80%);
            opacity: 0.65;
            background-color: #f5f5f5;
            border: 1px dashed #ccc;
        }
        .menu-card.stok-habis .btn-order {
            background-color: #888888 !important;
            color: #ffffff !important;
            cursor: not-allowed !important;
            box-shadow: none !important;
            pointer-events: none !important;
        }

        .select-option-style {
            width: 100%;
            padding: 6px 10px;
            margin-top: 6px;
            margin-bottom: 8px;
            border: 1px solid #E87522;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: bold;
            color: #6B3E26;
            background-color: #FFF8F3;
            outline: none;
            cursor: pointer;
        }

        .cart-float-btn {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background-color: #E87522;
            color: #FFFFFF;
            border: none;
            border-radius: 50px;
            padding: 0.9rem 1.6rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(232, 117, 34, 0.4);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1001;
            transition: all 0.3s ease;
        }
        .cart-float-btn:hover {
            background-color: #D06215;
            transform: translateY(-3px);
        }
        .cart-badge {
            background-color: #C83A2E;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .cart-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        .cart-modal-overlay.active { display: flex; }
        .cart-modal {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 550px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .cart-header {
            background-color: #6B3E26;
            color: #ffffff;
            padding: 1.2rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cart-header h3 { margin: 0; font-family: 'Montserrat', sans-serif; font-size: 1.2rem; }
        .close-cart { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .cart-body { padding: 1.5rem; overflow-y: auto; flex-grow: 1; }
        .cart-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px dashed #EAE3DF;
        }
        .cart-item-img { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; }
        .cart-item-info { flex-grow: 1; }
        .cart-item-title { font-weight: 700; color: #2D2522; margin-bottom: 2px; }
        .cart-item-price { color: #E87522; font-weight: 700; font-size: 0.9rem; }
        .qty-controls { display: flex; align-items: center; gap: 8px; }
        .btn-qty {
            background: #FFF5EE;
            border: 1px solid #FCD3B2;
            color: #E87522;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        .cart-footer { padding: 1.2rem 1.5rem; background: #FAFAFA; border-top: 1px solid #EAE3DF; }
        .cart-total-row { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; color: #6B3E26; }
        .btn-checkout {
            width: 100%;
            background-color: #E87522;
            color: white;
            border: none;
            padding: 0.9rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(232, 117, 34, 0.3);
        }
        .btn-checkout:hover { background-color: #D06215; }
        .cart-empty { text-align: center; color: #888; padding: 2rem 0; }
    </style>
</head>
<body>

    <div class="info-bar">
        📍 <strong>Alamat Toko:</strong> <?= $ALAMAT_TOKO; ?> | 📱 WA: <strong><?= $NO_WA_TOKO; ?></strong> | 
        <?php if ($is_toko_buka) : ?>
            <span style="color:#81C784;">🟢 TOKO BUKA</span>
        <?php else : ?>
            <span style="color:#FF8A80;">🔴 TOKO TUTUP (Buka Jam 09.00 WIB)</span>
        <?php endif; ?>
    </div>

    <header>
        <nav class="navbar">
            <a href="index.php" class="logo">
                🍳 <?= $NAMA_TOKO; ?>
            </a>
            <ul class="nav-links">
                <li>
                    <a href="nota_pelanggan.php" style="background:#2E7D32; color:white; padding:6px 14px; border-radius:20px; text-decoration:none; font-weight:bold; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(46,125,50,0.3);">
                        🛵 Lacak Status COD
                    </a>
                </li>
                <li><a href="#pengenalan">Pengenalan</a></li>
                <li><a href="#katalog">Katalog Menu</a></li>
                <li><a href="#kontak">Kontak</a></li>
                <li><a href="login.php" class="btn-login" style="background:#E87522; color:white; padding:6px 14px; border-radius:20px; text-decoration:none;">🔐 Login Admin</a></li>
            </ul>
        </nav>
    </header>

    <section id="pengenalan" class="hero-about">
        <h1>Selamat Datang di <?= $NAMA_TOKO; ?></h1>
        <p>
            Menyajikan Nasi Goreng beraroma rempah pilihan khas racikan otentik di Bojonegoro dengan pilihan porsi mantap, aneka topping pelengkap favorit, serta minuman segar. Setiap porsi dimasak segar langsung saat Anda memesan!
        </p>
        <div>
            <?php if ($is_toko_buka) : ?>
                <span class="store-badge badge-open">🟢 TOKO BUKA (Melayani Pesanan)</span>
            <?php else : ?>
                <span class="store-badge badge-closed">🔴 TOKO TUTUP (Jam Operasional: 09.00 - 22.00 WIB)</span>
            <?php endif; ?>

            <span class="store-badge" style="background:#6B3E26; margin-left:8px;">📍 Jalan Pattimura, Kabupaten Bojonegoro, Kecamatan Bojonegoro</span>
        </div>
        <div style="margin-top: 1.8rem;">
            <a href="#katalog" class="btn-cta" style="background:#E87522; color:white; padding:12px 28px; border-radius:30px; font-weight:bold; text-decoration:none;">🍱 Lihat Katalog & Pesan</a>
        </div>
    </section>

    <section id="katalog" class="container" style="padding: 2rem 1.5rem;">
        <div class="section-title" style="text-align:center; margin-bottom:1.5rem;">
            <h2 style="color:#6B3E26; font-family:'Montserrat', sans-serif;">Daftar Katalog Menu Lengkap</h2>
            <p style="color:#665E5A;">Pilih variasi Nasi Goreng, Minuman Segar, dan Topping Pelengkap favorit Anda</p>
        </div>

        <div class="search-container">
            <input type="text" id="cari" class="search-input" placeholder="🔍 Cari menu nasi goreng, minuman, atau topping...">
        </div>

        <?php 
        $ada_menu = false;
        foreach ($kelompok as $kat => $items) {
            if (!empty($items)) {
                $ada_menu = true;
                break;
            }
        }
        ?>

        <?php if (!$ada_menu) : ?>
            <p style="text-align: center; color: #888; font-size: 1.1rem; padding: 3rem 0;">
                Belum ada menu tersedia di database.
            </p>
        <?php else : ?>
            <?php foreach ($kelompok as $kat => $items) : ?>
                <?php if (!empty($items)) : ?>
                    <section class="kategori-sec">
                        <h2><?= e($judul_kategori[$kat] ?? ucfirst($kat)); ?></h2>
                        <div class="menu-grid">
                            <?php foreach ($items as $m) : ?>
                                <?php 
                                    $id_m     = $m['id_menu'];
                                    $nama_m   = $m['nama_menu'];
                                    $harga_m  = $m['harga'];
                                    $porsi_m  = $m['variasi_porsi_fix'];
                                    $desc_m   = $m['deskripsi'] ?? '';
                                    
                                    // PENGECEKAN STOK SERAGAM DARI ADMIN DENGAN BERBAGAI VARIASI TEKS
                                    $stok_raw = strtolower(trim((string)($m['stok'] ?? 'tersedia')));
                                    $is_habis = ($stok_raw === 'habis' || $stok_raw === 'tidak tersedia' || $stok_raw === 'tidak_tersedia' || $stok_raw === 'kosong' || $stok_raw === '0' || strpos($stok_raw, 'habis') !== false || strpos($stok_raw, 'tidak') !== false);

                                    $src_img  = dapatkanGambarMenu($nama_m, $m['gambar'] ?? '');
                                    $is_telur = (strpos(strtolower($nama_m), 'telur') !== false);
                                ?>
                                <article class="menu-card <?= $is_habis ? 'stok-habis' : ''; ?>" data-nama="<?= e(strtolower($nama_m . ' ' . $desc_m)); ?>">
                                    <div class="menu-img-container">
                                        <img src="<?= e($src_img); ?>" 
                                             alt="<?= e($nama_m); ?>" 
                                             class="menu-img-photo" 
                                             loading="lazy"
                                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=600&q=80';">
                                        
                                        <?php if ($is_habis) : ?>
                                            <span class="menu-badge-overlay" style="background:#888888;">🔴 STOK HABIS</span>
                                        <?php elseif (!empty($porsi_m)) : ?>
                                            <span class="menu-badge-overlay"><?= e($porsi_m); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="menu-body">
                                        <h3 class="menu-title"><?= e($nama_m); ?></h3>
                                        <p class="menu-desc"><?= e($desc_m); ?></p>
                                        
                                        <?php if ($is_telur) : ?>
                                            <div style="margin-top:6px;">
                                                <label style="font-size:0.8rem; font-weight:bold; color:#6B3E26;">🍳 Jenis Telur:</label>
                                                <select id="opsi_telur_<?= $id_m; ?>" class="select-option-style" <?= $is_habis ? 'disabled' : ''; ?>>
                                                    <option value="Dadar">🍳 Telur Dadar</option>
                                                    <option value="Ceplok">🍳 Telur Ceplok (Mata Sapi)</option>
                                                </select>
                                            </div>
                                        <?php else : ?>
                                            <div class="portion-select">
                                                <span class="portion-tag">Porsi: <?= e($porsi_m); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="menu-footer">
                                            <span class="price"><?= rupiah($harga_m); ?></span>
                                            
                                            <?php if ($is_habis) : ?>
                                                <button type="button" class="btn-order" disabled style="background-color:#888888 !important; color:#ffffff !important; cursor:not-allowed !important; pointer-events:none !important; box-shadow:none !important;">
                                                    🔴 Stok Habis
                                                </button>
                                            <?php else : ?>
                                                <button type="button" 
                                                        class="btn-order"
                                                        onclick="tambahKeKeranjang(<?= $id_m; ?>, '<?= addslashes($nama_m); ?>', <?= $harga_m; ?>, '<?= addslashes($porsi_m); ?>', '<?= addslashes($src_img); ?>', <?= $is_telur ? 'true' : 'false'; ?>)">
                                                    + Keranjang
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <button class="cart-float-btn" onclick="bukaModalKeranjang()">
        🛒 Keranjang Belanja
        <span class="cart-badge" id="cartBadgeCount">0</span>
    </button>

    <div class="cart-modal-overlay" id="cartModal">
        <div class="cart-modal">
            <div class="cart-header">
                <h3>🛒 Keranjang Pesanan Anda</h3>
                <button class="close-cart" onclick="tutupModalKeranjang()">&times;</button>
            </div>
            
            <div class="cart-body" id="cartItemList">
            </div>
            
            <div class="cart-footer">
                <div class="cart-total-row">
                    <span>Total Pembayaran:</span>
                    <span id="cartTotalPrice" style="color: #E87522;">Rp 0</span>
                </div>
                
                <form action="form_pesanan.php" method="POST" id="formCheckout">
                    <input type="hidden" name="cart_data" id="cartDataInput">
                    <button type="button" class="btn-checkout" onclick="prosesCheckout()">
                        Lanjut Isikan Alamat & Checkout ➔
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer id="kontak" style="background:#2D2522; color:white; padding: 2.5rem 1.5rem 1rem;">
        <div style="max-width:1100px; margin:0 auto; display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:2rem;">
            <div>
                <h4 style="color:#FFD1A4; margin-bottom:0.8rem; font-family:'Montserrat', sans-serif;">🍳 <?= $NAMA_TOKO; ?></h4>
                <p style="font-size:0.9rem; line-height:1.6; color:#ccc;">
                    Pusat Kuliner Nasi Goreng Otentik dengan bumbu rempah pilihan terbaik di Bojonegoro.
                </p>
            </div>
            <div>
                <h4 style="color:#FFD1A4; margin-bottom:0.8rem; font-family:'Montserrat', sans-serif;">📍 Alamat & Jam Buka</h4>
                <p style="font-size:0.9rem; line-height:1.6; color:#ccc;">
                    <?= $ALAMAT_TOKO; ?><br>
                    📱 WA: <strong><?= $NO_WA_TOKO; ?></strong><br>
                    🕒 Jam Operasional: <strong>09.00 - 22.00 WIB</strong>
                </p>
            </div>
            <div>
                <h4 style="color:#FFD1A4; margin-bottom:0.8rem; font-family:'Montserrat', sans-serif;">Portal Akses Pegawai</h4>
                <p style="font-size:0.9rem; color:#ccc;">
                    Portal khusus Administrator Owner:<br>
                    <a href="login.php" style="color:#E87522; font-weight:bold; text-decoration:underline;">🔑 Login Portal Admin</a>
                </p>
            </div>
        </div>
        <div style="text-align:center; border-top:1px solid #443B37; margin-top:2rem; padding-top:1rem; font-size:0.85rem; color:#888;">
            &copy; <?= date('Y'); ?> <?= $NAMA_TOKO; ?>. All rights reserved.
        </div>
    </footer>

    <!-- MENERUSKAN STATUS JAM OPERASIONAL KE JAVASCRIPT -->
    <script>
        const isTokoBukaPHP = <?= $is_toko_buka ? 'true' : 'false'; ?>;
    </script>
    
    <!-- MEMANGGIL FILE JAVASCRIPT EKSTERNAL -->
    <script src="assets/js/script.js"></script>
</body>
</html>