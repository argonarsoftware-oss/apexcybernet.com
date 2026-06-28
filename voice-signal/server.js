/**
 * Apex Cybernet Voice Signaling Server
 *
 * Dependency-free (except express). Uses Server-Sent Events for server→client push.
 * Clients POST SDP/ICE payloads; server relays them to the addressed peer via SSE.
 * Authentication: HMAC-SHA256 JWT issued by PHP, validated here using the same secret.
 */
const express = require('express');
const crypto  = require('crypto');
const fs      = require('fs');
const path    = require('path');

const PORT   = parseInt(process.env.PORT || '3000', 10);
const SECRET = (fs.readFileSync(process.env.VOICE_SECRET_FILE || path.join(__dirname, '.voice-secret'), 'utf8') || '').trim();
if (!SECRET) { console.error('FATAL: voice secret file missing or empty'); process.exit(1); }

const app = express();
app.use(express.json({ limit: '256kb' }));

// ── In-memory registry ──
// peerKey = `${roomId}|${peerId}` → { write, res, displayName, joinedAt, mic, roomId, peerId }
const peers = new Map();
// roomId → Set<peerKey>
const rooms = new Map();

function keyFor(rid, pid) { return rid + '|' + pid; }
function b64url(buf)      { return Buffer.from(buf).toString('base64').replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,''); }
function b64urlDec(s)     { return Buffer.from(s.replace(/-/g,'+').replace(/_/g,'/') + '==='.slice(0, (4 - s.length % 4) % 4), 'base64'); }

function verifyToken(tok) {
    if (!tok) return null;
    const parts = String(tok).split('.');
    if (parts.length !== 3) return null;
    const [h, p, s] = parts;
    const expected = b64url(crypto.createHmac('sha256', SECRET).update(h + '.' + p).digest());
    if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(s))) return null;
    let payload;
    try { payload = JSON.parse(b64urlDec(p).toString('utf8')); } catch { return null; }
    if (!payload.exp || Date.now()/1000 > payload.exp) return null;
    if (!payload.peer_id) return null;
    // Normalise: derive room_id from group_id if older token format
    if (!payload.room_id) {
        if (payload.group_id) payload.room_id = 'g:' + payload.group_id;
        else return null;
    }
    return payload;
}

function listMembers(roomId) {
    const out = [];
    for (const k of (rooms.get(roomId) || [])) {
        const p = peers.get(k);
        if (p) out.push({ peer_id: p.peerId, display_name: p.displayName, mic: p.mic, joined_at: p.joinedAt });
    }
    return out;
}

function pushTo(peerKey, event) {
    const target = peers.get(peerKey);
    if (target) try { target.write(event); } catch {}
}

function broadcast(roomId, except, event) {
    for (const k of (rooms.get(roomId) || [])) {
        if (k !== except) pushTo(k, event);
    }
}

// ── Health ──
app.get('/rtc/health', (_req, res) => {
    res.json({ ok: true, peers: peers.size, rooms: rooms.size, uptime_sec: Math.round(process.uptime()) });
});

// ── SSE stream: join a voice room ──
app.get('/rtc/stream', (req, res) => {
    const auth = verifyToken(req.query.token);
    if (!auth) { res.status(401).end('invalid_token'); return; }
    const { room_id: rid, peer_id: pid, display_name: dn } = auth;
    const pk = keyFor(rid, pid);

    res.set({
        'Content-Type':      'text/event-stream',
        'Cache-Control':     'no-cache, no-transform',
        'Connection':        'keep-alive',
        'X-Accel-Buffering': 'no'
    });
    res.flushHeaders();
    res.write(': connected\n\n');

    const write = (obj) => {
        try { res.write('data: ' + JSON.stringify(obj) + '\n\n'); } catch {}
    };

    if (peers.has(pk)) {
        try { peers.get(pk).res.end(); } catch {}
    }

    const peer = { write, res, roomId: rid, peerId: pid, displayName: dn || ('#' + pid), mic: true, joinedAt: Date.now() };
    peers.set(pk, peer);
    if (!rooms.has(rid)) rooms.set(rid, new Set());
    rooms.get(rid).add(pk);

    write({ type: 'roster', members: listMembers(rid).filter(m => m.peer_id !== pid) });
    broadcast(rid, pk, { type: 'join', peer: { peer_id: pid, display_name: peer.displayName, mic: true } });

    const pingTimer = setInterval(() => { try { res.write(': ping\n\n'); } catch {} }, 25000);

    req.on('close', () => {
        clearInterval(pingTimer);
        peers.delete(pk);
        const r = rooms.get(rid);
        if (r) {
            r.delete(pk);
            if (r.size === 0) rooms.delete(rid);
        }
        broadcast(rid, pk, { type: 'leave', peer_id: pid });
    });
});

// ── Signal relay: forward SDP/ICE to addressee ──
app.post('/rtc/signal', (req, res) => {
    const auth = verifyToken(req.body.token || req.query.token);
    if (!auth) { res.status(401).json({ error: 'invalid_token' }); return; }
    const { room_id: rid, peer_id: pid } = auth;
    const toPid = parseInt(req.body.to);
    const data  = req.body.data;
    if (!toPid || data === undefined) { res.status(400).json({ error: 'missing_to_or_data' }); return; }
    const toKey = keyFor(rid, toPid);
    if (!peers.has(toKey)) { res.json({ ok: false, reason: 'peer_offline' }); return; }
    pushTo(toKey, { type: 'signal', from: pid, data });
    res.json({ ok: true });
});

// ── Mic state update (muted/unmuted) — broadcast to the room ──
app.post('/rtc/mic', (req, res) => {
    const auth = verifyToken(req.body.token || req.query.token);
    if (!auth) { res.status(401).json({ error: 'invalid_token' }); return; }
    const { room_id: rid, peer_id: pid } = auth;
    const pk = keyFor(rid, pid);
    const peer = peers.get(pk);
    if (!peer) { res.status(404).json({ error: 'not_in_room' }); return; }
    peer.mic = !!req.body.mic;
    broadcast(rid, null, { type: 'mic', peer_id: pid, mic: peer.mic });
    res.json({ ok: true });
});

app.listen(PORT, '127.0.0.1', () => {
    console.log(`[apexcybernet-voice] listening on 127.0.0.1:${PORT}`);
});
