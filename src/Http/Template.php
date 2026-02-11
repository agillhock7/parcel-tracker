<?php
declare(strict_types=1);

namespace App\Http;

final class Template
{
    public function __construct(private string $viewsPath)
    {
    }

    /** @param array<string, mixed> $params */
    public function render(string $view, array $params = [], string $layout = 'layout'): void
    {
        $viewPath = $this->viewsPath . '/' . ltrim($view, '/') . '.php';
        if (!is_file($viewPath)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Template missing: {$view}\n";
            return;
        }

        extract($params, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = (string)ob_get_clean();

        $layoutPath = $this->viewsPath . '/' . ltrim($layout, '/') . '.php';
        if (!is_file($layoutPath)) {
            echo $content;
            return;
        }

        require $layoutPath;
    }
}

