<?php
declare(strict_types=1);
// Maintainer tool: downloads the site fonts from Google Fonts into public/assets/fonts, so sites can
// serve them themselves (Settings → Optimization → Fonts → On this server). Needs network access;
// commit the result. Run again to update the fonts. Chinese (Noto Sans/Serif SC) is left out on
// purpose: it is several megabytes per weight, and readers' system fonts cover Chinese well.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!extension_loaded('curl')) { fwrite(STDERR, "Enable ext-curl to download the fonts.\n"); exit(1); }

// File prefix => [family, axes for the Google Fonts CSS2 API, folder in github.com/google/fonts/ofl]
$families = [
    'source-sans-3' => ['Source Sans 3', 'ital,wght@0,400..700;1,400..700', 'sourcesans3'],
    'source-serif-4' => ['Source Serif 4', 'ital,opsz,wght@0,8..60,400..700;1,8..60,400..700', 'sourceserif4'],
    'jetbrains-mono' => ['JetBrains Mono', 'wght@400..700', 'jetbrainsmono'],
];
// The interface font in Latin script is on every page, so browsers fetch it before the CSS asks.
$preload = ['source-sans-3-normal-latin'];
$directory = dirname(__DIR__) . '/public/assets/fonts';

function download(string $url): string
{
    if (!preg_match('~^https://(?:fonts\.googleapis\.com|fonts\.gstatic\.com|raw\.githubusercontent\.com/google/fonts)/~', $url)) {
        throw new RuntimeException('Unexpected URL: ' . $url);
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60,
        // Google picks the font format from the browser; a current Chrome gets WOFF2.
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    ]);
    $body = curl_exec($handle); $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if (!is_string($body) || $status !== 200 || $body === '') { throw new RuntimeException('Download failed (HTTP ' . $status . '): ' . $url); }
    return $body;
}

try {
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) { throw new RuntimeException('Cannot create ' . $directory); }
    $css = "/* Meteor fonts, served from this site. Made by bin/fonts.php from Google Fonts.\n   License: SIL Open Font License 1.1, see the OFL-*.txt files next to this one. */\n";
    $files = []; $preloadFiles = []; $bytes = 0;
    foreach ($families as $prefix => [$family, $axes, $license]) {
        $sheet = download('https://fonts.googleapis.com/css2?family=' . str_replace(' ', '+', $family) . ':' . $axes . '&display=swap');
        // Google's CSS is a list of "/* subset */ @font-face { … }" blocks, one per script and style.
        preg_match_all('~/\*\s*([a-z0-9-]+)\s*\*/\s*(@font-face\s*\{[^}]*\})~', $sheet, $blocks, PREG_SET_ORDER);
        if (!$blocks) { throw new RuntimeException('No @font-face rules for ' . $family . '.'); }
        foreach ($blocks as [, $subset, $rule]) {
            if (!preg_match('~font-style:\s*(normal|italic)~', $rule, $style) || !preg_match('~url\((https://fonts\.gstatic\.com/[^)\s]+\.woff2)\)~', $rule, $url)) {
                throw new RuntimeException('Unexpected @font-face rule for ' . $family . ' (' . $subset . ').');
            }
            if (!isset($files[$url[1]])) {
                $font = download($url[1]);
                if (!str_starts_with($font, 'wOF2')) { throw new RuntimeException('Not a WOFF2 file: ' . $url[1]); }
                $name = $prefix . '-' . $style[1] . '-' . $subset;
                $files[$url[1]] = $name . '.' . substr(hash('sha256', $font), 0, 8) . '.woff2';
                file_put_contents($directory . '/' . $files[$url[1]], $font); $bytes += strlen($font);
                if (in_array($name, $preload, true)) { $preloadFiles[] = $files[$url[1]]; }
            }
            $css .= '/* ' . $subset . " */\n" . str_replace($url[0], 'url(' . $files[$url[1]] . ')', $rule) . "\n";
        }
        file_put_contents($directory . '/OFL-' . $prefix . '.txt', download('https://raw.githubusercontent.com/google/fonts/main/ofl/' . $license . '/OFL.txt'));
    }
    foreach (glob($directory . '/*.woff2') ?: [] as $old) {
        if (!in_array(basename($old), $files, true)) { unlink($old); }
    }
    file_put_contents($directory . '/fonts.css', $css);
    file_put_contents($directory . '/fonts.json', json_encode(['meteor_fonts' => 1, 'families' => array_column($families, 0),
        'files' => count($files), 'preload' => $preloadFiles], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    printf("%d font files (%.0f KB) in public/assets/fonts. Commit them with fonts.css and fonts.json.\n", count($files), $bytes / 1024);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
