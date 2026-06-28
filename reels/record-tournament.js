/**
 * Record reel-tournament.html using real production data.
 *
 * Usage:
 *   node record-tournament.js
 *
 * Output: reels/output/reel-tournament-{timestamp}.mp4
 *
 * Requirements:
 *   npm install  (puppeteer already in package.json)
 *   ffmpeg must be on PATH
 */

const puppeteer = require('puppeteer');
const path  = require('path');
const fs    = require('fs');
const https = require('https');

const WIDTH    = 720;
const HEIGHT   = 1280;
const FPS      = 30;
const DURATION = 55; // seconds — must match TOTAL_MS in the HTML
const FRAMES   = FPS * DURATION;
const MS_FRAME = 1000 / FPS;

const DATA_URL = 'https://argonar.co/admin/api/reel-data.php?k=argonar2026';
const HTML_FILE = path.join(__dirname, 'reel-tournament.html');

const outDir = path.join(__dirname, 'output');
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

const stamp     = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
const framesDir = path.join(outDir, `frames-${stamp}`);
fs.mkdirSync(framesDir, { recursive: true });
const outputMp4 = path.join(outDir, `reel-tournament-${stamp}.mp4`);

function fetchJson(url) {
    return new Promise((resolve, reject) => {
        https.get(url, res => {
            let raw = '';
            res.on('data', c => raw += c);
            res.on('end', () => { try { resolve(JSON.parse(raw)); } catch(e) { reject(e); } });
        }).on('error', reject);
    });
}

(async () => {
    console.log('╔══════════════════════════════════════╗');
    console.log('║   Argonar Tournament Reel Recorder   ║');
    console.log('╚══════════════════════════════════════╝');

    // 1. Fetch live data
    console.log('\n[1/4] Fetching live tournament data...');
    let liveData = null;
    try {
        liveData = await fetchJson(DATA_URL);
        console.log(`      ✓ ${liveData.totals.teams} teams, ${liveData.totals.participants} players, ₱${liveData.prize_pool.toLocaleString()} prize pool`);
    } catch (e) {
        console.warn(`      ⚠ Could not fetch live data (${e.message}). Using HTML defaults.`);
    }

    // 2. Launch browser
    console.log('\n[2/4] Launching browser...');
    const browser = await puppeteer.launch({
        headless: 'new',
        args: [
            `--window-size=${WIDTH},${HEIGHT}`,
            '--no-sandbox', '--disable-setuid-sandbox',
            '--font-render-hinting=none',
            '--disable-web-security',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: WIDTH, height: HEIGHT, deviceScaleFactor: 1 });
    const cdp = await page.createCDPSession();

    await page.goto(`file:///${HTML_FILE.replace(/\\/g, '/')}`, { waitUntil: 'networkidle0', timeout: 30000 });

    // Pause virtual time
    await cdp.send('Emulation.setVirtualTimePolicy', { policy: 'pause' });

    // Inject live data if available
    if (liveData) {
        await page.evaluate((d) => window.loadReelData(d), liveData);
        console.log('      ✓ Live data injected into reel');
    }

    // Let it settle
    await cdp.send('Emulation.setVirtualTimePolicy', { policy: 'pauseIfNetworkFetchesPending', budget: 200 });
    await new Promise(r => setTimeout(r, 80));

    // 3. Capture frames
    console.log(`\n[3/4] Capturing ${FRAMES} frames @ ${FPS}fps (${DURATION}s)...`);
    const t0 = Date.now();

    for (let f = 0; f < FRAMES; f++) {
        await cdp.send('Emulation.setVirtualTimePolicy', {
            policy: 'pauseIfNetworkFetchesPending',
            budget: MS_FRAME,
        });
        await new Promise(r => setTimeout(r, 8));
        await page.screenshot({ path: path.join(framesDir, `f-${String(f).padStart(5,'0')}.png`), type: 'png' });

        if (f % FPS === 0 || f === FRAMES - 1) {
            const sec   = (f / FPS).toFixed(0);
            const pct   = Math.round((f / FRAMES) * 100);
            const bar   = '█'.repeat(Math.floor(pct / 4)) + '░'.repeat(25 - Math.floor(pct / 4));
            process.stdout.write(`\r      [${bar}] ${pct}% — ${sec}s / ${DURATION}s`);
        }
    }

    const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
    console.log(`\n      ✓ ${FRAMES} frames captured in ${elapsed}s`);
    await browser.close();

    // 4. Encode with ffmpeg
    console.log('\n[4/4] Encoding MP4 with ffmpeg...');
    const { execSync } = require('child_process');
    const cmd = [
        'ffmpeg -y',
        `-framerate ${FPS}`,
        `-i "${path.join(framesDir, 'f-%05d.png')}"`,
        '-c:v libx264 -pix_fmt yuv420p',
        '-preset slow -crf 16',
        `-vf "scale=${WIDTH}:${HEIGHT}"`,
        `"${outputMp4}"`,
    ].join(' ');

    try {
        execSync(cmd, { stdio: 'inherit' });
        fs.rmSync(framesDir, { recursive: true, force: true });
        console.log('\n╔══════════════════════════════════════╗');
        console.log('║           REEL COMPLETE ✓            ║');
        console.log('╠══════════════════════════════════════╣');
        console.log(`║  ${outputMp4.slice(-38).padEnd(38)} ║`);
        console.log('╚══════════════════════════════════════╝');
    } catch (e) {
        console.error('\nffmpeg failed:', e.message);
        console.log('Frames saved at:', framesDir);
        console.log('Run manually:\n ', cmd);
    }
})();
