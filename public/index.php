<?php
declare(strict_types=1);

// Minimal front controller for shared hosting (no framework required).
//
// Repo layout expects public/ as a subfolder. Some cPanel deploy setups flatten public/
// into the domain document root. Detect both to avoid blank pages after deploy.
$appRoot = dirname(__DIR__);
if (!is_dir($appRoot . '/src') && is_dir(__DIR__ . '/src')) {
    $appRoot = __DIR__;
}

$logDir = $appRoot . '/storage/logs';
@mkdir($logDir, 0775, true);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php-error.log');
error_reporting(E_ALL);

spl_autoload_register(function (string $class) use ($appRoot): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $rel = substr($class, 4); // strip "App\"
    $path = $appRoot . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

App\Infrastructure\Env::loadIfPresent($appRoot . '/.env');

set_exception_handler(function (Throwable $e) use ($logDir): void {
    $line = '[' . gmdate('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n";
    @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Server error\n";
});

function redirectTo(string $path): void
{
    header('Location: ' . $path, true, 302);
    exit;
}

function appBaseUrl(): string
{
    $env = trim((string)getenv('APP_URL'));
    if ($env !== '') {
        return rtrim($env, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function absoluteUrl(string $path, string $baseUrl): string
{
    if ($path === '') {
        return $baseUrl;
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

$csrf = new App\Http\Csrf();
$flash = new App\Http\Flash();
$tpl = new App\Http\Template($appRoot . '/views');
$db = new App\Infrastructure\JsonDb($appRoot . '/storage/db.json');
$shipments = new App\Shipment\ShipmentService($db);
$baseUrl = appBaseUrl();
$siteName = 'Parcel Tracker';
$brandImage = '/assets/branding/parceltracker-logo-1024.png';
$brandImageUrl = absoluteUrl($brandImage, $baseUrl);

$router = new App\Http\Router();
$router->get('/', function () use ($tpl, $shipments, $csrf, $flash, $baseUrl, $siteName, $brandImageUrl): void {
    $all = $shipments->listShipments(true);
    $upcoming = array_values(array_filter($all, function (array $s): bool {
        $st = (string)($s['status'] ?? 'unknown');
        $archived = !empty($s['archived']);
        return !$archived && $st !== 'delivered';
    }));
    $past = array_values(array_filter($all, function (array $s): bool {
        $st = (string)($s['status'] ?? 'unknown');
        $archived = !empty($s['archived']);
        return $archived || $st === 'delivered';
    }));

    $description = 'Track deliveries, update shipment timelines, and monitor parcel status in one clean dashboard.';
    $tpl->render('home', [
        'title' => $siteName,
        'csrf' => $csrf->token(),
        'flash' => $flash->consume(),
        'upcoming' => $upcoming,
        'past' => $past,
        'counts' => [
            'upcoming' => count($upcoming),
            'past' => count($past),
        ],
        'meta' => [
            'site_name' => $siteName,
            'description' => $description,
            'url' => absoluteUrl('/', $baseUrl),
            'type' => 'website',
            'image' => $brandImageUrl,
            'image_alt' => 'Parcel Tracker logo',
            'image_width' => '1024',
            'image_height' => '1024',
            'theme_color' => '#f6f7fb',
            'site_url' => $baseUrl,
        ],
    ]);
});

$router->post('/shipments', function () use ($shipments, $csrf, $flash): void {
    $csrf->requireValidPost();
    try {
        $id = $shipments->createShipment(
            (string)($_POST['tracking_number'] ?? ''),
            (string)($_POST['label'] ?? ''),
            (string)($_POST['carrier'] ?? '')
        );
        $flash->set('ok', 'Shipment created.');
        redirectTo('/shipments/' . $id);
    } catch (Throwable $e) {
        $flash->set('err', $e->getMessage());
        redirectTo('/');
    }
});

$router->get('/shipments/{id}', function (array $params) use ($tpl, $shipments, $csrf, $flash, $baseUrl, $siteName, $brandImageUrl): void {
    $id = (string)($params['id'] ?? '');
    $s = $shipments->getShipment($id);
    $events = $shipments->getEvents($id);
    $label = trim((string)($s['label'] ?? ''));
    $tracking = (string)($s['tracking_number'] ?? '');
    $status = (string)($s['status'] ?? 'unknown');
    $name = $label !== '' ? $label : $tracking;
    $description = 'Shipment ' . $name . ' is currently ' . $status . '. View latest timeline updates and tracking events.';
    $tpl->render('shipment', [
        'title' => $name . ' | ' . $siteName,
        'csrf' => $csrf->token(),
        'flash' => $flash->consume(),
        'shipment' => $s,
        'events' => $events,
        'defaultTime' => gmdate('Y-m-d\\TH:i'),
        'meta' => [
            'site_name' => $siteName,
            'description' => $description,
            'url' => absoluteUrl('/shipments/' . rawurlencode($id), $baseUrl),
            'type' => 'website',
            'image' => $brandImageUrl,
            'image_alt' => 'Parcel Tracker logo',
            'image_width' => '1024',
            'image_height' => '1024',
            'theme_color' => '#f6f7fb',
            'site_url' => $baseUrl,
        ],
    ]);
});

$router->post('/shipments/{id}/events', function (array $params) use ($shipments, $csrf, $flash): void {
    $csrf->requireValidPost();
    $id = (string)($params['id'] ?? '');
    try {
        $shipments->addEvent(
            $id,
            (string)($_POST['event_time'] ?? ''),
            (string)($_POST['location'] ?? ''),
            (string)($_POST['description'] ?? ''),
            (string)($_POST['status'] ?? null)
        );
        $flash->set('ok', 'Event added.');
        redirectTo('/shipments/' . $id);
    } catch (Throwable $e) {
        $flash->set('err', $e->getMessage());
        redirectTo('/shipments/' . rawurlencode($id));
    }
});

$router->post('/shipments/{id}/archive', function (array $params) use ($shipments, $csrf, $flash): void {
    $csrf->requireValidPost();
    $id = (string)($params['id'] ?? '');
    try {
        $archived = ((string)($_POST['archived'] ?? '0')) === '1';
        $shipments->setArchived($id, $archived);
        $flash->set('ok', $archived ? 'Archived.' : 'Unarchived.');
        redirectTo('/shipments/' . $id);
    } catch (Throwable $e) {
        $flash->set('err', $e->getMessage());
        redirectTo('/shipments/' . rawurlencode($id));
    }
});

$router->get('/health', function () use ($appRoot): void {
    header('Content-Type: application/json; charset=utf-8');

    $dbOk = null;
    if (getenv('DB_HOST') && getenv('DB_NAME') && getenv('DB_USER')) {
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
        'storage' => is_writable($appRoot . '/storage'),
    ], JSON_UNESCAPED_SLASHES);
});

$router->dispatch();
