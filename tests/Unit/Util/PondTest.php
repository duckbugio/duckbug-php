<?php

declare(strict_types=1);

namespace Unit\Util;

use DuckBug\Util\Pond;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @internal
 */
final class PondTest extends TestCase
{
    /** @var Pond */
    private $context;

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $this->context = Pond::ripple(['password', 'token']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret';
        $_SERVER['REMOTE_ADDR'] = '192.168.0.1';

        $_COOKIE = ['session' => 'abc123'];
        $_ENV = ['TOKEN' => 'secret_token', 'APP_ENV' => 'testing'];
        $_SESSION['user_id'] = 42;
        $_FILES = [
            'file' => [
                'name' => 'file.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/phpYzdqkD',
                'error' => 0,
                'size' => 123,
            ],
        ];
    }

    public function testGetMethod(): void
    {
        self::assertSame('POST', $this->context->getMethod());
    }

    public function testGetUrl(): void
    {
        self::assertSame('https://example.com/test', $this->context->getUrl());
    }

    public function testGetHeaders(): void
    {
        $headers = $this->context->getHeaders();
        self::assertIsArray($headers);
        self::assertArrayHasKey('Authorization', $headers);
    }

    public function testGetCookies(): void
    {
        $cookies = $this->context->getCookies();
        self::assertSame(['session' => 'abc123'], $cookies);
    }

    public function testGetSession(): void
    {
        self::assertSame(['user_id' => 42], $this->context->getSession());
    }

    public function testGetFiles(): void
    {
        self::assertArrayHasKey('file', $this->context->getFiles());
    }

    public function testGetEnv(): void
    {
        $env = $this->context->getEnv();
        self::assertSame(['TOKEN' => '***', 'APP_ENV' => 'testing'], $env);
    }

    public function testGetUserIp(): void
    {
        self::assertSame('192.168.0.1', $this->context->getUserIp());
    }

    public function testMaskSensitiveData(): void
    {
        $data = [
            'password' => 'secret',
            'token' => '123456',
            'other' => 'visible',
            'nested' => ['password' => 'secret'],
        ];

        $method = new ReflectionMethod(Pond::class, 'maskSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke($this->context, $data);

        self::assertSame('***', $result['password']);
        self::assertSame('***', $result['token']);
        self::assertSame('visible', $result['other']);
        self::assertSame('***', $result['nested']['password']);
    }

    public function testEmptyContext(): void
    {
        $_SERVER = [
            'REQUEST_TIME_FLOAT' => microtime(true),
            'SCRIPT_NAME' => '/index.php',
        ];
        $_COOKIE = [];
        $_SESSION = [];
        $_FILES = [];
        $_ENV = [];

        $context = Pond::ripple(['password', 'token']);

        self::assertNull($context->getHeaders());
        self::assertNull($context->getCookies());
        self::assertNull($context->getSession());
        self::assertNull($context->getFiles());
        self::assertNull($context->getEnv());
    }

    public function testDeepNestedSensitiveMasking(): void
    {
        $data = [
            'a' => [
                'b' => [
                    'password' => 'deep-secret',
                ],
            ],
        ];

        $method = new ReflectionMethod(Pond::class, 'maskSensitiveData');
        $method->setAccessible(true);
        $result = $method->invoke($this->context, $data);

        self::assertSame('***', $result['a']['b']['password']);
    }

    public function testNonSensitiveDataNotMasked(): void
    {
        $data = ['email' => 'test@example.com'];

        $method = new ReflectionMethod(Pond::class, 'maskSensitiveData');
        $method->setAccessible(true);
        $result = $method->invoke($this->context, $data);

        self::assertSame('test@example.com', $result['email']);
    }
}
