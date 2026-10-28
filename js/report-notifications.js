(() => {
    const badges = document.querySelectorAll('[data-report-notification]');
    if (!badges.length) return;

    const loadNotifications = async () => {
        try {
            const response = await fetch('api/report-notifications.php', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error(`Chargement des notifications impossible (${response.status}).`);
            const counts = await response.json();
            badges.forEach((badge) => {
                const count = Number(counts[badge.dataset.reportNotification] || 0);
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = count < 1;
            });
        } catch (error) {
            console.error(error);
        }
    };

    loadNotifications();
    window.setInterval(loadNotifications, 60000);
})();
