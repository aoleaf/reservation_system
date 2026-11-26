<?php

// 入力チェックの文言（画面ごとに文言を決めていないところで使う。スターターキットのログイン・パスワードの画面など）
return [
    'required' => ':attribute を入れてください。',
    'email' => ':attribute の形が正しくありません。',
    'string' => ':attribute は文字で入れてください。',
    'confirmed' => ':attribute が確認の入力と一致しません。',
    'lowercase' => ':attribute は小文字で入れてください。',
    'current_password' => 'パスワードが違います。',
    'min' => ['string' => ':attribute は :min 文字以上で入れてください。'],
    'max' => ['string' => ':attribute は :max 文字以内で入れてください。'],
    'unique' => 'その :attribute は、もう使われています。',
    'password' => [
        'letters' => ':attribute には英字を入れてください。',
        'mixed' => ':attribute には大文字と小文字の両方を入れてください。',
        'numbers' => ':attribute には数字を入れてください。',
        'symbols' => ':attribute には記号を入れてください。',
        'uncompromised' => 'その :attribute は漏れたことがあるため使えません。別のものにしてください。',
    ],
    'attributes' => [
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'name' => 'お名前',
    ],
];
