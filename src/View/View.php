<?php

declare(strict_types=1);

namespace App\View;

use RuntimeException;

/**
 * Plain-PHP templates with an optional layout.
 *
 * Templates live in `templates/` and receive their data as variables. Inside a
 * layout the rendered page is available as `$content`. Escape everything with `e()`.
 */
final class View
{
    /** @param array<string, mixed> $shared variables available to every template */
    public function __construct(
        private readonly string $templatePath,
        private array $shared = [],
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $data = $data + $this->shared;
        $content = $this->renderFile($template, $data);

        return $layout === null ? $content : $this->renderFile('layouts/' . $layout, ['content' => $content] + $data);
    }

    /**
     * Renders a reusable fragment from `templates/partials/`. Templates call it as `$view->partial(...)`.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $name, array $data = []): string
    {
        return $this->renderFile('partials/' . $name, $data + $this->shared);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->templatePath . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        $data['view'] = $this;

        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();

            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        })($file, $data);
    }
}
