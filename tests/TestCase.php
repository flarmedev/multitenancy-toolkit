<?php

namespace Tests;

use Flarme\MultitenancyToolkit\MultitenancyToolkitProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Multitenancy\MultitenancyServiceProvider;
use Tests\Feature\Fixtures\FixtureServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function multitenancyToolkitConfig(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(MultitenancyServiceProvider::class);

        foreach ($this->multitenancyToolkitConfig() as $key => $value) {
            config()->set($key, $value);
        }

        $this->app->register(MultitenancyToolkitProvider::class);
        $this->app->register(FixtureServiceProvider::class);
    }
}
