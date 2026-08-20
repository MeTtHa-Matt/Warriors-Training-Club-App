document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('commits-list');
    const btnRefresh = document.getElementById('commits-refresh');
    const modal = new bootstrap.Modal(document.getElementById('commitModal'));
    const modalTitle = document.getElementById('commitModalLabel');
    const modalBody = document.getElementById('commitModalBody');

    async function fetchCommits() {
        list.innerHTML = '<div class="text-center text-muted py-4">Chargement...</div>';
        try {
            const res = await fetch('api/commits.php');
            if (!res.ok) throw new Error('Network');
            const data = await res.json();
            renderCommits(data);
        } catch (err) {
            list.innerHTML = '<div class="text-danger py-4">Erreur de chargement</div>';
        }
    }

    function renderCommits(items) {
        if (!items || items.length === 0) {
            list.innerHTML = '<p class="mb-0 text-white">Aucun commit enregistré pour le moment.</p>';
            return;
        }
        const rows = [];
        items.slice(0, 40).forEach(c => {
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

    btnRefresh.addEventListener('click', function () {
        fetchCommits();
    });

    // initial load
    fetchCommits();
});
