# DuckBug Drupal 7

Drupal 7 legacy module integration for DuckBug.

## Install

```bash
composer require duckbug/duckbug-drupal7
```

## settings.php

```php
$conf['duckbug_dsn'] = '__PUBLIC_DSN__';
$conf['duckbug_environment'] = 'production';
$conf['duckbug_service'] = 'drupal7';
```

Enable the `duckbug_drupal7` module to register runtime handlers and forward `watchdog()` events to DuckBug.
