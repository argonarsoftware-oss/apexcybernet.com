# Apex Cybernet

## Project
Blank state. Previous construction tools project archived in `archive/construction-tools` branch.

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
