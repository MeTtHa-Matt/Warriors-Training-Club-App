const numberFormat = new Intl.NumberFormat('fr-FR');
const dateFormat = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
const chartColor = '#d8b45c';
const gridColor = '#34332e';
const dashboardRefreshInterval = 120_000;
let trafficPoints = [];
let periodDays = 28;
let chartMetric = 'views';
let dashboardRequestInFlight = false;
let refreshQueued = false;
let lastDashboardRequestAt = 0;

function analyticsMessage(status) {
    if (status === 'database_unavailable') return 'Connexion MySQL indisponible dans le dashboard.';
    if (status === 'analytics_unavailable') return 'Table analytics_events absente ou droit SELECT manquant.';
    return 'Statistiques indisponibles pour le moment.';
}

document.querySelector('#today-date').textContent = dateFormat.format(new Date());

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
}

function formatInteger(value) {
    return numberFormat.format(Number.isFinite(Number(value)) ? Number(value) : 0);
}

function drawChart(points, emptyMessage = 'Aucune visite enregistrée sur cette période.') {
    const canvas = document.getElementById('traffic-chart');
    const empty = document.getElementById('chart-empty');
    const context = canvas.getContext('2d');
    const bounds = canvas.getBoundingClientRect();
    const ratio = Math.max(1, window.devicePixelRatio || 1);

    canvas.width = Math.round(bounds.width * ratio);
    canvas.height = Math.round(bounds.height * ratio);
    context.scale(ratio, ratio);
    context.clearRect(0, 0, bounds.width, bounds.height);

    if (!Array.isArray(points) || points.length === 0) {
        empty.textContent = emptyMessage;
        empty.hidden = false;
        return;
    }

    empty.hidden = true;
    const values = points.map((point) => Number(point[chartMetric]) || 0);
    const maximum = Math.max(1, ...values);
    const left = 34;
    const right = 8;
    const top = 15;
    const bottom = 25;
    const chartWidth = Math.max(1, bounds.width - left - right);
    const chartHeight = Math.max(1, bounds.height - top - bottom);
    const guideValues = [maximum, Math.round(maximum / 2), 0];

    context.font = '9px Trebuchet MS, sans-serif';
    context.textBaseline = 'middle';
    guideValues.forEach((value, index) => {
        const y = top + chartHeight * index / 2;
        context.strokeStyle = gridColor;
        context.lineWidth = 1;
        context.beginPath();
        context.moveTo(left, y);
        context.lineTo(bounds.width - right, y);
        context.stroke();
        context.fillStyle = '#99958a';
        context.textAlign = 'right';
        context.fillText(formatInteger(value), left - 7, y);
    });

    const coordinates = values.map((value, index) => ({
        x: left + (values.length === 1 ? chartWidth / 2 : chartWidth * index / (values.length - 1)),
        y: top + chartHeight - chartHeight * value / maximum,
    }));

    if (coordinates.length > 0) {
        context.beginPath();
        context.moveTo(coordinates[0].x, top + chartHeight);
        coordinates.forEach((point) => context.lineTo(point.x, point.y));
        context.lineTo(coordinates.at(-1).x, top + chartHeight);
        context.closePath();
        context.fillStyle = 'rgba(216, 180, 92, 0.12)';
        context.fill();

        context.beginPath();
        coordinates.forEach((point, index) => {
            if (index === 0) context.moveTo(point.x, point.y);
            else context.lineTo(point.x, point.y);
        });
        context.strokeStyle = chartColor;
        context.lineWidth = 2;
        context.lineJoin = 'round';
        context.lineCap = 'round';
        context.stroke();
    }

    [0, Math.floor((points.length - 1) / 2), points.length - 1].forEach((index) => {
        const rawDate = String(points[index]?.date || '');
        const date = rawDate.length === 8
            ? new Date(`${rawDate.slice(0, 4)}-${rawDate.slice(4, 6)}-${rawDate.slice(6, 8)}T12:00:00`)
            : null;
        if (!date || Number.isNaN(date.getTime())) return;
        const x = coordinates[index].x;
        context.fillStyle = '#99958a';
        context.textAlign = index === 0 ? 'left' : index === points.length - 1 ? 'right' : 'center';
        context.fillText(new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(date), x, bounds.height - 7);
    });
}

function renderPages(pages, emptyMessage = 'Aucune page consultée sur cette période.') {
    const body = document.getElementById('top-pages');
    body.replaceChildren();
    if (!Array.isArray(pages) || pages.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 2;
        cell.className = 'table-placeholder';
        cell.textContent = emptyMessage;
        row.append(cell);
        body.append(row);
        return;
    }

    pages.forEach((page) => {
        const row = document.createElement('tr');
        const title = document.createElement('td');
        const views = document.createElement('td');
        title.textContent = page.path || 'Page sans titre';
        title.title = page.path || '';
        views.textContent = formatInteger(page.views);
        row.append(title, views);
        body.append(row);
    });
}

function renderRows(id, items, labelFor, countFor, emptyMessage) {
    const body = document.getElementById(id);
    body.replaceChildren();
    if (!Array.isArray(items) || items.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 2;
        cell.className = 'table-placeholder';
        cell.textContent = emptyMessage;
        row.append(cell);
        body.append(row);
        return;
    }
    items.forEach((item) => {
        const row = document.createElement('tr');
        const label = document.createElement('td');
        const count = document.createElement('td');
        label.textContent = labelFor(item);
        const countValue = countFor(item);
        count.textContent = typeof countValue === 'number' ? formatInteger(countValue) : String(countValue);
        row.append(label, count);
        body.append(row);
    });
}

function selectWarriorsSite() {
    document.getElementById('sites-screen').hidden = true;
    document.getElementById('site-detail').hidden = false;
    if (trafficPoints.length > 0) drawChart(trafficPoints);
}

async function loadDashboard({ quiet = false, queueIfBusy = false } = {}) {
    if (dashboardRequestInFlight) {
        if (queueIfBusy) refreshQueued = true;
        return;
    }
    if (document.visibilityState !== 'visible') return;

    dashboardRequestInFlight = true;
    lastDashboardRequestAt = Date.now();
    const requestedPeriodDays = periodDays;
    refreshQueued = false;
    if (!quiet) setText('data-status', 'Mise à jour des données…');

    try {
        const response = await fetch(`api.php?period=${requestedPeriodDays}`, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (response.status === 401) {
            window.location.assign('index.php');
            return;
        }
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const payload = await response.json();
        const analytics = payload.analytics;
        if (!refreshQueued) periodDays = Number(payload.periodDays) || requestedPeriodDays;
        document.querySelectorAll('[data-period]').forEach((control) => {
            control.setAttribute('aria-pressed', String(Number(control.dataset.period) === periodDays));
        });
        document.querySelectorAll('[data-period-label]').forEach((label) => {
            label.textContent = `${periodDays} JOURS`;
        });
        document.querySelectorAll('[data-period-note]').forEach((note) => {
            note.textContent = note.dataset.periodNote === 'average'
                ? `profondeur moyenne · ${periodDays} j`
                : `sur les ${periodDays} derniers jours`;
        });
        const site = payload.site;
        const analyticsUnavailable = analyticsMessage(payload.analyticsStatus);
        const connected = Boolean(payload.sites?.find((item) => item.id === 'warriors')?.connected);
        const siteButton = document.getElementById('warriors-site');
        siteButton.disabled = false;
        setText('warriors-status', connected ? 'CONNECTÉ' : 'INDISPONIBLE');
        setText('sites-feedback', connected ? '1 site connecté' : analyticsUnavailable);

        if (analytics) {
            trafficPoints = Array.isArray(analytics.daily) ? analytics.daily : [];
            setText('sessions', formatInteger(analytics.totals.sessions));
            setText('page-views', formatInteger(analytics.totals.pageViews));
            setText('pages-per-session', Number(analytics.totals.pagesPerSession || 0).toLocaleString('fr-FR', { maximumFractionDigits: 1 }));
            setText('click-count', formatInteger(analytics.totals.clickCount));
            setText('engagement', `${(Number(analytics.totals.engagementRate) * 100).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} %`);
            setText('form-submissions', formatInteger(analytics.totals.formSubmissions));
            setText('outbound-clicks', formatInteger(analytics.totals.outboundClicks));
            setText('bounce-rate', `${(Number(analytics.totals.bounceRate) * 100).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} %`);
            setText('event-total', `Événements : ${formatInteger(analytics.totals.eventCount)}`);
            drawChart(analytics.daily);
            setText('chart-metric-label', chartMetric === 'sessions' ? 'Sessions distinctes' : 'Vues de page');
            renderPages(analytics.pages);
            renderRows('top-clicks', analytics.clicks, (item) => {
                const target = item.label || [item.host, item.path].filter(Boolean).join('') || (item.target === 'button' ? 'Bouton sans libellé' : 'Lien');
                return `${item.type === 'download' ? 'Téléchargement' : item.target === 'button' ? 'Bouton' : 'Lien'} · ${target}`;
            }, (item) => item.clicks, 'Aucun clic enregistré.');
            renderRows('top-sources', analytics.sources, (item) => item.source, (item) => item.visits, 'Aucune source externe.');
            renderRows('top-forms', analytics.forms, (item) => item.label, (item) => item.submissions, 'Aucun formulaire envoyé.');
            renderRows('device-breakdown', analytics.devices, (item) => ({ desktop: 'Ordinateur', mobile: 'Mobile', tablet: 'Tablette' }[item.name] || item.name), (item) => item.visitors, 'Aucune donnée appareil.');
            renderRows('browser-breakdown', analytics.browsers, (item) => item.name, (item) => item.visitors, 'Aucune donnée navigateur.');
            renderRows('os-breakdown', analytics.operatingSystems, (item) => ({ android: 'Android', ios: 'iOS', windows: 'Windows', macos: 'macOS', linux: 'Linux', chromeos: 'ChromeOS', other: 'Autre' }[item.name] || item.name), (item) => item.sessions, 'Aucune donnée système.');
            renderRows('viewport-breakdown', analytics.viewports, (item) => ({ small: 'Petit écran', medium: 'Écran moyen', large: 'Grand écran', unknown: 'Inconnu' }[item.name] || item.name), (item) => item.sessions, 'Aucune donnée d’affichage.');
            renderRows('scroll-breakdown', analytics.scroll, (item) => `${item.depth} % parcourus`, (item) => item.sessions, 'Aucune profondeur mesurée.');
            renderRows('performance-breakdown', analytics.performance, (item) => ({ page_load_ms: 'Chargement complet', fcp_ms: 'Premier affichage (FCP)', lcp_ms: 'Contenu principal (LCP)', inp_ms: 'Réactivité (INP)', cls: 'Stabilité visuelle (CLS)' }[item.name] || item.name), (item) => {
                const average = Number(item.average || 0).toLocaleString('fr-FR', { maximumFractionDigits: 1 });
                return `${average}${item.name === 'cls' ? '' : ' ms'} · ${formatInteger(item.samples)} mesures`;
            }, 'Aucune mesure de performance.');
            renderRows('hour-breakdown', analytics.hourly, (item) => `${String(item.hour).padStart(2, '0')} h – ${String((Number(item.hour) + 1) % 24).padStart(2, '0')} h`, (item) => item.visits, 'Aucune heure enregistrée.');
        } else {
            trafficPoints = [];
            setText('sessions', '—');
            setText('page-views', '—');
            setText('pages-per-session', '—');
            setText('click-count', '—');
            setText('engagement', '—');
            setText('form-submissions', '—');
            setText('outbound-clicks', '—');
            setText('bounce-rate', '—');
            setText('event-total', 'Événements : —');
            drawChart([], analyticsUnavailable);
            renderPages([], analyticsUnavailable);
            renderRows('top-clicks', [], () => '', () => 0, analyticsUnavailable);
            renderRows('top-sources', [], () => '', () => 0, analyticsUnavailable);
            renderRows('top-forms', [], () => '', () => 0, analyticsUnavailable);
            renderRows('device-breakdown', [], () => '', () => 0, analyticsUnavailable);
            renderRows('browser-breakdown', [], () => '', () => 0, analyticsUnavailable);
            renderRows('os-breakdown', [], () => '', () => 0, analyticsUnavailable);
            renderRows('viewport-breakdown', [], () => '', () => 0, analyticsUnavailable);
            renderRows('scroll-breakdown', [], () => '', () => 0, analyticsUnavailable);
            renderRows('performance-breakdown', [], () => '', () => 0, analyticsUnavailable);
            renderRows('hour-breakdown', [], () => '', () => 0, analyticsUnavailable);
        }

        if (site) {
            setText('members', formatInteger(site.members));
            setText('active-members-note', `${formatInteger(site.activeMembers)} actifs sur 30 j`);
            setText('upcoming-sessions', formatInteger(site.upcomingSessions));
            setText('monthly-sessions', formatInteger(site.sessionsThisMonth));
            setText('enrolments', formatInteger(site.enrolmentsThisMonth));
            setText('rankings', formatInteger(site.rankings));
            setText('reports', formatInteger(site.reports));
        } else {
            ['members', 'upcoming-sessions', 'monthly-sessions', 'enrolments', 'rankings', 'reports'].forEach((id) => setText(id, '—'));
            setText('active-members-note', 'Base de données indisponible.');
        }

        const available = [analytics, site].filter(Boolean).length;
        const status = available === 2
            ? 'Toutes les sources sont à jour.'
            : analytics
                ? 'Statistiques à jour. Base du club indisponible.'
                : site
                    ? `Données du club à jour. Statistiques : ${analyticsUnavailable}`
                    : analyticsUnavailable;
        setText('data-status', status);
        setText('last-updated', `Mis à jour à ${new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(payload.generatedAt))}`);
    } catch {
        setText('data-status', 'Impossible de charger les données.');
        setText('sites-feedback', 'API du dashboard indisponible.');
        document.getElementById('warriors-site').disabled = false;
        setText('last-updated', '');
    } finally {
        dashboardRequestInFlight = false;
        lastDashboardRequestAt = Date.now();
        if (refreshQueued && document.visibilityState === 'visible') {
            refreshQueued = false;
            void loadDashboard();
        }
    }
}

document.querySelectorAll('[data-period]').forEach((control) => {
    control.addEventListener('click', () => {
        const requestedPeriod = Number(control.dataset.period);
        if (![7, 28, 90].includes(requestedPeriod) || requestedPeriod === periodDays) return;
        periodDays = requestedPeriod;
        void loadDashboard({ queueIfBusy: true });
    });
});
document.querySelectorAll('[data-chart-mode]').forEach((control) => {
    control.addEventListener('click', () => {
        chartMetric = control.dataset.chartMode === 'sessions' ? 'sessions' : 'views';
        document.querySelectorAll('[data-chart-mode]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button === control));
        });
        setText('chart-metric-label', chartMetric === 'sessions' ? 'Sessions distinctes' : 'Vues de page');
        if (trafficPoints.length > 0) drawChart(trafficPoints);
    });
});
document.getElementById('warriors-site').addEventListener('click', selectWarriorsSite);
document.getElementById('back-to-sites').addEventListener('click', () => {
    document.getElementById('site-detail').hidden = true;
    document.getElementById('sites-screen').hidden = false;
});
window.addEventListener('resize', () => {
    if (trafficPoints.length > 0) drawChart(trafficPoints);
});

function refreshDashboardIfDue() {
    if (document.visibilityState !== 'visible' || dashboardRequestInFlight) return;
    if (!refreshQueued && Date.now() - lastDashboardRequestAt < dashboardRefreshInterval) return;
    void loadDashboard({ quiet: true });
}

document.addEventListener('visibilitychange', refreshDashboardIfDue);
window.setInterval(refreshDashboardIfDue, dashboardRefreshInterval);
if (new URLSearchParams(window.location.search).get('view') === 'analytics') {
    selectWarriorsSite();
}
if (document.visibilityState === 'visible') void loadDashboard();