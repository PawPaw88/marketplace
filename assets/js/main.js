document.addEventListener("DOMContentLoaded", function () {
  const slides = document.querySelectorAll(".hero-slide");
  const indicators = document.querySelectorAll(".indicator span");
  const heroSlider = document.querySelector(".hero-slider");
  const categoryButtons = document.querySelectorAll(".category-btn");
  const productCards = document.querySelectorAll(".product-card");
  const categoryContainer = document.querySelector(".category-container");
  const header = document.querySelector("header");
  const categoryButtonsContainer = document.querySelector(".category-buttons");

  let currentIndex = 0;
  let intervalId;
  let startX = 0;
  let isSwiping = false;
  let lastScrollTop = 0;

  // Pastikan wishlistItems berisi format ID yang konsisten
  const normalizedWishlistItems = wishlistItems.map((id) => {
    // Jika ID berbentuk objek dengan properti $oid, ambil nilai $oid-nya
    return typeof id === "object" && id.$oid ? id.$oid : id;
  });

  // Fungsi helper untuk normalisasi ID
  function normalizeId(id) {
    return typeof id === "object" && id.$oid ? id.$oid : id;
  }

  function showSlide(index) {
    slides.forEach((slide, i) => {
      slide.classList.remove("active");
      indicators[i].classList.remove("active");
      if (i === index) {
        slide.classList.add("active");
        indicators[i].classList.add("active");
      }
    });
  }

  function nextSlide() {
    currentIndex = (currentIndex + 1) % slides.length;
    showSlide(currentIndex);
  }

  function prevSlide() {
    currentIndex = (currentIndex - 1 + slides.length) % slides.length;
    showSlide(currentIndex);
  }

  function startSlideshow() {
    intervalId = setInterval(nextSlide, 5000);
  }

  function stopSlideshow() {
    clearInterval(intervalId);
  }

  heroSlider.addEventListener("touchstart", (e) => {
    stopSlideshow();
    startX = e.touches[0].clientX;
    isSwiping = true;
  });

  heroSlider.addEventListener("touchmove", (e) => {
    if (!isSwiping) return;
    const moveX = e.touches[0].clientX;
    const deltaX = moveX - startX;
    if (Math.abs(deltaX) > 50) {
      if (deltaX > 0) {
        prevSlide();
      } else {
        nextSlide();
      }
      isSwiping = false;
    }
  });

  heroSlider.addEventListener("touchend", () => {
    isSwiping = false;
    startSlideshow();
  });

  indicators.forEach((indicator, i) => {
    indicator.addEventListener("click", () => {
      currentIndex = i;
      showSlide(currentIndex);
      stopSlideshow();
      startSlideshow();
    });
  });

  heroSlider.addEventListener("mouseenter", stopSlideshow);
  heroSlider.addEventListener("mouseleave", startSlideshow);

  categoryButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const category = button.getAttribute("data-category");
      categoryButtons.forEach((btn) => btn.classList.remove("active"));
      button.classList.add("active");
      productCards.forEach((card) => {
        if (
          category === "all" ||
          card.getAttribute("data-category") === category
        ) {
          card.style.display = "block";
        } else {
          card.style.display = "none";
        }
      });
    });
  });

  window.addEventListener("scroll", () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const headerHeight = header.offsetHeight;
    header.style.transform = "translateY(0)";
    if (scrollTop > headerHeight) {
      categoryContainer.classList.add("fixed");
      categoryContainer.style.top = `${headerHeight}px`;
    } else {
      categoryContainer.classList.remove("fixed");
      categoryContainer.style.top = "";
    }
    lastScrollTop = scrollTop;
  });

  categoryButtonsContainer.addEventListener("wheel", (e) => {
    if (e.deltaY !== 0) {
      e.preventDefault();
      categoryButtonsContainer.scrollLeft += e.deltaY;
    }
  });

  productCards.forEach((card) => {
    card.addEventListener("click", function () {
      const productData = JSON.parse(this.getAttribute("data-product"));
      openProductModal(productData);
    });
  });

  feather.replace({ width: 20, height: 20 });

  function showModal(modalId) {
    const allModals = document.querySelectorAll(".header-modal");
    allModals.forEach((modal) => {
      modal.style.display = "none";
    });
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.style.display = "block";
    }
  }

  document.querySelector(".cart-icon").addEventListener("click", function (e) {
    e.stopPropagation();
    showModal("cartModal");
  });

  document.querySelector(".notif-icon").addEventListener("click", function (e) {
    e.stopPropagation();
    showModal("notifModal");
  });

  document.querySelector(".chat-icon").addEventListener("click", function (e) {
    e.stopPropagation();
    showModal("chatModal");
  });

  const userProfile = document.querySelector(".user-profile");
  if (userProfile) {
    userProfile.addEventListener("click", function (e) {
      e.stopPropagation();
      showModal("profileModal");
    });
  }

  document.addEventListener("click", function (e) {
    if (
      !e.target.closest(".header-modal") &&
      !e.target.closest(".user-profile") &&
      !e.target.closest(".icon-wrapper")
    ) {
      const modals = document.querySelectorAll(".header-modal");
      modals.forEach((modal) => {
        modal.style.display = "none";
      });
    }
  });

  const modalTabs = document.querySelectorAll(".modal-tab");
  const modalTabContents = document.querySelectorAll(".modal-tab-content");

  modalTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      const tabTarget = tab.getAttribute("data-tab");
      modalTabs.forEach((t) => t.classList.remove("active"));
      modalTabContents.forEach((c) => c.classList.remove("active"));
      tab.classList.add("active");
      document.getElementById(tabTarget).classList.add("active");
    });
  });

  function openProductModal(product) {
    if (!product || !product.nama_produk) {
      console.error("Data produk tidak valid:", product);
      return;
    }

    const productId = normalizeId(product._id);

    const modalContent = document.querySelector(".modal-content");
    modalContent.innerHTML = `
        <button class="back-button"><i class="ri-arrow-left-line"></i> Kembali</button>
        <div class="product-main-info">
            <h2 id="modalProductName">${product.nama_produk}</h2>
            <div class="modal-product-info">
                <div class="modal-rating">
                    <i class="ri-star-fill"></i>
                    <span>4.5</span>
                    <span>(50 ulasan)</span>
                </div>
                <div class="modal-actions">
                    <button class="modal-action-btn">
                        <i class="ri-heart-${normalizedWishlistItems.includes(productId) ? "fill" : "line"}"></i>
                        ${normalizedWishlistItems.includes(productId) ? "Hapus dari Wishlist" : "Tambah ke Wishlist"}
                    </button>
                </div>
            </div>
            <div class="modal-image-container">
                <img id="modalProductImage" src="assets/img/products/${product.gambar[0]}" alt="${product.nama_produk}">
            </div>
        </div>
        <div class="modal-tabs">
            <div class="modal-tab active" data-tab="details">Informasi Produk</div>
            <div class="modal-tab" data-tab="reviews">Ulasan</div>
        </div>
        <div class="modal-tab-content active" id="details">
            <div class="seller-info">
                <img id="modalSellerAvatar" src="assets/img/avatar/${product.penjual.avatar || "1.jpg"}" alt="Seller Avatar" class="seller-avatar">
                <div class="seller-details">
                    <p id="modalProductSeller" class="seller-name">${product.penjual.username}</p>
                    <p id="modalSellerAddress" class="seller-address">${product.penjual.alamat || "Alamat tidak tersedia"}</p>
                </div>
            </div>
            <p id="modalProductPrice">Rp ${product.harga.toLocaleString("id-ID")}</p>
            <p id="modalProductDescription">${product.deskripsi}</p>
            <div class="modal-buttons">
                <button id="modalAddToCart">Tambah ke Keranjang</button>
            </div>
        </div>
        <div class="modal-tab-content" id="reviews">
            <div class="review-item">
                <div class="review-header">
                    <img src="assets/img/avatar/1.jpg" alt="Reviewer" class="reviewer-avatar">
                    <div class="reviewer-info">
                        <div class="reviewer-name">John Doe</div>
                        <div class="review-date">20 Mar 2024</div>
                    </div>
                </div>
                <div class="review-rating">
                    <i class="ri-star-fill"></i>
                    <i class="ri-star-fill"></i>
                    <i class="ri-star-fill"></i>
                    <i class="ri-star-fill"></i>
                    <i class="ri-star-fill"></i>
                </div>
                <p class="review-text">Produk sangat bagus dan sesuai dengan deskripsi. Pengiriman cepat dan packing aman.</p>
            </div>
        </div>
    `;

    // Tambahkan data produk ke modal content
    modalContent.setAttribute('data-product', encodeURIComponent(JSON.stringify(product)));

    const wishlistButton = modalContent.querySelector(".modal-action-btn");
    wishlistButton.onclick = () => toggleWishlist(productId);

    const modal = document.getElementById("productModal");
    modal.style.display = "block";

    const backButton = modalContent.querySelector(".back-button");
    backButton.onclick = () => {
        modal.style.display = "none";
    };

    const addToCartButton = document.getElementById("modalAddToCart");
    addToCartButton.onclick = () => {
        if (isLoggedIn) {
            showQuantityModal(productId, product.stok);
        } else {
            showNotification("Silakan login terlebih dahulu", "error");
        }
    };

    // Tambahkan event listener untuk tab setelah modal content diperbarui
    const modalTabs = modalContent.querySelectorAll(".modal-tab");
    const modalTabContents = modalContent.querySelectorAll(".modal-tab-content");

    modalTabs.forEach((tab) => {
        tab.addEventListener("click", () => {
            const tabTarget = tab.getAttribute("data-tab");
            modalTabs.forEach((t) => t.classList.remove("active"));
            modalTabContents.forEach((c) => c.classList.remove("active"));
            tab.classList.add("active");
            modalContent.querySelector(`#${tabTarget}`).classList.add("active");
        });
    });
  }

  function toggleWishlist(productId) {
    if (!isLoggedIn) {
      showNotification("Silakan login terlebih dahulu", "error");
      return;
    }

    fetch("index.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `action=toggleWishlist&productId=${productId}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const wishlistButton = document.querySelector(".modal-action-btn");
          if (data.isInWishlist) {
            wishlistButton.innerHTML =
              '<i class="ri-heart-fill"></i> Hapus dari Wishlist';
            if (!wishlistItems.includes(productId)) {
              wishlistItems.push(productId);
            }
          } else {
            wishlistButton.innerHTML =
              '<i class="ri-heart-line"></i> Tambah ke Wishlist';
            const index = wishlistItems.indexOf(productId);
            if (index > -1) {
              wishlistItems.splice(index, 1);
            }
          }
          showNotification(data.message, "success");
        } else {
          showNotification(data.message, "error");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification("Terjadi kesalahan saat mengubah wishlist", "error");
      });
  }

  function showNotification(message, type = "success") {
    const existingNotification = document.querySelector(".notification-popup");
    if (existingNotification) {
      existingNotification.remove();
    }

    const notification = document.createElement("div");
    notification.className = `notification-popup ${type}`;

    const icon = type === "success" ? "ri-check-line" : "ri-error-warning-line";

    notification.innerHTML = `
        <i class="${icon}"></i>
        <span>${message}</span>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.classList.add("show");
    }, 100);

    setTimeout(() => {
      notification.classList.remove("show");
      setTimeout(() => {
        notification.remove();
      }, 300);
    }, 3000);
  }

  function showQuantityModal(productId, stock) {
    const quantityModal = document.getElementById("quantityModal");
    if (!quantityModal) {
      console.error("Modal quantity tidak ditemukan");
      return;
    }

    const quantityInput = document.getElementById("productQuantity");
    const confirmButton = quantityModal.querySelector(".confirm-add");
    const cancelButton = quantityModal.querySelector(".cancel-add");

    // Reset dan set nilai maksimum input
    quantityInput.value = 1;
    quantityInput.max = stock;

    // Tambahkan event listener untuk validasi input
    quantityInput.addEventListener('input', function() {
        let value = parseInt(this.value);
        
        // Pastikan nilai tidak negatif
        if (value < 1) {
            this.value = 1;
            value = 1;
        }
        
        // Pastikan nilai tidak melebihi stok
        if (value > stock) {
            this.value = stock;
            value = stock;
            showNotification("Jumlah melebihi stok yang tersedia", "error");
        }

        // Update status tombol konfirmasi
        confirmButton.disabled = value > stock || value < 1;
    });

    // Tampilkan informasi stok
    const stockInfo = document.createElement("div");
    stockInfo.className = "stock-info";
    stockInfo.textContent = `Stok tersedia: ${stock}`;
    quantityModal.querySelector(".quantity-input-container").appendChild(stockInfo);

    // Tampilkan modal
    quantityModal.style.display = "block";

    // Event handler untuk tombol cancel
    cancelButton.onclick = () => {
        quantityModal.style.display = "none";
        stockInfo.remove();
        // Hapus event listener saat modal ditutup
        quantityInput.removeEventListener('input', null);
    };

    // Event handler untuk tombol confirm
    confirmButton.onclick = () => {
        const quantity = parseInt(quantityInput.value);
        
        if (quantity < 1 || quantity > stock) {
            showNotification("Jumlah tidak valid", "error");
            return;
        }

        addToCart(productId, quantity);
        quantityModal.style.display = "none";
        stockInfo.remove();
        // Hapus event listener saat modal ditutup
        quantityInput.removeEventListener('input', null);
    };

    // Tutup modal ketika klik di luar modal
    window.onclick = (event) => {
        if (event.target === quantityModal) {
            quantityModal.style.display = "none";
            stockInfo.remove();
            // Hapus event listener saat modal ditutup
            quantityInput.removeEventListener('input', null);
        }
    };
  }

  function addToCart(productId, quantity) {
    fetch("index.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `action=addToCart&productId=${productId}&quantity=${quantity}`,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showNotification(data.message, "success");
          refreshCartModal();
          document.getElementById("cartModal").style.display = "block";
        } else {
          showNotification(data.message, "error");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification(
          "Terjadi kesalahan saat menambahkan ke keranjang",
          "error"
        );
      });
  }

  showSlide(currentIndex);
  startSlideshow();
});
