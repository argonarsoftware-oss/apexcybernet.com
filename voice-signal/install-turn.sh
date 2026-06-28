#!/bin/bash
# Install + configure coturn (TURN server) on the Argonar VPS.
# Run as root. Idempotent — safe to re-run.
set -e

echo "── coturn TURN server install ──"

# 1) Install
if ! command -v turnserver &>/dev/null; then
    echo "[1/4] installing coturn package…"
    apt-get update -qq
    apt-get install -y coturn
else
    echo "[1/4] coturn already installed"
fi

# 2) Generate static-auth secret if missing
SECRET_FILE=/etc/turnserver-secret
if [ ! -f "$SECRET_FILE" ]; then
    openssl rand -hex 32 > "$SECRET_FILE"
    echo "[2/4] generated static-auth-secret"
else
    echo "[2/4] static-auth-secret already exists"
fi
# Ensure web user can read it for signing TURN credentials
chmod 644 "$SECRET_FILE"
chown root:www-data "$SECRET_FILE" 2>/dev/null || chown root:www-data "$SECRET_FILE" || true
SECRET=$(cat "$SECRET_FILE")

# 3) Configure
PUBLIC_IP=$(curl -fs https://api.ipify.org || echo "")
if [ -z "$PUBLIC_IP" ]; then PUBLIC_IP=$(hostname -I | awk '{print $1}'); fi
echo "    public IP detected: $PUBLIC_IP"

cat > /etc/turnserver.conf <<EOF
# Argonar coturn config — auto-generated, safe to edit
listening-port=3478
fingerprint
lt-cred-mech
use-auth-secret
static-auth-secret=$SECRET
realm=argonar.co
total-quota=100
stale-nonce=600
no-tls
no-dtls
no-multicast-peers
external-ip=$PUBLIC_IP
log-file=/var/log/turnserver.log
syslog
EOF
echo "[3/4] wrote /etc/turnserver.conf"

# Enable the service
sed -i 's/^#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn 2>/dev/null || true
if ! grep -q "^TURNSERVER_ENABLED=1" /etc/default/coturn 2>/dev/null; then
    echo "TURNSERVER_ENABLED=1" >> /etc/default/coturn
fi

systemctl enable coturn
systemctl restart coturn
sleep 1
systemctl status coturn --no-pager | head -8

# 4) UFW firewall
if command -v ufw &>/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow 3478/udp >/dev/null 2>&1 || true
    ufw allow 3478/tcp >/dev/null 2>&1 || true
    echo "[4/4] opened ports 3478/udp + 3478/tcp in ufw"
else
    echo "[4/4] ufw not active — make sure 3478/udp + 3478/tcp are reachable"
fi

echo
echo "✅ Done."
echo
echo "═══════════════════════════════════════════════════════════════════"
echo "  PHP-side: paste this secret into includes/voice_config.php"
echo "  (or set TURN_SECRET env var)."
echo
echo "  TURN secret (KEEP PRIVATE):"
echo "    $SECRET"
echo
echo "  Public IP your TURN listens on:"
echo "    $PUBLIC_IP:3478"
echo "═══════════════════════════════════════════════════════════════════"
