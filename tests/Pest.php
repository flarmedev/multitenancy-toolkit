<?php

require_once __DIR__ . '/Feature/Fixtures/helpers.php';

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Impersonation\ImpersonationEnabledTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(ImpersonationEnabledTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Impersonation');
