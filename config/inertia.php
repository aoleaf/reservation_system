<?php

// Inertia の設定のうち、変えるところだけ（ほかはパッケージの初期値のまま）。
return [
    // テストでページのファイルがあるか確かめるとき、このプロジェクトの置き場所（小文字の pages）を見る
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/pages')],
        'page_extensions' => ['js', 'jsx', 'ts', 'tsx'],
    ],
];
