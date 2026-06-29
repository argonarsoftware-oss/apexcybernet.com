<?php
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Contact & Support — Apex Cybernet Dota 2 Tournament';
$pageDescription = 'Get in touch with the Apex Cybernet Dota 2 Tournament organizers. Venue: Apex Cybernet Cafe, 7V94+HCQ, F. Jaca St, Cebu City. ₱550/team · ₱110/solo entry. FAQs about registration and tournament day.';
$canonicalUrl = canonical_url('contact.php');

$faqs = [
    [
        'q' => 'How much is the entry fee?',
        'a' => '₱550 per team or ₱110 per solo player. Pay via QR Ph (InstaPay) on the payment page right after you register — your slot is locked once payment is confirmed.',
    ],
    [
        'q' => 'How do I register for the tournament?',
        'a' => 'Go to the home page, pick your game, and click "Register Team" (if you have a full team) or "Solo Entry" (if you want to be matched with other players). Fill in the form and you\'ll get a reference code.',
    ],
    [
        'q' => 'When is the tournament?',
        'a' => 'The Apex Cybernet Dota 2 Tournament is on July 11, 2026 at Apex Cybernet Cafe, 7V94+HCQ, F. Jaca St, Cebu City, 6000 Cebu. Tournament starts at 11:00 AM. Call time is 10:00 AM.',
    ],
    [
        'q' => 'Where is the venue exactly?',
        'a' => 'Apex Cybernet Cafe — 7V94+HCQ, F. Jaca St, Cebu City, 6000 Cebu. Open the venue in Google Maps from the Contact page.',
    ],
    [
        'q' => 'What if I can\'t attend on tournament day?',
        'a' => 'Let us know as soon as possible via the Apex Cybernet Facebook page. If you notify us in advance, your registration will be reserved and reassigned to the next season or upcoming tournament. If you don\'t show up without notice, your team forfeits the match.',
    ],
    [
        'q' => 'Can I register as a solo player?',
        'a' => 'Yes. Click "Solo Entry" on the game you want to play. You\'ll be matched with other solo players based on your rank and preferred role. Once 5 solo players are matched, you\'ll form a team automatically.',
    ],
    [
        'q' => 'Do I need to pay for PC time at the venue?',
        'a' => 'Yes. PC time at Apex Cybernet Cafe is paid directly to the venue and is separate from the tournament entry fee.',
    ],
    [
        'q' => 'Is the bracket final before the tournament?',
        'a' => 'No — until the registration cut-off, the published bracket is a live preview based on currently registered teams. As more teams register, the bracket will be re-seeded to stay balanced. After the cut-off the bracket is finalized and seedings are locked.',
    ],
];

$faqJsonLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(function($f) {
        return [
            '@type' => 'Question',
            'name'  => $f['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $f['a'],
            ],
        ];
    }, $faqs),
];

$extraHead  = breadcrumb_jsonld([
    ['name' => 'Home',    'url' => 'https://apexcybernet.com/'],
    ['name' => 'Contact', 'url' => 'https://apexcybernet.com/contact.php'],
]);
$extraHead .= '<script type="application/ld+json">' . json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/he-chrome.php';
?>

<section class="he-page-hero">
    <div class="he-page-eyebrow">Contact &amp; support</div>
    <h1 class="he-page-title">Need help? Reach out.</h1>
    <p class="he-page-sub">Fastest path is Messenger — we usually reply within an hour during the day. Common questions are answered below.</p>
</section>

<div class="he-card" style="max-width:780px;">
    <div class="he-card-inner">
        <div class="he-card-section">
            <div class="he-card-section-label">Tournament day</div>
            <div style="display:flex; align-items:center; gap:16px; padding:20px 22px; background:linear-gradient(90deg, rgba(226,54,54,0.10), rgba(251,191,36,0.10)); border:1px solid var(--accent); border-radius:14px;">
                <div style="width:52px; height:52px; background:#fff; border:1px solid var(--border); border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--accent); font-size:26px;">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:11px; text-transform:uppercase; letter-spacing:0.1em; font-weight:800; color:var(--accent); margin-bottom:2px;">Mark your calendar</div>
                    <div style="font-size:28px; font-weight:800; color:var(--text); letter-spacing:-0.02em; line-height:1.1;">July 11, 2026</div>
                    <div style="font-size:13px; color:var(--text-muted); margin-top:4px;">Saturday · 11:00&nbsp;AM start · 10:00&nbsp;AM call time · Apex Cybernet Cafe</div>
                </div>
            </div>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">Channels</div>
            <a href="https://www.facebook.com/people/APEX-cybernet-cafe/61590841850979/" target="_blank" rel="noopener"
               style="display:flex; align-items:center; gap:14px; padding:16px 18px; background:var(--bg-subtle); border:1px solid var(--border); border-radius:12px; text-decoration:none; transition:border-color 0.15s; margin-bottom:10px;">
                <div style="width:40px; height:40px; background:#fff; border:1px solid var(--border); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#1877f2; font-size:20px;">
                    <i class="bi bi-facebook"></i>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:600; color:var(--text); font-size:14.5px;">Apex Cybernet</div>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-top:1px;">Message us on Facebook · fastest reply</div>
                </div>
                <i class="bi bi-arrow-up-right" style="color:var(--text-muted);"></i>
            </a>
            <a href="https://www.google.com/maps/search/?api=1&amp;query=7V94%2BHCQ+F.+Jaca+St+Cebu+City" target="_blank" rel="noopener"
               style="display:flex; align-items:center; gap:14px; padding:16px 18px; background:var(--bg-subtle); border:1px solid var(--border); border-radius:12px; text-decoration:none; transition:border-color 0.15s;">
                <div style="width:40px; height:40px; background:#fff; border:1px solid var(--border); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--danger); font-size:20px;">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:600; color:var(--text); font-size:14.5px;">Apex Cybernet Cafe</div>
                    <div style="font-size:12.5px; color:var(--text-muted); margin-top:1px;">7V94+HCQ, F. Jaca St, Cebu City, 6000 Cebu · opens in Maps</div>
                </div>
                <i class="bi bi-arrow-up-right" style="color:var(--text-muted);"></i>
            </a>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">Entry</div>
            <div style="font-size:13.5px; color:var(--text-body); line-height:1.6;">
                <strong>₱550/team · ₱110/solo.</strong> Paid via QR Ph (InstaPay) on the payment page after registration. PC time at Apex Cybernet Cafe is paid directly to the venue and is separate.
            </div>
        </div>

        <div class="he-card-section">
            <div class="he-card-section-label">FAQ</div>
            <?php foreach ($faqs as $f): ?>
                <details style="border-top:1px solid var(--border); padding:14px 0;">
                    <summary style="cursor:pointer; list-style:none; font-weight:600; font-size:14px; color:var(--text); display:flex; justify-content:space-between; align-items:center; gap:10px;">
                        <span><?= htmlspecialchars($f['q']) ?></span>
                        <i class="bi bi-chevron-down" style="font-size:13px; color:var(--text-muted); transition:transform 0.2s;"></i>
                    </summary>
                    <div style="padding-top:10px; color:var(--text-body); font-size:13.5px; line-height:1.65;"><?= $f['a'] ?></div>
                </details>
            <?php endforeach; ?>
            <style>
                details[open] summary i.bi-chevron-down { transform: rotate(180deg); }
                summary::-webkit-details-marker { display: none; }
            </style>
        </div>

        <div style="text-align:center; padding-top:8px;">
            <a href="https://www.facebook.com/people/APEX-cybernet-cafe/61590841850979/" target="_blank" rel="noopener" class="he-btn-primary">
                <i class="bi bi-messenger"></i> Message us on Messenger
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/he-foot.php'; return; ?>
