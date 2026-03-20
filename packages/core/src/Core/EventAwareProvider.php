<?php

declare(strict_types=1);

namespace DuckBug\Core;

interface EventAwareProvider
{
    public function captureEvent(Event $event): void;
}
