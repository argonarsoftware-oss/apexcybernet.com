# Argonar

## Project
Blank state. Previous construction tools project archived in `archive/construction-tools` branch.

## Local Dev
- XAMPP stack at `C:\xampp\htdocs\Argonar Construction`
- Local URL: `http://localhost/Argonar%20Construction/`
- MySQL: root, no password, database `argonar_construction`

## Auto-Deploy
- GitHub repo: `kierl-j/Argonar-Construction`
- Commits with `[deploy]` in the message trigger auto-deploy to VPS
- **Production URL**: https://argonar.co
- **Always auto-deploy**: Every commit must include `[deploy]` and be pushed immediately. Do not wait to be asked.

## Coding Style
- PHP: No frameworks, vanilla PHP + PDO
- JS: jQuery, no build tools
- CSS: Custom properties in `:root`, single `app.css`
- Forms: CSRF via `csrf_field()` / `csrf_check()`
- Naming: snake_case for PHP vars/DB columns, camelCase for JS
