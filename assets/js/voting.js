/**
 * Interaksi Bilik Suara & Konfirmasi Voting Permanen
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

(function () {
    'use strict';

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('ks-voting-form');
        if (!form) {
            return;
        }

        const radios = Array.from(form.querySelectorAll('input.ks-vote-radio[name="kontestan_id"]'));
        const summaryEl = document.getElementById('ks-selected-summary');
        const submitBtn = document.getElementById('ks-btn-submit-vote');

        if (submitBtn && !submitBtn.dataset.originalHtml) {
            submitBtn.dataset.originalHtml = submitBtn.innerHTML;
        }

        function setSubmitLoading() {
            if (!submitBtn) {
                return;
            }
            submitBtn.disabled = true;
            const loadingText = submitBtn.getAttribute('data-loading-text') || 'Menyimpan suara...';
            submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${escapeHtml(loadingText)}`;
        }

        function syncSelectionState() {
            let checkedRadio = null;

            radios.forEach((radio) => {
                const card = radio.closest('.ks-vote-option');
                const pillLabel = card ? card.querySelector('.ks-vote-check-text') : null;
                const pillIcon = card ? card.querySelector('.ks-vote-check-icon') : null;
                const footerBtn = card ? card.querySelector('.ks-select-btn') : null;

                if (radio.checked) {
                    checkedRadio = radio;
                    if (card) {
                        card.classList.add('is-selected');
                    }
                    if (pillLabel) {
                        pillLabel.textContent = 'Terpilih';
                    }
                    if (pillIcon) {
                        pillIcon.className = 'bi bi-check-circle-fill ks-vote-check-icon';
                    }
                    if (footerBtn) {
                        footerBtn.textContent = 'Terpilih';
                    }
                } else {
                    if (card) {
                        card.classList.remove('is-selected');
                    }
                    if (pillLabel) {
                        pillLabel.textContent = 'Pilih';
                    }
                    if (pillIcon) {
                        pillIcon.className = 'bi bi-circle ks-vote-check-icon';
                    }
                    if (footerBtn) {
                        footerBtn.textContent = 'Pilih';
                    }
                }
            });

            if (summaryEl) {
                if (checkedRadio) {
                    const nomor = checkedRadio.dataset.nomor || '-';
                    const nama = checkedRadio.dataset.nama || '-';
                    const instansi = checkedRadio.dataset.instansi || '-';
                    summaryEl.textContent = `${nomor} — ${nama} (${instansi})`;
                    summaryEl.classList.remove('text-muted');
                    summaryEl.classList.add('text-emerald', 'fw-semibold');
                } else {
                    summaryEl.textContent = 'Belum ada kontestan yang dipilih.';
                    summaryEl.classList.remove('text-emerald', 'fw-semibold');
                    summaryEl.classList.add('text-muted');
                }
            }

            if (submitBtn && form.dataset.voteSubmitting !== 'true') {
                submitBtn.disabled = checkedRadio === null;
            }
        }

        radios.forEach((radio) => {
            radio.addEventListener('change', syncSelectionState);
        });

        form.addEventListener('submit', (event) => {
            if (form.dataset.voteSubmitting === 'true') {
                event.preventDefault();
                return;
            }

            const selected = radios.find((radio) => radio.checked) || null;
            if (!selected) {
                event.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pilih Kontestan Terlebih Dahulu',
                        text: 'Silakan pilih satu kontestan sebelum mengirimkan suara Anda.',
                        confirmButtonColor: '#047857',
                        confirmButtonText: 'Mengerti'
                    });
                }
                return;
            }

            if (form.dataset.confirmed === 'true') {
                form.dataset.voteSubmitting = 'true';
                setSubmitLoading();
                return;
            }

            event.preventDefault();

            const kategori = selected.dataset.kategori || '';
            const nomor = selected.dataset.nomor || '-';
            const nama = selected.dataset.nama || '-';
            const instansi = selected.dataset.instansi || '-';

            if (typeof Swal === 'undefined') {
                const ok = window.confirm(
                    `Kirim suara kategori ${kategori} kepada ${nomor} — ${nama} (${instansi})?\n\nPerhatian: Suara bersifat permanen dan tidak dapat diubah.`
                );
                if (ok) {
                    form.dataset.confirmed = 'true';
                    form.dataset.voteSubmitting = 'true';
                    setSubmitLoading();
                    form.submit();
                }
                return;
            }

            const detailHtml = `
                <div class="text-start small border rounded-3 p-3 bg-light mb-3">
                    <div class="d-flex justify-content-between gap-2 mb-1">
                        <span class="text-muted">Kategori</span>
                        <strong>${escapeHtml(kategori)}</strong>
                    </div>
                    <div class="d-flex justify-content-between gap-2 mb-1">
                        <span class="text-muted">Nomor Peserta</span>
                        <strong>${escapeHtml(nomor)}</strong>
                    </div>
                    <div class="d-flex justify-content-between gap-2 mb-1">
                        <span class="text-muted">Nama Kontestan</span>
                        <strong class="text-end">${escapeHtml(nama)}</strong>
                    </div>
                    <div class="d-flex justify-content-between gap-2">
                        <span class="text-muted">Asal Instansi</span>
                        <span class="text-end">${escapeHtml(instansi)}</span>
                    </div>
                </div>
                <p class="small text-muted mb-0">
                    Pastikan pilihan Anda sudah tepat. Suara yang masuk <strong>bersifat permanen</strong> dan tidak dapat diubah atau dihapus pada periode ini.
                </p>
            `;

            Swal.fire({
                icon: 'question',
                title: 'Konfirmasi Kirim Suara',
                html: detailHtml,
                showCancelButton: true,
                confirmButtonColor: '#047857',
                cancelButtonColor: '#64748B',
                confirmButtonText: 'Ya, Kirim Suara',
                cancelButtonText: 'Batal',
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.dataset.voteSubmitting = 'true';
                    setSubmitLoading();
                    form.submit();
                }
            });
        });

        window.addEventListener('pageshow', () => {
            delete form.dataset.confirmed;
            delete form.dataset.voteSubmitting;
            if (submitBtn && submitBtn.dataset.originalHtml) {
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
            syncSelectionState();
        });

        syncSelectionState();
    });
})();

