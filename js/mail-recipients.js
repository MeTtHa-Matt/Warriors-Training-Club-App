(() => {
    const picker = document.getElementById('recipientPicker');
    const tokens = document.getElementById('recipientTokens');
    const input = document.getElementById('recipientInput');
    const suggestions = document.getElementById('recipientSuggestions');
    const countLabel = document.getElementById('recipientCount');
    const recipients = Array.isArray(window.mailRecipients) ? window.mailRecipients : [];
    if (!picker || !tokens || !input || !suggestions) return;

    const recipientsByEmail = new Map(recipients.map((recipient) => [recipient.email.toLowerCase(), recipient]));
    let activeSuggestion = -1;

    const selectedEmails = () => new Set(
        Array.from(tokens.querySelectorAll('input[name="recipients[]"]'), (field) => field.value.toLowerCase()),
    );

    const updateCount = () => {
        const count = tokens.querySelectorAll('input[name="recipients[]"]').length;
        if (countLabel) countLabel.textContent = String(count);
        const label = countLabel?.parentElement;
        if (label) {
            const text = Array.from(label.childNodes).find((node) => node.nodeType === Node.TEXT_NODE);
            if (text) text.textContent = ` destinataire${count > 1 ? 's' : ''}`;
        }
    };

    const hideSuggestions = () => {
        suggestions.hidden = true;
        suggestions.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeSuggestion = -1;
    };

    const addRecipient = (email) => {
        const normalizedEmail = email.trim().toLowerCase();
        const recipient = recipientsByEmail.get(normalizedEmail);
        if (!recipient || selectedEmails().has(normalizedEmail)) return false;

        const token = document.createElement('span');
        token.className = 'mail-recipient-token';
        token.dataset.recipientEmail = normalizedEmail;

        const label = document.createElement('span');
        label.textContent = `${recipient.name} <${recipient.email}>`;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'mail-recipient-token__remove';
        remove.setAttribute('aria-label', `Supprimer ${recipient.email}`);
        remove.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'recipients[]';
        hidden.value = normalizedEmail;

        token.append(label, remove, hidden);
        tokens.insertBefore(token, input);
        updateCount();
        return true;
    };

    const processEntry = (entry) => {
        const value = entry.trim().replace(/[;,]+$/, '').toLowerCase();
        if (!value) return false;
        const group = value.replace(/\s+/g, '');
        if (group === 'all' || group === 'admin' || group === 'nadmin') {
            const matches = recipients.filter((recipient) => (
                group === 'all' || (group === 'admin' ? recipient.admin : !recipient.admin)
            ));
            matches.forEach((recipient) => addRecipient(recipient.email));
            return true;
        }
        return addRecipient(value);
    };

    const renderSuggestions = () => {
        const query = input.value.trim().toLowerCase();
        if (!query || ['all', 'admin', 'nadmin'].includes(query)) {
            hideSuggestions();
            return;
        }

        const selected = selectedEmails();
        const matches = recipients
            .filter((recipient) => !selected.has(recipient.email.toLowerCase()))
            .filter((recipient) => recipient.email.toLowerCase().includes(query) || recipient.name.toLowerCase().includes(query))
            .slice(0, 8);

        suggestions.replaceChildren();
        matches.forEach((recipient, index) => {
            const option = document.createElement('li');
            option.id = `mail-recipient-option-${index}`;
            option.className = 'mail-recipient-suggestion';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', index === activeSuggestion ? 'true' : 'false');
            const name = document.createElement('strong');
            name.textContent = recipient.name;
            const email = document.createElement('span');
            email.textContent = recipient.email;
            option.append(name, email);
            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                addRecipient(recipient.email);
                input.value = '';
                hideSuggestions();
                input.focus();
            });
            suggestions.append(option);
        });

        suggestions.hidden = matches.length === 0;
        input.setAttribute('aria-expanded', String(matches.length > 0));
    };

    input.addEventListener('input', () => {
        activeSuggestion = -1;
        renderSuggestions();
    });

    input.addEventListener('keydown', (event) => {
        const options = suggestions.querySelectorAll('[role="option"]');
        if (event.key === 'ArrowDown' && options.length) {
            event.preventDefault();
            activeSuggestion = (activeSuggestion + 1) % options.length;
            renderSuggestions();
            input.setAttribute('aria-activedescendant', `mail-recipient-option-${activeSuggestion}`);
        } else if (event.key === 'ArrowUp' && options.length) {
            event.preventDefault();
            activeSuggestion = activeSuggestion <= 0 ? options.length - 1 : activeSuggestion - 1;
            renderSuggestions();
            input.setAttribute('aria-activedescendant', `mail-recipient-option-${activeSuggestion}`);
        } else if (event.key === 'Enter' && activeSuggestion >= 0 && options[activeSuggestion]) {
            event.preventDefault();
            options[activeSuggestion].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        } else if (event.key === ' ' || event.key === 'Enter' || event.key === ',' || event.key === ';') {
            if (processEntry(input.value)) {
                event.preventDefault();
                input.value = '';
                hideSuggestions();
            } else if (event.key !== ' ') {
                event.preventDefault();
            }
        } else if (event.key === 'Backspace' && input.value === '') {
            tokens.querySelector('.mail-recipient-token:last-of-type')?.remove();
            updateCount();
        } else if (event.key === 'Escape') {
            hideSuggestions();
        }
    });

    input.addEventListener('blur', () => {
        if (processEntry(input.value)) input.value = '';
        hideSuggestions();
    });

    tokens.addEventListener('click', (event) => {
        const remove = event.target.closest('.mail-recipient-token__remove');
        if (!remove) return;
        remove.closest('.mail-recipient-token')?.remove();
        updateCount();
        input.focus();
    });

    picker.addEventListener('click', (event) => {
        if (event.target === picker || event.target === tokens) input.focus();
    });

    document.querySelector('.mail-form')?.addEventListener('submit', (event) => {
        if (input.value.trim() && !processEntry(input.value)) {
            event.preventDefault();
            input.focus();
            renderSuggestions();
        } else {
            input.value = '';
        }
    });

    updateCount();
})();
