/**
 * Record the waitlist urgency reel as an MP4 video with background music.
 *
 * Usage:
 *   cd promo/
 *   node record_waitlist.js
 *
 * Output: promo/apexcybernet-waitlist-reel.mp4 (1080x1920, 30fps, ~52 seconds, with music)
 *
 * Requires: npm install  (puppeteer + puppeteer-screen-recorder already in package.json)
 *           ffmpeg available in PATH
 */

const puppeteer = require('puppeteer');
const { PuppeteerScreenRecorder } = require('puppeteer-screen-recorder');
const { execSync } = require('child_process');
const path = require('path');
const fs   = require('fs');

const REEL_URL    = `file://${path.resolve(__dirname, 'reel_waitlist.html')}`;
const VIDEO_RAW   = path.resolve(__dirname, 'apexcybernet-waitlist-reel-noaudio.mp4');
const MUSIC_FILE  = 'C:/Users/kirfenia/Downloads/TheFatRat - Warrior Song (DOTA 2 Music Pack).mp3';
const OUTPUT      = path.resolve(__dirname, 'apexcybernet-waitlist-reel.mp4');
const DURATION    = 52000; // 52 seconds

(async () => {
    console.log('Launching browser…');
    const browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--window-size=1080,1920',
            '--font-render-hinting=none',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1080, height: 1920, deviceScaleFactor: 1 });

    console.log('Loading reel…');
    await page.goto(REEL_URL, { waitUntil: 'networkidle0', timeout: 30000 });

    // Give Google Fonts a moment to load
    await new Promise(r => setTimeout(r, 1500));

    const recorder = new PuppeteerScreenRecorder(page, {
        followNewTab: false,
        fps: 30,
        ffmpeg_Path: null,
        videoFrame: { width: 1080, height: 1920 },
        videoCrf: 20,
        videoCodec: 'libx264',
        videoPreset: 'medium',
        videoBitrate: 5000,
        aspectRatio: '9:16',
    });

    console.log(`Recording ${DURATION / 1000}s → ${VIDEO_RAW} …`);
    await recorder.start(VIDEO_RAW);
    await new Promise(r => setTimeout(r, DURATION));
    await recorder.stop();
    await browser.close();
    console.log('Video recorded.');

    // Merge audio with ffmpeg — trim music to exact video length, fade out last 2s
    console.log('Merging music…');
    if (fs.existsSync(OUTPUT)) fs.unlinkSync(OUTPUT);
    const cmd = [
        'ffmpeg',
        `-i "${VIDEO_RAW}"`,
        `-i "${MUSIC_FILE}"`,
        `-map 0:v:0`,
        `-map 1:a:0`,
        `-c:v copy`,
        `-c:a aac -b:a 192k`,
        `-af "afade=t=out:st=${DURATION / 1000 - 2}:d=2"`,
        `-t ${DURATION / 1000}`,
        `-shortest`,
        `-y`,
        `"${OUTPUT}"`,
    ].join(' ');
    execSync(cmd, { stdio: 'inherit' });

    // Clean up the no-audio intermediate file
    fs.unlinkSync(VIDEO_RAW);

    console.log('Done!');
    console.log(`Output: ${OUTPUT}`);
})();
