<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pakar Pemilihan Laptop | Fuzzy Logic</title>
    <meta name="description" content="Sistem pakar berbasis Fuzzy Logic untuk membantu mahasiswa memilih laptop sesuai budget dan kebutuhan akademik.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

    <!-- Dekorasi Latar Belakang -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="app-wrapper">

        <!-- ====== HERO HEADER ====== -->
        <header class="hero">
            <div class="hero-badge"><i class='bx bx-brain'></i> AI-Powered</div>
            <div class="logo">
                <div class="logo-icon"><i class='bx bx-laptop'></i></div>
                <div>
                    <h1>Sistem Pakar Laptop</h1>
                    <span class="logo-subtitle">untuk Mahasiswa</span>
                </div>
            </div>
            <p class="hero-desc">Temukan laptop ideal untuk kebutuhan akademikmu menggunakan teknologi <strong>Fuzzy Logic</strong> — mesin cerdas yang mempertimbangkan CPU, GPU, RAM, Storage, dan Budget secara holistik.</p>
        </header>

        <!-- ====== MAIN LAYOUT ====== -->
        <main class="main-grid">

            <!-- ===== FORM PENCARIAN ===== -->
            <aside class="filter-panel glass-card">
                <h2 class="panel-title"><i class='bx bx-filter-alt'></i> Kriteria Pencarian</h2>

                <form id="filterForm">
                    <!-- Budget -->
                    <div class="field-group">
                        <label for="budget">Maksimal Budget</label>
                        <div class="input-icon-wrap">
                            <i class='bx bx-money-withdraw'></i>
                            <input type="number" id="budget" value="10000000" min="2000000" max="100000000" step="500000">
                        </div>
                        <span class="budget-display" id="budgetDisplay">Rp 10.000.000</span>
                        <input type="range" class="slider" id="budgetSlider" min="2000000" max="50000000" step="500000" value="10000000">
                        <div class="slider-labels"><span>Rp 2jt</span><span>Rp 50jt</span></div>
                    </div>

                    <!-- Profil Akademik -->
                    <div class="field-group">
                        <label for="profile">Profil Akademik</label>
                        <div class="select-wrap">
                            <i class='bx bx-user-circle'></i>
                            <select id="profile">
                                <option value="Pemrograman / Data Science">💻 Pemrograman / Data Science</option>
                                <option value="Desain Grafis / Multimedia">🎨 Desain Grafis / Multimedia</option>
                                <option value="Administrasi / Tugas Umum" selected>📝 Administrasi / Tugas Umum</option>
                                <option value="Gaming">🎮 Gaming / Kinerja Tinggi</option>
                            </select>
                            <i class='bx bx-chevron-down select-arrow'></i>
                        </div>
                    </div>

                    <!-- Ukuran Layar -->
                    <div class="field-group">
                        <label for="minDisplay">Layar Minimal (Opsional)</label>
                        <div class="select-wrap">
                            <i class='bx bx-tv'></i>
                            <select id="minDisplay">
                                <option value="0">Tidak ada preferensi</option>
                                <option value="13.0">≥ 13" (Ultraportabel)</option>
                                <option value="14.0">≥ 14" (Standar)</option>
                                <option value="15.6">≥ 15.6" (Layar Lebar)</option>
                            </select>
                            <i class='bx bx-chevron-down select-arrow'></i>
                        </div>
                    </div>

                    <!-- Tombol Submit -->
                    <button type="submit" class="btn-cari" id="btnCari">
                        <i class='bx bx-search-alt-2'></i>
                        <span>Cari Rekomendasi</span>
                    </button>
                </form>

                <!-- Info Metode -->
                <div class="method-info">
                    <i class='bx bx-info-circle'></i>
                    <p>Menggunakan <strong>Fuzzy Sugeno</strong> dengan himpunan linguistik pada 5 variabel input: CPU, GPU, RAM, Storage, dan Harga.</p>
                </div>
            </aside>

            <!-- ===== AREA HASIL ===== -->
            <section class="results-area">
                <!-- Header Hasil -->
                <div class="results-top">
                    <h2>Hasil Rekomendasi</h2>
                    <div id="resultsBadge" class="results-badge">Belum ada pencarian</div>
                </div>

                <!-- Loading -->
                <div id="loadingState" class="state-box hidden">
                    <div class="spinner-ring"></div>
                    <p>Menganalisis <strong>800+ laptop</strong> dengan algoritma Fuzzy Logic...</p>
                    <span>Mohon tunggu sebentar</span>
                </div>

                <!-- Empty -->
                <div id="emptyState" class="state-box">
                    <div class="empty-icon"><i class='bx bx-search-alt'></i></div>
                    <p>Atur kriteria pencarian dan klik <strong>Cari Rekomendasi</strong> untuk memulai.</p>
                </div>

                <!-- Error -->
                <div id="errorState" class="state-box hidden error-state">
                    <div class="empty-icon"><i class='bx bx-error-alt'></i></div>
                    <p id="errorMsg">Terjadi kesalahan.</p>
                </div>

                <!-- Grid Kartu -->
                <div id="resultsGrid" class="results-grid hidden"></div>
            </section>

        </main>

    </div>

    <!-- ====== MODAL DETAIL ====== -->
    <div id="detailModal" class="modal-overlay" role="dialog" aria-modal="true">
        <div class="modal-box glass-card">
            <button id="closeModal" class="modal-close" aria-label="Tutup">
                <i class='bx bx-x'></i>
            </button>
            <div id="modalContent"></div>
        </div>
    </div>

    <script src="../assets/script.js"></script>
</body>
</html>
