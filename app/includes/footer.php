<?php
/**
 * Site Footer Include — Herbama Fashions
 * ========================================
 * Master layout footer with 5-point trust strip, 4-column maroon footer,
 * newsletter subscription, social links, and floating WhatsApp button.
 */
declare(strict_types=1);

$settingModel = new SettingModel();
$instaUrl = (string)$settingModel->get('instagram_url', 'https://instagram.com/herbamafashions');
$fbUrl    = (string)$settingModel->get('facebook_url', 'https://facebook.com/herbamafashions');
?>
</main><!-- /#main-content -->

<!-- ── 1. Trust Strip Above Footer ──────────────────────────────────────── -->
<section class="trust-strip" aria-label="Herbama Fashions Guarantees">
    <div class="container">
        <div class="trust-strip__grid">
            <div class="trust-strip__item">
                <div class="trust-strip__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                </div>
                <div>
                    <h4 class="trust-strip__title">Pan-India Shipping</h4>
                    <p class="trust-strip__sub">Fast express delivery to all pin codes</p>
                </div>
            </div>

            <div class="trust-strip__item">
                <div class="trust-strip__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="trust-strip__title">International Dispatch</h4>
                    <p class="trust-strip__sub">Direct dispatch to UAE / Dubai</p>
                </div>
            </div>

            <div class="trust-strip__item">
                <div class="trust-strip__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div>
                    <h4 class="trust-strip__title">0-3 Years Ready to Ship</h4>
                    <p class="trust-strip__sub">Instant dispatch for baby &amp; toddler wear</p>
                </div>
            </div>

            <div class="trust-strip__item">
                <div class="trust-strip__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="6" cy="6" r="3"></circle>
                        <circle cx="6" cy="18" r="3"></circle>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                    </svg>
                </div>
                <div>
                    <h4 class="trust-strip__title">Customization in 5 Days</h4>
                    <p class="trust-strip__sub">Tailored to perfection by master artisans</p>
                </div>
            </div>

            <div class="trust-strip__item">
                <div class="trust-strip__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="trust-strip__title">Premium Quality Kidswear</h4>
                    <p class="trust-strip__sub">100% gentle, safe &amp; breathable fabrics</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── 2. Master Maroon Footer ──────────────────────────────────────────── -->
<footer class="site-footer" role="contentinfo">
    <div class="container">
        <div class="site-footer__grid">
            <!-- Brand Column -->
            <div class="site-footer__brand-col">
                <div class="site-footer__brand-logo" style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
                    <picture>
                        <source srcset="<?= e(asset('/assets/images/brand/logo-128.webp')) ?> 2x,
                                        <?= e(asset('/assets/images/brand/logo-64.webp')) ?> 1x"
                                type="image/webp">
                        <img src="<?= e(asset('/assets/images/brand/logo-64.png')) ?>"
                             srcset="<?= e(asset('/assets/images/brand/logo-128.png')) ?> 2x"
                             alt="Herabama Fashions logo — round maroon and gold badge"
                             width="56" height="56"
                             loading="lazy"
                             style="border-radius:50%;">
                    </picture>
                    <h3 class="site-footer__brand-title" style="margin:0;font-family:var(--font-heading,serif);">Herabama Fashions</h3>
                </div>
                <p class="site-footer__tagline">Premium Kidswear for Every Little Fashionista</p>
                <p style="font-size:0.85rem; color:rgba(255,249,243,0.75); line-height:1.6; margin-bottom:1.25rem;">
                    From playful everyday essentials to regal festive ensembles, we dress your little stars in comfort, love, and unmatched style.
                </p>
                <!-- Social Icons -->
                <div class="site-footer__socials">
                    <a href="<?= e($instaUrl) ?>" class="site-footer__social-link" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                        </svg>
                    </a>
                    <a href="<?= e($fbUrl) ?>" class="site-footer__social-link" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                        </svg>
                    </a>
                    <a href="https://youtube.com/@herbamafashions" class="site-footer__social-link" target="_blank" rel="noopener noreferrer" aria-label="Watch on YouTube">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33zM9.75 15.02V8.48l5.75 3.27-5.75 3.27z"/>
                        </svg>
                    </a>
                    <a href="https://pinterest.com/herbamafashions" class="site-footer__social-link" target="_blank" rel="noopener noreferrer" aria-label="Follow on Pinterest">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0a12 12 0 0 0-4.37 23.18c-.07-.98-.13-2.5.03-3.58l1.09-4.63s-.28-.56-.28-1.39c0-1.3.75-2.28 1.7-2.28.8 0 1.18.6 1.18 1.33 0 .81-.51 2.01-.78 3.13-.22.94.47 1.71 1.4 1.71 1.68 0 2.97-1.77 2.97-4.32 0-2.26-1.62-3.84-3.94-3.84-2.68 0-4.26 2.01-4.26 4.09 0 .81.31 1.68.7 2.15.08.1.09.18.07.28l-.26 1.07c-.04.17-.14.21-.32.13-1.2-.56-1.95-2.31-1.95-3.72 0-3.03 2.2-5.81 6.35-5.81 3.33 0 5.93 2.38 5.93 5.56 0 3.32-2.09 5.98-5 5.98-.98 0-1.9-.51-2.21-1.11l-.6 2.3c-.22.84-.81 1.9-1.21 2.54A12 12 0 1 0 12 0z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Column 1: Shop -->
            <div>
                <h4 class="site-footer__heading">Shop</h4>
                <ul class="site-footer__links">
                    <li><a href="<?= url('/collection.php?cat=girls') ?>">Girls Wear</a></li>
                    <li><a href="<?= url('/collection.php?cat=boys') ?>">Boys Wear</a></li>
                    <li><a href="<?= url('/collection.php?cat=infant') ?>">Infant Wear</a></li>
                    <li><a href="<?= url('/collection.php?cat=festive') ?>">Festive Collection</a></li>
                    <li><a href="<?= url('/new-arrivals.php') ?>">New Arrivals</a></li>
                    <li><a href="<?= url('/offers.php') ?>">Special Offers</a></li>
                </ul>
            </div>

            <!-- Column 2: Help -->
            <div>
                <h4 class="site-footer__heading">Help</h4>
                <ul class="site-footer__links">
                    <li><a href="<?= url('/faq.php') ?>">FAQs</a></li>
                    <li><a href="<?= url('/shipping.php') ?>">Shipping &amp; Delivery</a></li>
                    <li><a href="<?= url('/returns.php') ?>">Returns &amp; Exchange</a></li>
                    <li><a href="<?= url('/size-guide.php') ?>">Size Guide</a></li>
                    <li><a href="<?= url('/contact.php') ?>">Contact Us</a></li>
                </ul>
            </div>

            <!-- Column 3: About & Newsletter -->
            <div>
                <h4 class="site-footer__heading">About Herbama</h4>
                <ul class="site-footer__links" style="margin-bottom:1.5rem;">
                    <li><a href="<?= url('/about.php') ?>">Our Story</a></li>
                    <li><a href="<?= url('/why-herbama.php') ?>">Why Herbama</a></li>
                    <li><a href="<?= url('/quality-fabric.php') ?>">Quality &amp; Fabric</a></li>
                    <li><a href="<?= url('/privacy.php') ?>">Privacy Policy</a></li>
                    <li><a href="<?= url('/terms.php') ?>">Terms &amp; Conditions</a></li>
                </ul>

                <!-- Stay In The Loop -->
                <h4 class="site-footer__heading" style="margin-bottom:0.5rem;">Stay in the Loop</h4>
                <p class="site-footer__newsletter-desc">Get secret festive discounts &amp; first access to new collections.</p>
                <form id="newsletter-subscribe-form" class="newsletter-form" action="<?= url('/api/subscribe.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="email" name="email" class="newsletter-input" placeholder="Enter your email address" required>
                    <button type="submit" class="newsletter-btn">Join</button>
                </form>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="site-footer__bottom">
            <div>
                &copy; <?= date('Y') ?> <?= SITE_NAME ?>. Handcrafted with love for little ones.
            </div>
            <div>
                Pan-India Delivery &nbsp;|&nbsp; UAE &amp; Dubai Dispatch &nbsp;|&nbsp; 💳 Online Payment Only (UPI)
            </div>
        </div>
    </div>
</footer>

<!-- ── 3. Floating WhatsApp Chat Button ──────────────────────────────────── -->
<?php require_once APP_ROOT . '/includes/whatsapp-button.php'; ?>

<!-- ── 4. Global Toast Notification Box ──────────────────────────────────── -->
<div class="toast-notice" id="global-toast" role="status" aria-live="polite">
    <span class="toast-icon">✨</span>
    <span class="toast-msg">Item added to cart!</span>
</div>

<!-- ── 5. Global Vanilla JS ──────────────────────────────────────────────── -->
<script src="<?= asset('js/main.js') ?>"></script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>

</body>
</html>
