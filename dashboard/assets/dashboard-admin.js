(() => {
    const root = document.body;
    const csrf = root.dataset.csrf || '';
    const feedback = document.getElementById('admin-feedback');
    const tabs = [...document.querySelectorAll('[data-section]')];
    const views = [...document.querySelectorAll('[data-view]')];
    const labels = {
        overview: 'Vue générale', users: 'Utilisateurs', reports: 'Signalements', audit: 'Actions SQL',
        mail: 'Envoyer un mail', links: 'Liens d’accueil', commits: 'Commits GitHub', settings: 'Paramètres',
    };
    let currentSection = 'overview';

    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function setFeedback(message, error = false) {
        feedback.textContent = message;
        feedback.classList.toggle('is-error', error);
    }

    async function request(section, data = null, query = {}) {
        const options = { cache: 'no-store', headers: { Accept: 'application/json' } };
        const params = new URLSearchParams({ section, ...query });
        let url = `admin-api.php?${params}`;
        if (data) {
            data.set('csrf', csrf);
            options.method = 'POST';
            options.body = data;
            url = 'admin-api.php';
        }
        const response = await fetch(url, options);
        const result = await response.json();
        if (!response.ok || result.error) throw new Error(result.error || 'La requête a échoué.');
        return result.data ?? result;
    }

    function showSection(section) {
        currentSection = section;
        tabs.forEach((tab) => {
            const selected = tab.dataset.section === section;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        views.forEach((view) => { view.hidden = view.dataset.view !== section; });
        setFeedback(`${labels[section]} · chargement…`);
        loadSection(section).catch((error) => setFeedback(error.message, true));
    }

    function actionButton(text, action, id, danger = false) {
        const button = element('button', `admin-action${danger ? ' is-danger' : ''}`, text);
        button.type = 'button';
        button.addEventListener('click', () => runUserAction(action, id, text));
        return button;
    }

    async function loadOverview() {
        const data = await request('overview');
        const metrics = document.getElementById('overview-metrics');
        metrics.replaceChildren();
        [['Membres', data.users], ['Administrateurs', data.admins], ['En ligne', data.online], ['Signalements', data.reports]].forEach(([label, value], index) => {
            const item = element('article', `admin-metric${index === 0 ? ' admin-metric-featured' : ''}`);
            item.append(element('span', '', label), element('strong', '', Number(value).toLocaleString('fr-FR')));
            metrics.append(item);
        });
        const toggle = document.getElementById('maintenance-toggle');
        toggle.checked = Boolean(data.maintenance);
        document.getElementById('maintenance-label').textContent = toggle.checked ? 'Activé' : 'Désactivé';
        document.getElementById('admin-updated').textContent = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        setFeedback('Données à jour');
    }

    async function loadUsers() {
        const users = await request('users');
        const list = document.getElementById('users-list');
        list.replaceChildren();
        users.forEach((user) => {
            const isOnline = Number(user.is_online) === 1;
            const isAdmin = Number(user.admin) === 1;
            const managesSessions = Number(user.gerer_seances) === 1;
            const isBanned = Number(user.ban) === 1;
            const isEmailVerified = Number(user.email_verified) === 1;
            const isInMaintenance = Number(user.maintenance) === 1;
            const card = element('article', `admin-user-card${isBanned ? ' is-banned' : ''}`);
            card.dataset.userId = String(user.id);
            card.dataset.search = `${user.firstname} ${user.lastname} ${user.email}`.toLocaleLowerCase('fr');

            const identity = element('div', 'admin-user-identity');
            const avatarWrap = element('div', 'admin-user-avatar-wrap');
            const initials = `${String(user.firstname || '').slice(0, 1)}${String(user.lastname || '').slice(0, 1)}`.toLocaleUpperCase('fr');
            avatarWrap.append(element('span', 'admin-user-avatar', initials || '?'));
            const dot = element('span', `user-profile-card__status-dot ${isOnline ? 'is-online' : 'is-offline'}`);
            dot.setAttribute('role', 'img');
            dot.setAttribute('aria-label', isOnline ? 'Utilisateur en ligne' : 'Utilisateur hors ligne');
            avatarWrap.append(dot);

            const details = element('div', 'admin-user-details');
            details.append(element('strong', 'admin-user-name', `${user.firstname} ${user.lastname}`));
            details.append(element('span', 'admin-user-email', user.email));
            const presence = element('span', `admin-user-presence${isOnline ? ' is-online' : ' is-offline'}`, isOnline ? 'En ligne' : 'Hors ligne');
            presence.dataset.onlineLabel = '';
            details.append(presence);
            identity.append(avatarWrap, details);
            card.append(identity);

            const badges = element('div', 'admin-user-badges');
            badges.append(element('span', isAdmin ? 'admin-badge is-admin' : 'admin-badge', isAdmin ? 'Admin' : 'Membre'));
            if (managesSessions) badges.append(element('span', 'admin-badge is-coach', 'Gère les séances'));
            if (isBanned) badges.append(element('span', 'admin-badge is-unverified', 'Banni'));
            if (!isEmailVerified) badges.append(element('span', 'admin-badge is-unverified', 'E-mail à vérifier'));
            if (isInMaintenance) badges.append(element('span', 'admin-badge', 'Maintenance'));
            card.append(badges);

            const menu = element('details', 'admin-user-menu');
            menu.append(element('summary', '', 'Gérer le compte'));
            const actions = element('div', 'admin-user-actions');
            actions.append(actionButton(isAdmin ? 'Retirer admin' : 'Rendre admin', 'toggle_admin', user.id));
            actions.append(actionButton(managesSessions ? 'Retirer séances' : 'Droit séances', 'toggle_gerer_seances', user.id));
            actions.append(actionButton(isBanned ? 'Débannir' : 'Bannir', 'toggle_ban', user.id, !isBanned));
            if (!isEmailVerified) actions.append(actionButton('Valider e-mail', 'verify_email', user.id));
            actions.append(actionButton('Supprimer', 'delete_account', user.id, true));
            menu.append(actions);
            card.append(menu);
            list.append(card);
        });
        if (!users.length) list.append(element('p', 'admin-empty-block', 'Aucun membre enregistré.'));
        filterUsers();
        setFeedback(`${users.length} comptes chargés`);
    }

    function filterUsers() {
        const query = document.getElementById('user-search').value.trim().toLocaleLowerCase('fr');
        document.querySelectorAll('.admin-user-card').forEach((card) => { card.hidden = !card.dataset.search.includes(query); });
    }

    async function refreshOnlineStatuses() {
        if (currentSection !== 'users' || document.visibilityState !== 'visible') return;
        const onlineIds = await request('online');
        const online = new Set(onlineIds.map(String));
        document.querySelectorAll('.admin-user-card').forEach((card) => {
            const isOnline = online.has(card.dataset.userId);
            const dot = card.querySelector('.user-profile-card__status-dot');
            const label = card.querySelector('[data-online-label]');
            dot.classList.toggle('is-online', isOnline);
            dot.classList.toggle('is-offline', !isOnline);
            dot.setAttribute('aria-label', isOnline ? 'Utilisateur en ligne' : 'Utilisateur hors ligne');
            label.classList.toggle('is-online', isOnline);
            label.classList.toggle('is-offline', !isOnline);
            label.textContent = isOnline ? 'En ligne' : 'Hors ligne';
        });
    }

    async function runUserAction(action, id, label) {
        const destructive = ['delete_account', 'toggle_ban', 'toggle_admin'].includes(action);
        if (destructive && !window.confirm(`${label} pour ce compte ?`)) return;
        const data = new FormData();
        data.set('action', action);
        data.set('target_id', id);
        try {
            await request('', data);
            await loadUsers();
            setFeedback('Modification enregistrée');
        } catch (error) {
            setFeedback(error.message, true);
        }
    }

    async function loadReports() {
        const reports = await request('reports');
        const list = document.getElementById('reports-list');
        list.replaceChildren();
        document.getElementById('reports-count').textContent = `${reports.length} signalement${reports.length === 1 ? '' : 's'}`;
        reports.forEach((report) => {
            const item = element('article', 'admin-record');
            const head = element('header', 'admin-record-head');
            head.append(element('strong', '', report.email || 'Adresse inconnue'), element('time', '', formatDate(report.created_at)));
            const message = element('p', 'admin-record-message', report.message || '');
            const remove = element('button', 'admin-action is-danger', 'Supprimer');
            remove.type = 'button';
            remove.addEventListener('click', async () => {
                if (!window.confirm('Supprimer ce signalement ?')) return;
                const data = new FormData();
                data.set('action', 'delete_report');
                data.set('report_id', report.id);
                try { await request('', data); await loadReports(); } catch (error) { setFeedback(error.message, true); }
            });
            item.append(head, message, remove);
            list.append(item);
        });
        if (!reports.length) list.append(element('p', 'admin-empty-block', 'Aucun signalement enregistré.'));
        setFeedback('Signalements à jour');
    }

    async function loadAudit() {
        const logs = await request('audit');
        const body = document.getElementById('audit-list');
        body.replaceChildren();
        logs.forEach((log) => {
            const item = element('article', 'admin-record admin-audit-item');
            const head = element('div', 'admin-audit-head');
            head.append(element('strong', '', log.query_type || 'Action SQL'), element('time', '', formatDate(log.created_at)));
            const summary = element('div', 'admin-audit-summary');
            summary.append(element('span', 'admin-badge', log.actor_name || 'Visiteur'));
            summary.append(element('span', 'admin-badge', log.table_name || 'unknown'));
            summary.append(element('span', `admin-badge${log.status === 'ok' ? ' is-admin' : ' is-unverified'}`, log.status || 'inconnu'));
            const statement = document.createElement('details');
            statement.className = 'admin-audit-query';
            statement.append(element('summary', '', 'Afficher la requête'));
            statement.append(element('pre', '', log.statement || ''));
            item.append(head, summary, statement);
            body.append(item);
        });
        if (!logs.length) body.append(element('p', 'admin-empty-block', 'Aucune action journalisée.'));
        setFeedback(`${logs.length} lignes du journal`);
    }

    async function loadLinks() {
        const links = await request('links');
        const form = document.getElementById('links-form');
        form.replaceChildren();
        Object.entries(links).forEach(([key, link]) => {
            const label = element('label', 'admin-form-wide', link.title);
            const input = element('input');
            input.type = 'url';
            input.name = key;
            input.value = link.url;
            input.required = true;
            label.append(input);
            form.append(label);
        });
        form.append(submitButton('Enregistrer les liens'));
        setFeedback('Liens chargés');
    }

    async function loadSettings() {
        const settings = await request('settings');
        const form = document.getElementById('settings-form');
        form.elements.session_timeout_seconds.value = settings.session_timeout_seconds;
        form.elements.session_modal_threshold_seconds.value = settings.session_modal_threshold_seconds;
        setFeedback('Paramètres chargés');
    }

    async function loadCommits() {
        const commits = await request('commits');
        const list = document.getElementById('commits-list');
        list.replaceChildren();
        commits.forEach((commit) => {
            const item = element('article', 'admin-record admin-commit');
            const head = element('header', 'admin-record-head');
            const title = element('strong', '', (commit.message || 'Commit sans message').split('\n')[0]);
            const detailToggle = element('button', 'admin-action', 'Fichiers modifiés');
            detailToggle.type = 'button';
            const details = element('div', 'admin-commit-details');
            details.hidden = true;
            let detailLoaded = false;
            detailToggle.addEventListener('click', async () => {
                details.hidden = !details.hidden;
                if (details.hidden || detailLoaded) return;
                detailToggle.disabled = true;
                try {
                    const detail = await request('commit_detail', null, { sha: commit.id });
                    detail.files.forEach((file) => {
                        details.append(element('p', '', `${file.status} · ${file.filename} · +${file.additions} / -${file.deletions}`));
                    });
                    if (!detail.files.length) details.append(element('p', '', 'Aucun fichier détaillé.'));
                    detailLoaded = true;
                } catch (error) {
                    details.append(element('p', 'is-error', error.message));
                } finally {
                    details.hidden = false;
                    detailToggle.disabled = false;
                }
            });
            if (commit.url && /^https:\/\//i.test(commit.url)) {
                const link = element('a', 'admin-action', 'Ouvrir');
                link.href = commit.url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                head.append(title, detailToggle, link);
            } else head.append(title, detailToggle);
            item.append(head, element('p', 'admin-record-meta', `${commit.author || 'Auteur inconnu'} · ${formatDate(commit.timestamp)} · ${commit.id || ''}`), details);
            list.append(item);
        });
        if (!commits.length) list.append(element('p', 'admin-empty-block', 'Aucun commit synchronisé.'));
        setFeedback(`${commits.length} commits chargés`);
    }

    function formatDate(value) {
        if (!value) return 'Date inconnue';
        const date = new Date(value.includes('T') ? value : `${value.replace(' ', 'T')}Z`);
        return Number.isNaN(date.valueOf()) ? value : date.toLocaleString('fr-FR');
    }

    function submitButton(text) {
        const button = element('button', 'button button-dark', text);
        button.type = 'submit';
        return button;
    }

    async function submitForm(event, action) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        data.set('action', action);
        try {
            await request('', data);
            setFeedback('Modifications enregistrées');
            if (action === 'save_settings') await loadSettings();
            if (action === 'save_links') await loadLinks();
        } catch (error) { setFeedback(error.message, true); }
    }

    async function loadSection(section) {
        if (section === 'overview') return loadOverview();
        if (section === 'users') return loadUsers();
        if (section === 'reports') return loadReports();
        if (section === 'audit') return loadAudit();
        if (section === 'links') return loadLinks();
        if (section === 'commits') return loadCommits();
        if (section === 'settings') return loadSettings();
        if (section === 'mail') {
            const data = await request('mail');
            document.getElementById('recipient-count').textContent = `${data.recipients} destinataires actifs`;
            setFeedback('Prêt à rédiger');
        }
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => showSection(tab.dataset.section));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const offset = event.key === 'ArrowRight' ? 1 : -1;
            tabs[(index + offset + tabs.length) % tabs.length].focus();
            tabs[(index + offset + tabs.length) % tabs.length].click();
        });
    });
    document.getElementById('user-search').addEventListener('input', filterUsers);
    document.getElementById('maintenance-toggle').addEventListener('change', async (event) => {
        const toggle = event.currentTarget;
        const enabled = toggle.checked;
        const data = new FormData();
        data.set('action', 'set_maintenance');
        data.set('enabled', enabled ? '1' : '0');
        toggle.disabled = true;
        try {
            await request('', data);
            document.getElementById('maintenance-label').textContent = enabled ? 'Activé' : 'Désactivé';
            setFeedback('Mode maintenance mis à jour');
        } catch (error) {
            toggle.checked = !enabled;
            setFeedback(error.message, true);
        } finally {
            toggle.disabled = false;
        }
    });
    document.getElementById('links-form').addEventListener('submit', (event) => submitForm(event, 'save_links'));
    document.getElementById('settings-form').addEventListener('submit', (event) => submitForm(event, 'save_settings'));
    document.getElementById('mail-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const mailForm = event.currentTarget;
        const editor = document.getElementById('mail-editor');
        if (!editor.textContent.trim()) {
            setFeedback('Rédige un message avant l’envoi.', true);
            editor.focus();
            return;
        }
        if (!window.confirm('Envoyer ce message à tous les membres ayant accepté les e-mails ?')) return;
        document.getElementById('message-html').value = editor.innerHTML;
        const data = new FormData(mailForm);
        data.set('action', 'send_bulk_mail');
        try {
            const result = await request('', data);
            mailForm.reset();
            editor.replaceChildren();
            setFeedback(`Message envoyé à ${result.sent} membre(s), ${result.failed} échec(s)`);
        } catch (error) { setFeedback(error.message, true); }
    });
    document.querySelectorAll('[data-editor-command]').forEach((button) => {
        button.addEventListener('click', () => {
            const command = button.dataset.editorCommand;
            const editor = document.getElementById('mail-editor');
            editor.focus();
            if (command === 'createLink') {
                const url = window.prompt('Adresse du lien (https://…)');
                if (!url) return;
                document.execCommand(command, false, url);
            } else {
                document.execCommand(command, false);
            }
        });
    });
    document.getElementById('refresh-commits').addEventListener('click', async () => {
        const data = new FormData();
        data.set('action', 'refresh_commits');
        try {
            await request('', data);
            await loadCommits();
        } catch (error) { setFeedback(error.message, true); }
    });
    showSection(currentSection);
    window.setInterval(() => {
        if (currentSection === 'audit') loadAudit().catch((error) => setFeedback(error.message, true));
    }, 10000);
    window.setInterval(() => {
        refreshOnlineStatuses().catch(() => {});
    }, 15000);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refreshOnlineStatuses().catch(() => {});
    });
})();