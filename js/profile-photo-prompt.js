(() => {
    const modalElement = document.getElementById('profilePhotoPrompt');
    const uploadButton = document.getElementById('profilePhotoPromptUpload');
    const uploadInput = document.getElementById('profilePhotoPromptInput');
    const uploadForm = document.getElementById('profilePhotoPromptForm');
    if (!modalElement || !uploadButton || !uploadInput || !uploadForm || !window.bootstrap?.Modal) return;

    const reminderKey = `wtc-profile-photo-reminder-${modalElement.dataset.userId}`;
    const reminderDelay = 7 * 24 * 60 * 60 * 1000;
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    let uploadStarted = false;

    modalElement.addEventListener('hidden.bs.modal', () => {
        if (!uploadStarted) {
            window.localStorage.setItem(reminderKey, String(Date.now() + reminderDelay));
        }
    });

    uploadButton.addEventListener('click', () => {
        uploadStarted = true;
        uploadInput.click();
        modal.hide();
    });

    uploadInput.addEventListener('change', () => {
        if (uploadInput.files?.length) uploadForm.submit();
        else {
            uploadStarted = false;
            window.localStorage.setItem(reminderKey, String(Date.now() + reminderDelay));
        }
    });
    uploadInput.addEventListener('cancel', () => {
        uploadStarted = false;
        window.localStorage.setItem(reminderKey, String(Date.now() + reminderDelay));
    });

    const remindAt = Number(window.localStorage.getItem(reminderKey) || 0);
    if (Date.now() >= remindAt) {
        window.setTimeout(() => modal.show(), 2500);
    }
})();
