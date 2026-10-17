<?php
// Shown before the app can boot ($problem is 'rewrite' or 'subfolder'). Styles are inline because
// /assets may not be reachable while routing is broken.
http_response_code(503);
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=UTF-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
header('X-Robots-Tag: noindex, nofollow');
$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Set up Meteor</title>
<style>
body { margin: 0; background: #f8fafc; color: #0f172a; font: 17px/1.6 system-ui, sans-serif; }
main { max-width: 42rem; margin: 0 auto; padding: 3rem 1.5rem; }
h1 { font-size: 1.875rem; line-height: 1.2; }
code { font: 0.9em ui-monospace, monospace; background: #e2e8f0; padding: 0.1em 0.3em; border-radius: 4px; }
.warn { background: #fffbeb; color: #451a03; padding: 1rem; border-radius: 8px; }
@media (prefers-color-scheme: dark) { body { background: #020617; color: #f1f5f9; } code { background: #1e293b; } }
</style></head>
<body><main>
<h1>Meteor needs one more server step</h1>
<?php if ($problem === 'subfolder'): ?>
<p>Meteor is running in a sub-folder (<code><?= $esc($base) ?></code>). It must be the root of a domain or subdomain.</p>
<ul>
<li>Move the contents of this folder up into the domain's web root, <strong>or</strong></li>
<li>point a domain or subdomain's document root at this folder (or, better, at its <code>public/</code> folder).</li>
</ul>
<?php else: ?>
<p>This web server is not applying Meteor's <code>.htaccess</code> rules, so it cannot route requests.</p>
<p class="warn">Do not install until this is fixed: files outside <code>public/</code>, such as the settings file and database, could be downloadable.</p>
<ul>
<li><strong>Best:</strong> point the document root at the <code>public/</code> folder.</li>
<li><strong>Apache:</strong> enable <code>mod_rewrite</code> and allow <code>.htaccess</code> files (<code>AllowOverride All</code>).</li>
<li><strong>Nginx:</strong> <code>.htaccess</code> is never read; set <code>root</code> to the <code>public/</code> folder (see README).</li>
</ul>
<?php endif ?>
<p>Then reload this page to start the installer.</p>
</main></body></html>
