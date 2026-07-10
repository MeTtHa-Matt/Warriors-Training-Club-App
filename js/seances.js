(function () {
  "use strict";

  const MOIS = [
    "Janvier",
    "Février",
    "Mars",
    "Avril",
    "Mai",
    "Juin",
    "Juillet",
    "Août",
    "Septembre",
    "Octobre",
    "Novembre",
    "Décembre",
  ];
  const JOURS = [
    "dimanche",
    "lundi",
    "mardi",
    "mercredi",
    "jeudi",
    "vendredi",
    "samedi",
  ];

  const state = {
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1, // 1-based
    monthSeances: [],
    currentSeanceId: null,
    currentIsRegistered: false,
  };

  function todayStr() {
    const d = new Date();
    return (
      d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate())
    );
  }

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function formatDateFr(dateStr) {
    const [y, m, d] = dateStr.slice(0, 10).split("-").map(Number);
    const dateObj = new Date(y, m - 1, d);
    const jour = JOURS[dateObj.getDay()];
    return (
      jour.charAt(0).toUpperCase() +
      jour.slice(1) +
      " " +
      d +
      " " +
      MOIS[m - 1].toLowerCase() +
      " " +
      y
    );
  }

  function formatHeure(t) {
    return t ? t.slice(0, 5) : "";
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  function daysInMonth(year, month) {
    return new Date(year, month, 0).getDate();
  }

  function firstWeekday(year, month) {
    const d = new Date(year, month - 1, 1).getDay(); // 0=dim..6=sam
    return d === 0 ? 7 : d; // 1=lun..7=dim
  }

  function getModal(id) {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
  }

  function switchModal(fromId, toId) {
    const fromEl = document.getElementById(fromId);
    const fromModal = bootstrap.Modal.getInstance(fromEl);
    function onHidden() {
      fromEl.removeEventListener("hidden.bs.modal", onHidden);
      getModal(toId).show();
    }
    fromEl.addEventListener("hidden.bs.modal", onHidden);
    if (fromModal) fromModal.hide();
    else getModal(toId).show();
  }

  function showToast(message, isError) {
    const toastEl = document.getElementById("wtcToast");
    document.getElementById("wtcToastBody").textContent = message;
    toastEl.classList.toggle("wtc-toast--error", !!isError);
    toastEl.classList.toggle("wtc-toast--success", !isError);
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 }).show();
  }

  async function apiRequest(url, options = {}) {
    const method = options.method || "GET";
    const headers = { Accept: "application/json", ...(options.headers || {}) };
    const fetchOptions = { method, headers };

    if (options.body !== undefined) {
      headers["Content-Type"] = "application/json";
      fetchOptions.body = JSON.stringify(options.body);
    }

    const res = await fetch(url, fetchOptions);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw data;
    return data;
  }

  async function apiGet(url) {
    return apiRequest(url);
  }

  async function apiPost(url, body) {
    return apiRequest(url, { method: "POST", body });
  }

  async function apiDelete(url, body) {
    return apiRequest(url, { method: "DELETE", body });
  }

  async function loadMonth(year, month) {
    try {
      const data = await apiGet(
        `includes/seances/list_month.php?year=${year}&month=${month}`,
      );
      state.year = data.year;
      state.month = data.month;
      state.monthSeances = data.seances;
      renderCalendar();
    } catch (e) {
      showToast("Impossible de charger le calendrier.", true);
    }
  }

  function renderCalendar() {
    document.getElementById("calTitle").textContent =
      `${MOIS[state.month - 1]} ${state.year}`;

    const countsByDate = {};
    state.monthSeances.forEach((s) => {
      const d = s.date_seance.slice(0, 10);
      countsByDate[d] = (countsByDate[d] || 0) + 1;
    });

    const grid = document.getElementById("calGrid");
    grid.innerHTML = "";

    const blanks = firstWeekday(state.year, state.month) - 1;
    for (let i = 0; i < blanks; i++) {
      const empty = document.createElement("div");
      empty.className = "calendar-cell calendar-cell--empty";
      grid.appendChild(empty);
    }

    const total = daysInMonth(state.year, state.month);
    const today = todayStr();

    for (let day = 1; day <= total; day++) {
      const dateStr = `${state.year}-${pad(state.month)}-${pad(day)}`;
      const count = countsByDate[dateStr] || 0;

      const cell = document.createElement("button");
      cell.type = "button";
      cell.className = "calendar-cell";
      if (dateStr === today) cell.classList.add("calendar-cell--today");
      if (count > 0) cell.classList.add("calendar-cell--has-seance");

      cell.innerHTML =
        `<span class="calendar-cell__day">${day}</span>` +
        (count > 0 ? `<span class="calendar-cell__badge">${count}</span>` : "");

      if (count > 0) {
        cell.addEventListener("click", () => openDay(dateStr));
      } else {
        cell.disabled = true;
      }

      grid.appendChild(cell);
    }
  }

  function openDay(dateStr) {
    const sessions = state.monthSeances.filter(
      (s) => s.date_seance.slice(0, 10) === dateStr,
    );

    if (sessions.length === 1) {
      openSeanceDetail(sessions[0].id);
      return;
    }

    document.getElementById("dayModalTitle").textContent =
      formatDateFr(dateStr);
    const body = document.getElementById("dayModalBody");
    body.innerHTML = sessions
      .map(
        (s) => `
            <button type="button" class="day-seance-item" data-id="${s.id}">
                <span class="day-seance-item__time">${formatHeure(s.heure_debut)} - ${formatHeure(s.heure_fin)}</span>
                <span class="day-seance-item__type">${escapeHtml(s.type_seance)}</span>
                <i class="bi bi-chevron-right"></i>
            </button>
        `,
      )
      .join("");

    body.querySelectorAll(".day-seance-item").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = parseInt(btn.dataset.id, 10);
        switchModal("dayModal", "seanceModal");
        loadSeanceDetail(id);
      });
    });

    getModal("dayModal").show();
  }

  async function loadUpcoming() {
    try {
      const data = await apiGet("includes/seances/upcoming.php?limit=30");
      renderUpcoming(data.seances);
    } catch (e) {
      showToast("Impossible de charger les prochaines séances.", true);
    }
  }

  function renderUpcoming(seances) {
    const list = document.getElementById("upcomingList");
    const empty = document.getElementById("upcomingEmpty");

    list.querySelectorAll(".upcoming-item").forEach((el) => el.remove());

    if (!seances.length) {
      empty.style.display = "";
      return;
    }
    empty.style.display = "none";

    seances.forEach((s) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "upcoming-item";
      item.innerHTML = `
                <div class="upcoming-item__date">
                    <span class="upcoming-item__day">${s.date_seance.slice(8, 10)}</span>
                    <span class="upcoming-item__month">${MOIS[parseInt(s.date_seance.slice(5, 7), 10) - 1].slice(0, 3)}</span>
                </div>
                <div class="upcoming-item__info">
                    <p class="upcoming-item__type">${escapeHtml(s.type_seance)}</p>
                    <p class="upcoming-item__meta">${formatHeure(s.heure_debut)} - ${formatHeure(s.heure_fin)} · ${escapeHtml(s.lieu_seance)}</p>
                </div>
                <i class="bi bi-chevron-right upcoming-item__arrow"></i>
            `;
      item.addEventListener("click", () => openSeanceDetail(s.id));
      list.appendChild(item);
    });
  }

  function openSeanceDetail(id) {
    getModal("seanceModal").show();
    loadSeanceDetail(id);
  }

  async function loadSeanceDetail(id) {
    const body = document.getElementById("seanceDetailBody");
    const actions = document.getElementById("seanceModalActions");
    body.innerHTML = '<p class="seance-detail__loading">Chargement…</p>';
    actions.innerHTML = "";

    try {
      const data = await apiGet(`includes/seances/detail.php?id=${id}`);
      const s = data.seance;

      state.currentSeanceId = id;
      state.currentIsRegistered = data.is_registered;

      body.innerHTML = `
                <div class="seance-detail__row">
                    <span class="seance-detail__label">Date</span>
                    <span class="seance-detail__value">${formatDateFr(s.date_seance)}<br>${formatHeure(s.heure_debut)} – ${formatHeure(s.heure_fin)}</span>
                </div>
                <div class="seance-detail__row">
                    <span class="seance-detail__label">Type</span>
                    <span class="seance-detail__value">${escapeHtml(s.type_seance)}</span>
                </div>
                <div class="seance-detail__row">
                    <span class="seance-detail__label">Coach</span>
                    <span class="seance-detail__value">${escapeHtml(s.coach)}</span>
                </div>
                <div class="seance-detail__row">
                    <span class="seance-detail__label">Lieu de la séance</span>
                    <span class="seance-detail__value">${escapeHtml(s.lieu_seance)}</span>
                </div>
                <div class="seance-detail__row">
                    <span class="seance-detail__label">Lieu de rendez-vous</span>
                    <span class="seance-detail__value">${escapeHtml(s.lieu_rdv)}</span>
                </div>
                ${
                  s.description
                    ? `
                <div class="seance-detail__row seance-detail__row--full">
                    <span class="seance-detail__label">Descriptif</span>
                    <p class="seance-detail__value">${escapeHtml(s.description)}</p>
                </div>`
                    : ""
                }
            `;

      let actionsHtml = "";
      actionsHtml += `<button type="button" class="btn btn-wtc-outline rounded-pill" id="btnVoirInscrits">Voir les inscrits</button>`;
      if (data.can_manage) {
        actionsHtml += `<button type="button" class="btn btn-wtc-outline rounded-pill" id="btnModifierSeance">Modifier</button>`;
        actionsHtml += `<button type="button" class="btn btn-wtc-outline rounded-pill" id="btnSupprimerSeance" style="color: #d32f2f;">Supprimer</button>`;
      }
      if (data.has_inscriptions) {
        actionsHtml += `<button type="button" class="btn btn-wtc-outline rounded-pill" id="btnMesInscriptions">Voir mes inscriptions</button>`;
      }
      if (data.is_registered) {
        actionsHtml += `<button type="button" class="btn btn-wtc-gold rounded-pill" id="btnSInscrire">S'inscrire</button>`;
      } else if (data.registration_allowed) {
        actionsHtml += `<button type="button" class="btn btn-wtc-gold rounded-pill" id="btnSInscrire">S'inscrire</button>`;
      } else {
        actionsHtml += `<button type="button" class="btn btn-wtc-outline rounded-pill" disabled>Inscriptions fermées</button>`;
      }
      actions.innerHTML = actionsHtml;

      const btnVoirInscrits = document.getElementById("btnVoirInscrits");
      if (btnVoirInscrits)
        btnVoirInscrits.addEventListener("click", () => openInscrits(id));

      if (data.can_manage) {
        const btnModifierSeance = document.getElementById("btnModifierSeance");
        if (btnModifierSeance)
          btnModifierSeance.addEventListener("click", () =>
            openModifierSeance(s),
          );

        const btnSupprimerSeance =
          document.getElementById("btnSupprimerSeance");
        if (btnSupprimerSeance)
          btnSupprimerSeance.addEventListener("click", () =>
            handleSupprimerSeance(id, s),
          );
      }

      const btnMesInscriptionsEl =
        document.getElementById("btnMesInscriptions");
      if (btnMesInscriptionsEl)
        btnMesInscriptionsEl.addEventListener("click", () =>
          openMesInscriptions(id),
        );

      const btnSInscrire = document.getElementById("btnSInscrire");
      if (btnSInscrire && !btnSInscrire.disabled) {
        btnSInscrire.addEventListener("click", () => handleSInscrire());
      }
    } catch (e) {
      body.innerHTML =
        '<p class="seance-detail__loading">Impossible de charger cette séance.</p>';
    }
  }

  function handleSInscrire() {
    if (state.currentIsRegistered) {
      switchModal("seanceModal", "inscrireQuelquunModal");
    } else {
      switchModal("seanceModal", "choixInscriptionModal");
    }
  }

  function openModifierSeance(seance) {
    // Remplir le formulaire de modification
    document.getElementById("editSeanceId").value = state.currentSeanceId;
    document.getElementById("editSeanceDate").value = seance.date_seance;
    document.getElementById("editSeanceHeureDebut").value =
      seance.heure_debut.slice(0, 5);
    document.getElementById("editSeanceHeureFin").value =
      seance.heure_fin.slice(0, 5);
    document.getElementById("editSeanceType").value = seance.type_seance;
    document.getElementById("editSeanceCoach").value = seance.coach;
    document.getElementById("editSeanceLieu").value = seance.lieu_seance;
    document.getElementById("editSeanceRdv").value = seance.lieu_rdv;
    document.getElementById("editSeanceDescription").value =
      seance.description || "";

    switchModal("seanceModal", "editSeanceModal");
  }

  function handleSupprimerSeance(id, seance) {
    const confirmed = window.confirm(
      `Êtes-vous sûr de vouloir supprimer la séance du ${formatDateFr(seance.date_seance)} ?`,
    );
    if (!confirmed) return;

    supprimerSeance(id);
  }

  async function supprimerSeance(id) {
    try {
      await apiPost("includes/seances/supprimer_seance.php", { id });
      getModal("seanceModal").hide();
      showToast("La séance a bien été supprimée.");
      loadMonth(state.year, state.month);
      loadUpcoming();
    } catch (e) {
      showToast("Impossible de supprimer la séance.", true);
    }
  }

  async function openInscrits(id) {
    getModal("inscritsModal").show();
    const body = document.getElementById("inscritsBody");
    body.innerHTML = '<p class="seance-detail__loading">Chargement…</p>';

    try {
      const data = await apiGet(
        `includes/seances/inscrits.php?seance_id=${id}`,
      );
      if (!data.inscrits.length) {
        body.innerHTML =
          '<p class="upcoming-empty">Aucun inscrit pour le moment.</p>';
        return;
      }
      body.innerHTML =
        `<ul class="inscrits-list">` +
        data.inscrits
          .map(
            (i) => `
                <li class="inscrits-list__item">
                    <span class="inscrits-list__name">${escapeHtml(i.firstname)} ${escapeHtml(i.lastname)}</span>
                    <span class="inscrits-list__meta">Inscrit par ${escapeHtml(i.par_firstname)} ${escapeHtml(i.par_lastname)}</span>
                </li>
            `,
          )
          .join("") +
        `</ul>`;
    } catch (e) {
      body.innerHTML =
        '<p class="upcoming-empty">Impossible de charger la liste des inscrits.</p>';
    }
  }

  async function openMesInscriptions(id) {
    getModal("mesInscriptionsModal").show();
    const body = document.getElementById("mesInscriptionsBody");
    body.innerHTML = '<p class="seance-detail__loading">Chargement…</p>';

    try {
      const data = await apiGet(
        `includes/seances/mes_inscriptions.php?seance_id=${id}`,
      );
      if (!data.inscriptions.length) {
        body.innerHTML =
          '<p class="upcoming-empty">Tu n\'as inscrit personne pour cette séance.</p>';
        return;
      }
      body.innerHTML =
        `<ul class="inscrits-list">` +
        data.inscriptions
          .map(
            (i) => `
                <li class="inscrits-list__item inscrits-list__item--action">
                    <span class="inscrits-list__name">${escapeHtml(i.firstname)} ${escapeHtml(i.lastname)}</span>
                    <button type="button" class="btn btn-wtc-outline rounded-pill btn-sm" data-inscription-id="${i.id}" data-action="delete">
                        Désinscrire
                    </button>
                </li>
            `,
          )
          .join("") +
        `</ul>`;

      body.querySelectorAll('[data-action="delete"]').forEach((button) => {
        button.addEventListener("click", async () => {
          const inscriptionId = parseInt(button.dataset.inscriptionId, 10);
          if (!inscriptionId) return;

          try {
            await apiDelete("includes/seances/mes_inscriptions.php", {
              inscription_id: inscriptionId,
            });
            showToast("L'inscription a bien été supprimée.");
            openMesInscriptions(id);
            loadMonth(state.year, state.month);
            loadUpcoming();
            if (state.currentSeanceId === id) {
              loadSeanceDetail(id);
            }
          } catch (e) {
            showToast("Impossible de supprimer cette inscription.", true);
          }
        });
      });
    } catch (e) {
      body.innerHTML =
        '<p class="upcoming-empty">Impossible de charger tes inscriptions.</p>';
    }
  }

  const btnMInscrire = document.getElementById("btnMInscrire");
  if (btnMInscrire) {
    btnMInscrire.addEventListener("click", async () => {
      if (!state.currentSeanceId) return;
      try {
        await apiPost("includes/seances/inscrire.php", {
          seance_id: state.currentSeanceId,
          mode: "self",
        });
        state.currentIsRegistered = true;
        getModal("choixInscriptionModal").hide();
        showToast("Tu es bien inscrit à la séance.");
        loadMonth(state.year, state.month);
      } catch (e) {
        showToast(
          e.error === "already_registered"
            ? "Tu es déjà inscrit à cette séance."
            : e.error === "registration_closed"
              ? "Inscriptions fermées : la séance a déjà commencé."
              : "Impossible de t'inscrire.",
          true,
        );
      }
    });
  }

  const btnInscrireQuelquunFromChoix = document.getElementById(
    "btnInscrireQuelquunFromChoix",
  );
  if (btnInscrireQuelquunFromChoix) {
    btnInscrireQuelquunFromChoix.addEventListener("click", () => {
      switchModal("choixInscriptionModal", "inscrireQuelquunModal");
    });
  }

  const formInscrireQuelquun = document.getElementById("formInscrireQuelquun");
  if (formInscrireQuelquun) {
    formInscrireQuelquun.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!state.currentSeanceId) return;

      const firstname = document.getElementById("guestFirstname").value.trim();
      const lastname = document.getElementById("guestLastname").value.trim();
      const alertBox = document.getElementById("inscrireQuelquunAlert");
      alertBox.style.display = "none";

      if (!firstname || !lastname) {
        alertBox.textContent = "Merci de renseigner un prénom et un nom.";
        alertBox.style.display = "";
        return;
      }

      try {
        await apiPost("includes/seances/inscrire.php", {
          seance_id: state.currentSeanceId,
          mode: "other",
          firstname,
          lastname,
        });
        document.getElementById("formInscrireQuelquun").reset();
        getModal("inscrireQuelquunModal").hide();
        showToast(`${firstname} ${lastname} a bien été inscrit(e).`);
        loadMonth(state.year, state.month);
      } catch (e2) {
        alertBox.textContent = "Impossible d'inscrire cette personne.";
        alertBox.style.display = "";
      }
    });
  }

  const formEditSeance = document.getElementById("formEditSeance");
  if (formEditSeance) {
    formEditSeance.addEventListener("submit", async (e) => {
      e.preventDefault();
      const alertBox = document.getElementById("editSeanceAlert");
      alertBox.style.display = "none";

      const id = parseInt(document.getElementById("editSeanceId").value, 10);
      const dateSeance = document.getElementById("editSeanceDate").value;
      const heureDebut = document.getElementById("editSeanceHeureDebut").value;
      const heureFin = document.getElementById("editSeanceHeureFin").value;
      const typeSeance = document.getElementById("editSeanceType").value.trim();
      const coach = document.getElementById("editSeanceCoach").value.trim();
      const lieu = document.getElementById("editSeanceLieu").value.trim();
      const rdv = document.getElementById("editSeanceRdv").value.trim();
      const description = document
        .getElementById("editSeanceDescription")
        .value.trim();

      if (!dateSeance || !heureDebut || !heureFin || !typeSeance) {
        alertBox.textContent =
          "Tous les champs obligatoires doivent être remplis.";
        alertBox.style.display = "";
        return;
      }

      try {
        await apiPost("includes/seances/modifier_seance.php", {
          id,
          date_seance: dateSeance,
          heure_debut: heureDebut,
          heure_fin: heureFin,
          type_seance: typeSeance,
          coach,
          lieu_seance: lieu,
          lieu_rdv: rdv,
          description,
        });
        formEditSeance.reset();
        getModal("editSeanceModal").hide();
        showToast("La séance a bien été modifiée.");
        loadMonth(state.year, state.month);
        loadUpcoming();
        if (state.currentSeanceId) {
          loadSeanceDetail(state.currentSeanceId);
        }
      } catch (e2) {
        alertBox.textContent =
          e2.messages && e2.messages.length
            ? e2.messages.join(" ")
            : "Impossible de modifier la séance.";
        alertBox.style.display = "";
      }
    });
  }

  const newTypeInput = document.getElementById("newType");
  const newTypeButtons = document.querySelectorAll(
    ".wtc-choice-pill[data-type-group='new']",
  );
  const templateSessionTypeInput = document.getElementById(
    "templateSessionType",
  );
  const templateSessionTypeButtons = document.querySelectorAll(
    ".wtc-choice-pill[data-type-group='template']",
  );
  const templateSessionTypeAutre = document.getElementById(
    "templateSessionTypeAutre",
  );
  const templateState = { templates: [] };

  function toggleChoiceButtons(buttons, selectedValue, inputEl, otherEl) {
    buttons.forEach((button) => {
      button.classList.toggle(
        "is-active",
        button.dataset.value === selectedValue,
      );
    });
    if (otherEl) {
      otherEl.style.display = selectedValue === "__autre__" ? "" : "none";
    }
  }

  async function loadTemplates() {
    try {
      const data = await apiGet("includes/seances/templates.php");
      templateState.templates = data.templates || [];
      renderTemplateList();
    } catch (e) {
      templateState.templates = [];
      renderTemplateList();
    }
  }

  function renderTemplateList() {
    const list = document.getElementById("templateManagerList");
    if (!list) return;

    if (!templateState.templates.length) {
      list.innerHTML =
        '<p class="upcoming-empty">Aucun template pour le moment.</p>';
      return;
    }

    list.innerHTML = templateState.templates
      .map((template) => {
        const sessions =
          typeof template.sessions === "string"
            ? JSON.parse(template.sessions)
            : template.sessions || [];
        const weekdayLabels = {
          1: "Lun",
          2: "Mar",
          3: "Mer",
          4: "Jeu",
          5: "Ven",
          6: "Sam",
          7: "Dim",
        };
        const weekdaySet = new Set();
        sessions.forEach((s) => {
          if (s.weekday) weekdaySet.add(s.weekday);
        });
        const weekdaysText = Array.from(weekdaySet)
          .sort()
          .map((day) => weekdayLabels[day] || day)
          .join(", ");

        const firstSession = sessions[0] || {};
        const timeStart = firstSession.heure_debut
          ? firstSession.heure_debut.slice(0, 5)
          : "–";
        const timeEnd = firstSession.heure_fin
          ? firstSession.heure_fin.slice(0, 5)
          : "–";
        const typeSeance = firstSession.type_seance || "–";

        return `
          <div class="p-3 rounded-4 border" style="border-color: rgba(244,239,226,0.15); background: rgba(244,239,226,0.03);">
            <div class="d-flex justify-content-between align-items-start gap-3">
              <div>
                <p class="mb-1 fw-semibold">${escapeHtml(template.name)}</p>
                <p class="auth-hint mb-1">${escapeHtml(weekdaysText || "–")}</p>
                <p class="auth-hint mb-0">${escapeHtml(typeSeance)} · ${timeStart} - ${timeEnd}</p>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-wtc-gold rounded-pill btn-sm" data-action="apply" data-id="${template.id}">Appliquer</button>
                <button type="button" class="btn btn-wtc-outline rounded-pill btn-sm" data-action="edit" data-id="${template.id}">Modifier</button>
                <button type="button" class="btn btn-wtc-outline rounded-pill btn-sm" data-action="remove" data-id="${template.id}">Retirer</button>
              </div>
            </div>
          </div>`;
      })
      .join("");

    list.querySelectorAll("[data-action='apply']").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const confirmed = window.confirm(
          "Créer les séances à partir de ce template ?",
        );
        if (!confirmed) return;

        try {
          const result = await apiPost("includes/seances/apply_template.php", {
            template_id: Number(btn.dataset.id),
            mode: "apply",
          });
          showToast(
            result.created
              ? `Template appliqué : ${result.created} séance(s) créée(s).`
              : "Aucune séance supplémentaire à créer.",
          );
          loadMonth(state.year, state.month);
          loadUpcoming();
        } catch (e) {
          showToast("Impossible d’appliquer le template.", true);
        }
      });
    });

    list.querySelectorAll("[data-action='remove']").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const confirmed = window.confirm(
          "Retirer ce template supprimera aussi les séances déjà générées à partir de ce modèle. Continuer ?",
        );
        if (!confirmed) return;

        try {
          const result = await apiPost("includes/seances/apply_template.php", {
            template_id: Number(btn.dataset.id),
            mode: "remove",
          });
          showToast(
            result.removed
              ? `${result.removed} séance(s) supprimée(s).`
              : "Aucune séance à supprimer.",
          );
          loadTemplates();
          loadMonth(state.year, state.month);
          loadUpcoming();
        } catch (e) {
          showToast("Impossible de supprimer le template.", true);
        }
      });
    });

    list.querySelectorAll("[data-action='edit']").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const templateId = Number(btn.dataset.id);
        const template = templateState.templates.find(
          (t) => t.id === templateId,
        );
        if (!template) return;

        // Charger les donnees du template dans le formulaire
        loadTemplateForEdit(template);
      });
    });
  }

  function loadTemplateForEdit(template) {
    // Remplir les donnees du template
    document.getElementById("templateName").value = template.name;

    // Charger les seances du template
    const sessions =
      typeof template.sessions === "string"
        ? JSON.parse(template.sessions)
        : template.sessions;
    templateSessions.length = 0;
    sessions.forEach((session) => {
      templateSessions.push(session);
    });
    renderTemplateSessionsList();

    // Marquer le template pour la modification
    document.getElementById("templateName").dataset.templateId = template.id;

    // Ouvrir le modal
    getModal("newTemplateModal").show();
  }

  function toggleTypeChoice(buttons, inputEl, otherEl) {
    const selected = inputEl.value;
    toggleChoiceButtons(buttons, selected, inputEl, otherEl);
  }

  if (newTypeInput) {
    newTypeButtons.forEach((button) => {
      button.addEventListener("click", () => {
        newTypeInput.value = button.dataset.value;
        toggleTypeChoice(
          newTypeButtons,
          newTypeInput,
          document.getElementById("newTypeAutre"),
        );
      });
    });

    templateSessionTypeButtons.forEach((button) => {
      button.addEventListener("click", () => {
        templateSessionTypeInput.value = button.dataset.value;
        toggleTypeChoice(
          templateSessionTypeButtons,
          templateSessionTypeInput,
          templateSessionTypeAutre,
        );
      });
    });
  }

  const btnAjouterSeance = document.getElementById("btnAjouterSeance");
  if (btnAjouterSeance) {
    btnAjouterSeance.addEventListener("click", () => {
      getModal("ajouterSeanceModal").show();
    });
  }

  const formAjouterSeance = document.getElementById("formAjouterSeance");
  if (formAjouterSeance) {
    formAjouterSeance.addEventListener("submit", async (e) => {
      e.preventDefault();
      const alertBox = document.getElementById("ajouterSeanceAlert");
      alertBox.style.display = "none";

      const typeValue =
        newTypeInput.value === "__autre__"
          ? document.getElementById("newTypeAutre").value.trim()
          : newTypeInput.value;

      const payload = {
        date_seance: document.getElementById("newDate").value,
        heure_debut: document.getElementById("newHeureDebut").value,
        heure_fin: document.getElementById("newHeureFin").value,
        type_seance: typeValue,
        coach: document.getElementById("newCoach").value.trim(),
        lieu_seance: document.getElementById("newLieuSeance").value.trim(),
        lieu_rdv: document.getElementById("newLieuRdv").value.trim(),
        description: document.getElementById("newDescription").value.trim(),
      };

      try {
        await apiPost("includes/seances/ajouter.php", payload);
        document.getElementById("formAjouterSeance").reset();
        newTypeInput.value = "";
        toggleTypeChoice(
          newTypeButtons,
          newTypeInput,
          document.getElementById("newTypeAutre"),
        );
        document.getElementById("newTypeAutre").style.display = "none";
        getModal("ajouterSeanceModal").hide();
        showToast("La séance a bien été créée.");
        loadMonth(state.year, state.month);
        loadUpcoming();
      } catch (e2) {
        const messages =
          e2.messages && e2.messages.length
            ? e2.messages.join(" ")
            : "Impossible de créer la séance.";
        alertBox.textContent = messages;
        alertBox.style.display = "";
      }
    });
  }

  const templateManagerBtn = document.getElementById("btnTemplateManager");
  const templateManagerModal = document.getElementById("templateManagerModal");
  const newTemplateBtn = document.getElementById("btnNewTemplate");
  const newTemplateModal = document.getElementById("newTemplateModal");
  const templateFormAlert = document.getElementById("templateFormAlert");
  const templateSessionsList = document.getElementById("templateSessionsList");
  const addTemplateSessionBtn = document.getElementById(
    "btnAddTemplateSession",
  );
  const templateSessionModal = document.getElementById("templateSessionModal");
  const templateSessionForm = document.getElementById("formTemplateSession");
  const templateSessions = [];

  function renderTemplateSessionsList() {
    if (!templateSessionsList) return;

    if (!templateSessions.length) {
      templateSessionsList.innerHTML =
        '<p class="upcoming-empty">Aucune séance ajoutée pour le moment.</p>';
      return;
    }

    templateSessionsList.innerHTML = templateSessions
      .map(
        (session, index) => `
        <div class="p-3 rounded-4 border" style="border-color: rgba(244,239,226,0.15); background: rgba(244,239,226,0.03);">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <p class="mb-1 fw-semibold">Séance ${index + 1}</p>
              <p class="auth-hint mb-1">${escapeHtml(session.date)} · ${escapeHtml(session.heure_debut)} - ${escapeHtml(session.heure_fin)}</p>
              <p class="auth-hint mb-0">${escapeHtml(session.type_seance || "Séance")}</p>
            </div>
            <button type="button" class="btn btn-wtc-outline rounded-pill btn-sm" data-remove-session="${index}">Retirer</button>
          </div>
        </div>`,
      )
      .join("");

    templateSessionsList
      .querySelectorAll("[data-remove-session]")
      .forEach((button) => {
        button.addEventListener("click", () => {
          const index = Number(button.dataset.removeSession);
          templateSessions.splice(index, 1);
          renderTemplateSessionsList();
        });
      });
  }

  function resetTemplateForm() {
    const form = document.getElementById("formCreateTemplate");
    if (form) {
      form.reset();
    }

    const templateNameInput = document.getElementById("templateName");
    if (templateNameInput) {
      delete templateNameInput.dataset.templateId;
    }

    templateSessions.length = 0;
    renderTemplateSessionsList();

    if (templateFormAlert) {
      templateFormAlert.style.display = "none";
    }
  }

  if (templateManagerBtn && templateManagerModal) {
    templateManagerBtn.addEventListener("click", () => {
      getModal("templateManagerModal").show();
      loadTemplates().catch(() => {
        /* Le modal reste ouvert même si le chargement des templates échoue. */
      });
    });
  }

  if (newTemplateBtn && newTemplateModal) {
    newTemplateBtn.addEventListener("click", () => {
      resetTemplateForm();

      const managerModalEl = document.getElementById("templateManagerModal");
      const managerModal = managerModalEl
        ? bootstrap.Modal.getOrCreateInstance(managerModalEl)
        : null;
      const createModal = bootstrap.Modal.getOrCreateInstance(newTemplateModal);

      if (
        managerModal &&
        managerModalEl &&
        managerModalEl.classList.contains("show")
      ) {
        const onHidden = () => {
          managerModalEl.removeEventListener("hidden.bs.modal", onHidden);
          createModal.show();
        };
        managerModalEl.addEventListener("hidden.bs.modal", onHidden, {
          once: true,
        });
        managerModal.hide();
      } else {
        createModal.show();
      }
    });
  }

  if (addTemplateSessionBtn) {
    addTemplateSessionBtn.addEventListener("click", () => {
      if (templateSessionForm) {
        templateSessionForm.reset();
      }
      if (templateSessionTypeInput) {
        templateSessionTypeInput.value = "";
      }
      if (templateSessionTypeAutre) {
        templateSessionTypeAutre.style.display = "none";
      }
      if (templateSessionModal) {
        if (newTemplateModal && newTemplateModal.classList.contains("show")) {
          switchModal("newTemplateModal", "templateSessionModal");
        } else {
          getModal("templateSessionModal").show();
        }
      }
    });
  }

  if (templateSessionForm) {
    templateSessionForm.addEventListener("submit", (e) => {
      e.preventDefault();

      const alertBox = document.getElementById("templateSessionAlert");
      if (alertBox) {
        alertBox.style.display = "none";
      }

      const weekday =
        (document.getElementById("templateSessionWeekday") || {}).value || "";
      const heureDebut =
        (document.getElementById("templateSessionHeureDebut") || {}).value ||
        "";
      const heureFin =
        (document.getElementById("templateSessionHeureFin") || {}).value || "";
      const typeValue =
        templateSessionTypeInput &&
        templateSessionTypeInput.value === "__autre__"
          ? (templateSessionTypeAutre || {}).value.trim()
          : (templateSessionTypeInput || {}).value.trim();
      const coach = (
        (document.getElementById("templateSessionCoach") || {}).value || ""
      ).trim();
      const lieu = (
        (document.getElementById("templateSessionLieu") || {}).value || ""
      ).trim();
      const rdv = (
        (document.getElementById("templateSessionRdv") || {}).value || ""
      ).trim();
      const description = (
        (document.getElementById("templateSessionDescription") || {}).value ||
        ""
      ).trim();

      if (!weekday || !heureDebut || !heureFin || !typeValue) {
        if (alertBox) {
          alertBox.textContent =
            "Veuillez remplir tous les champs obligatoires.";
          alertBox.style.display = "";
        } else {
          alert("Veuillez remplir tous les champs obligatoires.");
        }
        return;
      }

      templateSessions.push({
        weekday: parseInt(weekday, 10),
        heure_debut: heureDebut,
        heure_fin: heureFin,
        type_seance: typeValue,
        coach,
        lieu_seance: lieu,
        lieu_rdv: rdv,
        description,
      });

      renderTemplateSessionsList();
      templateSessionForm.reset();
      if (templateSessionTypeInput) {
        templateSessionTypeInput.value = "";
      }
      if (templateSessionTypeAutre) {
        templateSessionTypeAutre.style.display = "none";
      }
      if (newTemplateModal) {
        switchModal("templateSessionModal", "newTemplateModal");
      } else {
        getModal("templateSessionModal").hide();
      }
    });
  }

  const formCreateTemplate = document.getElementById("formCreateTemplate");
  if (formCreateTemplate) {
    formCreateTemplate.addEventListener("submit", async (e) => {
      e.preventDefault();
      templateFormAlert.style.display = "none";

      if (!templateSessions.length) {
        templateFormAlert.textContent =
          "Ajoute au moins une séance au template avant de l’enregistrer.";
        templateFormAlert.style.display = "";
        return;
      }

      const payload = {
        name: document.getElementById("templateName").value.trim(),
        sessions: templateSessions,
      };

      const templateId = parseInt(
        document.getElementById("templateName").dataset.templateId || 0,
      );
      if (templateId > 0) {
        payload.id = templateId;
      }

      try {
        await apiPost("includes/seances/templates.php", payload);

        if (templateId > 0) {
          await apiPost("includes/seances/update_template_seances.php", {
            template_id: templateId,
          });
        }

        formCreateTemplate.reset();
        delete document.getElementById("templateName").dataset.templateId;
        templateSessions.length = 0;
        renderTemplateSessionsList();
        getModal("newTemplateModal").hide();
        showToast(
          templateId > 0
            ? "Le template a bien été modifié."
            : "Le template a bien été créé.",
        );
        loadTemplates();
        loadMonth(state.year, state.month);
        loadUpcoming();
      } catch (e2) {
        const messages =
          e2.messages && e2.messages.length
            ? e2.messages.join(" ")
            : "Impossible de créer le template.";
        templateFormAlert.textContent = messages;
        templateFormAlert.style.display = "";
      }
    });
  }

  const calPrev = document.getElementById("calPrev");
  if (calPrev) {
    calPrev.addEventListener("click", () => {
      let { year, month } = state;
      month -= 1;
      if (month < 1) {
        month = 12;
        year -= 1;
      }
      loadMonth(year, month);
    });
  }

  const calNext = document.getElementById("calNext");
  if (calNext) {
    calNext.addEventListener("click", () => {
      let { year, month } = state;
      month += 1;
      if (month > 12) {
        month = 1;
        year += 1;
      }
      loadMonth(year, month);
    });
  }

  loadMonth(state.year, state.month);
  loadUpcoming();
})();
