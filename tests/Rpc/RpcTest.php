<?php

declare(strict_types=1);

use Microservices\Exceptions\ConfigurationException;
use Microservices\Exceptions\ServiceException;
use Microservices\Services\Rpc\LocalServices;
use Microservices\Tests\Fixtures\Billing\Contracts\BillingService;

it('refuses a method of a contract the service does not answer', function () {
    app(LocalServices::class)->call('orders', BillingService::class, 'invoiceFor', ['orderId' => 1]);
})->throws(ServiceException::class, 'Service [orders] answers no');

it('names the package of the http driver when it is not installed', function () {
    app(BillingService::class)->invoiceFor(7);
})->throws(ConfigurationException::class, 'composer require mk-josias/laravel-microservices-http-rpc');
