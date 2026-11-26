<?php

namespace Tests\Feature\Line;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Line\LineMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** 送り先のグループの決め方：招待されたグループを候補にし、スタッフが設定画面で決める */
class LineSetupTest extends TestCase
{
    use RefreshDatabase;

    private FakeLine $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->line = new FakeLine; // 署名は 'ok' だけを正しいとする
        $this->app->instance(LineMessenger::class, $this->line);
    }

    private function webhook(array $events, string $sig = 'ok'): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/line/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LINE_SIGNATURE' => $sig], json_encode(['events' => $events]));
    }

    public function test_署名が合わない通知は_400_で何もしない(): void
    {
        $this->webhook([['type' => 'join', 'source' => ['type' => 'group', 'groupId' => 'Cevil']]], 'bad')->assertStatus(400);
        $this->assertNull(Setting::current()->line_group_candidate_id);
    }

    public function test_招待されると候補になり_スタッフが決めるまでは送り先にしない(): void
    {
        $this->webhook([['type' => 'join', 'source' => ['type' => 'group', 'groupId' => 'C1234567890abcdef']]])->assertOk();
        $s = Setting::current();
        $this->assertSame('C1234567890abcdef', $s->line_group_candidate_id);
        $this->assertNull($s->line_group_id);

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->get('/admin/settings')->assertInertia(fn (Assert $p) => $p
            ->where('line.group', null)->where('line.candidate.id', 'C1234…cdef')->where('line.digestAt', '08:00')->where('line.configured', false)
        );
        $this->actingAs($staff)->post('/admin/settings/line/group')->assertSessionHasNoErrors();
        $this->assertSame('C1234567890abcdef', Setting::current()->line_group_id);
        $this->assertNull(Setting::current()->line_group_candidate_id);

        $this->actingAs($staff)->post('/admin/settings/line/test')->assertSessionHas('line_notice');
        $this->assertSame('C1234567890abcdef', $this->line->sent[0]['to']);
    }

    public function test_グループから外されたら送り先から外す_個人とのやりとりは無視(): void
    {
        Setting::current()->update(['line_group_id' => 'Cshop']);
        $this->webhook([['type' => 'message', 'source' => ['type' => 'user', 'userId' => 'U1']], ['type' => 'leave', 'source' => ['type' => 'group', 'groupId' => 'Cother']]])->assertOk();
        $this->assertSame('Cshop', Setting::current()->line_group_id);
        $this->webhook([['type' => 'leave', 'source' => ['type' => 'group', 'groupId' => 'Cshop']]])->assertOk();
        $this->assertNull(Setting::current()->line_group_id);
    }

    public function test_候補が無いとき_送り先が無いときはエラー_スタッフだけ(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->post('/admin/settings/line/group')->assertSessionHasErrors('line');
        $this->actingAs($staff)->post('/admin/settings/line/test')->assertSessionHasErrors('line');
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))->post('/admin/settings/line/test')->assertForbidden();
    }
}
