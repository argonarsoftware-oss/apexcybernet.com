<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\SecureServer;
use React\Socket\Server as ReactServer;

if (!defined('QR_HMAC_SECRET')) {
    define('QR_HMAC_SECRET', 'apexcybernet_qr_secret_2026');
}

class HCoinSocket implements MessageComponentInterface
{
    /** @var ConnectionInterface[] */
    protected array $clients = [];

    /** @var array[] Keys: uid, since */
    protected array $state = [];

    protected ?PDO $pdo = null;

    public function __construct()
    {
        $this->connectDb();
    }

    protected function connectDb(): void
    {
        try {
            $this->pdo = new PDO(
                'mysql:host=localhost;dbname=apexcybernet;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            echo '[ws] DB connect failed: ' . $e->getMessage() . PHP_EOL;
            $this->pdo = null;
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        $this->clients[$id] = $conn;
        $this->state[$id]   = ['uid' => null, 'since' => time()];
        echo '[ws] New connection #' . $id . PHP_EOL;
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $id   = spl_object_id($from);
        $data = json_decode($msg, true);

        if (!is_array($data) || !isset($data['type'])) {
            return;
        }

        if ($data['type'] === 'auth') {
            $token = $data['token'] ?? '';
            $result = $this->verifyToken($token);

            if ($result === false) {
                $from->send(json_encode(['type' => 'error', 'message' => 'Invalid or expired token']));
                $from->close();
                return;
            }

            $this->state[$id]['uid']   = $result;
            $this->state[$id]['since'] = time();
            $from->send(json_encode(['type' => 'ready']));
            echo '[ws] Auth OK for uid=' . $result . ' on #' . $id . PHP_EOL;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $id = spl_object_id($conn);
        unset($this->clients[$id], $this->state[$id]);
        echo '[ws] Connection #' . $id . ' closed' . PHP_EOL;
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $id = spl_object_id($conn);
        echo '[ws] Error on #' . $id . ': ' . $e->getMessage() . PHP_EOL;
        unset($this->clients[$id], $this->state[$id]);
        $conn->close();
    }

    /**
     * Verify token format "uid:ts:sig". Returns uid string on success, false on failure.
     */
    protected function verifyToken(string $token)
    {
        $parts = explode(':', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$uid, $ts, $sig] = $parts;

        if (!is_numeric($ts) || abs(time() - (int)$ts) >= 90) {
            return false;
        }

        $expected = hash_hmac('sha256', $uid . ':' . $ts, QR_HMAC_SECRET);
        if (!hash_equals($expected, $sig)) {
            return false;
        }

        return $uid;
    }

    /**
     * Called every 0.5s by the event loop timer. Queries new charges for each authed client.
     */
    public function tick(): void
    {
        foreach ($this->clients as $id => $conn) {
            $st = $this->state[$id] ?? null;
            if (!$st || $st['uid'] === null) {
                continue;
            }

            $uid   = $st['uid'];
            $since = $st['since'];

            try {
                if ($this->pdo === null) {
                    $this->connectDb();
                    if ($this->pdo === null) {
                        continue;
                    }
                }

                $stmt = $this->pdo->prepare(
                    "SELECT t.amount, t.ref, UNIX_TIMESTAMP(t.created_at) AS ts, a.h_coins
                     FROM h_coin_transactions t
                     JOIN accounts a ON a.id = t.account_id
                     WHERE t.account_id = :uid
                       AND t.type = 'debit'
                       AND t.reason = 'qr_payment'
                       AND t.created_at > FROM_UNIXTIME(:since)
                     ORDER BY t.created_at ASC"
                );
                $stmt->execute([':uid' => $uid, ':since' => $since]);
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $merchant = strpos($row['ref'], 'to:') === 0 ? substr($row['ref'], 3) : $row['ref'];
                    $conn->send(json_encode([
                        'type'     => 'charge',
                        'amount'   => (int)$row['amount'],
                        'merchant' => $merchant,
                        'ts'       => (int)$row['ts'],
                        'h_coins'  => (int)$row['h_coins'],
                    ]));

                    // Advance since so we don't re-send this row
                    if ((int)$row['ts'] + 1 > $this->state[$id]['since']) {
                        $this->state[$id]['since'] = (int)$row['ts'] + 1;
                    }
                }
            } catch (PDOException $e) {
                echo '[ws] DB error for uid=' . $uid . ': ' . $e->getMessage() . ' — reconnecting' . PHP_EOL;
                $this->pdo = null;
            }
        }
    }
}

// --- Main ---

$loop   = Factory::create();
$socket = new HCoinSocket();

// Tick every 0.5 seconds
$loop->addPeriodicTimer(0.5, function () use ($socket) {
    $socket->tick();
});

$wsServer   = new WsServer($socket);
$httpServer = new HttpServer($wsServer);
$port       = 6001;

$certPath = '/etc/letsencrypt/live/apexcybernet.com/fullchain.pem';
$keyPath  = '/etc/letsencrypt/live/apexcybernet.com/privkey.pem';

if (file_exists($certPath) && file_exists($keyPath)) {
    // Production: secure WSS
    $reactServer = new ReactServer('0.0.0.0:' . $port, $loop);
    $secureServer = new SecureServer($reactServer, $loop, [
        'local_cert'        => $certPath,
        'local_pk'          => $keyPath,
        'allow_self_signed' => false,
        'verify_peer'       => false,
    ]);
    $server = new IoServer($httpServer, $secureServer, $loop);
    echo '[ws] Starting WSS server on port ' . $port . ' (production, TLS)' . PHP_EOL;
} else {
    // Local: plain WS
    $server = IoServer::factory($httpServer, $port, '0.0.0.0', $loop);
    echo '[ws] Starting WS server on port ' . $port . ' (local, no TLS)' . PHP_EOL;
}

$server->run();
