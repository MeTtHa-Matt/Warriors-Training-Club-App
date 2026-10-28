(() => {
    const conversation = document.querySelector('[data-report-conversation]');
    if (!conversation) return;

    const reportId = conversation.dataset.reportId;
    const endpoint = conversation.dataset.endpoint;
    const csrfToken = conversation.dataset.csrf;
    const viewerRole = conversation.dataset.viewerRole;
    const body = conversation.querySelector('.report-conversation__body');
    const composer = conversation.querySelector('.report-conversation__composer');
    const textarea = composer?.querySelector('textarea');
    const errorElement = conversation.querySelector('[data-conversation-error]');
    let lastMessageId = Number(conversation.dataset.lastMessageId || 0);
    let polling = false;
    let interval = 5000;
    let pollTimer = 0;

    const schedulePoll = (delay) => {
        window.clearTimeout(pollTimer);
        pollTimer = window.setTimeout(fetchUpdates, delay);
    };

    const scrollToLatest = (force = false) => {
        if (!body) return;
        const distanceToBottom = body.scrollHeight - body.scrollTop - body.clientHeight;
        if (force || distanceToBottom < 120) body.scrollTop = body.scrollHeight;
    };

    const appendMessage = (message) => {
        if (!body || !message || Number(message.id) <= lastMessageId) return;
        const shouldScroll = body.scrollHeight - body.scrollTop - body.clientHeight < 120;
        const isAdminMessage = message.sender_type === 'admin';
        const isAdminView = conversation.dataset.viewerRole === 'admin';
        const isOutgoing = isAdminMessage === isAdminView;
        const article = document.createElement('article');
        article.className = `report-chat-message ${isOutgoing ? 'report-chat-message--user' : 'report-chat-message--admin'}`;

        const bubble = document.createElement('div');
        bubble.className = 'report-chat-message__bubble';
        if (!isOutgoing) {
            const sender = document.createElement('small');
            sender.className = 'report-chat-message__sender';
            sender.textContent = isAdminMessage ? 'Assistance WTC' : 'Utilisateur';
            bubble.append(sender);
        }
        bubble.append(document.createTextNode(message.message));

        const time = document.createElement('time');
        time.textContent = message.created_at_label;
        article.append(bubble, time);
        body.append(article);
        lastMessageId = Number(message.id);
        conversation.dataset.lastMessageId = String(lastMessageId);
        if (shouldScroll) scrollToLatest(true);
    };

    const showError = (message = '') => {
        if (!errorElement) return;
        errorElement.textContent = message;
        errorElement.hidden = message === '';
    };

    const closeComposer = (status) => {
        if (status !== 'closed' || !composer) return;
        composer.remove();
        const statusLabel = conversation.querySelector('.report-conversation__identity small');
        if (statusLabel) statusLabel.textContent = 'Dossier résolu';
        const header = conversation.querySelector('.report-conversation__header');
        if (header && !header.querySelector('.report-status--closed')) {
            const badge = document.createElement('span');
            badge.className = 'report-status report-status--closed';
            badge.textContent = 'Résolu';
            header.append(badge);
        }
    };

    const fetchUpdates = async () => {
        if (polling || document.hidden) return;
        polling = true;
        try {
            const url = new URL(endpoint, window.location.href);
            url.searchParams.set('report', reportId);
            url.searchParams.set('after', String(lastMessageId));
            url.searchParams.set('viewer_role', viewerRole);
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(`Mise à jour impossible (${response.status}).`);
            const result = await response.json();
            result.messages.forEach(appendMessage);
            closeComposer(result.status);
            if (result.messages.length > 0) {
                interval = 5000;
                showError();
            } else {
                interval = Math.min(interval + 2000, 15000);
            }
        } catch (error) {
            interval = Math.min(interval * 2, 30000);
            console.error(error);
        } finally {
            polling = false;
            schedulePoll(document.hidden ? 15000 : interval);
        }
    };

    composer?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = textarea?.value.trim() || '';
        if (!message || !textarea || !composer) return;

        const sendButton = composer.querySelector('button[type="submit"]');
        if (sendButton) sendButton.disabled = true;
        showError();
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                body: new URLSearchParams({
                    report: reportId,
                    viewer_role: viewerRole,
                    csrf_token: csrfToken,
                    message,
                }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Le message n’a pas pu être envoyé.');
            appendMessage(result.message);
            textarea.value = '';
            textarea.focus();
            scrollToLatest(true);
        } catch (error) {
            showError(error.message);
        } finally {
            if (sendButton?.isConnected) sendButton.disabled = false;
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            interval = 5000;
            schedulePoll(0);
        }
    });

    scrollToLatest(true);
    schedulePoll(interval);
})();
