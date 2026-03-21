<?php

declare(strict_types=1);

namespace DuckBug\Core;

interface Provider
{
    public function captureEvent(Event $event): void;
}
