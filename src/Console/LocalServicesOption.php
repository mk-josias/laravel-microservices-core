<?php

declare(strict_types=1);

namespace Microservices\Console;

use Illuminate\Container\Container;
use Microservices\Contracts\Colocation;

/** The services a command works on: those --service names, else the one running, else every local one. */
trait LocalServicesOption
{
    /** @return list<string> */
    protected function selectedServices(): array
    {
        $named = array_values(array_map(strval(...), (array) $this->option('service')));

        if ($named !== []) {
            return $named;
        }

        $colocation = Container::getInstance()->make(Colocation::class);
        $current = $colocation->current();

        return $current !== null ? [$current] : $colocation->local();
    }
}
