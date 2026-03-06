# WordPress Mail API 仕様

## 1. 概要

WordPress のメール送信は `wp_mail()` 関数を中心に構成されています。内部的には PHPMailer ライブラリ（WordPress にバンドル）を使用し、デフォルトでは PHP の `mail()` 関数経由で送信します。プラグインは `phpmailer_init` アクションや `pre_wp_mail` フィルターを通じて SMTP 設定の変更やトランスポートの差し替えが可能です。

`wp_mail()` は Pluggable 関数（`pluggable.php` で定義）であるため、プラグインやテーマが同名の関数を先に定義することで完全に置き換え可能です。

### 主要クラス・ファイル

| クラス/ファイル | 説明 |
|---|---|
| `wp_mail()` | メール送信のメイン関数（`pluggable.php`） |
| `PHPMailer\PHPMailer\PHPMailer` | メール構築・送信ライブラリ（`wp-includes/PHPMailer/`） |
| `PHPMailer\PHPMailer\SMTP` | SMTP プロトコル実装 |
| `PHPMailer\PHPMailer\Exception` | PHPMailer 例外クラス |

### グローバル変数

| 変数 | 型 | 説明 |
|---|---|---|
| `$phpmailer` | `PHPMailer\PHPMailer\PHPMailer` | グローバルな PHPMailer インスタンス。`wp_mail()` 内で初期化・再利用される |

## 2. データ構造

### PHPMailer の主要プロパティ

`wp_mail()` が設定する PHPMailer の主要プロパティ:

| プロパティ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `$Mailer` | `string` | `'mail'` | 送信方式（`'mail'`, `'smtp'`, `'sendmail'`） |
| `$From` | `string` | `'wordpress@{domain}'` | 送信元メールアドレス |
| `$FromName` | `string` | `'WordPress'` | 送信元名 |
| `$Sender` | `string` | `''` | Return-Path（エンベロープ送信者） |
| `$ContentType` | `string` | `'text/plain'` | Content-Type |
| `$CharSet` | `string` | `'UTF-8'` (WP default) | 文字エンコーディング |
| `$Encoding` | `string` | `'8bit'` | 転送エンコーディング |
| `$Host` | `string` | `'localhost'` | SMTP ホスト |
| `$Port` | `int` | `25` | SMTP ポート |
| `$SMTPAuth` | `bool` | `false` | SMTP 認証の有効/無効 |
| `$SMTPSecure` | `string` | `''` | 暗号化方式（`'tls'`, `'ssl'`, `''`） |

### `wp_mail()` の引数

```php
function wp_mail(
    string|array $to,           // 宛先（文字列またはアドレス配列）
    string $subject,            // 件名
    string $message,            // 本文
    string|array $headers = '', // ヘッダー（文字列または配列）
    string|array $attachments = [] // 添付ファイルパス
): bool
```

### ヘッダーの解析

`$headers` は文字列（改行区切り）または配列で指定可能です。以下のヘッダーが特別に処理されます:

| ヘッダー | 処理 |
|---|---|
| `From:` | `$phpmailer->setFrom()` に反映。名前付き形式 `"Name <email>"` に対応 |
| `Content-Type:` | `$phpmailer->ContentType` に反映。`charset=` パラメータも解析 |
| `Cc:` | `$phpmailer->addCc()` で CC 追加 |
| `Bcc:` | `$phpmailer->addBcc()` で BCC 追加 |
| `Reply-To:` | `$phpmailer->addReplyTo()` で Reply-To 追加 |
| その他 | `$phpmailer->addCustomHeader()` でカスタムヘッダーとして追加 |

## 3. API リファレンス

### メール送信

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_mail()` | `(string\|array $to, string $subject, string $message, string\|array $headers = '', string\|array $attachments = []): bool` | メールを送信。成功時 `true`、失敗時 `false` |

### メール関連ユーティリティ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_new_user_notification()` | `(int $user_id, null $deprecated = null, string $notify = '')` | 新規ユーザー登録通知メール |
| `wp_password_change_notification()` | `(WP_User $user)` | パスワード変更通知メール（管理者向け） |
| `wp_notify_postauthor()` | `(int\|WP_Comment $comment_id, string $deprecated = null): bool` | コメント通知メール（投稿者向け） |
| `wp_notify_moderator()` | `(int $comment_id): true` | コメントモデレーション通知メール |
| `retrieve_password()` | `(string $user_login = null): true\|WP_Error` | パスワードリセットメール送信 |

これらの通知関数はすべて内部的に `wp_mail()` を呼び出します。

## 4. 実行フロー

### `wp_mail()` のフロー

```
wp_mail($to, $subject, $message, $headers, $attachments)
│
├── 【フィルター】 pre_wp_mail (null, $atts)
│   └── null 以外を返すとショートサーキット（メール送信をスキップ）
│   └── $atts = compact('to', 'subject', 'message', 'headers', 'attachments')
│
├── 【フィルター】 wp_mail ($atts)
│   └── $to, $subject, $message, $headers, $attachments を一括フィルタリング
│   └── 配列のキーを個別に書き換え可能
│
├── $headers の解析
│   ├── 文字列の場合 → explode("\n", $headers) で配列化
│   ├── From, Content-Type, Cc, Bcc, Reply-To を個別に抽出
│   └── その他はカスタムヘッダーとして保持
│
├── PHPMailer インスタンスの初期化
│   ├── global $phpmailer の存在チェック
│   ├── 未初期化または PHPMailer でない場合
│   │   └── require PHPMailer, SMTP, Exception クラス
│   │   └── new PHPMailer(true)  // exceptions 有効
│   └── clearAllRecipients(), clearAttachments() etc. でリセット
│
├── From の設定
│   ├── デフォルト: wordpress@{SERVER_NAME からドットで始まるサブドメインを除去}
│   ├── 【フィルター】 wp_mail_from ($from_email)
│   ├── 【フィルター】 wp_mail_from_name ($from_name)
│   └── $phpmailer->setFrom($from_email, $from_name, false)
│
├── 宛先の設定
│   ├── $to が文字列の場合 → explode(',', $to) で配列化
│   └── 各アドレスを $phpmailer->addAddress()
│
├── Content-Type の判定
│   ├── ヘッダーで明示されている場合 → その値を使用
│   ├── 未指定の場合 → 【フィルター】 wp_mail_content_type ('text/plain')
│   └── 'text/html' の場合 → $phpmailer->isHTML(true)
│
├── 文字エンコーディングの設定
│   ├── ヘッダーの charset パラメータ or
│   └── 【フィルター】 wp_mail_charset (get_bloginfo('charset'))
│
├── Cc / Bcc / Reply-To の設定
│   └── 各ヘッダーから解析したアドレスを addCc/addBcc/addReplyTo
│
├── カスタムヘッダーの設定
│   └── $phpmailer->addCustomHeader($name, $value)
│
├── 件名・本文の設定
│   ├── $phpmailer->Subject = $subject
│   └── $phpmailer->Body = $message
│
├── 添付ファイルの設定
│   └── 各ファイルパスを $phpmailer->addAttachment()
│
├── 【アクション】 phpmailer_init (&$phpmailer)
│   └── PHPMailer インスタンスを直接操作可能
│   └── SMTP 設定の変更、Mailer の差し替え等に使用
│
├── $phpmailer->send()
│   ├── 成功 → $send = true
│   └── 例外 → $mail_error_data に保存、$send = false
│
├── 失敗時
│   └── 【アクション】 wp_mail_failed (WP_Error)
│       └── WP_Error にはエラーメッセージと mail_data が含まれる
│
├── 成功時
│   └── 【アクション】 wp_mail_succeeded ($mail_data)
│
└── return $send (bool)
```

### デフォルト From アドレスの生成ロジック

```php
$sitename = wp_parse_url(network_home_url(), PHP_URL_HOST);

// www. プレフィックスがある場合は除去
if (str_starts_with($sitename, 'www.')) {
    $sitename = substr($sitename, 4);
}

$from_email = 'wordpress@' . $sitename;
```

### SMTP 設定の例

`phpmailer_init` アクションでの SMTP 設定:

```php
add_action('phpmailer_init', function (PHPMailer $phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = 'smtp.example.com';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = 587;
    $phpmailer->Username   = 'user@example.com';
    $phpmailer->Password   = 'secret';
    $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
});
```

### PHPMailer のリセット処理

`wp_mail()` は毎回の呼び出しで PHPMailer インスタンスを再利用しますが、以下のリセットを行います:

```php
$phpmailer->clearAllRecipients();  // To, Cc, Bcc を全クリア
$phpmailer->clearAttachments();    // 添付ファイルをクリア
$phpmailer->clearCustomHeaders();  // カスタムヘッダーをクリア
$phpmailer->clearReplyTos();       // Reply-To をクリア
$phpmailer->Body    = '';
$phpmailer->AltBody = '';
```

> **注意**: `$phpmailer->Mailer` や SMTP 設定はリセットされません。`phpmailer_init` で設定した SMTP 構成は次の `wp_mail()` 呼び出しでも維持されます。

## 5. フック一覧

### フィルター

| フック名 | パラメータ | 説明 |
|---|---|---|
| `pre_wp_mail` | `(null\|bool $return, array $atts)` | `wp_mail()` 実行前のショートサーキット。null 以外を返すとメール送信をスキップしその値を返す |
| `wp_mail` | `(array $atts)` | 送信パラメータの一括フィルタリング。`$atts` は `to`, `subject`, `message`, `headers`, `attachments` のキーを持つ配列 |
| `wp_mail_from` | `(string $from_email)` | 送信元メールアドレス |
| `wp_mail_from_name` | `(string $from_name)` | 送信元名 |
| `wp_mail_content_type` | `(string $content_type)` | Content-Type（デフォルト `'text/plain'`） |
| `wp_mail_charset` | `(string $charset)` | 文字エンコーディング |

### アクション

| フック名 | パラメータ | 説明 |
|---|---|---|
| `phpmailer_init` | `(PHPMailer &$phpmailer)` | PHPMailer インスタンスの初期化後、`send()` 前に呼ばれる。SMTP 設定やトランスポート変更に使用 |
| `wp_mail_failed` | `(WP_Error $error)` | メール送信失敗時。`$error->get_error_data()` に `$mail_data` 配列が含まれる |
| `wp_mail_succeeded` | `(array $mail_data)` | メール送信成功時。`$mail_data` は `to`, `subject`, `message`, `headers`, `attachments` を含む |

### 通知関連フィルター

| フック名 | パラメータ | 説明 |
|---|---|---|
| `wp_new_user_notification_email` | `(array $email, WP_User $user, string $blogname)` | 新規ユーザー向け通知メールの内容 |
| `wp_new_user_notification_email_admin` | `(array $email, WP_User $user, string $blogname)` | 管理者向け新規ユーザー通知メールの内容 |
| `password_change_email` | `(array $email, array $user, array $userdata)` | パスワード変更メールの内容 |
| `email_change_email` | `(array $email, array $user, array $userdata)` | メールアドレス変更メールの内容 |
| `retrieve_password_title` | `(string $title, string $user_login, WP_User $user_data)` | パスワードリセットメールの件名 |
| `retrieve_password_message` | `(string $message, string $key, string $user_login, WP_User $user_data)` | パスワードリセットメールの本文 |
| `retrieve_password_email` | `(array $email, string $key, string $user_login, WP_User $user_data)` | パスワードリセットメールの全体（WordPress 6.0+） |
| `comment_notification_recipients` | `(string[] $emails, int $comment_id)` | コメント通知メールの受信者 |
| `comment_notification_text` | `(string $text, int $comment_id)` | コメント通知メールの本文 |
| `comment_notification_subject` | `(string $subject, int $comment_id)` | コメント通知メールの件名 |
| `comment_notification_headers` | `(string $headers, int $comment_id)` | コメント通知メールのヘッダー |
| `comment_moderation_recipients` | `(string[] $emails, int $comment_id)` | モデレーション通知メールの受信者 |
| `comment_moderation_text` | `(string $text, int $comment_id)` | モデレーション通知メールの本文 |
| `comment_moderation_subject` | `(string $subject, int $comment_id)` | モデレーション通知メールの件名 |

## 6. Pluggable 関数としての特性

`wp_mail()` は `pluggable.php` で `if (!function_exists('wp_mail'))` ガードの中に定義されています。プラグインが `plugins_loaded` より前（例: mu-plugins）で `wp_mail()` を定義すると、WordPress のデフォルト実装は読み込まれません。

```php
// mu-plugins/custom-mailer.php
function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
    // 完全にカスタムな実装
    // ※ フィルター・アクションの発火は自己責任
}
```

> **注意**: Pluggable 関数を置き換える場合、`wp_mail` フィルターや `phpmailer_init` アクション等のフックが発火しなくなる可能性があります。他のプラグインとの互換性に注意が必要です。
