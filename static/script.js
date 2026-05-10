document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('recommendationForm');
    const budgetInput = document.getElementById('budget');
    const budgetSlider = document.getElementById('budgetSlider');
    const resultsContainer = document.getElementById('resultsContainer');
    const resultCount = document.getElementById('resultCount');
    const loading = document.getElementById('loading');
    const modal = document.getElementById('detailModal');
    const closeModal = document.getElementById('closeModal');
    const modalBody = document.getElementById('modalBody');

    // Sync budget input and slider
    budgetSlider.addEventListener('input', (e) => {
        budgetInput.value = e.target.value;
    });

    budgetInput.addEventListener('input', (e) => {
        if(e.target.value >= 2000000 && e.target.value <= 50000000) {
            budgetSlider.value = e.target.value;
        }
    });

    // Format Rupiah
    const formatRupiah = (number) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number);
    };

    // Close modal
    closeModal.addEventListener('click', () => {
        modal.classList.remove('active');
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });

    // Handle form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // UI states
        resultsContainer.innerHTML = '';
        loading.classList.remove('hidden');
        resultCount.textContent = 'Menganalisis...';
        
        const payload = {
            budget: budgetInput.value,
            profile: document.getElementById('profile').value,
            min_display: document.getElementById('minDisplay').value
        };

        try {
            const response = await fetch('/api/recommend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            
            loading.classList.add('hidden');
            resultCount.textContent = `${data.length} Ditemukan`;

            if (data.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="empty-state">
                        <i class='bx bx-sad'></i>
                        <p>Tidak ada laptop yang sesuai dengan kriteria dan budget ini.</p>
                    </div>`;
                return;
            }

            // Render cards
            data.forEach((laptop, index) => {
                const card = document.createElement('div');
                card.className = 'card';
                card.style.animationDelay = `${index * 0.1}s`;
                
                // Truncate name
                const shortName = laptop.name.length > 50 ? laptop.name.substring(0, 47) + '...' : laptop.name;
                
                card.innerHTML = `
                    <div class="card-score">${Math.round(laptop.score)}</div>
                    <div class="card-brand">${laptop.brand}</div>
                    <h3 class="card-title" title="${laptop.name}">${shortName}</h3>
                    <div class="card-price">${formatRupiah(laptop.price)}</div>
                    
                    <div class="card-specs">
                        <div class="spec-item">
                            <i class='bx bx-chip'></i>
                            <span>${laptop.cpu.substring(0, 25)}${laptop.cpu.length > 25 ? '...' : ''}</span>
                        </div>
                        <div class="spec-item">
                            <i class='bx bx-memory-card'></i>
                            <span>${laptop.ram} RAM | ${laptop.storage}</span>
                        </div>
                        <div class="spec-item">
                            <i class='bx bx-desktop'></i>
                            <span>${laptop.display} Display</span>
                        </div>
                    </div>
                    
                    <button class="btn-outline view-details">Lihat Detail & Alasan</button>
                `;
                
                const btn = card.querySelector('.view-details');
                btn.addEventListener('click', () => showDetails(laptop));
                
                resultsContainer.appendChild(card);
            });

        } catch (error) {
            console.error('Error fetching recommendations:', error);
            loading.classList.add('hidden');
            resultsContainer.innerHTML = `
                <div class="empty-state" style="color: #ef4444;">
                    <i class='bx bx-error-circle'></i>
                    <p>Terjadi kesalahan saat mengambil data. Pastikan server Flask berjalan.</p>
                </div>`;
            resultCount.textContent = 'Error';
        }
    });

    function showDetails(laptop) {
        modalBody.innerHTML = `
            <div class="modal-header">
                <div class="card-brand">${laptop.brand}</div>
                <h2>${laptop.name}</h2>
            </div>
            
            <div class="card-price" style="margin-bottom: 1rem;">${formatRupiah(laptop.price)}</div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="spec-item">
                    <i class='bx bx-chip'></i>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary)">Processor</div>
                        <div>${laptop.cpu}</div>
                    </div>
                </div>
                <div class="spec-item">
                    <i class='bx bx-grid-alt'></i>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary)">Graphics</div>
                        <div>${laptop.gpu}</div>
                    </div>
                </div>
                <div class="spec-item">
                    <i class='bx bx-memory-card'></i>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary)">Memory (RAM)</div>
                        <div>${laptop.ram}</div>
                    </div>
                </div>
                <div class="spec-item">
                    <i class='bx bx-hdd'></i>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary)">Storage</div>
                        <div>${laptop.storage}</div>
                    </div>
                </div>
                <div class="spec-item">
                    <i class='bx bx-desktop'></i>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary)">Layar</div>
                        <div>${laptop.display} Inch</div>
                    </div>
                </div>
                <div class="spec-item">
                    <i class='bx bx-line-chart'></i>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary)">Suitability Score</div>
                        <div style="color: var(--success); font-weight: bold;">${Math.round(laptop.score)}%</div>
                    </div>
                </div>
            </div>
            
            <div class="reason-box">
                <i class='bx bx-check-shield'></i>
                <div>
                    <h4 style="margin-bottom: 0.25rem;">Alasan Rekomendasi (Fuzzy Logic)</h4>
                    <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">${laptop.reason}</p>
                </div>
            </div>
        `;
        
        modal.classList.add('active');
    }
});
