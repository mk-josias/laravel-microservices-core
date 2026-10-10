<?php

declare(strict_types=1);

namespace Microservices\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Microservices\Providers\MicroservicesServiceProvider;
use Microservices\Testing\InteractsWithServices;
use Microservices\Tests\Fixtures\App\Models\CustomerShadow;
use Microservices\Tests\Fixtures\App\Providers\OrdersServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/** The `orders` application, on its own: it answers OrdersService and calls `billing`, which runs elsewhere. */
abstract class TestCase extends Orchestra
{
    use InteractsWithServices;
    use RefreshDatabase;

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [MicroservicesServiceProvider::class, OrdersServiceProvider::class];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        $app->useAppPath(__DIR__.'/Fixtures/App');

        $config = $app->make(Repository::class);

        $config->set('database.default', 'testing');
        $config->set('microservices.name', 'orders');
        $config->set('microservices.services', [
            'billing' => ['host' => 'http://billing.test', 'namespace' => 'Microservices\Tests\Fixtures\Billing'],
        ]);
        $config->set('microservices.shadows', [CustomerShadow::class]);
        $config->set('microservices.rpc.secret', 'test-secret');
        $config->set('microservices.events.streams.default.driver', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }
}
