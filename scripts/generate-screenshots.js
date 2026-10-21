const puppeteer = require('puppeteer-core');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const WP_LOAD = '/Applications/XAMPP/xamppfiles/htdocs/holvek-uat/wp-load.php';
const PHP_BIN = '/Applications/XAMPP/xamppfiles/bin/php';
const BASE_URL = 'http://localhost/holvek-uat/wp-admin';

const SCREENSHOT_TARGETS = [
    {
        num: 1,
        title: 'Quinrel Dashboard Overview',
        url: `${BASE_URL}/admin.php?page=quinrel#dashboard`,
        async prepare(page) {
            await page.waitForSelector('#quinrel-admin-app .quinrel-wrapper', { timeout: 15000 });
            await new Promise(r => setTimeout(r, 2000));
        }
    },
    {
        num: 2,
        title: 'Quinrel Panel in Block Editor',
        url: `${BASE_URL}/post.php?post=1&action=edit`,
        async prepare(page) {
            await page.waitForSelector('.edit-post-header', { timeout: 20000 });
            // Close Gutenberg welcome guide modal if open
            await page.evaluate(() => {
                const closeBtn = document.querySelector('.components-modal__header button[aria-label="Close"]');
                if (closeBtn) closeBtn.click();
            });
            await new Promise(r => setTimeout(r, 1000));

            // Open Quinrel sidebar
            await page.evaluate(() => {
                const id = 'quinrel-editor-sidebar/quinrel-sidebar';
                if (window.wp && window.wp.data) {
                    if (window.wp.data.dispatch('core/interface')) {
                        window.wp.data.dispatch('core/interface').enableComplementaryArea('core/edit-post', id);
                    } else if (window.wp.data.dispatch('core/edit-post')) {
                        window.wp.data.dispatch('core/edit-post').openGeneralSidebar(id);
                    }
                }
            });
            await page.waitForSelector('.quinrel-editor-wrapper', { timeout: 10000 });
            await new Promise(r => setTimeout(r, 2000));
        }
    },
    {
        num: 3,
        title: 'Redirects Manager (Technical SEO)',
        url: `${BASE_URL}/admin.php?page=quinrel#technical`,
        async prepare(page) {
            await page.waitForSelector('#quinrel-admin-app .quinrel-wrapper', { timeout: 15000 });
            // Click "Redirects Manager" subtab button
            await page.evaluate(() => {
                const buttons = Array.from(document.querySelectorAll('button'));
                const btn = buttons.find(b => b.innerText && b.innerText.includes('Redirects Manager'));
                if (btn) btn.click();
            });
            await new Promise(r => setTimeout(r, 2000));
        }
    },
    {
        num: 4,
        title: 'Technical SEO Audit',
        url: `${BASE_URL}/admin.php?page=quinrel#site_audit`,
        async prepare(page) {
            await page.waitForSelector('#quinrel-admin-app .quinrel-wrapper', { timeout: 15000 });
            await new Promise(r => setTimeout(r, 2000));
        }
    },
    {
        num: 5,
        title: 'Image SEO',
        url: `${BASE_URL}/admin.php?page=quinrel#image_seo`,
        async prepare(page) {
            await page.waitForSelector('#quinrel-admin-app .quinrel-wrapper', { timeout: 15000 });
            await new Promise(r => setTimeout(r, 2000));
        }
    },
    {
        num: 6,
        title: 'Schema Markup Templates',
        url: `${BASE_URL}/admin.php?page=quinrel#schema`,
        async prepare(page) {
            await page.waitForSelector('#quinrel-admin-app .quinrel-wrapper', { timeout: 15000 });
            await new Promise(r => setTimeout(r, 2000));
        }
    },
    {
        num: 7,
        title: 'Keyword Rank Tracker',
        url: `${BASE_URL}/admin.php?page=quinrel#rank_tracker`,
        async prepare(page) {
            await page.waitForSelector('#quinrel-admin-app .quinrel-wrapper', { timeout: 15000 });
            await new Promise(r => setTimeout(r, 2000));
        }
    },
    {
        num: 8,
        title: 'XML Sitemap Settings',
        url: `${BASE_URL}/admin.php?page=quinrel#sitemaps`,
        async prepare(page) {
            await page.waitForSelector('#quinrel-admin-app .quinrel-wrapper', { timeout: 15000 });
            await new Promise(r => setTimeout(r, 2000));
        }
    }
];

async function main() {
    console.log('=== Quinrel Screenshot Generator ===');

    // 1. Fetch Auth Cookies from WordPress
    console.log('[1/4] Fetching WordPress auth cookies...');
    const phpScript = `<?php
require '${WP_LOAD}';
$user_id = 1;
$expiration = time() + 86400;
$manager = WP_Session_Tokens::get_instance($user_id);
$token = $manager->create($expiration);
$logged_in = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);
$auth = wp_generate_auth_cookie($user_id, $expiration, 'auth', $token);
echo json_encode([
    ['name' => LOGGED_IN_COOKIE, 'value' => $logged_in],
    ['name' => AUTH_COOKIE, 'value' => $auth]
]);
`;
    const rawCookies = execSync(PHP_BIN, { input: phpScript }).toString().trim();
    const cookies = JSON.parse(rawCookies).map(c => ({
        name: c.name,
        value: c.value,
        domain: 'localhost',
        path: '/',
        httpOnly: true,
        secure: false
    }));

    // 2. Launch Puppeteer
    console.log('[2/4] Launching Headless Chrome...');
    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        defaultViewport: { width: 1280, height: 800 },
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1280,800'
        ]
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 800 });
    await page.setCookie(...cookies);

    // 3. Process Each Target
    console.log('[3/4] Capturing screenshots 1 through 8...');
    const outDirs = [
        path.join(__dirname, '../assets/screenshots'),
        path.join(__dirname, '../assets/wporg')
    ];

    for (const dir of outDirs) {
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    }

    for (const target of SCREENSHOT_TARGETS) {
        const filename = `screenshot-${target.num}.png`;
        console.log(`\n  📸 Capturing #${target.num}: ${target.title}`);
        console.log(`     Navigating to: ${target.url}`);

        try {
            await page.goto(target.url, { waitUntil: 'domcontentloaded', timeout: 35000 });
            if (target.prepare) {
                await target.prepare(page);
            }

            // Capture screenshot buffer (1280x800)
            const buffer = await page.screenshot({
                type: 'png',
                clip: { x: 0, y: 0, width: 1280, height: 800 }
            });

            // Write to both assets/screenshots and assets/wporg
            for (const dir of outDirs) {
                const targetPath = path.join(dir, filename);
                fs.writeFileSync(targetPath, buffer);
                const stats = fs.statSync(targetPath);
                console.log(`     Saved ${targetPath} (${Math.round(stats.size / 1024)} KB)`);
            }
        } catch (err) {
            console.error(`     ❌ Failed capturing screenshot-${target.num}:`, err.message);
        }
    }

    await browser.close();
    console.log('\n[4/4] All screenshots captured successfully.');
}

main().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
