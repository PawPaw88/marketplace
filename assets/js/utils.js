function refreshCartModal() {
  fetch("/marketplace/api/get_cart.php")
    .then((response) => response.text())
    .then((html) => {
      const cartModal = document.getElementById("cartModal");
      const modalContent = cartModal.querySelector(".header-modal-content");
      modalContent.innerHTML = html;

      // Perbaiki link "Lihat Keranjang"
      const viewCartLink = modalContent.querySelector('.view-all-btn');
      if (viewCartLink) {
        viewCartLink.href = '/marketplace/views/cart/payment.php';
      }

      // Perbaiki semua link lainnya di dalam modal
      const allLinks = modalContent.querySelectorAll('a:not(.view-all-btn)');
      allLinks.forEach((link) => {
        if (!link.href.includes("/marketplace/")) {
          link.href = "/marketplace" + (link.href.startsWith("/") ? "" : "/") + link.href;
        }
      });

      cartModal.style.display = "block";
    })
    .catch((error) => {
      console.error("Error refreshing cart:", error);
      showNotification("Gagal memperbarui keranjang", "error");
    });
}
