<?php

declare(strict_types=1);

namespace DuckBug\HttpClient;

interface HttpClientInterface
{
    public function send(string $dsn, string $type, array $data): int;
}
