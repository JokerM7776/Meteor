<!doctype html>
<html lang="<?= e($i18n->locale()) ?>" dir="<?= e($i18n->direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('site_name')) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&amp;family=Source+Serif+4:wght@400;600&amp;family=JetBrains+Mono:wght@400;500&amp;family=Noto+Sans+SC:wght@400;500;700&amp;family=Noto+Serif+SC:wght@400;600&amp;display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-50 font-display text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <?= $content /* Trusted template output, already escaped at its source. */ ?>
</body>
</html>
