/**
 * Polling AJAX Real-Time Halaman Klasemen Publik
 * Voting Pemilihan Kakang Senduk Ponorogo
 */

(function () {
    'use strict';

    const POLL_INTERVAL_MS = 15000;
    const intFormatter = new Intl.NumberFormat('id-ID');
    const pctFormatter = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    function createEl(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }

    function formatVotes(n) {
        return intFormatter.format(Number(n) || 0);
    }

    function formatPct(p) {
        return pctFormatter.format(Number(p) || 0) + '%';
    }

    function renderPodium(container, kategori, podiumList) {
        if (!container) return;
        container.replaceChildren();

        if (!Array.isArray(podiumList) || podiumList.length === 0) {
            const alertDiv = createEl('div', 'alert alert-light border small text-muted mb-4');
            const icon = createEl('i', 'bi bi-info-circle me-1');
            alertDiv.appendChild(icon);
            alertDiv.appendChild(
                document.createTextNode(
                    `Belum ada suara yang masuk untuk kategori ${kategori} pada periode ini. Podium akan tampil setelah suara masuk.`
                )
            );
            container.appendChild(alertDiv);
            return;
        }

        const rowDiv = createEl('div', 'row g-3 mb-4 align-items-end justify-content-center');
        const orderMap = { 1: 'order-md-2', 2: 'order-md-1', 3: 'order-md-3' };
        const medalMap = { 1: 'Juara 1', 2: 'Juara 2', 3: 'Juara 3' };

        podiumList.forEach((row) => {
            const rank = Number(row.ranking) || 1;
            const orderCls = orderMap[rank] || '';
            const col = createEl('div', `col-12 col-md-4 ${orderCls}`.trim());

            const slot = createEl('div', `ks-podium-slot ${rank === 1 ? 'ks-podium-rank-1' : ''}`.trim());

            const badge = createEl(
                'span',
                `badge rounded-pill ${rank === 1 ? 'text-bg-warning' : 'text-bg-secondary'} mb-2 px-3 py-1`
            );
            badge.appendChild(createEl('i', 'bi bi-trophy-fill me-1'));
            badge.appendChild(document.createTextNode(medalMap[rank] || `Peringkat ${rank}`));
            slot.appendChild(badge);

            const img = createEl('img', 'ks-podium-avatar');
            img.src = row.foto_url || '';
            img.alt = `Foto ${row.nama || ''}`;
            img.loading = 'lazy';
            img.decoding = 'async';
            slot.appendChild(img);

            slot.appendChild(createEl('div', 'small text-muted fw-semibold', row.nomor_peserta || '-'));
            slot.appendChild(createEl('h3', 'h6 fw-bold mb-1', row.nama || '-'));
            slot.appendChild(createEl('div', 'small text-muted mb-1', row.asal_instansi || '-'));

            const score = createEl(
                'div',
                'small text-emerald fw-bold',
                `${formatVotes(row.jumlah_suara)} suara (${formatPct(row.persentase)})`
            );
            slot.appendChild(score);

            col.appendChild(slot);
            rowDiv.appendChild(col);
        });

        container.appendChild(rowDiv);
    }

    function renderTable(container, kategori, items) {
        if (!container) return;
        container.replaceChildren();

        if (!Array.isArray(items) || items.length === 0) {
            const emptyDiv = createEl('div', 'ks-empty-state');
            emptyDiv.appendChild(createEl('p', 'small text-muted mb-0', `Belum ada kontestan aktif pada kategori ${kategori}.`));
            container.appendChild(emptyDiv);
            return;
        }

        const card = createEl('div', 'ks-card overflow-hidden');
        const responsive = createEl('div', 'table-responsive');
        const table = createEl('table', 'table align-middle mb-0');

        const thead = document.createElement('thead');
        thead.className = 'table-light small text-uppercase';
        const trHead = document.createElement('tr');
        [
            { text: 'Rank', style: 'width: 72px;' },
            { text: 'Kontestan' },
            { text: 'No. Peserta' },
            { text: 'Asal Instansi' },
            { text: 'Jumlah Suara', cls: 'text-end' },
            { text: 'Persentase', style: 'min-width: 170px;' }
        ].forEach((colSpec) => {
            const th = document.createElement('th');
            if (colSpec.cls) th.className = colSpec.cls;
            if (colSpec.style) th.setAttribute('style', colSpec.style);
            th.textContent = colSpec.text;
            trHead.appendChild(th);
        });
        thead.appendChild(trHead);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        items.forEach((row) => {
            const tr = document.createElement('tr');

            const tdRank = createEl('td', 'fw-bold');
            tdRank.appendChild(document.createTextNode(`#${Number(row.ranking) || 0}`));
            if (row.is_seri) {
                const tieBadge = createEl('span', 'badge text-bg-light border text-muted ms-1 fw-normal', 'Seri');
                tieBadge.title = 'Urutan tie-breaker berdasarkan nomor peserta';
                tdRank.appendChild(tieBadge);
            }
            tr.appendChild(tdRank);

            const tdName = document.createElement('td');
            const flexBox = createEl('div', 'd-flex align-items-center gap-3');
            const img = createEl('img', 'ks-avatar-thumb');
            img.src = row.foto_url || '';
            img.alt = `Foto ${row.nama || ''}`;
            img.loading = 'lazy';
            img.decoding = 'async';
            flexBox.appendChild(img);

            const link = createEl('a', 'fw-semibold text-dark text-decoration-none', row.nama || '-');
            link.href = row.detail_url || '#';
            flexBox.appendChild(link);
            tdName.appendChild(flexBox);
            tr.appendChild(tdName);

            const tdNo = document.createElement('td');
            tdNo.appendChild(createEl('span', 'badge text-bg-light border', row.nomor_peserta || '-'));
            tr.appendChild(tdNo);

            const tdInst = createEl('td', 'small text-muted', row.asal_instansi || '-');
            tr.appendChild(tdInst);

            const tdVotes = createEl('td', 'text-end fw-semibold', formatVotes(row.jumlah_suara));
            tr.appendChild(tdVotes);

            const tdPct = document.createElement('td');
            const pctVal = Math.max(0, Math.min(100, Number(row.persentase) || 0));
            const pctWrap = createEl('div', 'd-flex align-items-center gap-2');
            const prog = createEl('div', 'progress ks-progress flex-grow-1');
            prog.setAttribute('role', 'progressbar');
            prog.setAttribute('aria-valuenow', String(pctVal));
            prog.setAttribute('aria-valuemin', '0');
            prog.setAttribute('aria-valuemax', '100');

            const bar = createEl('div', 'progress-bar ks-progress-bar');
            bar.style.width = `${pctVal}%`;
            prog.appendChild(bar);

            pctWrap.appendChild(prog);
            pctWrap.appendChild(createEl('span', 'small fw-semibold text-nowrap', formatPct(row.persentase)));
            tdPct.appendChild(pctWrap);
            tr.appendChild(tdPct);

            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        responsive.appendChild(table);
        card.appendChild(responsive);
        container.appendChild(card);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('ks-klasemen-root');
        if (!root) return;

        const pollUrl = root.getAttribute('data-poll-url');
        if (!pollUrl) return;

        const refreshBtn = document.getElementById('ks-btn-refresh-klasemen');
        const originalBtnHtml = refreshBtn ? refreshBtn.innerHTML : '';
        let inFlight = false;

        async function refreshLeaderboard(isManual) {
            if (inFlight) return;
            inFlight = true;

            if (isManual && refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyegarkan...';
            }

            try {
                const sep = pollUrl.includes('?') ? '&' : '?';
                const res = await fetch(`${pollUrl}${sep}_t=${Date.now()}`, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                });
                if (!res.ok) return;

                const payload = await res.json();
                if (!payload || !payload.success) return;

                const statKakang = document.getElementById('ks-stat-kakang');
                const statSenduk = document.getElementById('ks-stat-senduk');
                const statTotal = document.getElementById('ks-stat-total');
                const updatedEl = document.getElementById('ks-klasemen-updated-at');
                const totalLabelKakang = document.getElementById('ks-total-label-kakang');
                const totalLabelSenduk = document.getElementById('ks-total-label-senduk');

                if (statKakang) statKakang.textContent = formatVotes(payload.total_kakang);
                if (statSenduk) statSenduk.textContent = formatVotes(payload.total_senduk);
                if (statTotal) statTotal.textContent = formatVotes(payload.total_semua);
                if (updatedEl) updatedEl.textContent = payload.waktu_pembaruan_detik || payload.waktu_pembaruan || '-';
                if (totalLabelKakang) totalLabelKakang.textContent = `${formatVotes(payload.total_kakang)} suara`;
                if (totalLabelSenduk) totalLabelSenduk.textContent = `${formatVotes(payload.total_senduk)} suara`;

                if (payload.kakang) {
                    renderPodium(document.getElementById('ks-podium-container-kakang'), 'Kakang', payload.kakang.podium);
                    renderTable(document.getElementById('ks-table-container-kakang'), 'Kakang', payload.kakang.items);
                }
                if (payload.senduk) {
                    renderPodium(document.getElementById('ks-podium-container-senduk'), 'Senduk', payload.senduk.podium);
                    renderTable(document.getElementById('ks-table-container-senduk'), 'Senduk', payload.senduk.items);
                }
            } catch (err) {
                // Abaikan gangguan jaringan sementara agar polling berikutnya tetap berjalan
            } finally {
                inFlight = false;
                if (refreshBtn && originalBtnHtml) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = originalBtnHtml;
                }
            }
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => refreshLeaderboard(true));
        }

        setInterval(() => {
            if (!document.hidden) {
                refreshLeaderboard(false);
            }
        }, POLL_INTERVAL_MS);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                refreshLeaderboard(false);
            }
        });
    });
})();

