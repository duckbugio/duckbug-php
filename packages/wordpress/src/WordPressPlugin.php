<?php

declare(strict_types=1);

namespace DuckBug\Integrations\WordPress;

final class WordPressPlugin
{
    /** @var bool */
    private static $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (\function_exists('add_action')) {
            add_action('plugins_loaded', [self::class, 'boot'], 5);
            return;
        }

        self::boot();
    }

    public static function boot(): void
    {
        WordPressBootstrap::registerHooks();
    }
}
