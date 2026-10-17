<?php
// Reached only when the root .htaccess is not applied (no mod_rewrite, AllowOverride None or
// Nginx): the app cannot route safely from here, so explain how to finish the server setup.
$problem = 'rewrite';
require __DIR__ . '/views/setup.php';
