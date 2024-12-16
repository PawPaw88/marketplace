function deleteCartItem(itemId) {
  showConfirmationPopup(
    "Hapus Produk",
    "Apakah Anda yakin ingin menghapus produk ini dari keranjang?",
    () => {
      const formData = new FormData();
      formData.append("itemId", itemId);

      fetch("api/delete_cart_item.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            refreshCartModal();
            showNotification(
              "Produk berhasil dihapus dari keranjang",
              "success"
            );
          } else {
            showNotification(data.message || "Gagal menghapus produk", "error");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("Terjadi kesalahan saat menghapus produk", "error");
        });
    }
  );
}

function clearCart() {
  showConfirmationPopup(
    "Kosongkan Keranjang",
    "Apakah Anda yakin ingin menghapus semua produk dari keranjang?",
    () => {
      fetch("api/clear_cart.php", {
        method: "POST",
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            refreshCartModal();
            showNotification("Keranjang berhasil dikosongkan", "success");
          } else {
            showNotification(
              data.message || "Gagal mengosongkan keranjang",
              "error"
            );
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("Terjadi kesalahan", "error");
        });
    }
  );
}
