<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_スタッフは管理画面へ_生徒さんはマイページへ()
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff]))->get('/dashboard')->assertRedirect('/admin');
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))->get('/dashboard')->assertRedirect('/member');
    }

    public function test_入口はお客様の予約のページ()
    {
        $this->get('/')->assertRedirect('/reserve');
    }
}
