<?php // Drag handle for a sortable row. Expects $handleName (what the row is called). Shown by admin.js. ?>
<button type="button" class="drag-handle" data-drag-handle aria-label="Move <?= e($handleName) ?>" title="Drag to reorder, or use the arrow keys" hidden>
<svg width="14" height="18" viewBox="0 0 14 18" aria-hidden="true" fill="currentColor"><circle cx="4" cy="3.5" r="1.5"/><circle cx="10" cy="3.5" r="1.5"/><circle cx="4" cy="9" r="1.5"/><circle cx="10" cy="9" r="1.5"/><circle cx="4" cy="14.5" r="1.5"/><circle cx="10" cy="14.5" r="1.5"/></svg>
</button>
