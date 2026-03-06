# WordPress Settings API 仕様

## 1. 概要

WordPress の Settings API は、管理画面の設定ページを構築するための仕組みです。設定の登録、セクションとフィールドの定義、バリデーション、フォームのレンダリング、保存処理を統一的に管理します。Options API の上に構築されており、`register_setting()` で登録された設定は `update_option()` を通じて `wp_options` テーブルに保存されます。

Settings API は以下のグローバル変数で状態を管理します:

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_registered_settings` | `array` | `register_setting()` で登録された全設定のメタ情報 |
| `$wp_settings_sections` | `array` | `add_settings_section()` で登録されたセクション情報 |
| `$wp_settings_fields` | `array` | `add_settings_field()` で登録されたフィールド情報 |
| `$wp_settings_errors` | `array` | `add_settings_error()` で登録されたエラー・通知メッセージ |

Settings API の基本構造は以下の階層です:

```
設定ページ（page slug）
└── セクション（section）
    └── フィールド（field）
        └── 設定（option_name → wp_options テーブル）
```

## 2. データ構造

### `$wp_registered_settings`

`register_setting()` で登録された設定のメタ情報を格納します。

```php
$wp_registered_settings = [
    'my_option_name' => [
        'group'             => 'my_settings_group',   // 設定グループ名
        'type'              => 'string',              // データ型
        'description'       => '説明',                // 設定の説明
        'sanitize_callback' => callable|null,          // サニタイズコールバック
        'show_in_rest'      => false,                 // REST API で公開するか
        'default'           => mixed,                 // デフォルト値
    ],
];
```

### `$wp_settings_sections`

ページごとのセクション情報を格納します。

```php
$wp_settings_sections = [
    'my_page_slug' => [
        'section_id' => [
            'id'             => 'section_id',          // セクション ID
            'title'          => 'セクションタイトル',    // 表示タイトル
            'callback'       => callable|null,          // セクション説明のコールバック
            'before_section' => '',                    // セクション前の HTML（WordPress 6.2+）
            'after_section'  => '',                    // セクション後の HTML（WordPress 6.2+）
            'section_class'  => '',                    // セクションの CSS クラス（WordPress 6.2+）
        ],
    ],
];
```

### `$wp_settings_fields`

ページ・セクションごとのフィールド情報を格納します。

```php
$wp_settings_fields = [
    'my_page_slug' => [
        'section_id' => [
            'field_id' => [
                'id'       => 'field_id',              // フィールド ID
                'title'    => 'フィールドラベル',        // 表示ラベル
                'callback' => callable,                 // フィールドの HTML 出力コールバック
                'args'     => [],                      // コールバックに渡される引数
            ],
        ],
    ],
];
```

### `$wp_settings_errors`

設定保存時のエラーや通知メッセージを格納します。

```php
$wp_settings_errors = [
    [
        'setting' => 'my_option_name',   // 関連する設定名
        'code'    => 'error_code',       // エラーコード
        'message' => 'エラーメッセージ',   // 表示メッセージ
        'type'    => 'error',            // 'error', 'success', 'warning', 'info'
    ],
];
```

## 3. API リファレンス

### 設定登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_setting()` | `(string $option_group, string $option_name, array $args = []): void` | 設定を登録 |
| `unregister_setting()` | `(string $option_group, string $option_name, callable $deprecated = ''): void` | 設定の登録を解除 |
| `get_registered_settings()` | `(): array` | 登録済み設定の一覧を取得 |

### セクション API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_settings_section()` | `(string $id, string $title, callable $callback, string $page, array $args = []): void` | 設定セクションを追加 |

### フィールド API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_settings_field()` | `(string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = []): void` | 設定フィールドを追加 |

### レンダリング API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `settings_fields()` | `(string $option_group): void` | Nonce フィールドと hidden input を出力 |
| `do_settings_sections()` | `(string $page): void` | ページに属する全セクション・フィールドをレンダリング |
| `do_settings_fields()` | `(string $page, string $section): void` | セクションに属するフィールドをレンダリング |

### エラー API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_settings_error()` | `(string $setting, string $code, string $message, string $type = 'error'): void` | エラーまたは通知を追加 |
| `get_settings_errors()` | `(string $setting = '', bool $sanitize = false): array` | 設定エラーの一覧を取得 |
| `settings_errors()` | `(string $setting = '', bool $sanitize = false, bool $hide_on_update = false): void` | エラー・通知メッセージを HTML 出力 |

## 4. 実行フロー

### 設定の登録フロー

```
register_setting('my_group', 'my_option', [
    'type'              => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'default'           => '',
    'show_in_rest'      => true,
])
│
├── $wp_registered_settings['my_option'] にメタ情報を格納
│
├── sanitize_callback が指定されている場合
│   └── add_filter('sanitize_option_my_option', $sanitize_callback)
│
├── default が指定されている場合
│   └── add_filter('default_option_my_option', ...)  // get_option() のデフォルト値
│
└── show_in_rest が true の場合
    └── REST API 用の設定エンドポイントに登録
```

### `settings_fields()` の出力

```php
settings_fields('my_group');
```

出力される HTML:

```html
<input type="hidden" name="option_page" value="my_group" />
<input type="hidden" name="action" value="update" />
<!-- wp_nonce_field('my_group-options') の出力 -->
<input type="hidden" id="_wpnonce" name="_wpnonce" value="abc123..." />
<input type="hidden" name="_wp_http_referer" value="/wp-admin/..." />
```

### `do_settings_sections()` のレンダリングフロー

```
do_settings_sections('my_page')
│
├── $wp_settings_sections['my_page'] の各セクションに対して:
│   │
│   ├── $section['before_section'] があれば出力（WordPress 6.2+）
│   │
│   ├── $section['title'] が空でなければ <h2> で出力
│   │
│   ├── $section['callback'] があれば実行（セクション説明の出力）
│   │
│   ├── $wp_settings_fields['my_page'][$section['id']] にフィールドがあれば
│   │   ├── <table class="form-table" role="presentation"> 開始
│   │   ├── do_settings_fields('my_page', $section['id'])
│   │   │   └── 各フィールド:
│   │   │       ├── <tr> 開始
│   │   │       ├── <th scope="row"> でラベル出力
│   │   │       ├── <td> でコールバック実行（フィールド HTML 出力）
│   │   │       └── </tr> 終了
│   │   └── </table> 終了
│   │
│   └── $section['after_section'] があれば出力
│
└── 完了
```

### 設定保存フロー（`options.php`）

WordPress の設定保存は `wp-admin/options.php` で処理されます:

```
POST /wp-admin/options.php
│
├── 1. Nonce 検証
│   └── check_admin_referer($option_page . '-options')
│
├── 2. 権限チェック
│   └── current_user_can('manage_options')
│       または option_page_capability_{$option_page} フィルターで変更可能
│
├── 3. $option_page のホワイトリストチェック
│   ├── $allowedoptions = apply_filters('allowed_options', $allowedoptions)
│   │   ※ register_setting() 時に自動的に追加される
│   └── ホワイトリストにない場合 → wp_die('Error: Options page not found.')
│
├── 4. 各オプションに対して:
│   │
│   ├── $_POST[$option] から値を取得
│   │
│   ├── sanitize_option($option, $value) を呼び出し
│   │   └── sanitize_option_{$option} フィルターが適用される
│   │       ※ register_setting() の sanitize_callback が実行される
│   │
│   └── update_option($option, $value)
│
├── 5. エラーチェック
│   ├── get_settings_errors() でエラーを確認
│   └── エラーがあれば Transient に保存してリダイレクト
│
└── 6. 設定ページにリダイレクト（?settings-updated=true）
```

### エラーの表示フロー

```
settings_errors('my_option')
│
├── get_settings_errors('my_option', $sanitize)
│   ├── $sanitize が true の場合
│   │   └── sanitize_option('my_option', get_option('my_option'))
│   │       └── sanitize_callback 内で add_settings_error() が呼ばれる可能性
│   │
│   ├── グローバル $wp_settings_errors から設定名でフィルタリング
│   │
│   └── Transient 'settings_errors' からもエラーを取得（リダイレクト経由）
│
├── 各エラーに対して:
│   └── <div id="setting-error-{$code}" class="notice notice-{$type} is-dismissible">
│       <p><strong>{$message}</strong></p>
│       </div>
│
└── 完了
```

## 5. 設定ページの構築パターン

### 典型的な設定ページの実装

```php
// 1. 設定の登録（admin_init フックで実行）
add_action('admin_init', function () {
    // 設定を登録
    register_setting('my_plugin_settings', 'my_plugin_option', [
        'type'              => 'array',
        'sanitize_callback' => 'my_plugin_sanitize',
        'default'           => ['field1' => '', 'field2' => 0],
    ]);

    // セクションを追加
    add_settings_section(
        'my_section',
        '基本設定',
        function () { echo '<p>基本的な設定項目です。</p>'; },
        'my_plugin_page'
    );

    // フィールドを追加
    add_settings_field(
        'my_field1',
        'フィールド 1',
        function () {
            $options = get_option('my_plugin_option');
            echo '<input type="text" name="my_plugin_option[field1]" value="' .
                 esc_attr($options['field1'] ?? '') . '" />';
        },
        'my_plugin_page',
        'my_section'
    );
});

// 2. メニューページの追加
add_action('admin_menu', function () {
    add_options_page(
        'My Plugin Settings',
        'My Plugin',
        'manage_options',
        'my_plugin_page',
        'my_plugin_render_settings_page'
    );
});

// 3. 設定ページのレンダリング
function my_plugin_render_settings_page(): void {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('my_plugin_settings');
    do_settings_sections('my_plugin_page');
    submit_button();
    echo '</form>';
    echo '</div>';
}
```

### `$args` パラメータ（`add_settings_field`）

`add_settings_field()` の `$args` パラメータはコールバックに渡されます。WordPress はいくつかの特別なキーを認識します:

| キー | 型 | 説明 |
|---|---|---|
| `label_for` | `string` | `<label>` タグの `for` 属性に使用される ID |
| `class` | `string` | `<tr>` タグに追加される CSS クラス |

```php
add_settings_field(
    'my_field',
    'My Field',
    'render_field',
    'my_page',
    'my_section',
    [
        'label_for' => 'my-field-id',
        'class'     => 'my-custom-class',
    ]
);
```

出力される HTML:

```html
<tr class="my-custom-class">
    <th scope="row"><label for="my-field-id">My Field</label></th>
    <td><!-- render_field() の出力 --></td>
</tr>
```

## 6. ホワイトリスト機構

### `allowed_options` フィルター

`options.php` は登録されていないオプションの保存を拒否します。`register_setting()` を呼ぶと、そのオプション名が自動的にホワイトリストに追加されます。

```php
// register_setting() 内部で実行される処理:
add_filter('allowed_options', function ($allowed) use ($option_group, $option_name) {
    $allowed[$option_group][] = $option_name;
    return $allowed;
});
```

ホワイトリストにないオプションを保存しようとすると、`options.php` は以下のエラーで中断します:

```
Error: Options page not found in the allowed options list.
```

### `option_page_capability_{$option_page}` フィルター

デフォルトでは設定ページへのアクセスには `manage_options` 権限が必要です。このフィルターで権限を変更できます:

```php
add_filter('option_page_capability_my_plugin_settings', function () {
    return 'edit_posts';  // より低い権限を許可
});
```

## 7. REST API との連携

`register_setting()` で `show_in_rest` を有効にすると、`/wp/v2/settings` エンドポイントで設定値の取得・更新が可能になります。

```php
register_setting('my_group', 'my_option', [
    'type'         => 'object',
    'show_in_rest' => [
        'schema' => [
            'type'       => 'object',
            'properties' => [
                'field1' => ['type' => 'string'],
                'field2' => ['type' => 'integer'],
            ],
        ],
    ],
]);
```

### REST API エンドポイント

| メソッド | エンドポイント | 説明 |
|---|---|---|
| `GET` | `/wp/v2/settings` | 全登録設定を取得 |
| `POST`/`PUT`/`PATCH` | `/wp/v2/settings` | 設定を更新 |

REST API 経由での更新でも `sanitize_callback` が適用されます。

## 8. フック一覧

### Filter

| フック名 | パラメータ | 説明 |
|---|---|---|
| `sanitize_option_{$option}` | `(mixed $value, string $option, mixed $original_value)` | オプション値のサニタイズ（`register_setting()` の `sanitize_callback` がここに登録される） |
| `allowed_options` | `(array $allowed_options)` | 保存可能なオプションのホワイトリスト |
| `option_page_capability_{$option_page}` | `(string $capability)` | 設定ページに必要な権限を変更 |
| `default_option_{$option}` | `(mixed $default, string $option, bool $passed_default)` | `register_setting()` の `default` がここに登録される |

### Action

| フック名 | パラメータ | 説明 |
|---|---|---|
| `register_setting` | `(string $option_group, string $option_name, array $args)` | 設定登録時 |
| `unregister_setting` | `(string $option_group, string $option_name)` | 設定登録解除時 |
| `update_option_{$option}` | `(mixed $old_value, mixed $value, string $option)` | 設定更新時（Options API のフック） |
| `updated_option` | `(string $option, mixed $old_value, mixed $value)` | 設定更新後（Options API のフック） |

### Settings API 固有の処理タイミング

| タイミング | 推奨フック | 説明 |
|---|---|---|
| 設定の登録 | `admin_init` | `register_setting()`, `add_settings_section()`, `add_settings_field()` |
| メニューの追加 | `admin_menu` | `add_options_page()`, `add_menu_page()` |
| エラーの追加 | `sanitize_callback` 内 | `add_settings_error()` |
| エラーの表示 | 設定ページ出力時 | `settings_errors()` |
