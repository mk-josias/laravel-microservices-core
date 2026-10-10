<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/microservices-foundation-'.uniqid();
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('creates the shared package with a folder for this service', function () {
    $this->artisan("foundation:make {$this->root} --package=acme/foundation")->assertSuccessful();

    $composer = json_decode((string) file_get_contents($this->root.'/composer.json'), true);

    expect($composer['name'])->toBe('acme/foundation')
        ->and($composer['autoload']['psr-4'])->toBe(['Foundation\\' => 'src/'])
        ->and($this->root.'/src/Orders/Contracts')->toBeDirectory()
        ->and($this->root.'/src/Orders/Shadows')->toBeDirectory();
});

it('adds a service to an existing package without touching its composer.json', function () {
    File::ensureDirectoryExists($this->root);
    file_put_contents($this->root.'/composer.json', '{"name": "acme/shared"}');

    $this->artisan("foundation:make {$this->root}")
        ->expectsOutputToContain('composer require acme/shared:@dev')
        ->assertSuccessful();

    expect(file_get_contents($this->root.'/composer.json'))->toBe('{"name": "acme/shared"}')
        ->and($this->root.'/src/Orders/Services')->toBeDirectory();
});

it('refuses to run before the service is named', function () {
    config()->set('microservices.name', null);

    $this->artisan("foundation:make {$this->root}")->assertFailed();

    expect($this->root)->not->toBeDirectory();
});
