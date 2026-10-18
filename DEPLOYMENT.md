# Production deployment checks

Keep the server's existing `APP_KEY`. Do not copy a local `.env` to the server or run `php artisan key:generate` after data has been stored. `composer setup` now generates a key only when `.env` has no `APP_KEY`.

Set these values in the server's `.env` before caching configuration:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
SESSION_SECURE_COOKIE=true
```

Replace the example domain with the real HTTPS origin. Keep database and mail credentials only in the server environment; rotate any credential that has been shared outside the deployment environment. New uploads are limited to JPG and PNG files of at most 5 MB, 4000 pixels per side, and 8 megapixels total. Registration and supporting images are decoded and re-encoded before storage. Existing PDFs remain accessible to authorized users, but new PDFs cannot be uploaded.

For an existing installation, back up the database and stored documents, deploy the code and `composer.lock`, then run:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan config:cache
php artisan queue:restart
composer audit --locked --no-dev
```

Verify that the server has a running queue worker and that its HTTPS pages set the session cookie with `Secure`. Confirm that an allowed document uploads successfully and that an existing approved slip still verifies after the migration.

The slip-history migration reconstructs earlier slip details from the records available at migration time. These records are labeled as reconstructed on verification and printed pages because the exact original printed values cannot be recovered from data that was never versioned. Slips approved after this change capture their details at issuance.
