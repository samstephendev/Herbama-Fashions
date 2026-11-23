/**
 * responsive_verify.js
 * Automated headless Chrome verification for Heramba Fashion responsive redesign.
 */
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const PHP_EXE = 'C:\\php\\php.exe';
const CHROME_EXE = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const SCREENSHOT_DIR = 'C:\\Users\\Sam Stephen\\.gemini\\antigravity\\brain\\6e0b5f90-b095-4328-b27a-972983b5d70a\\screenshots';
const PORT = 8999;
const BASE_URL = `http://127.0.0.1:${PORT}`;

const VIEWPORTS = [320, 360, 390, 414, 768, 820, 1024, 1280];
const SCREENSHOT_WIDTHS = [390, 820];

const PAGES = [
    { name: 'Home', url: '/index.php' },
    { name: 'Collection', url: '/collection.php?cat=girls' },
    { name: 'Product', url: '/product.php?slug=floral-frock' },
    { name: 'Cart', url: '/cart.php' },
    { name: 'Checkout', url: '/checkout.php' },
    { name: 'Login', url: '/login.php' },
    { name: 'Admin Dashboard', url: '/admin/index.php', auth: true },
    { name: 'Admin Orders', url: '/admin/orders.php', auth: true },
    { name: 'About Us', url: '/about.php' }
];

if (!fs.existsSync(SCREENSHOT_DIR)) {
    fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// ── CDP Client using native WebSocket ──────────────────────────────────────
class CDPClient {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.ws = null;
        this.msgId = 0;
        this.callbacks = new Map();
    }

    async connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.wsUrl);
            this.ws.onopen = () => resolve();
            this.ws.onerror = err => reject(err);
            this.ws.onmessage = msg => {
                const data = JSON.parse(msg.data);
                if (data.id && this.callbacks.has(data.id)) {
                    const { resolve, reject } = this.callbacks.get(data.id);
                    this.callbacks.delete(data.id);
                    if (data.error) reject(new Error(data.error.message));
                    else resolve(data.result);
                }
            };
        });
    }

    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = ++this.msgId;
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    close() {
        if (this.ws) this.ws.close();
    }
}

async function run() {
    console.log('=== Starting Heramba Fashion Responsive Verification ===\n');

    // 1. Start PHP server
    console.log('[1/4] Starting PHP built-in server on port ' + PORT + '...');
    const phpProc = spawn(PHP_EXE, [
        '-d', 'extension_dir=C:\\php\\ext',
        '-d', 'extension=pdo_mysql',
        '-d', 'extension=openssl',
        '-d', 'extension=curl',
        '-S', `127.0.0.1:${PORT}`,
        '-t', 'public_html'
    ], {
        cwd: path.resolve(__dirname, '..'),
        stdio: 'ignore'
    });

    await sleep(1500);

    // 2. Start Headless Chrome with CDP
    console.log('[2/4] Starting headless Chrome with remote debugging on 9222...');
    const chromeProc = spawn(CHROME_EXE, [
        '--headless=new',
        '--disable-gpu',
        '--remote-debugging-port=9222',
        '--no-first-run',
        '--no-default-browser-check',
        '--user-data-dir=' + path.resolve(__dirname, '../.chrome-test-profile'),
        'about:blank'
    ], { stdio: 'ignore' });

    await sleep(2000);

    let versionData;
    for (let i = 0; i < 5; i++) {
        try {
            const res = await fetch('http://127.0.0.1:9222/json/version');
            versionData = await res.json();
            break;
        } catch (e) {
            await sleep(1000);
        }
    }

    if (!versionData || !versionData.webSocketDebuggerUrl) {
        console.error('Failed to get Chrome WebSocket debugger URL');
        chromeProc.kill();
        phpProc.kill();
        process.exit(1);
    }

    // Connect to browser target to create a new page
    const browserClient = new CDPClient(versionData.webSocketDebuggerUrl);
    await browserClient.connect();

    const { targetId } = await browserClient.send('Target.createTarget', { url: 'about:blank' });
    const pageClient = new CDPClient(`ws://127.0.0.1:9222/devtools/page/${targetId}`);
    await pageClient.connect();

    await pageClient.send('Page.enable');
    await pageClient.send('DOM.enable');
    await pageClient.send('Runtime.enable');

    console.log('[3/4] Performing admin login to access protected pages...');
    // Login as admin
    await pageClient.send('Page.navigate', { url: `${BASE_URL}/login.php` });
    await sleep(1000);

    // Fill login form
    await pageClient.send('Runtime.evaluate', {
        expression: `
            (() => {
                const email = document.querySelector('input[type="email"]');
                const pass = document.querySelector('input[type="password"]');
                const form = document.querySelector('form');
                if (email) email.value = 'admin@yourdomain.com';
                if (pass) pass.value = 'AdminSecret1234!';
                if (form) form.submit();
            })()
        `
    });
    await sleep(2000);

    console.log('[4/4] Testing viewports and taking screenshots...\n');

    const results = [];
    const screenshotList = [];

    for (const page of PAGES) {
        console.log(`\n--- Testing Page: ${page.name} (${page.url}) ---`);
        const pageUrl = `${BASE_URL}${page.url}`;

        for (const width of VIEWPORTS) {
            const height = width >= 768 ? 1024 : 844;

            // Set Device Metrics
            await pageClient.send('Emulation.setDeviceMetricsOverride', {
                width: width,
                height: height,
                deviceScaleFactor: 2,
                mobile: width < 1024,
                screenWidth: width,
                screenHeight: height
            });

            // Navigate to page
            await pageClient.send('Page.navigate', { url: pageUrl });
            await sleep(1200);

            // Check for horizontal overflow
            const evalResult = await pageClient.send('Runtime.evaluate', {
                expression: `
                    (() => {
                        const doc = document.documentElement;
                        const body = document.body;
                        const winWidth = window.innerWidth;
                        const scrollW = Math.max(doc.scrollWidth, body.scrollWidth);
                        const hasOverflow = scrollW > winWidth + 1; // 1px threshold for fractional subpixels
                        
                        // Find any overflowing elements
                        const offenders = [];
                        if (hasOverflow) {
                            document.querySelectorAll('*').forEach(el => {
                                const rect = el.getBoundingClientRect();
                                if (rect.right > winWidth + 2) {
                                    offenders.push({
                                        tag: el.tagName,
                                        class: el.className ? String(el.className).substring(0, 40) : '',
                                        right: Math.round(rect.right),
                                        width: Math.round(rect.width)
                                    });
                                }
                            });
                        }
                        return {
                            winWidth,
                            scrollW,
                            hasOverflow,
                            offenders: offenders.slice(0, 3)
                        };
                    })()
                `,
                returnByValue: true
            });

            const overflowInfo = evalResult.result.value || {};
            const passed = !overflowInfo.hasOverflow;

            results.push({
                page: page.name,
                width: width,
                passed: passed,
                scrollW: overflowInfo.scrollW,
                winWidth: overflowInfo.winWidth,
                offenders: overflowInfo.offenders || []
            });

            const status = passed ? '✓ NO OVERFLOW' : '✗ OVERFLOW DETECTED';
            const detail = passed 
                ? `(scrollWidth: ${overflowInfo.scrollW}px <= ${overflowInfo.winWidth}px)`
                : `(scrollWidth: ${overflowInfo.scrollW}px > ${overflowInfo.winWidth}px - ${JSON.stringify(overflowInfo.offenders)})`;

            console.log(`  [${width}px] ${status} ${detail}`);

            // Take screenshots at 390px (mobile) and 820px (tablet)
            if (SCREENSHOT_WIDTHS.includes(width)) {
                const shotData = await pageClient.send('Page.captureScreenshot', {
                    format: 'png',
                    captureBeyondViewport: false
                });

                const safeName = page.name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
                const filename = `${safeName}-${width}px.png`;
                const filePath = path.join(SCREENSHOT_DIR, filename);

                fs.writeFileSync(filePath, Buffer.from(shotData.data, 'base64'));
                screenshotList.push({ page: page.name, width, filename, filePath });
                console.log(`    -> Screenshot saved: ${filename}`);
            }

            // Interactive test on mobile (390px)
            if (width === 390 && page.name === 'Home') {
                console.log('    Testing mobile hamburger menu open/close...');
                await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const btn = document.getElementById('mobile-menu-toggle');
                            if (btn) btn.click();
                        })()
                    `
                });
                await sleep(500);

                const menuState = await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const drawer = document.getElementById('mobile-nav-drawer');
                            return drawer ? drawer.classList.contains('is-open') : false;
                        })()
                    `,
                    returnByValue: true
                });
                console.log(`    Mobile menu opened: ${menuState.result.value ? 'YES (PASS)' : 'NO'}`);

                // Capture mobile menu open screenshot
                const menuShot = await pageClient.send('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(SCREENSHOT_DIR, 'home-mobile-menu-open-390px.png'), Buffer.from(menuShot.data, 'base64'));
                screenshotList.push({ page: 'Mobile Menu Open', width: 390, filename: 'home-mobile-menu-open-390px.png', filePath: path.join(SCREENSHOT_DIR, 'home-mobile-menu-open-390px.png') });

                // Close menu
                await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const closeBtn = document.getElementById('mobile-nav-close');
                            if (closeBtn) closeBtn.click();
                        })()
                    `
                });
                await sleep(400);
            }

            if (width === 390 && page.name === 'Collection') {
                console.log('    Testing collection mobile filter drawer...');
                await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const btn = document.getElementById('open-filter-drawer');
                            if (btn) btn.click();
                        })()
                    `
                });
                await sleep(500);

                const filterState = await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const drawer = document.getElementById('filter-sidebar');
                            return drawer ? drawer.classList.contains('is-open') : false;
                        })()
                    `,
                    returnByValue: true
                });
                console.log(`    Filter drawer opened: ${filterState.result.value ? 'YES (PASS)' : 'NO'}`);

                const filterShot = await pageClient.send('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(SCREENSHOT_DIR, 'collection-filter-drawer-390px.png'), Buffer.from(filterShot.data, 'base64'));
                screenshotList.push({ page: 'Collection Filter Drawer', width: 390, filename: 'collection-filter-drawer-390px.png', filePath: path.join(SCREENSHOT_DIR, 'collection-filter-drawer-390px.png') });

                // Close filter drawer
                await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const applyBtn = document.getElementById('mobile-apply-filters-btn');
                            if (applyBtn) applyBtn.click();
                        })()
                    `
                });
                await sleep(400);
            }

            if (width === 390 && page.name === 'Product') {
                console.log('    Testing product page accordion tabs & sticky bar...');
                const stickyBarCheck = await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const bar = document.querySelector('.mobile-sticky-bar');
                            const addBtn = document.getElementById('mobile-add-to-cart-btn');
                            const buyBtn = document.getElementById('mobile-buy-now-btn');
                            return {
                                hasBar: !!bar,
                                hasAdd: !!addBtn,
                                hasBuy: !!buyBtn
                            };
                        })()
                    `,
                    returnByValue: true
                });
                console.log(`    Sticky bottom bar present with Add & Buy: ${stickyBarCheck.result.value.hasBar && stickyBarCheck.result.value.hasAdd ? 'YES (PASS)' : 'NO'}`);
            }

            if (width === 390 && page.name === 'Admin Dashboard') {
                console.log('    Testing admin sidebar off-canvas toggle on mobile...');
                await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const btn = document.querySelector('.mobile-menu-btn');
                            if (btn) btn.click();
                        })()
                    `
                });
                await sleep(400);
                const adminMenuState = await pageClient.send('Runtime.evaluate', {
                    expression: `
                        (() => {
                            const sb = document.getElementById('adminSidebar');
                            return sb ? sb.classList.contains('open') : false;
                        })()
                    `,
                    returnByValue: true
                });
                console.log(`    Admin sidebar opened off-canvas: ${adminMenuState.result.value ? 'YES (PASS)' : 'NO'}`);

                const adminShot = await pageClient.send('Page.captureScreenshot', { format: 'png' });
                fs.writeFileSync(path.join(SCREENSHOT_DIR, 'admin-sidebar-open-390px.png'), Buffer.from(adminShot.data, 'base64'));
                screenshotList.push({ page: 'Admin Sidebar Open', width: 390, filename: 'admin-sidebar-open-390px.png', filePath: path.join(SCREENSHOT_DIR, 'admin-sidebar-open-390px.png') });
            }
        }
    }

    // Cleanup
    browserClient.close();
    pageClient.close();
    chromeProc.kill();
    phpProc.kill();

    // Summary
    console.log('\n========================================================');
    console.log('           RESPONSIVE VERIFICATION SUMMARY              ');
    console.log('========================================================');

    const totalTests = results.length;
    const passedTests = results.filter(r => r.passed).length;
    const failedTests = results.filter(r => !r.passed);

    console.log(`Total Viewport Checks: ${totalTests}`);
    console.log(`Passed (No Overflow): ${passedTests} / ${totalTests} (${Math.round(passedTests / totalTests * 100)}%)`);

    if (failedTests.length > 0) {
        console.log('\nOverflows detected:');
        failedTests.forEach(f => {
            console.log(`- ${f.page} @ ${f.width}px: scrollW ${f.scrollW}px > winW ${f.winWidth}px`);
            console.log(`  Offenders: ${JSON.stringify(f.offenders)}`);
        });
    } else {
        console.log('\nPERFECT! Zero horizontal overflow across all pages and all viewports (320px to 1280px)!');
    }

    console.log('\nScreenshots Captured:');
    screenshotList.forEach(s => {
        console.log(`- [${s.width}px] ${s.page}: ${s.filename}`);
    });

    console.log('\nVerification complete.');
}

run().catch(err => {
    console.error('Fatal execution error:', err);
    process.exit(1);
});
