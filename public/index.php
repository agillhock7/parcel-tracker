<?php
declare(strict_types=1);

// Minimal front controller for shared hosting (no framework required).

$root = dirname(__DIR__);
require $root . '/src/Infrastructure/Env.php';
require $root . '/src/Http/Router.php';

App\Infrastructure\Env::loadIfPresent($root . '/.env');

$router = new App\Http\Router();
$router->get('/', function (): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Parcel Tracker</title></head><body style="font-family:system-ui, sans-serif; padding:24px;">';
    echo '<h1>Parcel Tracker</h1>';
    echo '<p>App scaffold is deployed. Next: add DB + shipment workflows.</p>';
    echo '<p><a href="/health">Health</a></p>';
    echo '</body></html>';
});

$router->get('/health', function (): void {
    header('Content-Type: application/json; charset=utf-8');

    $dbOk = null;
    if (getenv('DB_HOST') && getenv('DB_NAME') && getenv('DB_USER')) {
        require_once dirname(__DIR__) . '/src/Infrastructure/Db.php';
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
    ], JSON_UNESCAPED_SLASHES);
});

$router->dispatch();

