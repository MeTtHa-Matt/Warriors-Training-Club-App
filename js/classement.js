document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('rankingApp');
    if (!app) return;

    const apiUrl = app.dataset.api;
    const csrfToken = app.dataset.csrf || '';
    const drawer = document.getElementById('categoryDrawer');
    const categoryContent = document.getElementById('categoryContent');
    const categoryList = document.getElementById('categoryList');
    const personalBlock = document.getElementById('personalBlock');
    const personalBestList = document.getElementById('personalBestList');
    const createCategoryButton = document.getElementById('openCreateCategory');
    const categoryActions = document.getElementById('categoryActions');
    const subcategorySection = document.getElementById('subcategorySection');
    const subcategoryList = document.getElementById('subcategoryList');
    const categoryHasSubcategories = document.getElementById('categoryHasSubcategories');
    const createCategoryContinue = document.getElementById('createCategoryContinue');
    const subcategoryWizardElement = document.getElementById('subcategoryWizardModal');
    const subcategoryDraftName = document.getElementById('subcategoryDraftName');
    const subcategoryDraftList = document.getElementById('subcategoryDraftList');
    const subcategoryDraftEmpty = document.getElementById('subcategoryDraftEmpty');
    const subcategoryWizardError = document.getElementById('subcategoryWizardError');
    const subcategoryWizardCategory = document.getElementById('subcategoryWizardCategory');
    const finishCategoryCreation = document.getElementById('finishCategoryCreation');
    const partnerSearch = document.getElementById('partnerSearch');
    const partnerResults = document.getElementById('partnerResults');
    const selectedPartnersElement = document.getElementById('selectedPartners');
    const editPartnerSearch = document.getElementById('editPartnerSearch');
    const editPartnerResults = document.getElementById('editPartnerResults');
    const editSelectedPartnersElement = document.getElementById('editSelectedPartners');
    const toast = document.getElementById('rankingToast');
    const addRecordForm = document.getElementById('addRecordForm');
    const addRecordModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addRecordModal'));
    const confirmRecordModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmRecordModal'));
    const createCategoryElement = document.getElementById('createCategoryModal');
    const createCategoryModal = bootstrap.Modal.getOrCreateInstance(createCategoryElement);
    const subcategoryWizardModal = bootstrap.Modal.getOrCreateInstance(subcategoryWizardElement);
    const detailsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('recordDetailsModal'));
    const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('rankingConfirmModal'));
    const manageSubcategoriesElement = document.getElementById('manageSubcategoriesModal');
    const manageSubcategoriesModal = bootstrap.Modal.getOrCreateInstance(manageSubcategoriesElement);
    const editRecordTimeElement = document.getElementById('editRecordTimeModal');
    const editRecordTimeModal = bootstrap.Modal.getOrCreateInstance(editRecordTimeElement);

    let isAdmin = false;
    let activeCategoryId = null;
    let activeSubcategoryId = null;
    let activeSubcategories = [];
    let currentUserId = 0;
    let activeRecords = [];
    let categoriesCache = [];
    let pendingEntry = null;
    let pendingCategoryCreation = null;
    let confirmCallback = null;
    let toastTimer = null;
    let pendingCategoryDraft = null;
    let draftSubcategoryNames = [];
    let selectedPartners = [];
    let managedCategoryId = null;
    let pendingTimeEditRecordId = null;
    let editingRecord = null;
    let editSelectedPartners = [];
    let reopenSubcategoryManager = false;
    let partnerSearchTimer = null;
    let partnerSearchSequence = 0;
    let editPartnerSearchTimer = null;
    let editPartnerSearchSequence = 0;
    const photoInput = document.getElementById('achievementPhotos');
    const photoList = document.getElementById('achievementPhotoList');
    const photoCount = document.getElementById('achievementPhotoCount');
    const photoPicker = document.getElementById('selectAchievementPhotos');
    const photoError = document.getElementById('addRecordError');
    const maxPhotoCount = 10;
    const maxPhotoBytes = Number(document.getElementById('addRecordForm').querySelector('.modal-body').dataset.photoMaxBytes);
    const maxPhotoTotalBytes = Number(document.getElementById('addRecordForm').querySelector('.modal-body').dataset.photoTotalMaxBytes);
    const maxPhotoLabel = (maxPhotoBytes / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 });
    const maxPhotoTotalLabel = (maxPhotoTotalBytes / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 });

    async function requestJson(url, options = {}) {
        const response = await fetch(url, { cache: 'no-store', ...options });
        let payload;
        try {
            payload = await response.json();
        } catch (_) {
            throw new Error('Réponse du serveur invalide.');
        }
        if (!response.ok) throw new Error(payload.error || 'Une erreur est survenue.');
        return payload;
    }

    async function postForm(formData) {
        return requestJson(apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
    }

    function postValues(values) {
        const body = new URLSearchParams({ ...values, csrf_token: csrfToken });
        return requestJson(apiUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body
        });
    }

    function showToast(message) {
        toast.textContent = message;
        toast.classList.add('is-visible');
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
    }

    function formatTime(totalSeconds) {
        const value = Math.max(0, Number(totalSeconds) || 0);
        const hours = Math.floor(value / 3600);
        const minutes = Math.floor((value % 3600) / 60);
        const seconds = value % 60;
        const pad = part => String(part).padStart(2, '0');
        return hours > 0 ? `${pad(hours)}:${pad(minutes)}:${pad(seconds)}` : `${pad(minutes)}:${pad(seconds)}`;
    }

    function formatDurationInput(value) {
        const digits = String(value || '').replace(/\D/g, '').slice(0, 6);
        if (digits.length <= 2) return digits;
        if (digits.length <= 4) return `${digits.slice(0, 2)}:${digits.slice(2)}`;
        return `${digits.slice(0, 2)}:${digits.slice(2, 4)}:${digits.slice(4)}`;
    }

    function handleDurationInput(input) {
        input.addEventListener('focus', () => input.select());
        input.addEventListener('input', () => {
            const cursor = input.selectionStart ?? input.value.length;
            const digitsBeforeCursor = input.value.slice(0, cursor).replace(/\D/g, '').length;
            const formatted = formatDurationInput(input.value);
            input.value = formatted;
            let nextCursor = 0;
            let digitsSeen = 0;
            while (nextCursor < formatted.length && digitsSeen < digitsBeforeCursor) {
                if (/\d/.test(formatted[nextCursor])) digitsSeen++;
                nextCursor++;
            }
            input.setSelectionRange(nextCursor, nextCursor);
        });
    }

    function parseDurationInput(value) {
        const match = String(value || '').match(/^(\d{2}):(\d{2}):(\d{2})$/);
        if (!match) return null;
        const [, hours, minutes, seconds] = match.map(Number);
        if (hours > 99 || minutes > 59 || seconds > 59) return null;
        return { hours, minutes, seconds };
    }

    function formatDurationParts(hours, minutes, seconds) {
        const pad = value => String(value).padStart(2, '0');
        return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    }

    const performanceTimeInput = document.getElementById('performanceTime');
    const editPerformanceTimeInput = document.getElementById('editPerformanceTime');
    [performanceTimeInput, editPerformanceTimeInput].forEach(handleDurationInput);

    const selectedPhotos = [];
    let photoPreviewUrls = [];

    function setPhotoError(message = '') {
        photoError.textContent = message;
        photoError.hidden = message === '';
    }

    function renderSelectedPhotos() {
        photoPreviewUrls.forEach(url => URL.revokeObjectURL(url));
        photoPreviewUrls = [];
        photoList.replaceChildren();
        photoCount.textContent = `${selectedPhotos.length} / ${maxPhotoCount}`;
        photoPicker.disabled = selectedPhotos.length >= maxPhotoCount;
        photoInput.disabled = selectedPhotos.length >= maxPhotoCount;

        if (selectedPhotos.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'ranking-photo-empty';
            empty.textContent = 'Aucune photo sélectionnée';
            photoList.append(empty);
            return;
        }

        selectedPhotos.forEach((file, index) => {
            const previewUrl = URL.createObjectURL(file);
            photoPreviewUrls.push(previewUrl);
            const item = document.createElement('div');
            item.className = 'ranking-photo-item';
            item.innerHTML = `
                <img src="${previewUrl}" alt="Aperçu de la photo ${index + 1}" loading="lazy">
                <button class="ranking-photo-remove" type="button" data-remove-photo="${index}" aria-label="Retirer la photo ${index + 1}" title="Retirer cette photo">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>`;
            photoList.append(item);
        });
    }

    function clearSelectedPhotos() {
        selectedPhotos.length = 0;
        photoInput.value = '';
        renderSelectedPhotos();
    }

    photoPicker.addEventListener('click', () => photoInput.click());
    photoInput.addEventListener('change', () => {
        let errorMessage = '';
        const files = Array.from(photoInput.files || []);
        photoInput.value = '';

        for (const file of files) {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                errorMessage = 'Choisis uniquement des images JPEG, PNG ou WebP.';
                continue;
            }
            if (file.size > maxPhotoBytes) {
                errorMessage = `Chaque photo doit faire ${maxPhotoLabel} Mo maximum sur cet appareil.`;
                continue;
            }
            if (selectedPhotos.length >= maxPhotoCount) {
                errorMessage = `Tu peux ajouter jusqu’à ${maxPhotoCount} photos.`;
                break;
            }
            const selectedBytes = selectedPhotos.reduce((total, selectedFile) => total + selectedFile.size, 0);
            if (selectedBytes + file.size > maxPhotoTotalBytes) {
                errorMessage = `La taille totale des photos ne peut pas dépasser ${maxPhotoTotalLabel} Mo sur cet appareil.`;
                continue;
            }
            selectedPhotos.push(file);
        }

        renderSelectedPhotos();
        setPhotoError(errorMessage);
    });

    photoList.addEventListener('click', event => {
        const removeButton = event.target.closest('[data-remove-photo]');
        if (!removeButton) return;
        selectedPhotos.splice(Number(removeButton.dataset.removePhoto), 1);
        setPhotoError();
        renderSelectedPhotos();
    });

    renderSelectedPhotos();

    function clearPartnerSelection() {
        selectedPartners = [];
        selectedPartnersElement.replaceChildren();
        selectedPartnersElement.hidden = true;
        partnerSearch.value = '';
        partnerResults.replaceChildren();
        partnerResults.hidden = true;
    }

    function renderSelectedPartners(selection = selectedPartners, target = selectedPartnersElement) {
        target.replaceChildren();
        selection.forEach(partner => {
            const item = document.createElement('div');
            item.className = 'ranking-selected-partner';
            const name = document.createElement('span');
            name.textContent = `${partner.label} a participé avec moi`;
            const removeButton = document.createElement('button');
            removeButton.className = 'ranking-partner-remove';
            removeButton.type = 'button';
            removeButton.dataset.removePartner = partner.key;
            removeButton.setAttribute('aria-label', `Retirer ${partner.label}`);
            removeButton.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
            item.append(name, removeButton);
            target.append(item);
        });
        target.hidden = selection.length === 0;
    }

    function parseExternalName(value) {
        const parts = String(value || '').trim().split(/\s+/u).filter(Boolean);
        if (parts.length < 2) return null;
        const fullName = parts.join(' ');
        return { firstname: parts.shift(), lastname: parts.join(' '), fullName };
    }

    function normalizePartnerName(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('fr')
            .trim()
            .replace(/\s+/g, ' ');
    }

    function hasExactPartnerMatch(users, externalName) {
        const enteredName = normalizePartnerName(externalName.fullName);
        return users.some(user => {
            const firstname = normalizePartnerName(user.firstname);
            const lastname = normalizePartnerName(String(user.label || '').slice(String(user.firstname || '').length));
            return enteredName === `${firstname} ${lastname}`.trim()
                || enteredName === `${lastname} ${firstname}`.trim();
        });
    }

    function showPartnerResults(users, query, target = partnerResults) {
        target.replaceChildren();
        const externalName = parseExternalName(query);
        const canAddExternal = externalName && !hasExactPartnerMatch(users, externalName);

        if (!users.length) {
            const empty = document.createElement('p');
            empty.className = 'ranking-partner-empty';
            empty.textContent = 'Pour ajouter un participant non inscrit, écrivez son nom et son prénom.';
            target.append(empty);
        } else {
            users.forEach(user => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'ranking-partner-option';
                option.setAttribute('role', 'option');
                option.dataset.partnerId = String(user.id);
                option.dataset.firstname = user.firstname;
                option.dataset.lastInitial = user.last_initial;
                option.textContent = user.label;
                target.append(option);
            });
        }

        if (canAddExternal) {
            if (users.length) {
                const guidance = document.createElement('p');
                guidance.className = 'ranking-partner-empty';
                guidance.textContent = 'Pour ajouter un participant non inscrit, écrivez son nom et son prénom.';
                target.append(guidance);
            }
            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'ranking-partner-add';
            addButton.dataset.addExternalPartner = 'true';
            addButton.innerHTML = `<i class="bi bi-plus-lg" aria-hidden="true"></i><span>Ajouter ${escapeHtml(`${externalName.firstname} ${externalName.lastname}`)}</span>`;
            target.append(addButton);
        }
        target.hidden = false;
    }

    partnerSearch.addEventListener('input', () => {
        window.clearTimeout(partnerSearchTimer);
        const query = partnerSearch.value.trim();
        const searchId = ++partnerSearchSequence;
        if (query.length < 2) {
            partnerResults.replaceChildren();
            partnerResults.hidden = true;
            return;
        }

        const searching = document.createElement('p');
        searching.className = 'ranking-partner-empty';
        searching.textContent = 'Recherche…';
        partnerResults.replaceChildren(searching);
        partnerResults.hidden = false;
        partnerSearchTimer = window.setTimeout(async () => {
            try {
                const data = await requestJson(`${apiUrl}?users=${encodeURIComponent(query)}`);
                if (searchId !== partnerSearchSequence) return;
                showPartnerResults(data.users || [], query);
            } catch (error) {
                if (searchId !== partnerSearchSequence) return;
                const message = document.createElement('p');
                message.className = 'ranking-partner-empty';
                message.textContent = error.message;
                partnerResults.replaceChildren(message);
            }
        }, 220);
    });

    function handlePartnerResultClick(event, searchField, resultElement, selection, renderSelection, existingParticipants = []) {
        const addExternalButton = event.target.closest('[data-add-external-partner]');
        if (addExternalButton) {
            const externalName = parseExternalName(searchField.value);
            if (!externalName) return;
            const key = `external:${normalizePartnerName(`${externalName.firstname} ${externalName.lastname}`)}`;
            const alreadyInRecord = existingParticipants.some(participant => participant.external
                && normalizePartnerName(`${participant.firstname} ${participant.lastname}`) === normalizePartnerName(`${externalName.firstname} ${externalName.lastname}`));
            if (!alreadyInRecord && !selection.some(partner => partner.key === key) && selection.length >= 20) {
                showToast('Tu peux ajouter au maximum 20 participants à une performance.');
                return;
            }
            if (!alreadyInRecord && !selection.some(partner => partner.key === key)) {
                selection.push({
                    key,
                    type: 'external',
                    firstname: externalName.firstname,
                    lastname: externalName.lastname,
                    label: `${externalName.firstname} ${externalName.lastname}`
                });
                renderSelection();
            }
            searchField.value = '';
            resultElement.replaceChildren();
            resultElement.hidden = true;
            return;
        }
        const option = event.target.closest('[data-partner-id]');
        if (!option) return;
        const partnerId = Number(option.dataset.partnerId);
        const key = `member:${partnerId}`;
        const alreadyInRecord = existingParticipants.some(participant => Number(participant.user_id) === partnerId);
        if (!alreadyInRecord && selection.length >= 20) {
            showToast('Tu peux ajouter au maximum 20 participants à une performance.');
            return;
        }
        if (!alreadyInRecord && !selection.some(partner => partner.key === key)) {
            selection.push({ key, type: 'member', id: partnerId, label: option.textContent });
            renderSelection();
        }
        searchField.value = '';
        resultElement.replaceChildren();
        resultElement.hidden = true;
    }

    partnerResults.addEventListener('click', event => {
        handlePartnerResultClick(event, partnerSearch, partnerResults, selectedPartners, renderSelectedPartners);
        partnerSearchSequence++;
    });

    editPartnerSearch.addEventListener('input', () => {
        window.clearTimeout(editPartnerSearchTimer);
        const query = editPartnerSearch.value.trim();
        const searchId = ++editPartnerSearchSequence;
        if (query.length < 2) {
            editPartnerResults.replaceChildren();
            editPartnerResults.hidden = true;
            return;
        }
        const searching = document.createElement('p');
        searching.className = 'ranking-partner-empty';
        searching.textContent = 'Recherche…';
        editPartnerResults.replaceChildren(searching);
        editPartnerResults.hidden = false;
        editPartnerSearchTimer = window.setTimeout(async () => {
            try {
                const data = await requestJson(`${apiUrl}?users=${encodeURIComponent(query)}`);
                if (searchId === editPartnerSearchSequence) showPartnerResults(data.users || [], query, editPartnerResults);
            } catch (error) {
                if (searchId !== editPartnerSearchSequence) return;
                const message = document.createElement('p');
                message.className = 'ranking-partner-empty';
                message.textContent = error.message;
                editPartnerResults.replaceChildren(message);
            }
        }, 220);
    });

    editPartnerResults.addEventListener('click', event => {
        handlePartnerResultClick(
            event,
            editPartnerSearch,
            editPartnerResults,
            editSelectedPartners,
            () => renderSelectedPartners(editSelectedPartners, editSelectedPartnersElement),
            editingRecord?.participants || []
        );
        editPartnerSearchSequence++;
    });

    selectedPartnersElement.addEventListener('click', event => {
        const removeButton = event.target.closest('[data-remove-partner]');
        if (!removeButton) return;
        selectedPartners = selectedPartners.filter(partner => partner.key !== removeButton.dataset.removePartner);
        renderSelectedPartners();
    });

    editSelectedPartnersElement.addEventListener('click', event => {
        const removeButton = event.target.closest('[data-remove-partner]');
        if (!removeButton) return;
        editSelectedPartners = editSelectedPartners.filter(partner => partner.key !== removeButton.dataset.removePartner);
        renderSelectedPartners(editSelectedPartners, editSelectedPartnersElement);
    });

    async function loadSummary() {
        const data = await requestJson(apiUrl);
        isAdmin = data.is_admin === true;
        categoriesCache = data.categories || [];
        createCategoryButton.hidden = !isAdmin;
        renderCategories(categoriesCache);
        renderPersonalBest(categoriesCache);
    }

    function renderCategories(categories) {
        categoryList.replaceChildren();
        if (!categories.length) {
            const empty = document.createElement('p');
            empty.className = 'ranking-message';
            empty.textContent = 'Aucun classement pour le moment. Crée le premier tableau.';
            categoryList.append(empty);
            return;
        }

        categories.forEach(category => {
            const card = document.createElement('article');
            card.className = 'ranking-category-card';
            card.innerHTML = `
                ${isAdmin ? `<button class="ranking-category-manage" type="button" data-manage-subcategories="${escapeHtml(category.id)}" aria-label="Gérer les sous-catégories de ${escapeHtml(category.name)}" title="Gérer les sous-catégories"><i class="bi bi-list-ul" aria-hidden="true"></i></button>` : ''}
                ${isAdmin ? `<button class="ranking-category-delete" type="button" data-delete-category="${escapeHtml(category.id)}" aria-label="Supprimer le classement ${escapeHtml(category.name)}" title="Supprimer ce classement"><i class="bi bi-trash3" aria-hidden="true"></i></button>` : ''}
                <button class="ranking-category-open" type="button" data-open-category="${escapeHtml(category.id)}" aria-label="Ouvrir le classement ${escapeHtml(category.name)}">
                    <span class="ranking-category-card__icon" aria-hidden="true"><i class="bi bi-trophy"></i></span>
                    <span class="ranking-category-card__copy">
                        <span class="ranking-category-card__name">${escapeHtml(category.name)}</span>
                        <span class="ranking-category-card__meta">${Number(category.record_count) || 0} performance${Number(category.record_count) === 1 ? '' : 's'}</span>
                    </span>
                    <i class="bi bi-chevron-right ranking-category-card__arrow" aria-hidden="true"></i>
                </button>`;
            categoryList.append(card);
        });
    }

    function renderPersonalBest(categories) {
        const bestCategories = categories.filter(category => category.personal_best);
        personalBestList.replaceChildren();
        personalBlock.hidden = bestCategories.length === 0;
        bestCategories.forEach(category => {
            const best = category.personal_best;
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'ranking-personal-card';
            card.dataset.openCategory = category.id;
            card.innerHTML = `
                <span class="ranking-personal-card__medal" aria-hidden="true"><i class="bi bi-award"></i></span>
                <span class="ranking-personal-card__copy">
                    <span class="ranking-personal-card__name">${escapeHtml(category.name)}</span>
                    <span class="ranking-personal-card__meta">${escapeHtml(best.competition || 'Meilleure performance')}</span>
                </span>
                <span class="ranking-personal-card__time">${formatTime(best.time_seconds)}</span>`;
            personalBestList.append(card);
        });
    }

    function setDrawerOpen(open) {
        drawer.classList.toggle('is-open', open);
        drawer.setAttribute('aria-hidden', String(!open));
        drawer.inert = !open;
        document.body.classList.toggle('ranking-drawer-open', open);
        categoryActions.hidden = !open;
    }

    async function openCategory(categoryId, updateHistory = true, subcategoryId = null) {
        const enteringSubcategory = activeCategoryId === String(categoryId)
            && subcategoryId !== null
            && activeSubcategoryId !== String(subcategoryId);
        activeCategoryId = String(categoryId);
        activeSubcategoryId = subcategoryId === null ? null : String(subcategoryId);
        setDrawerOpen(true);
        if (enteringSubcategory) drawer.scrollTop = 0;
        document.getElementById('categoryTitle').textContent = 'Chargement…';
        document.getElementById('podium').innerHTML = '<div class="ranking-podium__empty">Chargement des performances…</div>';
        document.getElementById('recordList').replaceChildren();
        if (updateHistory) {
            const url = new URL(window.location.href);
            url.searchParams.set('categorie', activeCategoryId);
            if (activeSubcategoryId) url.searchParams.set('sous_categorie', activeSubcategoryId);
            else url.searchParams.delete('sous_categorie');
            history.pushState({ rankingCategory: activeCategoryId, rankingSubcategory: activeSubcategoryId }, '', url);
        }
        try {
            const params = new URLSearchParams({ category: activeCategoryId });
            if (activeSubcategoryId) params.set('subcategory', activeSubcategoryId);
            const data = await requestJson(`${apiUrl}?${params}`);
            if (activeCategoryId !== String(categoryId) || activeSubcategoryId !== (subcategoryId === null ? null : String(subcategoryId))) return;
            activeRecords = data.records || [];
            isAdmin = data.is_admin === true;
            currentUserId = Number(data.user_id) || 0;
            activeSubcategories = data.subcategories || [];
            renderCategory(data.category, activeRecords, data);
            if (enteringSubcategory) {
                void categoryContent.offsetHeight;
                drawer.scrollTop = 0;
                categoryContent.classList.remove('is-subcategory-entering');
                void categoryContent.offsetWidth;
                categoryContent.classList.add('is-subcategory-entering');
            }
        } catch (error) {
            document.getElementById('categoryTitle').textContent = 'Classement indisponible';
            document.getElementById('podium').innerHTML = `<div class="ranking-podium__empty">${escapeHtml(error.message)}</div>`;
            showToast(error.message);
        }
    }

    function renderCategory(category, records, data) {
        document.getElementById('categoryTitle').textContent = category.name;
        const hasSubcategories = !activeSubcategoryId && activeSubcategories.length > 0;
        subcategorySection.hidden = Boolean(activeSubcategoryId) || !hasSubcategories;
        renderSubcategories(activeSubcategories);
        categoryActions.hidden = !activeSubcategoryId && hasSubcategories;
        document.querySelector('.ranking-back span').textContent = activeSubcategoryId
            ? (data.parent_category?.name || 'Catégorie')
            : 'Classements';
        const recordHeading = document.querySelector('.ranking-table-heading');
        const hasSpecialMention = hasSubcategories && records.length > 3;
        recordHeading.hidden = hasSubcategories && !hasSpecialMention;
        document.getElementById('recordListTitle').textContent = hasSpecialMention
            ? 'Mention spéciale aux 4e et 5e places'
            : 'Toutes les performances';
        renderPodium(records);
        renderRecordList(records.slice(3), 3);
    }

    function renderSubcategories(subcategories) {
        subcategoryList.replaceChildren();
        subcategories.forEach(subcategory => {
            const card = document.createElement('article');
            card.className = 'ranking-category-card ranking-subcategory-card';
            card.innerHTML = `
                <button class="ranking-category-open" type="button" data-open-subcategory="${escapeHtml(subcategory.id)}" aria-label="Ouvrir la sous-catégorie ${escapeHtml(subcategory.name)}">
                    <span class="ranking-category-card__icon" aria-hidden="true"><i class="bi bi-bar-chart-line"></i></span>
                    <span class="ranking-category-card__copy">
                        <span class="ranking-category-card__name">${escapeHtml(subcategory.name)}</span>
                        <span class="ranking-category-card__meta">${Number(subcategory.record_count) || 0} résultat${Number(subcategory.record_count) === 1 ? '' : 's'}</span>
                    </span>
                    <i class="bi bi-chevron-right ranking-category-card__arrow" aria-hidden="true"></i>
                </button>`;
            subcategoryList.append(card);
        });
    }

    function recordName(record) {
        if (record.external && record.lastname) return `${record.firstname || ''} ${record.lastname}`.trim();
        return `${record.firstname || 'Membre'}${record.last_initial ? ` ${String(record.last_initial).slice(0, 1)}.` : ''}`;
    }

    function recordDisplayName(record) {
        const participants = Array.isArray(record.participants) ? record.participants : [];
        return participants.length ? participants.map(recordName).join(' · ') : recordName(record);
    }

    function formatEventDate(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value || ''))) return '';
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day).toLocaleDateString('fr-FR');
    }

    function renderPodium(records) {
        const podium = document.getElementById('podium');
        podium.replaceChildren();
        if (!records.length) {
            const empty = document.createElement('div');
            empty.className = 'ranking-podium__empty';
            empty.textContent = 'Pas encore de performance. Le podium est à prendre.';
            podium.append(empty);
            return;
        }

        const places = [
            { index: 1, label: '2', className: 'second' },
            { index: 0, label: '1', className: 'first' },
            { index: 2, label: '3', className: 'third' }
        ];
        places.forEach(place => {
            const record = records[place.index];
            const lane = document.createElement('div');
            lane.className = `ranking-podium__lane ranking-podium__lane--${place.className}`;
            const result = document.createElement('button');
            result.type = 'button';
            result.className = 'ranking-podium__result';
            if (!record) {
                result.disabled = true;
                result.setAttribute('aria-label', `Place ${place.label} disponible`);
                result.innerHTML = '<span class="ranking-podium__medal"><i class="bi bi-award" aria-hidden="true"></i></span><span class="ranking-podium__name">À prendre</span>';
            } else {
                result.dataset.recordId = record.id;
                result.setAttribute('aria-label', `${place.label}${place.label === '1' ? 'er' : 'e'} : ${recordDisplayName(record)}, ${formatTime(record.time_seconds)}. Voir les détails.`);
                result.innerHTML = `<span class="ranking-podium__medal"><i class="bi bi-award" aria-hidden="true"><\/i><span>${place.label}</span></span><span class="ranking-podium__name">${escapeHtml(recordDisplayName(record))}</span><span class="ranking-podium__time">${formatTime(record.time_seconds)}</span>`;
            }
            const step = document.createElement('div');
            step.className = 'ranking-podium__step';
            step.setAttribute('aria-hidden', 'true');
            step.innerHTML = `<span>${place.label}</span>`;
            lane.append(result, step);
            podium.append(lane);
        });
    }

    function renderRecordList(records, startingRank = 3) {
        const list = document.getElementById('recordList');
        list.replaceChildren();
        if (!records.length) return;

        records.forEach((record, index) => {
            const item = document.createElement('li');
            item.className = 'ranking-record';
            item.innerHTML = `
                <button class="ranking-record__button" type="button" data-record-id="${escapeHtml(record.id)}" aria-label="Voir la performance de ${escapeHtml(recordDisplayName(record))}, ${formatTime(record.time_seconds)}">
                    <span class="ranking-record__rank">${startingRank + index + 1}</span>
                    <span class="ranking-record__main">
                    <span class="ranking-record__name">${escapeHtml(recordDisplayName(record))}</span>
                    <span class="ranking-record__competition">${escapeHtml(record.competition || 'Compétition')}${formatEventDate(record.event_date) ? ` · ${formatEventDate(record.event_date)}` : ''}</span>
                    </span>
                    <span class="ranking-record__time">${formatTime(record.time_seconds)}</span>
                    ${record.photos && record.photos.length ? `<span class="ranking-record__photos" aria-label="${record.photos.length} photo(s)"><i class="bi bi-images" aria-hidden="true"></i> ${record.photos.length}</span>` : '<span class="ranking-record__photos"></span>'}
                    <i class="bi bi-chevron-right ranking-record__arrow" aria-hidden="true"></i>
                </button>`;
            if ((record.participants || []).some(participant => Number(participant.user_id) === currentUserId)) {
                item.insertAdjacentHTML('beforeend', `<button class="ranking-record__self-delete" type="button" data-remove-participation="${escapeHtml(record.id)}" aria-label="Retirer ta participation à ${escapeHtml(record.competition || 'cette performance')}" title="Retirer ma participation"><i class="bi bi-trash3" aria-hidden="true"></i></button>`);
            }
            list.append(item);
        });
    }

    function closeCategory() {
        if (!drawer.classList.contains('is-open')) return;
        setDrawerOpen(false);
        activeCategoryId = null;
        activeSubcategoryId = null;
        const url = new URL(window.location.href);
        url.searchParams.delete('categorie');
        url.searchParams.delete('sous_categorie');
        history.replaceState({}, '', url);
    }

    function openRecord(recordId) {
        const record = activeRecords.find(item => String(item.id) === String(recordId));
        if (!record) return;
        const body = document.getElementById('recordDetailsBody');
        const footer = document.getElementById('recordDetailsFooter');
        document.getElementById('recordDetailsTitle').textContent = recordDisplayName(record);
        body.replaceChildren();
        footer.querySelectorAll('[data-remove-participation], [data-edit-time]').forEach(button => button.remove());

        const meta = document.createElement('div');
        meta.className = 'ranking-record-details__meta';
        meta.innerHTML = `<span class="ranking-record-details__competition">${escapeHtml(record.competition || 'Compétition non précisée')} · ${escapeHtml(formatEventDate(record.event_date) || 'Date inconnue')}</span><strong class="ranking-record-details__time">${formatTime(record.time_seconds)}</strong>`;
        body.append(meta);

        if (Array.isArray(record.participants) && record.participants.length > 1) {
            const participants = document.createElement('p');
            participants.className = 'ranking-record-details__participants';
            participants.textContent = `Réalisé avec ${record.participants.map(participant => recordName(participant)).join(', ')}`;
            body.append(participants);
        }

        if (Array.isArray(record.photos) && record.photos.length) {
            const gallery = document.createElement('div');
            gallery.className = 'ranking-gallery';
            gallery.dataset.index = '0';
            gallery.dataset.photos = JSON.stringify(record.photos);
            gallery.innerHTML = `
                <img src="${apiUrl}?photo=${encodeURIComponent(record.photos[0])}" alt="Photo de la performance" loading="lazy">
                ${record.photos.length > 1 ? '<button class="ranking-gallery__nav ranking-gallery__nav--previous" type="button" data-gallery-step="-1" aria-label="Photo précédente"><i class="bi bi-chevron-left" aria-hidden="true"></i></button><button class="ranking-gallery__nav ranking-gallery__nav--next" type="button" data-gallery-step="1" aria-label="Photo suivante"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>' : ''}
                <span class="ranking-gallery__count">1 / ${record.photos.length}</span>`;
            body.append(gallery);
        } else {
            const empty = document.createElement('p');
            empty.className = 'ranking-message';
            empty.textContent = 'Aucune photo n’a été ajoutée à cette performance.';
            body.append(empty);
        }

        if ((record.participants || []).some(participant => Number(participant.user_id) === currentUserId)) {
            const editButton = document.createElement('button');
            editButton.className = 'btn btn-wtc-outline me-auto';
            editButton.type = 'button';
            editButton.dataset.editTime = record.id;
            editButton.innerHTML = '<i class="bi bi-pencil me-1" aria-hidden="true"></i>Modifier';
            const removeButton = document.createElement('button');
            removeButton.className = 'btn btn-wtc-outline';
            removeButton.type = 'button';
            removeButton.dataset.removeParticipation = record.id;
            removeButton.innerHTML = '<i class="bi bi-person-dash me-1" aria-hidden="true"></i>Retirer ma participation';
            footer.prepend(removeButton, editButton);
        }
        detailsModal.show();
    }

    function renderManagedSubcategories(category) {
        if (!category) return;
        const list = document.getElementById('managedSubcategoryList');
        list.replaceChildren();
        document.getElementById('manageSubcategoriesCategory').textContent = category.name;
        (category.subcategories || []).forEach((subcategory, index) => {
            const item = document.createElement('li');
            item.className = 'ranking-subcategory-draft';
            item.innerHTML = `
                <span class="ranking-subcategory-draft__index">${String(index + 1).padStart(2, '0')}</span>
                <span class="ranking-subcategory-draft__name">${escapeHtml(subcategory.name)} <small>· ${Number(subcategory.record_count) || 0} performance(s)</small></span>
                ${isAdmin ? `<button class="ranking-subcategory-draft__remove" type="button" data-delete-managed-subcategory="${escapeHtml(subcategory.id)}" data-subcategory-name="${escapeHtml(subcategory.name)}" data-record-count="${Number(subcategory.record_count) || 0}" aria-label="Supprimer ${escapeHtml(subcategory.name)}" title="Supprimer cette sous-catégorie">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>` : ''}`;
            list.append(item);
        });
    }

    function openSubcategoryManager(categoryId) {
        const category = categoriesCache.find(item => String(item.id) === String(categoryId));
        if (!category) return;
        managedCategoryId = String(categoryId);
        document.getElementById('manageSubcategoriesForm').reset();
        document.getElementById('manageSubcategoriesError').hidden = true;
        document.getElementById('manageSubcategoriesTitle').textContent = isAdmin ? 'Gérer les sous-catégories' : 'Ajouter une sous-catégorie';
        renderManagedSubcategories(category);
        manageSubcategoriesModal.show();
    }

    function openTimeEditor(recordId) {
        const record = activeRecords.find(item => String(item.id) === String(recordId));
        if (!record || !(record.participants || []).some(participant => Number(participant.user_id) === currentUserId)) return;
        pendingTimeEditRecordId = String(recordId);
        editingRecord = record;
        editSelectedPartners = [];
        renderSelectedPartners(editSelectedPartners, editSelectedPartnersElement);
        editPartnerSearch.value = '';
        editPartnerResults.replaceChildren();
        editPartnerResults.hidden = true;
        editPartnerSearchSequence++;
        const totalSeconds = Number(record.time_seconds) || 0;
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        editPerformanceTimeInput.value = formatDurationParts(hours, minutes, seconds);
        document.getElementById('editSharedTimeNotice').hidden = (record.participants || []).length < 2;
        document.getElementById('editRecordTimeError').hidden = true;
        detailsModal.hide();
    }

    function askConfirm(message, callback, sourceModal = null, actionLabel = 'Supprimer') {
        document.getElementById('rankingConfirmMessage').textContent = message;
        document.getElementById('rankingConfirmAction').textContent = actionLabel;
        confirmCallback = callback;
        if (sourceModal) {
            sourceModal.addEventListener('hidden.bs.modal', () => confirmModal.show(), { once: true });
            bootstrap.Modal.getOrCreateInstance(sourceModal).hide();
        } else {
            confirmModal.show();
        }
    }

    document.getElementById('rankingConfirmAction').addEventListener('click', async event => {
        if (!confirmCallback) return;
        const button = event.currentTarget;
        button.disabled = true;
        try {
            await confirmCallback();
            confirmCallback = null;
            confirmModal.hide();
        } catch (error) {
            showToast(error.message);
        } finally {
            button.disabled = false;
        }
    });

    async function deleteCategory(categoryId, categoryName) {
        await postValues({ action: 'delete_category', category_id: categoryId });
        if (activeCategoryId === String(categoryId)) closeCategory();
        await loadSummary();
        showToast(`« ${categoryName} » a été supprimé.`);
    }

    async function removeOwnParticipation(recordId, sourceModal = null) {
        askConfirm('Retirer uniquement ta participation à cet événement ?', async () => {
            await postValues({ action: 'remove_participation', record_id: recordId });
            if (sourceModal) detailsModal.hide();
            await loadSummary();
            if (activeCategoryId) await openCategory(activeCategoryId, false, activeSubcategoryId);
            showToast('Ta participation a été retirée.');
        }, sourceModal, 'Retirer');
    }

    categoryList.addEventListener('click', event => {
        const manageButton = event.target.closest('[data-manage-subcategories]');
        if (manageButton) {
            event.stopPropagation();
            openSubcategoryManager(manageButton.dataset.manageSubcategories);
            return;
        }
        const deleteButton = event.target.closest('[data-delete-category]');
        if (deleteButton) {
            event.stopPropagation();
            const categoryId = deleteButton.dataset.deleteCategory;
            const categoryName = deleteButton.closest('.ranking-category-card')?.querySelector('.ranking-category-card__name')?.textContent || 'ce classement';
            askConfirm(`Supprimer « ${categoryName} » ainsi que toutes ses performances et photos ?`, () => deleteCategory(categoryId, categoryName));
            return;
        }
        const card = event.target.closest('[data-open-category]');
        if (card) openCategory(card.dataset.openCategory);
    });

    personalBestList.addEventListener('click', event => {
        const card = event.target.closest('[data-open-category]');
        if (card) openCategory(card.dataset.openCategory);
    });

    subcategoryList.addEventListener('click', event => {
        const card = event.target.closest('[data-open-subcategory]');
        if (card) openCategory(activeCategoryId, true, card.dataset.openSubcategory);
    });

    document.getElementById('backToCategories').addEventListener('click', () => {
        if (activeSubcategoryId) {
            if (history.state && history.state.rankingSubcategory) {
                history.back();
            } else {
                const url = new URL(window.location.href);
                url.searchParams.delete('sous_categorie');
                history.replaceState({ rankingCategory: activeCategoryId }, '', url);
                openCategory(activeCategoryId, false);
            }
            return;
        }
        if (history.state && history.state.rankingCategory) history.back();
        else closeCategory();
    });

    window.addEventListener('popstate', () => {
        const categoryId = new URL(window.location.href).searchParams.get('categorie');
        const subcategoryId = new URL(window.location.href).searchParams.get('sous_categorie');
        if (categoryId) openCategory(categoryId, false, subcategoryId);
        else {
            setDrawerOpen(false);
            activeCategoryId = null;
            activeSubcategoryId = null;
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && drawer.classList.contains('is-open') && !document.querySelector('.modal.show')) closeCategory();
    });

    document.getElementById('openAddRecord').addEventListener('click', () => {
        document.getElementById('addRecordError').hidden = true;
        addRecordModal.show();
    });

    function resetConfirmRecordDialog() {
        document.querySelector('#confirmRecordModal .eyebrow').textContent = 'Confirmation';
        document.getElementById('confirmRecordTitle').textContent = 'Déclaration sur l’honneur';
        document.querySelector('#confirmRecordModal .ranking-confirm-copy').textContent = 'Je confirme que les informations et les photos transmises sont exactes. Toute fausse déclaration peut entraîner une exclusion du système de classement pendant plusieurs mois.';
        document.getElementById('confirmRecordSubmit').textContent = 'Je confirme';
    }

    function confirmCategoryCreation(formData, categoryName, subcategoryCount, sourceModal) {
        pendingCategoryCreation = { formData, categoryName, subcategoryCount };
        document.querySelector('#confirmRecordModal .eyebrow').textContent = 'Vérification';
        document.getElementById('confirmRecordTitle').textContent = 'Confirmer la création du classement';
        document.querySelector('#confirmRecordModal .ranking-confirm-copy').textContent = `Tu vas créer le classement « ${categoryName} »${subcategoryCount ? ` avec ${subcategoryCount} sous-catégorie${subcategoryCount > 1 ? 's' : ''}` : ''}. Je confirme sur l’honneur qu’il est en rapport avec le CrossFit. La création d’un classement sans rapport avec le CrossFit peut entraîner mon bannissement du système de classement pendant plusieurs mois.`;
        document.getElementById('confirmRecordSubmit').textContent = 'Créer le classement';
        document.getElementById('confirmRecordError').hidden = true;
        sourceModal.addEventListener('hidden.bs.modal', () => confirmRecordModal.show(), { once: true });
        bootstrap.Modal.getOrCreateInstance(sourceModal).hide();
    }

    addRecordForm.addEventListener('submit', event => {
        event.preventDefault();
        if (!addRecordForm.reportValidity()) return;
        const formData = new FormData(addRecordForm);
        const totalBytes = selectedPhotos.reduce((sum, file) => sum + file.size, 0);
        if (selectedPhotos.length > maxPhotoCount || selectedPhotos.some(file => file.size > maxPhotoBytes) || totalBytes > maxPhotoTotalBytes) {
            setPhotoError(`Ajoute jusqu’à ${maxPhotoCount} photos, dans les limites affichées.`);
            return;
        }
        const duration = parseDurationInput(formData.get('performance_time'));
        if (!duration) {
            setPhotoError('Saisis le temps au format HH:MM:SS, avec des minutes et secondes entre 00 et 59.');
            performanceTimeInput.focus();
            return;
        }
        const { hours, minutes, seconds } = duration;
        if (hours * 3600 + minutes * 60 + seconds <= 0) {
            setPhotoError('Le temps doit être supérieur à zéro.');
            return;
        }
        formData.delete('performance_time');
        formData.set('hours', String(hours));
        formData.set('minutes', String(minutes));
        formData.set('seconds', String(seconds));
        selectedPhotos.forEach(file => formData.append('photos[]', file, file.name));
        formData.set('action', 'create_record');
        formData.set('category_id', activeCategoryId || '');
        formData.set('subcategory_id', activeSubcategoryId || '');
        formData.set('partner_ids', JSON.stringify(selectedPartners.map(partner => partner.type === 'external'
            ? { firstname: partner.firstname, lastname: partner.lastname }
            : partner.id)));
        formData.set('csrf_token', csrfToken);
        pendingEntry = formData;
        addRecordModal.hide();
    });

    document.getElementById('addRecordModal').addEventListener('hidden.bs.modal', () => {
        if (pendingEntry) confirmRecordModal.show();
    });

    document.getElementById('confirmRecordModal').addEventListener('hidden.bs.modal', () => {
        if (pendingEntry) {
            pendingEntry = null;
            addRecordForm.reset();
            clearSelectedPhotos();
            clearPartnerSelection();
        }
        if (pendingCategoryCreation) {
            pendingCategoryCreation = null;
            clearCategoryDraft();
            createCategoryContinue.textContent = 'Créer';
        }
        resetConfirmRecordDialog();
    });

    document.getElementById('confirmRecordSubmit').addEventListener('click', async event => {
        if (!pendingEntry && !pendingCategoryCreation) return;
        const button = event.currentTarget;
        const error = document.getElementById('confirmRecordError');
        button.disabled = true;
        error.hidden = true;
        try {
            if (pendingCategoryCreation) {
                const categoryCreation = pendingCategoryCreation;
                await postForm(categoryCreation.formData);
                pendingCategoryCreation = null;
                pendingCategoryDraft = null;
                draftSubcategoryNames = [];
                clearCategoryDraft();
                createCategoryContinue.textContent = 'Créer';
                renderSubcategoryDrafts();
                confirmRecordModal.hide();
                await loadSummary();
                showToast(categoryCreation.subcategoryCount
                    ? 'Classement et sous-catégories créés.'
                    : 'Classement créé.');
                return;
            }
            await postForm(pendingEntry);
            pendingEntry = null;
            confirmRecordModal.hide();
            addRecordForm.reset();
            clearSelectedPhotos();
            clearPartnerSelection();
            await loadSummary();
            if (activeCategoryId) await openCategory(activeCategoryId, false, activeSubcategoryId);
            showToast('Temps ajouté au classement.');
        } catch (requestError) {
            error.textContent = requestError.message;
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });

    function renderSubcategoryDrafts() {
        subcategoryDraftList.replaceChildren();
        draftSubcategoryNames.forEach((name, index) => {
            const item = document.createElement('li');
            item.className = 'ranking-subcategory-draft';
            item.innerHTML = `
                <span class="ranking-subcategory-draft__index">${String(index + 1).padStart(2, '0')}</span>
                <span class="ranking-subcategory-draft__name">${escapeHtml(name)}</span>
                <button class="ranking-subcategory-draft__remove" type="button" data-remove-subcategory="${index}" aria-label="Retirer ${escapeHtml(name)}" title="Retirer cette sous-catégorie">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>`;
            subcategoryDraftList.append(item);
        });
        subcategoryDraftEmpty.hidden = draftSubcategoryNames.length > 0;
        finishCategoryCreation.disabled = draftSubcategoryNames.length === 0;
    }

    function addSubcategoryDraft() {
        const name = subcategoryDraftName.value.trim();
        if (!name) {
            subcategoryWizardError.textContent = 'Saisis un nom de sous-catégorie.';
            subcategoryWizardError.hidden = false;
            subcategoryDraftName.focus();
            return;
        }
        if (name.length > 80) {
            subcategoryWizardError.textContent = 'Le nom est limité à 80 caractères.';
            subcategoryWizardError.hidden = false;
            return;
        }
        if (draftSubcategoryNames.some(existing => existing.toLocaleLowerCase('fr') === name.toLocaleLowerCase('fr'))) {
            subcategoryWizardError.textContent = 'Cette sous-catégorie est déjà dans la liste.';
            subcategoryWizardError.hidden = false;
            return;
        }
        if (draftSubcategoryNames.length >= 100) {
            subcategoryWizardError.textContent = 'La catégorie ne peut pas dépasser 100 sous-catégories.';
            subcategoryWizardError.hidden = false;
            return;
        }
        draftSubcategoryNames.push(name);
        subcategoryDraftName.value = '';
        subcategoryWizardError.hidden = true;
        renderSubcategoryDrafts();
        subcategoryDraftName.focus();
    }

    function clearCategoryDraft() {
        pendingCategoryDraft = null;
        draftSubcategoryNames = [];
        const form = document.getElementById('createCategoryForm');
        form.reset();
        renderSubcategoryDrafts();
    }

    document.getElementById('openCreateCategory').addEventListener('click', () => {
        clearCategoryDraft();
        document.getElementById('createCategoryError').hidden = true;
        createCategoryContinue.textContent = categoryHasSubcategories.checked ? 'Continuer' : 'Créer';
        createCategoryModal.show();
    });

    categoryHasSubcategories.addEventListener('change', () => {
        createCategoryContinue.textContent = categoryHasSubcategories.checked ? 'Continuer' : 'Créer';
    });

    createCategoryElement.addEventListener('hidden.bs.modal', () => {
        if (!pendingCategoryDraft) return;
        subcategoryWizardCategory.textContent = pendingCategoryDraft.name;
        subcategoryWizardError.hidden = true;
        renderSubcategoryDrafts();
        subcategoryWizardModal.show();
        subcategoryDraftName.focus();
    });

    subcategoryWizardElement.addEventListener('hidden.bs.modal', () => {
        if (pendingCategoryDraft && !pendingCategoryCreation) clearCategoryDraft();
    });

    document.getElementById('createCategoryForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const error = document.getElementById('createCategoryError');
        error.hidden = true;
        const categoryName = form.elements.name.value.trim();

        if (categoryHasSubcategories.checked) {
            if (!pendingCategoryDraft || pendingCategoryDraft.name !== categoryName) {
                pendingCategoryDraft = { name: categoryName };
                draftSubcategoryNames = [];
                subcategoryDraftName.value = '';
            }
            createCategoryModal.hide();
            return;
        }

        const submitButton = form.querySelector('[type="submit"]');
        submitButton.disabled = true;
        try {
            const body = new FormData();
            body.set('action', 'create_category');
            body.set('name', categoryName);
            body.set('has_subcategories', '0');
            body.set('subcategories', JSON.stringify([]));
            body.set('csrf_token', csrfToken);
            if (!isAdmin) {
                confirmCategoryCreation(body, categoryName, 0, createCategoryElement);
                return;
            }
            await postForm(body);
            createCategoryModal.hide();
            clearCategoryDraft();
            createCategoryContinue.textContent = 'Créer';
            await loadSummary();
            showToast('Classement créé.');
        } catch (requestError) {
            error.textContent = requestError.message;
            error.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });

    document.getElementById('addSubcategoryDraft').addEventListener('click', addSubcategoryDraft);
    subcategoryDraftName.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addSubcategoryDraft();
        }
    });
    subcategoryDraftList.addEventListener('click', event => {
        const removeButton = event.target.closest('[data-remove-subcategory]');
        if (!removeButton) return;
        draftSubcategoryNames.splice(Number(removeButton.dataset.removeSubcategory), 1);
        subcategoryWizardError.hidden = true;
        renderSubcategoryDrafts();
    });

    document.getElementById('closeSubcategoryWizard').addEventListener('click', () => subcategoryWizardModal.hide());
    finishCategoryCreation.addEventListener('click', async () => {
        if (!pendingCategoryDraft || draftSubcategoryNames.length === 0) return;
        subcategoryWizardError.hidden = true;
        finishCategoryCreation.disabled = true;
        try {
            const body = new FormData();
            body.set('action', 'create_category');
            body.set('name', pendingCategoryDraft.name);
            body.set('has_subcategories', '1');
            body.set('subcategories', JSON.stringify(draftSubcategoryNames));
            body.set('csrf_token', csrfToken);
            if (!isAdmin) {
                confirmCategoryCreation(body, pendingCategoryDraft.name, draftSubcategoryNames.length, subcategoryWizardElement);
                finishCategoryCreation.disabled = false;
                return;
            }
            await postForm(body);
            pendingCategoryDraft = null;
            draftSubcategoryNames = [];
            subcategoryWizardModal.hide();
            document.getElementById('createCategoryForm').reset();
            createCategoryContinue.textContent = 'Créer';
            renderSubcategoryDrafts();
            await loadSummary();
            showToast('Classement et sous-catégories créés.');
        } catch (requestError) {
            subcategoryWizardError.textContent = requestError.message;
            subcategoryWizardError.hidden = false;
            finishCategoryCreation.disabled = false;
        }
    });

    document.getElementById('recordList').addEventListener('click', event => {
        const removeButton = event.target.closest('[data-remove-participation]');
        if (removeButton) {
            removeOwnParticipation(removeButton.dataset.removeParticipation);
            return;
        }
        const recordButton = event.target.closest('[data-record-id]');
        if (recordButton) openRecord(recordButton.dataset.recordId);
    });

    document.getElementById('podium').addEventListener('click', event => {
        const recordButton = event.target.closest('[data-record-id]');
        if (recordButton) openRecord(recordButton.dataset.recordId);
    });

    document.getElementById('recordDetailsFooter').addEventListener('click', event => {
        const editButton = event.target.closest('[data-edit-time]');
        if (editButton) {
            openTimeEditor(editButton.dataset.editTime);
            return;
        }
        const removeButton = event.target.closest('[data-remove-participation]');
        if (removeButton) removeOwnParticipation(removeButton.dataset.removeParticipation, document.getElementById('recordDetailsModal'));
    });

    document.getElementById('manageSubcategoriesForm').addEventListener('submit', async event => {
        event.preventDefault();
        const input = document.getElementById('managedSubcategoryName');
        const error = document.getElementById('manageSubcategoriesError');
        const name = input.value.trim();
        if (!name) {
            error.textContent = 'Saisis un nom de sous-catégorie.';
            error.hidden = false;
            input.focus();
            return;
        }
        error.hidden = true;
        const submitButton = event.currentTarget.querySelector('[type="submit"]');
        submitButton.disabled = true;
        try {
            await postValues({ action: 'add_subcategory', category_id: managedCategoryId, name });
            await loadSummary();
            input.value = '';
            renderManagedSubcategories(categoriesCache.find(category => String(category.id) === managedCategoryId));
            showToast('Sous-catégorie ajoutée.');
        } catch (requestError) {
            error.textContent = requestError.message;
            error.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });

    document.getElementById('managedSubcategoryList').addEventListener('click', event => {
        const button = event.target.closest('[data-delete-managed-subcategory]');
        if (!button) return;
        const subcategoryId = button.dataset.deleteManagedSubcategory;
        const subcategoryName = button.dataset.subcategoryName;
        const recordCount = Number(button.dataset.recordCount) || 0;
        const detail = recordCount
            ? ` ainsi que ses ${recordCount} performance(s) et photos associées`
            : '';
        askConfirm(`Supprimer « ${subcategoryName} »${detail} ?`, async () => {
            const response = await postValues({ action: 'delete_subcategory', category_id: managedCategoryId, subcategory_id: subcategoryId });
            await loadSummary();
            reopenSubcategoryManager = true;
            showToast(response.deleted_record_count ? 'Sous-catégorie et performances supprimées.' : 'Sous-catégorie supprimée.');
        }, manageSubcategoriesElement);
    });

    document.getElementById('rankingConfirmModal').addEventListener('hidden.bs.modal', () => {
        if (!reopenSubcategoryManager) return;
        reopenSubcategoryManager = false;
        openSubcategoryManager(managedCategoryId);
    });

    document.getElementById('recordDetailsModal').addEventListener('hidden.bs.modal', () => {
        if (!pendingTimeEditRecordId) return;
        editRecordTimeModal.show();
    });

    editRecordTimeElement.addEventListener('hidden.bs.modal', () => {
        if (pendingTimeEditRecordId) pendingTimeEditRecordId = null;
        editingRecord = null;
        editSelectedPartners = [];
        renderSelectedPartners(editSelectedPartners, editSelectedPartnersElement);
    });

    document.getElementById('editRecordTimeForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const error = document.getElementById('editRecordTimeError');
        const submitButton = form.querySelector('[type="submit"]');
        const duration = parseDurationInput(editPerformanceTimeInput.value);
        if (!duration) {
            error.textContent = 'Saisis le temps au format HH:MM:SS, avec des minutes et secondes entre 00 et 59.';
            error.hidden = false;
            editPerformanceTimeInput.focus();
            return;
        }
        const { hours, minutes, seconds } = duration;
        if (hours * 3600 + minutes * 60 + seconds <= 0) {
            error.textContent = 'Le temps doit être supérieur à zéro.';
            error.hidden = false;
            return;
        }
        error.hidden = true;
        submitButton.disabled = true;
        try {
            await postValues({
                action: 'edit_time',
                record_id: pendingTimeEditRecordId,
                hours: String(hours),
                minutes: String(minutes),
                seconds: String(seconds)
            });
            if (editSelectedPartners.length) {
                await postValues({
                    action: 'add_participants',
                    record_id: pendingTimeEditRecordId,
                    partner_ids: JSON.stringify(editSelectedPartners.map(partner => partner.type === 'external'
                        ? { firstname: partner.firstname, lastname: partner.lastname }
                        : partner.id))
                });
            }
            pendingTimeEditRecordId = null;
            editingRecord = null;
            editSelectedPartners = [];
            editRecordTimeModal.hide();
            await loadSummary();
            if (activeCategoryId) await openCategory(activeCategoryId, false, activeSubcategoryId);
            showToast('Performance mise à jour.');
        } catch (requestError) {
            error.textContent = requestError.message;
            error.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });

    function shiftGallery(gallery, step) {
        const photos = JSON.parse(gallery.dataset.photos || '[]');
        if (!photos.length) return;
        const index = (Number(gallery.dataset.index) + step + photos.length) % photos.length;
        gallery.dataset.index = String(index);
        gallery.querySelector('img').src = `${apiUrl}?photo=${encodeURIComponent(photos[index])}`;
        gallery.querySelector('.ranking-gallery__count').textContent = `${index + 1} / ${photos.length}`;
    }

    let galleryTouchStartX = null;
    document.getElementById('recordDetailsBody').addEventListener('touchstart', event => {
        if (event.target.closest('.ranking-gallery')) galleryTouchStartX = event.changedTouches[0].clientX;
    }, { passive: true });

    document.getElementById('recordDetailsBody').addEventListener('touchend', event => {
        const gallery = event.target.closest('.ranking-gallery');
        if (!gallery || galleryTouchStartX === null) return;
        const distance = galleryTouchStartX - event.changedTouches[0].clientX;
        galleryTouchStartX = null;
        if (Math.abs(distance) > 45) shiftGallery(gallery, distance > 0 ? 1 : -1);
    }, { passive: true });

    document.getElementById('recordDetailsBody').addEventListener('click', event => {
        const stepButton = event.target.closest('[data-gallery-step]');
        if (!stepButton) return;
        shiftGallery(stepButton.closest('.ranking-gallery'), Number(stepButton.dataset.galleryStep));
    });

    loadSummary().then(() => {
        const initialCategory = new URL(window.location.href).searchParams.get('categorie');
        const initialSubcategory = new URL(window.location.href).searchParams.get('sous_categorie');
        if (initialCategory) openCategory(initialCategory, false, initialSubcategory);
    }).catch(error => {
        categoryList.innerHTML = `<p class="ranking-message">${escapeHtml(error.message)}</p>`;
        showToast(error.message);
    });
});