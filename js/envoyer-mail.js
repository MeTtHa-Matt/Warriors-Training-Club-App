const stylePresets = {
  classique: { label: "Classique", hero: "Un message simple et élégant" },
  chaleureux: { label: "Chaleureux", hero: "Une voix plus proche des membres" },
  professionnel: {
    label: "Professionnel",
    hero: "Un ton structuré et rassurant",
  },
  energetique: { label: "Énergique", hero: "Un message dynamique et motivant" },
};

const subjectInput = document.getElementById("subject");
const styleSelect = document.getElementById("style_preset");
const signatureInput = document.getElementById("signature");
const editor = document.getElementById("messageEditor");
const hiddenMessageInput = document.getElementById("message_html");
const previewMeta = document.getElementById("mailPreviewMeta");
const previewMessage = document.getElementById("mailPreviewMessage");
const previewSignature = document.getElementById("mailPreviewSignature");
const styleBadge = document.getElementById("styleBadge");
const summarySubject = document.getElementById("mailSummarySubject");
const summaryStyle = document.getElementById("mailSummaryStyle");
const fontSizeSelect = document.getElementById("fontSizeSelect");
const fontColorInput = document.getElementById("fontColorInput");
const attachmentInput = document.getElementById("attachments");
const attachmentFiles = document.getElementById("attachmentFiles");
const editorBaseTextColor =
  getComputedStyle(document.documentElement)
    .getPropertyValue("--paper")
    .trim() || "#f4efe2";

function syncEditorContent() {
  if (!editor || !hiddenMessageInput) {
    return;
  }

  const html = editor.innerHTML.trim();
  hiddenMessageInput.value = html;

  if (previewMessage) {
    previewMessage.innerHTML =
      html ||
      '<p style="margin:0; color:#8a8a8a;">Votre message apparaîtra ici.</p>';
  }
}

function updatePreview() {
  const presetName =
    styleSelect && styleSelect.value ? styleSelect.value : "classique";
  const preset = stylePresets[presetName] || stylePresets.classique;

  if (previewMeta) {
    previewMeta.textContent = preset.hero;
  }
  if (styleBadge) {
    styleBadge.textContent = preset.label;
  }
  if (summaryStyle) {
    summaryStyle.textContent = preset.label;
  }
  if (summarySubject) {
    summarySubject.textContent =
      subjectInput && subjectInput.value.trim()
        ? subjectInput.value.trim()
        : "À définir";
  }
  if (previewSignature) {
    previewSignature.textContent =
      signatureInput && signatureInput.value.trim()
        ? signatureInput.value.trim()
        : "L’équipe du club";
  }

  syncEditorContent();
}

function setEditorTextColor(colorValue) {
  if (!editor) {
    return;
  }

  editor.style.color = colorValue;
  if (fontColorInput) {
    fontColorInput.value = colorValue;
  }
}

function updateToolbarState() {
  document.querySelectorAll("[data-command]").forEach(function (button) {
    const command = button.getAttribute("data-command");
    const isActive =
      ["bold", "italic", "underline"].includes(command) &&
      document.queryCommandState(command);
    button.classList.toggle("is-active", isActive);
    button.setAttribute("aria-pressed", isActive ? "true" : "false");
  });
}

function applyCommand(command, value) {
  if (!editor) {
    return;
  }

  if (command === "foreColor" && value) {
    setEditorTextColor(value);
  }

  document.execCommand(command, false, value);
  editor.focus();
  updateToolbarState();
  syncEditorContent();
}

document.querySelectorAll("[data-command]").forEach(function (button) {
  button.addEventListener("click", function () {
    applyCommand(button.getAttribute("data-command"));
  });
});

if (fontSizeSelect) {
  fontSizeSelect.addEventListener("change", function () {
    if (fontSizeSelect.value) {
      applyCommand("fontSize", fontSizeSelect.value);
      fontSizeSelect.value = "";
    }
  });
}

if (fontColorInput) {
  fontColorInput.addEventListener("input", function () {
    applyCommand("foreColor", fontColorInput.value);
  });
}

[subjectInput, styleSelect, signatureInput]
  .filter(Boolean)
  .forEach(function (el) {
    el.addEventListener("input", updatePreview);
    el.addEventListener("change", updatePreview);
  });

if (editor) {
  editor.addEventListener("input", function () {
    syncEditorContent();
    updateToolbarState();
  });
  editor.addEventListener("keyup", function () {
    syncEditorContent();
    updateToolbarState();
  });
  editor.addEventListener("mouseup", updateToolbarState);
  editor.addEventListener("click", updateToolbarState);
}

if (attachmentInput && attachmentFiles) {
  attachmentInput.addEventListener("change", function () {
    const files = Array.from(this.files || []);
    attachmentFiles.innerHTML = files.length
      ? '<div class="mail-attachment-list__title">Pièces jointes sélectionnées</div>' +
        files
          .map(function (file) {
            return '<div class="mail-attachment-item">' + file.name + "</div>";
          })
          .join("")
      : "";
  });
}

const mailForm = document.querySelector("form.mail-form");
if (mailForm) {
  mailForm.addEventListener("submit", function () {
    syncEditorContent();
  });
}

if (editor) {
  setEditorTextColor(editorBaseTextColor);
}
if (fontColorInput) {
  fontColorInput.value = editorBaseTextColor;
}

updatePreview();
updateToolbarState();
