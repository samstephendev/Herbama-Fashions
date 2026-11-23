<?php
/**
 * Heramba Fashion — Contact Us Page
 * =====================================
 * Contact details, address, WhatsApp direct link, and message form saved to messages table.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Contact Us — Heramba Fashion';
$pageDesc  = 'Get in touch with Heramba Fashion. Chat on WhatsApp, email us, or send an inquiry for custom kidswear orders.';
$bodyClass = 'page-static page-contact';

$settingModel = new SettingModel();
$waNumber = (string)$settingModel->get('whatsapp_number', WHATSAPP_NUMBER);
$cleanWa = preg_replace('/[^0-9]/', '', $waNumber);

$successMsg = '';
$errorMsg   = '';

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name    = post('name');
    $email   = post('email');
    $phone   = post('phone');
    $subject = post('subject', 'Customer Inquiry');
    $message = post('message');

    $contactModel = new ContactModel();
    $res = $contactModel->sendMessage($name, $email, $phone, $subject, $message);

    if ($res['ok']) {
        $successMsg = $res['message'];
    } else {
        $errorMsg = $res['error'] ?? 'Please fill in all required fields.';
    }
}

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Contact Us</h1>
        <p class="collection-hero__tagline">We are Here to Help You Style Your Little Star</p>
    </div>
</div>

<div class="container" style="max-width:1080px; padding-bottom: 4rem;">
    
    <?php if ($successMsg): ?>
        <div class="alert alert-success" style="margin-bottom:2rem;"><?= e($successMsg) ?></div>
    <?php elseif ($errorMsg): ?>
        <div class="alert alert-error" style="margin-bottom:2rem;"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 1.35fr; gap:2.5rem; align-items:start;">
        
        <!-- Left: Contact Details & Direct WhatsApp Card -->
        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:18px; padding:2rem; box-shadow:var(--shadow-sm);">
            <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:1.25rem;">Get In Touch</h2>
            
            <div style="margin-bottom:1.5rem;">
                <strong style="display:block; color:var(--color-maroon); font-size:0.95rem; margin-bottom:2px;">💬 Quickest Support: WhatsApp</strong>
                <p style="font-size:0.875rem; color:var(--color-dark-muted); margin-bottom:0.75rem;">For custom orders, size advice, or payment updates, our team replies fastest on WhatsApp.</p>
                <a href="https://wa.me/<?= e($cleanWa) ?>?text=Hello%20Heramba%20Fashions!%20I%20have%20an%20inquiry." 
                   class="btn-pill btn-pill-primary" 
                   style="background:#25D366; border-color:#25D366; color:#FFF; font-size:0.85rem; padding:0.6rem 1.25rem;"
                   target="_blank" 
                   rel="noopener noreferrer">
                    Open WhatsApp Chat &rarr;
                </a>
            </div>

            <div style="border-top:1px solid var(--color-border-subtle); padding-top:1.25rem; margin-bottom:1.25rem;">
                <strong style="display:block; color:var(--color-maroon); font-size:0.95rem; margin-bottom:2px;">📧 Email Us</strong>
                <a href="mailto:<?= e(ADMIN_EMAIL) ?>" style="font-size:0.9rem; color:var(--color-dark);"><?= e(ADMIN_EMAIL) ?></a>
                <p style="font-size:0.8rem; color:var(--color-dark-muted); margin-top:2px;">We reply within 24 business hours.</p>
            </div>

            <div style="border-top:1px solid var(--color-border-subtle); padding-top:1.25rem; margin-bottom:1.25rem;">
                <strong style="display:block; color:var(--color-maroon); font-size:0.95rem; margin-bottom:2px;">📍 Studio &amp; Workshop</strong>
                <p style="font-size:0.875rem; color:var(--color-dark); line-height:1.6; margin:0;">
                    Heramba Fashion Atelier<br>
                    Crafts &amp; Handloom Hub, Pan-India Dispatch<br>
                    UAE International Air Delivery Hub
                </p>
            </div>

            <div style="border-top:1px solid var(--color-border-subtle); padding-top:1.25rem;">
                <strong style="display:block; color:var(--color-maroon); font-size:0.95rem; margin-bottom:2px;">⏰ Working Hours</strong>
                <p style="font-size:0.85rem; color:var(--color-dark-muted); margin:0;">Monday &ndash; Saturday: 9:30 AM &ndash; 7:30 PM IST</p>
            </div>
        </div>

        <!-- Right: Message Form -->
        <div style="background:#FFFFFF; border:1px solid var(--color-border); border-radius:18px; padding:2rem; box-shadow:var(--shadow-sm);">
            <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.5rem; margin-bottom:0.5rem;">Send Us a Message</h2>
            <p style="font-size:0.875rem; color:var(--color-dark-muted); margin-bottom:1.5rem;">Fill out the form below and our styling team will get back to you shortly.</p>

            <form action="/contact.php" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="contact-name" class="form-label form-label--required">Your Full Name</label>
                    <input type="text" id="contact-name" name="name" class="form-control" placeholder="e.g. Priya Sundaram" required maxlength="120">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label for="contact-email" class="form-label form-label--required">Email Address</label>
                        <input type="email" id="contact-email" name="email" class="form-control" placeholder="priya@example.com" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="contact-phone" class="form-label">Phone / WhatsApp</label>
                        <input type="tel" id="contact-phone" name="phone" class="form-control" placeholder="+91 98765 43210" maxlength="20">
                    </div>
                </div>

                <div class="form-group">
                    <label for="contact-subject" class="form-label">Subject</label>
                    <select id="contact-subject" name="subject" class="form-control">
                        <option value="Custom Order Inquiry">Custom Tailoring (5 Working Days)</option>
                        <option value="Order Status Question">Order Status / Delivery Question</option>
                        <option value="UAE / International Shipping">UAE / Dubai Dispatch Inquiry</option>
                        <option value="Size Consultation">Size &amp; Fit Consultation</option>
                        <option value="General Question">General Feedback or Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="contact-message" class="form-label form-label--required">Your Message</label>
                    <textarea id="contact-message" name="message" class="form-control" rows="5" placeholder="Please describe what you are looking for (e.g. age of child, occasion, color preferences)..." required maxlength="3000"></textarea>
                </div>

                <button type="submit" class="btn-pill btn-pill-primary" style="width:100%; height:46px;">
                    Send Message &rarr;
                </button>
            </form>
        </div>

    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
