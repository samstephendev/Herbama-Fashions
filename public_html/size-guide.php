<?php
/**
 * Heramba Fashion — Size Guide
 * ===============================
 * Comprehensive age-to-size charts for girls, boys, and infants.
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/config/bootstrap.php';

$pageTitle = 'Kidswear Size Guide — Heramba Fashion';
$pageDesc  = 'Find the perfect fit for your child. Comprehensive size charts for girls, boys, and infants aged 0-14 years.';
$bodyClass = 'page-static page-sizeguide';

require_once APP_ROOT . '/includes/header.php';
?>

<div class="collection-hero">
    <div class="container">
        <h1 class="collection-hero__title">Kidswear Size Guide</h1>
        <p class="collection-hero__tagline">Find the Perfect, Comfortable Fit for Your Little One</p>
    </div>
</div>

<div class="container" style="max-width:920px; padding-bottom: 4rem;">
    <div style="background:#FFFFFF; border-radius:18px; padding: clamp(1.75rem, 5vw, 3rem); border:1px solid var(--color-border); box-shadow:var(--shadow-sm); line-height:1.7;">
        
        <p style="font-size:0.95rem; color:var(--color-dark-muted); margin-bottom:2rem; text-align:center;">
            If your child falls between two sizes, we recommend sizing up for comfortable movement and room to grow. For custom bespoke tailoring (5 working days), contact us on WhatsApp!
        </p>

        <!-- Girls Wear Size Chart -->
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.75rem;">Girls Wear (Frocks, Lehengas, Palazzo Sets)</h2>
        <div style="overflow-x:auto; margin-bottom:2.5rem;">
            <table class="size-table">
                <thead>
                    <tr>
                        <th>Age Label</th>
                        <th>Chest (in)</th>
                        <th>Waist (in)</th>
                        <th>Frock Length (in)</th>
                        <th>Approx. Height</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>1 - 2 Years</td><td>20 - 21"</td><td>20"</td><td>18 - 20"</td><td>78 - 86 cm</td></tr>
                    <tr><td>2 - 3 Years</td><td>21 - 22"</td><td>21"</td><td>21 - 23"</td><td>86 - 94 cm</td></tr>
                    <tr><td>3 - 4 Years</td><td>22 - 23"</td><td>22"</td><td>24 - 26"</td><td>94 - 102 cm</td></tr>
                    <tr><td>4 - 5 Years</td><td>23 - 24"</td><td>23"</td><td>26 - 28"</td><td>102 - 110 cm</td></tr>
                    <tr><td>5 - 6 Years</td><td>24 - 25"</td><td>24"</td><td>29 - 31"</td><td>110 - 118 cm</td></tr>
                    <tr><td>6 - 8 Years</td><td>26 - 27"</td><td>25"</td><td>32 - 34"</td><td>118 - 128 cm</td></tr>
                    <tr><td>8 - 10 Years</td><td>28 - 29"</td><td>26"</td><td>35 - 37"</td><td>128 - 138 cm</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Boys Wear Size Chart -->
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.75rem;">Boys Wear (Shirts, Shorts, Kurta Pyjama)</h2>
        <div style="overflow-x:auto; margin-bottom:2.5rem;">
            <table class="size-table">
                <thead>
                    <tr>
                        <th>Age Label</th>
                        <th>Chest (in)</th>
                        <th>Waist (in)</th>
                        <th>Kurta Length (in)</th>
                        <th>Shorts Length (in)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>1 - 2 Years</td><td>21 - 22"</td><td>19 - 20"</td><td>18"</td><td>9"</td></tr>
                    <tr><td>2 - 3 Years</td><td>22 - 23"</td><td>20 - 21"</td><td>20"</td><td>10.5"</td></tr>
                    <tr><td>3 - 4 Years</td><td>23 - 24"</td><td>21 - 22"</td><td>22"</td><td>11.5"</td></tr>
                    <tr><td>4 - 5 Years</td><td>24 - 25"</td><td>22 - 23"</td><td>24"</td><td>12.5"</td></tr>
                    <tr><td>5 - 6 Years</td><td>25 - 26"</td><td>23 - 24"</td><td>26"</td><td>13.5"</td></tr>
                    <tr><td>6 - 8 Years</td><td>27 - 28"</td><td>24 - 25"</td><td>28"</td><td>15"</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Infant Wear Size Chart -->
        <h2 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.4rem; margin-bottom:0.75rem;">Infant Wear (Rompers, Onesies &amp; Sets)</h2>
        <div style="overflow-x:auto; margin-bottom:2rem;">
            <table class="size-table">
                <thead>
                    <tr>
                        <th>Age Label</th>
                        <th>Chest (in)</th>
                        <th>Length (in)</th>
                        <th>Weight (kg)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>0 - 6 Months</td><td>18"</td><td>14 - 15"</td><td>4 - 7 kg</td></tr>
                    <tr><td>6 - 12 Months</td><td>19"</td><td>16 - 17"</td><td>7 - 10 kg</td></tr>
                    <tr><td>12 - 18 Months</td><td>20"</td><td>18 - 19"</td><td>10 - 12 kg</td></tr>
                    <tr><td>18 - 24 Months</td><td>21"</td><td>20 - 21"</td><td>12 - 14 kg</td></tr>
                </tbody>
            </table>
        </div>

        <!-- How to Measure Tips -->
        <div style="background:var(--color-blush-card); border-radius:14px; padding:1.5rem; border:1px solid var(--color-border-subtle);">
            <h3 style="font-family:var(--font-serif); color:var(--color-maroon); font-size:1.2rem; margin-bottom:0.5rem;">How to Measure Your Child</h3>
            <ul style="list-style:disc; padding-left:1.5rem; font-size:0.9rem; color:var(--color-dark-muted); line-height:1.7;">
                <li><strong>Chest:</strong> Measure around the fullest part of your child’s chest, keeping the tape comfortably relaxed under the armpits.</li>
                <li><strong>Waist:</strong> Measure around the natural waistline, just above the belly button.</li>
                <li><strong>Length:</strong> Measure from the top of the shoulder down to where you desire the frock or kurta hem to fall.</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once APP_ROOT . '/includes/footer.php'; ?>
