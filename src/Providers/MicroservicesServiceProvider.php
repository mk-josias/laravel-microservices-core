<?php

declare(strict_types=1);

namespace Microservices\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Microservices\Console\Commands\AnnounceShadows;
use Microservices\Console\Commands\ConsumeEvents;
use Microservices\Console\Commands\ExportEvents;
use Microservices\Console\Commands\ImportEvents;
use Microservices\Console\Commands\MakeFoundation;
use Microservices\Console\Commands\PruneConsumptions;
use Microservices\Console\Commands\PublishEvents;
use Microservices\Console\Commands\RepublishEvents;
use Microservices\Console\Commands\TrimEvents;
use Microservices\Console\Commands\WantShadows;
use Microservices\Contracts\Colocation;
use Microservices\Contracts\Rpc\Transport as RpcTransport;
use Microservices\Contracts\Stream\Bus;
use Microservices\Contracts\Stream\Transport;
use Microservices\Services\Rpc\LocalServices;
use Microservices\Services\Rpc\RpcServices;
use Microservices\Services\Rpc\TransportManager as RpcTransportManager;
use Microservices\Services\Shadows\ShadowRegistry;
use Microservices\Services\SingleService;
use Microservices\Services\Stream\Emitter;
use Microservices\Services\Stream\Outbox\ArchiveStores;
use Microservices\Services\Stream\PayloadVersions;
use Microservices\Services\Stream\TransportManager;

final class MicroservicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/microservices.php', 'microservices');
        $this->mergeSections(['rpc', 'events']);

        // If: a package running several services in one process binds its own, whichever provider registers first.
        $this->app->bindIf(Colocation::class, SingleService::class);
        $this->app->singletonIf(ShadowRegistry::class);
        $this->app->bindIf(RpcTransport::class, RpcTransportManager::class);

        $this->app->singleton(TransportManager::class);
        $this->app->singleton(ArchiveStores::class);
        $this->app->singleton(PayloadVersions::class);
        $this->app->singleton(RpcTransportManager::class);
        $this->app->singleton(RpcServices::class);
        $this->app->singleton(LocalServices::class);

        $this->app->singleton(Bus::class, Emitter::class);

        // Injecting the contract yields the default stream; the manager stays the seam
        // where a consumer registers its own driver with extend().
        $this->app->bind(
            Transport::class,
            static fn (Application $app): Transport => $app->make(TransportManager::class)->stream(),
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/microservices.php' => config_path('microservices.php'),
        ], 'microservices-config');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishEvents::class, RepublishEvents::class, ConsumeEvents::class, TrimEvents::class, PruneConsumptions::class,
                ExportEvents::class, ImportEvents::class, AnnounceShadows::class, WantShadows::class, MakeFoundation::class,
            ]);
        }
    }

    /**
     * An application's `'events' => ['listen' => …]` keeps the shipped streams and tables.
     *
     * @param  list<string>  $sections
     */
    private function mergeSections(array $sections): void
    {
        if ($this->app instanceof \Illuminate\Foundation\Application && $this->app->configurationIsCached()) {
            return;
        }

        $defaults = require __DIR__.'/../../config/microservices.php';
        $config = $this->app->make('config');

        foreach ($sections as $section) {
            $config->set("microservices.{$section}", array_replace($defaults[$section], (array) $config->get("microservices.{$section}", [])));
        }
    }
}
