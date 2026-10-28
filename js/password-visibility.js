document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const input = document.getElementById(toggle.dataset.passwordToggle);
    const icon = toggle.querySelector('i');
    if (!input || !icon) return;

    toggle.addEventListener('click', () => {
        const showPassword = input.type === 'password';
        input.type = showPassword ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(showPassword));
        toggle.setAttribute(
            'aria-label',
            showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
        );
        icon.classList.toggle('bi-eye', !showPassword);
        icon.classList.toggle('bi-eye-slash', showPassword);
    });
});
