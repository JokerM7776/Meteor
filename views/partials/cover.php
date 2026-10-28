<?php // A post's cover image. $post (Post). ?>
<?php if ($post->cover !== null): ?>
<img class="h-full w-full object-cover" src="<?= e(url($post->cover->url)) ?>" alt="" loading="lazy">
<?php endif ?>
