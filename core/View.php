<?php
declare(strict_types=1);
namespace Core;

final class View
{
    private ?string $themeRoot = null;
    public function __construct(private string $root, ?string $themeRoot = null) { $this->setThemeRoot($themeRoot); }
    public function setThemeRoot(?string $root): void { $this->themeRoot = $root === null ? null : (realpath($root) ?: null); }

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
        $public = $template === 'layouts/public' || preg_match('~^(public|partials|slots)/~', $template);
        if ($public && $this->themeRoot !== null) {
            $candidate = realpath($this->themeRoot . '/' . $template . '.php');
            if ($candidate !== false && str_starts_with($candidate, $this->themeRoot . DIRECTORY_SEPARATOR) && is_file($candidate)) {
                try { return self::evaluate($candidate, $data); }
                catch (\Throwable $error) {
                    \App\Theme\Deprecated::log('Theme ' . basename($this->themeRoot) . ' template ' . $template . ': ' . $error->getMessage() . ' ' . $error->getFile() . ':' . $error->getLine());
                }
            }
        }
        if (str_starts_with($template, 'slots/')) { return ''; }
        $file = $this->root . '/' . $template . '.php';
        if (!is_file($file)) { throw new \RuntimeException('Template not found: ' . $template); }
        return self::evaluate($file, $data);
    }

    private static function evaluate(string $file, array $data): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            // A static closure keeps the View instance and caller's locals out of template scope.
            (static function (string $__file, array $__data): void { extract($__data, EXTR_SKIP); require $__file; })($file, $data);
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            while (ob_get_level() > $level) { ob_end_clean(); }
            throw $error;
        }
    }
}
