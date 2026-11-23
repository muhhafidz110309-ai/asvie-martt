<?php
require_once '../config/database.php';
requireLogin();

/* ============================================================
   HAPUS TRANSAKSI
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete') {
    $id         = (int)($_POST['id'] ?? 0);
    $kembalikan = !empty($_POST['kembalikan_stok']);

    if ($id <= 0) {
        flash('error', 'ID transaksi tidak valid.');
        redirect('laporan.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$id]);
        $trx = $stmt->fetch();

        if (!$trx) throw new Exception('Transaksi tidak ditemukan.');

        if ($kembalikan) {
            $stmt = $pdo->prepare("SELECT produk_id, qty FROM transaksi_detail WHERE transaksi_id = ?");
            $stmt->execute([$id]);
            $detail = $stmt->fetchAll();

            $updStok = $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
            foreach ($detail as $d) {
                if (!empty($d['produk_id'])) {
                    $updStok->execute([(int)$d['qty'], (int)$d['produk_id']]);
                }
            }
        }

        $pdo->prepare("DELETE FROM transaksi_detail WHERE transaksi_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM transaksi WHERE id = ?")->execute([$id]);

        $pdo->commit();
        flash('success', 'Transaksi ' . $trx['kode'] . ' berhasil dihapus' . ($kembalikan ? ' dan stok dikembalikan.' : '.'));

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
    }

    redirect('laporan.php?' . http_build_query([
        'dari'   => $_POST['dari']   ?? date('Y-m-01'),
        'sampai' => $_POST['sampai'] ?? date('Y-m-d'),
    ]));
}

/* ============================================================
   HAPUS SEMUA TRANSAKSI (per periode)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete_all') {
    $dari       = $_POST['dari']   ?? date('Y-m-01');
    $sampai     = $_POST['sampai'] ?? date('Y-m-d');
    $kembalikan = !empty($_POST['kembalikan_stok']);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id FROM transaksi
            WHERE jenis='penjualan' AND DATE(tanggal) BETWEEN ? AND ?
            FOR UPDATE
        ");
        $stmt->execute([$dari, $sampai]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            throw new Exception('Tidak ada transaksi pada periode tersebut.');
        }

        if ($kembalikan) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("
                SELECT produk_id, qty FROM transaksi_detail
                WHERE transaksi_id IN ($placeholders)
            ");
            $stmt->execute($ids);
            $detail = $stmt->fetchAll();

            $updStok = $pdo->prepare("UPDATE produk SET stok = stok + ? WHERE id = ?");
            foreach ($detail as $d) {
                if (!empty($d['produk_id'])) {
                    $updStok->execute([(int)$d['qty'], (int)$d['produk_id']]);
                }
            }
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM transaksi_detail WHERE transaksi_id IN ($placeholders)")
            ->execute($ids);
        $pdo->prepare("DELETE FROM transaksi WHERE id IN ($placeholders)")
            ->execute($ids);

        $pdo->commit();
        flash('success', count($ids) . ' transaksi berhasil dihapus' . ($kembalikan ? ' dan stok dikembalikan.' : '.'));

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Gagal menghapus: ' . $e->getMessage());
    }

    redirect('laporan.php?' . http_build_query(['dari' => $dari, 'sampai' => $sampai]));
}

/* ============================================================
   TAMBAH PENGELUARAN
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'add_pengeluaran') {
    $tanggal    = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $kategori   = trim($_POST['kategori'] ?? 'Lain-lain');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $nominal    = (float)str_replace(['.', ','], ['', '.'], $_POST['jumlah'] ?? '0');
    $dari       = $_POST['dari']   ?? date('Y-m-01');
    $sampai     = $_POST['sampai'] ?? date('Y-m-d');

    try {
        if ($keterangan === '') throw new Exception('Keterangan wajib diisi.');
        if ($nominal <= 0)      throw new Exception('Jumlah pengeluaran harus lebih dari 0.');

        $tglFull = (strlen($tanggal) === 10) ? $tanggal . ' ' . date('H:i:s') : $tanggal;

        $stmt = $pdo->prepare("
            INSERT INTO pengeluaran (tanggal, kategori, keterangan, nominal, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tglFull,
            $kategori,
            $keterangan,
            $nominal,
            $_SESSION['user']['id'] ?? null,
        ]);

        flash('success', 'Pengeluaran berhasil ditambahkan.');
    } catch (Exception $e) {
        flash('error', 'Gagal menambah pengeluaran: ' . $e->getMessage());
    }

    redirect('laporan.php?' . http_build_query(['dari' => $dari, 'sampai' => $sampai]));
}

/* ============================================================
   HAPUS PENGELUARAN
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'delete_pengeluaran') {
    $id     = (int)($_POST['id'] ?? 0);
    $dari   = $_POST['dari']   ?? date('Y-m-01');
    $sampai = $_POST['sampai'] ?? date('Y-m-d');

    try {
        if ($id <= 0) throw new Exception('ID pengeluaran tidak valid.');
        $stmt = $pdo->prepare("DELETE FROM pengeluaran WHERE id = ?");
        $stmt->execute([$id]);
        flash('success', 'Pengeluaran berhasil dihapus.');
    } catch (Exception $e) {
        flash('error', 'Gagal menghapus pengeluaran: ' . $e->getMessage());
    }

    redirect('laporan.php?' . http_build_query(['dari' => $dari, 'sampai' => $sampai]));
}

/* ============================================================
   FILTER PERIODE
   ============================================================ */
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

/* ============================================================
   REKAP PENJUALAN (hanya jenis='penjualan')
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jml, COALESCE(SUM(total),0) AS omzet,
           COALESCE(AVG(total),0) AS rata
    FROM transaksi
    WHERE jenis='penjualan' AND DATE(tanggal) BETWEEN ? AND ?
");
$stmt->execute([$dari, $sampai]);
$rekapJual = $stmt->fetch();

/* ============================================================
   REKAP PELUNASAN HUTANG (jenis='pelunasan')
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jml, COALESCE(SUM(bayar),0) AS total
    FROM transaksi
    WHERE jenis='pelunasan' AND DATE(tanggal) BETWEEN ? AND ?
");
$stmt->execute([$dari, $sampai]);
$rekapPelunasan = $stmt->fetch();

/* ============================================================
   REKAP PER METODE PEMBAYARAN (Tunai vs QRIS vs Hutang)
   Untuk penjualan saja. Pelunasan ditambahkan manual ke kas tunai.
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(metode_pembayaran,'tunai') AS metode,
        COUNT(*) AS jml,
        COALESCE(SUM(total),0) AS omzet,
        COALESCE(SUM(CASE WHEN COALESCE(metode_pembayaran,'tunai')='tunai' THEN (bayar-kembalian) ELSE total END),0) AS terbayar
    FROM transaksi
    WHERE jenis='penjualan' AND DATE(tanggal) BETWEEN ? AND ?
    GROUP BY COALESCE(metode_pembayaran,'tunai')
");
$stmt->execute([$dari, $sampai]);
$rekapMetode = [];
foreach ($stmt->fetchAll() as $row) {
    $rekapMetode[strtolower($row['metode'])] = $row;
}

$kasTunai  = $rekapMetode['tunai']  ?? ['jml'=>0,'omzet'=>0,'terbayar'=>0];
$kasQris   = $rekapMetode['qris']   ?? ['jml'=>0,'omzet'=>0,'terbayar'=>0];
$kasHutang = $rekapMetode['hutang'] ?? ['jml'=>0,'omzet'=>0,'terbayar'=>0];

// Kas tunai = penjualan tunai + pelunasan hutang (uang masuk fisik)
$kasTunai['terbayar'] = (float)$kasTunai['terbayar'] + (float)$rekapPelunasan['total'];
$kasTunai['jml']      = (int)$kasTunai['jml'] + (int)$rekapPelunasan['jml'];
// Omzet penjualan tunai tidak ditambah pelunasan (agar tidak double), hanya info terpisah

/* ============================================================
   LABA KOTOR (hanya dari transaksi penjualan)
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM((d.harga - p.harga_beli) * d.qty),0) AS laba
    FROM transaksi_detail d
    JOIN produk p ON p.id = d.produk_id
    JOIN transaksi t ON t.id = d.transaksi_id
    WHERE t.jenis='penjualan' AND DATE(t.tanggal) BETWEEN ? AND ?
");
$stmt->execute([$dari, $sampai]);
$labaKotor = (float)$stmt->fetchColumn();

/* ============================================================
   DETAIL PRODUK TERJUAL (hanya penjualan)
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT p.id, p.kode, p.nama, p.kategori,
           SUM(d.qty) AS qty, SUM(d.subtotal) AS omzet,
           SUM((d.harga - p.harga_beli) * d.qty) AS laba
    FROM transaksi_detail d
    JOIN produk p ON p.id = d.produk_id
    JOIN transaksi t ON t.id = d.transaksi_id
    WHERE t.jenis='penjualan' AND DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY p.id, p.kode, p.nama, p.kategori
    ORDER BY omzet DESC
");
$stmt->execute([$dari, $sampai]);
$detailProduk = $stmt->fetchAll();

/* ============================================================
   REKAP HARIAN (penjualan + pelunasan dipisah)
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT DATE(tanggal) AS tgl,
           SUM(CASE WHEN jenis='penjualan' THEN 1 ELSE 0 END) AS jml_jual,
           SUM(CASE WHEN jenis='penjualan' THEN total ELSE 0 END) AS omzet,
           SUM(CASE WHEN jenis='pelunasan' THEN 1 ELSE 0 END) AS jml_pelunasan,
           SUM(CASE WHEN jenis='pelunasan' THEN bayar ELSE 0 END) AS total_pelunasan
    FROM transaksi
    WHERE jenis IN ('penjualan','pelunasan') AND DATE(tanggal) BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY tgl DESC
");
$stmt->execute([$dari, $sampai]);
$harian = $stmt->fetchAll();

/* ============================================================
   DAFTAR TRANSAKSI + DETAIL (penjualan + pelunasan)
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT t.*, u.nama AS kasir, t.metode_pembayaran,
           (SELECT COUNT(*) FROM transaksi_detail WHERE transaksi_id = t.id) AS jml_item
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.jenis IN ('penjualan','pelunasan') AND DATE(t.tanggal) BETWEEN ? AND ?
    ORDER BY t.id DESC
    LIMIT 100
");
$stmt->execute([$dari, $sampai]);
$daftarTransaksi = $stmt->fetchAll();

$detailByTransaksi = [];
if ($daftarTransaksi) {
    $trxIds = array_column($daftarTransaksi, 'id');
    $placeholders = implode(',', array_fill(0, count($trxIds), '?'));
    $stmt = $pdo->prepare("
        SELECT d.*, p.nama AS nama_produk, p.kode AS kode_produk, p.satuan
        FROM transaksi_detail d
        JOIN produk p ON p.id = d.produk_id
        WHERE d.transaksi_id IN ($placeholders)
        ORDER BY d.id ASC
    ");
    $stmt->execute($trxIds);
    foreach ($stmt->fetchAll() as $row) {
        $detailByTransaksi[$row['transaksi_id']][] = $row;
    }
}

/* ============================================================
   LAPORAN HUTANG (per konsumen)
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT nama,
           COUNT(*)           AS jml_item,
           SUM(qty)           AS total_qty,
           SUM(harga * qty)   AS total_belanja,
           SUM(dibayar)       AS total_dibayar,
           SUM(sisa)          AS total_sisa,
           MIN(tanggal)       AS tgl_awal,
           MAX(tanggal)       AS tgl_akhir
    FROM hutang
    WHERE DATE(tanggal) BETWEEN ? AND ?
    GROUP BY nama
    ORDER BY
      CASE WHEN SUM(sisa) > 0 THEN 0 ELSE 1 END,
      MAX(tanggal) DESC,
      nama ASC
");
$stmt->execute([$dari, $sampai]);
$hutangGroup = $stmt->fetchAll();

$totalHutang = array_sum(array_map(fn($h) => (float)$h['total_sisa'], $hutangGroup));

$detailByKonsumen = [];
if ($hutangGroup) {
    $namaList = array_column($hutangGroup, 'nama');
    $placeholders = implode(',', array_fill(0, count($namaList), '?'));

    $stmt = $pdo->prepare("
        SELECT h.*, p.nama AS nama_produk, p.kode AS kode_produk, p.satuan
        FROM hutang h
        JOIN produk p ON p.id = h.produk_id
        WHERE h.nama IN ($placeholders)
          AND DATE(h.tanggal) BETWEEN ? AND ?
        ORDER BY h.nama ASC, h.sisa > 0 DESC, h.id DESC
    ");
    $stmt->execute(array_merge($namaList, [$dari, $sampai]));

    foreach ($stmt->fetchAll() as $row) {
        $detailByKonsumen[$row['nama']][] = $row;
    }
}

/* ============================================================
   LAPORAN PENGELUARAN
   ============================================================ */
$totalPengeluaran = 0.0;
$pengeluaranByKategori = [];
$daftarPengeluaran = [];
$rekapKeluar = ['total' => 0, 'jml' => 0];

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(nominal),0) AS total, COUNT(*) AS jml
        FROM pengeluaran
        WHERE DATE(tanggal) BETWEEN ? AND ?
    ");
    $stmt->execute([$dari, $sampai]);
    $rekapKeluar = $stmt->fetch();
    $totalPengeluaran = (float)($rekapKeluar['total'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT kategori, COUNT(*) AS jml, COALESCE(SUM(nominal),0) AS total
        FROM pengeluaran
        WHERE DATE(tanggal) BETWEEN ? AND ?
        GROUP BY kategori
        ORDER BY total DESC
    ");
    $stmt->execute([$dari, $sampai]);
    $pengeluaranByKategori = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT p.*, u.nama AS petugas
        FROM pengeluaran p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE DATE(p.tanggal) BETWEEN ? AND ?
        ORDER BY p.tanggal DESC, p.id DESC
        LIMIT 100
    ");
    $stmt->execute([$dari, $sampai]);
    $daftarPengeluaran = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Laporan pengeluaran error: ' . $e->getMessage());
    flash('error', 'Gagal memuat data pengeluaran: ' . $e->getMessage());
}

$labaBersih = $labaKotor - $totalPengeluaran;
$kategoriPengeluaran = ['Operasional', 'Gaji', 'Listrik & Air', 'Sewa', 'Transport', 'Pembelian Stok', 'Lain-lain'];

$pageTitle    = 'Laporan';
$pageSubtitle = 'Laporan penjualan & pengeluaran';
require_once '../includes/header.php';
?>

<style>
  /* ============================================================
     LAPORAN STYLES
     ============================================================ */
  :root {
    --dash-radius: 14px;
    --dash-shadow: 0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
    --dash-shadow-hover: 0 8px 24px rgba(15,23,42,.10), 0 2px 6px rgba(15,23,42,.06);
    --dash-transition: all .25s cubic-bezier(.4,0,.2,1);
  }

  .filter-card .card-body { padding: 18px 20px; }
  .filter-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
  .filter-form .form-group { margin: 0; }
  .filter-form .form-group label { font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block; }
  .filter-form input { border-radius: 8px; border: 1.5px solid #e2e8f0; padding: 9px 12px; font-size: 13px; transition: var(--dash-transition); }
  .filter-form input:focus { border-color: var(--g500, #10b981); box-shadow: 0 0 0 3px rgba(16,185,129,.12); outline: none; }

  .btn-export-xls, .btn-export-pdf { display: inline-flex; align-items: center; gap: 6px; color: #fff !important; border: none; transition: var(--dash-transition); }
  .btn-export-xls { background: linear-gradient(135deg, #10b981, #047857); box-shadow: 0 4px 12px -4px rgba(16, 185, 129, 0.6); }
  .btn-export-xls:hover { filter: brightness(1.08); transform: translateY(-2px); box-shadow: 0 8px 20px -6px rgba(16, 185, 129, 0.75); }
  .btn-export-pdf { background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 4px 12px -4px rgba(239, 68, 68, 0.6); }
  .btn-export-pdf:hover { filter: brightness(1.08); transform: translateY(-2px); box-shadow: 0 8px 20px -6px rgba(239, 68, 68, 0.75); }

  .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
  @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 640px) { .stats-grid { grid-template-columns: 1fr; } }

  .stat { position: relative; background: #fff; border: 1px solid var(--border, #e5e7eb); border-radius: var(--dash-radius); padding: 18px 20px; overflow: hidden; box-shadow: var(--dash-shadow); transition: var(--dash-transition); display: flex; flex-direction: column; justify-content: center; }
  .stat:hover { transform: translateY(-3px); box-shadow: var(--dash-shadow-hover); border-color: var(--g300, #86efac); }
  .stat::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: linear-gradient(90deg, var(--g500, #10b981), var(--g600, #059669)); opacity: .85; }
  .stat:nth-child(2)::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }
  .stat:nth-child(3)::before { background: linear-gradient(90deg, #ef4444, #dc2626); }
  .stat:nth-child(4)::before { background: linear-gradient(90deg, #8b5cf6, #7c3aed); }
  .stat:nth-child(5)::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
  .stat:nth-child(6)::before { background: linear-gradient(90deg, #06b6d4, #0891b2); }
  .stat:nth-child(7)::before { background: linear-gradient(90deg, #ec4899, #db2777); }

  .stat-label { font-size: 11.5px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
  .stat-value { font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; line-height: 1.15; }
  .stat-value.neg { color: #dc2626; }
  .stat-value.pos { color: #047857; }
  .stat-sub { font-size: 11.5px; color: #94a3b8; margin-top: 5px; }

  .card { border-radius: var(--dash-radius); box-shadow: var(--dash-shadow); transition: var(--dash-transition); margin-bottom: 20px; background: #fff; }
  .card:hover { box-shadow: var(--dash-shadow-hover); }
  .card-head { padding: 16px 20px; border-bottom: 1px solid var(--border, #e5e7eb); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .card-head h2 { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
  .card-head .head-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

  .table-wrap { overflow-x: auto; }
  .table-wrap table { width: 100%; border-collapse: collapse; }
  .table-wrap thead th { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; padding: 12px 16px; border-bottom: 1px solid var(--border, #e5e7eb); text-align: left; }
  .table-wrap tbody td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; color: #334155; }
  .table-wrap tbody tr:hover { background-color: #f8fafc; }

  .text-right { text-align: right !important; }
  .text-center { text-align: center !important; }
  .strong { font-weight: 600; color: #0f172a; }

  .row-konsumen, .row-transaksi { cursor: pointer; transition: background-color .2s; }
  .row-konsumen:hover, .row-transaksi:hover { background: #f0fdf4 !important; }
  .row-konsumen.active, .row-transaksi.active { background: #dcfce7 !important; box-shadow: inset 4px 0 0 var(--g600, #059669); }
  .row-lunas { background: #f0fdf4 !important; }

  .toggle-icon { display: inline-block; transition: transform .3s; color: var(--g600, #059669); font-size: 12px; font-weight: 700; }
  .toggle-icon.open { transform: rotate(90deg); }

  .row-detail > td { padding: 0 !important; background: #fafafa; border-bottom: 1px solid var(--border, #e5e7eb); }
  .detail-inner { max-height: 0; overflow: hidden; opacity: 0; transform: translateY(-8px); transition: max-height .4s ease, opacity .3s ease, transform .3s ease; }
  .detail-inner.open { max-height: 1500px; opacity: 1; transform: translateY(0); }
  .detail-content { padding: 14px 20px 16px 50px; }

  .empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; font-size: 13.5px; }
  .empty-state .emoji { font-size: 32px; display: block; margin-bottom: 8px; }

  .tag { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: .02em; }
  .tag-green { background: #dcfce7; color: #166534; }
  .tag-red { background: #fee2e2; color: #991b1b; }
  .tag-blue { background: #dbeafe; color: #1e40af; }
  .tag-amber { background: #fef3c7; color: #92400e; }
  .tag-purple { background: #ede9fe; color: #6d28d9; }
  .tag-gray { background: #f1f5f9; color: #475569; }

  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
  .modal-overlay.show { display: flex; }
  .modal-box { background: #fff; border-radius: 14px; width: 100%; max-width: 440px; margin: 20px; overflow: hidden; }
  .modal-head { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
  .modal-head h3 { font-size: 16px; font-weight: 700; margin: 0; }
  .modal-close { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; line-height: 1; }
  .modal-body { padding: 20px; }
  .modal-foot { padding: 14px 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 8px; justify-content: flex-end; }
  .modal-info { margin-top: 14px; padding: 14px 16px; background: #f8fafc; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; font-size: 13px; }
  .modal-info > div { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
  .modal-info > div + div { margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
  .modal-warn { margin-top: 14px; padding: 10px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 12px; color: #92400e; line-height: 1.5; }
  .modal-warn.danger { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
  .modal-kode { margin-top: 8px; padding: 10px 14px; background: linear-gradient(135deg, var(--g50, #ecfdf5), #f0fdf4); border: 1.5px solid #a7f3d0; border-radius: 8px; font-weight: 700; color: var(--g700, #047857); text-align: center; font-size: 15px; }
  .modal-check { display: flex; align-items: center; gap: 10px; margin-top: 14px; padding: 12px 14px; background: #f0fdf4; border: 1.5px solid #a7f3d0; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: #065f46; }
  .modal-check input { width: 18px; height: 18px; accent-color: var(--g500, #10b981); cursor: pointer; }

  .kategori-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
  .chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

  .detail-table-wrap { background: #fff; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .detail-table-wrap thead tr { background: #f1f5f9; }
  .detail-table-wrap thead th { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #475569; }
  .detail-table-wrap tbody tr { border-bottom: 1px solid #f1f5f9; }
  .detail-table-wrap tbody tr:hover { background: #f8fafc; }
  .detail-table-wrap tfoot tr { background: #ecfdf5; border-top: 2px solid var(--border, #e5e7eb); }
  .detail-table-wrap tfoot td { padding: 12px 14px; font-weight: 700; }
  .lunas-row { background: #f0fdf4 !important; }

  .trx-note { margin-top: 10px; padding: 10px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; font-size: 12px; color: #92400e; line-height: 1.5; }

  .badge-jenis { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 10.5px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
  .badge-jenis.penjualan { background: #dbeafe; color: #1e40af; }
  .badge-jenis.pelunasan { background: #fef3c7; color: #92400e; }
</style>

<!-- ===== FILTER ===== -->
<div class="card filter-card no-print">
  <div class="card-head">
    <h2>🔎 Filter Periode Laporan</h2>
  </div>
  <div class="card-body">
    <form method="get" class="filter-form">
      <div class="form-group">
        <label>Dari Tanggal</label>
        <input type="date" name="dari" value="<?= e($dari) ?>">
      </div>
      <div class="form-group">
        <label>Sampai Tanggal</label>
        <input type="date" name="sampai" value="<?= e($sampai) ?>">
      </div>
      <button class="btn btn-primary">🔍 Tampilkan</button>
      <a href="export_laporan.php?dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>&format=xls"
         class="btn btn-export-xls" title="Download untuk Microsoft Excel">📥 Excel</a>
      <a href="export_laporan.php?dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>&format=pdf"
         class="btn btn-export-pdf" title="Download PDF" target="_blank">📄 PDF</a>
    </form>
  </div>
</div>

<!-- ===== STATS UTAMA ===== -->
<div class="stats-grid">
  <div class="stat">
    <div class="stat-label">Total Omzet</div>
    <div class="stat-value"><?= rupiah($rekapJual['omzet']) ?></div>
    <div class="stat-sub"><?= (int)$rekapJual['jml'] ?> transaksi penjualan</div>
  </div>
  <div class="stat">
    <div class="stat-label">Laba Kotor</div>
    <div class="stat-value"><?= rupiah($labaKotor) ?></div>
    <div class="stat-sub">Selisih harga jual &amp; beli</div>
  </div>
  <div class="stat">
    <div class="stat-label">Total Pengeluaran</div>
    <div class="stat-value neg"><?= rupiah($totalPengeluaran) ?></div>
    <div class="stat-sub"><?= (int)($rekapKeluar['jml'] ?? 0) ?> catatan</div>
  </div>
  <div class="stat">
    <div class="stat-label">Laba Bersih</div>
    <div class="stat-value <?= $labaBersih < 0 ? 'neg' : 'pos' ?>"><?= rupiah($labaBersih) ?></div>
    <div class="stat-sub">Laba kotor − pengeluaran</div>
  </div>
  <div class="stat">
    <div class="stat-label">💰 Pelunasan Hutang</div>
    <div class="stat-value" style="color:#d97706"><?= rupiah($rekapPelunasan['total']) ?></div>
    <div class="stat-sub"><?= (int)$rekapPelunasan['jml'] ?> transaksi pelunasan</div>
  </div>
  <div class="stat">
    <div class="stat-label">Rata-rata / Transaksi</div>
    <div class="stat-value"><?= rupiah($rekapJual['rata']) ?></div>
    <div class="stat-sub">Nilai belanja rata-rata</div>
  </div>
</div>

<!-- ===== PEMISAHAN KAS TUNAI vs QRIS ===== -->
<div class="stats-grid" style="grid-template-columns:repeat(2,1fr)">
  <!-- KAS TUNAI (penjualan tunai + pelunasan hutang) -->
  <div class="stat" style="border-left:4px solid #10b981">
    <div class="stat-label">💵 Kas Tunai</div>
    <div class="stat-value pos"><?= rupiah($kasTunai['terbayar']) ?></div>
    <div class="stat-sub">
      <?= (int)$kasTunai['jml'] ?> transaksi ·
      Penjualan: <?= rupiah($kasTunai['omzet']) ?> ·
      Pelunasan: <?= rupiah($rekapPelunasan['total']) ?>
    </div>
  </div>

  <!-- KAS QRIS -->
  <div class="stat" style="border-left:4px solid #3b82f6">
    <div class="stat-label">📱 Kas QRIS</div>
    <div class="stat-value" style="color:#2563eb"><?= rupiah($kasQris['terbayar']) ?></div>
    <div class="stat-sub">
      <?= (int)$kasQris['jml'] ?> transaksi ·
      Omzet: <?= rupiah($kasQris['omzet']) ?>
    </div>
  </div>
</div>

<!-- Total Kas Fisik vs Digital -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 20px; display:flex; gap:24px; flex-wrap:wrap; justify-content:space-around; text-align:center">
    <div>
      <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase">Total Uang Masuk</div>
      <div style="font-size:20px;font-weight:800;color:#047857">
        <?= rupiah($kasTunai['terbayar'] + $kasQris['terbayar']) ?>
      </div>
    </div>
    <div style="border-left:1px solid #e2e8f0;padding-left:24px">
      <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase">Cash di Tangan</div>
      <div style="font-size:20px;font-weight:800;color:#10b981">
        <?= rupiah($kasTunai['terbayar']) ?>
      </div>
    </div>
    <div style="border-left:1px solid #e2e8f0;padding-left:24px">
      <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase">Saldo QRIS</div>
      <div style="font-size:20px;font-weight:800;color:#3b82f6">
        <?= rupiah($kasQris['terbayar']) ?>
      </div>
    </div>
  </div>
</div>

<!-- ===== REKAP HARIAN ===== -->
<div class="card">
  <div class="card-head">
    <h2>📅 Rekap Penjualan Harian</h2>
    <span class="tag tag-blue"><?= count($harian) ?> hari</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tanggal</th>
          <th class="text-center">Jml Penjualan</th>
          <th class="text-right">Omzet Penjualan</th>
          <th class="text-center">Jml Pelunasan</th>
          <th class="text-right">Total Pelunasan</th>
          <th class="text-right">Total Uang Masuk</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$harian): ?>
        <tr><td colspan="6">
          <div class="empty-state"><span class="emoji">📭</span>Tidak ada data pada periode ini.</div>
        </td></tr>
      <?php else: foreach ($harian as $h):
        $totalMasuk = (float)$h['omzet'] + (float)$h['total_pelunasan'];
      ?>
        <tr>
          <td class="strong"><?= tanggalIndo($h['tgl'] . ' 00:00:00') ?></td>
          <td class="text-center"><span class="tag tag-blue"><?= (int)$h['jml_jual'] ?></span></td>
          <td class="text-right strong"><?= rupiah($h['omzet']) ?></td>
          <td class="text-center"><span class="tag tag-amber"><?= (int)$h['jml_pelunasan'] ?></span></td>
          <td class="text-right" style="color:#d97706; font-weight:600"><?= rupiah($h['total_pelunasan']) ?></td>
          <td class="text-right strong" style="color:#047857"><?= rupiah($totalMasuk) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== DAFTAR TRANSAKSI (PENJUALAN + PELUNASAN) ===== -->
<div class="card">
  <div class="card-head">
    <h2>📋 Daftar Transaksi</h2>
    <span class="tag tag-blue"><?= count($daftarTransaksi) ?> transaksi</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:30px"></th>
          <th>Kode</th>
          <th>Tanggal</th>
          <th>Kasir</th>
          <th class="text-center">Jenis</th>
          <th class="text-center">Metode</th>
          <th class="text-right">Total</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$daftarTransaksi): ?>
        <tr><td colspan="8">
          <div class="empty-state"><span class="emoji">📭</span>Tidak ada transaksi pada periode ini.</div>
        </td></tr>
      <?php else: foreach ($daftarTransaksi as $t):
        $rowId = 'trx-' . (int)$t['id'];
        $isPelunasan = ($t['jenis'] === 'pelunasan');
        $details = $detailByTransaksi[$t['id']] ?? [];
      ?>
        <tr class="row-transaksi" data-target="<?= $rowId ?>">
          <td class="text-center"><span class="toggle-icon" id="icon-<?= $rowId ?>">▶</span></td>
          <td class="strong"><code><?= e($t['kode']) ?></code></td>
          <td><?= tanggalIndo($t['tanggal']) ?></td>
          <td><?= e($t['kasir'] ?? '-') ?></td>
          <td class="text-center">
            <?php if ($isPelunasan): ?>
              <span class="badge-jenis pelunasan">Pelunasan</span>
            <?php else: ?>
              <span class="badge-jenis penjualan">Penjualan</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php
              $m = strtolower($t['metode_pembayaran'] ?? 'tunai');
              $mLabel = $m === 'qris' ? '📱 QRIS' : ($m === 'hutang' ? '📝 Hutang' : '💵 Tunai');
              $mClass = $m === 'qris' ? 'tag-blue' : ($m === 'hutang' ? 'tag-red' : 'tag-green');
            ?>
            <span class="tag <?= $mClass ?>"><?= $mLabel ?></span>
          </td>
          <td class="text-right strong"><?= rupiah($t['total']) ?></td>
          <td class="text-center" style="white-space:nowrap" onclick="event.stopPropagation()">
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="bukaHapusTransaksi(<?= (int)$t['id'] ?>, '<?= e(addslashes($t['kode'])) ?>', '<?= e($dari) ?>', '<?= e($sampai) ?>')"
                    title="Hapus Transaksi">🗑</button>
          </td>
        </tr>

        <tr class="row-detail" id="<?= $rowId ?>">
          <td colspan="8">
            <div class="detail-inner" id="inner-<?= $rowId ?>">
              <div class="detail-content">
                <?php if ($t['keterangan']): ?>
                  <div class="trx-note">📝 <?= e($t['keterangan']) ?></div>
                <?php endif; ?>
                <div class="detail-table-wrap" style="margin-top:10px">
                  <table style="margin:0">
                    <thead>
                      <tr>
                        <th>Kode Produk</th>
                        <th>Nama Produk</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!$details): ?>
                      <tr><td colspan="5"><div class="empty-state" style="padding:16px">Tidak ada detail item.</div></td></tr>
                    <?php else: foreach ($details as $d): ?>
                      <tr>
                        <td><span class="tag tag-gray"><?= e($d['kode_produk']) ?></span></td>
                        <td><?= e($d['nama_produk']) ?></td>
                        <td class="text-center"><?= (int)$d['qty'] ?> <?= e($d['satuan']) ?></td>
                        <td class="text-right"><?= rupiah($d['harga']) ?></td>
                        <td class="text-right strong"><?= rupiah($d['subtotal']) ?></td>
                      </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colspan="4" class="text-right">Total</td>
                        <td class="text-right"><?= rupiah($t['total']) ?></td>
                      </tr>
                      <tr>
                        <td colspan="4" class="text-right">Dibayar</td>
                        <td class="text-right"><?= rupiah($t['bayar']) ?></td>
                      </tr>
                      <?php if ((float)$t['kembalian'] > 0): ?>
                      <tr>
                        <td colspan="4" class="text-right">Kembalian</td>
                        <td class="text-right"><?= rupiah($t['kembalian']) ?></td>
                      </tr>
                      <?php endif; ?>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== LAPORAN HUTANG ===== -->
<div class="card">
  <div class="card-head">
    <h2>💳 Laporan Hutang Konsumen</h2>
    <?php if ($totalHutang > 0): ?>
      <span class="tag tag-red">Sisa Total: <?= rupiah($totalHutang) ?></span>
    <?php else: ?>
      <span class="tag tag-green">Semua Lunas 🎉</span>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:30px"></th>
          <th>Nama Konsumen</th>
          <th class="text-center">Jml Item</th>
          <th class="text-right">Total Belanja</th>
          <th class="text-right">Sudah Dibayar</th>
          <th class="text-right">Sisa Hutang</th>
          <th class="text-center">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$hutangGroup): ?>
        <tr><td colspan="7">
          <div class="empty-state"><span class="emoji">🎉</span>Tidak ada data hutang pada periode ini.</div>
        </td></tr>
      <?php else: foreach ($hutangGroup as $hg):
        $sisa = (float)$hg['total_sisa'];
        $isLunas = ($sisa <= 0);
        $rowId = 'hutang-' . md5($hg['nama']);
        $details = $detailByKonsumen[$hg['nama']] ?? [];
      ?>
        <tr class="row-konsumen <?= $isLunas ? 'row-lunas' : '' ?>" data-target="<?= $rowId ?>">
          <td class="text-center"><span class="toggle-icon" id="icon-<?= $rowId ?>">▶</span></td>
          <td class="strong"><?= e($hg['nama']) ?></td>
          <td class="text-center"><?= (int)$hg['jml_item'] ?> produk</td>
          <td class="text-right"><?= rupiah($hg['total_belanja']) ?></td>
          <td class="text-right text-green"><?= rupiah($hg['total_dibayar']) ?></td>
          <td class="text-right strong <?= $isLunas ? 'text-green' : 'text-red' ?>" style="<?= $isLunas ? 'color:#047857' : 'color:#dc2626' ?>">
            <?= $isLunas ? '✓ LUNAS' : rupiah($sisa) ?>
          </td>
          <td class="text-center">
            <?php if ($isLunas): ?>
              <span class="tag tag-green">LUNAS</span>
            <?php else: ?>
              <span class="tag tag-red">BELUM LUNAS</span>
            <?php endif; ?>
          </td>
        </tr>

        <tr class="row-detail" id="<?= $rowId ?>">
          <td colspan="7">
            <div class="detail-inner" id="inner-<?= $rowId ?>">
              <div class="detail-content">
                <div class="detail-table-wrap">
                  <table style="margin:0">
                    <thead>
                      <tr>
                        <th>Kode</th>
                        <th>Produk</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Dibayar</th>
                        <th class="text-right">Sisa</th>
                        <th class="text-center">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!$details): ?>
                      <tr><td colspan="8"><div class="empty-state" style="padding:16px">Tidak ada detail.</div></td></tr>
                    <?php else: foreach ($details as $d):
                      $subtotal = (float)$d['harga'] * (int)$d['qty'];
                      $sisaItem = (float)$d['sisa'];
                      $isLunasItem = ($sisaItem <= 0);
                    ?>
                      <tr class="<?= $isLunasItem ? 'lunas-row' : '' ?>">
                        <td><span class="tag tag-gray"><?= e($d['kode_produk']) ?></span></td>
                        <td><?= e($d['nama_produk']) ?></td>
                        <td class="text-center"><?= (int)$d['qty'] ?> <?= e($d['satuan']) ?></td>
                        <td class="text-right"><?= rupiah($d['harga']) ?></td>
                        <td class="text-right"><?= rupiah($subtotal) ?></td>
                        <td class="text-right text-green"><?= rupiah($d['dibayar']) ?></td>
                        <td class="text-right strong" style="<?= $isLunasItem ? 'color:#047857' : 'color:#dc2626' ?>">
                          <?= $isLunasItem ? 'Rp 0' : rupiah($sisaItem) ?>
                        </td>
                        <td class="text-center">
                          <?php if ($isLunasItem): ?>
                            <span class="tag tag-green">✓ LUNAS</span>
                          <?php else: ?>
                            <span class="tag tag-red">BELUM</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== DETAIL PRODUK TERJUAL ===== -->
<div class="card">
  <div class="card-head">
    <h2>📦 Detail Produk Terjual</h2>
    <span class="tag tag-blue"><?= count($detailProduk) ?> jenis</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Produk</th>
          <th>Kategori</th>
          <th class="text-center">Qty Terjual</th>
          <th class="text-right">Omzet</th>
          <th class="text-right">Laba</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$detailProduk): ?>
        <tr><td colspan="6">
          <div class="empty-state"><span class="emoji">📦</span>Tidak ada produk terjual pada periode ini.</div>
        </td></tr>
      <?php else: foreach ($detailProduk as $dp): ?>
        <tr>
          <td><span class="tag tag-gray"><?= e($dp['kode']) ?></span></td>
          <td class="strong"><?= e($dp['nama']) ?></td>
          <td><?= e($dp['kategori'] ?? '-') ?></td>
          <td class="text-center"><?= (int)$dp['qty'] ?></td>
          <td class="text-right strong"><?= rupiah($dp['omzet']) ?></td>
          <td class="text-right text-green"><?= rupiah($dp['laba']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== LAPORAN PENGELUARAN ===== -->
<div class="card">
  <div class="card-head">
    <h2>💸 Laporan Pengeluaran</h2>
    <div class="head-actions">
      <span class="tag tag-red">Total: <?= rupiah($totalPengeluaran) ?></span>
      <button type="button" class="btn btn-primary btn-sm" onclick="bukaTambahPengeluaran()">+ Tambah</button>
    </div>
  </div>

  <?php if ($pengeluaranByKategori): ?>
  <div style="padding:14px 20px; border-bottom:1px solid #e5e7eb; background:#f8fafc">
    <div class="kategori-chips">
      <?php foreach ($pengeluaranByKategori as $pk): ?>
        <span class="chip"><?= e($pk['kategori']) ?>: <strong><?= rupiah($pk['total']) ?></strong></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Kategori</th>
          <th>Keterangan</th>
          <th>Petugas</th>
          <th class="text-right">Jumlah</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$daftarPengeluaran): ?>
        <tr><td colspan="6">
          <div class="empty-state"><span class="emoji">💸</span>Tidak ada pengeluaran pada periode ini.</div>
        </td></tr>
      <?php else: foreach ($daftarPengeluaran as $pg): ?>
        <tr>
          <td><?= tanggalIndo($pg['tanggal']) ?></td>
          <td><span class="tag tag-amber"><?= e($pg['kategori']) ?></span></td>
          <td><?= e($pg['keterangan']) ?></td>
          <td><?= e($pg['petugas'] ?? '-') ?></td>
          <td class="text-right strong" style="color:#dc2626"><?= rupiah($pg['nominal']) ?></td>
          <td class="text-center">
            <form method="post" style="display:inline"
                  onsubmit="return confirm('Hapus pengeluaran ini?\n\n<?= e(addslashes($pg['keterangan'])) ?>\n<?= rupiah($pg['nominal']) ?>')">
              <input type="hidden" name="act" value="delete_pengeluaran">
              <input type="hidden" name="id" value="<?= (int)$pg['id'] ?>">
              <input type="hidden" name="dari" value="<?= e($dari) ?>">
              <input type="hidden" name="sampai" value="<?= e($sampai) ?>">
              <button class="btn btn-danger btn-sm">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== MODAL TAMBAH PENGELUARAN ===== -->
<div class="modal-overlay no-print" id="modalTambahPengeluaran">
  <div class="modal-box">
    <div class="modal-head">
      <h3>💸 Tambah Pengeluaran</h3>
      <button type="button" class="modal-close" onclick="tutupTambahPengeluaran()">×</button>
    </div>
    <form method="post">
      <input type="hidden" name="act" value="add_pengeluaran">
      <input type="hidden" name="dari" value="<?= e($dari) ?>">
      <input type="hidden" name="sampai" value="<?= e($sampai) ?>">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px">Tanggal</label>
          <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required
                 style="width:100%;border-radius:8px;border:1.5px solid #e2e8f0;padding:9px 12px;font-size:13px">
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px">Kategori</label>
          <select name="kategori" required
                  style="width:100%;border-radius:8px;border:1.5px solid #e2e8f0;padding:9px 12px;font-size:13px">
            <?php foreach ($kategoriPengeluaran as $kp): ?>
              <option value="<?= e($kp) ?>"><?= e($kp) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px">Keterangan</label>
          <input type="text" name="keterangan" placeholder="Contoh: Beli ATK" required
                 style="width:100%;border-radius:8px;border:1.5px solid #e2e8f0;padding:9px 12px;font-size:13px">
        </div>
        <div class="form-group">
          <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px">Jumlah (Rp)</label>
          <input type="text" name="jumlah" placeholder="0" required
                 style="width:100%;border-radius:8px;border:1.5px solid #e2e8f0;padding:9px 12px;font-size:13px;font-weight:700">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="tutupTambahPengeluaran()">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== MODAL HAPUS TRANSAKSI ===== -->
<div class="modal-overlay no-print" id="modalHapusTransaksi">
  <div class="modal-box">
    <div class="modal-head">
      <h3 style="color:#dc2626">Hapus Transaksi?</h3>
      <button type="button" class="modal-close" onclick="tutupHapusTransaksi()">×</button>
    </div>
    <form method="post" id="formHapusTransaksi">
      <input type="hidden" name="act" value="delete">
      <input type="hidden" name="id" id="hapusTrxId">
      <input type="hidden" name="dari" id="hapusTrxDari">
      <input type="hidden" name="sampai" id="hapusTrxSampai">
      <div class="modal-body">
        <p style="margin:0 0 8px; font-size:13px; color:#334155">Anda akan menghapus transaksi:</p>
        <p class="modal-kode" id="hapusTrxKode">-</p>
        <label class="modal-check">
          <input type="checkbox" name="kembalikan_stok" value="1">
          <span>Kembalikan stok produk</span>
        </label>
        <p class="modal-warn danger">⚠️ Data yang dihapus tidak dapat dikembalikan.</p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="tutupHapusTransaksi()">Batal</button>
        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ============================================================
   TOGGLE DETAIL
   ============================================================ */
const closeDetail = (inner) => {
  inner.classList.remove('open');
  const id = inner.id.replace('inner-', '');
  document.querySelectorAll(`.row-konsumen[data-target="${id}"], .row-transaksi[data-target="${id}"]`).forEach(r => r.classList.remove('active'));
  document.getElementById('icon-' + id)?.classList.remove('open');
};

document.querySelectorAll('.row-konsumen, .row-transaksi').forEach(row => {
  row.addEventListener('click', function () {
    const targetId = this.dataset.target;
    const inner = document.getElementById('inner-' + targetId);
    if (!inner) return;

    const isOpen = inner.classList.contains('open');
    document.querySelectorAll('.detail-inner.open').forEach(el => { if (el !== inner) closeDetail(el); });

    if (isOpen) {
      closeDetail(inner);
    } else {
      inner.classList.add('open');
      this.classList.add('active');
      document.getElementById('icon-' + targetId)?.classList.add('open');
      setTimeout(() => this.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 350);
    }
  });
});

/* ============================================================
   MODAL TAMBAH PENGELUARAN
   ============================================================ */
function bukaTambahPengeluaran() { document.getElementById('modalTambahPengeluaran').classList.add('show'); }
function tutupTambahPengeluaran() { document.getElementById('modalTambahPengeluaran').classList.remove('show'); }
document.getElementById('modalTambahPengeluaran').addEventListener('click', function (e) { if (e.target === this) tutupTambahPengeluaran(); });

/* ============================================================
   MODAL HAPUS TRANSAKSI
   ============================================================ */
function bukaHapusTransaksi(id, kode, dari, sampai) {
  document.getElementById('hapusTrxId').value = id;
  document.getElementById('hapusTrxKode').textContent = kode;
  document.getElementById('hapusTrxDari').value = dari;
  document.getElementById('hapusTrxSampai').value = sampai;
  document.getElementById('modalHapusTransaksi').classList.add('show');
}
function tutupHapusTransaksi() { document.getElementById('modalHapusTransaksi').classList.remove('show'); }
document.getElementById('modalHapusTransaksi').addEventListener('click', function (e) { if (e.target === this) tutupHapusTransaksi(); });

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { tutupTambahPengeluaran(); tutupHapusTransaksi(); }
});

/* ============================================================
   FORMAT ANGKA INPUT PENGELUARAN
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
  const jumlahInput = document.querySelector('input[name="jumlah"]');
  if (jumlahInput) {
    jumlahInput.addEventListener('input', function() {
      const val = this.value.replace(/\D/g, '');
      this.value = val === '' ? '' : parseInt(val, 10).toLocaleString('id-ID');
    });
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>