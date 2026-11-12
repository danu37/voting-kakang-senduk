/**
 * JavaScript Global Aplikasi
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

(function () {
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

document.addEventListener('DOMContentLoaded', () => {
    // 1. Tampilkan notifikasi flash melalui SweetAlert2 jika tersedia
    const flashEl = document.getElementById('ks-flash-data');
    if (flashEl && typeof Swal !== 'undefined') {
        try {
            const flash = JSON.parse(flashEl.textContent || '{}');
            if (flash && flash.message) {
                const iconMap = {
                    success: 'success',
                    error: 'error',
                    danger: 'error',
                    warning: 'warning',
                    info: 'info'
                };
                Swal.fire({
                    icon: iconMap[flash.type] || 'info',
                    title: flash.title || 'Informasi',
                    text: flash.message,
                    confirmButtonColor: '#047857',
                    confirmButtonText: 'Tutup'
                });
            }
        } catch (err) {
            // Abaikan JSON tidak valid
        }
    }

    // 2. Toggle tampil/sembunyikan password
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-toggle-password');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';

            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
            btn.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });

    // 3. Loading state tombol saat submit form untuk mencegah klik ganda
    document.querySelectorAll('form[data-loading-form]').forEach((form) => {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn && !submitBtn.dataset.originalHtml) {
            submitBtn.dataset.originalHtml = submitBtn.innerHTML;
        }

        form.addEventListener('submit', () => {
            if (!form.checkValidity()) {
                return;
            }
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const loadingText = submitBtn.getAttribute('data-loading-text') || 'Memproses...';
                submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${escapeHtml(loadingText)}`;
            }
        });
    });

    // Pulihkan tombol loading jika pengguna kembali lewat riwayat browser (bfcache)
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-loading-form] button[type="submit"]').forEach((btn) => {
            if (btn.dataset.originalHtml) {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml;
            }
        });
    });

    // 4. Konfirmasi modal SweetAlert2 untuk aksi logout / penting
    document.querySelectorAll('form[data-confirm-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            if (typeof Swal === 'undefined') {
                return;
            }

            event.preventDefault();
            const title = form.getAttribute('data-confirm-title') || 'Konfirmasi Tindakan';
            const text = form.getAttribute('data-confirm-text') || 'Apakah Anda yakin ingin melanjutkan?';
            const confirmBtn = form.getAttribute('data-confirm-btn') || 'Ya, Lanjutkan';

            Swal.fire({
                icon: 'question',
                title: title,
                text: text,
                showCancelButton: true,
                confirmButtonColor: '#047857',
                cancelButtonColor: '#64748B',
                confirmButtonText: confirmBtn,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });
});
})();

