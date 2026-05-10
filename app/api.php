<?php
/**
 * Sistem Pakar Pemilihan Laptop - Fuzzy Logic
 * Backend API: Membaca CSV dan menjalankan inferensi fuzzy
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// =============================================
// FUNGSI MEMBERSHIP (KEANGGOTAAN FUZZY)
// =============================================

/**
 * Fungsi Trapesium: nilai max di antara b dan c
 */
function trapz($x, $a, $b, $c, $d) {
    if ($x <= $a || $x >= $d) return 0.0;
    if ($x >= $b && $x <= $c)  return 1.0;
    if ($x > $a && $x < $b)    return ($b - $a) > 0 ? ($x - $a) / ($b - $a) : 1.0;
    if ($x > $c && $x < $d)    return ($d - $c) > 0 ? ($d - $x) / ($d - $c) : 1.0;
    return 0.0;
}

/**
 * Fungsi Segitiga (Triangular)
 */
function trimf($x, $a, $b, $c) {
    return trapz($x, $a, $b, $b, $c);
}

/**
 * Fungsi min (AND operator)
 */
function fand(...$vals) {
    return min($vals);
}

/**
 * Fungsi max (OR operator)
 */
function f_or(...$vals) {
    return max($vals);
}

// =============================================
// BACA DAN PARSE DATASET CSV
// =============================================

function loadDataset($filepath) {
    $data = [];
    if (!file_exists($filepath)) {
        return $data;
    }

    $handle = fopen($filepath, 'r');
    if (!$handle) return $data;

    $header = fgetcsv($handle); // Baca baris header
    // Normalisasi nama header: hapus BOM, trim whitespace
    $header = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $header);

    // Header sudah dibersihkan secara fisik ke 'nomor'

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === count($header)) {
            $laptop = array_combine($header, $row);

            // Konversi tipe data numerik
            $laptop['price']        = (float) ($laptop['price'] ?? 0);
            $laptop['cpu_score']    = (float) ($laptop['cpu_score'] ?? 50);
            $laptop['gpu_class']    = (float) ($laptop['gpu_class'] ?? 1);
            $laptop['ram_gb']       = (float) ($laptop['ram_gb'] ?? 4);
            $laptop['storage_gb']   = (float) ($laptop['storage_gb'] ?? 256);
            $laptop['display_size'] = (float) ($laptop['display_size'] ?? 14.0);

            // Konversi penyimpanan: jika nilai <= 4, asumsi dalam TB -> GB
            if ($laptop['storage_gb'] <= 4 && $laptop['storage_gb'] > 0) {
                $laptop['storage_gb'] = $laptop['storage_gb'] * 1024;
            }

            $data[] = $laptop;
        }
    }
    fclose($handle);
    return $data;
}

// =============================================
// MESIN INFERENSI FUZZY
// =============================================

function fuzzyInference($laptop, $budget, $profile) {
    $p = $laptop['price'];
    $c = $laptop['cpu_score'];
    $g = $laptop['gpu_class'];
    $r = $laptop['ram_gb'];
    $s = $laptop['storage_gb'];

    // -- Himpunan Keanggotaan Input --
    $m = [
        'price' => [
            'Rendah' => trapz($p, 0, 0, 7000000, 12000000),
            'Sedang' => trimf($p, 7000000, 15000000, 25000000),
            'Tinggi' => trapz($p, 15000000, 25000000, 1e9, 1e9),
        ],
        'cpu' => [
            'Rendah' => trapz($c, 0, 0, 40, 62),
            'Sedang' => trimf($c, 40, 65, 82),
            'Tinggi' => trapz($c, 65, 80, 100, 100),
        ],
        'gpu' => [
            'Rendah' => trapz($g, 0, 1, 1, 2.5),
            'Sedang' => trapz($g, 1.5, 2, 3, 3.5),
            'Tinggi' => trapz($g, 2.5, 3.5, 5, 5),
        ],
        'ram' => [
            'Rendah' => trapz($r, 0, 0, 4, 8),
            'Sedang' => trimf($r, 4, 8, 16),
            'Tinggi' => trapz($r, 8, 16, 128, 128),
        ],
        'storage' => [
            'Rendah' => trapz($s, 0, 0, 256, 512),
            'Sedang' => trimf($s, 256, 512, 1024),
            'Tinggi' => trapz($s, 512, 1024, 4096, 4096),
        ],
    ];

    // -- Toleransi Budget (Fuzzy) --
    // Tepat di bawah budget = kecocokan penuh; 15% di atas budget = kecocokan 0
    $within_budget = trapz($p, 0, 0, $budget, $budget * 1.15);

    // -- Basis Aturan Fuzzy berdasarkan Profil --
    $rules = [];

    if ($profile === 'Pemrograman / Data Science') {
        // IF cpu TINGGI AND ram TINGGI THEN suitability TINGGI
        $rules[] = ['w' => fand($m['cpu']['Tinggi'], $m['ram']['Tinggi']), 'output' => 95, 'reason' => 'CPU & RAM sangat mumpuni untuk coding dan data science.'];
        // IF cpu SEDANG AND ram TINGGI THEN suitability SEDANG
        $rules[] = ['w' => fand($m['cpu']['Sedang'], $m['ram']['Tinggi']), 'output' => 65, 'reason' => 'RAM besar mendukung multitasking, CPU cukup untuk pemrograman.'];
        // IF cpu TINGGI AND ram SEDANG THEN suitability SEDANG
        $rules[] = ['w' => fand($m['cpu']['Tinggi'], $m['ram']['Sedang']), 'output' => 60, 'reason' => 'CPU cepat memperlancar kompilasi kode, RAM cukup untuk kebutuhan dasar.'];
        // IF cpu SEDANG AND ram SEDANG THEN suitability SEDANG-RENDAH
        $rules[] = ['w' => fand($m['cpu']['Sedang'], $m['ram']['Sedang']), 'output' => 50, 'reason' => 'Spesifikasi cukup untuk pemrograman ringan dan perkuliahan.'];
        // IF storage TINGGI THEN bonus
        $rules[] = ['w' => fand($m['cpu']['Tinggi'], $m['storage']['Tinggi']), 'output' => 70, 'reason' => 'Storage besar cocok untuk menyimpan dataset dan project besar.'];
        // IF cpu RENDAH OR ram RENDAH THEN suitability RENDAH
        $rules[] = ['w' => f_or($m['cpu']['Rendah'], $m['ram']['Rendah']), 'output' => 20, 'reason' => 'Spesifikasi tidak memadai untuk tugas pemrograman berat.'];

    } elseif ($profile === 'Desain Grafis / Multimedia') {
        // IF gpu TINGGI AND cpu TINGGI AND ram TINGGI THEN TINGGI
        $rules[] = ['w' => fand($m['gpu']['Tinggi'], $m['cpu']['Tinggi'], $m['ram']['Tinggi']), 'output' => 95, 'reason' => 'Performa grafis & prosesor maksimal, ideal untuk rendering 3D dan video editing berat.'];
        // IF gpu TINGGI AND cpu SEDANG THEN SEDANG-TINGGI
        $rules[] = ['w' => fand($m['gpu']['Tinggi'], $m['cpu']['Sedang']), 'output' => 75, 'reason' => 'GPU dedicated powerful sangat menunjang desain UI/UX dan editing foto/video.'];
        // IF gpu SEDANG AND cpu TINGGI THEN SEDANG
        $rules[] = ['w' => fand($m['gpu']['Sedang'], $m['cpu']['Tinggi']), 'output' => 60, 'reason' => 'CPU kencang dengan GPU mid-range, cocok untuk desain 2D dan editing ringan.'];
        // IF gpu SEDANG AND cpu SEDANG THEN SEDANG-RENDAH
        $rules[] = ['w' => fand($m['gpu']['Sedang'], $m['cpu']['Sedang']), 'output' => 45, 'reason' => 'Cukup untuk desain grafis dan ilustrasi 2D standar.'];
        // IF gpu RENDAH THEN RENDAH
        $rules[] = ['w' => $m['gpu']['Rendah'], 'output' => 15, 'reason' => 'GPU integrated tidak direkomendasikan untuk desain grafis dan rendering 3D.'];

    } elseif ($profile === 'Administrasi / Tugas Umum') {
        // IF price RENDAH AND cpu SEDANG AND ram SEDANG THEN TINGGI
        $rules[] = ['w' => fand($m['price']['Rendah'], $m['cpu']['Sedang'], $m['ram']['Sedang']), 'output' => 95, 'reason' => 'Ideal untuk tugas kuliah: harga hemat, performa lebih dari cukup untuk Office & browsing.'];
        // IF price RENDAH AND cpu RENDAH AND ram SEDANG THEN TINGGI
        $rules[] = ['w' => fand($m['price']['Rendah'], $m['ram']['Sedang']), 'output' => 80, 'reason' => 'Sangat hemat, RAM cukup untuk multitasking dan pengetikan dokumen.'];
        // IF cpu SEDANG AND ram SEDANG THEN SEDANG
        $rules[] = ['w' => fand($m['cpu']['Sedang'], $m['ram']['Sedang']), 'output' => 65, 'reason' => 'Performa stabil dan andal untuk tugas akademik sehari-hari.'];
        // IF cpu RENDAH AND ram SEDANG THEN SEDANG
        $rules[] = ['w' => fand($m['cpu']['Rendah'], $m['ram']['Sedang']), 'output' => 55, 'reason' => 'Cocok untuk kebutuhan ringan: browsing, dokumen, dan presentasi.'];
        // IF ram RENDAH THEN RENDAH
        $rules[] = ['w' => $m['ram']['Rendah'], 'output' => 20, 'reason' => 'RAM terlalu kecil, berisiko lag saat membuka banyak tab browser atau dokumen.'];

    } elseif ($profile === 'Gaming') {
        // IF gpu TINGGI AND cpu TINGGI THEN TINGGI
        $rules[] = ['w' => fand($m['gpu']['Tinggi'], $m['cpu']['Tinggi']), 'output' => 97, 'reason' => 'Kombinasi GPU & CPU gaming-grade, mampu menjalankan game AAA di setting tinggi.'];
        // IF gpu TINGGI AND cpu SEDANG THEN SEDANG-TINGGI
        $rules[] = ['w' => fand($m['gpu']['Tinggi'], $m['cpu']['Sedang']), 'output' => 75, 'reason' => 'GPU kelas atas, ideal untuk gaming kompetitif dan esports.'];
        // IF gpu SEDANG AND cpu TINGGI THEN SEDANG
        $rules[] = ['w' => fand($m['gpu']['Sedang'], $m['cpu']['Tinggi']), 'output' => 60, 'reason' => 'CPU bertenaga dengan GPU menengah, cocok untuk game indie dan setting medium.'];
        // IF gpu SEDANG AND cpu SEDANG AND ram TINGGI THEN SEDANG
        $rules[] = ['w' => fand($m['gpu']['Sedang'], $m['cpu']['Sedang'], $m['ram']['Tinggi']), 'output' => 55, 'reason' => 'RAM besar membantu performa, cocok untuk game ringan dan multitasking.'];
        // IF gpu RENDAH THEN RENDAH
        $rules[] = ['w' => $m['gpu']['Rendah'], 'output' => 10, 'reason' => 'GPU integrated tidak direkomendasikan untuk gaming modern.'];
    }

    // Aturan umum terakhir (fallback)
    $rules[] = ['w' => 0.05, 'output' => 30, 'reason' => 'Spesifikasi laptop sesuai dengan kebutuhan dasar pengguna.'];

    // -- Defuzzifikasi: Sugeno Weighted Average --
    $numerator   = 0;
    $denominator = 0;
    $bestReason  = 'Laptop ini memenuhi kriteria minimum pencarian.';
    $maxWeight   = -1;

    foreach ($rules as $rule) {
        $numerator   += $rule['w'] * $rule['output'];
        $denominator += $rule['w'];
        if ($rule['w'] > $maxWeight && $rule['w'] > 0.08) {
            $maxWeight  = $rule['w'];
            $bestReason = $rule['reason'];
        }
    }

    $suitability = $denominator > 0 ? ($numerator / $denominator) : 0;

    // -- Tambahan: Preferensi Brand & OS (Kriteria Tambahan) --
    $bonus = 1.0;
    
    // Brand Match
    if (!empty($laptop['brand_pref']) && strcasecmp($laptop['brand'], $laptop['brand_pref']) === 0) {
        $bonus += 0.15; // Bonus 15% jika brand cocok
    }

    // OS Match
    if (!empty($laptop['os_pref'])) {
        $actualOS = strtolower($laptop['OS'] ?? '');
        $prefOS = strtolower($laptop['os_pref']);
        
        // Pengecekan substring sederhana untuk OS
        if (str_contains($actualOS, $prefOS)) {
            $bonus += 0.10; // Bonus 10% jika OS cocok
        }
    }

    $finalScore = ($suitability * $within_budget) * $bonus;
    $finalScore = min(max($finalScore, 0), 100);

    if ($finalScore < 15 && $within_budget < 0.5) {
        $bestReason = 'Harga laptop melebihi toleransi budget yang ditentukan.';
    }

    return ['score' => round($finalScore, 2), 'reason' => $bestReason];
}

// =============================================
// HANDLER REQUEST API
// =============================================

$input  = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => 'Request tidak valid. Gunakan POST JSON.']);
    exit;
}

$budget     = (float) ($input['budget']     ?? 10000000);
$profile    = (string)($input['profile']    ?? 'Administrasi / Tugas Umum');
$minDisplay = (float) ($input['min_display'] ?? 0);
$brandPref  = (string)($input['brand']       ?? '');
$osPref     = (string)($input['os']          ?? '');

// Path ke CSV (dua level di atas folder app/)
$csvPath = __DIR__ . '/../laptop_data_cleaned (2).csv';
$laptops = loadDataset($csvPath);

if (empty($laptops)) {
    echo json_encode(['error' => "Dataset tidak ditemukan atau kosong di path: $csvPath"]);
    exit;
}

$results = [];

foreach ($laptops as $laptop) {
    // === HARD FILTER: Layar Minimal ===
    if ($minDisplay > 0 && $laptop['display_size'] < $minDisplay) continue;

    // === HARD FILTER: Pra-filter harga ===
    if ($laptop['price'] > $budget * 1.2) continue;

    // === HARD FILTER: Brand (jika dipilih) ===
    if (!empty($brandPref)) {
        $laptopBrand = strtolower(trim($laptop['brand'] ?? ''));
        $prefBrand   = strtolower(trim($brandPref));
        if ($laptopBrand !== $prefBrand) continue;
    }

    // === HARD FILTER: OS (jika dipilih) ===
    // Normalisasi: petakan value pilihan user ke kata kunci yang ada di kolom OS dataset
    if (!empty($osPref)) {
        $osRaw = strtolower(trim($laptop['OS'] ?? ''));

        // Peta normalisasi: value form → keyword yang dicari di kolom OS dataset
        $osKeywordMap = [
            'windows 11' => ['windows 11'],
            'windows 10' => ['windows 10'],
            'mac'        => ['mac os', 'mac catalina', 'mac high sierra', 'mac 10.', 'macos'],
            'chrome'     => ['chrome os'],
            'ubuntu'     => ['ubuntu'],
            'android'    => ['android'],
            'dos'        => ['dos'],
        ];

        $prefKey     = strtolower(trim($osPref));
        $keywords    = $osKeywordMap[$prefKey] ?? [strtolower($osPref)];

        $matched = false;
        foreach ($keywords as $kw) {
            if (str_contains($osRaw, $kw)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) continue;
    }

    // Masukkan pref ke data laptop untuk bonus skor (opsional, sudah ditangani oleh hard filter)
    $laptop['brand_pref'] = $brandPref;
    $laptop['os_pref']    = $osPref;

    $res = fuzzyInference($laptop, $budget, $profile);

    if ($res['score'] > 15) {
        $results[] = [
            'nomor'   => $laptop['nomor']     ?? '0',
            'name'    => $laptop['name']      ?? 'N/A',
            'brand'   => $laptop['brand']     ?? 'N/A',
            'price'   => $laptop['price'],
            'cpu'     => $laptop['processor'] ?? 'N/A',
            'gpu'     => $laptop['GPU']       ?? 'N/A',
            'ram'     => intval($laptop['ram_gb']) . ' GB',
            'storage' => intval($laptop['storage_gb']) . ' GB',
            'display' => $laptop['display_size'] . '"',
            'os'      => $laptop['OS']        ?? 'N/A',
            'score'   => $res['score'],
            'reason'  => $res['reason'],
        ];
    }
}

// Urutkan: skor tertinggi, jika sama → harga lebih murah tampil dulu
usort($results, function($a, $b) {
    if ($b['score'] !== $a['score']) return $b['score'] <=> $a['score'];
    return $a['price'] <=> $b['price'];
});

// Top 10
echo json_encode(array_slice($results, 0, 10));
