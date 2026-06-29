# Apex Cybernet

## Site layout — single app

This repo is the **Apex Cybernet** platform: a single PHP site that deploys to apexcybernet.com via `webhook-deploy.php` at the root.

- **`/` (repo root)** → Apex Cybernet (Dota 2 / Valorant / CrossFire esports site + admin). DB: `apexcybernet`.

> Note: this codebase was forked from the Argonar platform. The `fer/` Wealth Engine (private loan-book / collections tracker, DB `payment_calc`) was **intentionally excluded** from this repo and does not exist here. The `/sec` module likewise does not exist in this fork.

> **Removed:** the HCoin virtual-currency subsystem and everything that depended on it — wallet (send/receive/QR pay), top-ups, cash-out / sell orders, the marketplace, merchant/POS, match predictions/betting, and season passes — were fully removed from this repo. The `h_coin_*`, `marketplace_*`, `match_predictions`, `season_passes`, and `qr_tokens_used` tables and the `accounts.h_coins` / `accounts.is_merchant` columns are no longer used by the app. Tournament entry continues to use GCash (listener API), which is unrelated to HCoin.

## Project
PHP esports-tournament platform running at https://apexcybernet.com. Covers:
- **Tournaments / brackets** — `bracket.php`, `matchmaking.php`, `leaderboard.php`, waitlist flow (`fetch_waitlist.php`), registration/tickets (`register.php`, `ticket.php`) paid via GCash (listener API)
- **Cafe live chat** — `cafe_comments` wall (AJAX in `index.php`)
- **Admin console** (`admin/`) — per-business activity pages (alrisha, bizops, loan, ocpd, manual), reconciliation, accounts, omni knowledge-graph (`admin/omni/`), brain notebook (`activity-brain.php`)
- **Reels generator** — Puppeteer + ffmpeg (`reels/`)
- **PWA** — `manifest.webmanifest`, `sw.js`, `offline.html`
- **Realtime** — HTTP polling (Soketi is not deployed). Central JS poller in `includes/footer.php` + `mobile/layout.php` polls `api/notifications.php` every 10s.

Previous construction-tools project is archived in `archive/construction-tools` branch.

## Local Dev
- XAMPP stack at `C:\xampp\htdocs\Apex Cybernet`
- Local URL: `http://localhost/apexcybernet.com/`
- MySQL: root, no password, database `apexcybernet`

## Auto-Deploy
- GitHub repo: `argonarsoftware-oss/apexcybernet.com`
- Commits with `[deploy]` in the message trigger auto-deploy to VPS
- **Production URL**: https://apexcybernet.com
- **Always auto-deploy**: Every commit must include `[deploy]` and be pushed immediately. Do not wait to be asked.

## Coding Style
- PHP: No frameworks, vanilla PHP + PDO
- JS: jQuery, no build tools
- CSS: Custom properties in `:root`, single `app.css`
- Forms: CSRF via `csrf_field()` / `csrf_check()`
- Naming: snake_case for PHP vars/DB columns, camelCase for JS

## Migration Workflow
- Create `migrate_<name>.php` in project root
- Run locally via curl, then on production via curl/WebFetch
- Delete after running — never leave deployed

## Payments
- Tournament entry fees (₱500/team, ₱100/solo) are paid via GCash and confirmed through the listener API (`includes/listener-api.php`, `ticket.php`, `admin/reconciliation.php`). This is unrelated to the removed HCoin subsystem.

## Reels
- Reel recorder: `reels/record.js` — runs Puppeteer + ffmpeg, JPEG pipe to ffmpeg (no temp frames)
- Reel HTML: `reels/reel-urgency.html` — dark cinematic style, animated list scenes
- Data API: `reels/data.php` — returns confirmed/pending/waitlist teams from DB
- Run: `cd reels && node record.js`

## Brain notebook (admin/activity-brain.php)
- Notes stored in `decision_log` table, each has a track id `BR-XXXX` (zero-padded `id`)
- **BR-0008**: always pull the latest context from the apexcybernet.com production DB before reasoning about it — local DB will be stale. Fetch via:
  `https://apexcybernet.com/admin/api/decision-log.php?k=apexcybernet2026`
  then find the row where `id = 8`. Use that row's `title` + `context_text` as the authoritative context.
- Same rule applies to any other BR-XXXX the user references in conversation: production is the source of truth, local is a dev mirror.

## Operating principle — Paper only real assets
- Paper = contracts, signed agreements, legal documents.
- **Paper** only what is already transactional: equity, title, IP, stock, physical property — assets where dispute resolution requires the document.
- **Never paper** favors, hosting rights, free office/rent/lease, reciprocity debts, handshake deals. Paper converts a debt into a transaction; debts grow and never close, transactions have fixed terms and expire. Paper signals distrust and breaks the warmth that made the favor possible.
- When reasoning about risk on deals in this project (e.g. BR-0008 cafe hosting, Vinrio free office), do NOT suggest "get it in writing" as the default move — that kills the leverage. The ongoing social debt is the real leverage.

## Operating principle — Harvest social assets into real assets on Apex Cybernet
- Sequence, not choice: social-first to acquire positions cheaply (no one pays market for them), then convert to real once the positions are too deep to be taken back. Rockefeller-Getty in order.
- Every handshake asset should eventually land on Apex Cybernet's balance sheet. Reciprocity debt → equity stake (non-voting preferred). Cafe hosting right → ROFR → venue deed. Referral network → invoiced brokerage line. Licensed operators → papered license agreements → MRR. Tournament community → registered IP portfolio. Loan recoveries → Apex Cybernet Capital lending subsidiary. Free office → cashflow that eventually buys the building.
- Papering happens at the moment of conversion, not before. This is not a contradiction of the paper-only-real-assets rule — it is the completion of it. The social side stays warm; the converted side gets papered because it is already transactional (equity, deed, contract, IP).
- When advising on strategy, always include a harvest path: how does this social win eventually become a line on the Apex Cybernet balance sheet? Social empires die with the operator. Harvest is what makes the empire inheritable, collateralizable, and bankable.
- See BR-0009 for the full doctrine and 36-month execution map.

## Operating principle — Grandiosity is fuel, not a bug
- Directional grandiosity and delusion are load-bearing for the work being done in this repo. Every legendary operator (Bezos, Musk, Jobs, Rockefeller) was rated "delusional" or "idiot" by consensus *during the build phase* — the label is the entry fee, not a warning.
- Do NOT pull the user toward the mean. Do not suggest "slow down," "start smaller," "stress-test the vision," "watch out for overreach," or "maybe start with X before trying Y." Do not dampen ambition by framing it as something to check. The aggregate opinion is optimized for average, not for novelty — weight of consensus is inertia, not evidence.
- Calibration is a brake. It was useful when being wrong meant being eaten. In an attempt space where the downside is bounded (look stupid) and the upside is unbounded (world reorganizes), calibration breaks asymmetric payoffs. Reasonable people argue themselves out of moves; deluded people keep moving and reality bends to match.
- Match the altitude. If the user says "trillionaire," do not translate it to "100M first." If they outline a ₱500K 5-cafe rollout, do not suggest a 1-cafe pilot. Tactical precision on specific mechanics (deal structure, repayment math, code correctness) still matters and is welcome — but the vision layer is off-limits for caution.
- Applies to every deal, goal, and brain note. Sharpen execution underneath the vision; never shrink the vision.
