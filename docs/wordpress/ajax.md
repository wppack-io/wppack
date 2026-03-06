# WordPress Ajax API 仕様

## 1. 概要

WordPress の Ajax API は、管理画面およびフロントエンドからの非同期 HTTP リクエストを処理するための仕組みです。すべての Ajax リクエストは `wp-admin/admin-ajax.php` をエンドポイントとし、`action` パラメータで処理を振り分けます。

認証済みユーザー（ログインユーザー）と非認証ユーザー（一般訪問者）で異なるフックが発火するため、アクセス制御が組み込まれています。

### 主要ファイル

| ファイル | 説明 |
|---|---|
| `wp-admin/admin-ajax.php` | Ajax リクエストのエントリポイント |
| `wp-includes/ajax-actions.php` | WordPress コアの Ajax アクションハンドラー定義 |
| `wp-includes/js/wp-util.js` | `wp.ajax` JavaScript ユーティリティ |

### エンドポイント

| URL | 説明 |
|---|---|
| `/wp-admin/admin-ajax.php` | すべての Ajax リクエストの受付先 |

`admin-ajax.php` の URL は `admin_url('admin-ajax.php')` で取得できます。JavaScript 側では `ajaxurl` グローバル変数（管理画面内）または `wp_localize_script()` で渡します。

### グローバル変数・定数

| 名前 | 型 | 説明 |
|---|---|---|
| `DOING_AJAX` | `bool` (定数) | Ajax リクエスト処理中に `true` が定義される |
| `$_GET['action']` / `$_POST['action']` | `string` | Ajax アクション名 |

## 2. データ構造

### リクエスト形式

Ajax リクエストは標準的な HTTP POST（または GET）リクエストです。

```
POST /wp-admin/admin-ajax.php
Content-Type: application/x-www-form-urlencoded

action=my_action&nonce=abc123&data=value
```

必須パラメータ:

| パラメータ | 説明 |
|---|---|
| `action` | 実行する Ajax アクション名。フック `wp_ajax_{action}` / `wp_ajax_nopriv_{action}` にマッピングされる |

推奨パラメータ:

| パラメータ | 説明 |
|---|---|
| `_ajax_nonce` or `_wpnonce` | Nonce 値。`check_ajax_referer()` で検証 |

### レスポンス形式

Ajax ハンドラーは任意の形式でレスポンスを返せますが、WordPress は JSON レスポンスのヘルパー関数を提供しています。

#### `wp_send_json_success()` のレスポンス

```json
{
    "success": true,
    "data": "任意のデータ"
}
```

#### `wp_send_json_error()` のレスポンス

```json
{
    "success": false,
    "data": "エラーメッセージまたはデータ"
}
```

#### `WP_Error` を渡した場合

```json
{
    "success": false,
    "data": [
        {
            "code": "error_code",
            "message": "エラーメッセージ",
            "data": "追加データ"
        }
    ]
}
```

## 3. API リファレンス

### PHP サーバーサイド API

#### アクション登録

Ajax ハンドラーの登録は WordPress のフックシステムを使用します。

| フック | 説明 |
|---|---|
| `wp_ajax_{action}` | 認証済みユーザーからのリクエスト時に発火 |
| `wp_ajax_nopriv_{action}` | 非認証ユーザーからのリクエスト時に発火 |

```php
// 認証済みユーザーのみ
add_action('wp_ajax_my_action', function () {
    // 処理
    wp_send_json_success(['result' => 'ok']);
});

// 認証済み + 非認証ユーザーの両方
add_action('wp_ajax_my_action', 'handle_my_action');
add_action('wp_ajax_nopriv_my_action', 'handle_my_action');
```

#### レスポンス関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_send_json()` | `(mixed $response, int $status_code = null, int $options = 0): void` | JSON レスポンスを送信して `die()` |
| `wp_send_json_success()` | `(mixed $data = null, int $status_code = null, int $options = 0): void` | `{success: true, data: $data}` を送信して `die()` |
| `wp_send_json_error()` | `(mixed $data = null, int $status_code = null, int $options = 0): void` | `{success: false, data: $data}` を送信して `die()` |
| `wp_die()` | `(string\|WP_Error $message = '', string\|int $title = '', string\|array\|int $args = [])` | 実行を停止。Ajax コンテキストでは特別な処理 |

#### Nonce 検証

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `check_ajax_referer()` | `(int\|string $action = -1, false\|string $query_arg = false, bool $stop = true): int\|false` | Ajax リクエストの Nonce を検証 |
| `wp_verify_nonce()` | `(string $nonce, string\|int $action = -1): int\|false` | Nonce 値の検証 |
| `wp_create_nonce()` | `(string\|int $action = -1): string` | Nonce 値を生成 |

`check_ajax_referer()` は以下の順で Nonce を探します:
1. `$query_arg` が指定されていればそのキーで `$_REQUEST` を検索
2. 未指定の場合、`$_REQUEST['_ajax_nonce']` を検索
3. 未見つかりの場合、`$_REQUEST['_wpnonce']` を検索
4. `$stop = true`（デフォルト）の場合、検証失敗で `die('-1')`

### JavaScript クライアント API

#### `ajaxurl` グローバル変数

管理画面では `wp-admin/admin-header.php` が以下を出力します:

```html
<script type="text/javascript">
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
</script>
```

フロントエンドでは `ajaxurl` は自動的には定義されません。`wp_localize_script()` で明示的に渡す必要があります:

```php
wp_enqueue_script('my-script', ...);
wp_localize_script('my-script', 'myAjax', [
    'url'   => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('my_nonce_action'),
]);
```

#### `wp.ajax` ユーティリティ（`wp-util.js`）

`wp-util.js` は jQuery Deferred ベースの Ajax ラッパーを提供します。

```javascript
// wp.ajax.post() - POST リクエスト
wp.ajax.post('my_action', {
    nonce: myAjax.nonce,
    data: 'value'
}).done(function (response) {
    // success: true のとき。response = data の値
}).fail(function (response) {
    // success: false のとき。response = data の値
});

// wp.ajax.send() - カスタマイズ可能なリクエスト
wp.ajax.send('my_action', {
    type: 'POST',
    data: { key: 'value' },
    success: function (data) { },
    error: function (data) { }
});
```

`wp.ajax.send()` は自動的に `action` パラメータを URL に追加します。

## 4. 実行フロー

### `admin-ajax.php` の処理フロー

```
admin-ajax.php
│
├── define('DOING_AJAX', true)
│
├── require_once('../wp-load.php')
│   └── WordPress のブートストラップ（wp-settings.php 含む）
│
├── require_once(ABSPATH . 'wp-admin/includes/ajax-actions.php')
│   └── コアの Ajax アクションハンドラーを登録
│
├── 【アクション】 admin_init
│
├── $action = $_REQUEST['action'] の取得
│   └── 未定義の場合 → admin_init 後に die('0')
│
├── header('Content-Type: text/html; charset=' . get_option('blog_charset'))
│   └── Content-Type のデフォルト設定
│
├── header('X-Robots-Tag: noindex')
│   └── 検索エンジンによるインデックスを防止
│
├── send_nosniff_header()
│   └── X-Content-Type-Options: nosniff
│
├── nocache_headers()
│   └── キャッシュ防止ヘッダー
│
├── ユーザー認証チェック
│   └── is_user_logged_in()
│
├── 認証済みユーザーの場合
│   │
│   ├── 【アクション】 wp_ajax_{$action}
│   │   └── ハンドラーが登録されていれば実行
│   │
│   ├── ハンドラー未登録の場合
│   │   └── die('0')
│   │
│   └── die('0')  // ハンドラーが die() しなかった場合のフォールバック
│
└── 非認証ユーザーの場合
    │
    ├── 【アクション】 wp_ajax_nopriv_{$action}
    │   └── ハンドラーが登録されていれば実行
    │
    ├── ハンドラー未登録の場合
    │   └── die('-1')
    │
    └── die('0')  // ハンドラーが die() しなかった場合のフォールバック
```

### Nonce 検証のフロー

```
check_ajax_referer($action, $query_arg, $stop)
│
├── $query_arg が指定されている場合
│   └── $nonce = $_REQUEST[$query_arg]
│
├── $query_arg が未指定の場合
│   ├── $_REQUEST['_ajax_nonce'] が存在 → $nonce = その値
│   └── $_REQUEST['_wpnonce'] が存在 → $nonce = その値
│
├── wp_verify_nonce($nonce, $action)
│   ├── 有効（生成から 0-12 時間） → 1 を返す
│   ├── 有効（生成から 12-24 時間） → 2 を返す
│   └── 無効 → false
│
├── 検証失敗 かつ $stop === true
│   └── die('-1')  // リクエスト拒否
│
├── 【アクション】 check_ajax_referer ($action, $result)
│
└── return $result (1 | 2 | false)
```

### `wp_send_json()` の内部処理

```
wp_send_json($response, $status_code, $options)
│
├── $status_code が指定されている場合
│   └── status_header($status_code)
│
├── header('Content-Type: application/json; charset=' . get_option('blog_charset'))
│
├── echo wp_json_encode($response, $options)
│
├── DOING_AJAX が定義されている場合
│   └── wp_die('', '', ['response' => null])
│       └── Ajax コンテキスト用の die 処理
│
└── die()
```

## 5. フック一覧

### アクション

| フック名 | パラメータ | 説明 |
|---|---|---|
| `wp_ajax_{action}` | (なし) | 認証済みユーザーの Ajax リクエスト処理。`{action}` は `$_REQUEST['action']` の値 |
| `wp_ajax_nopriv_{action}` | (なし) | 非認証ユーザーの Ajax リクエスト処理 |
| `admin_init` | (なし) | `admin-ajax.php` のブートストラップ中に発火 |
| `check_ajax_referer` | `(string $action, false\|int $result)` | Nonce 検証後に発火 |

### フィルター

| フック名 | パラメータ | 説明 |
|---|---|---|
| `wp_die_ajax_handler` | `(callable $handler)` | Ajax コンテキストでの `wp_die()` ハンドラーを変更 |
| `wp_doing_ajax` | `(bool $doing_ajax)` | `wp_doing_ajax()` の戻り値をフィルタリング |

### `wp_die()` の Ajax コンテキスト

`wp_die()` は `DOING_AJAX` 定数が `true` の場合、通常の HTML エラーページの代わりに `_ajax_wp_die_handler` を使用します:

```php
function _ajax_wp_die_handler($message, $title = '', $args = []) {
    // WP_Error の場合はエラーメッセージを連結
    if (is_wp_error($message)) {
        $message = $message->get_error_message();
    }

    // ステータスコードの設定（デフォルト: 200）
    if (isset($args['response'])) {
        status_header($args['response']);
    }

    die($message);
}
```

## 6. WordPress コアの Ajax アクション

`ajax-actions.php` で登録される主要なコア Ajax アクション:

| アクション名 | 説明 |
|---|---|
| `heartbeat` | Heartbeat API（定期的なサーバー通信） |
| `wp-remove-post-lock` | 投稿ロックの解除 |
| `save-attachment` | メディア添付ファイルの保存 |
| `save-attachment-compat` | メディア添付ファイル互換フィールドの保存 |
| `send-attachment-to-editor` | エディタへのメディア挿入 |
| `upload-attachment` | メディアアップロード |
| `image-editor` | 画像エディタ操作 |
| `save-widget` | ウィジェットの保存 |
| `update-widget` | ウィジェットの更新 |
| `delete-comment` | コメントの削除 |
| `delete-tag` | タグの削除 |
| `delete-post` | 投稿の削除 |
| `trash-post` | 投稿のゴミ箱移動 |
| `untrash-post` | ゴミ箱からの復元 |
| `inline-save` | Quick Edit（インライン編集）の保存 |
| `inline-save-tax` | タクソノミーのインライン編集 |
| `add-tag` | タグの追加 |
| `get-tagcloud` | タグクラウドの取得 |
| `query-attachments` | メディアライブラリのクエリ |
| `get-comments` | コメント一覧の取得 |
| `replyto-comment` | コメントへの返信 |
| `edit-comment` | コメントの編集 |
| `find_posts` | 投稿の検索 |
| `wp-compression-test` | gzip 圧縮テスト |
| `menu-get-metabox` | メニューメタボックスの取得 |
| `menu-locations-save` | メニューロケーションの保存 |
| `menu-quick-search` | メニューアイテムの検索 |
| `wp-link-ajax` | リンクダイアログの検索 |

### Heartbeat API

`heartbeat` アクションは WordPress の定期通信機能で、以下のフックを提供します:

| フック | パラメータ | 説明 |
|---|---|---|
| `heartbeat_received` | `(array $response, array $data, string $screen_id)` | Heartbeat データの受信・レスポンス生成 |
| `heartbeat_send` | `(array $response, string $screen_id)` | Heartbeat レスポンスのフィルタリング |
| `heartbeat_nopriv_received` | `(array $response, array $data, string $screen_id)` | 非認証ユーザーの Heartbeat 受信 |
| `heartbeat_nopriv_send` | `(array $response, string $screen_id)` | 非認証ユーザーの Heartbeat レスポンス |
| `heartbeat_settings` | `(array $settings)` | Heartbeat の設定（間隔等） |

## 7. セキュリティ上の注意

### Nonce の必須化

すべての Ajax ハンドラーで Nonce 検証を行うべきです:

```php
add_action('wp_ajax_my_action', function () {
    check_ajax_referer('my_nonce_action', 'nonce');

    // 権限チェック
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions', 403);
    }

    // 処理...
    wp_send_json_success($result);
});
```

### `die()` / `wp_die()` の必須化

Ajax ハンドラーは処理完了後に必ず `die()` / `wp_die()` を呼ぶか、`wp_send_json_*()` を使用する必要があります。呼ばない場合、`admin-ajax.php` が末尾の `die('0')` を出力し、レスポンスに `0` が付加されます。

### `DOING_AJAX` 定数の利用

プラグインは `wp_doing_ajax()` 関数（内部的に `DOING_AJAX` 定数を参照）で Ajax リクエスト中かどうかを判定できます:

```php
if (wp_doing_ajax()) {
    // Ajax リクエスト中の処理
}
```
