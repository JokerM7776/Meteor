<?php
$tabs = []; foreach (['settings' => 'Site', 'home' => 'Home page', 'author' => 'Author', 'appearance' => 'Appearance', 'themes' => 'Themes', 'strings' => 'Interface text', 'optimization' => 'Optimization'] as $key => $label) { $tabs['/admin/' . $key] = $label; }
$tabCurrent = '/admin/' . $active; $tabsLabel = 'Settings sections'; $tabCounts = [];
require __DIR__ . '/tabs.php';
