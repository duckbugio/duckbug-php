# DuckBug Drupal

Drupal 9/10/11 module integration for DuckBug.

## Install

```bash
composer require duckbug/duckbug-drupal
```

## settings.php

```php
$settings['duckbug'] = [
    'dsn' => '__PUBLIC_DSN__',
    'environment' => 'production',
    'service' => 'drupal',
];
```

Enable the `duckbug` module to register the logger bridge and exception subscriber.
