<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ログインしていないとログイン画面へ(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_スタッフでない人は入れない(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))->get('/admin')->assertForbidden();
    }

    public function test_スタッフは入れる(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get('/admin')->assertOk();
    }

    public function test_スタッフはログインすると管理画面へ(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/admin');
    }

    public function test_新規登録の画面は無い(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_staff_create_でスタッフを作れる(): void
    {
        $this->artisan('staff:create', ['email' => 'a@example.com', '--password' => 'longpassword'])->assertSuccessful();
        $this->assertSame(UserRole::Staff, User::query()->where('email', 'a@example.com')->first()->role);
        $this->artisan('staff:create', ['email' => 'a@example.com', '--password' => 'longpassword'])->assertFailed(); // 同じメールは不可
        $this->artisan('staff:create', ['email' => 'b@example.com', '--password' => 'short'])->assertFailed(); // 8文字未満は不可
    }
}
