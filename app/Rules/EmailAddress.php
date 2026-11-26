<?php

namespace App\Rules;

use App\Support\ContactFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** メールアドレスの形（名前@ドメイン.xx）。入力は先に ContactFormat::normalizeEmail でそろえておく */
class EmailAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ContactFormat::isEmail($value)) {
            $fail(ContactFormat::EMAIL_MESSAGE);
        }
    }
}
