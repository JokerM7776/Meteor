#!/bin/sh
# Two minified stylesheets: public pages load only public.css, admin pages only admin.css.
set -eu
cd "$(dirname "$0")/.."
tailwindcss -i src/public.css -o public/assets/public.css --minify
tailwindcss -i src/admin.css -o public/assets/admin.css --minify
