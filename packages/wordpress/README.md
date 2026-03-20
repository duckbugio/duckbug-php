# DuckBug WordPress

WordPress bootstrap, platform hooks and standalone plugin delivery for DuckBug.

## Install

```bash
composer require duckbug/duckbug-wordpress
```

## Bootstrap

```php
define('DUCKBUG_WORDPRESS_DSN', '__PUBLIC_DSN__');
define('DUCKBUG_WORDPRESS_ENVIRONMENT', 'production');

\DuckBug\Integrations\WordPress\WordPressBootstrap::registerHooks();
```

You can also use the `duckbug_wordpress_config` filter or the standalone plugin artifact built from this package.
