/**
 * Record the promo reel HTML as an MP4 video.
 *
 * Usage:
 *   cd promo/
 *   node record.js
 *
 * Output: promo/argonar-reel.mp4 (1080x1920, 30fps, ~18 seconds)
 *
 * Requires: npm install puppeteer puppeteer-screen-recorder
 */

const puppeteer = require('puppeteer');
const { PuppeteerScreenRecorder } = require('puppeteer-screen-recorder');
const path = require('path');

const REEL_URL = `file://${path.resolve(__dirname, 'reel.html')}`;
const OUTPUT   = path.resolve(__dirname, 'argonar-reel.mp4');
const DURATION = 18000; // 18 seconds

(async () => {
    console.log('Launching browser...');
    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--window-size=1080,1920',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1080, height: 1920, deviceScaleFactor: 1 });

    console.log('Loading reel...');
    await page.goto(REEL_URL, { waitUntil: 'networkidle0', timeout: 30000 });

    // Wait a moment for fonts to load
    await new Promise(r => setTimeout(r, 1000));

    const recorder = new PuppeteerScreenRecorder(page, {
        followNewTab: false,
        fps: 30,
        ffmpeg_Path: null, // auto-detect
        videoFrame: {
            width: 1080,
            height: 1920,
        },
        videoCrf: 23,
        videoCodec: 'libx264',
        videoPreset: 'medium',
        videoBitrate: 4000,
        aspectRatio: '9:16',
    });

    console.log(`Recording ${DURATION / 1000}s to ${OUTPUT} ...`);
    await recorder.start(OUTPUT);

    // Let the animation play
    await new Promise(r => setTimeout(r, DURATION));

    await recorder.stop();
    console.log('Recording complete!');
    console.log(`Output: ${OUTPUT}`);

    await browser.close();
})();
