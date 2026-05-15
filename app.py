from flask import Flask, request, render_template_string

app = Flask(__name__)

# ==============================================================================
# FUNGSI KEANGGOTAAN INPUT (FUZZIFIKASI)
# ==============================================================================
def trapesium(x, a, b, c, d):
    if x <= a or x >= d: return 0.0
    elif a < x < b: return (x - a) / (b - a)
    elif b <= x <= c: return 1.0
    elif c < x < d: return (d - x) / (d - c)
    return 0.0

def segitiga(x, a, b, c):
    if x <= a or x >= c: return 0.0
    elif a < x <= b: return (x - a) / (b - a)
    elif b < x < c: return (c - x) / (c - b)
    return 0.0

def fuzzifikasi(harga, ram, peny, layar):
    f_harga = {
        'Murah': trapesium(harga, 0, 0, 5, 10),
        'Sedang': segitiga(harga, 5, 10, 15),
        'Mahal': trapesium(harga, 10, 15, 30, 30)
    }
    f_ram = {
        'Kecil': trapesium(ram, 0, 0, 4, 8),
        'Sedang': segitiga(ram, 4, 8, 16),
        'Besar': trapesium(ram, 8, 16, 64, 64)
    }
    f_peny = {
        'Kecil': trapesium(peny, 0, 0, 256, 512),
        'Sedang': segitiga(peny, 256, 512, 1000),
        'Besar': trapesium(peny, 512, 1000, 4000, 4000)
    }
    f_layar = {
        'Kecil': trapesium(layar, 0, 0, 11, 14),
        'Standar': segitiga(layar, 11, 14, 16),
        'Besar': trapesium(layar, 14, 16, 20, 20)
    }
    return f_harga, f_ram, f_peny, f_layar

# ==============================================================================
# BASIS ATURAN BERSAMA (8 ATURAN)
# ==============================================================================
def evaluasi_aturan(f_harga, f_ram, f_peny, f_layar):
    rules_eval = []
    
    def add_rule(h, r, p, l, concl_tsu, concl_sug):
        alpha = min(f_harga[h], f_ram[r], f_peny[p], f_layar[l])
        rules_eval.append({
            'alpha': alpha,
            'tsu': concl_tsu,
            'sug': concl_sug
        })

    add_rule('Murah', 'Besar', 'Besar', 'Standar', 'Tinggi', 'Tinggi')
    add_rule('Mahal', 'Kecil', 'Kecil', 'Kecil', 'Rendah', 'Rendah')
    add_rule('Sedang', 'Sedang', 'Sedang', 'Standar', 'Tinggi', 'Cukup')
    add_rule('Mahal', 'Kecil', 'Sedang', 'Standar', 'Rendah', 'Rendah')
    add_rule('Sedang', 'Besar', 'Besar', 'Besar', 'Tinggi', 'Tinggi')
    add_rule('Murah', 'Kecil', 'Kecil', 'Standar', 'Rendah', 'Rendah')
    add_rule('Murah', 'Sedang', 'Sedang', 'Kecil', 'Tinggi', 'Cukup')
    add_rule('Mahal', 'Besar', 'Besar', 'Besar', 'Tinggi', 'Tinggi')
    
    rules_eval.append({'alpha': 0.01, 'tsu': 'Rendah', 'sug': 'Rendah'})
    return rules_eval

# ==============================================================================
# INFERENSI TSUKAMOTO
# ==============================================================================
def inferensi_tsukamoto(rules):
    def z_rendah(alpha): return 100 - (100 * alpha)
    def z_tinggi(alpha): return 100 * alpha
    
    sum_alpha_z = 0
    sum_alpha = 0
    
    for r in rules:
        if r['alpha'] > 0:
            if r['tsu'] == 'Tinggi':
                z = z_tinggi(r['alpha'])
            else:
                z = z_rendah(r['alpha'])
            sum_alpha_z += r['alpha'] * z
            sum_alpha += r['alpha']
            
    return sum_alpha_z / sum_alpha

# ==============================================================================
# INFERENSI SUGENO (ORDE NOL)
# ==============================================================================
def inferensi_sugeno(rules):
    konstanta = {'Rendah': 25, 'Cukup': 65, 'Tinggi': 100}
    
    sum_alpha_z = 0
    sum_alpha = 0
    
    for r in rules:
        if r['alpha'] > 0:
            z = konstanta[r['sug']]
            sum_alpha_z += r['alpha'] * z
            sum_alpha += r['alpha']
            
    return sum_alpha_z / sum_alpha

def interpretasi_skor(skor):
    if skor < 40: return "Tidak Direkomendasikan"
    elif skor < 70: return "Cukup"
    else: return "Sangat Direkomendasikan"

# ==============================================================================
# TEMPLATE HTML TUNGGAL
# ==============================================================================
HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Rekomendasi Laptop Fuzzy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 12px; }
        .navbar-brand { font-weight: bold; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="/">💻 Sistem Rekomendasi Laptop (Logika Fuzzy)</a>
        </div>
    </nav>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card mb-5 border-0">
                    <div class="card-header bg-primary text-white text-center py-3" style="border-radius: 12px 12px 0 0;">
                        <h5 class="mb-0">Pilih Spesifikasi dan Metode Logika</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary">Harga (Juta Rupiah)</label>
                                    <input type="number" step="0.1" class="form-control form-control-lg" name="harga" value="{{ request.form.harga | default('10') }}" required>
                                    <div class="form-text">Contoh: 15.5 untuk Rp 15.500.000</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary">RAM (GB)</label>
                                    <input type="number" class="form-control form-control-lg" name="ram" value="{{ request.form.ram | default('8') }}" required>
                                    <div class="form-text">Contoh: 8, 16, 32</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary">Penyimpanan (GB)</label>
                                    <input type="number" class="form-control form-control-lg" name="peny" value="{{ request.form.peny | default('512') }}" required>
                                    <div class="form-text">Contoh: 256, 512, 1000</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary">Ukuran Layar (Inci)</label>
                                    <input type="number" step="0.1" class="form-control form-control-lg" name="layar" value="{{ request.form.layar | default('14') }}" required>
                                    <div class="form-text">Contoh: 13.3, 14, 15.6</div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary">Pilih Metode Inferensi Fuzzy</label>
                                <select class="form-select form-select-lg border-primary" name="metode" required>
                                    <option value="tsukamoto" {% if request.form.metode == 'tsukamoto' %}selected{% endif %}>🟢 Metode Tsukamoto</option>
                                    <option value="sugeno" {% if request.form.metode == 'sugeno' %}selected{% endif %}>🔵 Metode Sugeno (Orde Nol)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm py-3">HITUNG SKOR REKOMENDASI</button>
                        </form>

                        {% if result is not none %}
                        <div class="alert alert-{{ 'primary' if request.form.metode == 'tsukamoto' else 'success' }} text-center mt-5 p-4 shadow-sm" style="border-radius: 12px;">
                            <p class="mb-2 text-uppercase fw-bold" style="letter-spacing: 1px;">Hasil Inferensi {{ request.form.metode | title }}</p>
                            <h1 class="display-3 fw-bold mb-3">{{ "%.2f"|format(result) }} <span class="fs-4 text-muted">/ 100</span></h1>
                            <h4 class="mb-0">Kategori: <span class="badge bg-{{ 'danger' if kategori == 'Tidak Direkomendasikan' else 'warning text-dark' if kategori == 'Cukup' else 'success' }} px-3 py-2">{{ kategori }}</span></h4>
                        </div>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
"""

# ==============================================================================
# FLASK ROUTES
# ==============================================================================
@app.route('/', methods=['GET', 'POST'])
def index():
    result = None
    kategori = None
    
    if request.method == 'POST':
        harga = float(request.form.get('harga', 0))
        ram = float(request.form.get('ram', 0))
        peny = float(request.form.get('peny', 0))
        layar = float(request.form.get('layar', 0))
        metode = request.form.get('metode')
        
        f_harga, f_ram, f_peny, f_layar = fuzzifikasi(harga, ram, peny, layar)
        rules = evaluasi_aturan(f_harga, f_ram, f_peny, f_layar)
        
        if metode == 'tsukamoto':
            result = inferensi_tsukamoto(rules)
        else:
            result = inferensi_sugeno(rules)
            
        kategori = interpretasi_skor(result)
        
    return render_template_string(HTML_TEMPLATE, result=result, kategori=kategori)

if __name__ == '__main__':
    print("="*60)
    print("🚀 Menjalankan Server Web (Tsukamoto & Sugeno) - PORT 5000")
    print("="*60)
    app.run(debug=True, port=5000)
