/**
 * JavaScript Halaman Administrator (Preview Upload Foto & Grafik Chart.js)
 */

(function () {
    'use strict';

document.addEventListener('DOMContentLoaded', () => {
    // 1. Live Preview & Validasi Ukuran/Ekstensi Client-Side untuk Upload Foto Kontestan
    const fotoInput = document.getElementById('foto');
    const previewImg = document.getElementById('fotoPreview');
    if (fotoInput && previewImg) {
        if (!previewImg.dataset.defaultSrc) {
            previewImg.dataset.defaultSrc = previewImg.src;
        }

        fotoInput.addEventListener('change', () => {
            const file = fotoInput.files && fotoInput.files[0] ? fotoInput.files[0] : null;
            if (!file) {
                if (previewImg.dataset.defaultSrc) {
                    previewImg.src = previewImg.dataset.defaultSrc;
                }
                return;
            }

            const maxBytes = 2 * 1024 * 1024;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!allowedTypes.includes(file.type)) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Format Tidak Didukung',
                        text: 'Pilih file gambar berformat JPEG, PNG, atau WebP.',
                        confirmButtonColor: '#047857'
                    });
                }
                fotoInput.value = '';
                if (previewImg.dataset.defaultSrc) {
                    previewImg.src = previewImg.dataset.defaultSrc;
                }
                return;
            }

            if (file.size > maxBytes) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Ukuran File Terlalu Besar',
                        text: 'Maksimal ukuran foto kontestan adalah 2 MB.',
                        confirmButtonColor: '#047857'
                    });
                }
                fotoInput.value = '';
                if (previewImg.dataset.defaultSrc) {
                    previewImg.src = previewImg.dataset.defaultSrc;
                }
                return;
            }

            const reader = new FileReader();
            reader.onload = (evt) => {
                if (evt.target && typeof evt.target.result === 'string') {
                    previewImg.src = evt.target.result;
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // 2. Inisialisasi Grafik Chart.js dari data JSON aman
    if (typeof Chart !== 'undefined') {
        document.querySelectorAll('canvas[data-chart-json]').forEach((canvas) => {
            const jsonId = canvas.getAttribute('data-chart-json');
            const scriptEl = jsonId ? document.getElementById(jsonId) : null;
            if (!scriptEl) return;

            try {
                const payload = JSON.parse(scriptEl.textContent || '{}');
                const labels = Array.isArray(payload.labels) ? payload.labels : [];
                const values = Array.isArray(payload.values) ? payload.values : [];
                const colors = (Array.isArray(payload.colors) || (typeof payload.colors === 'string' && payload.colors !== ''))
                    ? payload.colors
                    : '#047857';
                const labelTitle = payload.datasetLabel || 'Jumlah Suara';

                new Chart(canvas, {
                    type: payload.type || 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: labelTitle,
                            data: values,
                            backgroundColor: colors,
                            borderRadius: 6,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: (payload.type === 'doughnut')
                            }
                        },
                        scales: payload.type === 'doughnut' ? {} : {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            } catch (err) {
                // Abaikan JSON grafik tidak valid
            }
        });
    }
});
})();

