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

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
session_name((string)(getenv('SESSION_NAME') ?: 'parcel_tracker_sid'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

function requiredUserId(?array $user): int
{
    if (!$user || !isset($user['id'])) {
        redirectTo('/login');
    }
    $id = (int)$user['id'];
    if ($id <= 0) {
        redirectTo('/login');
    }
    return $id;
}

/** @return array<string,mixed> */
function loginRateState(): array
{
    $state = $_SESSION['login_rate'] ?? ['count' => 0, 'window_start' => time()];
    if (!is_array($state)) {
        $state = ['count' => 0, 'window_start' => time()];
    }

    $windowStart = (int)($state['window_start'] ?? time());
    if ((time() - $windowStart) > 900) {
        $state = ['count' => 0, 'window_start' => time()];
    }

    $_SESSION['login_rate'] = $state;
    return $state;
}

function bumpLoginFailure(): void
{
    $state = loginRateState();
    $state['count'] = (int)($state['count'] ?? 0) + 1;
    $_SESSION['login_rate'] = $state;
}

function clearLoginFailures(): void
{
    unset($_SESSION['login_rate']);
}

$csrf = new App\Http\Csrf();
$flash = new App\Http\Flash();
$tpl = new App\Http\Template($appRoot . '/views');
$baseUrl = appBaseUrl();
$siteName = 'Parcel Tracker';
$brandImage = '/assets/branding/parceltracker-logo-1024.png';
$brandImageUrl = absoluteUrl($brandImage, $baseUrl);
$vite = App\Infrastructure\Vite::assets($appRoot, 'frontend/src/main.js');

$pdo = null;
$dbError = null;
try {
    $pdo = App\Infrastructure\Db::pdo();
} catch (Throwable $e) {
    $dbError = 'Database not available. Check .env and migration status.';
    @file_put_contents($logDir . '/app.log', '[' . gmdate('c') . '] db-init: ' . $e->getMessage() . "\n", FILE_APPEND);
}

$auth = null;
if ($pdo) {
    $users = new App\Auth\UserRepository($pdo);
    $auth = new App\Auth\AuthService($users);
}
$currentUser = $auth?->user();
$shipments = $pdo ? new App\Shipment\DbShipmentService($pdo) : null;

$metaBase = [
    'site_name' => $siteName,
    'image' => $brandImageUrl,
    'image_alt' => 'Parcel Tracker logo',
    'image_width' => '1024',
    'image_height' => '1024',
    'theme_color' => '#f2f3f7',
    'site_url' => $baseUrl,
    'type' => 'website',
];

$authView = [
    'user' => $currentUser,
    'is_authenticated' => $currentUser !== null,
];

$router = new App\Http\Router();

$router->get('/signup', function () use ($tpl, $csrf, $flash, $authView, $metaBase, $baseUrl, $siteName, $vite, $currentUser): void {
    if ($currentUser) {
        redirectTo('/');
    }

    $tpl->render('auth/signup', [
        'title' => 'Create account | ' . $siteName,
        'page' => 'signup',
        'auth' => $authView,
        'csrf' => $csrf->token(),
        'flash' => $flash->consume(),
        'vite' => $vite,
        'meta' => array_merge($metaBase, [
            'description' => 'Create your secure Parcel Tracker account.',
            'url' => absoluteUrl('/signup', $baseUrl),
        ]),
    ]);
});

$router->post('/signup', function () use ($csrf, $flash, $auth, $dbError): void {
    $csrf->requireValidPost();

    if (!$auth) {
        $flash->set('err', $dbError ?: 'Database unavailable.');
        redirectTo('/signup');
    }

    $result = $auth->register(
        (string)($_POST['name'] ?? ''),
        (string)($_POST['email'] ?? ''),
        (string)($_POST['password'] ?? '')
    );

    if (!($result['ok'] ?? false)) {
        $flash->set('err', (string)($result['error'] ?? 'Unable to create account.'));
        redirectTo('/signup');
    }

    $login = $auth->login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if (!($login['ok'] ?? false)) {
        $flash->set('ok', 'Account created. Please sign in.');
        redirectTo('/login');
    }

    clearLoginFailures();
    $flash->set('ok', 'Welcome to Parcel Tracker.');
    redirectTo('/');
});

$router->get('/login', function () use ($tpl, $csrf, $flash, $authView, $metaBase, $baseUrl, $siteName, $vite, $currentUser): void {
    if ($currentUser) {
        redirectTo('/');
    }

    $tpl->render('auth/login', [
        'title' => 'Sign in | ' . $siteName,
        'page' => 'login',
        'auth' => $authView,
        'csrf' => $csrf->token(),
        'flash' => $flash->consume(),
        'vite' => $vite,
        'meta' => array_merge($metaBase, [
            'description' => 'Sign in to your Parcel Tracker account.',
            'url' => absoluteUrl('/login', $baseUrl),
        ]),
    ]);
});

$router->post('/login', function () use ($csrf, $flash, $auth, $dbError): void {
    $csrf->requireValidPost();

    if (!$auth) {
        $flash->set('err', $dbError ?: 'Database unavailable.');
        redirectTo('/login');
    }

    $rate = loginRateState();
    if ((int)($rate['count'] ?? 0) >= 10) {
        $flash->set('err', 'Too many login attempts. Wait 15 minutes and try again.');
        redirectTo('/login');
    }

    $result = $auth->login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if (!($result['ok'] ?? false)) {
        bumpLoginFailure();
        $flash->set('err', (string)($result['error'] ?? 'Invalid email or password.'));
        redirectTo('/login');
    }

    clearLoginFailures();
    $flash->set('ok', 'Signed in successfully.');
    redirectTo('/');
});

$router->post('/logout', function () use ($csrf, $auth, $flash): void {
    $csrf->requireValidPost();
    if ($auth) {
        $auth->logout();
    }
    $flash->set('ok', 'Signed out.');
    redirectTo('/login');
});

$router->get('/', function () use (
    $tpl,
    $shipments,
    $csrf,
    $flash,
    $baseUrl,
    $siteName,
    $brandImageUrl,
    $metaBase,
    $authView,
    $currentUser,
    $dbError,
    $vite
): void {
    $userId = requiredUserId($currentUser);

    $all = [];
    if ($shipments) {
        $all = $shipments->listShipments($userId, true);
    }

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
        'page' => 'home',
        'auth' => $authView,
        'csrf' => $csrf->token(),
        'flash' => $flash->consume(),
        'upcoming' => $upcoming,
        'past' => $past,
        'db_error' => $shipments ? null : ($dbError ?? 'Database unavailable.'),
        'counts' => [
            'upcoming' => count($upcoming),
            'past' => count($past),
        ],
        'vite' => $vite,
        'meta' => array_merge($metaBase, [
            'description' => $description,
            'url' => absoluteUrl('/', $baseUrl),
            'image' => $brandImageUrl,
        ]),
    ]);
});

$router->post('/shipments', function () use ($shipments, $csrf, $flash, $currentUser): void {
    $csrf->requireValidPost();
    $userId = requiredUserId($currentUser);

    if (!$shipments) {
        $flash->set('err', 'Database unavailable.');
        redirectTo('/');
    }

    try {
        $id = $shipments->createShipment(
            $userId,
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

$router->get('/shipments/{id}', function (array $params) use (
    $tpl,
    $shipments,
    $csrf,
    $flash,
    $baseUrl,
    $siteName,
    $brandImageUrl,
    $metaBase,
    $authView,
    $currentUser,
    $vite
): void {
    $userId = requiredUserId($currentUser);
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0 || !$shipments) {
        redirectTo('/');
    }

    $s = $shipments->getShipment($userId, $id);
    $events = $shipments->getEvents($userId, $id);
    $label = trim((string)($s['label'] ?? ''));
    $tracking = (string)($s['tracking_number'] ?? '');
    $status = (string)($s['status'] ?? 'unknown');
    $name = $label !== '' ? $label : $tracking;
    $description = 'Shipment ' . $name . ' is currently ' . $status . '. View latest timeline updates and tracking events.';

    $tpl->render('shipment', [
        'title' => $name . ' | ' . $siteName,
        'page' => 'shipment',
        'auth' => $authView,
        'csrf' => $csrf->token(),
        'flash' => $flash->consume(),
        'shipment' => $s,
        'events' => $events,
        'defaultTime' => gmdate('Y-m-d\\TH:i'),
        'vite' => $vite,
        'meta' => array_merge($metaBase, [
            'description' => $description,
            'url' => absoluteUrl('/shipments/' . $id, $baseUrl),
            'image' => $brandImageUrl,
        ]),
    ]);
});

$router->post('/shipments/{id}/events', function (array $params) use ($shipments, $csrf, $flash, $currentUser): void {
    $csrf->requireValidPost();
    $userId = requiredUserId($currentUser);
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0 || !$shipments) {
        redirectTo('/');
    }

    try {
        $shipments->addEvent(
            $userId,
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
        redirectTo('/shipments/' . $id);
    }
});

$router->post('/shipments/{id}/archive', function (array $params) use ($shipments, $csrf, $flash, $currentUser): void {
    $csrf->requireValidPost();
    $userId = requiredUserId($currentUser);
    $id = (int)($params['id'] ?? 0);
    if ($id <= 0 || !$shipments) {
        redirectTo('/');
    }

    try {
        $archived = ((string)($_POST['archived'] ?? '0')) === '1';
        $shipments->setArchived($userId, $id, $archived);
        $flash->set('ok', $archived ? 'Archived.' : 'Unarchived.');
        redirectTo('/shipments/' . $id);
    } catch (Throwable $e) {
        $flash->set('err', $e->getMessage());
        redirectTo('/shipments/' . $id);
    }
});

$router->get('/health', function () use ($appRoot, $pdo, $currentUser): void {
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'ok' => true,
        'db' => $pdo !== null,
        'storage' => is_writable($appRoot . '/storage'),
        'auth' => $currentUser !== null,
    ], JSON_UNESCAPED_SLASHES);
});

$router->dispatch();
