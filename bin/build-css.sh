#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
tailwindcss -i src/app.css -o public/assets/style.css --minify
