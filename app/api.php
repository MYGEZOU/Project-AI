<?php
/**
 * Sistem Pakar Pemilihan Laptop - Fuzzy Logic (Sugeno/Tsukamoto Hybrid)
 * Backend API: Membaca CSV dan menjalankan inferensi fuzzy
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// =============================================
// FUNGSI MEMBERSHIP (KEANGGOTAAN FUZZY)
// =============================================

function trapz($x, $a, $b, $c, $d) {
    if ($x <= $a || $x >= $d) return 0.0;
    if ($x >= $b && $x <= $c)  return 1.0;
    if ($x > $a && $x < $b)    return ($b - $a) > 0 ? ($x - $a) / ($b - $a) : 1.0;
    if ($x > $c && $x < $d)    return ($d - $c) > 0 ? ($d - $x) / ($d - $c) : 1.0;
    return 0.0;
}

function trimf($x, $a, $b, $c) {
    return trapz($x, $a, $b, $b, $c);
}

function fand(...$vals) {
    return min($vals);
}

// =============================================
// BACA DAN PARSE DATASET CSV
// =============================================

function loadDataset($filepath) {
    $data = [];
    if (!file_exists($filepath)) return $data;

    $handle = fopen($filepath, 'r');
    if (!$handle) return $data;

    $header = fgetcsv($handle);
    if (!$header) return $data;
    
    $header = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $header);

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === count($header)) {
            $laptop = array_combine($header, $row);
            
            // Konversi numerik
            $laptop['price']        = (float) ($laptop['price'] ?? 0);
            $laptop['cpu_score']    = (float) ($laptop['cpu_score'] ?? 50);
            $laptop['gpu_class']    = (float) ($laptop['gpu_class'] ?? 1);
            $laptop['ram_gb']       = (float) ($laptop['ram_gb'] ?? 4);
            $laptop['storage_gb']   = (float) ($laptop['storage_gb'] ?? 256);
            $laptop['display_size'] = (float) ($laptop['display_size'] ?? 14.0);

            if ($laptop['storage_gb'] <= 4 && $laptop['storage_gb'] > 0) {
                $laptop['storage_gb'] *= 1024;
            }

            $data[] = $laptop;
        }
    }
    fclose($handle);
    return $data;
}

// =============================================
// MESIN INFERENSI FUZZY (SUGENO & TSUKAMOTO)
// =============================================

/**
 * METODE SUGENO
 * Output berupa nilai konstanta (z)
 */
function fuzzyInferenceSugeno($laptop, $budget, $profile) {
    $p = $laptop['price'];
    $c = $laptop['cpu_score'];
    $g = $laptop['gpu_class'];
    $r = $laptop['ram_gb'];

    // Fuzzifikasi
    $m = [
        'price' => [
            'Murah'  => trapz($p, 0, 0, 6000000, 10000000),
            'Sedang' => trimf($p, 7000000, 15000000, 25000000),
            'Mahal'  => trapz($p, 18000000, 28000000, 1e12, 1e12),
        ],
        'cpu' => [
            'Rendah' => trapz($c, 0, 0, 35, 55),
            'Sedang' => trimf($c, 40, 65, 85),
            'Tinggi' => trapz($c, 70, 85, 100, 100),
        ],
        'gpu' => [
            'Basic'  => trapz($g, 0, 1, 1, 2.2),
            'Mid'    => trimf($g, 1.8, 3, 4),
            'High'   => trapz($g, 3.5, 4.5, 5.5, 5.5),
        ],
        'ram' => [
            'Kecil'  => trapz($r, 0, 0, 4, 8),
            'Cukup'  => trimf($r, 4, 8, 16),
            'Besar'  => trapz($r, 8, 16, 128, 128),
        ],
    ];

    // Budget Match: laptop yang harganya mendekati budget mendapat nilai lebih tinggi
    // Sweet spot: 65%-100% dari budget (nilai penuh), di luar range ini nilainya berkurang
    $budget_match = trapz($p, 0, $budget * 0.65, $budget, $budget * 1.15);
    $rules = [];
    
    if ($profile === 'Pemrograman / Data Science') {
        $rules[] = ['w' => fand($m['cpu']['Tinggi'], $m['ram']['Besar']), 'z' => 100, 'msg' => 'Spek Monster: CPU & RAM terbaik untuk kompilasi.'];
        $rules[] = ['w' => fand($m['cpu']['Sedang'], $m['ram']['Besar']), 'z' => 85,  'msg' => 'Sangat Oke: RAM besar mendukung multitasking IDE.'];
        $rules[] = ['w' => $m['cpu']['Rendah'], 'z' => 20, 'msg' => 'Prosesor kurang bertenaga untuk pengembangan software.'];
    } elseif ($profile === 'Desain Grafis / Multimedia') {
        $rules[] = ['w' => fand($m['gpu']['High'], $m['cpu']['Tinggi']), 'z' => 100, 'msg' => 'Sempurna: Kombinasi GPU & CPU terbaik untuk rendering.'];
        $rules[] = ['w' => $m['gpu']['Basic'], 'z' => 15, 'msg' => 'GPU Integrated kurang disarankan untuk desain profesional.'];
    } elseif ($profile === 'Gaming') {
        $rules[] = ['w' => fand($m['gpu']['High'], $m['cpu']['Tinggi']), 'z' => 100, 'msg' => 'Gaming Beast: Siap libas game AAA rata kanan.'];
        $rules[] = ['w' => $m['gpu']['Basic'], 'z' => 10, 'msg' => 'Bukan laptop gaming: Performa grafis terbatas.'];
    } else {
        $rules[] = ['w' => fand($m['price']['Murah'], $m['ram']['Cukup']), 'z' => 100, 'msg' => 'Pilihan Cerdas: Harga terjangkau dengan RAM cukup.'];
        $rules[] = ['w' => $m['ram']['Kecil'], 'z' => 30, 'msg' => 'RAM 4GB mungkin terasa lambat saat ini.'];
    }

    $num = 0; $den = 0;
    $best_msg = "Sesuai kriteria.";
    $max_w = -1;

    foreach ($rules as $r) {
        $num += $r['w'] * $r['z'];
        $den += $r['w'];
        if ($r['w'] > $max_w && $r['w'] > 0) {
            $max_w = $r['w'];
            $best_msg = $r['msg'];
        }
    }

    $score = ($den > 0) ? ($num / $den) : 40;
    return ['score' => round($score * $budget_match, 2), 'reason' => $best_msg];
}

/**
 * METODE TSUKAMOTO
 * Output dihitung menggunakan fungsi keanggotaan monoton (z = f(alpha))
 */
function fuzzyInferenceTsukamoto($laptop, $budget, $profile) {
    $p = $laptop['price'];
    $c = $laptop['cpu_score'];
    $g = $laptop['gpu_class'];
    $r = $laptop['ram_gb'];

    // Fuzzifikasi (Input sama dengan Sugeno)
    $m = [
        'cpu' => [
            'Rendah' => trapz($c, 0, 0, 35, 55),
            'Sedang' => trimf($c, 40, 65, 85),
            'Tinggi' => trapz($c, 70, 85, 100, 100),
        ],
        'gpu' => [
            'Basic'  => trapz($g, 0, 1, 1, 2.2),
            'High'   => trapz($g, 3.5, 4.5, 5.5, 5.5),
        ],
        'ram' => [
            'Kecil'  => trapz($r, 0, 0, 4, 8),
            'Besar'  => trapz($r, 8, 16, 128, 128),
        ],
    ];

    // Budget Match: sweet spot 65%-100% dari budget
    $budget_match = trapz($p, 0, $budget * 0.65, $budget, $budget * 1.15);
    
    /**
     * Definisi Output Monoton (Suitability 0-100)
     * Tinggi: z = 0 + (alpha * 100) -> Makin besar alpha, makin tinggi z
     * Rendah: z = 100 - (alpha * 100) -> Makin besar alpha, makin rendah z
     */
    $rules = [];
    
    if ($profile === 'Pemrograman / Data Science') {
        $a1 = fand($m['cpu']['Tinggi'], $m['ram']['Besar']);
        $rules[] = ['w' => $a1, 'z' => (0 + ($a1 * 100)), 'msg' => '[Tsukamoto] Spek sangat direkomendasikan.'];
        
        $a2 = $m['cpu']['Rendah'];
        $rules[] = ['w' => $a2, 'z' => (100 - ($a2 * 100)), 'msg' => '[Tsukamoto] Performa CPU terlalu rendah.'];
    } elseif ($profile === 'Desain Grafis / Multimedia' || $profile === 'Gaming') {
        $a1 = fand($m['gpu']['High'], $m['cpu']['Tinggi']);
        $rules[] = ['w' => $a1, 'z' => (0 + ($a1 * 100)), 'msg' => '[Tsukamoto] Grafis & CPU sangat kuat.'];
        
        $a2 = $m['gpu']['Basic'];
        $rules[] = ['w' => $a2, 'z' => (100 - ($a2 * 100)), 'msg' => '[Tsukamoto] Performa grafis kurang.'];
    } else {
        $a1 = $m['ram']['Besar'];
        $rules[] = ['w' => $a1, 'z' => (0 + ($a1 * 100)), 'msg' => '[Tsukamoto] Sangat nyaman untuk multitasking.'];
        
        $a2 = $m['ram']['Kecil'];
        $rules[] = ['w' => $a2, 'z' => (100 - ($a2 * 100)), 'msg' => '[Tsukamoto] RAM terlalu terbatas.'];
    }

    $num = 0; $den = 0;
    $max_w = -1; $best_msg = "Sesuai.";
    
    foreach ($rules as $r) {
        if ($r['w'] > 0) {
            $num += $r['w'] * $r['z'];
            $den += $r['w'];
            if ($r['w'] > $max_w) {
                $max_w = $r['w'];
                $best_msg = $r['msg'];
            }
        }
    }

    $score = ($den > 0) ? ($num / $den) : 50;
    return ['score' => round($score * $budget_match, 2), 'reason' => $best_msg];
}

// =============================================
// MAIN HANDLER
// =============================================

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => 'Invalid Request']);
    exit;
}

$budget  = (float)($input['budget'] ?? 10000000);
$profile = $input['profile'] ?? 'Administrasi / Tugas Umum';
$method  = strtolower($input['method'] ?? 'sugeno'); // 'sugeno' atau 'tsukamoto'
$brandPref = $input['brand'] ?? '';

$csvPath = __DIR__ . '/../laptop_data_cleaned (2).csv';
$laptops = loadDataset($csvPath);

if (empty($laptops)) {
    echo json_encode(['error' => 'Dataset tidak ditemukan atau kosong. Path: ' . $csvPath]);
    exit;
}

$results = [];
foreach ($laptops as $laptop) {
    if (!empty($brandPref) && strcasecmp($laptop['brand'], $brandPref) !== 0) continue;
    // Hard filter: hanya laptop dalam 115% dari budget yang diproses
    if ($laptop['price'] > $budget * 1.15) continue;

    // Pilih Metode
    if ($method === 'tsukamoto') {
        $res = fuzzyInferenceTsukamoto($laptop, $budget, $profile);
    } else {
        $res = fuzzyInferenceSugeno($laptop, $budget, $profile);
    }
    
    if ($res['score'] > 5) {
        $results[] = [
            'nomor'   => $laptop['nomor']     ?? '0',
            'name'    => $laptop['name']       ?? 'N/A',
            'brand'   => $laptop['brand']      ?? 'N/A',
            'price'   => $laptop['price'],
            'cpu'     => $laptop['processor']  ?? 'N/A',
            'gpu'     => $laptop['GPU']        ?? 'N/A',
            'ram'     => intval($laptop['ram_gb'])     . ' GB',
            'storage' => intval($laptop['storage_gb']) . ' GB',
            'display' => ($laptop['display_size'] ?? '?') . '"',
            'os'      => $laptop['OS']         ?? 'N/A',
            'score'   => $res['score'],
            'reason'  => $res['reason'],
            'method'  => strtoupper($method)
        ];
    }
}

usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
echo json_encode(array_slice($results, 0, 12));
