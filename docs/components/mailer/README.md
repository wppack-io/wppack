# Mailer コンポーネント

**パッケージ:** `wppack/mailer`
**名前空間:** `WpPack\Component\Mailer\`
**レイヤー:** Abstraction

WordPress のメール送信をモダン化するコンポーネント。PHPMailer を継承した `PhpMailer` で WordPress のグローバル `$phpmailer` を差し替え、DSN ベースのトランスポート設定とオブジェクト指向の Email API を提供します。

## インストール

```bash
composer require wppack/mailer
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// wp_mail() の直接使用
$headers = ['Content-Type: text/html; charset=UTF-8'];
wp_mail('user@example.com', 'Welcome', '<h1>Hello</h1>', $headers);
```

### After（WpPack）

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Email;
use WpPack\Component\Mailer\Transport\Transport;

// DSN からトランスポートを生成して WordPress に登録
$mailer = new Mailer('ses+api://KEY:SECRET@default?region=ap-northeast-1');
$mailer->boot();

// または TransportInterface を直接渡す
$transport = Transport::fromDsn('ses+api://KEY:SECRET@default?region=ap-northeast-1');
$mailer = new Mailer($transport);
$mailer->boot();

// パス 1: wp_mail() がそのまま SES 経由になる
wp_mail('user@example.com', 'Welcome', '<h1>Hello</h1>');

// パス 2: 型安全な Symfony スタイル API（推奨）
$email = (new Email())
    ->from('noreply@example.com')
    ->to('user@example.com')
    ->subject('Welcome!')
    ->html('<h1>Hello</h1>')
    ->text('Hello');

$mailer->send($email);
```

## 2つの送信パス

### パス 1: wp_mail() 経由（WordPress レガシー互換）

```
wp_mail()
  ↓
Mailer::onWpMail()  ← wp_mail フィルターでグローバル $phpmailer を差し替え
  ↓
WordPress が PHPMailer を設定（From, To, Body, Attachments...）
  ↓
WordPress が wp_mail_from, wp_mail_content_type 等のフィルターを適用
  ↓
phpmailer_init アクション（WordPress が発火、他プラグインが設定変更可能）
  ↓
PhpMailer::send() → preSend() → postSend()
  ↓
postSend() がトランスポートの send() を呼び出し
  ↓
wp_mail_succeeded / wp_mail_failed
```

PhpMailer にトランスポートが登録済みのため、`postSend()` が直接 Transport の `send()` を呼び出します。サードパーティプラグインや既存のテーマが使う `wp_mail()` がそのまま設定したトランスポート（SES 等）経由になります。

### パス 2: $mailer->send() 直接送信（推奨）

```
$mailer->send($email)
  ↓
PhpMailer のメッセージ状態をクリア（clearAllRecipients 等）
  ↓
Email の内容で PHPMailer を設定（populatePhpMailer）
  ↓
wp_mail_from / wp_mail_from_name フィルターを適用
  ↓
phpmailer_init アクションを発火（プラグイン互換）
  ↓
PhpMailer::send() → preSend() → postSend()
  ↓
postSend() がトランスポートの send() を呼び出し
  ↓
wp_mail_succeeded / wp_mail_failed
```

`wp_mail()` を呼ばずに PHPMailer を直接操作。トランスポートはコンストラクタ時に PhpMailer に登録済みのため、`postSend()` で自動的に呼び出されます。`phpmailer_init` 以降の WordPress フック（`wp_mail_succeeded`, `wp_mail_failed`）は発火するため、ログプラグイン等との互換性を維持します。

### 使い分け

| パス | 用途 |
|------|------|
| パス 1（`wp_mail()`） | WordPress の既存コード、サードパーティプラグインとの互換性 |
| パス 2（`$mailer->send()`） | Symfony スタイルの型安全な API。Email オブジェクトで構造化されたメール送信 |

## DSN 設定

`wp-config.php` で `MAILER_DSN` 定数を定義してトランスポートを切り替えます。

### コアトランスポート

| DSN | getName() | 説明 |
|-----|----------|------|
| `native://default` | `mail` | PHP `mail()` 関数（WordPress デフォルト） |
| `smtp://user:pass@host:port?encryption=tls` | `smtp` | 汎用 SMTP（デフォルト: ポート 587, TLS） |
| `smtps://user:pass@host` | `smtp` | SMTP over SSL（デフォルト: ポート 465, SSL） |
| `null://default` | `null` | テスト用 no-op |

### サードパーティトランスポート

| パッケージ | プロバイダ | 詳細 |
|-----------|----------|------|
| `wppack/amazon-mailer` | Amazon SES | [amazon-mailer.md](amazon-mailer.md) |
| `wppack/azure-mailer` | Azure Communication Services | [azure-mailer.md](azure-mailer.md) |
| `wppack/sendgrid-mailer` | SendGrid | [sendgrid-mailer.md](sendgrid-mailer.md) |

```php
// wp-config.php
define('MAILER_DSN', 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');
```

## PhpMailer

PHPMailer を拡張し、トランスポートオブジェクトを直接保持するクラス。

```php
class PhpMailer extends \PHPMailer\PHPMailer\PHPMailer
{
    private ?TransportInterface $transport = null;

    public function setTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
        $this->Mailer = $transport->getName();
    }

    public function postSend(): bool
    {
        if ($this->transport !== null) {
            $this->transport->send($this);
            return true;
        }
        return parent::postSend();
    }

    public function nativePostSend(): bool
    {
        return parent::postSend();
    }
}
```

各トランスポートの動作:

| トランスポート | getName() | 動作 |
|--------------|-----------|------|
| NativeTransport | `mail` | `nativePostSend()` → `mailSend()` |
| SmtpTransport | `smtp` | SMTP 設定 + `nativePostSend()` → `smtpSend()` |
| NullTransport | `null` | no-op（送信なし） |
| SesHttpTransport | `ses+https` | SES Raw API で送信 |
| SesApiTransport | `sesapi` | SES Simple API で送信 |
| SesSmtpTransport | `ses+smtp` | SES SMTP 設定 + `nativePostSend()` → `smtpSend()` |
| AzureApiTransport | `azureapi` | Azure REST API で送信 |
| SendGridApiTransport | `sendgridapi` | SendGrid v3 API で送信 |
| SendGridSmtpTransport | `sendgrid+smtp` | SendGrid SMTP で送信 |

## トランスポートアーキテクチャ

### TransportInterface

```php
interface TransportInterface
{
    public function getName(): string;
    public function send(PhpMailer $phpMailer): void;
}
```

- `getName()`: トランスポート名を返す（PhpMailer の `$Mailer` プロパティ値として使用）
- `send()`: メール送信を実行（PhpMailer の `postSend()` から呼び出される）
- PHPMailer インスタンスの生成は `Mailer` が管理（Transport の責務外）

### AbstractTransport

API やカスタムプロトコルで送信するトランスポートの基底クラス。`send()` で例外を `TransportException` にラップします。SmtpTransport / NativeTransport を含むすべてのトランスポートの基底。

```php
abstract class AbstractTransport implements TransportInterface
{
    abstract public function getName(): string;
    abstract protected function doSend(PhpMailer $phpMailer): void;

    public function send(PhpMailer $phpMailer): void
    {
        try {
            $this->doSend($phpMailer);
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }
}
```

### AbstractApiTransport

API ベースのトランスポートの基底クラス。PHPMailer のプロパティから構造化データを抽出するヘルパーを提供。

```php
abstract class AbstractApiTransport extends AbstractTransport
{
    abstract protected function doSendApi(PhpMailer $phpMailer): string;

    protected function doSend(PhpMailer $phpMailer): void
    {
        $messageId = $this->doSendApi($phpMailer);
        if (!str_starts_with($messageId, '<')) {
            $messageId = '<' . $messageId . '>';
        }
        $phpMailer->setLastMessageId($messageId);
    }
}
```

## Email Fluent API

```php
$email = (new Email())
    ->from(new Address('admin@example.com', 'Admin'))
    ->to('user1@example.com', 'user2@example.com')
    ->cc('cc@example.com')
    ->bcc('bcc@example.com')
    ->replyTo('reply@example.com')
    ->subject('Monthly Report')
    ->text('Plain text body')
    ->html('<h1>HTML body</h1>')
    ->attach('/path/to/report.pdf', 'report.pdf', 'application/pdf')
    ->embed('/path/to/logo.png', 'logo')
    ->priority(Email::PRIORITY_HIGH)
    ->addHeader('X-Campaign', 'spring-sale')
    ->returnPath('bounce@example.com');
```

### Set / Add パターン（Symfony 準拠）

`to()`, `cc()`, `bcc()`, `replyTo()` は **置換**、`addTo()`, `addCc()`, `addBcc()`, `addReplyTo()` は **追加**:

```php
$email = (new Email())
    ->to('user1@example.com')     // user1 のみ
    ->addTo('user2@example.com'); // user1 + user2

$email->to('user3@example.com'); // user3 のみ（user1, user2 は消える）
```

### Address

```php
use WpPack\Component\Mailer\Address;

$address = new Address('user@example.com', 'John Doe');
$address = new Address('John Doe <john@example.com>');  // "Name <email>" 形式もパース
$address = new Address('user@example.com');

echo $address->toString(); // "John Doe" <user@example.com>
```

### Attachment

`Email::attach()` でファイルを添付、`Email::embed()` でインライン画像を埋め込みます。内部的に `Attachment` 値オブジェクトが生成されます。

```php
use WpPack\Component\Mailer\Attachment;

// Email の fluent API で添付
$email = (new Email())
    ->attach('/path/to/report.pdf', 'report.pdf', 'application/pdf')
    ->embed('/path/to/logo.png', 'logo', 'image/png');

// Attachment コンストラクタ（直接生成）
$attachment = new Attachment(
    path: '/path/to/file.pdf',
    name: 'document.pdf',        // 表示名（省略時はファイル名）
    contentType: 'application/pdf', // MIME タイプ（省略可）
    inline: false,               // true でインライン添付（CID 埋め込み）
);
```

インライン添付は HTML 内で `cid:` 参照できます:

```php
$email = (new Email())
    ->embed('/path/to/logo.png', 'logo')
    ->html('<img src="cid:logo" alt="Logo">');
```

### 優先度定数

| 定数 | 値 |
|------|---|
| `Email::PRIORITY_HIGHEST` | 1 |
| `Email::PRIORITY_HIGH` | 2 |
| `Email::PRIORITY_NORMAL` | 3（デフォルト） |
| `Email::PRIORITY_LOW` | 4 |
| `Email::PRIORITY_LOWEST` | 5 |

## Envelope

SMTP エンベロープ（送信者 + 全受信者）を表す値オブジェクト。`Mailer::send()` で自動生成されますが、明示的に指定することも可能です。

```php
use WpPack\Component\Mailer\Envelope;

// Email から自動生成（From, To, Cc, Bcc を集約）
$envelope = Envelope::create($email);
echo $envelope->getSender()->address;       // From アドレス
echo count($envelope->getRecipients());     // To + Cc + Bcc の合計

// 明示的に指定（SMTP MAIL FROM を From ヘッダと別にする場合）
$mailer->send($email, new Envelope(
    new Address('bounce@example.com'),
    [new Address('user@example.com')],
));
```

## TemplatedEmail

テンプレートベースのメール作成。`Email` を拡張し、`TemplateRendererInterface` でレンダリングします。

```php
use WpPack\Component\Mailer\TemplatedEmail;

$email = (new TemplatedEmail())
    ->from('noreply@example.com')
    ->to('user@example.com')
    ->subject('Welcome!')
    ->htmlTemplate('emails/welcome.html.php')
    ->textTemplate('emails/welcome.txt.php')
    ->context(['name' => $user->name, 'url' => $activationUrl]);

$mailer->send($email);
```

テンプレートレンダラーの設定:

```php
$mailer = new Mailer($transport);
$mailer->setTemplateRenderer($renderer); // TemplateRendererInterface 実装
$mailer->boot();
```

`send()` 時に `TemplatedEmail` を検出し、`html()` / `text()` が未設定の場合にテンプレートをレンダリングします。手動で `html()` / `text()` を設定済みの場合はテンプレートより優先されます。

`htmlTemplate(null)` / `textTemplate(null)` で設定済みのテンプレートをリセットできます。

### TemplateRendererInterface

```php
interface TemplateRendererInterface
{
    public function render(string $template, array $context = []): string;
}
```

Templating コンポーネント等の実装を注入できます。

## SentMessage

`wp_mail_succeeded` アクションデータの `sent_message` キーから取得できます。メッセージ ID と Envelope を保持する値オブジェクトです。

```php
add_action('wp_mail_succeeded', function (array $data): void {
    $sentMessage = $data['sent_message'];
    $messageId = $sentMessage->getMessageId(); // SES のメッセージ ID 等
    $originalEmail = $sentMessage->getEmail();
    $envelope = $sentMessage->getEnvelope();   // Sender + Recipients
});
```

## WordPress フック連携

Mailer コンポーネントは以下の WordPress フックと連携します:

### パス 1（wp_mail 経由）で発火するフック

| フック | タイプ | 説明 |
|--------|-------|------|
| `wp_mail` | filter | メールパラメータを変更（PhpMailer 差し替え） |
| `wp_mail_from` | filter | From アドレスを変更 |
| `wp_mail_from_name` | filter | From 名を変更 |
| `wp_mail_content_type` | filter | Content-Type を変更 |
| `wp_mail_charset` | filter | 文字エンコーディングを変更 |
| `phpmailer_init` | action | PHPMailer 設定を変更（WordPress が発火） |
| `wp_mail_succeeded` | action | 送信成功後 |
| `wp_mail_failed` | action | 送信失敗時 |

### パス 2（$mailer->send() 経由）で発火するフック

| フック | タイプ | 説明 |
|--------|-------|------|
| `wp_mail_from` | filter | From アドレスを変更 |
| `wp_mail_from_name` | filter | From 名を変更 |
| `phpmailer_init` | action | PHPMailer 設定を変更（Mailer が発火） |
| `wp_mail_succeeded` | action | 送信成功後 |
| `wp_mail_failed` | action | 送信失敗時 |

## テスト

### TestMailer

```php
use WpPack\Component\Mailer\Test\TestMailer;

$testMailer = new TestMailer();
$email = (new Email())
    ->from('noreply@example.com')
    ->to('user@example.com')
    ->subject('Test');

$testMailer->sendEmail($email);

// 送信されたメッセージを検証
$messages = $testMailer->getSentMessages();
$testMailer->reset();
```

### MailerAssertions トレイト

```php
use WpPack\Component\Mailer\Test\MailerAssertions;

class EmailTest extends TestCase
{
    use MailerAssertions;

    protected function getTestMailer(): TestMailer { return $this->testMailer; }

    public function test_email_sent(): void
    {
        // ... send email ...
        $this->assertEmailSent(1);
        $this->assertEmailSentTo('user@example.com');
        $this->assertEmailSentFrom('admin@example.com');
        $this->assertEmailSubject('Welcome');
        $this->assertEmailBodyContains('Hello');
        $this->assertEmailHtmlContains('<h1>');
        $this->assertNoEmailSent(); // 送信なしを検証

        $last = $this->getLastSentEmail(); // SentMessage を取得
    }
}
```

## クイックスタート

### 基本設定

```php
use WpPack\Component\Mailer\Mailer;

// プラグインまたはテーマの初期化（DSN 文字列を直接渡せる）
$mailer = new Mailer(MAILER_DSN);
$mailer->boot(); // wp_mail() フックを登録

// これ以降、すべての wp_mail() が設定したトランスポートを使用
```

### カスタム PhpMailer の注入

`Mailer` コンストラクタの第2引数で、拡張した `PhpMailer` を注入できます。

```php
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\PhpMailer;

// PhpMailer を継承してカスタマイズ
class MyPhpMailer extends PhpMailer
{
    // カスタムロジック
}

$phpMailer = new MyPhpMailer(true);
$mailer = new Mailer(MAILER_DSN, $phpMailer);
$mailer->boot();
```

### SES トランスポートの使用

```php
// wppack/amazon-mailer がインストールされていれば自動検出
$mailer = new Mailer('ses+api://KEY:SECRET@default?region=ap-northeast-1');
$mailer->boot();
```

### お問い合わせフォーム

```php
use WpPack\Component\Mailer\Email;

$email = (new Email())
    ->to(get_option('admin_email'))
    ->replyTo($userEmail, $userName)
    ->subject('お問い合わせ')
    ->html(sprintf('<h2>%s</h2><p>%s</p>', esc_html($userName), nl2br(esc_html($message))))
    ->text(sprintf("From: %s\n\n%s", $userName, $message));

$mailer->send($email);
```

### 添付ファイル付きメール

```php
$email = (new Email())
    ->from('noreply@example.com')
    ->to($customer->email)
    ->subject('Invoice #' . $order->id)
    ->html($invoiceHtml)
    ->attach('/path/to/invoice.pdf', 'invoice.pdf', 'application/pdf')
    ->priority(Email::PRIORITY_HIGH);

$mailer->send($email);
```

## Transport ファクトリ

### fromDsn()（スタンドアロン）

```php
// インストール済みのブリッジパッケージを class_exists() で自動検出
$transport = Transport::fromDsn('ses+api://KEY:SECRET@default?region=ap-northeast-1');
```

`fromDsn()` は Symfony Mailer と同じパターンです。`wppack/amazon-mailer` をインストールすれば、SES DSN が自動的に使用可能になります。ファクトリを手動で渡す必要はありません。

### fromString()（DI コンテナ経由）

```php
// DI コンテナからファクトリが注入された Transport インスタンスを使用
$transport = new Transport($factories);  // iterable<TransportFactoryInterface>
$resolved = $transport->fromString('ses+api://KEY:SECRET@default?region=ap-northeast-1');
```

### DI コンテナとの統合

`Transport` コンストラクタは `iterable<TransportFactoryInterface>` を受け取ります。DI コンテナのタグ付きサービスでファクトリを自動注入できます。

```php
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;

// services.php でタグ付きサービスとして登録
// $services->set(NativeTransportFactory::class)->tag('mailer.transport_factory');
// $services->set(SesTransportFactory::class)->tag('mailer.transport_factory');
final class NativeTransportFactory implements TransportFactoryInterface { /* ... */ }

final class SesTransportFactory implements TransportFactoryInterface { /* ... */ }
```

コンパイラーパスでファクトリを自動収集し、Transport に注入:

```php
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;

final class RegisterTransportFactoriesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        $factories = [];
        foreach ($builder->findTaggedServiceIds('mailer.transport_factory') as $id => $tags) {
            $factories[] = new Reference($id);
        }
        $builder->findDefinition(Transport::class)->setArgument(0, $factories);
    }
}

// TransportInterface をファクトリサービスで解決
$builder->register(TransportInterface::class)
    ->setFactory([new Reference(Transport::class), 'fromString'])
    ->addArgument('%mailer.dsn%');

// Mailer サービス
$builder->register(Mailer::class)
    ->addArgument(new Reference(TransportInterface::class));
```

DI 環境では `$container->get(Mailer::class)` でメーラーを取得できます。

## 独自トランスポートの作成

### API ベースのトランスポート

外部 API 経由で送信するトランスポートを作成する場合は `AbstractApiTransport` を継承します。

```php
use WpPack\Component\Mailer\Transport\AbstractApiTransport;
use WpPack\Component\Mailer\PhpMailer;

final class MyApiTransport extends AbstractApiTransport
{
    public function __construct(
        private readonly MyApiClient $client,
    ) {}

    public function getName(): string
    {
        return 'myapi';
    }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        $result = $this->client->send([
            'from' => $phpMailer->From,
            'to' => array_column($phpMailer->getToAddresses(), 0),
            'subject' => $phpMailer->Subject,
            'body' => $phpMailer->Body,
        ]);

        return $result->getMessageId();
    }
}
```

### Raw MIME トランスポート

PHPMailer が構築した MIME メッセージ全体を送信する場合は `AbstractTransport` を継承します。

```php
use WpPack\Component\Mailer\Transport\AbstractTransport;
use WpPack\Component\Mailer\PhpMailer;

final class MyRawTransport extends AbstractTransport
{
    public function getName(): string
    {
        return 'myraw';
    }

    protected function doSend(PhpMailer $phpMailer): void
    {
        $mime = $phpMailer->getSentMIMEMessage();
        // $mime を外部サービスに送信
        $messageId = $this->sendRaw($mime);
        $phpMailer->setLastMessageId('<' . $messageId . '>');
    }
}
```

### SMTP ベースのトランスポート

SMTP エンドポイントに接続するトランスポートは `SmtpTransport` を継承します。

```php
use WpPack\Component\Mailer\Transport\SmtpTransport;

final class MySmtpTransport extends SmtpTransport
{
    public function __construct(string $username, string $password, string $region)
    {
        parent::__construct(
            host: sprintf('smtp.%s.example.com', $region),
            port: 587,
            username: $username,
            password: $password,
            encryption: 'tls',
        );
    }

    public function getName(): string
    {
        return 'mysmtp';
    }
}
```

### ファクトリの作成

DSN からトランスポートを生成するファクトリを作成します。

```php
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\Dsn;

final class MyTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'myapi' => new MyApiTransport(new MyApiClient($dsn->getUser(), $dsn->getPassword())),
            'mysmtp' => new MySmtpTransport($dsn->getUser(), $dsn->getPassword(), $dsn->getOption('region', 'us-east-1')),
            default => throw new UnsupportedSchemeException($dsn),
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), ['myapi', 'mysmtp'], true);
    }
}
```

DI 環境では `services.php` で `->tag('mailer.transport_factory')` を付けるだけで自動登録されます。

## クラス一覧

| クラス | 説明 |
|-------|------|
| `Mailer` | 2つの送信パスのエントリポイント |
| `Email` | Fluent メールビルダー |
| `TemplatedEmail` | テンプレート対応メールビルダー（Email 拡張） |
| `Address` | メールアドレス値オブジェクト |
| `Attachment` | 添付ファイル値オブジェクト |
| `Envelope` | Sender/Recipients メタデータ |
| `SentMessage` | 送信結果（メッセージ ID） |
| `PhpMailer` | 拡張 PHPMailer（トランスポート保持） |
| `TemplateRendererInterface` | テンプレート連携インターフェース |
| `Header\Headers` | メールヘッダー管理 |
| `Transport\TransportInterface` | トランスポートインターフェース |
| `Transport\AbstractTransport` | トランスポート基底クラス |
| `Transport\AbstractApiTransport` | API トランスポート基底クラス |
| `Transport\NativeTransport` | PHP `mail()` トランスポート |
| `Transport\SmtpTransport` | SMTP トランスポート |
| `Transport\NullTransport` | テスト用 no-op トランスポート |
| `Transport\Dsn` | DSN パーサー |
| `Transport\TransportFactoryInterface` | ファクトリインターフェース |
| `Transport\NativeTransportFactory` | コアトランスポートファクトリ |
| `Transport\Transport` | DSN ルーター |
| `Test\TestMailer` | テスト用メーラー |
| `Test\MailerAssertions` | テストアサーショントレイト |

## 依存関係

### 必須
- なし（WordPress の PHPMailer を使用）

### 推奨
- **wppack/amazon-mailer** -- Amazon SES トランスポート
- **wppack/azure-mailer** -- Azure Communication Services トランスポート
- **wppack/sendgrid-mailer** -- SendGrid トランスポート
- **Templating コンポーネント** -- テンプレートエンジン連携
