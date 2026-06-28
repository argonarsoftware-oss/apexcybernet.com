#!/bin/bash
# Argonar Voice Signaling — idempotent installer.
# Re-running is safe: it only changes things that are missing or out of date.
# Does NOT install Node.js, apt packages, or anything system-wide.
set -e

cd "$(dirname "$0")"

echo "── Argonar Voice deploy ──"
echo "Working dir: $(pwd)"
echo

# ── 1) Dependencies (local only: voice-signal/node_modules/) ──────────────
if [ -d node_modules/express ]; then
    echo "[1/5] skipping  : express already installed in ./node_modules/"
else
    echo "[1/5] installing: express → ./node_modules/ (local only, no global, no apt)"
    npm install --omit=dev --no-audit --no-fund
fi

# ── 2) Signing secret (generate only if missing) ─────────────────────────
if [ -f .voice-secret ]; then
    echo "[2/5] skipping  : .voice-secret already exists (preserved)"
else
    echo "[2/5] generating: .voice-secret (48 random bytes)"
    openssl rand -hex 48 > .voice-secret
    chmod 640 .voice-secret
    chown www-data:www-data .voice-secret 2>/dev/null || true
fi

# ── 3) systemd unit (copy only if different) ─────────────────────────────
SYSTEMD_UNIT=/etc/systemd/system/argonar-voice.service
if [ -f "$SYSTEMD_UNIT" ] && cmp -s argonar-voice.service "$SYSTEMD_UNIT"; then
    echo "[3/5] skipping  : argonar-voice.service unchanged at $SYSTEMD_UNIT"
    RELOAD_NEEDED=0
else
    echo "[3/5] installing: argonar-voice.service → $SYSTEMD_UNIT"
    cp -f argonar-voice.service "$SYSTEMD_UNIT"
    RELOAD_NEEDED=1
fi

# ── 4) Enable + start (or restart) the service ───────────────────────────
if [ "$RELOAD_NEEDED" = "1" ]; then
    echo "[4/5] running   : systemctl daemon-reload"
    systemctl daemon-reload
fi

if systemctl is-enabled --quiet argonar-voice; then
    echo "[4/5] skipping  : argonar-voice already enabled (starts on boot)"
else
    echo "[4/5] enabling  : argonar-voice (will start on boot)"
    systemctl enable argonar-voice
fi

if systemctl is-active --quiet argonar-voice; then
    if [ "$RELOAD_NEEDED" = "1" ]; then
        echo "[4/5] restarting: argonar-voice (unit file changed)"
        systemctl restart argonar-voice
    else
        echo "[4/5] skipping  : argonar-voice already running (not restarting)"
    fi
else
    echo "[4/5] starting  : argonar-voice"
    systemctl start argonar-voice
fi

sleep 1

# ── 5) Health check ──────────────────────────────────────────────────────
echo "[5/5] health    :"
if curl -fsS --max-time 3 http://127.0.0.1:3000/rtc/health; then
    echo
    echo
    echo "✅ Signaling server is up on 127.0.0.1:3000"
else
    echo
    echo "❌ Health check failed. Last 20 log lines:"
    journalctl -u argonar-voice -n 20 --no-pager || true
    exit 1
fi

echo
echo "── Next step ──"
echo "Add this line to your HTTPS vhost for argonar.co (one time only):"
echo "    Include $(pwd)/apache-voice.conf"
echo "Then: sudo apachectl configtest && sudo systemctl reload apache2"
echo "Verify:  curl -s https://argonar.co/rtc/health"
