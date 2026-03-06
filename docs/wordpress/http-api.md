# WordPress HTTP API 仕様

## 1. 概要

WordPress の HTTP API は、外部サーバーとの HTTP 通信を統一的に行うためのレイヤーです。`WP_Http` クラスを中心に、トランスポート抽象化・リダイレクト処理・プロキシ対応・SSL 検証・Cookie 管理を一括で提供します。

ユーザーは `wp_remote_get()` / `wp_remote_post()` 等のラッパー関数を通じてリクエストを発行し、内部的には `WP_Http::request()` に委譲されます。

### 主要クラス

| クラス | ファイル | 説明 |
|---|---|---|
| `WP_Http` | `class-wp-http.php` | HTTP API のファサード。リクエストの発行・トランスポート選択を統括 |
| `WP_Http_Curl` | `class-wp-http-curl.php` | cURL トランスポート実装 |
| `WP_Http_Streams` | `class-wp-http-streams.php` | PHP Streams（`fopen` / `stream_socket_client`）トランスポート実装 |
| `WP_Http_Proxy` | `class-wp-http-proxy.php` | プロキシ設定管理 |
| `WP_Http_Cookie` | `class-wp-http-cookie.php` | HTTP Cookie のパース・構築 |
| `WP_Http_Encoding` | `class-wp-http-encoding.php` | gzip / deflate エンコーディングの処理 |
| `Requests` | `Requests/` (bundled library) | WordPress 6.2+ では `WpOrg\Requests` ライブラリに内部委譲 |

### グローバル変数・定数

| 名前 | 型 | 説明 |
|---|---|---|
| `WP_PROXY_HOST` | `string` (定数) | プロキシホスト |
| `WP_PROXY_PORT` | `string` (定数) | プロキシポート |
| `WP_PROXY_USERNAME` | `string` (定数) | プロキシ認証ユーザー名 |
| `WP_PROXY_PASSWORD` | `string` (定数) | プロキシ認証パスワード |
| `WP_PROXY_BYPASS_HOSTS` | `string` (定数) | プロキシバイパスホスト（カンマ区切り） |
| `WP_ACCESSIBLE_HOSTS` | `string` (定数) | `WP_HTTP_BLOCK_EXTERNAL` 有効時にアクセス許可するホスト |
| `WP_HTTP_BLOCK_EXTERNAL` | `bool` (定数) | 外部 HTTP リクエストをブロック |

## 2. データ構造

### レスポンス形式

すべての HTTP API 関数は成功時に連想配列、失敗時に `WP_Error` を返します。

```php
// 成功時のレスポンス構造
$response = [
    'headers'  => Requests_Utility_CaseInsensitiveDictionary, // レスポンスヘッダー
    'body'     => string,   // レスポンスボディ
    'response' => [
        'code'    => int,    // HTTP ステータスコード (200, 404, etc.)
        'message' => string, // ステータスメッセージ ('OK', 'Not Found', etc.)
    ],
    'cookies'       => WP_Http_Cookie[], // Cookie オブジェクト配列
    'filename'      => string|null,      // ストリーム保存時のファイルパス
    'http_response' => WP_HTTP_Requests_Response, // 生のレスポンスオブジェクト
];
```

### リクエスト引数（`$args`）

`WP_Http::request()` に渡すオプション配列のデフォルト値:

```php
$defaults = [
    'method'              => 'GET',
    'timeout'             => 5,            // 秒
    'redirection'         => 5,            // 最大リダイレクト回数
    'httpversion'         => '1.0',
    'user-agent'          => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
    'reject_unsafe_urls'  => false,
    'blocking'            => true,         // false = 非同期（レスポンスを待たない）
    'headers'             => [],
    'cookies'             => [],
    'body'                => null,
    'compress'            => false,
    'decompress'          => true,
    'sslverify'           => true,
    'sslcertificates'     => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
    'stream'              => false,        // true = ファイルにストリーム保存
    'filename'            => null,         // stream 保存先ファイルパス
    'limit_response_size' => null,         // レスポンスサイズ上限（バイト）
];
```

### WP_Http_Cookie クラス

```php
class WP_Http_Cookie {
    public $name;       // Cookie 名
    public $value;      // Cookie 値
    public $expires;    // 有効期限（Unix タイムスタンプ）
    public $path;       // パス
    public $domain;     // ドメイン
    public $port;       // ポート
    public $host_only;  // ホストのみ一致フラグ
}
```

### WP_Http_Proxy クラス

```php
class WP_Http_Proxy {
    public function is_enabled(): bool;          // プロキシが有効か
    public function host(): string;              // プロキシホスト
    public function port(): int;                 // プロキシポート
    public function username(): string;          // 認証ユーザー名
    public function password(): string;          // 認証パスワード
    public function authentication(): string;    // "username:password" 形式
    public function authentication_header(): string; // Basic 認証ヘッダー
    public function use_authentication(): bool;  // 認証が必要か
    public function send_through_proxy(string $uri): bool; // 指定 URI でプロキシを使用するか
}
```

## 3. API リファレンス

### ショートカット関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_remote_get()` | `(string $url, array $args = []): array\|WP_Error` | GET リクエスト |
| `wp_remote_post()` | `(string $url, array $args = []): array\|WP_Error` | POST リクエスト |
| `wp_remote_head()` | `(string $url, array $args = []): array\|WP_Error` | HEAD リクエスト |
| `wp_remote_request()` | `(string $url, array $args = []): array\|WP_Error` | 任意メソッドでリクエスト |
| `wp_safe_remote_get()` | `(string $url, array $args = []): array\|WP_Error` | 安全な GET（`reject_unsafe_urls` 強制） |
| `wp_safe_remote_post()` | `(string $url, array $args = []): array\|WP_Error` | 安全な POST（`reject_unsafe_urls` 強制） |
| `wp_safe_remote_head()` | `(string $url, array $args = []): array\|WP_Error` | 安全な HEAD（`reject_unsafe_urls` 強制） |
| `wp_safe_remote_request()` | `(string $url, array $args = []): array\|WP_Error` | 安全な任意メソッド（`reject_unsafe_urls` 強制） |

`wp_safe_remote_*()` は `reject_unsafe_urls` を `true` に強制し、`wp_http_validate_url()` による URL 検証を行います（ローカルネットワーク・プライベート IP へのリクエストをブロック）。

### レスポンス取得関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_remote_retrieve_headers()` | `(array\|WP_Error $response): \Requests_Utility_CaseInsensitiveDictionary\|array` | レスポンスヘッダーを取得 |
| `wp_remote_retrieve_header()` | `(array\|WP_Error $response, string $header): array\|string` | 特定のヘッダーを取得 |
| `wp_remote_retrieve_response_code()` | `(array\|WP_Error $response): int\|string` | ステータスコードを取得 |
| `wp_remote_retrieve_response_message()` | `(array\|WP_Error $response): string` | ステータスメッセージを取得 |
| `wp_remote_retrieve_body()` | `(array\|WP_Error $response): string` | レスポンスボディを取得 |
| `wp_remote_retrieve_cookies()` | `(array\|WP_Error $response): WP_Http_Cookie[]` | Cookie 配列を取得 |
| `wp_remote_retrieve_cookie()` | `(array\|WP_Error $response, string $name): WP_Http_Cookie\|string` | 特定の Cookie を取得 |
| `wp_remote_retrieve_cookie_value()` | `(array\|WP_Error $response, string $name): string` | 特定の Cookie の値を取得 |

### URL 検証・ユーティリティ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_http_validate_url()` | `(string $url): string\|false` | URL の安全性を検証（SSRF 対策） |
| `allowed_http_request_hosts()` | `(bool $is_external, string $host): bool` | ホストへのリクエストを許可するか判定 |
| `wp_parse_url()` | `(string $url, int $component = -1): mixed` | `parse_url()` のラッパー（PHP バグ回避） |

## 4. 実行フロー

### `wp_remote_get()` のフロー

```
wp_remote_get($url, $args)
│
└── wp_remote_request($url, array_merge($args, ['method' => 'GET']))
    │
    └── _wp_http_get_object()->request($url, $args)
        │   ※ _wp_http_get_object() は WP_Http のシングルトンを返す
        │
        └── WP_Http::request($url, $args)
            │
            ├── $args = wp_parse_args($args, $defaults)  // デフォルト値マージ
            │
            ├── 【フィルター】 http_request_args ($args, $url)
            │   └── リクエスト引数を変更可能
            │
            ├── 【フィルター】 pre_http_request (false, $args, $url)
            │   └── false 以外を返すとリクエストをショートサーキット
            │   └── テスト時のモックに利用
            │
            ├── URL バリデーション
            │   ├── reject_unsafe_urls → wp_http_validate_url()
            │   └── WP_HTTP_BLOCK_EXTERNAL → ホスト許可チェック
            │
            ├── プロキシ設定の適用
            │   └── WP_Http_Proxy::send_through_proxy() で判定
            │
            ├── Cookie の構築
            │   └── WP_Http_Cookie::getHeaderValue() でヘッダー文字列化
            │
            ├── SSL 証明書パスの解決
            │   └── 【フィルター】 https_ssl_verify ($sslverify, $url)
            │   └── 【フィルター】 https_local_ssl_verify ($sslverify, $url)
            │
            ├── Requests ライブラリへ委譲
            │   └── Requests::request($url, $headers, $data, $type, $options)
            │
            ├── レスポンスの変換
            │   └── WP_HTTP_Requests_Response → 配列形式に変換
            │
            ├── 【アクション】 http_api_debug ($response, 'response', ..., $args, $url)
            │
            └── return $response (array | WP_Error)
```

### `pre_http_request` によるショートサーキット

`pre_http_request` フィルターは HTTP リクエストの実行前に呼ばれ、`false` 以外の値を返すとリクエストを実際には送信せず、その値をレスポンスとして返します。テストでのモックに広く利用されます。

```php
add_filter('pre_http_request', function ($preempt, $args, $url) {
    if (str_contains($url, 'api.example.com')) {
        return [
            'headers'  => [],
            'body'     => json_encode(['status' => 'ok']),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies'  => [],
            'filename' => null,
        ];
    }
    return $preempt; // false を返せば通常のリクエストが実行される
}, 10, 3);
```

### トランスポート選択

WordPress 6.2 以降、内部的に `WpOrg\Requests` ライブラリに委譲するため、従来の `WP_Http_Curl` / `WP_Http_Streams` による直接的なトランスポート選択は行われなくなっています。`Requests` ライブラリが自動的に利用可能なトランスポート（cURL 優先、フォールバックとして PHP Streams）を選択します。

### 非ブロッキングリクエスト

`blocking` を `false` に設定すると、レスポンスを待たずに処理を返します。

```php
wp_remote_get('https://example.com/ping', [
    'blocking' => false,
    'timeout'  => 0.01,
]);
```

非ブロッキング時はレスポンス配列のデフォルト値（空ボディ、ステータス 200）が返されます。

## 5. フック一覧

### フィルター

| フック名 | パラメータ | 説明 |
|---|---|---|
| `pre_http_request` | `(false\|array\|WP_Error $preempt, array $args, string $url)` | リクエスト実行前のショートサーキット。false 以外を返すとリクエストをスキップ |
| `http_request_args` | `(array $args, string $url)` | リクエスト引数のフィルタリング |
| `http_request_timeout` | `(float $timeout_value)` | タイムアウト値のフィルタリング |
| `http_request_redirection_count` | `(int $max_redirects)` | 最大リダイレクト回数のフィルタリング |
| `http_request_version` | `(string $version)` | HTTP バージョンのフィルタリング |
| `http_headers_useragent` | `(string $user_agent)` | User-Agent ヘッダーのフィルタリング |
| `http_request_reject_unsafe_urls` | `(bool $reject, string $url)` | 安全でない URL の拒否判定 |
| `https_ssl_verify` | `(bool $verify, string $url)` | 外部 URL への SSL 検証の有効/無効 |
| `https_local_ssl_verify` | `(bool $verify, string $url)` | ローカル URL への SSL 検証の有効/無効 |
| `http_allowed_safe_ports` | `(int[] $ports, string $url, string $scheme)` | 許可ポート番号の配列 |
| `block_local_requests` | `(bool $block)` | ローカルリクエストのブロック判定 |
| `http_response` | `(array $response, array $args, string $url)` | レスポンスのフィルタリング（成功時のみ） |

### アクション

| フック名 | パラメータ | 説明 |
|---|---|---|
| `http_api_debug` | `(mixed $response, string $context, string $class, array $args, string $url)` | リクエスト完了後のデバッグ。`$context` は `'response'` または `'transports_list'` |
| `requests-{hook_name}` | (各種) | `Requests` ライブラリ内部のフック（`requests-curl.before_send` 等） |

## 6. SSRF 対策

### `wp_http_validate_url()`

`reject_unsafe_urls` が有効な場合（`wp_safe_remote_*()` 使用時は常に有効）、以下のチェックが行われます:

1. **スキーム**: `http` または `https` のみ許可
2. **ポート**: `80`, `443`, `8080` のみ許可（`http_allowed_safe_ports` フィルターで変更可能）
3. **ユーザー情報**: URL に `user:pass@` が含まれていれば拒否
4. **ホスト解決**: DNS 解決し、IP アドレスを検証
5. **プライベート IP**: RFC 1918 / RFC 4193 / ループバック / リンクローカルアドレスをブロック
6. **ホスト比較**: 解決結果が元のホスト名と異なる場合の DNS Rebinding 対策

### `WP_HTTP_BLOCK_EXTERNAL`

```php
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,*.github.com');
```

有効にすると外部ホストへのリクエストを全てブロックし、`WP_ACCESSIBLE_HOSTS` に列挙されたホストのみ許可します。ワイルドカード（`*`）によるサブドメインマッチに対応しています。
