<?php if (!empty($post['cover_image'])): ?>
<img class="h-full w-full object-cover" src="<?= e(url($post['cover_image'])) ?>" alt="" loading="lazy">
<?php endif ?>
