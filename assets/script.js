// =============================================
// SISTEM PAKAR LAPTOP - Frontend JS
// =============================================

const API_URL = '../app/api.php';

const $ = (id) => document.getElementById(id);

// Elements
const form          = $('filterForm');
const budgetInput   = $('budget');
const budgetSlider  = $('budgetSlider');
const budgetDisplay = $('budgetDisplay');
const profileSel    = $('profile');
const displaySel    = $('minDisplay');
const osSel         = $('osPref');
const brandSel      = $('brandPref');
const btnCari       = $('btnCari');

const loadingState  = $('loadingState');
const emptyState    = $('emptyState');
const errorState    = $('errorState');
const errorMsg      = $('errorMsg');
const resultsGrid   = $('resultsGrid');
const resultsBadge  = $('resultsBadge');

const modal         = $('detailModal');
const closeModal    = $('closeModal');
const modalContent  = $('modalContent');

// =============================================
// FORMAT RUPIAH
// =============================================
const formatRupiah = (n) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n);

// =============================================
// SINKRON SLIDER ↔ INPUT BUDGET
// =============================================
const updateBudgetDisplay = (val) => {
    budgetDisplay.textContent = formatRupiah(val);
};

budgetSlider.addEventListener('input', (e) => {
    budgetInput.value = e.target.value;
    updateBudgetDisplay(e.target.value);
});

budgetInput.addEventListener('input', (e) => {
    const v = parseInt(e.target.value) || 10000000;
    const clamped = Math.min(Math.max(v, 2000000), 40000000);
    budgetSlider.value = clamped;
    updateBudgetDisplay(e.target.value);
});

updateBudgetDisplay(budgetInput.value);

// =============================================
// UI STATE HELPERS
// =============================================
const showState = (active) => {
    [loadingState, emptyState, errorState, resultsGrid].forEach(el => el.classList.add('hidden'));
    active.classList.remove('hidden');
};

// =============================================
// SCORE RING SVG
// =============================================
function buildScoreRing(score) {
    const r   = 20;
    const circ = 2 * Math.PI * r;
    const fill = circ - (circ * score / 100);
    return `
    <div class="score-ring" title="Skor kecocokan: ${Math.round(score)}%">
      <svg viewBox="0 0 48 48" width="50" height="50">
        <circle class="score-track" cx="24" cy="24" r="${r}"/>
        <circle class="score-fill"
          cx="24" cy="24" r="${r}"
          stroke-dasharray="${circ}"
          stroke-dashoffset="${fill}"/>
        <text class="score-text" x="24" y="24" transform="rotate(90 24 24)">${Math.round(score)}</text>
      </svg>
    </div>`;
}

// =============================================
// RENDER KARTU LAPTOP
// =============================================
function renderCards(laptops) {
    resultsGrid.innerHTML = '';

    laptops.forEach((laptop, idx) => {
        const card = document.createElement('div');
        card.className = 'laptop-card';
        card.style.animationDelay = `${idx * 0.07}s`;

        // Potong nama GPU agar tidak terlalu panjang
        const gpuShort = laptop.gpu.length > 30 ? laptop.gpu.substring(0, 28) + '…' : laptop.gpu;

        card.innerHTML = `
            <span class="rank-badge">#${idx + 1}</span>
            ${buildScoreRing(laptop.score)}
            <div class="card-brand">${laptop.brand}</div>
            <div class="card-name" title="${laptop.name}">${laptop.name}</div>
            <div class="card-price">${formatRupiah(laptop.price)}</div>
            <div class="card-specs">
                <div class="spec-row"><i class='bx bx-chip'></i><span>${laptop.cpu}</span></div>
                <div class="spec-row"><i class='bx bx-grid-alt'></i><span>${gpuShort}</span></div>
                <div class="spec-row"><i class='bx bx-memory-card'></i><span>${laptop.ram} RAM • ${laptop.storage} Storage</span></div>
                <div class="spec-row"><i class='bx bx-desktop'></i><span>${laptop.display} • ${laptop.os}</span></div>
            </div>
            <button class="card-btn"><i class='bx bx-info-circle'></i> Lihat Detail & Alasan Fuzzy</button>
        `;

        card.querySelector('.card-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            showModal(laptop, idx + 1);
        });
        card.addEventListener('click', () => showModal(laptop, idx + 1));

        resultsGrid.appendChild(card);
    });
}

// =============================================
// MODAL DETAIL
// =============================================
function showModal(laptop, rank) {
    const scoreW = Math.round(laptop.score);

    modalContent.innerHTML = `
        <div class="modal-brand">${laptop.brand} · Peringkat #${rank}</div>
        <div class="modal-name">${laptop.name}</div>
        <div class="modal-price">${formatRupiah(laptop.price)}</div>

        <!-- Skor Kecocokan -->
        <div class="modal-score-row">
            <div class="modal-score-num">${scoreW}%</div>
            <div class="score-bar-wrap">
                <div class="score-bar-label">Skor Kecocokan Fuzzy Logic</div>
                <div class="score-bar-track">
                    <div class="score-bar-fill" style="width: ${scoreW}%"></div>
                </div>
            </div>
        </div>

        <!-- Spesifikasi Grid -->
        <div class="modal-specs-grid">
            <div class="modal-spec-item">
                <i class='bx bx-chip'></i>
                <div>
                    <div class="spec-label">PROCESSOR</div>
                    <div class="spec-value">${laptop.cpu}</div>
                </div>
            </div>
            <div class="modal-spec-item">
                <i class='bx bx-grid-alt'></i>
                <div>
                    <div class="spec-label">GRAPHICS</div>
                    <div class="spec-value">${laptop.gpu}</div>
                </div>
            </div>
            <div class="modal-spec-item">
                <i class='bx bx-memory-card'></i>
                <div>
                    <div class="spec-label">RAM</div>
                    <div class="spec-value">${laptop.ram}</div>
                </div>
            </div>
            <div class="modal-spec-item">
                <i class='bx bx-hdd'></i>
                <div>
                    <div class="spec-label">STORAGE</div>
                    <div class="spec-value">${laptop.storage}</div>
                </div>
            </div>
            <div class="modal-spec-item">
                <i class='bx bx-desktop'></i>
                <div>
                    <div class="spec-label">LAYAR</div>
                    <div class="spec-value">${laptop.display}</div>
                </div>
            </div>
            <div class="modal-spec-item">
                <i class='bx bx-laptop'></i>
                <div>
                    <div class="spec-label">SISTEM OPERASI</div>
                    <div class="spec-value">${laptop.os}</div>
                </div>
            </div>
        </div>

        <!-- Alasan Fuzzy -->
        <div class="fuzzy-reason">
            <i class='bx bx-check-shield'></i>
            <div>
                <strong>Alasan Rekomendasi (Fuzzy Rule)</strong>
                ${laptop.reason}
            </div>
        </div>
    `;

    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

closeModal.addEventListener('click', closeModalFn);
modal.addEventListener('click', (e) => { if (e.target === modal) closeModalFn(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModalFn(); });

function closeModalFn() {
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

// =============================================
// FETCH REKOMENDASI
// =============================================
form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const budget     = parseInt(budgetInput.value) || 10000000;
    const profile    = profileSel.value;
    const minDisplay = parseFloat(displaySel.value) || 0;
    const os         = osSel.value;
    const brand      = brandSel.value;

    // UI: Loading
    showState(loadingState);
    resultsBadge.textContent = 'Menganalisis...';
    btnCari.classList.add('loading');
    btnCari.disabled = true;

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ budget, profile, min_display: minDisplay, os, brand })
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();

        if (data.error) {
            showState(errorState);
            errorMsg.textContent = '⚠️ ' + data.error;
            resultsBadge.textContent = 'Error';
            return;
        }

        if (!Array.isArray(data) || data.length === 0) {
            showState(emptyState);
            emptyState.querySelector('p').innerHTML =
                'Tidak ada laptop yang cocok dengan kriteria ini. Coba perbesar budget atau ganti profil akademik.';
            resultsBadge.textContent = '0 Ditemukan';
            return;
        }

        // Render hasil
        showState(resultsGrid);
        renderCards(data);
        resultsBadge.textContent = `${data.length} Laptop Terbaik`;
        resultsBadge.style.background = 'rgba(16,185,129,.12)';
        resultsBadge.style.borderColor = 'rgba(16,185,129,.3)';
        resultsBadge.style.color = '#34d399';

    } catch (err) {
        showState(errorState);
        errorMsg.textContent = `Gagal terhubung ke server API. Pastikan XAMPP Apache aktif. (${err.message})`;
        resultsBadge.textContent = 'Gagal';
        console.error(err);
    } finally {
        btnCari.classList.remove('loading');
        btnCari.disabled = false;
    }
});
