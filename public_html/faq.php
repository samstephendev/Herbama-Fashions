<?php
/**
 * Heramba Fashion — FAQs Page
 * ==============================
 * Interactive accordion with answers to common shipping, payment, custom order, and sizing questions.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Frequently Asked Questions (FAQs) — Heramba Fashion';
$pageDesc  = 'Find answers about ordering, custom tailoring, online UPI payments, shipping across India and UAE, and returns.';
$bodyClass = 'page-static page-faq';

$faqs = [
    [
        'q' => 'How does ordering and payment work at Heramba Fashion?',
        'a' => 'Our ordering process is smooth and personal! You browse our collections, select sizes, and click Checkout. Your order is registered in our system and opens a pre-filled WhatsApp message directly to our boutique team. You confirm any special preferences, complete your payment safely via UPI ("Online Payment Only, No COD"), and our team confirms dispatch.'
    ],
    [
        'q' => 'Why is there no Cash on Delivery (COD)?',
        'a' => 'Because our kidswear is crafted in small, artisanal batches and often tailored to specific measurements, we operate on an "Online Payment Only, No COD" model via instant UPI. This guarantees genuine orders, prevents transit returns, and keeps our prices accessible without inflated COD fees.'
    ],
    [
        'q' => 'How long does custom tailoring take?',
        'a' => 'Customization takes 5 working days from the moment your measurements and payment are confirmed on WhatsApp. Our master tailors craft the piece specifically for your child before dispatching it via express courier.'
    ],
    [
        'q' => 'Which sizes are ready for immediate dispatch?',
        'a' => 'Sizes from 0 to 3 Years (0-6M, 6-12M, 1-2Y, 2-3Y) in our core collections are kept in stock and ready to ship within 24 to 48 hours.'
    ],
    [
        'q' => 'Do you ship to the UAE (Dubai, Abu Dhabi, Sharjah)?',
        'a' => 'Yes! We dispatch directly to the UAE via international air express. Deliveries typically arrive within 5 to 7 business days. You can place your order online and coordinate international shipping details on WhatsApp.'
    ],
    [
        'q' => 'What if the size does not fit my child?',
        'a' => 'We offer an easy 7-day size exchange on all standard collection items. Simply reach out to us on WhatsApp with your Order ID, and we will guide you through the quick replacement process.'
    ],
    [
        'q' => 'Are your fabrics safe for sensitive baby skin?',
        'a' => 'Absolutely. We only use 100% natural breathable combed cottons, pure mulmul linings, and child-safe non-toxic dyes. All interior seams are cleanly finished with zero scratchy threads.'
    ],
];

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Frequently Asked Questions</h1>
        <p class="collection-hero__tagline">Everything You Need to Know About Ordering, Delivery &amp; Sizing</p>
    </div>
</div>

<div class="container" style="max-width:860px; padding-bottom: 4rem;">
    <div style="display:flex; flex-direction:column; gap:1rem;">
        <?php foreach ($faqs as $idx => $faq): ?>
            <div class="faq-accordion-item" style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:14px; overflow:hidden; box-shadow:var(--shadow-sm);">
                <button type="button" 
                        class="faq-accordion-trigger" 
                        style="width:100%; text-align:left; padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; font-family:var(--font-serif); font-size:1.15rem; font-weight:700; color:var(--color-maroon); background:none; cursor:pointer;"
                        aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>">
                    <span><?= e($faq['q']) ?></span>
                    <span class="faq-icon" style="font-size:1.3rem; transition:transform 200ms ease; color:var(--color-gold-dark);"><?= $idx === 0 ? '−' : '+' ?></span>
                </button>
                <div class="faq-accordion-content" style="padding:0 1.5rem 1.25rem; font-size:0.925rem; color:var(--color-dark-muted); line-height:1.7; display:<?= $idx === 0 ? 'block' : 'none' ?>;">
                    <?= e($faq['a']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Need more help CTA -->
    <div style="margin-top:3rem; text-align:center; padding:2rem; background:var(--color-blush-card); border-radius:16px; border:1px solid var(--color-border-subtle);">
        <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.5rem;">Have a Question Not Listed Here?</h3>
        <p style="color:var(--color-dark-muted); font-size:0.9rem; margin-bottom:1.25rem;">Chat directly with our boutique styling team on WhatsApp for immediate guidance.</p>
        <a href="https://wa.me/919876543210?text=Hello%20Heramba%20Fashions!%20I%20have%20a%20question." class="btn-pill btn-pill-primary" target="_blank" rel="noopener noreferrer">
            Ask on WhatsApp &rarr;
        </a>
    </div>
</div>

<script>
document.querySelectorAll('.faq-accordion-trigger').forEach(trigger => {
    trigger.addEventListener('click', () => {
        const item = trigger.closest('.faq-accordion-item');
        const content = item.querySelector('.faq-accordion-content');
        const icon = trigger.querySelector('.faq-icon');
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (isOpen) {
            content.style.display = 'none';
            trigger.setAttribute('aria-expanded', 'false');
            icon.textContent = '+';
        } else {
            content.style.display = 'block';
            trigger.setAttribute('aria-expanded', 'true');
            icon.textContent = '−';
        }
    });
});
</script>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
