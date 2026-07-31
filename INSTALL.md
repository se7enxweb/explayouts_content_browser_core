# Installing explayouts_content_browser_core

## Requirements

- Exponential Legacy / Exponential 6
- PHP 8.1, 8.2, 8.3 or 8.4

## Dependencies

- `explayouts_content_browser` — provides the `expLayoutsContentBrowserItem` value object returned by the backend. Activate it first.

## Steps

1. Place the extension in `extension/explayouts_content_browser_core`.

2. Activate it (after its dependency) in `settings/override/site.ini.append.php`:

   ```ini
   [ExtensionSettings]
   ActiveExtensions[]=explayouts_content_browser
   ActiveExtensions[]=explayouts_content_browser_core
   ```

   To activate it for a single siteaccess only, use `ActiveAccessExtensions[]` in `settings/siteaccess/<name>/site.ini.append.php` instead.

3. Regenerate autoloads:

   ```bash
   php bin/php/ezpgenerateautoloads.php -e
   ```

4. Clear caches:

   ```bash
   php bin/php/ezcache.php --clear-all --purge --allow-root-user
   ```
