import os
import pandas as pd
from flask import Flask, render_template, request, jsonify

app = Flask(__name__)

# Load and clean dataset globally
try:
    df = pd.read_csv('laptop_data_cleaned (2).csv')
    
    # Rename 'Unnamed: 0' or 'Unnamed: 0.1' to 'nomor'
    if 'Unnamed: 0.1' in df.columns:
        df.rename(columns={'Unnamed: 0.1': 'nomor'}, inplace=True)
    elif 'Unnamed: 0' in df.columns:
        df.rename(columns={'Unnamed: 0': 'nomor'}, inplace=True)
        
    df['price'] = pd.to_numeric(df['price'], errors='coerce').fillna(0)
    df['cpu_score'] = pd.to_numeric(df['cpu_score'], errors='coerce').fillna(50)
    df['gpu_class'] = pd.to_numeric(df['gpu_class'], errors='coerce').fillna(1)
    df['ram_gb'] = pd.to_numeric(df['ram_gb'], errors='coerce').fillna(4)
    df['storage_gb'] = pd.to_numeric(df['storage_gb'], errors='coerce').fillna(256)
    # Convert 1.0/2.0 TB storage to GB
    df['storage_gb'] = df['storage_gb'].apply(lambda x: x * 1024 if x <= 4 else x)
    df['display_size'] = pd.to_numeric(df['display_size'], errors='coerce').fillna(14.0)
    
except Exception as e:
    print(f"Error loading CSV: {e}")
    df = pd.DataFrame()

def trapz(x, a, b, c, d):
    if x <= a or x >= d: return 0.0
    if a <= x <= b: return (x - a) / (b - a) if b > a else 1.0
    if b <= x <= c: return 1.0
    if c <= x <= d: return (d - x) / (d - c) if d > c else 1.0
    return 0.0

def trimf(x, a, b, c):
    return trapz(x, a, b, b, c)

def fuzzy_inference(laptop, budget, profile):
    p = laptop['price']
    c = laptop['cpu_score']
    g = laptop['gpu_class']
    r = laptop['ram_gb']
    s = laptop['storage_gb']

    m = {
        'price': {
            'Rendah': trapz(p, 0, 0, 6000000, 10000000),
            'Sedang': trimf(p, 6000000, 10000000, 15000000),
            'Tinggi': trapz(p, 10000000, 15000000, 100000000, 100000000)
        },
        'cpu': {
            'Rendah': trapz(c, 0, 0, 40, 60),
            'Sedang': trimf(c, 40, 65, 80),
            'Tinggi': trapz(c, 65, 80, 100, 100)
        },
        'gpu': {
            'Rendah': trapz(g, 0, 1, 1, 2.5),
            'Sedang': trapz(g, 1.5, 2, 3, 3.5),
            'Tinggi': trapz(g, 2.5, 3.5, 5, 5)
        },
        'ram': {
            'Rendah': trapz(r, 0, 0, 4, 8),
            'Sedang': trimf(r, 4, 8, 16),
            'Tinggi': trapz(r, 8, 16, 128, 128)
        },
        'storage': {
            'Rendah': trapz(s, 0, 0, 256, 512),
            'Sedang': trimf(s, 256, 512, 1024),
            'Tinggi': trapz(s, 512, 1024, 4096, 4096)
        }
    }

    # Toleransi Harga: 100% kecocokan jika <= budget, menurun drastis hingga budget + 15%
    within_budget = trapz(p, 0, 0, budget, budget * 1.15)
    
    rules = []
    
    def add_rule(level, score, reason):
        rules.append({'level': level, 'score': score, 'reason': reason})

    if profile == 'Pemrograman / Data Science':
        add_rule('Tinggi', min(m['cpu']['Tinggi'], m['ram']['Tinggi']), "CPU & RAM sangat mumpuni untuk coding/data science.")
        add_rule('Sedang', min(m['cpu']['Sedang'], m['ram']['Tinggi']), "RAM besar, CPU cukup untuk pemrograman menengah.")
        add_rule('Sedang', min(m['cpu']['Tinggi'], m['ram']['Sedang']), "CPU cepat, RAM standar untuk pemrograman.")
        add_rule('Rendah', max(m['cpu']['Rendah'], m['ram']['Rendah']), "Spesifikasi (CPU/RAM) kurang untuk kompilasi kode berat.")
    elif profile == 'Desain Grafis / Multimedia':
        add_rule('Tinggi', min(m['gpu']['Tinggi'], m['cpu']['Tinggi'], m['ram']['Tinggi']), "Performa grafis & prosesor maksimal untuk rendering.")
        add_rule('Sedang', min(m['gpu']['Tinggi'], m['cpu']['Sedang']), "GPU dedicated yang bagus, cocok untuk desain UI/UX.")
        add_rule('Sedang', min(m['gpu']['Sedang'], m['cpu']['Tinggi']), "Prosesor kuat, GPU standar, oke untuk editing ringan.")
        add_rule('Rendah', m['gpu']['Rendah'], "Kekurangan GPU dedicated, tidak disarankan untuk 3D/video editing.")
    elif profile == 'Administrasi / Tugas Umum':
        add_rule('Tinggi', min(m['price']['Rendah'], m['cpu']['Sedang'], m['ram']['Sedang']), "Sangat ideal: harga terjangkau & performa cukup untuk office.")
        add_rule('Tinggi', min(m['price']['Rendah'], m['cpu']['Rendah'], m['ram']['Sedang']), "Sangat hemat, RAM cukup untuk multitasking ringan.")
        add_rule('Sedang', min(m['cpu']['Sedang'], m['ram']['Sedang']), "Performa stabil untuk tugas sehari-hari.")
        add_rule('Rendah', m['ram']['Rendah'], "RAM terlalu kecil, bisa lag saat buka banyak dokumen.")
    elif profile == 'Gaming':
        add_rule('Tinggi', min(m['gpu']['Tinggi'], m['cpu']['Tinggi']), "Kombinasi sempurna untuk gaming AAA.")
        add_rule('Sedang', min(m['gpu']['Sedang'], m['cpu']['Tinggi']), "GPU mid-range, CPU tangguh untuk gaming kompetitif.")
        add_rule('Sedang', min(m['gpu']['Tinggi'], m['ram']['Sedang']), "GPU gahar, RAM pas-pasan, perlu penyesuaian grafis.")
        add_rule('Rendah', max(m['gpu']['Rendah'], m['gpu']['Sedang']), "Tidak direkomendasikan untuk gaming modern.")
        
    # Prevent divide by zero
    add_rule('Rendah', 0.05, "Spesifikasi tidak teridentifikasi relevan.")

    # Agregasi Sugeno
    num = 0
    den = 0
    best_reason = ""
    max_score = -1

    for r in rules:
        weight = 25 if r['level'] == 'Rendah' else 60 if r['level'] == 'Sedang' else 95
        num += r['score'] * weight
        den += r['score']
        
        if r['score'] > max_score and r['score'] > 0.06:
            max_score = r['score']
            best_reason = r['reason']

    suitability = (num / den) if den > 0 else 0
    
    # Penalti budget
    final_score = suitability * within_budget
    
    if final_score < 20 and within_budget < 0.5:
        best_reason = "Harga melebihi toleransi budget pengguna."

    return final_score, best_reason


@app.route('/')
def index():
    return render_template('index.html')


@app.route('/api/recommend', methods=['POST'])
def recommend():
    if df.empty:
        return jsonify({"error": "Dataset belum dimuat atau tidak ditemukan."}), 500

    data = request.json
    budget = float(data.get('budget', 10000000))
    profile = data.get('profile', 'Administrasi / Tugas Umum')
    min_display = float(data.get('min_display', 0))

    results = []
    
    for _, row in df.iterrows():
        # Pra-filter: layar (crisp filter opsional)
        if min_display > 0 and row['display_size'] < min_display:
            continue
            
        # Pra-filter: harga jauh di atas budget di-skip untuk optimasi
        if row['price'] > budget * 1.2:
            continue

        score, reason = fuzzy_inference(row, budget, profile)
        
        if score > 20: # Hanya tampilkan yang minimal agak cocok
            results.append({
                'name': str(row['name']),
                'brand': str(row['brand']),
                'price': float(row['price']),
                'cpu': str(row['processor']),
                'gpu': str(row['GPU']),
                'ram': f"{int(row['ram_gb'])} GB",
                'storage': f"{int(row['storage_gb'])} GB",
                'display': f"{row['display_size']}\"",
                'score': round(score, 2),
                'reason': reason
            })
            
    # Urutkan berdasarkan skor tertinggi, lalu harga terendah jika skor mirip
    results.sort(key=lambda x: (x['score'], -x['price']), reverse=True)
    
    # Ambil top 10
    top_results = results[:10]
    
    return jsonify(top_results)

if __name__ == '__main__':
    app.run(debug=True, port=5000)
