<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // テストは Vite のビルド結果（manifest）に頼らない（新しいページをビルドする前でも通るように）
        $this->withoutVite();
    }
}
