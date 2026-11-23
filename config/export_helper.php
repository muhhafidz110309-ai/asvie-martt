<?php
/**
 * config/export_helper.php
 * Helper untuk export XLS & PDF (Dompdf)
 */

/**
 * Validasi periode dari & sampai (pass-by-reference)
 */
function export_validate_periode(&$dari, &$sampai) {
    $dari   = $_GET['dari']   ?? date('Y-m-01');
    $sampai = $_GET['sampai'] ?? date('Y-m-d');

    $d1 = DateTime::createFromFormat('Y-m-d', $dari);
    $d2 = DateTime::createFromFormat('Y-m-d', $sampai);

    if (!$d1 || $d1->format('Y-m-d') !== $dari) {
        $dari = date('Y-m-01');
    }
    if (!$d2 || $d2->format('Y-m-d') !== $sampai) {
        $sampai = date('Y-m-d');
    }

    if (strtotime($dari) > strtotime($sampai)) {
        [$dari, $sampai] = [$sampai, $dari];
    }
}

/**
 * Ambil format dari URL, default xls
 * ✅ Hanya izinkan xls & pdf (csv dihapus)
 */
function export_format() {
    $f = strtolower($_GET['format'] ?? 'xls');
    return in_array($f, ['xls', 'pdf']) ? $f : 'xls';
}

/**
 * Kirim HTTP header untuk download XLS
 * Untuk PDF, header dikirim oleh Dompdf::stream()
 */
function export_send_headers($filename, $format = 'xls') {
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * ============================================================
 *  PDF HELPER (Dompdf)
 * ============================================================
 * Pastikan sudah install:
 *   composer require dompdf/dompdf
 * atau manual di: libs/dompdf/autoload.inc.php
 */

/**
 * Load Dompdf (autoload). Support composer & manual install.
 *
 * @return bool true kalau berhasil, false kalau library tidak ditemukan
 */
function pdf_load_library() {
    static $loaded = null;
    if ($loaded !== null) return $loaded;

    // 1) Coba via Composer
    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
        $loaded = class_exists('\\Dompdf\\Dompdf');
        if ($loaded) return true;
    }

    // 2) Fallback: manual install di libs/dompdf/
    $manualAutoload = __DIR__ . '/../libs/dompdf/autoload.inc.php';
    if (file_exists($manualAutoload)) {
        require_once $manualAutoload;
        $loaded = class_exists('\\Dompdf\\Dompdf');
        return $loaded;
    }

    $loaded = false;
    return false;
}

/**
 * Render HTML ke PDF dan langsung download.
 *
 * @param string $html        HTML lengkap (boleh ada <html><body>)
 * @param string $filename    Nama file tanpa ekstensi (auto tambah .pdf)
 * @param string $orientation 'portrait' | 'landscape'
 * @param string $paper       Ukuran kertas: 'A4', 'Letter', 'Legal', dll
 * @param bool   $download    true = download, false = tampil di browser
 * @return bool true kalau sukses, false kalau library tidak ada
 */
function pdf_output($html, $filename, $orientation = 'landscape', $paper = 'A4', $download = true) {
    if (!pdf_load_library()) {
        // Library belum install — beri pesan jelas
        http_response_code(500);
        echo "<h2>Dompdf belum terinstall</h2>";
        echo "<p>Jalankan perintah berikut di folder project:</p>";
        echo "<pre>composer require dompdf/dompdf</pre>";
        echo "<p>Atau download manual dari <a href='https://github.com/dompdf/dompdf/releases'>github.com/dompdf/dompdf/releases</a> ";
        echo "dan extract ke <code>libs/dompdf/</code>.</p>";
        exit;
    }

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper($paper, $orientation);
    $dompdf->render();

    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $filename);
    $dompdf->stream($filename . '.pdf', [
        'Attachment' => (bool)$download, // true = langsung download
    ]);
    exit;
}

/**
 * Wrapper siap pakai untuk laporan: render HTML laporan ke PDF
 * dan kirim sebagai download.
 *
 * Contoh pemakaian di export_laporan.php:
 *
 *   pdf_laporan($html, "Laporan_Penjualan_{$dari}_sd_{$sampai}");
 */
function pdf_laporan($html, $filename) {
    pdf_output($html, $filename, 'landscape', 'A4', true);
}