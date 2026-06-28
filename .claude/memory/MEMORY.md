# Apex Cybernet - Memory

## User Preferences
- **Always auto-deploy**: Commit with `[deploy]` and `git push` after every change — don't wait to be asked

## Production URL
- **Domain**: https://apexcybernet.com

## Migration Workflow
- Create `migrate_<name>.php` in project root
- Run locally via curl, run on production via curl/WebFetch
- Delete after running — never leave deployed

## HCoin Blockchain
- [HCoin blockchain setup](project_hcoin_blockchain.md) — treasury address, deployment status, chain details

## Project Structure
- See `CLAUDE.md` in project root for full details
- Tools follow CRUD pattern in their own directories (boq/, rebar/)
- PayRex payments with webhook + success page dual activation
