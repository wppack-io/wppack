# WordPress REST API 仕様

## 1. 概要

WordPress REST API は、WordPress のデータを HTTP エンドポイント経由で JSON 形式で公開する仕組みです。WordPress 4.7 でコアに統合され、投稿・ユーザー・タクソノミーなどの CRUD 操作を外部アプリケーションやフロントエンドから実行できます。

REST API は以下のコアクラスで構成されています:

| クラス | 説明 |
|---|---|
| `WP_REST_Server` | REST API のルーターおよびディスパッチャ。ルート登録・リクエスト処理・レスポンス送信を担当 |
| `WP_REST_Request` | HTTP リクエストのラッパー。パラメータ解析・バリデーション・サニタイズを提供 |
| `WP_REST_Response` | HTTP レスポンスのラッパー。`WP_HTTP_Response` を拡張し、リンクやステータスコードを管理 |
| `WP_REST_Controller` | エンドポイントコントローラーの抽象基底クラス。CRUD パターンを標準化 |

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_rest_server` | `WP_REST_Server\|null` | REST API サーバーのシングルトンインスタンス |

REST API のベース URL はデフォルトで `/wp-json/` です。パーマリンク無効時は `/?rest_route=/` にフォールバックします。

## 2. データ構造

### WP_REST_Server クラス

```php
class WP_REST_Server {
    const READABLE   = 'GET';
    const CREATABLE  = 'POST';
    const EDITABLE   = 'POST, PUT, PATCH';
    const DELETABLE  = 'DELETE';
    const ALLMETHODS  = 'GET, POST, PUT, PATCH, DELETE';

    protected $namespaces = [];  // 登録済み名前空間の配列
    protected $endpoints  = [];  // ルートとそのハンドラーの配列
    protected $route_map  = [];  // ルートパターンからルート名へのマップ
    protected $embed_cache = []; // 埋め込みレスポンスのキャッシュ
}
```

### ルート登録構造

`register_rest_route()` で登録される各ルートは以下の構造を持ちます:

```
$endpoints = [
    '/wp/v2/posts' => [
        [
            'methods'             => ['GET' => true],
            'callback'            => callable,
            'permission_callback' => callable,
            'args'                => [
                'param_name' => [
                    'description'       => string,
                    'type'              => string,
                    'required'          => bool,
                    'default'           => mixed,
                    'validate_callback' => callable,
                    'sanitize_callback' => callable,
                ],
            ],
        ],
        // ... 追加のメソッドハンドラー
    ],
];
```

### WP_REST_Request クラス

```php
class WP_REST_Request implements ArrayAccess {
    protected $method      = '';       // HTTP メソッド
    protected $params      = [];       // パラメータソース別の配列
    protected $headers     = [];       // HTTP ヘッダー
    protected $body        = null;     // リクエストボディ
    protected $route       = '';       // マッチしたルート
    protected $attributes  = [];       // ルート属性（args, callback 等）
    protected $parsed_json = false;    // JSON パース済みフラグ
    protected $parsed_body = false;    // ボディパース済みフラグ
}
```

パラメータは以下の優先順位（上が高い）で統合されます:

| ソース | 定数 | 説明 |
|---|---|---|
| `defaults` | — | ルート定義の `default` 値 |
| `GET` | — | URL クエリパラメータ |
| `POST` | — | フォームデータ（`application/x-www-form-urlencoded`） |
| `FILES` | — | アップロードファイル |
| `JSON` | — | JSON ボディ（`application/json`） |
| `URL` | — | URL パスから抽出された名前付きパラメータ |

`$request->get_param('key')` は URL → JSON → POST → GET → defaults の順で最初にマッチした値を返します。`$request->get_params()` は全ソースをマージした配列を返します。

### WP_REST_Response クラス

```php
class WP_REST_Response extends WP_HTTP_Response {
    protected $links     = [];    // HAL 形式のリンク
    protected $matched_route = ''; // マッチしたルートパターン
    protected $matched_handler = null; // マッチしたハンドラー
}
```

## 3. API リファレンス

### ルート登録

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_rest_route()` | `(string $route_namespace, string $route, array $args = [], bool $override = false): bool` | REST ルートを登録 |
| `register_rest_field()` | `(string\|array $object_type, string $attribute, array $args = []): void` | 既存オブジェクトタイプにフィールドを追加 |

`register_rest_route()` は `rest_api_init` アクション内で呼び出す必要があります。`$route_namespace` は `vendor/v1` 形式の名前空間です。

```php
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/items', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'get_items_handler',
        'permission_callback' => '__return_true',
    ]);
});
```

### リクエスト操作

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_REST_Request::get_method()` | `(): string` | HTTP メソッドを取得 |
| `WP_REST_Request::get_route()` | `(): string` | マッチしたルートパスを取得 |
| `WP_REST_Request::get_params()` | `(): array` | 全パラメータを統合して取得 |
| `WP_REST_Request::get_param()` | `(string $key): mixed\|null` | 指定パラメータを取得 |
| `WP_REST_Request::set_param()` | `(string $key, mixed $value): void` | パラメータを設定 |
| `WP_REST_Request::get_header()` | `(string $key): string\|null` | ヘッダーを取得 |
| `WP_REST_Request::get_json_params()` | `(): array` | JSON ボディをパースして取得 |
| `WP_REST_Request::get_body()` | `(): string` | 生のリクエストボディを取得 |
| `WP_REST_Request::get_file_params()` | `(): array` | アップロードファイルを取得 |
| `WP_REST_Request::has_valid_params()` | `(): true\|WP_Error` | パラメータバリデーションを実行 |
| `WP_REST_Request::sanitize_params()` | `(): true\|WP_Error` | パラメータサニタイズを実行 |

### レスポンス操作

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_REST_Response::add_link()` | `(string $rel, string $href, array $attributes = []): void` | HAL リンクを追加 |
| `WP_REST_Response::add_links()` | `(array $links): void` | 複数リンクを一括追加 |
| `WP_REST_Response::get_links()` | `(): array` | 登録済みリンクを取得 |
| `WP_REST_Response::set_matched_route()` | `(string $route): void` | マッチしたルートを設定 |
| `WP_REST_Response::header()` | `(string $key, string $value, bool $replace = true): void` | レスポンスヘッダーを設定 |

### サーバー操作

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_REST_Server::register_route()` | `(string $route_namespace, string $route, array $route_args, bool $override = false): void` | ルートを内部登録 |
| `WP_REST_Server::dispatch()` | `(WP_REST_Request $request): WP_REST_Response` | リクエストをディスパッチ |
| `WP_REST_Server::serve_request()` | `(?string $path = null): null\|false` | リクエストを処理してレスポンスを送信 |
| `WP_REST_Server::get_routes()` | `(?string $namespace = null): array` | 登録済みルートを取得 |
| `WP_REST_Server::get_namespaces()` | `(): array` | 登録済み名前空間を取得 |
| `WP_REST_Server::get_index()` | `(array $request): WP_REST_Response` | API インデックス（Discovery）を返す |
| `WP_REST_Server::response_to_data()` | `(WP_REST_Response $response, bool $embed): array` | レスポンスを配列に変換 |
| `WP_REST_Server::envelope_response()` | `(WP_REST_Response $response, bool $embed): WP_REST_Response` | エンベロープ形式でラップ |

### ヘルパー関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `rest_url()` | `(string $path = ''): string` | REST API の URL を生成 |
| `rest_get_server()` | `(): WP_REST_Server` | サーバーインスタンスを取得（なければ初期化） |
| `rest_do_request()` | `(string\|WP_REST_Request $request): WP_REST_Response` | 内部 REST リクエストを実行 |
| `rest_ensure_response()` | `(WP_REST_Response\|WP_Error\|mixed $response): WP_REST_Response\|WP_Error` | 値を `WP_REST_Response` に変換 |
| `rest_ensure_request()` | `(WP_REST_Request\|string $request): WP_REST_Request` | 値を `WP_REST_Request` に変換 |
| `rest_get_url_prefix()` | `(): string` | REST URL プレフィックス（デフォルト: `wp-json`）を取得 |
| `rest_api_loaded()` | `(): void` | `parse_request` から REST API をブートストラップ |
| `rest_get_queried_resource_route()` | `(): string` | 現在のクエリに対応する REST ルートを取得 |
| `rest_preload_api_request()` | `(array $memo, string $path): array` | REST リクエストをプリロード |

### 認証関連

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `rest_cookie_check_errors()` | `(WP_Error\|mixed $result): WP_Error\|mixed\|bool` | クッキー認証のエラーチェック |
| `rest_cookie_collect_status()` | `(): void` | クッキー認証ステータスを収集 |
| `rest_application_password_check_errors()` | `(WP_Error\|null\|true $result): WP_Error\|null\|true` | アプリケーションパスワード認証チェック |
| `rest_application_password_collect_status()` | `(WP_Error\|mixed $user_or_error): void` | アプリケーションパスワードステータスを収集 |

## 4. 実行フロー

### リクエスト処理フロー

```
HTTP Request: GET /wp-json/wp/v2/posts
│
├── parse_request アクション
│   └── rest_api_loaded()
│       ├── REST パスを判定（$_SERVER['PATH_INFO'] or 'rest_route' パラメータ）
│       └── rest_get_server()->serve_request($path)
│
├── WP_REST_Server::serve_request($path)
│   │
│   ├── do_action('rest_api_init', $this)     // ルート登録のタイミング
│   │
│   ├── apply_filters('rest_pre_serve_request', false, $result, $request, $this)
│   │   └── true が返されたらサーバーの処理をスキップ
│   │
│   ├── $result = $this->check_authentication()
│   │   └── apply_filters('rest_authentication_errors', null)
│   │       └── WP_Error が返されたら認証失敗
│   │
│   ├── $result = $this->dispatch($request)
│   │
│   ├── $result = rest_ensure_response($result)
│   │
│   ├── apply_filters('rest_post_dispatch', $result, $this, $request)
│   │
│   ├── ヘッダー送信（CORS、Allow、Link 等）
│   │
│   └── JSON エンコードして出力
│
└── exit
```

### ディスパッチフロー

```
WP_REST_Server::dispatch($request)
│
├── apply_filters('rest_pre_dispatch', null, $this, $request)
│   └── null 以外が返されたらディスパッチをスキップ
│
├── ルートマッチング
│   ├── 登録済みルートを正規表現で走査
│   ├── preg_match() でパスパラメータを抽出
│   └── HTTP メソッドが許可されているか確認
│       └── 不一致なら 405 Method Not Allowed
│
├── permission_callback の実行
│   ├── コールバック結果を確認
│   ├── false → 403 Forbidden（rest_forbidden）
│   └── WP_Error → そのエラーを返す
│
├── $request->has_valid_params()
│   └── 各パラメータの validate_callback を実行
│
├── $request->sanitize_params()
│   └── 各パラメータの sanitize_callback を実行
│
├── apply_filters('rest_dispatch_request', null, $request, $route, $handler)
│   └── null 以外が返されたらコールバックをスキップ
│
├── $response = call_user_func($handler['callback'], $request)
│
└── return rest_ensure_response($response)
```

### 認証フロー

REST API は複数の認証メカニズムをサポートしています:

```
rest_authentication_errors フィルター
│
├── Cookie 認証（デフォルト）
│   ├── logged_in cookie を確認
│   ├── nonce 検証（X-WP-Nonce ヘッダーまたは _wpnonce パラメータ）
│   └── nonce 不一致 → 未認証として扱う（エラーにはならない）
│
├── Application Passwords（WordPress 5.6+）
│   ├── Authorization: Basic ヘッダーを解析
│   ├── ユーザー名 + アプリケーションパスワードで認証
│   └── wp_authenticate_application_password() で検証
│
└── カスタム認証（プラグイン）
    └── rest_authentication_errors フィルターで実装
```

## 5. コントローラーパターン

### WP_REST_Controller 抽象クラス

WordPress コアのエンドポイントは `WP_REST_Controller` を継承して実装されます:

```php
abstract class WP_REST_Controller {
    protected $namespace;  // 名前空間（例: 'wp/v2'）
    protected $rest_base;  // ベースパス（例: 'posts'）
    protected $schema;     // キャッシュされたスキーマ

    // サブクラスで実装するメソッド
    public function register_routes(): void;
    public function get_items($request): WP_REST_Response|WP_Error;
    public function get_item($request): WP_REST_Response|WP_Error;
    public function create_item($request): WP_REST_Response|WP_Error;
    public function update_item($request): WP_REST_Response|WP_Error;
    public function delete_item($request): WP_REST_Response|WP_Error;
    public function get_items_permissions_check($request): true|WP_Error;
    public function get_item_permissions_check($request): true|WP_Error;
    public function create_item_permissions_check($request): true|WP_Error;
    public function update_item_permissions_check($request): true|WP_Error;
    public function delete_item_permissions_check($request): true|WP_Error;
    public function get_item_schema(): array;
    public function get_collection_params(): array;
}
```

### コアエンドポイント一覧

| エンドポイント | コントローラークラス | 説明 |
|---|---|---|
| `/wp/v2/posts` | `WP_REST_Posts_Controller` | 投稿 |
| `/wp/v2/pages` | `WP_REST_Posts_Controller` | 固定ページ |
| `/wp/v2/media` | `WP_REST_Attachments_Controller` | メディア |
| `/wp/v2/comments` | `WP_REST_Comments_Controller` | コメント |
| `/wp/v2/categories` | `WP_REST_Terms_Controller` | カテゴリー |
| `/wp/v2/tags` | `WP_REST_Terms_Controller` | タグ |
| `/wp/v2/users` | `WP_REST_Users_Controller` | ユーザー |
| `/wp/v2/taxonomies` | `WP_REST_Taxonomies_Controller` | タクソノミー |
| `/wp/v2/types` | `WP_REST_Post_Types_Controller` | 投稿タイプ |
| `/wp/v2/statuses` | `WP_REST_Post_Statuses_Controller` | 投稿ステータス |
| `/wp/v2/settings` | `WP_REST_Settings_Controller` | サイト設定 |
| `/wp/v2/themes` | `WP_REST_Themes_Controller` | テーマ |
| `/wp/v2/plugins` | `WP_REST_Plugins_Controller` | プラグイン |
| `/wp/v2/block-types` | `WP_REST_Block_Types_Controller` | ブロックタイプ |
| `/wp/v2/search` | `WP_REST_Search_Controller` | 検索 |

## 6. スキーマとバリデーション

### JSON Schema

REST API は JSON Schema (Draft 4) をベースとしたスキーマシステムを使用しています。`get_item_schema()` で定義されたスキーマはエンドポイントの OPTIONS レスポンスに含まれます。

```php
// スキーマの例
[
    '$schema'    => 'http://json-schema.org/draft-04/schema#',
    'title'      => 'post',
    'type'       => 'object',
    'properties' => [
        'id' => [
            'description' => 'Unique identifier for the post.',
            'type'        => 'integer',
            'context'     => ['view', 'edit', 'embed'],
            'readonly'    => true,
        ],
        'title' => [
            'description' => 'The title for the post.',
            'type'        => 'object',
            'context'     => ['view', 'edit'],
            'properties'  => [
                'raw'      => ['type' => 'string', 'context' => ['edit']],
                'rendered' => ['type' => 'string', 'context' => ['view', 'edit'], 'readonly' => true],
            ],
        ],
    ],
]
```

### context パラメータ

レスポンスに含まれるフィールドは `context` パラメータで制御されます:

| コンテキスト | 説明 |
|---|---|
| `view` | デフォルト。公開可能なフィールド |
| `edit` | 編集時に必要なフィールド（raw 値を含む） |
| `embed` | 埋め込み時の最小フィールドセット |

### バリデーション関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `rest_validate_value_from_schema()` | `(mixed $value, array $args, string $param = ''): true\|WP_Error` | スキーマに基づくバリデーション |
| `rest_sanitize_value_from_schema()` | `(mixed $value, array $args, string $param = ''): mixed\|WP_Error` | スキーマに基づくサニタイズ |
| `rest_validate_request_arg()` | `(mixed $value, WP_REST_Request $request, string $param): true\|WP_Error` | リクエスト引数のバリデーション |
| `rest_sanitize_request_arg()` | `(mixed $value, WP_REST_Request $request, string $param): mixed\|WP_Error` | リクエスト引数のサニタイズ |
| `rest_parse_request_arg()` | `(mixed $value, WP_REST_Request $request, string $param): mixed` | リクエスト引数のパース |

## 7. フック一覧

### Action

| フック名 | 引数 | 説明 |
|---|---|---|
| `rest_api_init` | `(WP_REST_Server $server)` | REST API 初期化時。ルート登録のタイミング |
| `rest_after_insert_{$post_type}` | `(WP_Post $post, WP_REST_Request $request, bool $creating)` | 投稿挿入後 |
| `rest_delete_{$post_type}` | `(WP_Post $post, WP_REST_Response $response, WP_REST_Request $request)` | 投稿削除後 |
| `rest_insert_{$post_type}` | `(WP_Post $post, WP_REST_Request $request, bool $creating)` | 投稿挿入直後（メタ更新前） |
| `rest_after_insert_comment` | `(WP_Comment $comment, WP_REST_Request $request, bool $creating)` | コメント挿入後 |
| `rest_insert_comment` | `(WP_Comment $comment, WP_REST_Request $request, bool $creating)` | コメント挿入直後 |
| `rest_delete_comment` | `(WP_Comment $comment, WP_REST_Response $response, WP_REST_Request $request)` | コメント削除後 |
| `rest_after_insert_user` | `(WP_User $user, WP_REST_Request $request, bool $creating)` | ユーザー挿入後 |
| `rest_insert_user` | `(WP_User $user, WP_REST_Request $request, bool $creating)` | ユーザー挿入直後 |
| `rest_delete_user` | `(WP_User $user, WP_REST_Response $response, WP_REST_Request $request)` | ユーザー削除後 |

### Filter

| フック名 | 引数 | 説明 |
|---|---|---|
| `rest_url` | `(string $url)` | REST API ベース URL をフィルター |
| `rest_url_prefix` | `(string $prefix)` | URL プレフィックス（デフォルト: `wp-json`）をフィルター |
| `rest_authentication_errors` | `(WP_Error\|null\|true $errors)` | 認証エラーをフィルター。カスタム認証の実装ポイント |
| `rest_pre_serve_request` | `(bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server)` | レスポンス送信前。`true` 返却でデフォルト出力をスキップ |
| `rest_pre_dispatch` | `(mixed $result, WP_REST_Server $server, WP_REST_Request $request)` | ディスパッチ前。null 以外を返すとディスパッチをスキップ |
| `rest_dispatch_request` | `(mixed $dispatch_result, WP_REST_Request $request, string $route, array $handler)` | コールバック実行前。null 以外を返すとコールバックをスキップ |
| `rest_post_dispatch` | `(WP_REST_Response $result, WP_REST_Server $server, WP_REST_Request $request)` | ディスパッチ後のレスポンスをフィルター |
| `rest_request_before_callbacks` | `(WP_REST_Response\|WP_Error\|mixed $response, array $handler, WP_REST_Request $request)` | コールバック実行前のレスポンスをフィルター |
| `rest_request_after_callbacks` | `(WP_REST_Response\|WP_Error\|mixed $response, array $handler, WP_REST_Request $request)` | コールバック実行後のレスポンスをフィルター |
| `rest_prepare_{$post_type}` | `(WP_REST_Response $response, WP_Post $post, WP_REST_Request $request)` | 投稿レスポンスをフィルター |
| `rest_prepare_comment` | `(WP_REST_Response $response, WP_Comment $comment, WP_REST_Request $request)` | コメントレスポンスをフィルター |
| `rest_prepare_user` | `(WP_REST_Response $response, WP_User $user, WP_REST_Request $request)` | ユーザーレスポンスをフィルター |
| `rest_prepare_taxonomy` | `(WP_REST_Response $response, WP_Taxonomy $taxonomy, WP_REST_Request $request)` | タクソノミーレスポンスをフィルター |
| `rest_{$post_type}_query` | `(array $args, WP_REST_Request $request)` | 投稿クエリ引数をフィルター |
| `rest_{$taxonomy}_query` | `(array $args, WP_REST_Request $request)` | タクソノミークエリ引数をフィルター |
| `rest_allowed_cors_headers` | `(array $headers)` | CORS で許可するヘッダーをフィルター |
| `rest_exposed_cors_headers` | `(array $headers)` | CORS で公開するレスポンスヘッダーをフィルター |
| `rest_send_nocache_headers` | `(bool $send)` | キャッシュ無効ヘッダーを送信するかフィルター |
| `rest_jsonp_enabled` | `(bool $enabled)` | JSONP サポートの有効/無効をフィルター |
| `rest_endpoints_description` | `(array $routes)` | OPTIONS レスポンスのエンドポイント記述をフィルター |
