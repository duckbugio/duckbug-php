<?php

declare(strict_types=1);

namespace DuckBug\Core;

interface FlushableProvider
{
    public function flush(): void;
}
