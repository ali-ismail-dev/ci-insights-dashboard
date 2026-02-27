<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
{
    parent::setUp();
    
    // Senior Move: Ensure search indexing is disabled in CI
    config(['scout.driver' => null]);
}

}
