<?php
/**
 * Editorial chrome — clean nav + page wrapper opener.
 * Pair with `includes/he-foot.php` at the bottom of the page.
 * Hides the legacy broadcast chrome via .home-editorial CSS overrides
 * already shipped in css/app.css.
 */
?>
<div class="home-editorial">
<style>
.home-editorial ~ * .navbar, body > .navbar:not(.he-nav),
body > #briefing, body > #livechat, body > .footer-wrap,
body > footer:not(.he-footer), body > .left-sidebar-rail,
body > .idx-layout, body > .sponsors-bar, body > .ticker-wrap,
body > .winner-banner, body > .season-banner, body > .season-banner-chips,
body > .games-grid, body > .registered-section, body > .orgs-section,
body > .terms-landing, body > .prize-pick, body > .hero,
.navbar:not(.he-nav), .sponsors-bar, .season-banner,
#guest-join-banner, .live-banner, .live-chat, .cta-stack,
.idx-layout, .left-sidebar-rail, .reg-container > .back-link + .reg-card { display: none !important; }

/* Container reset for editorial */
.home-editorial .reg-container, .home-editorial .ticket-container,
.home-editorial .he-wrap { display: block; }
</style>
<header class="he-nav">
    <div class="he-nav-inner">
        <a href="<?= base_url() ?>" class="he-brand">
            <div class="he-brand-mark">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/>
                </svg>
            </div>
            <div>
                <div class="he-brand-name">Apex Cybernet</div>
                <div class="he-brand-tag">Tournament · S2</div>
            </div>
        </a>
        <nav class="he-nav-links">
            <a href="<?= base_url('bracket.php?game=dota2') ?>" class="he-nav-link">Bracket</a>
            <a href="<?= base_url('rules.php') ?>" class="he-nav-link">Rules</a>
            <a href="<?= base_url('contact.php') ?>" class="he-nav-link">Contact</a>
            <a href="<?= base_url('register.php?game=dota2') ?>" class="he-cta-mini">Register →</a>
        </nav>
    </div>
</header>
<main class="he-main">
