<?php

declare(strict_types=1);

namespace Drupal\Core\Site {
    final class Settings
    {
        /** @var array<string, mixed> */
        public static $values = [];

        /**
         * @param mixed $default
         * @return mixed
         */
        public static function get(string $key, $default = null)
        {
            return \array_key_exists($key, self::$values) ? self::$values[$key] : $default;
        }
    }
}

namespace Drupal\Core\Session {
    interface AccountInterface
    {
    }
}

namespace DuckBug\Tests\Unit {
    use Drupal\Core\Site\Settings;
    use DuckBug\Duck;
    use DuckBug\Integrations\Drupal\DrupalBootstrap;
    use DuckBug\Integrations\Drupal7\Drupal7Bootstrap;
    use DuckBug\Integrations\WordPress\WordPressBootstrap;
    use PHPUnit\Framework\TestCase;
    use ReflectionClass;
    use ReflectionException;

    /**
     * @internal
     */
    final class FrameworkBootstrapSmokeTest extends TestCase
    {
        /**
         * @throws ReflectionException
         */
        protected function tearDown(): void
        {
            $this->resetDuckState();
            Settings::$values = [];
            restore_error_handler();
            restore_exception_handler();
        }

        /**
         * @throws ReflectionException
         */
        public function testWordPressBootstrapBootsFromOverrides(): void
        {
            $this->resetDuckState();

            $duck = WordPressBootstrap::boot([
                'dsn' => 'https://duckbug.io',
                'service' => 'wordpress-app',
                'environment' => 'production',
                'release' => 'wp@1.2.3',
            ]);

            self::assertInstanceOf(Duck::class, $duck);
            self::assertSame('wordpress-app', $duck->getScope()->getService());
            self::assertSame('production', $duck->getScope()->getEnvironment());
            self::assertSame('wp@1.2.3', $duck->getScope()->getRelease());
            self::assertSame('duckbug-wordpress', $duck->getScope()->getSDK()['name']);
            self::assertSame('wordpress', $duck->getScope()->getRuntime()['framework']);
        }

        /**
         * @throws ReflectionException
         */
        public function testDrupalBootstrapBootsFromSettings(): void
        {
            $this->resetDuckState();
            Settings::$values['duckbug'] = [
                'dsn' => 'https://duckbug.io',
                'service' => 'drupal-app',
                'environment' => 'stage',
            ];

            $duck = DrupalBootstrap::boot();

            self::assertInstanceOf(Duck::class, $duck);
            self::assertSame('drupal-app', $duck->getScope()->getService());
            self::assertSame('stage', $duck->getScope()->getEnvironment());
            self::assertSame('duckbug-drupal', $duck->getScope()->getSDK()['name']);
            self::assertSame('drupal', $duck->getScope()->getRuntime()['framework']);
        }

        /**
         * @throws ReflectionException
         */
        public function testDrupal7BootstrapBootsFromOverrides(): void
        {
            $this->resetDuckState();

            $duck = Drupal7Bootstrap::boot([
                'dsn' => 'https://duckbug.io',
                'service' => 'drupal7-app',
                'environment' => 'legacy',
            ]);

            self::assertInstanceOf(Duck::class, $duck);
            self::assertSame('drupal7-app', $duck->getScope()->getService());
            self::assertSame('legacy', $duck->getScope()->getEnvironment());
            self::assertSame('duckbug-drupal7', $duck->getScope()->getSDK()['name']);
            self::assertSame('drupal7', $duck->getScope()->getRuntime()['framework']);
        }

        /**
         * @throws ReflectionException
         */
        private function resetDuckState(): void
        {
            $reflection = new ReflectionClass(Duck::class);

            foreach (['duck', 'pond'] as $propertyName) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue(null, null);
            }
        }
    }
}
