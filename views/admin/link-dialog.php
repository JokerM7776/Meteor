<?php // Link dialog for the editor toolbar. It stays outside the translation form. ?>
<dialog id="link-dialog" class="admin-dialog max-w-lg!" aria-labelledby="link-heading">
<form method="dialog" class="space-y-4 p-5" data-link-form>
<h2 id="link-heading" class="text-lg font-bold">Link</h2>
<label class="field">Address<input type="text" inputmode="url" name="url" required placeholder="https://… or /en/posts/…" spellcheck="false" autocomplete="off" data-link-url></label>
<label class="field">Text<input name="text" data-link-text></label>
<p class="text-xs text-slate-500 dark:text-slate-400">Use https://, http://, mailto: or a path such as /en/about.</p>
<div class="flex flex-wrap justify-end gap-3">
<button type="button" class="btn-danger-outline me-auto" data-link-remove>Remove link</button>
<button type="button" class="btn" data-close>Cancel</button>
<button class="btn-primary" value="ok">Apply</button>
</div>
</form>
</dialog>
