<?php
declare(strict_types=1);

// Minimal front controller for shared hosting (no framework required).

// This app is developed with a repo layout where public/ is a subfolder.
// Some cPanel deploy setups flatten public/ into the domain document root.
// Detect both to avoid blank pages after deploy.
$root = dirname(__DIR__);
if (!is_dir($root . '/src') && is_dir(__DIR__ . '/src')) {
    $root = __DIR__;
}

$logDir = $root . '/storage/logs';
@mkdir($logDir, 0775, true);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-error.log');
error_reporting(E_ALL);

require $root . '/src/Infrastructure/Env.php';
require $root . '/src/Infrastructure/JsonDb.php';
require $root . '/src/Shipment/ShipmentService.php';
require $root . '/src/Http/Router.php';

App\Infrastructure\Env::loadIfPresent($root . '/.env');

$csrf = null;
if (PHP_SAPI !== 'cli') {
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    $csrf = (string)$_SESSION['csrf'];
}

set_exception_handler(function (Throwable $e) use ($logDir): void {
    $line = '[' . gmdate('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Server error\n";
});

$db = new App\Infrastructure\JsonDb($root . '/storage/db.json');
$shipments = new App\Shipment\ShipmentService($db);

/** @return string */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectTo(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function requireCsrf(?string $csrf): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $posted = (string)($_POST['csrf'] ?? '');
    if (!$csrf || !hash_equals($csrf, $posted)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }
}

$router = new App\Http\Router();
$router->get('/', function () use ($shipments, $csrf): void {
    $msg = (string)($_GET['msg'] ?? '');
    $err = (string)($_GET['err'] ?? '');
    $items = $shipments->listShipments(false);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Parcel Tracker</title>';
    echo '<link rel="stylesheet" href="/assets/app.css">';
    echo '</head><body><div class="wrap">';
    echo '<div class="top"><div><h1 class="title">Parcel Tracker</h1><p class="sub">Prototype: add shipments and track events manually.</p></div>';
    echo '<div><a class="pill" href="/health">health</a></div></div>';

    if ($err !== '') {
        echo '<div class="msg err">' . h($err) . '</div>';
    } elseif ($msg !== '') {
        echo '<div class="msg ok">' . h($msg) . '</div>';
    }

    echo '<div class="grid">';

    echo '<div class="card"><div class="hd">Add Shipment</div><div class="bd">';
    echo '<form method="post" action="/shipments">';
    echo '<input type="hidden" name="csrf" value="' . h((string)$csrf) . '">';
    echo '<label>Tracking number</label><input name="tracking_number" autocomplete="off" required>';
    echo '<div class="row two">';
    echo '<div><label>Label (optional)</label><input name="label" autocomplete="off"></div>';
    echo '<div><label>Carrier (optional)</label><input name="carrier" autocomplete="off" placeholder="USPS, UPS, FedEx..."></div>';
    echo '</div>';
    echo '<p style="margin:12px 0 0;"><button class="btn" type="submit">Create</button></p>';
    echo '</form></div></div>';

    echo '<div class="card"><div class="hd">Shipments</div><div class="bd">';
    if (!$items) {
        echo '<p class="sub">No shipments yet.</p>';
    } else {
        echo '<table><thead><tr><th>Label</th><th>Tracking</th><th>Status</th><th>Updated</th></tr></thead><tbody>';
        foreach ($items as $s) {
            $id = (string)$s['id'];
            $label = (string)($s['label'] ?? '');
            $tn = (string)($s['tracking_number'] ?? '');
            $st = (string)($s['status'] ?? 'unknown');
            $upd = (string)($s['updated_at'] ?? '');
            echo '<tr>';
            echo '<td><a href="/shipments/' . h($id) . '">' . h($label !== '' ? $label : $id) . '</a></td>';
            echo '<td><code>' . h($tn) . '</code></td>';
            echo '<td><span class="pill">' . h($st) . '</span></td>';
            echo '<td class="sub">' . h($upd) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div></div>';

    echo '</div></div></body></html>';
});

$router->post('/shipments', function () use ($shipments, $csrf): void {
    requireCsrf($csrf);
    try {
        $id = $shipments->createShipment(
            (string)($_POST['tracking_number'] ?? ''),
            (string)($_POST['label'] ?? ''),
            (string)($_POST['carrier'] ?? '')
        );
        redirectTo('/shipments/' . $id . '?msg=created');
    } catch (Throwable $e) {
        redirectTo('/?err=' . rawurlencode($e->getMessage()));
    }
});

$router->get('/shipments/{id}', function (array $params) use ($shipments, $csrf): void {
    $id = (string)($params['id'] ?? '');
    $msg = (string)($_GET['msg'] ?? '');
    $err = (string)($_GET['err'] ?? '');

    $s = $shipments->getShipment($id);
    $events = $shipments->getEvents($id);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Shipment</title><link rel="stylesheet" href="/assets/app.css"></head><body><div class="wrap">';
    echo '<p class="sub"><a href="/">&larr; Back</a></p>';
    echo '<div class="top"><div><h1 class="title">' . h((string)($s['label'] ?? 'Shipment')) . '</h1>';
    echo '<p class="sub"><code>' . h((string)($s['tracking_number'] ?? '')) . '</code></p></div>';
    echo '<div><span class="pill">' . h((string)($s['status'] ?? 'unknown')) . '</span></div></div>';

    if ($err !== '') {
        echo '<div class="msg err">' . h($err) . '</div>';
    } elseif ($msg !== '') {
        echo '<div class="msg ok">' . h($msg) . '</div>';
    }

    echo '<div class="grid">';

    echo '<div class="card"><div class="hd">Add Event</div><div class="bd">';
    echo '<form method="post" action="/shipments/' . h($id) . '/events">';
    echo '<input type="hidden" name="csrf" value="' . h((string)$csrf) . '">';
    echo '<label>Time</label><input type="datetime-local" name="event_time" value="' . h(gmdate('Y-m-d\\TH:i')) . '">';
    echo '<div class="row two">';
    echo '<div><label>Location (optional)</label><input name="location"></div>';
    echo '<div><label>Status</label><select name="status">';
    foreach (['created','in_transit','out_for_delivery','delivered','exception','unknown'] as $st) {
        $sel = ((string)($s['status'] ?? 'created') === $st) ? ' selected' : '';
        echo '<option value="' . h($st) . '"' . $sel . '>' . h($st) . '</option>';
    }
    echo '</select></div></div>';
    echo '<label>Description</label><textarea name="description" required placeholder="Package accepted, arrived at facility, out for delivery..."></textarea>';
    echo '<p style="margin:12px 0 0;"><button class="btn" type="submit">Add Event</button></p>';
    echo '</form>';

    $archived = !empty($s['archived']);
    echo '<hr style="border:0;border-top:1px solid rgba(255,255,255,0.14); margin:14px 0;">';
    echo '<form method="post" action="/shipments/' . h($id) . '/archive">';
    echo '<input type="hidden" name="csrf" value="' . h((string)$csrf) . '">';
    echo '<input type="hidden" name="archived" value="' . ($archived ? '0' : '1') . '">';
    echo '<button class="btn danger" type="submit">' . ($archived ? 'Unarchive' : 'Archive') . '</button>';
    echo '</form>';

    echo '</div></div>';

    echo '<div class="card"><div class="hd">Timeline</div><div class="bd">';
    if (!$events) {
        echo '<p class="sub">No events yet.</p>';
    } else {
        echo '<table><thead><tr><th>Time</th><th>Location</th><th>Description</th></tr></thead><tbody>';
        foreach ($events as $ev) {
            echo '<tr>';
            echo '<td class="sub">' . h((string)($ev['event_time'] ?? '')) . '</td>';
            echo '<td>' . h((string)($ev['location'] ?? '')) . '</td>';
            echo '<td>' . h((string)($ev['description'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div></div>';

    echo '</div></div></body></html>';
});

$router->post('/shipments/{id}/events', function (array $params) use ($shipments, $csrf): void {
    requireCsrf($csrf);
    $id = (string)($params['id'] ?? '');
    try {
        $shipments->addEvent(
            $id,
            (string)($_POST['event_time'] ?? ''),
            (string)($_POST['location'] ?? ''),
            (string)($_POST['description'] ?? ''),
            (string)($_POST['status'] ?? null)
        );
        redirectTo('/shipments/' . $id . '?msg=event+added');
    } catch (Throwable $e) {
        redirectTo('/shipments/' . rawurlencode($id) . '?err=' . rawurlencode($e->getMessage()));
    }
});

$router->post('/shipments/{id}/archive', function (array $params) use ($shipments, $csrf): void {
    requireCsrf($csrf);
    $id = (string)($params['id'] ?? '');
    try {
        $archived = ((string)($_POST['archived'] ?? '0')) === '1';
        $shipments->setArchived($id, $archived);
        redirectTo('/shipments/' . $id . '?msg=' . ($archived ? 'archived' : 'unarchived'));
    } catch (Throwable $e) {
        redirectTo('/shipments/' . rawurlencode($id) . '?err=' . rawurlencode($e->getMessage()));
    }
});

$router->get('/health', function () use ($root): void {
    header('Content-Type: application/json; charset=utf-8');

    $dbOk = null;
    if (getenv('DB_HOST') && getenv('DB_NAME') && getenv('DB_USER')) {
        require_once $root . '/src/Infrastructure/Db.php';
        try {
            App\Infrastructure\Db::pdo();
            $dbOk = true;
        } catch (Throwable $e) {
            $dbOk = false;
        }
    }

    echo json_encode([
        'ok' => true,
        'db' => $dbOk,
        'storage' => is_writable($root . '/storage'),
    ], JSON_UNESCAPED_SLASHES);
});

$router->dispatch();
