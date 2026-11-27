<?php

namespace App\Rules;

use App\Support\ContactFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** 電話番号の形（0 で始まる 10〜11 桁。ハイフン・括弧は可）。入力は先に ContactFormat::normalizePhone でそろえておく */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ContactFormat::isPhone($value)) {
            $fail(ContactFormat::PHONE_MESSAGE);
        }
    }
}
