<?php

namespace App\Listener;

use App\Bootstrap\LoadFile;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;

class LoadFileListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            BootApplication::class,
        ];
    }

    public function process(object $event): void
    {
        LoadFile::load();
    }
}