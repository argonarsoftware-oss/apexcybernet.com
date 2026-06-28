/**
 * Puppeteer reel recorder — real-time JPEG capture piped directly to ffmpeg.
 * No frame files written to disk; encoding happens concurrently.
 *
 * Usage:
 *   node record.js [--data-url <url>] [--html <filename>]
 *
 * Output: reels/output/reel-{timestamp}.mp4
 */

const puppeteer = require('puppeteer');
const path  = require('path');
const fs    = require('fs');
const https = require('https');
const http  = require('http');
const { spawn } = require('child_process');

const WIDTH    = 720;
const HEIGHT   = 1280;
const FPS      = 30;
const MS_FRAME = 1000 / FPS;

// Parse flags
const args    = process.argv.slice(2);
const getArg  = (flag) => { const i = args.indexOf(flag); return i !== -1 ? args[i + 1] : null; };

const DATA_URL  = getArg('--data-url') || 'https://argonar.co/reels/data.php';
const HTML_FILE = getArg('--html')     || 'reel-urgency.html';
const AUDIO     = getArg('--audio')    || 'C:\\Users\\kirfenia\\Downloads\\TheFatRat & Anjulie - Close To The Sun (1).mp3';

const outDir = path.join(__dirname, 'output');
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

const stamp     = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
const outputMp4 = path.join(outDir, `reel-${stamp}.mp4`);

function fetchJson(url) {
    return new Promise((resolve, reject) => {
        const lib = url.startsWith('https') ? https : http;
        lib.get(url, res => {
            let raw = '';
            res.on('data', c => raw += c);
            res.on('end', () => {
                try { resolve(JSON.parse(raw)); }
                catch (e) { reject(new Error('JSON parse failed: ' + raw.slice(0, 100))); }
            });
        }).on('error', reject);
    });
}

(async () => {
    console.log('╔══════════════════════════════════════╗');
    console.log('║      Argonar Reel Recorder v3        ║');
    console.log('╚══════════════════════════════════════╝');
    console.log(`   HTML  : ${HTML_FILE}`);
    console.log(`   Data  : ${DATA_URL}\n`);

    // 1. Fetch live data
    console.log('[1/4] Fetching live data...');
    let liveData = null;
    try {
        liveData = await fetchJson(DATA_URL);
        const c = liveData.confirmed?.length ?? 0;
        const p = liveData.pending?.length ?? 0;
        console.log(`      ✓ ${c} confirmed, ${p} pending`);
    } catch (e) {
        console.warn(`      ⚠ ${e.message} — using HTML fallback data`);
    }

    // 2. Launch browser
    console.log('\n[2/4] Launching browser...');
    const browser = await puppeteer.launch({
        headless: 'new',
        protocolTimeout: 60000,
        args: [
            `--window-size=${WIDTH},${HEIGHT}`,
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--font-render-hinting=none',
            '--disable-web-security',
            '--allow-file-access-from-files',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: WIDTH, height: HEIGHT, deviceScaleFactor: 1 });
    page.on('requestfailed', () => {});

    // Stub fetch(data.php) before page scripts run
    if (liveData) {
        await page.evaluateOnNewDocument((data) => {
            const _orig = window.fetch;
            window.fetch = function(url, ...rest) {
                if (typeof url === 'string' && url.includes('data.php')) {
                    return Promise.resolve({ ok: true, json: () => Promise.resolve(data) });
                }
                return _orig ? _orig.call(window, url, ...rest) : Promise.reject(new Error('no fetch'));
            };
        }, liveData);
    }

    const htmlPath = path.resolve(__dirname, HTML_FILE);
    await page.goto(`file:///${htmlPath.replace(/\\/g, '/')}`, {
        waitUntil: 'domcontentloaded',
        timeout: 30000,
    });

    // Give the reel time to initialize
    await new Promise(r => setTimeout(r, 600));

    // Read total reel duration from the page's sceneSequence
    const totalMs = await page.evaluate(() => {
        if (typeof sceneSequence !== 'undefined' && sceneSequence.length)
            return sceneSequence.reduce((a, s) => a + s.duration, 0) + 1000;
        return 55000;
    }).catch(() => 55000);

    const TOTAL_FRAMES = Math.ceil((totalMs / 1000) * FPS);
    console.log(`      ✓ Reel duration: ${(totalMs/1000).toFixed(1)}s → ${TOTAL_FRAMES} frames`);

    // 3. Spawn ffmpeg reading JPEG frames from stdin (no temp files)
    const hasAudio = AUDIO && fs.existsSync(AUDIO);
    console.log(`\n[3/4] Capturing ${TOTAL_FRAMES} frames @ ${FPS}fps → piping to ffmpeg...`);
    if (hasAudio) console.log(`      ♪  Audio: ${path.basename(AUDIO)}`);
    else          console.log(`      ⚠  No audio (file not found): ${AUDIO}`);

    const ffmpegArgs = [
        '-y',
        // Video input: JPEG frames from stdin
        '-framerate', String(FPS),
        '-f', 'image2pipe',
        '-vcodec', 'mjpeg',
        '-i', 'pipe:0',
    ];

    if (hasAudio) {
        ffmpegArgs.push('-i', AUDIO);
    }

    ffmpegArgs.push(
        '-c:v', 'libx264',
        '-pix_fmt', 'yuv420p',
        '-preset', 'fast',
        '-crf', '18',
        '-vf', `scale=${WIDTH}:${HEIGHT}`,
    );

    if (hasAudio) {
        ffmpegArgs.push(
            '-c:a', 'aac',
            '-b:a', '192k',
            '-map', '0:v',
            '-map', '1:a',
            '-shortest',   // trim to video length
        );
    }

    ffmpegArgs.push(outputMp4);

    const ffmpeg = spawn('ffmpeg', ffmpegArgs, { stdio: ['pipe', 'ignore', 'ignore'] });

    ffmpeg.on('error', e => { console.error('\nffmpeg spawn error:', e.message); });

    const t0 = Date.now();

    for (let f = 0; f < TOTAL_FRAMES; f++) {
        const frameStart = Date.now();

        // JPEG is 3-5x faster to encode than PNG and sufficient for video
        const buf = await page.screenshot({ type: 'jpeg', quality: 92 });
        ffmpeg.stdin.write(buf);

        if (f % FPS === 0 || f === TOTAL_FRAMES - 1) {
            const sec = (f / FPS).toFixed(0);
            const tot = (totalMs / 1000).toFixed(0);
            const pct = Math.round((f / TOTAL_FRAMES) * 100);
            const bar = '█'.repeat(Math.floor(pct / 4)) + '░'.repeat(25 - Math.floor(pct / 4));
            process.stdout.write(`\r      [${bar}] ${pct}% — ${sec}s / ${tot}s`);
        }

        // Pace to ~30fps (real-time so animation matches frame sequence)
        const took = Date.now() - frameStart;
        const wait = Math.max(1, MS_FRAME - took);
        await new Promise(r => setTimeout(r, wait));
    }

    const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
    console.log(`\n      ✓ ${TOTAL_FRAMES} frames in ${elapsed}s`);

    // Close ffmpeg stdin to signal end of stream
    await new Promise((resolve, reject) => {
        ffmpeg.stdin.end();
        ffmpeg.on('close', code => {
            if (code === 0) resolve();
            else reject(new Error(`ffmpeg exited with code ${code}`));
        });
    });

    await browser.close();

    console.log('\n╔══════════════════════════════════════╗');
    console.log('║           REEL COMPLETE ✓            ║');
    console.log('╠══════════════════════════════════════╣');
    console.log(`║  ${path.basename(outputMp4).padEnd(38)} ║`);
    console.log('╚══════════════════════════════════════╝\n');
    console.log('Output:', outputMp4);
})().catch(e => {
    console.error('\nFatal:', e.message);
    process.exit(1);
});
