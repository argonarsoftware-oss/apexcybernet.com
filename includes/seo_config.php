<?php
/**
 * SEO / Analytics configuration
 *
 * Set these constants with your real IDs to enable the integrations.
 * Leaving any value empty disables that integration gracefully.
 *
 * Scope: all public pages. Admin and API routes are excluded
 * automatically in includes/header.php.
 */

// Google Analytics 4 — e.g. 'G-XXXXXXXXXX'
if (!defined('GA_MEASUREMENT_ID')) define('GA_MEASUREMENT_ID', '');

// Google Search Console verification code (value from <meta name="google-site-verification">)
if (!defined('GSC_VERIFICATION')) define('GSC_VERIFICATION', '');

// Optional: Meta (Facebook) Pixel ID
if (!defined('META_PIXEL_ID')) define('META_PIXEL_ID', '');

// Resend (transactional email) — used for password reset flow.
// Get your key at https://resend.com/api-keys (free tier: 3000/mo).
// From address must be on a domain you've verified in Resend.
if (!defined('RESEND_API_KEY'))  define('RESEND_API_KEY',  're_RDWAjP9o_2QAJedqqA6UDweDF83GmAhYc');
if (!defined('MAIL_FROM_ADDR'))  define('MAIL_FROM_ADDR',  'no-reply@apexcybernet.com');
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME',  'Apex Cybernet');

/**
 * Returns true if the current request is an admin or api endpoint that
 * should NOT have tracking scripts injected. Called from header.php.
 */
function seo_is_tracked_page(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (stripos($uri, '/admin/') !== false) return false;
    if (stripos($uri, '/api/')   !== false) return false;
    if (stripos($uri, '/reels/') !== false) return false;
    return true;
}
