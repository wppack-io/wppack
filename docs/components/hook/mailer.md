## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Mailer/Subscriber/`

### メール送信設定フック

#### #[WpMailFromFilter]

**WordPress Hook:** `wp_mail_from`

```php
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailFromFilter;

class MailFromCustomizer
{
    #[WpMailFromFilter]
    public function customizeFrom(string $from): string
    {
        return 'noreply@example.com';
    }
}
```

#### #[WpMailFromNameFilter]

**WordPress Hook:** `wp_mail_from_name`

```php
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailFromNameFilter;

class MailFromNameCustomizer
{
    #[WpMailFromNameFilter]
    public function customizeFromName(string $name): string
    {
        return get_bloginfo('name');
    }
}
```

#### #[WpMailContentTypeFilter]

**WordPress Hook:** `wp_mail_content_type`

```php
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailContentTypeFilter;

class MailContentTypeCustomizer
{
    #[WpMailContentTypeFilter]
    public function setHtmlContentType(string $contentType): string
    {
        return 'text/html';
    }
}
```

#### #[WpMailCharsetFilter]

**WordPress Hook:** `wp_mail_charset`

```php
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailCharsetFilter;

class MailCharsetCustomizer
{
    #[WpMailCharsetFilter]
    public function setCharset(string $charset): string
    {
        return 'UTF-8';
    }
}
```

### メール内容フック

#### #[WpMailFilter]

**WordPress Hook:** `wp_mail`

```php
use WPPack\Component\Hook\Attribute\Mailer\Filter\WpMailFilter;

class MailModifier
{
    #[WpMailFilter]
    public function modifyMail(array $args): array
    {
        // BCC に管理者を追加
        $args['headers'][] = 'Bcc: admin@example.com';

        return $args;
    }
}
```

#### #[PreWpMailFilter]

**WordPress Hook:** `pre_wp_mail`

```php
use WPPack\Component\Hook\Attribute\Mailer\Filter\PreWpMailFilter;

class MailGatekeeper
{
    #[PreWpMailFilter]
    public function gatekeepMail(null|bool $return, array $atts): null|bool
    {
        // 特定ドメインへの送信をブロック
        if (str_ends_with($atts['to'], '@blocked.example.com')) {
            return false;
        }

        return null;
    }
}
```

### メール送信結果フック

#### #[WpMailSucceededAction]

**WordPress Hook:** `wp_mail_succeeded`

```php
use WPPack\Component\Hook\Attribute\Mailer\Action\WpMailSucceededAction;

class MailSuccessHandler
{
    #[WpMailSucceededAction]
    public function onMailSucceeded(array $mailData): void
    {
        // 送信成功をログに記録
        do_action('wppack.mailer.log', 'success', $mailData['to'], $mailData['subject']);
    }
}
```

#### #[WpMailFailedAction]

**WordPress Hook:** `wp_mail_failed`

```php
use Psr\Log\LoggerInterface;
use WPPack\Component\Hook\Attribute\Mailer\Action\WpMailFailedAction;

class MailFailureHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[WpMailFailedAction]
    public function onMailFailed(\WP_Error $error): void
    {
        // 送信失敗をログに記録
        $this->logger->error('Mail send failed', ['error' => $error->get_error_message()]);
    }
}
```

#### #[PhpMailerInitAction]

**WordPress Hook:** `phpmailer_init`

```php
use WPPack\Component\Hook\Attribute\Mailer\Action\PhpMailerInitAction;

class PhpMailerConfigurator
{
    #[PhpMailerInitAction]
    public function configureSMTP(\PHPMailer\PHPMailer\PHPMailer $phpmailer): void
    {
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.example.com';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 587;
        $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }
}
```

## クイックリファレンス

```php
// 送信設定
#[WpMailFromFilter(priority?: int = 10)]         // 送信元メールアドレス
#[WpMailFromNameFilter(priority?: int = 10)]     // 送信元名
#[WpMailContentTypeFilter(priority?: int = 10)]  // Content-Type
#[WpMailCharsetFilter(priority?: int = 10)]      // 文字セット

// メール内容
#[WpMailFilter(priority?: int = 10)]             // メール引数の変更
#[PreWpMailFilter(priority?: int = 10)]          // 送信前のゲートキーパー

// 送信結果
#[WpMailSucceededAction(priority?: int = 10)]    // 送信成功時の処理
#[WpMailFailedAction(priority?: int = 10)]       // 送信失敗時の処理
#[PhpMailerInitAction(priority?: int = 10)]      // PHPMailer の初期化設定
```
