<?php
require_once __DIR__ . '/includes/general/verifications.php';
require_once __DIR__ . '/includes/general/conversation.php';

$conversationViewerEmail = $conversationViewerEmail ?? CONVERSATION_USER_EMAIL;
$conversationEndpoint = $conversationEndpoint ?? 'coucou.php';
$conversationBackUrl = $conversationBackUrl ?? 'index.php';

if (strtolower((string) ($_SESSION['email'] ?? '')) !== $conversationViewerEmail) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['messages'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(conversation_messages(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Requête non autorisée']);
        exit;
    }

    try {
        $newMessage = conversation_add_message($conversationViewerEmail, (string) $_POST['message']);
        echo json_encode(['success' => true, 'message' => $newMessage], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Coucou 👋</title>
<style>
    :root {
        --gris-fond: #f5f5f7;
        --texte: #1d1d1f;
        --texte-secondaire: #86868b;
        --bulle-recue: #e9e9eb;
        --bleu-systeme: #007aff;
        --rose: #ff375f;
        --violet: #bf5af2;
        --degrade: linear-gradient(135deg, var(--rose), var(--violet));
        --rayon-carte: 26px;
    }

    * {
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }

    html, body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }

    body {
        min-height: 100vh;
        min-height: 100svh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gris-fond);
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display",
                     "Helvetica Neue", Arial, sans-serif;
        color: var(--texte);
        padding: 78px 14px 66px;
        position: relative;
        -webkit-font-smoothing: antialiased;
    }

    /* Halos de couleur, très doux, en arrière-plan */
    .halo {
        position: fixed;
        width: 60vw;
        height: 60vw;
        max-width: 420px;
        max-height: 420px;
        border-radius: 50%;
        filter: blur(70px);
        opacity: 0.35;
        z-index: 0;
        animation: derive 16s ease-in-out infinite;
    }
    .halo-1 {
        top: -10%;
        left: -15%;
        background: #ffd1e0;
    }
    .halo-2 {
        bottom: -12%;
        right: -15%;
        background: #e3cfff;
        animation-delay: -8s;
    }
    .halo-3 {
        top: 42%;
        left: 50%;
        width: 46vw;
        height: 46vw;
        max-width: 320px;
        max-height: 320px;
        background: #ffe8c9;
        opacity: 0.25;
        animation: derive 22s ease-in-out infinite;
        animation-delay: -4s;
    }

    @keyframes derive {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50% { transform: translate(4%, 3%) scale(1.08); }
    }

    /* --- Barre supérieure façon iPhone --- */
    .barre-haut {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 5;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 26px 0;
        pointer-events: none;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: -0.01em;
        color: var(--texte);
    }

    .barre-haut .icones {
        display: flex;
        align-items: center;
        gap: 5px;
        opacity: 0.85;
    }

    .ile-dynamique {
        position: fixed;
        top: 12px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 6;
        width: 108px;
        height: 32px;
        border-radius: 20px;
        background: #0a0a0c;
        box-shadow: 0 0 0 0 rgba(255, 55, 95, 0.5);
        animation: lueur-ile 3.2s ease-in-out infinite;
        pointer-events: none;
    }

    @keyframes lueur-ile {
        0%, 100% { box-shadow: 0 0 0 0 rgba(191, 90, 242, 0.28); }
        50% { box-shadow: 0 0 0 8px rgba(191, 90, 242, 0); }
    }

    /* --- Étincelles décoratives haut / bas d'écran --- */
    .etincelle {
        position: fixed;
        z-index: 2;
        font-size: 1.1rem;
        opacity: 0;
        animation: scintiller ease-in-out infinite;
        pointer-events: none;
        filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.8));
    }

    @keyframes scintiller {
        0%, 100% { opacity: 0; transform: scale(0.6) rotate(0deg); }
        50% { opacity: 0.9; transform: scale(1.1) rotate(15deg); }
    }

    /* --- Bas d'écran : indicateur + signature --- */
    .bas-ecran {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 5;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding-bottom: 10px;
        pointer-events: none;
    }

    .signature-bas {
        font-size: 0.78rem;
        font-weight: 500;
        color: var(--texte-secondaire);
        display: flex;
        align-items: center;
        gap: 5px;
        letter-spacing: -0.01em;
    }

    .coeur-bas {
        display: inline-block;
        animation: battement 1.4s ease-in-out infinite;
    }

    @keyframes battement {
        0%, 100% { transform: scale(1); }
        25% { transform: scale(1.25); }
        40% { transform: scale(1); }
    }

    .barre-accueil {
        width: 134px;
        height: 5px;
        border-radius: 3px;
        background: var(--texte);
        opacity: 0.85;
    }

    /* Aura autour de la fenêtre de conversation (fixe, non tournante) */
    .aura {
        position: absolute;
        z-index: 0;
        inset: -3px;
        border-radius: calc(var(--rayon-carte) + 3px);
        padding: 3px;
        background: conic-gradient(from 200deg, #ff375f, #bf5af2, #64d2ff, #ffd60a, #ff375f);
        opacity: 0.55;
        filter: blur(14px);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
    }

    /* Poussière de particules ambiantes, discrète et lente */
    .particules {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        overflow: hidden;
    }

    .grain {
        position: absolute;
        border-radius: 50%;
        opacity: 0;
        animation: monter linear infinite;
    }

    @keyframes monter {
        0% { transform: translateY(0) scale(0.6); opacity: 0; }
        12% { opacity: 0.55; }
        88% { opacity: 0.4; }
        100% { transform: translateY(-100vh) scale(1); opacity: 0; }
    }

    /* --- Fenêtre de conversation --- */
    .fenetre-wrapper {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 400px;
    }

    .fenetre {
        position: relative;
        width: 100%;
        background: rgba(255, 255, 255, 0.78);
        backdrop-filter: blur(24px) saturate(180%);
        -webkit-backdrop-filter: blur(24px) saturate(180%);
        border-radius: var(--rayon-carte);
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 24px 60px rgba(0,0,0,0.12);
        overflow: hidden;
        opacity: 0;
        animation: entree 0.6s cubic-bezier(.2,.9,.25,1) 0.1s forwards;
    }

    @keyframes entree {
        from { opacity: 0; transform: translateY(18px) scale(0.97); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* --- En-tête type contact --- */
    .entete {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 16px 18px;
        border-bottom: 1px solid rgba(0,0,0,0.06);
    }

    .bouton-retour {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 12px 16px 0;
        color: var(--texte-secondaire);
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
        transition: color 0.15s ease, transform 0.15s ease;
    }

    .bouton-retour:hover,
    .bouton-retour:focus-visible {
        color: var(--bleu-systeme);
        transform: translateX(-2px);
    }

    .avatar {
        width: 38px;
        height: 38px;
        min-width: 38px;
        border-radius: 50%;
        background: var(--degrade);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        box-shadow: 0 2px 6px rgba(255, 55, 95, 0.35);
    }

    .entete-texte {
        display: flex;
        flex-direction: column;
        line-height: 1.25;
    }

    .entete-nom {
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: -0.01em;
    }

    .entete-statut {
        font-size: 0.78rem;
        color: var(--texte-secondaire);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .entete-statut::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #34c759;
    }

    /* --- Corps de conversation --- */
    .corps {
        padding: 18px 16px 6px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-height: 190px;
    }

    .ligne {
        display: flex;
        max-width: 84%;
    }

    .ligne.recu { justify-content: flex-start; align-self: flex-start; }
    .ligne.envoye { justify-content: flex-end; align-self: flex-end; }

    .bulle {
        padding: 10px 14px;
        font-size: 0.96rem;
        line-height: 1.36;
        opacity: 0;
        transform: scale(0.4);
        transform-origin: bottom left;
    }

    .ligne.envoye .bulle { transform-origin: bottom right; }

    .bulle.apparait {
        animation: pop-bulle 0.42s cubic-bezier(.34,1.56,.64,1) forwards;
    }

    @keyframes pop-bulle {
        0% { opacity: 0; transform: scale(0.4); }
        60% { opacity: 1; }
        100% { opacity: 1; transform: scale(1); }
    }

    .ligne.recu .bulle {
        background: var(--bulle-recue);
        color: var(--texte);
        border-radius: 18px 18px 18px 5px;
    }

    .ligne.envoye .bulle {
        background: var(--degrade);
        color: white;
        border-radius: 18px 18px 5px 18px;
    }

    .emoji-inline {
        display: inline-block;
    }

    .clin-oeil { animation: clin 2.6s ease-in-out 1.2s infinite; }
    @keyframes clin {
        0%, 44%, 100% { transform: scaleY(1); }
        47% { transform: scaleY(0.15); }
        50% { transform: scaleY(1); }
    }

    .sourire { animation: sourit 2.2s ease-in-out 1.6s infinite; }
    @keyframes sourit {
        0%, 100% { transform: rotate(0deg) scale(1); }
        50% { transform: rotate(6deg) scale(1.12); }
    }

    /* --- Indicateur "en train d'écrire" --- */
    .indicateur {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--bulle-recue);
        padding: 12px 16px;
        border-radius: 18px 18px 18px 5px;
        width: fit-content;
    }

    .indicateur span {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #9a9a9e;
        animation: rebond 1.1s ease-in-out infinite;
    }
    .indicateur span:nth-child(2) { animation-delay: 0.15s; }
    .indicateur span:nth-child(3) { animation-delay: 0.3s; }

    @keyframes rebond {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
        30% { transform: translateY(-4px); opacity: 1; }
    }

    /* --- Zone d'action (bouton / champ) --- */
    .zone-action {
        padding: 10px 16px 18px;
    }

    #zone-reponse, #zone-confirmation {
        opacity: 0;
    }

    #zone-confirmation.visible {
        animation: apparition-douce 0.45s ease forwards;
    }

    #zone-reponse.visible {
        display: block;
        animation: apparition-douce 0.45s ease forwards;
    }

    #zone-reponse { display: none; }

    @keyframes apparition-douce {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .bouton-systeme {
        width: 100%;
        border: none;
        cursor: pointer;
        font-family: inherit;
        font-size: 0.98rem;
        font-weight: 600;
        letter-spacing: -0.01em;
        padding: 13px 20px;
        border-radius: 14px;
        background: var(--degrade);
        color: white;
        box-shadow: 0 8px 20px rgba(255, 55, 95, 0.28);
        transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease;
    }

    .bouton-systeme:active {
        transform: scale(0.97);
        box-shadow: 0 4px 12px rgba(255, 55, 95, 0.25);
    }

    .bouton-systeme:disabled {
        opacity: 0.6;
        transform: none;
    }

    .barre-saisie {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: 22px;
        padding: 6px 6px 6px 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .barre-saisie:focus-within {
        border-color: var(--bleu-systeme);
        box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.12);
    }

    #message {
        flex: 1;
        border: none;
        outline: none;
        resize: none;
        background: transparent;
        font-family: inherit;
        font-size: 0.95rem;
        color: var(--texte);
        max-height: 90px;
        padding: 8px 0;
        line-height: 1.3;
    }

    #message::placeholder {
        color: #b0b0b5;
    }

    .bouton-envoi {
        width: 34px;
        height: 34px;
        min-width: 34px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        background: var(--bleu-systeme);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.15s ease, opacity 0.15s ease, background 0.15s ease;
    }

    .bouton-envoi:disabled {
        background: #c7c7cc;
        cursor: default;
    }

    .bouton-envoi:active:not(:disabled) {
        transform: scale(0.9);
    }

    .bouton-envoi svg {
        width: 15px;
        height: 15px;
    }

    .libelle-envoi {
        text-align: center;
        font-size: 0.78rem;
        color: var(--texte-secondaire);
        margin-top: 8px;
    }

    #statut {
        margin-top: 8px;
        font-size: 0.85rem;
        min-height: 18px;
        text-align: center;
        color: var(--texte-secondaire);
    }

    /* --- Confirmation --- */
    #zone-confirmation {
        display: none;
        text-align: right;
    }

    #zone-confirmation.visible { display: block; }

    .accuse {
        font-size: 0.72rem;
        color: var(--texte-secondaire);
        text-align: right;
        margin-top: 4px;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }

    /* Mobile first : tout ce qui précède est pensé petit écran */
    @media (min-width: 480px) {
        .fenetre { max-width: 420px; }
        .bulle { font-size: 1rem; }
        .entete-nom { font-size: 1rem; }
    }    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
        }
    }
</style>
</head>
<body>

<div class="halo halo-1"></div>
<div class="halo halo-2"></div>
<div class="halo halo-3"></div>
<div class="particules" id="particules"></div>

<div class="barre-haut">
    <span>9:41</span>
    <span class="icones">📶 📡 🔋</span>
</div>
<div class="ile-dynamique"></div>

<div class="bas-ecran">
    <div class="barre-accueil"></div>
</div>

<div class="fenetre-wrapper">
    <div class="aura"></div>
    <div class="fenetre">
    <a class="bouton-retour" href="<?= htmlspecialchars($conversationBackUrl, ENT_QUOTES) ?>">
        <span aria-hidden="true">←</span> Retourner au site
    </a>
    <div class="entete">
        <div class="avatar">💕</div>
        <div class="entete-texte">
            <div class="entete-nom">Toi &amp; moi</div>
            <div class="entete-statut">actif à l'instant</div>
        </div>
    </div>

    <div class="corps" id="corps"></div>

    <div class="zone-action">
        <div id="zone-reponse">
            <div class="barre-saisie">
                <textarea id="message" rows="1" placeholder="Écris-moi quelque chose de doux..."></textarea>
                <button class="bouton-envoi" id="btn-envoyer" aria-label="Envoyer a Clem">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 19V5M12 5L5 12M12 5L19 12" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
            <div class="libelle-envoi">Envoyer a Matthieu</div>
            <div id="statut"></div>
        </div>

        <div id="zone-confirmation">
            <div class="ligne envoye" style="max-width:100%;">
                <div class="bulle apparait" id="bulle-envoyee" style="transform:scale(1); opacity:1;"></div>
            </div>
            <div class="accuse">Distribué 💌</div>
        </div>
    </div>
    </div>
</div>

<script>
    // --- Poussière de particules ambiantes en arrière-plan ---
    const particulesConteneur = document.getElementById('particules');
    const couleursParticules = ['#ff375f', '#bf5af2', '#ffd60a', '#64d2ff'];
    const nbParticules = window.innerWidth < 480 ? 10 : 16;

    for (let i = 0; i < nbParticules; i++) {
        const grain = document.createElement('span');
        grain.className = 'grain';
        const taille = 3 + Math.random() * 5;
        grain.style.width = taille + 'px';
        grain.style.height = taille + 'px';
        grain.style.left = Math.random() * 100 + 'vw';
        grain.style.bottom = (Math.random() * 20 - 10) + 'vh';
        grain.style.background = couleursParticules[Math.floor(Math.random() * couleursParticules.length)];
        grain.style.boxShadow = '0 0 ' + (taille * 2) + 'px ' + grain.style.background;
        const duree = 14 + Math.random() * 12;
        grain.style.animationDuration = duree + 's';
        grain.style.animationDelay = (Math.random() * duree) + 's';
        particulesConteneur.appendChild(grain);
    }

    // --- Étincelles décoratives en haut et en bas de l'écran ---
    const positionsEtincelles = [
        { top: '58px', left: '8vw', delai: 0 },
        { top: '72px', right: '10vw', delai: 0.8 },
        { top: '100px', left: '22vw', delai: 1.6 },
        { bottom: '70px', left: '9vw', delai: 0.4 },
        { bottom: '58px', right: '11vw', delai: 1.2 },
        { bottom: '92px', right: '24vw', delai: 2 }
    ];

    positionsEtincelles.forEach(pos => {
        const e = document.createElement('span');
        e.className = 'etincelle';
        e.textContent = '✨';
        if (pos.top) e.style.top = pos.top;
        if (pos.bottom) e.style.bottom = pos.bottom;
        if (pos.left) e.style.left = pos.left;
        if (pos.right) e.style.right = pos.right;
        e.style.animationDuration = (2.6 + Math.random() * 1.6) + 's';
        e.style.animationDelay = pos.delai + 's';
        document.body.appendChild(e);
    });

    const corps = document.getElementById('corps');
    const idsAffiches = new Set();

    function attendre(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function afficherBulle(message) {
        const ligne = document.createElement('div');
        const estEnvoye = message.sender.toLowerCase() === '<?= strtolower($conversationViewerEmail) ?>';
        ligne.className = 'ligne ' + (estEnvoye ? 'envoye' : 'recu');
        const bulle = document.createElement('div');
        bulle.className = 'bulle apparait';
        bulle.textContent = message.message;
        ligne.appendChild(bulle);
        corps.appendChild(ligne);
    }

    async function jouerSequence() {
        const reponse = await fetch('<?= htmlspecialchars($conversationEndpoint, ENT_QUOTES) ?>?messages=1', { credentials: 'same-origin', cache: 'no-store' });
        const messages = await reponse.json();

        for (const message of messages) {
            await attendre(500);

            // Indicateur "en train d'écrire"
            const ligneIndicateur = document.createElement('div');
            ligneIndicateur.className = 'ligne ' + (message.sender.toLowerCase() === '<?= strtolower($conversationViewerEmail) ?>' ? 'envoye' : 'recu');
            ligneIndicateur.innerHTML = '<div class="indicateur"><span></span><span></span><span></span></div>';
            corps.appendChild(ligneIndicateur);

            await attendre(950);
            ligneIndicateur.remove();

            // Bulle réelle
            afficherBulle(message);
            idsAffiches.add(message.id);
        }

        // Révèle directement la zone de réponse une fois la conversation terminée
        zoneReponse.classList.add('visible');
        messageInput.focus({ preventScroll: true });
    }

    async function actualiserMessages() {
        const reponse = await fetch('<?= htmlspecialchars($conversationEndpoint, ENT_QUOTES) ?>?messages=1', { credentials: 'same-origin', cache: 'no-store' });
        if (!reponse.ok) return;
        const messages = await reponse.json();
        messages.forEach(message => {
            if (!idsAffiches.has(message.id)) {
                afficherBulle(message);
                idsAffiches.add(message.id);
            }
        });
    }

    // --- Éléments de la zone d'action ---
    const zoneReponse = document.getElementById('zone-reponse');
    const zoneConfirmation = document.getElementById('zone-confirmation');
    const btnEnvoyer = document.getElementById('btn-envoyer');
    const messageInput = document.getElementById('message');
    const statut = document.getElementById('statut');
    const bulleEnvoyee = document.getElementById('bulle-envoyee');
    let envoiEnCours = false;

    jouerSequence().then(() => {
        setInterval(() => actualiserMessages().catch(() => {}), 2000);
    }).catch(() => {
        statut.textContent = 'Impossible de charger la conversation.';
    });

    messageInput.addEventListener('input', () => {
        messageInput.style.height = 'auto';
        messageInput.style.height = Math.min(messageInput.scrollHeight, 90) + 'px';
    });

    btnEnvoyer.addEventListener('click', async () => {
        if (envoiEnCours) return;
        const texte = messageInput.value.trim();
        if (texte === '') {
            statut.textContent = "Écris un petit mot avant d'envoyer 🙂";
            return;
        }

        envoiEnCours = true;
        btnEnvoyer.disabled = true;
        messageInput.disabled = true;
        statut.textContent = "Envoi en cours...";

        try {
            const reponse = await fetch('<?= htmlspecialchars($conversationEndpoint, ENT_QUOTES) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'message=' + encodeURIComponent(texte) + '&csrf_token=' + encodeURIComponent('<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>')
            });
            const data = await reponse.json();

            if (data.success) {
                if (data.message && !idsAffiches.has(data.message.id)) {
                    afficherBulle(data.message);
                    idsAffiches.add(data.message.id);
                }
                messageInput.value = '';
                messageInput.style.height = 'auto';
                statut.textContent = 'Message envoyé.';
            } else {
                statut.textContent = "Oups, l'envoi a échoué. Réessaie ?";
                btnEnvoyer.disabled = false;
                messageInput.disabled = false;
            }
            envoiEnCours = false;
            btnEnvoyer.disabled = false;
            messageInput.disabled = false;
            messageInput.focus({ preventScroll: true });
        } catch (e) {
            statut.textContent = "Erreur réseau, réessaie.";
            envoiEnCours = false;
            btnEnvoyer.disabled = false;
            messageInput.disabled = false;
        }
    });
</script>

</body>
</html>
