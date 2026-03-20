<?php
/**
 * Plugin Name: DuckBug for WordPress
 * Plugin URI: https://duckbug.io
 * Description: DuckBug error monitoring, logging and observability hooks for WordPress.
 * Version: 0.1.1-dev
 * Author: DuckBug
 * Author URI: https://duckbug.io
 * Requires PHP: 7.1
 * License: MIT.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$autoloadCandidates = [
    __DIR__ . '/vendor/autoload.php',
    ABSPATH . 'vendor/autoload.php',
];

$autoloaded = false;

foreach ($autoloadCandidates as $autoloadFile) {
    if (is_file($autoloadFile)) {
        require_once $autoloadFile;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded || !class_exists(\DuckBug\Integrations\WordPress\WordPressPlugin::class)) {
    return;
}

\DuckBug\Integrations\WordPress\WordPressPlugin::load();
