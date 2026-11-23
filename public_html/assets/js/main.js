/**
 * Heramba Fashion — Main Vanilla JavaScript
 * ===========================================
 * Handles search modal, size guide, wishlist toggles, cart updates,
 * mobile drawer, product gallery, and delivery checkers.
 */

'use strict';

(function () {
    const baseUrl = (window.SITE_URL || '').replace(/\/$/, '');

    /* ── Helper: Global Toast Notification ─────────────────────────────── */
    window.showToast = function (message, icon) {
        icon = icon || '✨';
        const toast = document.getElementById('global-toast');
        if (!toast) return;

        toast.querySelector('.toast-icon').textContent = icon;
        toast.querySelector('.toast-msg').textContent = message;
        toast.classList.add('is-visible');

        clearTimeout(toast._timeout);
        toast._timeout = setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 3200);
    };

    /* ── 1. Mobile Menu Toggle ─────────────────────────────────────────── */
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const primaryNav   = document.getElementById('primary-nav');

    if (mobileToggle && primaryNav) {
        mobileToggle.addEventListener('click', () => {
            const isOpen = primaryNav.classList.toggle('is-open');
            mobileToggle.setAttribute('aria-expanded', String(isOpen));
            document.body.style.overflow = isOpen ? 'hidden' : '';
        });
    }

    /* ── 2. Search Modal ────────────────────────────────────────────────── */
    const searchModal   = document.getElementById('search-modal');
    const openSearchBtn = document.getElementById('open-search-modal');
    const closeSearchBtn= document.getElementById('close-search-modal');
    const searchInput   = document.getElementById('search-live-input');
    const searchResults = document.getElementById('search-results-container');

    if (openSearchBtn && searchModal) {
        openSearchBtn.addEventListener('click', () => {
            searchModal.classList.add('is-open');
            if (searchInput) {
                setTimeout(() => searchInput.focus(), 50);
            }
        });
    }

    if (closeSearchBtn && searchModal) {
        closeSearchBtn.addEventListener('click', () => {
            searchModal.classList.remove('is-open');
        });
    }

    if (searchModal) {
        searchModal.addEventListener('click', (e) => {
            if (e.target === searchModal) {
                searchModal.classList.remove('is-open');
            }
        });
    }

    // Debounced Live Search
    if (searchInput && searchResults) {
        let debounceTimer = null;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const query = searchInput.value.trim();
            if (query.length < 2) return;

            debounceTimer = setTimeout(() => {
                fetch(`${baseUrl}/api/search.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (!data.items || data.items.length === 0) {
                            searchResults.innerHTML = `<p style="padding:1rem 0; color:var(--color-dark-muted); font-size:0.9rem;">No products found for "${query}". Try searching for frocks, kurti or dungarees.</p>`;
                            return;
                        }

                        let html = '<div style="display:flex; flex-direction:column; gap:0.75rem; padding-top:0.75rem;">';
                        data.items.slice(0, 5).forEach(item => {
                            const imgSrc = item.primary_image ? (item.primary_image.startsWith('http') || item.primary_image.startsWith('/') ? item.primary_image : `${baseUrl}/${item.primary_image.replace(/^\//, '')}`) : `${baseUrl}/assets/images/placeholder.svg`;
                            html += `
                                <a href="${baseUrl}/product.php?slug=${encodeURIComponent(item.slug)}" style="display:flex; align-items:center; gap:0.75rem; padding:0.5rem; border-radius:8px; background:var(--color-blush-card); text-decoration:none;">
                                    <img src="${imgSrc}" style="width:44px; height:44px; object-fit:cover; border-radius:6px;" alt="${item.name}">
                                    <div style="flex:1;">
                                        <div style="font-weight:600; font-size:0.9rem; color:var(--color-maroon);">${item.name}</div>
                                        <div style="font-size:0.8rem; color:var(--color-dark-muted);">₹${Number(item.base_price).toLocaleString('en-IN')} &bull; ${item.category_name || ''}</div>
                                    </div>
                                    <span style="font-size:0.8rem; font-weight:600; color:var(--color-gold-dark);">View &rarr;</span>
                                </a>
                            `;
                        });
                        html += `</div>
                        <div style="text-align:center; padding-top:1rem;">
                            <a href="${baseUrl}/search.php?q=${encodeURIComponent(query)}" style="font-size:0.85rem; font-weight:600; color:var(--color-maroon); text-decoration:underline;">View all results &rarr;</a>
                        </div>`;
                        searchResults.innerHTML = html;
                    })
                    .catch(() => {});
            }, 250);
        });
    }

    /* ── 3. Size Guide Modal ────────────────────────────────────────────── */
    const sizeGuideModal = document.getElementById('size-guide-modal');
    const closeSizeGuide = document.getElementById('close-size-guide-modal');

    document.querySelectorAll('.size-guide-modal-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            if (sizeGuideModal) sizeGuideModal.classList.add('is-open');
        });
    });

    if (closeSizeGuide && sizeGuideModal) {
        closeSizeGuide.addEventListener('click', () => {
            sizeGuideModal.classList.remove('is-open');
        });
    }

    if (sizeGuideModal) {
        sizeGuideModal.addEventListener('click', (e) => {
            if (e.target === sizeGuideModal) {
                sizeGuideModal.classList.remove('is-open');
            }
        });
    }

    // Global Escape Key to close modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (searchModal) searchModal.classList.remove('is-open');
            if (sizeGuideModal) sizeGuideModal.classList.remove('is-open');
            const filterDrawer = document.getElementById('filter-sidebar');
            if (filterDrawer) filterDrawer.classList.remove('is-open');
        }
    });

    /* ── 4. Wishlist Toggle ─────────────────────────────────────────────── */
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-wishlist-id]');
        if (!btn) return;

        e.preventDefault();
        const pId = btn.getAttribute('data-wishlist-id');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const formData = new FormData();
        formData.append('product_id', pId);
        formData.append('_csrf', csrf);

        fetch(`${baseUrl}/api/wishlist.php`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            if (data.ok) {
                const isActive = data.action === 'added';
                btn.classList.toggle('is-active', isActive);
                const svg = btn.querySelector('svg');
                if (svg) svg.setAttribute('fill', isActive ? 'currentColor' : 'none');

                const badge = document.getElementById('wishlist-badge');
                if (badge && typeof data.count !== 'undefined') {
                    badge.textContent = data.count;
                    badge.style.display = data.count > 0 ? '' : 'none';
                }

                showToast(isActive ? 'Added to your wishlist!' : 'Removed from wishlist', '❤️');
            }
        })
        .catch(() => {
            showToast('Please log in to manage your wishlist', 'ℹ️');
        });
    });

    /* ── 5. Newsletter Subscription ─────────────────────────────────────── */
    const newsletterForm = document.getElementById('newsletter-subscribe-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(newsletterForm);

            fetch(`${baseUrl}/api/subscribe.php`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    showToast(data.message || 'Thank you for subscribing!', '💌');
                    newsletterForm.reset();
                } else {
                    showToast(data.error || 'Subscription failed', '⚠️');
                }
            })
            .catch(() => {
                showToast('Unable to subscribe right now', '⚠️');
            });
        });
    }

    /* ── 6. Pincode Delivery Availability Checker ────────────────────────── */
    const pincodeBtn   = document.getElementById('check-pincode-btn');
    const pincodeInput = document.getElementById('pincode-input');
    const pincodeRes   = document.getElementById('pincode-result');

    if (pincodeBtn && pincodeInput && pincodeRes) {
        pincodeBtn.addEventListener('click', () => {
            const pin = pincodeInput.value.trim();
            if (/^\d{6}$/.test(pin)) {
                pincodeRes.innerHTML = `
                    <span style="color:var(--color-success); font-weight:600;">✓ Delivery Available!</span><br>
                    Estimated delivery in <strong>3-5 Working Days</strong> via Express Courier.<br>
                    <span style="font-size:11.5px; color:var(--color-dark-muted);">Sizes 0-3 Years are ready for immediate dispatch.</span>
                `;
            } else if (/^uae|dubai/i.test(pin) || pin.length === 5) {
                pincodeRes.innerHTML = `
                    <span style="color:var(--color-maroon); font-weight:600;">🌍 UAE / Dubai International Dispatch</span><br>
                    Dispatches directly to UAE within 5-7 business days.<br>
                    <a href="https://wa.me/919876543210?text=Inquiry%20for%20UAE%20Shipping" style="color:#1EAA52; font-weight:600; text-decoration:underline;">Confirm on WhatsApp &rarr;</a>
                `;
            } else {
                pincodeRes.innerHTML = `<span style="color:var(--color-danger);">Please enter a valid 6-digit Indian PIN code.</span>`;
            }
        });
    }

    /* ── 7. Product Detail Page Image Swap & Options ─────────────────────── */
    const mainImg = document.getElementById('product-main-image');
    document.querySelectorAll('.product-thumb').forEach(thumb => {
        thumb.addEventListener('click', () => {
            document.querySelectorAll('.product-thumb').forEach(t => t.classList.remove('is-active'));
            thumb.classList.add('is-active');

            const newSrc = thumb.getAttribute('data-img-src');
            if (mainImg && newSrc) {
                mainImg.style.opacity = '0.4';
                setTimeout(() => {
                    mainImg.src = newSrc;
                    mainImg.style.opacity = '1';
                }, 120);
            }
        });
    });

    // Tab Switching
    document.querySelectorAll('.product-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-tab-target');
            document.querySelectorAll('.product-tab-btn').forEach(b => b.classList.remove('is-active'));
            document.querySelectorAll('.product-tab-pane').forEach(p => p.classList.remove('is-active'));

            btn.classList.add('is-active');
            const targetPane = document.getElementById(targetId);
            if (targetPane) targetPane.classList.add('is-active');
        });
    });

    // Quantity Stepper
    const qtyInput = document.getElementById('product-quantity-input');
    const qtyMinus = document.getElementById('qty-minus');
    const qtyPlus  = document.getElementById('qty-plus');

    if (qtyInput && qtyMinus && qtyPlus) {
        qtyMinus.addEventListener('click', () => {
            let val = parseInt(qtyInput.value, 10) || 1;
            if (val > 1) qtyInput.value = val - 1;
        });

        qtyPlus.addEventListener('click', () => {
            let val = parseInt(qtyInput.value, 10) || 1;
            const max = parseInt(qtyInput.getAttribute('max') || '10', 10);
            if (val < max) qtyInput.value = val + 1;
        });
    }

    // Size Chip Selection
    document.querySelectorAll('.size-chip-btn').forEach(chip => {
        chip.addEventListener('click', () => {
            if (chip.disabled) return;
            document.querySelectorAll('.size-chip-btn').forEach(c => c.classList.remove('is-selected'));
            chip.classList.add('is-selected');

            const variantIdInput = document.getElementById('selected-variant-id');
            if (variantIdInput) {
                variantIdInput.value = chip.getAttribute('data-variant-id');
            }
        });
    });

    // Colour Swatch Selection
    document.querySelectorAll('.color-swatch-btn').forEach(swatch => {
        swatch.addEventListener('click', () => {
            document.querySelectorAll('.color-swatch-btn').forEach(s => s.classList.remove('is-selected'));
            swatch.classList.add('is-selected');
            const colorName = swatch.getAttribute('data-color-name');
            const colorLabel = document.getElementById('selected-color-label');
            if (colorLabel) colorLabel.textContent = colorName;
        });
    });

})();
