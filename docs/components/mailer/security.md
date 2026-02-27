# Mailer セキュリティガイド

Mailer コンポーネントのセキュリティ設計と推奨設定について説明します。

## ヘッダーインジェクション防止

### Address クラス

メールアドレスと名前に `\r`, `\n`, `\0` を含む値を拒否します。

```php
// 安全: InvalidArgumentException がスローされる
new Address("user@example.com\r\nBcc: evil@example.com");
new Address('user@example.com', "Name\nEvil-Header: value");
```

### Headers クラス

ヘッダー名・値に CRLF インジェクションを防止します。

```php
$headers = new Headers();
// 安全: InvalidArgumentException がスローされる
$headers->add("X-Bad\r\n", 'value');
$headers->add('X-Custom', "value\r\nEvil: header");
```

### メールアドレスバリデーション

`filter_var($address, FILTER_VALIDATE_EMAIL)` で検証します。

```php
// 安全: InvalidArgumentException がスローされる
new Address('not-an-email');
```

## クレデンシャル管理

### DSN のベストプラクティス

`Dsn::__toString()` はパスワードをマスクして出力します。ログや例外メッセージに安全に使用できます。

```php
$dsn = Dsn::fromString('ses+api://ACCESS_KEY:SECRET_KEY@default');
echo $dsn; // ses+api://ACCESS_KEY:****@default
```

### 環境変数の使用（推奨）

```php
// wp-config.php
define('MAILER_DSN', getenv('MAILER_DSN'));
```

### IAM ロールの使用（最も安全）

DSN にクレデンシャルを含めず、IAM ロールを使用します。

```php
define('MAILER_DSN', 'ses://default?region=ap-northeast-1');
```

async-aws が自動的に Instance Profile や ECS タスクロールから認証情報を取得します。

### TransportException のクレデンシャル保護

AWS SDK の例外をラップし、クレデンシャルをメッセージから除去します。`wp_mail_failed` アクションに渡されるエラーデータにもクレデンシャルは含まれません。

## SMTP セキュリティ

### デフォルト設定

| トランスポート | プロトコル | ポート | 暗号化 |
|--------------|----------|-------|--------|
| `smtp://` | STARTTLS | 587 | TLS |
| `ses+smtp://` | STARTTLS | 587 | TLS |
| `ses+smtps://` | SSL/TLS | 465 | SSL |

### TLS の強制

SmtpTransport はデフォルトで TLS（ポート 587）を使用します。平文 SMTP（ポート 25）は明示的な設定が必要です。

```php
// TLS（デフォルト、推奨）
define('MAILER_DSN', 'smtp://user:pass@smtp.example.com:587?encryption=tls');

// SSL
define('MAILER_DSN', 'smtp://user:pass@smtp.example.com:465?encryption=ssl');
```

## 入力バリデーション

### 添付ファイル

パスの存在確認と `is_readable()` チェックを行います。

```php
// 安全: InvalidArgumentException がスローされる
new Attachment('/nonexistent/file.pdf');
new Attachment('/etc/shadow'); // 読み取り権限がない場合
```

### DSN パース

不正な DSN は `InvalidArgumentException` で拒否します。

```php
// 安全: InvalidArgumentException がスローされる
Dsn::fromString('not-a-valid-dsn');
```

### Configuration Set 名

SES の Configuration Set 名はファクトリ経由で安全に処理されます。

## AWS SES 固有のセキュリティ

### 最小権限の IAM ポリシー

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail"
            ],
            "Resource": "arn:aws:ses:REGION:ACCOUNT_ID:identity/DOMAIN"
        }
    ]
}
```

### Configuration Set

SES Configuration Set を使用してイベント通知（配信、バウンス、苦情）を管理できます。

```php
define('MAILER_DSN', 'ses://default?region=ap-northeast-1&configuration_set=my-config');
```

## エラーハンドリングとログ

### wp_mail_failed アクション

送信失敗時に発火するアクション。クレデンシャルを含まない安全なエラーデータが渡されます。

```php
add_action('wp_mail_failed', function (\WP_Error $error) {
    error_log('Mail failed: ' . $error->get_error_message());
});
```

### TransportException

内部例外をチェーンし、ユーザーに見せるメッセージからはクレデンシャルを除去します。

```php
try {
    $mailer->send($email);
} catch (TransportException $e) {
    // メッセージにクレデンシャルは含まれない
    error_log($e->getMessage());
}
```

## PHPMailer セキュリティ設定

PhpMailer は常に例外モード（`true`）で初期化されます。

```php
// PhpMailer は常に例外モードで動作
$phpMailer = new PhpMailer(true);
```

## 推奨設定チェックリスト

- [ ] IAM ロールを使用してクレデンシャルを DSN に含めない
- [ ] SMTP は TLS/SSL を使用し、平文を避ける
- [ ] `MAILER_DSN` は環境変数経由で設定する
- [ ] SES Configuration Set でイベント通知を設定する
- [ ] IAM ポリシーで送信元ドメインを制限する
- [ ] `wp_mail_failed` アクションでエラーをログに記録する
- [ ] 本番環境では `null://default` を使用しない
- [ ] 定期的に IAM アクセスキーをローテーションする（使用する場合）
