<?php
declare(strict_types=1);

namespace App\Http;

use ReflectionFunction;

final class Router
{
    /**
     * Routes are checked in insertion order.
     *
     * @var array<string, list<array{pattern:string, regex:string, handler:callable}>>
     */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            $matches = [];
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $k => $v) {
                if (!is_string($k)) {
                    continue;
                }
                $params[$k] = $v;
            }

            $handler = $route['handler'];

            // Allow both handler() and handler(array $params) styles.
            $rf = new ReflectionFunction(\Closure::fromCallable($handler));
            if ($rf->getNumberOfParameters() >= 1) {
                $handler($params);
            } else {
                $handler();
            }
            return;
        }

        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found\n";
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $method = strtoupper($method);
        $this->routes[$method] ??= [];

        $regex = $this->compilePattern($pattern);
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    private function compilePattern(string $pattern): string
    {
        // Support literal paths and simple placeholders like /shipments/{id}/events
        $re = preg_quote($pattern, '#');
        $re = preg_replace('#\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}#', '(?P<$1>[^/]+)', $re);
        return '#^' . $re . '$#';
    }
}
