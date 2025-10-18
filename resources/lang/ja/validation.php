<?php
return [
    'required' => ':attribute は必須です。',
    'email'    => ':attribute は有効なメールアドレス形式で入力してください。',
    'unique'   => ':attribute は既に使われています。',
    'max'      => [
        'string' => ':attribute は :max 文字以内で入力してください。',
    ],
    'min'      => [
        'string' => ':attribute は :min 文字以上で入力してください。',
    ],
    'string'   => ':attribute は文字列でなければなりません。',

    'attributes' => [
        'email' => 'メールアドレス',
        'name'  => '名前',
        'password' => 'パスワード',
    ],
];
