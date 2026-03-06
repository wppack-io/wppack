# WordPress Nonce API 仕様

## 1. 概要

WordPress の Nonce（Number used Once）は、CSRF（Cross-Site Request Forgery）攻撃を防ぐためのセキュリティトークンです。名前に反して「一度きり」ではなく、一定時間内は同一のトークンが再利用されます。

Nonce は以下の要素を組み合わせたハッシュから生成されます:

| 要素 | 説明 |
|---|---|
| ユーザー ID | `wp_get_current_user()->ID` |
| セッショントークン | `wp_get_session_token()` — Cookie から取得 |
| Tick（時間変数） | `wp_nonce_tick()` — 現在の時間帯を表す整数 |
| Action | Nonce のコンテキストを示す文字列 |
| `NONCE_KEY` / `NONCE_SALT` | `wp-config.php` で定義されるサイト固有の暗号鍵 |

Nonce 関連の関数は `wp-includes/pluggable.php` に定義されており、プラグインで差し替え可能（Pluggable）です。

## 2. Tick システム

### Tick の仕組み

WordPress の Nonce は「Tick」と呼ばれる時間区間に基づいて動作します。デフォルトでは Nonce の有効期間は **24 時間**（`DAY_IN_SECONDS`）で、1 Tick = **12 時間**（有効期間の半分）です。

```php
function wp_nonce_tick( $action = -1 ) {
    $nonce_life = apply_filters('nonce_life', DAY_IN_SECONDS);
    return ceil(time() / ($nonce_life / 2));
}
```

| Tick | 説明 |
|---|---|
| 現在の Tick | `ceil(time() / 43200)` — 現在の 12 時間区間 |
| 前の Tick | 現在の Tick - 1 |

### 有効期間

Nonce の有効期間は **12 時間から 24 時間**の間で変動します:

- Tick の**開始直後**に生成された Nonce: 約 24 時間有効
- Tick の**終了直前**に生成された Nonce: 約 12 時間有効

```
Tick N                    Tick N+1                  Tick N+2
├──────── 12h ────────┤├──────── 12h ────────┤├──────── 12h ────────┤
     ↑                                              ↑
     Nonce 生成                                     この時点で無効
     (Tick N で生成 → Tick N+1 まで有効)
```

## 3. 生成アルゴリズム

### `wp_create_nonce()` の内部

```php
function wp_create_nonce($action = -1) {
    $user = wp_get_current_user();
    $uid  = (int) $user->ID;

    if (!$uid) {
        $uid = apply_filters('nonce_user_logged_out', $uid, $action);
    }

    $token = wp_get_session_token();
    $i     = wp_nonce_tick($action);

    return substr(wp_hash($i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
}
```

1. ユーザー ID を取得（未ログインの場合は `nonce_user_logged_out` フィルターで変更可能）
2. セッショントークンを Cookie から取得
3. 現在の Tick 値を取得
4. `Tick|Action|UserID|Token` を `wp_hash()` でハッシュ化
5. ハッシュの末尾から 12 文字目〜10 文字を切り出し（10 文字の英数字）

### `wp_hash()` の動作

```php
function wp_hash($data, $scheme = 'auth') {
    $salt = wp_salt($scheme);  // NONCE_KEY + NONCE_SALT
    return hash_hmac('md5', $data, $salt);
}
```

`wp_hash()` は HMAC-MD5 を使用し、`NONCE_KEY` と `NONCE_SALT`（`wp-config.php` で定義）をソルトとして組み合わせます。

## 4. API リファレンス

### 生成 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_create_nonce()` | `(string\|int $action = -1): string` | Nonce トークンを生成（10 文字の英数字） |
| `wp_nonce_field()` | `(string\|int $action = -1, string $name = '_wpnonce', bool $referer = true, bool $display = true): string` | Nonce を hidden フォームフィールドとして出力 |
| `wp_nonce_url()` | `(string $actionurl, string\|int $action = -1, string $name = '_wpnonce'): string` | URL に Nonce クエリパラメータを付加 |
| `wp_nonce_tick()` | `(string\|int $action = -1): float` | 現在の Tick 値を返す |

### 検証 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_verify_nonce()` | `(string $nonce, string\|int $action = -1): int\|false` | Nonce を検証 |
| `check_admin_referer()` | `(string\|int $action = -1, string $query_arg = '_wpnonce'): int\|false` | 管理画面リクエストの Nonce を検証 |
| `check_ajax_referer()` | `(string\|int $action = -1, string\|false $query_arg = false, bool $stop = true): int\|false` | AJAX リクエストの Nonce を検証 |

### `wp_verify_nonce()` の戻り値

| 戻り値 | 意味 |
|---|---|
| `1` | Nonce は有効。現在の Tick（0〜12 時間前）で生成 |
| `2` | Nonce は有効。前の Tick（12〜24 時間前）で生成 |
| `false` | Nonce は無効または有効期限切れ |

> **注意**: 戻り値 `2` はクライアントが古い Nonce を使用していることを示します。フォーム再読み込みやトークンリフレッシュの判断に使用できます。

### `check_admin_referer()` の動作

```php
function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
    // $_REQUEST[$query_arg] から Nonce を取得して検証
    $result = wp_verify_nonce($_REQUEST[$query_arg] ?? '', $action);

    do_action('check_admin_referer', $action, $result);

    if (!$result && !(-1 === $action && ...)) {
        wp_nonce_ays($action);  // "Are you sure?" ページを表示して die
        die();
    }

    return $result;
}
```

### `check_ajax_referer()` の動作

```php
function check_ajax_referer($action = -1, $query_arg = false, $stop = true) {
    // $query_arg が false の場合、'_ajax_nonce' → '_wpnonce' の順で検索
    $nonce = $_REQUEST['_ajax_nonce'] ?? $_REQUEST['_wpnonce'] ?? '';

    $result = wp_verify_nonce($nonce, $action);

    do_action('check_ajax_referer', $action, $result);

    if ($stop && false === $result) {
        if (wp_doing_ajax()) {
            wp_die(-1, 403);
        } else {
            die('-1');
        }
    }

    return $result;
}
```

## 5. 実行フロー

### Nonce 生成と検証のフロー

```
[フォーム生成時]
wp_nonce_field('save_settings')
│
├── wp_create_nonce('save_settings')
│   ├── uid = 1, token = 'abc...', tick = 4621987
│   ├── hash = wp_hash('4621987|save_settings|1|abc...')
│   └── nonce = substr(hash, -12, 10)  → '3a5f7b2c1d'
│
└── <input type="hidden" name="_wpnonce" value="3a5f7b2c1d" />
    <input type="hidden" name="_wp_http_referer" value="/wp-admin/..." />

[フォーム送信時]
check_admin_referer('save_settings')
│
├── $nonce = $_REQUEST['_wpnonce']  → '3a5f7b2c1d'
│
├── wp_verify_nonce('3a5f7b2c1d', 'save_settings')
│   ├── uid = 1, token = 'abc...', tick = 4621987
│   │
│   ├── 現在の Tick で検証
│   │   ├── expected = substr(wp_hash('4621987|save_settings|1|abc...'), -12, 10)
│   │   └── hash_equals(expected, '3a5f7b2c1d') → true → return 1
│   │
│   ├── 前の Tick で検証（現在の Tick で不一致の場合）
│   │   ├── expected = substr(wp_hash('4621986|save_settings|1|abc...'), -12, 10)
│   │   └── hash_equals(expected, nonce) → true → return 2
│   │
│   └── 両方不一致 → return false
│
├── do_action('check_admin_referer', 'save_settings', $result)
│
└── 検証失敗時 → wp_nonce_ays() で「本当に実行しますか？」ページを表示
```

### AJAX での Nonce フロー

```
[JavaScript]
jQuery.post(ajaxurl, {
    action: 'my_action',
    _ajax_nonce: myNonce  // PHP で wp_create_nonce() した値
});

[PHP: AJAX ハンドラー]
add_action('wp_ajax_my_action', function() {
    check_ajax_referer('my_action');  // 失敗時は wp_die(-1, 403)
    // ... 処理 ...
    wp_send_json_success($data);
});
```

## 6. フック一覧

### Filter

| フック名 | パラメータ | 説明 |
|---|---|---|
| `nonce_life` | `(int $nonce_life)` | Nonce の有効期間（秒）を変更。デフォルト: `DAY_IN_SECONDS`（86400） |
| `nonce_user_logged_out` | `(int $uid, string\|int $action)` | 未ログインユーザーの UID を変更（デフォルト: `0`） |

### Action

| フック名 | パラメータ | 説明 |
|---|---|---|
| `check_admin_referer` | `(string\|int $action, int\|false $result)` | 管理画面 Nonce 検証後 |
| `check_ajax_referer` | `(string\|int $action, int\|false $result)` | AJAX Nonce 検証後 |
| `wp_verify_nonce_failed` | `(string $nonce, string\|int $action, WP_User $user, string $token)` | Nonce 検証失敗時 |

## 7. セキュリティ上の注意

### Nonce の限界

- **認証には使えない**: Nonce は CSRF 防御のためのトークンであり、ユーザー認証や認可には使用不可。`current_user_can()` で権限を別途確認すること
- **再利用可能**: 同一 Tick 内では同じ Action に対して同一の Nonce が生成される。真のワンタイムトークンではない
- **推測困難だが秘密ではない**: HTML ソースやURL に露出するため、傍受される可能性がある

### ベストプラクティス

```php
// 正しいパターン: Nonce + 権限チェック
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
check_admin_referer('my_action_nonce');

// Action 名は具体的にする
wp_create_nonce('delete_post_42');        // 良い
wp_create_nonce('delete_post');           // やや不十分
wp_create_nonce(-1);                      // 非推奨（デフォルト値）
```

### Pluggable 関数

以下の Nonce 関数はプラグインで差し替え可能です（`pluggable.php` で定義）:

| 関数 | 説明 |
|---|---|
| `wp_create_nonce()` | Nonce 生成 |
| `wp_verify_nonce()` | Nonce 検証 |
| `wp_nonce_tick()` | Tick 計算 |

差し替えは `plugins_loaded` アクションより前（`mu-plugins` 等）で行う必要があります。
