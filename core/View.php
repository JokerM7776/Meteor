<?php
declare(strict_types=1);
namespace Core;

final class View
{
    public function __construct(private string $root) {}

    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = $this->partial($template, $data);
        return $layout === null ? $content : $this->partial($layout, ['content' => $content] + $data);
    }

    public function partial(string $template, array $data = []): string
    {
        if (!preg_match('~^[a-zA-Z0-9_/-]+$~D', $template) || str_contains($template, '..')) {
            throw new \InvalidArgumentException('Invalid template name.');
        }
        $file = $this->root . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }
        ob_start();
        try {
            extract($data, EXTR_SKIP);
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
