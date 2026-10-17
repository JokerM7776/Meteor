<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/autoload.php';
try { echo Core\ReleasePackage::build(dirname(__DIR__), dirname(__DIR__) . '/dist') . "\n"; }
catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
