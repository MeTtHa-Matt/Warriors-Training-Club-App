document.addEventListener('DOMContentLoaded', () => {
  const endpoint = 'api/sql-actions.php';
  const queryTypes = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'OTHER'];
  const rowsByType = Object.fromEntries(queryTypes.map(type => [type, document.getElementById(`sqlRows${type}`)]));
  const connectionStatus = document.getElementById('sqlActionsConnection');
  const updatedLabel = document.getElementById('sqlActionsUpdated');
  const typeFilter = document.getElementById('sqlActionsFilter');
  const queryModalElement = document.getElementById('sqlQueryModal');
  const queryModalStatement = document.getElementById('sqlQueryModalStatement');
  const queryModal = bootstrap.Modal.getOrCreateInstance(queryModalElement);
  const retainedByType = Object.fromEntries(queryTypes.map(type => [type, 0]));
  let cursor = null;
  let polling = false;

  function setConnection(connected, message = '') {
    connectionStatus.classList.toggle('is-connected', connected);
    connectionStatus.classList.toggle('has-error', !connected);
    connectionStatus.querySelector('span:last-child').textContent = connected ? 'En direct' : (message || 'Connexion interrompue');
    if (connected) {
      updatedLabel.textContent = `Actualisé à ${new Date().toLocaleTimeString('fr-FR')}`;
    }
  }

  function renderEntry(entry) {
    const type = queryTypes.includes(entry.query_type) ? entry.query_type : 'OTHER';
    const body = rowsByType[type];
    if (!retainedByType[type]) body.replaceChildren();

    const row = document.createElement('article');
    row.className = 'sql-actions-entry';

    const header = document.createElement('div');
    header.className = 'sql-actions-entry__meta';

    const timestamp = document.createElement('time');
    timestamp.className = 'sql-actions-entry__time';
    timestamp.dateTime = String(entry.created_at || '');
    timestamp.textContent = String(entry.created_at || '').slice(11, 19);
    header.append(timestamp);

    const actor = document.createElement('span');
    actor.className = 'sql-actions-entry__actor';
    actor.textContent = String(entry.actor_name || (entry.actor_id ? `Compte #${entry.actor_id}` : 'Visiteur'));
    header.append(actor);

    const badge = document.createElement('span');
    badge.className = `sql-actions-result${entry.status === 'ok' ? '' : ' is-error'}`;
    badge.textContent = entry.status === 'ok' ? 'Réussie' : 'Échec';
    header.append(badge);

    const statement = document.createElement('button');
    statement.className = 'sql-actions-entry__query';
    statement.type = 'button';
    statement.textContent = '...';
    statement.title = 'Afficher la requête SQL complète';
    statement.setAttribute('aria-label', 'Afficher la requête SQL complète');
    statement.addEventListener('click', () => {
      queryModalStatement.textContent = String(entry.statement || 'Requête indisponible');
      queryModal.show();
    });
    row.append(header, statement);

    body.prepend(row);
    retainedByType[type] += 1;
    while (retainedByType[type] > 150) {
      body.lastElementChild?.remove();
      retainedByType[type] -= 1;
    }
    const option = typeFilter.querySelector(`option[value="${type}"]`);
    option.textContent = `${option.dataset.label} (${retainedByType[type]})`;
  }

  async function poll() {
    if (polling || document.visibilityState !== 'visible') return;
    polling = true;
    try {
      const url = new URL(endpoint, window.location.href);
      if (cursor !== null) url.searchParams.set('after_id', String(cursor));
      const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      if (!response.ok) {
        const failure = await response.json().catch(() => ({}));
        setConnection(false, failure.error || `Réponse du serveur : HTTP ${response.status}`);
        return;
      }
      const data = await response.json();
      for (const entry of data.items || []) {
        renderEntry(entry);
        cursor = Math.max(cursor || 0, Number(entry.id) || 0);
      }
      setConnection(true);
    } catch (error) {
      setConnection(false, 'Serveur injoignable. Vérifie ta connexion et réessaie.');
    } finally {
      polling = false;
    }
  }

  typeFilter.addEventListener('change', () => {
    queryTypes.forEach(type => {
      document.getElementById(`sqlPanel${type}`).hidden = type !== typeFilter.value;
    });
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') poll();
  });

  poll();
  window.setInterval(poll, 10000);
});