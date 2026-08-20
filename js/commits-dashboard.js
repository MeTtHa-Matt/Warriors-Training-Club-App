document.addEventListener('DOMContentLoaded', function () {
    console.log('commits-dashboard initialized');
    const list = document.getElementById('commits-list');
    const modal = new bootstrap.Modal(document.getElementById('commitModal'));
    const modalTitle = document.getElementById('commitModalLabel');
    const modalBody = document.getElementById('commitModalBody');

    let lastSeenId = null;
    async function fetchCommits(force = false, updateOnly = false) {
        // when updateOnly is true, don't replace UI unless there's a new commit
        try {
            const url = 'api/commits.php' + (force ? '?force=1' : '');
            const res = await fetch(url, {cache: 'no-store'});
            if (!res.ok) throw new Error('Network');
            const data = await res.json();
            if (!Array.isArray(data)) return;
            // detect newest commit id (array is newest-first from API)
            const newest = data[0] && (data[0].id || data[0].sha || null);
            if (updateOnly) {
                if (newest && newest !== lastSeenId) {
                    renderCommits(data);
                    lastSeenId = newest;
                } else {
                    // no new commits, do nothing
                    console.log('no new commits');
                }
            } else {
                renderCommits(data);
                lastSeenId = newest;
            }
        } catch (err) {
            if (!updateOnly) list.innerHTML = '<div class="text-danger py-4">Erreur de chargement</div>';
            console.error('fetchCommits error', err);
        }
    }

    function renderCommits(items) {
        if (!items || items.length === 0) {
            list.innerHTML = '<p class="mb-0 text-white">Aucun commit enregistré pour le moment.</p>';
            return;
        }
        // ensure newest commits first (sort by timestamp/date desc)
        const sorted = items.slice().sort((a, b) => {
            const da = new Date(a.timestamp || a.date || 0).getTime() || 0;
            const db = new Date(b.timestamp || b.date || 0).getTime() || 0;
            return db - da;
        });
        const rows = [];
        sorted.slice(0, 40).forEach(c => {
            const date = new Date(c.timestamp || c.timestamp || c.date || c.timestamp || '');
            const when = isNaN(date) ? '' : date.toLocaleString();
            const shaShort = (c.id || '').substr(0, 12);
            const card = document.createElement('div');
            card.className = 'col-12 col-md-6 col-lg-4';
            card.innerHTML = `
                <div class="card bg-dark text-white mb-3">
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(c.message || '—')}</h5>
                        <h6 class="card-subtitle mb-2 text-muted">${escapeHtml(c.author || '')} — <code>${escapeHtml(shaShort)}</code></h6>
                        <p class="card-text small text-white-50">${escapeHtml(when)}</p>
                        <div class="d-flex gap-2">
                            ${c.url ? `<a class="btn btn-sm btn-wtc-gold" href="${escapeAttr(c.url)}" target="_blank" rel="noopener">Voir</a>` : ''}
                            <button class="btn btn-sm btn-outline-light btn-files" data-sha="${escapeAttr(c.id)}">Fichiers</button>
                        </div>
                    </div>
                </div>
            `;
            rows.push(card);
        });
        list.innerHTML = '';
        rows.forEach(r => list.appendChild(r));

        // attach handlers
        document.querySelectorAll('.btn-files').forEach(btn => {
            btn.addEventListener('click', async function () {
                const sha = this.dataset.sha;
                modalTitle.textContent = 'Chargement du commit ' + (sha ? sha.substr(0, 12) : '');
                modalBody.innerHTML = '<div class="py-4 text-center text-muted">Chargement...</div>';
                modal.show();
                try {
                    const res = await fetch('api/commit.php?sha=' + encodeURIComponent(sha));
                    if (!res.ok) throw new Error('Network');
                    const data = await res.json();
                    renderCommitDetails(data);
                } catch (err) {
                    modalBody.innerHTML = '<div class="text-danger py-4">Erreur lors du chargement du commit.</div>';
                }
            });
        });
    }

    function renderCommitDetails(data) {
        if (data.error) {
            modalBody.innerHTML = `<div class="text-danger">${escapeHtml(data.error)}</div>`;
            return;
        }
        let html = `<p><strong>${escapeHtml(data.message || '')}</strong></p>`;
        html += `<p class="small text-muted">${escapeHtml(data.author || '')} — ${escapeHtml(data.date || '')}</p>`;
        if (data.html_url) {
            html += `<p><a href="${escapeAttr(data.html_url)}" target="_blank" rel="noopener" class="btn btn-sm btn-wtc-gold">Ouvrir sur GitHub</a></p>`;
        }
        if (Array.isArray(data.files) && data.files.length) {
            html += '<div class="list-group">';
            data.files.forEach(f => {
                html += `<div class="list-group-item bg-dark text-white border-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold">${escapeHtml(f.filename || '')}</div>
                            <div class="small text-white-50">${escapeHtml(f.status || '')} — ${escapeHtml((f.changes||0) + ' changements')}</div>
                        </div>
                    </div>
                    ${f.patch ? `<pre class="mt-2 small text-white-50" style="white-space:pre-wrap;max-height:200px;overflow:auto;background:#0b0b0b;padding:8px;border-radius:4px">${escapeHtml(f.patch)}</pre>` : ''}
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<p class="text-white-50">Aucun fichier modifié listé.</p>';
        }

        modalBody.innerHTML = html;
    }

    function escapeHtml(s){
        if (s == null) return '';
        return String(s).replace(/[&"'<>]/g, function (c) {
            return {'&':'&amp;','"':'&quot;','\'':'&#39;','<':'&lt;','>':'&gt;'}[c];
        });
    }
    function escapeAttr(s){
        return escapeHtml(s).replace(/"/g, '%22');
    }


    // initial load: force fresh fetch from GitHub and render
    fetchCommits(true, false);
    // Simple polling: check every 15 seconds but only update UI if new commits appear
    setInterval(() => {
        fetchCommits(true, true);
    }, 15000);
});
