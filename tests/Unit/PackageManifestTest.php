<?php

declare(strict_types=1);

namespace DuckBug\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PackageManifestTest extends TestCase
{
    public function testRootPackageExposesSplitPackagesAndNewAdapters(): void
    {
        $root = \dirname(__DIR__, 2);
        $composer = $this->readJson($root . '/composer.json');

        self::assertSame('^1.0', $composer['require']['psr/http-server-middleware']);

        self::assertSame(
            [
                'duckbug/duckbug-core',
                'duckbug/duckbug-symfony',
                'duckbug/duckbug-laravel',
                'duckbug/duckbug-yii2',
                'duckbug/duckbug-slim4',
                'duckbug/duckbug-wordpress',
                'duckbug/duckbug-drupal',
                'duckbug/duckbug-drupal7',
            ],
            array_keys($composer['replace'])
        );

        self::assertSame(
            [
                'DuckBug\\' => 'src/',
                'DuckBug\\Integrations\\Yii2\\' => 'packages/yii2/src/',
                'DuckBug\\Integrations\\Slim4\\' => 'packages/slim4/src/',
                'DuckBug\\Integrations\\WordPress\\' => 'packages/wordpress/src/',
                'DuckBug\\Integrations\\Drupal\\' => 'packages/drupal/src/',
                'DuckBug\\Integrations\\Drupal7\\' => 'packages/drupal7/src/',
            ],
            $composer['autoload']['psr-4']
        );
    }

    public function testMirroredSourcesStayInSync(): void
    {
        $root = \dirname(__DIR__, 2);
        $mirrors = [
            'src/Duck.php' => 'packages/core/src/Duck.php',
            'src/Pond.php' => 'packages/core/src/Pond.php',
            'src/Core/Client.php' => 'packages/core/src/Core/Client.php',
            'src/Core/Event.php' => 'packages/core/src/Core/Event.php',
            'src/Core/ErrorEvent.php' => 'packages/core/src/Core/ErrorEvent.php',
            'src/Core/LogEvent.php' => 'packages/core/src/Core/LogEvent.php',
            'src/Core/TransactionEvent.php' => 'packages/core/src/Core/TransactionEvent.php',
            'src/Core/Span.php' => 'packages/core/src/Core/Span.php',
            'src/Core/Transaction.php' => 'packages/core/src/Core/Transaction.php',
            'src/Providers/DuckBugProvider.php' => 'packages/core/src/Providers/DuckBugProvider.php',
            'src/Providers/Psr3LoggerProvider.php' => 'packages/core/src/Providers/Psr3LoggerProvider.php',
            'src/HttpClient/HttpClient.php' => 'packages/core/src/HttpClient/HttpClient.php',
            'src/HttpClient/HttpClientInterface.php' => 'packages/core/src/HttpClient/HttpClientInterface.php',
            'src/HttpClient/TransportResult.php' => 'packages/core/src/HttpClient/TransportResult.php',
            'src/Integrations/ErrorHandlerIntegration.php' => 'packages/core/src/Integrations/ErrorHandlerIntegration.php',
            'src/Integrations/Psr15/DuckBugMiddleware.php' => 'packages/core/src/Integrations/Psr15/DuckBugMiddleware.php',
            'src/Monolog/DuckBugHandler.php' => 'packages/core/src/Monolog/DuckBugHandler.php',
            'src/Integrations/Symfony/DuckBugExceptionListener.php' => 'packages/symfony/src/DuckBugExceptionListener.php',
            'src/Integrations/Laravel/DuckBugServiceProvider.php' => 'packages/laravel/src/DuckBugServiceProvider.php',
        ];

        foreach ($mirrors as $rootFile => $packageFile) {
            self::assertFileExists($root . '/' . $rootFile);
            self::assertFileExists($root . '/' . $packageFile);
            self::assertSame(
                file_get_contents($root . '/' . $rootFile),
                file_get_contents($root . '/' . $packageFile),
                sprintf('Mirror drift detected between %s and %s', $rootFile, $packageFile)
            );
        }
    }

    public function testSplitPackagesDeclareExpectedManifestsAndEntrypoints(): void
    {
        $root = \dirname(__DIR__, 2);
        $packages = [
            'core' => [
                'name' => 'duckbug/duckbug-core',
                'autoloadPrefix' => 'DuckBug\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/Duck.php',
                    'src/Pond.php',
                    'src/Core/Client.php',
                    'src/Providers/DuckBugProvider.php',
                ],
            ],
            'symfony' => [
                'name' => 'duckbug/duckbug-symfony',
                'autoloadPrefix' => 'DuckBug\\Integrations\\Symfony\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/DuckBugExceptionListener.php',
                ],
            ],
            'laravel' => [
                'name' => 'duckbug/duckbug-laravel',
                'autoloadPrefix' => 'DuckBug\\Integrations\\Laravel\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/DuckBugServiceProvider.php',
                ],
            ],
            'yii2' => [
                'name' => 'duckbug/duckbug-yii2',
                'autoloadPrefix' => 'DuckBug\\Integrations\\Yii2\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/DuckBugContext.php',
                    'src/DuckBugTarget.php',
                    'src/DuckBugWebErrorHandler.php',
                    'src/DuckBugConsoleErrorHandler.php',
                ],
            ],
            'slim4' => [
                'name' => 'duckbug/duckbug-slim4',
                'autoloadPrefix' => 'DuckBug\\Integrations\\Slim4\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/DuckBugMiddleware.php',
                    'src/DuckBugErrorHandler.php',
                ],
            ],
            'wordpress' => [
                'name' => 'duckbug/duckbug-wordpress',
                'autoloadPrefix' => 'DuckBug\\Integrations\\WordPress\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/WordPressBootstrap.php',
                    'src/WordPressContext.php',
                    'src/WordPressHooks.php',
                    'src/WordPressPlugin.php',
                    'duckbug-wordpress.php',
                ],
            ],
            'drupal' => [
                'name' => 'duckbug/duckbug-drupal',
                'autoloadPrefix' => 'DuckBug\\Integrations\\Drupal\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/DrupalBootstrap.php',
                    'src/DrupalContext.php',
                    'src/DuckBugExceptionSubscriber.php',
                    'src/DuckBugLogger.php',
                    'duckbug.info.yml',
                    'duckbug.services.yml',
                    'duckbug.module',
                ],
            ],
            'drupal7' => [
                'name' => 'duckbug/duckbug-drupal7',
                'autoloadPrefix' => 'DuckBug\\Integrations\\Drupal7\\',
                'autoloadPath' => 'src/',
                'files' => [
                    'src/Drupal7Bootstrap.php',
                    'src/Drupal7Context.php',
                    'duckbug_drupal7.info',
                    'duckbug_drupal7.module',
                ],
            ],
        ];

        foreach ($packages as $directory => $package) {
            $composer = $this->readJson($root . '/packages/' . $directory . '/composer.json');

            self::assertSame($package['name'], $composer['name']);
            self::assertSame(
                [$package['autoloadPrefix'] => $package['autoloadPath']],
                $composer['autoload']['psr-4']
            );

            foreach ($package['files'] as $file) {
                self::assertFileExists($root . '/packages/' . $directory . '/' . $file);
            }
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertNotFalse($contents, sprintf('Failed to read %s', $path));

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);
        self::assertIsArray($decoded, sprintf('Failed to decode %s', $path));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
