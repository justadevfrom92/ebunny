# Preview builder

Builds the single-file preview (the testing link) by crawling the running app as the sample
admins and customers. Brand text is swapped for the placeholder "Sample Energy" brand.

```
php artisan migrate:fresh --seed && php artisan cache:clear
php artisan serve --port=8127          # leave running
python3 tools/preview/build.py         # writes storage/app/preview/site-preview.html (git-ignored)
```

- `shell.html` – the preview page; `@@DATA@@` is replaced with the crawled pages and assets.
- `shim.txt` – script injected into every page so links, forms and sign-ins work offline.
- `components.json` – website component placeholders.
- Output is kept under 16 MB (the artifact limit) by dropping the deepest record pages first.
