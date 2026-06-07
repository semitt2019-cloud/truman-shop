/* MEC — Product Grid Interactions (Shopee Orange Theme) */
(function () {
  'use strict';

  /* ================================================================
     FLASH SALE COUNTDOWN
     อ่าน timestamp (ms) จาก data-flash-end ของ .mec-flash
     ================================================================ */
  function startCountdown(endTimestamp) {
    var banner = document.querySelector('.mec-flash[data-flash-end]');
    if (!banner) return;

    var hEl = banner.querySelector('[data-unit="h"]');
    var mEl = banner.querySelector('[data-unit="m"]');
    var sEl = banner.querySelector('[data-unit="s"]');

    (function tick() {
      var diff = Math.max(0, endTimestamp - Date.now());
      var h = Math.floor(diff / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      if (hEl) hEl.textContent = String(h).padStart(2, '0');
      if (mEl) mEl.textContent = String(m).padStart(2, '0');
      if (sEl) sEl.textContent = String(s).padStart(2, '0');
      if (diff > 0) {
        setTimeout(tick, 1000);
      } else {
        banner.style.display = 'none';
      }
    })();
  }

  var flashBanner = document.querySelector('.mec-flash[data-flash-end]');
  if (flashBanner) {
    startCountdown(Number(flashBanner.dataset.flashEnd));
  }

  /* ================================================================
     QUICK ADD TO CART
     ================================================================ */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-quick-add');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    var idProduct = btn.dataset.idProduct;
    var idAttr    = btn.dataset.idProductAttribute || 0;
    if (!idProduct) return;

    var origHTML = btn.innerHTML;
    btn.disabled  = true;
    btn.innerHTML = '<span class="mec-spinner"></span>';

    var fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'update');
    fd.append('add', '1');
    fd.append('qty', '1');
    fd.append('id_product', idProduct);
    fd.append('id_product_attribute', idAttr);
    fd.append('token', (window.prestashop || {}).static_token || '');

    var cartUrl = (window.prestashop && window.prestashop.urls && window.prestashop.urls.pages && window.prestashop.urls.pages.cart)
      ? window.prestashop.urls.pages.cart
      : '/index.php?controller=cart';

    fetch(cartUrl, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    })
      .then(function (r) {
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
      })
      .then(function () {
        btn.innerHTML = '&#10003;';
        btn.style.background = '#1e5c35';

        document.dispatchEvent(new CustomEvent('updateCart', {
          detail: {
            reason: {
              idProduct: idProduct,
              idProductAttribute: idAttr,
              linkAction: 'add-to-cart',
            },
          },
        }));

        setTimeout(function () {
          btn.disabled = false;
          btn.style.background = '';
          btn.innerHTML = origHTML;
        }, 2000);
      })
      .catch(function () {
        btn.disabled = false;
        btn.style.background = '';
        btn.textContent = '⚠ ลองใหม่';
        setTimeout(function () {
          btn.innerHTML = origHTML;
        }, 2000);
      });
  });

  /* ================================================================
     WISHLIST TOGGLE
     toggle class .mec-card__wishlist--active (ไม่เปลี่ยน innerHTML)
     ================================================================ */
  var wishlist = JSON.parse(localStorage.getItem('mec_wishlist') || '[]');

  function syncWishlistUI() {
    document.querySelectorAll('.js-wishlist').forEach(function (btn) {
      var id = btn.dataset.idProduct;
      if (wishlist.indexOf(id) !== -1) {
        btn.classList.add('mec-card__wishlist--active');
        btn.setAttribute('aria-pressed', 'true');
      } else {
        btn.classList.remove('mec-card__wishlist--active');
        btn.setAttribute('aria-pressed', 'false');
      }
    });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-wishlist');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    var id  = btn.dataset.idProduct;
    var idx = wishlist.indexOf(id);
    if (idx === -1) {
      wishlist.push(id);
      btn.style.transform = 'scale(1.4)';
      setTimeout(function () { btn.style.transform = ''; }, 300);
    } else {
      wishlist.splice(idx, 1);
    }
    localStorage.setItem('mec_wishlist', JSON.stringify(wishlist));
    syncWishlistUI();
  });

  syncWishlistUI();

  /* ================================================================
     SORT BUTTONS
     อ่าน data-orderby และ data-orderway attributes
     ================================================================ */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.mec-sort-btn');
    if (!btn) return;

    var orderby  = btn.dataset.orderby;
    var orderway = btn.dataset.orderway || 'asc';
    if (!orderby) return;

    document.querySelectorAll('.mec-sort-btn').forEach(function (b) {
      b.classList.remove('mec-sort-btn--active');
      b.setAttribute('aria-pressed', 'false');
    });
    btn.classList.add('mec-sort-btn--active');
    btn.setAttribute('aria-pressed', 'true');

    var url = new URL(window.location.href);
    url.searchParams.set('orderby', orderby);
    url.searchParams.set('orderway', orderway);
    window.location.href = url.toString();
  });

  /* ================================================================
     LAZY LOAD IMAGES (IntersectionObserver)
     ================================================================ */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          if (img.dataset.src) {
            img.src = img.dataset.src;
            delete img.dataset.src;
          }
          io.unobserve(img);
        }
      });
    }, { rootMargin: '300px' });

    document.querySelectorAll('img.mec-card__img[data-src]').forEach(function (img) {
      io.observe(img);
    });
  }

})();
