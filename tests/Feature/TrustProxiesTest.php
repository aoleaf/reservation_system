<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** ロードバランサーの後ろ（HTTPS はロードバランサーで終わり、アプリには HTTP で届く） */
class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7', 'HTTP_X_FORWARDED_PORT' => '443', 'HTTP_X_FORWARDED_HOST' => 'yoyaku.example.com'];

    public function test_お客様の_IP_と_HTTPS_を正しく受け取る(): void
    {
        Route::get('/_proxy_check', fn () => response()->json(['ip' => request()->ip(), 'secure' => request()->isSecure(), 'url' => url('/x')]));
        $this->withServerVariables(self::PROXY)->get('/_proxy_check')
            ->assertJson(['ip' => '203.0.113.7', 'secure' => true, 'url' => 'https://yoyaku.example.com/x']);
    }

    public function test_HTTPS_で作った署名付き_URL_が_ロードバランサー経由でも合う(): void
    {
        $r = Reservation::factory()->for(Slot::factory()->weekend()->create())->create();
        // 本番では「支払う」のリクエストもロードバランサー経由の HTTPS なので、https の URL に署名する
        URL::forceRootUrl('https://yoyaku.example.com');
        URL::forceScheme('https');
        $signed = URL::temporarySignedRoute('reserve.complete', now()->addHour(), ['code' => $r->code]);
        URL::forceRootUrl(null);
        URL::forceScheme(null);
        $this->assertStringStartsWith('https://', $signed);

        $path = parse_url($signed, PHP_URL_PATH).'?'.parse_url($signed, PHP_URL_QUERY);
        $this->withServerVariables(self::PROXY)->get($path)->assertInertia(fn (Assert $p) => $p->where('reservation.id', $r->code));
    }
}
