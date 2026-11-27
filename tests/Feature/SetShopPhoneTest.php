<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetShopPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_お店の電話番号を変える_形が違えば変えない(): void
    {
        $this->artisan('settings:shop-phone', ['phone' => '０３－１２３４－５６７８'])->expectsOutputToContain('03-1234-5678')->assertSuccessful();
        $this->assertSame('03-1234-5678', Setting::current()->shop_phone);
        $this->artisan('settings:shop-phone', ['phone' => '1234'])->assertFailed();
        $this->assertSame('03-1234-5678', Setting::current()->shop_phone);
    }
}
