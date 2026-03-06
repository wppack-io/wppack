# WordPress ブロックエディタ API 仕様

## 1. 概要

WordPress のブロックエディタ（Gutenberg）API は、コンテンツをブロック単位で編集・管理する仕組みです。ブロックは HTML コメントのデリミタで区切られた構造化コンテンツとして保存され、サーバーサイドではブロックタイプの登録、パース、レンダリングを行います。

### 主要コンポーネント

| コンポーネント | クラス | 説明 |
|---|---|---|
| ブロックタイプレジストリ | `WP_Block_Type_Registry` | ブロックタイプの登録・管理（Singleton） |
| ブロックタイプ | `WP_Block_Type` | 個々のブロックタイプの定義 |
| ブロックパーサー | `WP_Block_Parser` | 投稿コンテンツをブロック構造にパース |
| ブロック | `WP_Block` | パース済みブロックのレンダリング用オブジェクト |
| ブロックパターンレジストリ | `WP_Block_Patterns_Registry` | ブロックパターンの登録・管理 |
| ブロックカテゴリ | — | ブロックのカテゴリ分類 |
| ブロックスタイル | `WP_Block_Styles_Registry` | ブロックスタイルバリエーションの管理 |
| ブロックテンプレート | — | 投稿タイプごとのデフォルトブロック構成 |

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$post` | `WP_Post` | 投稿コンテンツにブロックデータが格納 |

## 2. データ構造

### ブロックの保存形式（post_content）

ブロックは HTML コメントのデリミタで構造化されます:

```html
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Hello World</p>
<!-- /wp:paragraph -->

<!-- wp:image {"id":123,"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
    <img src="https://example.com/image.jpg" alt="" class="wp-image-123"/>
</figure>
<!-- /wp:image -->

<!-- wp:columns -->
<div class="wp-block-columns">
    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:paragraph -->
        <p>Column 1</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:paragraph -->
        <p>Column 2</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
</div>
<!-- /wp:columns -->
```

### ブロックデリミタの構文

```
開始タグ:  <!-- wp:{namespace/}blockName {JSON attributes} -->
終了タグ:  <!-- /wp:{namespace/}blockName -->
自己閉じ:  <!-- wp:{namespace/}blockName {JSON attributes} /-->
```

- コア名前空間（`core/`）は省略可能: `<!-- wp:paragraph -->` = `<!-- wp:core/paragraph -->`
- 属性はオプションの JSON オブジェクト
- 自己閉じブロック（innerHTML なし）: `<!-- wp:separator /-->`

### WP_Block_Type クラス

```php
class WP_Block_Type {
    public $name;                  // ブロック名（例: 'core/paragraph'）
    public $title;                 // 表示名
    public $category;              // カテゴリ（text, media, design 等）
    public $parent;                // 親ブロック制限（null = 制限なし）
    public $ancestor;              // 祖先ブロック制限
    public $allowed_blocks;        // 許可する子ブロック
    public $icon;                  // アイコン
    public $description;           // 説明
    public $keywords;              // 検索キーワード
    public $textdomain;            // テキストドメイン
    public $styles;                // スタイルバリエーション
    public $variations;            // ブロックバリエーション
    public $selectors;             // CSS セレクタ
    public $supports;              // サポート機能
    public $example;               // プレビュー用の例
    public $render_callback;       // サーバーサイドレンダリングのコールバック
    public $render_block;          // レンダリングメソッド
    public $attributes;            // 属性スキーマ
    public $uses_context;          // 使用するコンテキスト
    public $provides_context;      // 提供するコンテキスト
    public $block_hooks;           // 自動挿入フック
    public $editor_script_handles; // エディタ用スクリプトハンドル
    public $script_handles;        // フロント/エディタ共通スクリプトハンドル
    public $view_script_handles;   // フロント表示用スクリプトハンドル
    public $editor_style_handles;  // エディタ用スタイルハンドル
    public $style_handles;         // フロント/エディタ共通スタイルハンドル
    public $view_style_handles;    // フロント表示用スタイルハンドル
    public $api_version;           // API バージョン（1, 2, or 3）
}
```

### WP_Block_Type_Registry クラス

```php
class WP_Block_Type_Registry {
    private $registered_block_types = [];  // name => WP_Block_Type

    public function register(string|WP_Block_Type $name, array $args = []): WP_Block_Type|false;
    public function unregister(string|WP_Block_Type $name): WP_Block_Type|false;
    public function get_registered(string $name): WP_Block_Type|null;
    public function get_all_registered(): WP_Block_Type[];
    public function is_registered(string $name): bool;

    public static function get_instance(): self;  // Singleton
}
```

### WP_Block_Parser の出力

`WP_Block_Parser::parse()` はブロック配列を返します:

```php
// パース結果の構造
[
    [
        'blockName'    => 'core/paragraph',      // null = クラシックブロック（フリーフォーム HTML）
        'attrs'        => ['align' => 'center'],  // JSON 属性
        'innerBlocks'  => [],                     // ネストされたブロック
        'innerHTML'    => '<p class="...">Hello World</p>',  // 内部 HTML
        'innerContent' => ['<p class="...">Hello World</p>'], // 内部コンテンツ配列
    ],
    [
        'blockName'    => 'core/columns',
        'attrs'        => [],
        'innerBlocks'  => [
            ['blockName' => 'core/column', ...],
            ['blockName' => 'core/column', ...],
        ],
        'innerHTML'    => '<div class="wp-block-columns">\n\n</div>',
        'innerContent' => [
            '<div class="wp-block-columns">',
            null,  // null = innerBlocks のプレースホルダー
            null,
            '</div>',
        ],
    ],
]
```

### `innerContent` 配列

`innerContent` は `innerHTML` を内部ブロックの位置で分割した配列です:

- 文字列要素: そのまま出力する HTML フラグメント
- `null` 要素: `innerBlocks` の次のブロックが挿入される位置

### WP_Block クラス

```php
class WP_Block {
    public $parsed_block;    // パース結果の配列
    public $name;            // ブロック名
    public $block_type;      // WP_Block_Type インスタンス
    public $context;         // ブロックコンテキスト
    public $inner_blocks;    // WP_Block_List（子ブロック）
    public $inner_html;      // 内部 HTML
    public $inner_content;   // 内部コンテンツ配列

    public function render(array $options = []): string;
}
```

### ブロック属性スキーマ

```php
'attributes' => [
    'content' => [
        'type'     => 'string',        // 型: string, number, boolean, object, array, null
        'source'   => 'html',          // ソース: html, attribute, text, query, meta
        'selector' => 'p',             // CSS セレクタ（source が html/attribute/text の場合）
        'default'  => '',              // デフォルト値
    ],
    'level' => [
        'type'    => 'number',
        'default' => 2,
    ],
    'align' => [
        'type' => 'string',
        'enum' => ['left', 'center', 'right', 'wide', 'full'],
    ],
],
```

### block.json ファイル形式

WordPress 5.8+ ではブロックのメタデータを `block.json` で定義します:

```json
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "my-plugin/notice",
    "title": "Notice",
    "category": "text",
    "parent": ["core/group"],
    "icon": "star-filled",
    "description": "Shows warning notice.",
    "keywords": ["alert", "message"],
    "textdomain": "my-plugin",
    "attributes": {
        "message": {
            "type": "string",
            "source": "html",
            "selector": ".message"
        }
    },
    "providesContext": {
        "my-plugin/message": "message"
    },
    "usesContext": ["groupId"],
    "selectors": {
        "root": ".wp-block-my-plugin-notice"
    },
    "supports": {
        "align": true,
        "html": false,
        "color": {
            "background": true,
            "text": true
        },
        "typography": {
            "fontSize": true
        },
        "spacing": {
            "margin": true,
            "padding": true
        }
    },
    "styles": [
        { "name": "default", "label": "Default", "isDefault": true },
        { "name": "fancy", "label": "Fancy" }
    ],
    "editorScript": "file:./index.js",
    "editorStyle": "file:./index.css",
    "style": "file:./style-index.css",
    "render": "file:./render.php",
    "viewScript": "file:./view.js"
}
```

## 3. API リファレンス

### ブロック登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_block_type()` | `(string\|WP_Block_Type $block_type, array $args = []): WP_Block_Type\|false` | ブロックタイプを登録 |
| `register_block_type_from_metadata()` | `(string $file_or_folder, array $args = []): WP_Block_Type\|false` | `block.json` からブロックタイプを登録 |
| `unregister_block_type()` | `(string\|WP_Block_Type $name): WP_Block_Type\|false` | ブロックタイプを登録解除 |

`register_block_type()` は第 1 引数に `block.json` のパスを受け取ることもでき、その場合は内部で `register_block_type_from_metadata()` に委譲します。

### ブロッククエリ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `WP_Block_Type_Registry::get_instance()` | `(): WP_Block_Type_Registry` | レジストリの Singleton インスタンス |
| `WP_Block_Type_Registry::get_registered()` | `(string $name): WP_Block_Type\|null` | 登録済みブロックタイプを取得 |
| `WP_Block_Type_Registry::get_all_registered()` | `(): WP_Block_Type[]` | 全登録済みブロックタイプを取得 |
| `WP_Block_Type_Registry::is_registered()` | `(string $name): bool` | ブロックタイプが登録されているか |

### ブロックパース API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `parse_blocks()` | `(string $content): array` | コンテンツをブロック配列にパース |
| `has_blocks()` | `(int\|string\|WP_Post $post = null): bool` | コンテンツにブロックが含まれるか |
| `has_block()` | `(string $block_name, int\|string\|WP_Post $post = null): bool` | 特定ブロックが含まれるか |

### ブロックレンダリング API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `render_block()` | `(array $parsed_block): string` | パース済みブロックを HTML にレンダリング |
| `do_blocks()` | `(string $content): string` | コンテンツ内の全ブロックをレンダリング |
| `serialize_block()` | `(array $block): string` | ブロック配列をシリアライズ（HTML コメント形式） |
| `serialize_blocks()` | `(array $blocks): string` | 複数ブロックをシリアライズ |
| `get_comment_delimited_block_content()` | `(string\|null $block_name, array $attrs, string $content): string` | ブロックのデリミタ付き HTML を生成 |

### ブロックパターン API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_block_pattern()` | `(string $pattern_name, array $pattern_properties): bool` | ブロックパターンを登録 |
| `unregister_block_pattern()` | `(string $pattern_name): bool` | ブロックパターンを登録解除 |
| `register_block_pattern_category()` | `(string $category_name, array $category_properties): bool` | パターンカテゴリを登録 |
| `unregister_block_pattern_category()` | `(string $category_name): bool` | パターンカテゴリを登録解除 |

### ブロックスタイル API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_block_style()` | `(string\|array $block_name, array $style_properties): bool` | ブロックスタイルを登録 |
| `unregister_block_style()` | `(string $block_name, string $block_style_name): bool` | ブロックスタイルを登録解除 |

### ブロックカテゴリ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_default_block_categories()` | `(): array` | デフォルトのブロックカテゴリを取得 |
| `get_block_categories()` | `(WP_Post\|WP_Block_Editor_Context $post_or_context): array` | ブロックカテゴリの一覧を取得 |

デフォルトカテゴリ:

| スラッグ | タイトル |
|---|---|
| `text` | テキスト |
| `media` | メディア |
| `design` | デザイン |
| `widgets` | ウィジェット |
| `theme` | テーマ |
| `embed` | 埋め込み |

### ブロックサポート API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_block_wrapper_attributes()` | `(array $extra_attributes = []): string` | ブロックラッパー要素の属性文字列を生成 |
| `wp_apply_block_supports()` | `(): void` | ブロックサポートによるスタイルを適用 |

### ブロックアセット API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_enqueue_block_style()` | `(string $block_name, array $args): void` | ブロックのスタイルをエンキュー |
| `enqueue_block_styles_assets()` | `(): void` | 登録済みブロックスタイルアセットをエンキュー |

### ブロックテンプレート API

```php
// 投稿タイプにブロックテンプレートを設定
register_post_type('book', [
    'template' => [
        ['core/heading', ['placeholder' => 'Book Title']],
        ['core/paragraph', ['placeholder' => 'Summary...']],
        ['core/image', []],
    ],
    'template_lock' => 'all',  // 'all' | 'insert' | 'contentOnly' | false
]);
```

## 4. 実行フロー

### ブロックパースフロー

```
parse_blocks($content)
│
└── WP_Block_Parser::parse($document)
    │
    ├── 正規表現でブロックコメントデリミタを検出
    │   └── /<!--\s+wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+({...})?\s*(\/)?-->/
    │
    ├── ステートマシンによるパース
    │   ├── Stack ベースのネスト管理
    │   │
    │   ├── 開始タグ検出時:
    │   │   ├── 新しい WP_Block_Parser_Frame をスタックに push
    │   │   └── blockName, attrs, offset を記録
    │   │
    │   ├── 終了タグ検出時:
    │   │   ├── スタックから pop
    │   │   ├── innerHTML, innerContent を構築
    │   │   └── 親フレームの innerBlocks に追加
    │   │
    │   ├── 自己閉じタグ検出時:
    │   │   └── innerBlocks なしのブロックを直接追加
    │   │
    │   └── デリミタ間のフリーフォーム HTML:
    │       └── blockName = null のブロックとして追加
    │
    └── return ブロック配列
```

### ブロックレンダリングフロー

```
the_content フィルター
│
├── do_blocks($content)  ← priority 9（wpautop より前）
│   │
│   ├── parse_blocks($content)
│   │
│   └── 各ブロックに対して render_block($block):
│       │
│       ├── apply_filters('pre_render_block', null, $parsed_block, $parent_block)
│       │   └── null 以外が返ればそれを使用
│       │
│       ├── $block = new WP_Block($parsed_block, $available_context)
│       │
│       ├── WP_Block::render()
│       │   │
│       │   ├── コンテキストの解決
│       │   │   └── $block_type->uses_context からコンテキスト値を取得
│       │   │
│       │   ├── inner_blocks のレンダリング（再帰）
│       │   │   └── 各 innerBlock に対して WP_Block::render()
│       │   │
│       │   ├── innerContent の組み立て
│       │   │   ├── 文字列要素: そのまま結合
│       │   │   └── null 要素: 対応する inner_block のレンダリング結果を挿入
│       │   │
│       │   ├── ダイナミックブロックの場合（render_callback あり）:
│       │   │   └── call_user_func($block_type->render_callback, $attributes, $content, $this)
│       │   │
│       │   └── ブロックサポートの適用
│       │       └── wp_apply_block_supports()
│       │
│       ├── apply_filters('render_block', $block_content, $parsed_block, $block)
│       │
│       └── return $block_content
│
└── 後続の the_content フィルター（wpautop 等）
```

### ブロック登録フロー（block.json）

```
register_block_type(__DIR__)  ← block.json があるディレクトリ
│
├── register_block_type_from_metadata($file_or_folder, $args)
│   │
│   ├── block.json を読み込み・パース
│   │   └── wp_json_file_decode($metadata_file)
│   │
│   ├── メタデータのマッピング
│   │   ├── name → $settings['name']
│   │   ├── title → $settings['title']
│   │   ├── category → $settings['category']
│   │   ├── attributes → $settings['attributes']
│   │   ├── supports → $settings['supports']
│   │   └── ...
│   │
│   ├── アセットの登録
│   │   ├── editorScript → wp_register_script() → $settings['editor_script_handles']
│   │   ├── script → wp_register_script() → $settings['script_handles']
│   │   ├── viewScript → wp_register_script() → $settings['view_script_handles']
│   │   ├── editorStyle → wp_register_style() → $settings['editor_style_handles']
│   │   ├── style → wp_register_style() → $settings['style_handles']
│   │   └── viewStyle → wp_register_style() → $settings['view_style_handles']
│   │
│   ├── render の処理
│   │   └── "file:./render.php" → render_callback としてファイル読み込みクロージャを設定
│   │
│   └── WP_Block_Type_Registry::register($name, $settings)
│       └── new WP_Block_Type($name, $settings)
│
└── return WP_Block_Type
```

## 5. ダイナミックブロック

サーバーサイドでレンダリングされるブロック:

```php
register_block_type('my-plugin/latest-posts', [
    'attributes' => [
        'postsToShow' => [
            'type'    => 'number',
            'default' => 5,
        ],
    ],
    'render_callback' => function (array $attributes, string $content, WP_Block $block): string {
        $posts = get_posts([
            'numberposts' => $attributes['postsToShow'],
        ]);
        $output = '<ul class="wp-block-my-plugin-latest-posts">';
        foreach ($posts as $post) {
            $output .= sprintf(
                '<li><a href="%s">%s</a></li>',
                get_permalink($post),
                esc_html($post->post_title)
            );
        }
        $output .= '</ul>';
        return $output;
    },
]);
```

`render_callback` の引数:

| 引数 | 型 | 説明 |
|---|---|---|
| `$attributes` | `array` | ブロックの属性値（デフォルト値がマージ済み） |
| `$content` | `string` | innerContent のレンダリング結果（子ブロックを含む） |
| `$block` | `WP_Block` | ブロックインスタンス |

## 6. ブロックコンテキスト

親ブロックから子ブロックへ値を伝播する仕組み:

```php
// 親ブロック: コンテキストを提供
register_block_type('my-plugin/query', [
    'attributes' => [
        'queryId' => ['type' => 'number'],
    ],
    'provides_context' => [
        'my-plugin/queryId' => 'queryId',  // コンテキスト名 => 属性名
    ],
]);

// 子ブロック: コンテキストを使用
register_block_type('my-plugin/query-title', [
    'uses_context' => ['my-plugin/queryId'],
    'render_callback' => function ($attributes, $content, $block) {
        $query_id = $block->context['my-plugin/queryId'];
        // ...
    },
]);
```

## 7. ブロックフック（WordPress 6.4+）

ブロックを他のブロックの前後に自動挿入する機能:

```json
{
    "name": "my-plugin/after-post-content",
    "blockHooks": {
        "core/post-content": "after",
        "core/navigation": "firstChild"
    }
}
```

| 位置 | 説明 |
|---|---|
| `before` | ブロックの前 |
| `after` | ブロックの後 |
| `firstChild` | ブロックの最初の子 |
| `lastChild` | ブロックの最後の子 |

## 8. REST API エンドポイント

| エンドポイント | メソッド | 説明 |
|---|---|---|
| `/wp/v2/block-types` | GET | 登録済みブロックタイプ一覧 |
| `/wp/v2/block-types/{namespace}/{name}` | GET | 単一ブロックタイプの取得 |
| `/wp/v2/blocks` | GET, POST | 再利用ブロック（`wp_block` 投稿タイプ）の一覧/作成 |
| `/wp/v2/blocks/{id}` | GET, PUT, DELETE | 再利用ブロックの取得/更新/削除 |
| `/wp/v2/block-patterns/patterns` | GET | ブロックパターン一覧 |
| `/wp/v2/block-patterns/categories` | GET | パターンカテゴリ一覧 |
| `/wp/v2/block-renderer/{name}` | GET, POST | ダイナミックブロックのサーバーサイドレンダリング |
| `/wp/v2/global-styles/{id}` | GET, PUT | グローバルスタイルの取得/更新 |
| `/wp/v2/global-styles/themes/{stylesheet}` | GET | テーマのグローバルスタイル |

## 9. フック一覧

### Action フック

| フック名 | 発火タイミング | パラメータ |
|---|---|---|
| `init` | ブロック登録のタイミング（`register_block_type` を呼ぶ） | なし |
| `enqueue_block_editor_assets` | ブロックエディタのアセット読み込み時 | なし |
| `enqueue_block_assets` | ブロックアセット読み込み時（エディタ + フロント） | なし |

### Filter フック

| フック名 | フィルター対象 | パラメータ |
|---|---|---|
| `register_block_type_args` | ブロックタイプ登録時の引数 | `$args`, `$name` |
| `block_type_metadata` | `block.json` のメタデータ | `$metadata` |
| `block_type_metadata_settings` | メタデータから生成された設定 | `$settings`, `$metadata` |
| `allowed_block_types_all` | 許可されるブロックタイプ | `$allowed_block_types`, `$editor_context` |
| `block_categories_all` | ブロックカテゴリ | `$block_categories`, `$editor_context` |
| `pre_render_block` | ブロックレンダリング前（null 以外で上書き） | `$pre_render`, `$parsed_block`, `$parent_block` |
| `render_block` | ブロックレンダリング後の HTML | `$block_content`, `$parsed_block`, `$block` |
| `render_block_{$name}` | 特定ブロックのレンダリング後 | `$block_content`, `$parsed_block`, `$block` |
| `render_block_data` | レンダリング前のパース済みブロックデータ | `$parsed_block`, `$source_block`, `$parent_block` |
| `render_block_context` | ブロックコンテキスト | `$context`, `$parsed_block`, `$parent_block` |
| `block_editor_settings_all` | ブロックエディタの設定 | `$settings`, `$context` |
| `should_load_separate_core_block_assets` | コアブロックアセットを個別にロードするか | `$load_separate` |
| `block_parser_class` | ブロックパーサークラス名 | `$parser_class` |
| `hooked_block_types` | フック対象ブロックタイプ | `$hooked_blocks`, `$position`, `$anchor_block`, `$context` |
| `hooked_block_{$name}` | フック挿入されるブロックの内容 | `$parsed_hooked_block`, `$hooked_block_type`, `$relative_position`, `$parsed_anchor_block`, `$context` |
