<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake('ja_JP')->name(),
            'phone' => fake('ja_JP')->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'active' => true,
        ];
    }
}
