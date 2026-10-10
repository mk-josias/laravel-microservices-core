<?php

declare(strict_types=1);

namespace Microservices\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Microservices\Config\Services;

/** The Composer package every service requires: under Foundation\{Service}\, what each one shares with the others. */
final class MakeFoundation extends Command
{
    protected $signature = 'foundation:make
        {path=../foundation : Directory of the shared package, relative to the application}
        {--package= : Its Composer name (default: {this application\'s vendor}/foundation)}';

    protected $description = 'Create the package the services share, or add this service to it.';

    public function handle(Services $config): int
    {
        $name = $config->getName();

        if ($name === null) {
            $this->components->error('Set microservices.name first: the folder of this service is named after it.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        $root = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
        $package = (string) ($this->option('package') ?: $this->defaultPackage());

        if (! is_file($root.'/composer.json')) {
            is_dir($root) || mkdir($root, 0755, true);
            file_put_contents($root.'/composer.json', json_encode([
                'name' => $package,
                'description' => 'What each service shares with the others: contracts, RPC clients, event payloads, copies.',
                'type' => 'library',
                'require' => ['mk-josias/laravel-microservices' => '^0.1'],
                'autoload' => ['psr-4' => ['Foundation\\' => 'src/']],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } else {
            $package = (string) (json_decode((string) file_get_contents($root.'/composer.json'), true)['name'] ?? $package);
        }

        $service = Str::studly($name);

        foreach (['Contracts', 'Services', 'Payloads', 'Shadows'] as $folder) {
            $directory = "{$root}/src/{$service}/{$folder}";
            is_dir($directory) || mkdir($directory, 0755, true);
            is_file($directory.'/.gitkeep') || touch($directory.'/.gitkeep');
        }

        $this->components->info("Foundation\\{$service}\\ is in {$path}/src/{$service}.");
        $this->line("  composer config repositories.foundation path {$path}");
        $this->line("  composer require {$package}:@dev");
        $this->line("  Each service calling {$name} declares it: '{$name}' => ['host' => …, 'namespace' => 'Foundation\\{$service}']");

        return self::SUCCESS;
    }

    private function defaultPackage(): string
    {
        $composer = json_decode((string) @file_get_contents(base_path('composer.json')), true);
        $vendor = is_array($composer) && is_string($composer['name'] ?? null) ? Str::before($composer['name'], '/') : 'app';

        return $vendor.'/foundation';
    }
}
