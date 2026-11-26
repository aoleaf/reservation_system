<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** スタッフのアカウントを作る（画面からの新規登録は閉じている） */
class CreateStaff extends Command
{
    protected $signature = 'staff:create {email} {--name=スタッフ} {--password= : 省略すると入力を求める}';

    protected $description = 'スタッフのアカウントを作る';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?? $this->secret('パスワード（8文字以上）');

        $v = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        User::query()->create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => UserRole::Staff]);
        $this->info("スタッフ {$email} を作りました");

        return self::SUCCESS;
    }
}
