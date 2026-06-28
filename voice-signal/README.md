# Apex Cybernet Voice Signaling

Real-time group voice via WebRTC mesh. Node.js + Server-Sent Events (no WebSocket).
Good for groups of up to 4–5 participants per call on a 1-core VPS.
Media (audio) goes peer-to-peer directly between browsers. This server only relays
SDP offers/answers and ICE candidates.

## Files

| File | Purpose |
|---|---|
| `server.js` | The Node.js SSE signaling server. Stateless, no DB. |
| `package.json` | Only dep: `express`. |
| `apexcybernet-voice.service` | systemd unit — runs the server as `www-data`. |
| `apache-voice.conf` | Apache vhost snippet — proxies `/rtc/*` → `127.0.0.1:3000`. |
| `deploy.sh` | One-shot installer: npm install → systemd enable → health check. |

## First-time deploy (on the VPS)

```bash
cd /var/www/apexcybernet.com/voice-signal
sudo ./deploy.sh
```

That will:
1. `npm install`
2. Generate `.voice-secret` (48 random bytes, hex) if missing
3. Install + enable the `apexcybernet-voice` systemd service
4. Health-check `http://127.0.0.1:3000/rtc/health`

## One-time Apache step

Add this line inside your `apexcybernet.com` HTTPS vhost
(usually `/etc/apache2/sites-available/apexcybernet.com-le-ssl.conf`):

```apache
Include /var/www/apexcybernet.com/voice-signal/apache-voice.conf
```

Then:

```bash
sudo apachectl configtest
sudo systemctl reload apache2
curl -s https://apexcybernet.com/rtc/health
```

You should see `{"ok":true,"peers":0,"groups":0,...}`.

## How the secret is shared

The HMAC signing key lives at `voice-signal/.voice-secret` and is read by:
- Node.js (on boot, from `fs.readFileSync`)
- PHP (via `includes/voice_config.php`, on each token request)

Both must see the same file — they do, because they run on the same machine
and the file sits inside the Apex Cybernet project directory. The file is in
`.gitignore` so it never leaves the VPS.

## How auth works

1. Browser clicks "Start voice call" in a group chat.
2. PHP (`/api/voice-token.php`) checks the user is a member of that group,
   then issues a 10-minute JWT signed with `.voice-secret`.
3. Browser opens `EventSource('/rtc/stream?token=<jwt>')`.
4. Node verifies the token, places the peer in its in-memory group roster,
   and pushes roster/join/leave/signal events via SSE.
5. Browsers exchange SDP + ICE through POST `/rtc/signal`.
6. Audio flows directly P2P between browsers.

## Logs

```bash
sudo journalctl -u apexcybernet-voice -f
```

## Restart

```bash
sudo systemctl restart apexcybernet-voice
```

## Health

```bash
curl -s http://127.0.0.1:3000/rtc/health     # local
curl -s https://apexcybernet.com/rtc/health         # via Apache proxy
```
