document.addEventListener("DOMContentLoaded", () => {
  const shell = document.getElementById("chatShell");
  const messages = document.getElementById("chatMessages");
  const empty = document.getElementById("chatEmpty");
  const form = document.getElementById("chatForm");
  const input = document.getElementById("chatInput");
  const sendBtn = document.getElementById("chatSend");
  const resetBtn = document.getElementById("chatReset");

  if (!shell) return;

  // Auto-resize du textarea
  function autoResize() {
    input.style.height = "auto";
    input.style.height = Math.min(input.scrollHeight, 120) + "px";
  }

  function updateSendState() {
    sendBtn.disabled = input.value.trim().length === 0;
  }

  input.addEventListener("input", () => {
    autoResize();
    updateSendState();
  });

  // Entrée = envoyer, Maj+Entrée = retour à la ligne
  input.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  // Suggestions rapides
  document.querySelectorAll(".chat-suggestion").forEach((btn) => {
    btn.addEventListener("click", () => {
      input.value = btn.textContent.trim();
      autoResize();
      updateSendState();
      input.focus();
    });
  });

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function appendMessage(text, role) {
    if (empty && empty.isConnected) {
      empty.remove();
    }
    const wrap = document.createElement("div");
    wrap.className = "chat-msg chat-msg--" + role;
    const bubble = document.createElement("div");
    bubble.className = "chat-msg__bubble";
    bubble.textContent = text;
    wrap.appendChild(bubble);
    messages.appendChild(wrap);
    scrollToBottom();
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    appendMessage(text, "user");
    input.value = "";
    autoResize();
    updateSendState();

    const loading = document.createElement("div");
    loading.className = "chat-msg chat-msg--assistant";
    const loadingBubble = document.createElement("div");
    loadingBubble.className = "chat-msg__bubble";
    loadingBubble.textContent = "Pensée en cours…";
    loading.appendChild(loadingBubble);
    messages.appendChild(loading);
    scrollToBottom();

    try {
      const response = await fetch(window.location.pathname, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({ message: text }),
      });

      const data = await response.json();
      loading.remove();

      if (data && data.success && data.answer) {
        appendMessage(data.answer, "assistant");
      } else {
        appendMessage(
          "Je n’ai pas pu obtenir de réponse pour le moment.",
          "assistant",
        );
      }
    } catch (error) {
      loading.remove();
      appendMessage(
        "Une erreur est survenue pendant la communication avec l’assistant.",
        "assistant",
      );
    }
  });

  resetBtn.addEventListener("click", () => {
    messages.innerHTML = "";
    if (empty) {
      messages.appendChild(empty);
    }
    input.value = "";
    autoResize();
    updateSendState();
  });

  updateSendState();
});
