<?php
require_once "includes/general/session-config.php";
require_once "includes/general/verifications.php";
require_once __DIR__ . '/includes/general/conversation.php';

if (
    strtolower((string) ($_SESSION['email'] ?? '')) === CONVERSATION_USER_EMAIL
    && !conversation_has_messages_from(CONVERSATION_USER_EMAIL)
) {
    header('Location: coucou.php');
    exit;
}

include __DIR__ . "/includes/general/index-liens.php";
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warriors Training Club - Accueil</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=202607102000">
    <link rel="manifest" href="./manifest.json">
    <link rel="icon" type="image/png" sizes="any" href="./img/wtc.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./img/wtc.png">
    <meta name="application-name" content="Warriors Training Club">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Warriors">
    <meta name="msapplication-TileColor" content="#0C0B0A">
    <meta name="msapplication-TileImage" content="./img/wtc.png">
    <meta name="theme-color" content="#C9A227">
    <meta name="mobile-web-app-capable" content="yes">
</head>

<body>

    <?php require 'includes/general/navbar.php'; ?>

        

    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-8">
                    <span class="hero-badge mb-3"><span class="dot"></span>Saison 2026 — 2027</span>
                    <h1 class="mt-3 mb-3">Entre dans le <span class="accent">Warriors Training Club</span></h1>
                    <p class="lead">
                        Bienvenue sur l'application du WTC. Cardio Training, Hyrox, Running-Trail et
                        préparation physique : retrouve ici les horaires de la saison, tes documents
                        d'adhésion et toutes les infos pratiques du club.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                        <a href="<?= $heroInscriptionLink ?>" class="btn btn-wtc-gold rounded-pill" target="_blank"
                            rel="noopener">
                            Inscription en ligne
                        </a>
                        <a href="seances.php" class="btn btn-wtc-outline rounded-pill">Voir les séances</a>
                    </div>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                    <div class="mt-4" id="index-upcoming-seances">
                        <div class="section-head mt-5">
                            <h2>Envie de participer à la prochaine séance ?</h2>
                        </div>

                        <div id="indexUpcomingList" class="upcoming-list">
                            <p class="upcoming-empty" id="indexUpcomingEmpty" style="display:none;">
                                Aucune séance à venir pour le moment.
                            </p>
                        </div>
                        
                    </div>
                    <script>
                    (async function(){
                        try{
                            const res = await fetch('includes/seances/upcoming.php?limit=1', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                            if (res.status === 401) {
                                const empty = document.getElementById('indexUpcomingEmpty');
                                empty.textContent = 'Connecte-toi pour voir la prochaine séance.';
                                empty.style.display = '';
                                console.warn('includes/seances/upcoming.php -> 401 unauthorized');
                                return;
                            }
                            if (!res.ok) {
                                const empty = document.getElementById('indexUpcomingEmpty');
                                empty.textContent = 'Impossible de charger la prochaine séance.';
                                empty.style.display = '';
                                console.error('includes/seances/upcoming.php error', res.status, res.statusText);
                                return;
                            }
                            const data = await res.json();
                            const list = document.getElementById('indexUpcomingList');
                            const empty = document.getElementById('indexUpcomingEmpty');
                            if(!data.seances || !data.seances.length){ empty.style.display = ''; return; }
                            empty.style.display = 'none';
                            const s = data.seances[0];

                            // fetch detail to get registration status
                            let detail = null;
                            try{
                                const dres = await fetch('includes/seances/detail.php?id=' + encodeURIComponent(s.id), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                                if (dres.status === 401) {
                                    console.warn('includes/seances/detail.php (prefetch) -> 401 unauthorized');
                                } else if (dres.ok) {
                                    detail = await dres.json();
                                } else {
                                    console.error('includes/seances/detail.php (prefetch) error', dres.status, dres.statusText);
                                }
                            }catch(e){ /* ignore */ }

                            const MOIS = ["Janvier","Février","Mars","Avril","Mai","Juin","Juillet","Août","Septembre","Octobre","Novembre","Décembre"];
                            const item = document.createElement('div');
                            item.className = 'd-flex align-items-center gap-3';

                            const dateBlock = document.createElement('div');
                            dateBlock.className = 'upcoming-item__date';
                            dateBlock.innerHTML = `<span class="upcoming-item__day">${s.date_seance.slice(8,10)}</span><span class="upcoming-item__month">${MOIS[parseInt(s.date_seance.slice(5,7),10)-1].slice(0,3)}</span>`;

                            const infoBlock = document.createElement('div');
                            infoBlock.className = 'upcoming-item__info';
                            infoBlock.innerHTML = `<p class="upcoming-item__type">${(s.type_seance||'').replace(/</g,'&lt;')}</p><p class="upcoming-item__meta">${(s.heure_debut||'').slice(0,5)} - ${(s.heure_fin||'').slice(0,5)} · ${(s.lieu_seance||'').replace(/</g,'&lt;')}</p>`;

                            const inlineBtn = document.createElement('button');
                            inlineBtn.type = 'button';
                            inlineBtn.className = 'btn btn-wtc-gold rounded-pill';
                            inlineBtn.style.whiteSpace = 'nowrap';

                            const isRegisteredInline = detail && detail.is_registered;
                            const registrationAllowedInline = detail ? detail.registration_allowed : true;

                            if (isRegisteredInline) {
                                inlineBtn.textContent = 'Inscrit';
                                inlineBtn.disabled = true;
                                inlineBtn.classList.add('disabled');
                            } else if (!registrationAllowedInline) {
                                inlineBtn.textContent = 'Inscriptions fermées';
                                inlineBtn.disabled = true;
                                inlineBtn.classList.add('disabled');
                            } else {
                                inlineBtn.textContent = 'M\'inscrire';
                            }

                            // clicking inline button registers without opening modal
                            inlineBtn.addEventListener('click', async function(ev){
                                ev.preventDefault();
                                ev.stopPropagation();
                                if (inlineBtn.disabled) return;
                                inlineBtn.disabled = true;
                                const prevText = inlineBtn.textContent;
                                inlineBtn.textContent = 'Envoi...';
                                try{
                                    const resp = await fetch('includes/seances/inscrire.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                        body: JSON.stringify({ seance_id: s.id, mode: 'self' })
                                    });
                                    if (resp.ok) {
                                        inlineBtn.textContent = 'Inscrit';
                                        inlineBtn.classList.add('disabled');
                                        // if modal open, update its register button
                                        const modalBtn = document.getElementById('indexSeanceRegisterBtn');
                                        if (modalBtn) { modalBtn.textContent = 'Inscrit'; modalBtn.disabled = true; modalBtn.classList.add('disabled'); }
                                    } else {
                                        const err = await resp.json().catch(()=>({}));
                                        inlineBtn.disabled = false;
                                        inlineBtn.textContent = err.error === 'already_registered' ? 'Inscrit' : prevText;
                                        if (err.error === 'already_registered') { inlineBtn.disabled = true; inlineBtn.classList.add('disabled'); }
                                    }
                                }catch(e){
                                    inlineBtn.disabled = false;
                                    inlineBtn.textContent = prevText;
                                }
                            });

                            // clicking info opens modal
                            const a = document.createElement('a');
                            a.className = 'd-flex align-items-center gap-3 flex-grow-1';
                            a.style.textDecoration = 'none';
                            a.style.color = 'inherit';
                            a.href = '#';
                            a.dataset.seanceId = s.id;
                            a.innerHTML = '';
                            a.appendChild(dateBlock);
                            a.appendChild(infoBlock);
                            a.classList.remove('upcoming-item');
                            a.classList.add('flex-grow-1');

                            a.addEventListener('click', async function(e){
                                e.preventDefault();
                                const id = a.dataset.seanceId;
                                try{
                                    const dres = await fetch('includes/seances/detail.php?id=' + encodeURIComponent(id), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                                    if (dres.status === 401) {
                                        console.warn('includes/seances/detail.php -> 401 unauthorized when opening modal');
                                        return;
                                    }
                                    if (!dres.ok) {
                                        console.error('includes/seances/detail.php error when opening modal', dres.status, dres.statusText);
                                        return;
                                    }
                                    const dataDetail = await dres.json();
                                    const sd = dataDetail.seance;

                                    const modalTitle = document.getElementById('indexSeanceModalTitle');
                                    const modalBody = document.getElementById('indexSeanceDetailBody');
                                    const modalActions = document.getElementById('indexSeanceModalActions');

                                    // custom modal layout
                                    modalTitle.textContent = (sd.type_seance || 'Séance');
                                    modalBody.innerHTML = `
                                        <div style="display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
                                            <div style="min-width:96px;padding:0.5rem 0.75rem;border-radius:12px;background:rgba(244,239,226,0.03);text-align:center;">
                                                <div style="font-size:1.6rem;font-weight:700;color:var(--gold);">${sd.date_seance.slice(8,10)}</div>
                                                <div style="font-size:0.9rem;color:var(--paper);">${MOIS[parseInt(sd.date_seance.slice(5,7),10)-1]}</div>
                                            </div>
                                            <div style="flex:1;min-width:200px;">
                                                <div style="margin-bottom:0.5rem;font-weight:600;color:var(--paper);">${(sd.type_seance||'')}</div>
                                                <div style="color:var(--grey);margin-bottom:0.25rem;">${sd.heure_debut.slice(0,5)} - ${sd.heure_fin.slice(0,5)} · ${(sd.lieu_seance||'')}</div>
                                                <div style="color:var(--paper);">Coach : ${(sd.coach||'')}</div>
                                                ${sd.description ? `<div style="margin-top:0.75rem;color:var(--grey);">${(sd.description||'')}</div>` : ''}
                                            </div>
                                        </div>
                                    `;

                                    // modal action button
                                    modalActions.innerHTML = '';
                                    const modalBtn = document.createElement('button');
                                    modalBtn.type = 'button';
                                    modalBtn.className = 'btn btn-wtc-gold rounded-pill';
                                    modalBtn.id = 'indexSeanceRegisterBtn';

                                    if (dataDetail.is_registered) {
                                        modalBtn.textContent = 'Inscrit';
                                        modalBtn.disabled = true;
                                        modalBtn.classList.add('disabled');
                                    } else if (!dataDetail.registration_allowed) {
                                        modalBtn.textContent = 'Inscriptions fermées';
                                        modalBtn.disabled = true;
                                        modalBtn.classList.add('disabled');
                                    } else {
                                        modalBtn.textContent = 'M\'inscrire';
                                        modalBtn.addEventListener('click', async function(ev){
                                            ev.preventDefault();
                                            modalBtn.disabled = true;
                                            modalBtn.textContent = 'Envoi...';
                                            try{
                                                const resp = await fetch('includes/seances/inscrire.php', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                                                    body: JSON.stringify({ seance_id: Number(id), mode: 'self' })
                                                });
                                                if (resp.ok) {
                                                    modalBtn.textContent = 'Inscrit';
                                                    modalBtn.classList.add('disabled');
                                                    inlineBtn.textContent = 'Inscrit'; inlineBtn.disabled = true; inlineBtn.classList.add('disabled');
                                                } else {
                                                    const err = await resp.json().catch(()=>({}));
                                                    modalBtn.disabled = false;
                                                    modalBtn.textContent = 'M\'inscrire';
                                                    if (err.error === 'already_registered') { modalBtn.textContent = 'Inscrit'; modalBtn.disabled = true; modalBtn.classList.add('disabled'); inlineBtn.textContent = 'Inscrit'; inlineBtn.disabled = true; inlineBtn.classList.add('disabled'); }
                                                }
                                            }catch(e){
                                                modalBtn.disabled = false;
                                                modalBtn.textContent = 'M\'inscrire';
                                            }
                                        });
                                    }
                                    modalActions.appendChild(modalBtn);

                                    const modalEl = document.getElementById('indexSeanceModal');
                                    (function whenBootstrapReady(fn){
                                        if (window.bootstrap && window.bootstrap.Modal) return fn();
                                        const t = setInterval(function(){
                                            if (window.bootstrap && window.bootstrap.Modal){ clearInterval(t); fn(); }
                                        }, 50);
                                        setTimeout(function(){ clearInterval(t); }, 5000);
                                    })(function(){
                                        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                                        modal.show();
                                    });

                                }catch(e){
                                    // ignore
                                }
                            });

                            item.appendChild(a);
                            item.appendChild(inlineBtn);
                            list.appendChild(item);

                        }catch(e){
                            // silent
                        }
                    })();
                    </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="horaires">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Programme hebdomadaire</p>
                <h2>Horaires saison 2026-2027</h2>
            </div>

            <div class="season-card">
                <span class="season-pill">Saison 2026-2027</span>
                <p class="season-card__title">Cardio Training / Hyrox / Running-Trail / Préparation physique</p>
                <p class="season-card__sub">Ados à partir de 15 ans &amp; adultes tous niveaux — débutants à confirmés.
                </p>

                <div class="timetable">
                    <div class="timetable-row">
                        <span class="timetable-row__day">Mardi</span>
                        <span class="timetable-row__time">20h15 — 21h30</span>
                        <span class="timetable-row__place">Dojo</span>
                    </div>
                    <div class="timetable-row">
                        <span class="timetable-row__day">Jeudi</span>
                        <span class="timetable-row__time">19h30 — 21h00</span>
                        <span class="timetable-row__place">Stade</span>
                    </div>
                    <div class="timetable-row">
                        <span class="timetable-row__day">Samedi</span>
                        <span class="timetable-row__time">10h00 — 11h30</span>
                        <span class="timetable-row__place">Dojo</span>
                    </div>
                </div>

                <p class="season-note">
                    Retrouve le détail des contenus sur l'onglet <a href="seances.php">Séances</a> pour visualiser les
                    séances de la semaine.
                </p>
            </div>
        </div>
    </section>

    <section class="section" id="liens">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Accès rapide</p>
                <h2>Inscriptions &amp; boutiques</h2>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-12 col-sm-6 col-lg-3">
                    <a class="link-card" href="<?= $heroInscriptionLink ?>" target="_blank" rel="noopener">
                        <div>
                            <p class="link-card__label">Adhésion</p>
                            <p class="link-card__title">Inscription en ligne</p>
                        </div>
                        <span class="link-card__arrow">HelloAsso <i class="bi bi-arrow-up-right"></i></span>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a class="link-card" href="<?= $cardBoutiqueBarresLink ?>" target="_blank" rel="noopener">
                        <div>
                            <p class="link-card__label">Boutique</p>
                            <p class="link-card__title">Barres de céréales Les Craq's</p>
                        </div>
                        <span class="link-card__arrow">HelloAsso <i class="bi bi-arrow-up-right"></i></span>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <a class="link-card" href="<?= $cardBoutiqueVetementsLink ?>" target="_blank" rel="noopener">
                        <div>
                            <p class="link-card__label">Boutique</p>
                            <p class="link-card__title">Vêtements Warriors</p>
                        </div>
                        <span class="link-card__arrow">Market Factory <i class="bi bi-arrow-up-right"></i></span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="sante">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Dossier d'adhésion</p>
                <h2>Questionnaires de santé</h2>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-12 col-md-6">
                    <div class="dossier">
                        <div class="dossier__head">
                            <h3>Je suis majeur</h3>
                            <span class="dossier__stamp">Majeur</span>
                        </div>
                        <div class="dossier__body">
                            <a class="doc-link" href="img/Questionnaire Santé Majeur.pdf" target="_blank">
                                <span>Questionnaire de santé</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                            <a class="doc-link" href="img/Attestation Questionnaire Santé Majeur.pdf" target="_blank">
                                <span>Attestation</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="dossier">
                        <div class="dossier__head">
                            <h3>Je suis mineur</h3>
                            <span class="dossier__stamp">Mineur</span>
                        </div>
                        <div class="dossier__body">
                            <a class="doc-link" href="img/Questionnaire Santé Mineur.pdf" target="_blank">
                                <span>Questionnaire de santé</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                            <a class="doc-link" href="img/Attestation Questionnaire Santé Mineur.pdf" target="_blank">
                                <span>Attestation</span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="adresse">
        <div class="container">
            <div class="section-head">
                <p class="eyebrow">Où nous trouver</p>
                <h2>Dojo Teddy Riner</h2>
            </div>

            <div class="row g-3 g-md-4">
                <div class="col-12 col-lg-5">
                    <div class="address-card">
                        <div>
                            <h3>Complexe sportif Teddy Riner</h3>
                            <p>77720 Mormant</p>
                        </div>
                        <a class="btn btn-wtc-outline rounded-pill align-self-start" href="<?= $cardMapLink ?>"
                            target="_blank" rel="noopener">
                            <i class="bi bi-geo-alt me-1"></i>Cliquez ici si vous êtes perdu
                        </a>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="map-frame">
                        <iframe src="https://www.google.com/maps?q=48.6113915,2.8807517&z=16&output=embed"
                            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            title="Localisation du Dojo Teddy Riner - Warriors Training Club">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="pwa-install-popup" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:2000;"> 
        <div style="max-width:520px;width:92%;background:#0b0b0b;color:var(--paper);border-radius:12px;padding:1.25rem;box-shadow:0 8px 30px rgba(0,0,0,0.6);">
            <h5 style="margin:0 0 0.5rem 0;">Installer la web app</h5>
            <p style="margin:0 0 1rem 0;color:var(--grey);">Vous n'avez pas encore installé la web app. Souhaitez-vous afficher la marche à suivre ?</p>
            <div class="d-flex" style="gap:0.5rem;">
                <button id="pwa-install-close" class="btn btn-wtc-outline rounded-pill">Je sais</button>
                <button id="pwa-install-open" class="btn btn-wtc-gold rounded-pill ms-auto">Comment l'installer ?</button>
            </div>
        </div>
    </div>

    <?php require 'includes/general/footer.php'; ?>

    <!-- Modal for index seance detail (moved out of hero to avoid stacking context issues) -->
    <div class="modal fade wtc-modal" id="indexSeanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content wtc-modal__content">
                <div class="modal-header wtc-modal__header">
                    <h5 class="modal-title" id="indexSeanceModalTitle">Détail de la séance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="seance-detail" id="indexSeanceDetailBody"></div>
                </div>
                <div class="modal-footer wtc-modal__footer" id="indexSeanceModalActions"></div>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/pwa-tutorials.js"></script>

</body>

</html>
