# Mailer コンポーネント

Mailer コンポーネントは、`wp_mail()` をラップ・拡張しつつ、Symfony Mailer にインスパイアされたオブジェクト指向インターフェースとスワップ可能なトランスポートによる高度な機能を提供する、WordPress 向けのモダンなメールソリューションです。

## このコンポーネントの機能

Mailer コンポーネントは、WordPress のメール処理を以下の機能で変革します：

- **WordPress 互換のシンプルな送信** - `wp_mail()` の直接的な置き換え
- **Fluent Email ビルダー** - 洗練されたメールを構築するためのチェーン可能なメソッド
- **DSN ベースのトランスポート** - Symfony Mailer 互換のファクトリパターン（Native、SES、Null）
- **テンプレートサポート** - レイアウトと継承機能
- **HTML とプレーンテキストの代替** - HTML からの自動テキスト生成
- **ファイル添付と埋め込み画像** - シンプルな API
- **カスタムヘッダーとメタデータ** - トラッキングとキャンペーン管理用
- **wp_mail() 統合** - すべての WordPress メールを WpPack Mailer 経由にリダイレクト
- **テストユーティリティ** - メール検証用のアサーション

## インストール

```bash
composer require wppack/mailer
```

## 従来の WordPress vs WpPack

### Before（従来の WordPress）

```php
// Basic wp_mail usage
function send_welcome_email($user_email, $username) {
    $to = $user_email;
    $subject = 'Welcome to ' . get_bloginfo('name');

    $message = '<html><body>';
    $message .= '<h1>Welcome ' . esc_html($username) . '!</h1>';
    $message .= '<p>Thank you for registering.</p>';
    $message .= '</body></html>';

    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    );

    wp_mail($to, $subject, $message, $headers);
}

// No built-in support for:
// - Email objects
// - Templates
// - Fluent interface
// - Easy attachments
// - HTML/text alternatives
```

### After（WpPack）

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Email;

class WelcomeEmailService
{
    public function __construct(private Mailer $mailer) {}

    public function sendWelcomeEmail(\WP_User $user): void
    {
        // Method 1: Simple send (wp_mail style)
        $this->mailer->send(
            $user->user_email,
            'Welcome to ' . get_bloginfo('name'),
            $this->getWelcomeMessage($user)
        );

        // Method 2: Fluent interface
        $this->mailer->create()
            ->to($user->user_email, $user->display_name)
            ->subject('Welcome to ' . get_bloginfo('name'))
            ->template('emails/welcome', ['user' => $user])
            ->send();

        // Method 3: Email object (Symfony style)
        $email = new Email();
        $email->to($user->user_email, $user->display_name)
            ->subject('Welcome to ' . get_bloginfo('name'))
            ->template('emails/welcome', ['user' => $user])
            ->priority(Email::PRIORITY_HIGH);

        $this->mailer->sendEmail($email);
    }
}
```

## トランスポートシステム

Symfony Mailer と同じアーキテクチャで、DSN ベースのトランスポート設定とファクトリパターンを提供します。

### TransportInterface

```php
namespace WpPack\Component\Mailer\Transport;

interface TransportInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage;

    public function __toString(): string;
}
```

### AbstractTransport

すべてのカスタムトランスポートの基底クラス。

```php
namespace WpPack\Component\Mailer\Transport;

abstract class AbstractTransport implements TransportInterface
{
    abstract protected function doSend(SentMessage $message): void;

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage;
}
```

### AbstractApiTransport

HTTP API ベースのトランスポートの基底クラス。SES API のような REST API でメールを送信するトランスポート向け。

```php
namespace WpPack\Component\Mailer\Transport;

abstract class AbstractApiTransport extends AbstractTransport
{
    abstract protected function doSendApi(
        SentMessage $sentMessage,
        Email $email,
        Envelope $envelope,
    ): ResponseInterface;
}
```

### Dsn

DSN（Data Source Name）文字列をパースするクラス。

```php
namespace WpPack\Component\Mailer\Transport;

final class Dsn
{
    public function __construct(
        private readonly string $scheme,
        private readonly string $host,
        private readonly ?string $user = null,
        private readonly ?string $password = null,
        private readonly ?int $port = null,
        /** @var array<string, string> */
        private readonly array $options = [],
    ) {}

    public static function fromString(string $dsn): self;

    public function getScheme(): string;
    public function getHost(): string;
    public function getUser(): ?string;
    public function getPassword(): ?string;
    public function getPort(): ?int;
    public function getOption(string $key, ?string $default = null): ?string;
}
```

### TransportFactoryInterface

各トランスポートパッケージが実装するファクトリインターフェース。

```php
namespace WpPack\Component\Mailer\Transport;

interface TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface;

    public function supports(Dsn $dsn): bool;
}
```

### Transport ファクトリ

DSN 文字列からトランスポートインスタンスを生成するファクトリクラス。

```php
namespace WpPack\Component\Mailer\Transport;

final class Transport
{
    /**
     * @param iterable<TransportFactoryInterface> $factories
     */
    public function __construct(iterable $factories = []) {}

    public static function fromDsn(string $dsn): TransportInterface;
}
```

### DSN 設定

`wp-config.php` で `MAILER_DSN` 定数を定義してトランスポートを切り替えます。

```php
// wp-config.php

// WordPress PHPMailer（デフォルト）
define('MAILER_DSN', 'native://default');

// Amazon SES API（wppack/amazon-mailer が必要）
define('MAILER_DSN', 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

// Amazon SES SMTP（wppack/amazon-mailer が必要）
define('MAILER_DSN', 'ses://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');

// テスト用（送信しない）
define('MAILER_DSN', 'null://default');
```

### ビルトイントランスポート

| DSN | トランスポート | 説明 |
|-----|-----------|-------------|
| `native://default` | `NativeTransport` | WordPress の `wp_mail()` / PHPMailer を使用（デフォルト） |
| `null://default` | `NullTransport` | テスト用（実際には送信しない） |

```php
use WpPack\Component\Mailer\Transport\Transport;

// DSN から自動生成
$transport = Transport::fromDsn('native://default');

// 直接インスタンス化
use WpPack\Component\Mailer\Transport\NativeTransport;
use WpPack\Component\Mailer\Transport\NullTransport;

$transport = new NativeTransport();
$transport = new NullTransport();
```

### サードパーティトランスポート

追加のトランスポートはプラグインパッケージで提供されます。

| サービス | パッケージ | DSN |
|---------|-----------|-----|
| Amazon SES | `wppack/amazon-mailer` | `ses+api://ACCESS_KEY:SECRET_KEY@default?region=REGION` |

## Email クラス

Fluent インターフェースでメールを構築します。

```php
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Address;

$email = (new Email())
    ->from(new Address('admin@example.com', 'Admin'))
    ->to('user@example.com')
    ->cc('cc@example.com')
    ->bcc('bcc@example.com')
    ->replyTo('reply@example.com')
    ->subject('Monthly Report')
    ->text('Plain text body')
    ->html('<h1>HTML body</h1>')
    ->attach('/path/to/report.pdf', 'report.pdf', 'application/pdf')
    ->embed('/path/to/logo.png', 'logo')
    ->priority(Email::PRIORITY_HIGH);
```

### Address クラス

```php
use WpPack\Component\Mailer\Address;

$address = Address::create('user@example.com');
$address = new Address('user@example.com', 'John Doe');

// Email class accepts strings or Address objects
$email->to('user@example.com');
$email->to(new Address('user@example.com', 'John'));
$email->to('user1@example.com', 'user2@example.com');
```

### 優先度とヘッダー

```php
$email->priority(Email::PRIORITY_HIGH);
$email->priority(Email::PRIORITY_NORMAL);
$email->priority(Email::PRIORITY_LOW);

$email->addHeader('X-Campaign', 'spring-sale')
    ->addHeader('X-Tracking-ID', uniqid());

$email->returnPath('bounces@example.com');
```

### 添付ファイルと埋め込み画像

```php
$email = new Email();
$email->to('customer@example.com')
    ->subject('Your Documents')
    ->attach('/path/to/document.pdf')
    ->attach('/path/to/file.pdf', 'Invoice-2024.pdf')
    ->attachFromString($csvContent, 'data.csv', 'text/csv')
    ->embed('/path/to/logo.png', 'company-logo');

// Reference embedded image in HTML
$email->html('<img src="cid:company-logo" alt="Logo">');
```

### HTML とプレーンテキストの代替

```php
// Automatic text generation from HTML
$mailer->create()
    ->to('user@example.com')
    ->subject('Newsletter')
    ->html('<h1>Newsletter</h1><p>This is our newsletter.</p>')
    ->generateText()
    ->send();

// Manual text alternative
$mailer->create()
    ->to('user@example.com')
    ->subject('Newsletter')
    ->html($htmlVersion)
    ->text($plainTextVersion)
    ->send();

```

## Mailer クラス

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Transport\Transport;

// DSN からトランスポートを自動生成
$transport = Transport::fromDsn(MAILER_DSN);
$mailer = new Mailer($transport);

$sentMessage = $mailer->send($email);
$messageId = $sentMessage->getMessageId();
```

## wp_mail() 統合

`WpMailIntegration` は、すべての WordPress `wp_mail()` 呼び出しを WpPack Mailer 経由にリダイレクトし、設定されたトランスポートを既存のすべてのプラグインとテーマに適用します。

```php
use WpPack\Component\Mailer\WordPress\WpMailIntegration;

WpMailIntegration::override($mailer);

// All wp_mail() calls now use WpPack Mailer
wp_mail('user@example.com', 'Subject', 'Body');
```

## テンプレートシステム

### 基本テンプレート

```php
use WpPack\Component\Mailer\TemplatedEmail;

$email = (new TemplatedEmail())
    ->to('user@example.com')
    ->subject('Welcome!')
    ->template('emails/welcome')
    ->context([
        'username' => 'John',
        'activationUrl' => 'https://example.com/activate/abc123',
    ]);

$mailer->send($email);
```

### テンプレート継承

```php
// emails/layouts/base.php
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { background: #333; color: white; padding: 20px; }
        .content { padding: 20px; }
        .footer { background: #f0f0f0; padding: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= get_bloginfo('name') ?></h1>
    </div>
    <div class="content">
        <?php $this->block('content') ?>
    </div>
    <div class="footer">
        <?php $this->block('footer') ?>
            <p>&copy; <?= date('Y') ?> <?= get_bloginfo('name') ?></p>
        <?php $this->endblock() ?>
    </div>
</body>
</html>

// emails/welcome.php
<?php $this->extend('layouts/base') ?>

<?php $this->block('content') ?>
    <h2>Welcome, <?= esc_html($user->display_name) ?>!</h2>
    <p>Thank you for joining our community.</p>
    <p><a href="<?= esc_url($login_url) ?>">Login to your account</a></p>
<?php $this->endblock() ?>
```

## クイックスタート

### お問い合わせフォーム

```php
class ContactFormHandler
{
    public function __construct(private Mailer $mailer) {}

    public function handleSubmission(): void
    {
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $message = sanitize_textarea_field($_POST['message']);

        $this->mailer->create()
            ->to(get_option('admin_email'))
            ->replyTo($email, $name)
            ->subject('Contact Form Submission')
            ->html(sprintf(
                '<h2>New Contact Form Submission</h2>
                <p><strong>From:</strong> %s &lt;%s&gt;</p>
                <p><strong>Message:</strong></p><p>%s</p>',
                esc_html($name),
                esc_html($email),
                nl2br(esc_html($message))
            ))
            ->text(sprintf("From: %s <%s>\n\nMessage:\n%s", $name, $email, $message))
            ->send();
    }
}
```

### 注文確認（添付ファイル付き）

```php
class OrderEmail
{
    public function __construct(private Mailer $mailer) {}

    public function sendOrderConfirmation($order): void
    {
        $invoicePath = $this->generateInvoice($order);

        $email = new Email();
        $email->to($order->get_billing_email(), $order->get_formatted_billing_full_name())
            ->subject('Order #' . $order->get_order_number() . ' Confirmed')
            ->html($this->getOrderHtml($order))
            ->text($this->getOrderText($order))
            ->attach($invoicePath, 'invoice-' . $order->get_order_number() . '.pdf')
            ->priority(Email::PRIORITY_HIGH);

        $this->mailer->sendEmail($email);
    }
}
```

### ユーザー通知

```php
class UserNotifications
{
    public function __construct(private Mailer $mailer) {}

    public function notifyPasswordReset(\WP_User $user, string $resetLink): void
    {
        $this->mailer->create()
            ->to($user->user_email, $user->display_name)
            ->subject('Password Reset Request')
            ->template('password-reset', [
                'user' => $user,
                'reset_link' => $resetLink,
                'expires' => '24 hours'
            ])
            ->priority(Email::PRIORITY_HIGH)
            ->send();
    }
}
```

### ニュースレター（パーソナライゼーション付き）

```php
class NewsletterService
{
    public function __construct(private Mailer $mailer) {}

    public function sendMonthlyNewsletter(): void
    {
        $subscribers = get_users([
            'meta_key' => 'newsletter_subscriber',
            'meta_value' => 'yes'
        ]);

        foreach ($subscribers as $subscriber) {
            $email = new Email();
            $email->to($subscriber->user_email, $subscriber->display_name)
                ->subject('Monthly Newsletter - ' . date('F Y'))
                ->template('newsletter', [
                    'subscriber' => $subscriber,
                    'articles' => $this->getLatestArticles(),
                    'unsubscribe_url' => $this->getUnsubscribeUrl($subscriber)
                ])
                ->addHeader('List-Unsubscribe', '<' . $this->getUnsubscribeUrl($subscriber) . '>');

            $this->mailer->sendEmail($email);
        }
    }
}
```

## Named Hook アトリビュート

### メール設定

```php
#[WpMailFromFilter(priority?: int = 10)]             // 「From」メールアドレスを変更
#[WpMailFromNameFilter(priority?: int = 10)]         // 「From」名を変更
#[WpMailContentTypeFilter(priority?: int = 10)]      // Content-Type を変更（text/html）
#[WpMailCharsetFilter(priority?: int = 10)]          // 文字エンコーディングを変更
```

### メール処理

```php
#[WpMailFilter(priority?: int = 10)]                 // 送信前にメールパラメータを変更
#[PreWpMailFilter(priority?: int = 10)]              // メール送信のショートサーキットまたはリダイレクト
```

### メールステータス

```php
#[WpMailSucceededAction(priority?: int = 10)]        // メール送信成功後
#[WpMailFailedAction(priority?: int = 10)]           // メール配信失敗の処理
```

### PHPMailer 設定

```php
#[PhpMailerInitAction(priority?: int = 10)]          // PHPMailer/SMTP 設定の構成
```

### 例：メールカスタマイズ

```php
use WpPack\Component\Mailer\Attribute\WpMailFromFilter;
use WpPack\Component\Mailer\Attribute\WpMailFromNameFilter;
use WpPack\Component\Mailer\Attribute\WpMailContentTypeFilter;
use WpPack\Component\Mailer\Attribute\WpMailFilter;
use WpPack\Component\Mailer\Attribute\WpMailFailedAction;
use WpPack\Component\Mailer\Attribute\PhpMailerInitAction;

class EmailCustomization
{
    #[WpMailFromFilter]
    public function setFromEmail(): string
    {
        return 'noreply@mysite.com';
    }

    #[WpMailFromNameFilter]
    public function setFromName(): string
    {
        return 'My Site';
    }

    #[WpMailContentTypeFilter]
    public function setContentType(): string
    {
        return 'text/html; charset=UTF-8';
    }

    #[WpMailFilter]
    public function modifyMailData(array $args): array
    {
        $args['headers'] ??= [];
        $args['headers'][] = 'X-Mailer: WpPack Mailer';
        return $args;
    }

    #[WpMailFailedAction]
    public function onMailFailed(\WP_Error $error): void
    {
        error_log('Mail failed: ' . $error->get_error_message());
    }

    #[PhpMailerInitAction]
    public function onPhpMailerInit(\PHPMailer\PHPMailer\PHPMailer $phpmailer): void
    {
        $phpmailer->isSMTP();
        $phpmailer->Host = $_ENV['SMTP_HOST'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $_ENV['SMTP_USERNAME'];
        $phpmailer->Password = $_ENV['SMTP_PASSWORD'];
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->Port = 587;
    }
}
```

### 例：メールライフサイクル

```php
use WpPack\Component\Mailer\Attribute\PreWpMailFilter;
use WpPack\Component\Mailer\Attribute\WpMailSucceededAction;

class EmailLifecycleHooks
{
    #[PreWpMailFilter]
    public function beforeSend(?bool $return, array $atts): ?bool
    {
        $this->logger->info('Sending email', [
            'to' => $atts['to'],
            'subject' => $atts['subject']
        ]);

        if ($this->isBlocklisted($atts['to'])) {
            $this->logger->warning('Email blocked', ['to' => $atts['to']]);
            return false;
        }

        return null; // Continue sending
    }

    #[WpMailSucceededAction]
    public function onMailSuccess(array $mailData): void
    {
        $this->metrics->increment('emails.sent');
        $this->logger->info('Email sent successfully', [
            'to' => $mailData['to'],
            'subject' => $mailData['subject']
        ]);
    }
}
```

### 例：メール送信失敗とリトライ

```php
use WpPack\Component\Mailer\Attribute\WpMailFailedAction;

class EmailFailureHandler
{
    #[WpMailFailedAction]
    public function onEmailFailed(\WP_Error $error): void
    {
        $mailData = $error->get_error_data();

        error_log(sprintf(
            'Email failed: %s (Code: %s)',
            $error->get_error_message(),
            $error->get_error_code()
        ));

        if ($this->isTemporaryFailure($error)) {
            $this->queueForRetry($mailData);
        }

        if ($this->isCriticalEmail($mailData)) {
            $this->notifyAdminOfFailure($error, $mailData);
        }
    }
}
```

## テスト

```php
use WpPack\Component\Mailer\Test\TestMailer;

$testMailer = new TestMailer();

$testMailer->send($email);

// Assertions
$testMailer->assertSent(1);
$testMailer->assertSentTo('user@example.com');
$testMailer->assertSubjectContains('Welcome');
```

### MailerAssertions トレイトの使用

```php
use WpPack\Component\Mailer\Test\MailerAssertions;

class EmailTest extends WP_UnitTestCase
{
    use MailerAssertions;

    public function test_welcome_email_sent(): void
    {
        $user = $this->factory->user->create_and_get();

        send_welcome_email($user);

        $this->assertEmailSent(function(Email $email) use ($user) {
            return $email->getTo()[0]->getAddress() === $user->user_email
                && str_contains($email->getSubject(), 'Welcome');
        });

        $this->assertEmailCount(1);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|-------------|
| `Mailer` | メール送信のエントリーポイント |
| `Email` | メールメッセージビルダー |
| `TemplatedEmail` | テンプレートベースのメール |
| `Address` | メールアドレス（名前付き） |
| `SentMessage` | 送信結果 |
| `Transport\TransportInterface` | トランスポートインターフェース |
| `Transport\AbstractTransport` | トランスポート基底クラス |
| `Transport\AbstractApiTransport` | API トランスポート基底クラス |
| `Transport\TransportFactoryInterface` | トランスポートファクトリインターフェース |
| `Transport\Transport` | DSN ファクトリ |
| `Transport\Dsn` | DSN パーサー |
| `Transport\NativeTransport` | WordPress PHPMailer ラッパー（デフォルト） |
| `Transport\NullTransport` | テスト用トランスポート |
| `WordPress\WpMailIntegration` | wp_mail() リダイレクト |
| `Test\TestMailer` | テスト用 Mailer |

## このコンポーネントの使用場面

**最適な用途：**
- `wp_mail()` のドロップイン置き換え
- 添付ファイル付きの複雑な HTML メール
- 一貫したブランディングのメールテンプレート
- トランザクションメール（注文確認、パスワードリセット）
- マーケティングメール（トラッキング付きニュースレター）
- ユーザーおよび管理者通知

**代替を検討すべき場合：**
- `wp_mail()` で十分なシンプルなテキストのみのメール
- 独自の SDK を持つ外部メールサービス

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress メールフックおよびフィルター統合用

### 推奨
- **Templating コンポーネント** - 高度なメールテンプレート機能用
