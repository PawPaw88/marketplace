function showNotification(message, type = "success") {
  const existingPopup = document.querySelector(".notification-popup");
  if (existingPopup) {
    existingPopup.remove();
  }
  const popup = document.createElement("div");
  popup.className = `notification-popup ${type}`;
  popup.innerHTML = `
        <i class="ri-checkbox-circle-line"></i>
        <span>${message}</span>
    `;
  document.body.appendChild(popup);
  setTimeout(() => popup.classList.add("show"), 10);
  setTimeout(() => {
    popup.classList.remove("show");
    setTimeout(() => popup.remove(), 300);
  }, 3000);
}

function showConfirmationPopup(title, message, onConfirm) {
  const existingPopup = document.querySelector(".confirmation-popup");
  if (existingPopup) {
    existingPopup.remove();
  }

  const popup = document.createElement("div");
  popup.className = "confirmation-popup";
  popup.innerHTML = `
        <div class="confirmation-content">
            <h3>${title}</h3>
            <p>${message}</p>
            <div class="confirmation-buttons">
                <button class="cancel-btn">Batal</button>
                <button class="confirm-btn">Ya, Hapus</button>
            </div>
        </div>
    `;

  document.body.appendChild(popup);

  const confirmBtn = popup.querySelector(".confirm-btn");
  const cancelBtn = popup.querySelector(".cancel-btn");

  confirmBtn.addEventListener("click", () => {
    onConfirm();
    popup.remove();
  });

  cancelBtn.addEventListener("click", () => popup.remove());

  setTimeout(() => popup.classList.add("show"), 100);
}
