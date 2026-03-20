<?php

declare(strict_types=1);

namespace DuckBug\Integrations\Yii2;

use Yii;

final class DuckBugContext
{
    /**
     * @return array<string, mixed>
     */
    public static function build(?string $category = null): array
    {
        $context = [
            'dTags' => self::buildTags($category),
            'extra' => [
                'yii2' => self::buildFrameworkContext($category),
            ],
        ];

        return self::stripEmptyValues($context);
    }

    /**
     * @return string[]
     */
    private static function buildTags(?string $category): array
    {
        $tags = ['framework:yii2'];

        if ($category !== null && trim($category) !== '') {
            $tags[] = 'category:' . trim($category);
        }

        return $tags;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildFrameworkContext(?string $category): array
    {
        $context = [];

        if ($category !== null && trim($category) !== '') {
            $context['category'] = trim($category);
        }

        if (!class_exists('Yii', false) || Yii::$app === null) {
            return $context;
        }

        $app = Yii::$app;

        if (isset($app->requestedRoute) && \is_string($app->requestedRoute) && trim($app->requestedRoute) !== '') {
            $context['route'] = trim($app->requestedRoute);
        }

        if (isset($app->controller) && $app->controller !== null) {
            $controller = $app->controller;

            if (isset($controller->id) && is_scalar($controller->id)) {
                $context['controller'] = trim((string)$controller->id);
            }

            if (isset($controller->action) && $controller->action !== null && isset($controller->action->id) && is_scalar($controller->action->id)) {
                $context['action'] = trim((string)$controller->action->id);
            }
        }

        if (isset($app->user) && $app->user !== null) {
            $user = $app->user;
            $userContext = [];

            if (isset($user->isGuest) && $user->isGuest === false) {
                if (method_exists($user, 'getId')) {
                    $id = $user->getId();
                    if (is_scalar($id) && trim((string)$id) !== '') {
                        $userContext['id'] = trim((string)$id);
                    }
                }

                if (isset($user->identity) && \is_object($user->identity)) {
                    $identity = $user->identity;

                    if (isset($identity->username) && is_scalar($identity->username) && trim((string)$identity->username) !== '') {
                        $userContext['username'] = trim((string)$identity->username);
                    }

                    if (isset($identity->email) && is_scalar($identity->email) && trim((string)$identity->email) !== '') {
                        $userContext['email'] = trim((string)$identity->email);
                    }
                }
            }

            if (!empty($userContext)) {
                $context['user'] = $userContext;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private static function stripEmptyValues(array $context): array
    {
        foreach ($context as $key => $value) {
            if (\is_array($value)) {
                $value = self::stripEmptyValues($value);
            }

            if ($value === null || $value === [] || $value === '') {
                unset($context[$key]);
                continue;
            }

            $context[$key] = $value;
        }

        return $context;
    }
}
