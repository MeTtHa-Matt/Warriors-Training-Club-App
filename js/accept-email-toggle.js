document.addEventListener("DOMContentLoaded", function () {
  const checkbox = document.getElementById("accept_email");

  if (!checkbox) {
    return;
  }

  checkbox.addEventListener("change", function () {
    const newValue = checkbox.checked ? 1 : 0;

    checkbox.disabled = true;

    fetch("includes/account/update-accept-email.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({ accept_email: newValue }),
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data.success) {
          checkbox.checked = !checkbox.checked;
        }
      })
      .catch(function () {
        checkbox.checked = !checkbox.checked;
      })
      .finally(function () {
        checkbox.disabled = false;
      });
  });
});
