# Sanitizer コンポーネント

Sanitizer コンポーネントは、WordPress におけるデータサニタイズに対して、カスタムサニタイザー、セキュリティ重視のデータ処理を備えた包括的で型安全なアプローチを提供します。

## このコンポーネントの機能

Sanitizer コンポーネントは、WordPress のデータサニタイズを以下の機能で変革します：

- **型安全なデータサニタイズ** - 厳密に型付けされたルール
- **組み込みサニタイズルール** - 一般的なデータ型向け
- **カスタムサニタイザー登録** - ドメイン固有のニーズに対応
- **入出力フィルタリング** - チェーン可能な操作
- **HTML 浄化** - 許可タグの設定可能
- **ファイルパスサニタイズ** - 安全なファイル処理
- **URL バリデーションとサニタイズ** - プロトコル制御付き
- **メールサニタイズ** - フォーマットバリデーション付き
- **データベースクエリサニタイズ** - SQL 安全性確保
- **フォームデータサニタイズ** - ネストフィールド対応
- **JSON サニタイズ** - 構造バリデーション付き

## インストール

```bash
composer require wppack/sanitizer
```

## 従来の WordPress vs WpPack

### Before（従来の WordPress）

```php
// Traditional WordPress - manual sanitization for each data type
$title = sanitize_text_field($_POST['title'] ?? '');
$content = wp_kses_post($_POST['content'] ?? '');
$url = esc_url_raw($_POST['url'] ?? '');
$email = sanitize_email($_POST['email'] ?? '');
$filename = sanitize_file_name($_POST['filename'] ?? '');

if (empty($title)) {
    wp_die('Title is required');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    wp_die('Invalid email');
}

$tags = [];
if (isset($_POST['tags']) && is_array($_POST['tags'])) {
    foreach ($_POST['tags'] as $tag) {
        $tags[] = sanitize_text_field($tag);
    }
}
```

### After（WpPack）

```php
use WpPack\Component\Sanitize\Sanitizer;

class FormSanitizer
{
    public function __construct(private Sanitizer $sanitizer) {}

    public function sanitizeFormData(array $data): array
    {
        $rules = [
            'title' => ['text', 'trim'],
            'content' => ['html', 'allow_tags:p,br,strong,em'],
            'url' => ['url', 'validate_protocol:http,https'],
            'email' => ['email', 'lowercase'],
            'filename' => ['filename', 'lowercase', 'max:255'],
            'price' => ['float', 'min:0', 'max:99999.99'],
            'quantity' => ['int', 'min:1', 'max:1000'],
            'tags' => ['array'],
            'tags.*' => ['text', 'trim', 'lowercase'],
            'metadata' => ['json'],
            'slug' => ['slug']
        ];

        return $this->sanitizer->sanitize($data, $rules);
    }
}
```

## コア機能

### ルールベースサニタイズ

```php
use WpPack\Component\Sanitize\Rules\SanitizationRules;

$rules = SanitizationRules::make([
    'name' => 'text|trim|max:255',
    'description' => 'html|allow_tags:p,br,ul,ol,li,strong,em',
    'price' => 'float|min:0|max:999999.99',
    'sku' => 'text|uppercase|regex:/^[A-Z0-9-]+$/',
    'categories' => 'array',
    'categories.*' => 'int',
    'attributes.*.name' => 'text',
    'attributes.*.value' => 'text',
    'images' => 'array|max:10',
    'images.*' => 'url',
    'status' => 'text|in:draft,published,archived',
]);

$sanitized = $this->sanitizer->sanitize($data, $rules);
```

### HTML サニタイズ

```php
use WpPack\Component\Sanitize\HtmlSanitizer;
use WpPack\Component\Sanitize\HtmlConfig;

$config = new HtmlConfig();
$config->allowElement('p');
$config->allowElement('br');
$config->allowElement('strong');
$config->allowElement('em');
$config->allowElement('a', ['href', 'title', 'target']);
$config->allowElement('img', ['src', 'alt', 'width', 'height']);

$config->allowProtocol('https');
$config->allowProtocol('http');
$config->allowProtocol('mailto');

$config->filterAttribute('a', 'href', function($value) {
    if (parse_url($value, PHP_URL_HOST) !== parse_url(home_url(), PHP_URL_HOST)) {
        $this->setAttribute('target', '_blank');
        $this->setAttribute('rel', 'noopener noreferrer');
    }
    return $value;
});

$clean = $this->htmlSanitizer->sanitize($html, $config);
```

### カスタムサニタイザー

```php
use WpPack\Component\Sanitize\AbstractSanitizer;
use WpPack\Component\Sanitize\Attribute\CustomSanitizer;

#[CustomSanitizer('color')]
class ColorSanitizer extends AbstractSanitizer
{
    public function sanitize($value): string
    {
        $color = preg_replace('/[^a-fA-F0-9#]/', '', (string)$value);

        if (strpos($color, '#') !== 0) {
            $color = '#' . $color;
        }

        if (preg_match('/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $color)) {
            if (strlen($color) === 4) {
                $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
            }
            return strtoupper($color);
        }

        return '#000000';
    }
}

$sanitizer->register(new ColorSanitizer());
$clean = $sanitizer->sanitize(['theme_color' => 'ff6600'], [
    'theme_color' => ['color']
]);
// Result: ['theme_color' => '#FF6600']
```

### モデルサニタイズ

```php
use WpPack\Component\Sanitize\Attribute\Sanitize;

class UserModel
{
    #[Sanitize(['text', 'trim'])]
    public string $username;

    #[Sanitize(['email', 'lowercase'])]
    public string $email;

    #[Sanitize(['text', 'trim'])]
    public ?string $firstName = null;

    #[Sanitize(['url', 'validate_protocol:http,https'])]
    public ?string $website = null;

    #[Sanitize(['html', 'strip_tags'])]
    public ?string $bio = null;
}

$user = new UserModel();
$user->username = '  JohnDoe123  ';
$user->email = 'JOHN@EXAMPLE.COM';
$sanitizer->sanitizeModel($user);
// $user->username = 'JohnDoe123'
// $user->email = 'john@example.com'
```

## クイックスタート

### 基本的なデータサニタイズ

```php
class FormHandler
{
    public function __construct(private Sanitizer $sanitizer) {}

    public function handleSubmit(array $data): array
    {
        $rules = [
            'name' => 'text|trim',
            'email' => 'email',
            'age' => 'int|min:18|max:120',
            'bio' => 'text|strip_tags|max:500',
            'website' => 'url'
        ];

        return $this->sanitizer->sanitize($data, $rules);
    }
}
```

### 組み込みサニタイザー

```php
$sanitizer->text('  Hello <script>alert("XSS")</script>  ');  // "Hello"
$sanitizer->email('JOHN@EXAMPLE.COM');                          // "john@example.com"
$sanitizer->url('http://example.com/<script>');                 // "http://example.com/"
$sanitizer->int('123.45');                                      // 123
$sanitizer->bool('yes');                                        // true
```

### お問い合わせフォームの例

```php
class ContactFormService
{
    public function __construct(
        private Sanitizer $sanitizer,
        private Mailer $mailer
    ) {}

    public function processForm(array $data): bool
    {
        $rules = [
            'name' => 'text|trim|max:100',
            'email' => 'email',
            'subject' => 'text|trim|max:200',
            'message' => 'text|trim|min:10|max:1000',
            'phone' => 'text|regex:/^[\d\s\-\+\(\)]+$/'
        ];

        $clean = $this->sanitizer->sanitize($data, $rules);

        return $this->mailer->send([
            'to' => get_option('admin_email'),
            'subject' => 'Contact Form: ' . $clean['subject'],
            'body' => $this->formatMessage($clean),
            'reply_to' => $clean['email']
        ]);
    }
}
```

### 商品フォームの例

```php
class ProductService
{
    public function __construct(private Sanitizer $sanitizer) {}

    public function saveProduct(array $data): int
    {
        $rules = [
            'title' => 'text|trim|max:200',
            'description' => 'html|allow_tags:p,br,ul,ol,li,strong,em',
            'price' => 'float|min:0|max:999999.99',
            'sale_price' => 'float|min:0|lt:price',
            'sku' => 'text|uppercase|regex:/^[A-Z0-9\-]+$/',
            'stock' => 'int|min:0',
            'categories' => 'array',
            'categories.*' => 'int',
            'featured' => 'bool',
            'status' => 'text|in:publish,draft,pending'
        ];

        $clean = $this->sanitizer->sanitize($data, $rules);

        return $this->createProduct($clean);
    }
}
```

## 高度な機能

### フォームサニタイズビルダー

```php
use WpPack\Component\Sanitize\FormRules;

$rules = FormRules::create()
    ->field('name')
        ->text()
        ->trim()
        ->maxLength(100)
        ->pattern('/^[a-zA-Z\s]+$/')
    ->field('email')
        ->email()
        ->lowercase()
    ->field('phone')
        ->text()
        ->transform(function($value) {
            return preg_replace('/[^0-9+\-\(\)\s]/', '', $value);
        });
```

### データベースサニタイズ

```php
use WpPack\Component\Sanitize\DatabaseSanitizer;

class QuerySanitizer
{
    public function __construct(private DatabaseSanitizer $dbSanitizer) {}

    public function sanitizeSearchQuery(string $search): string
    {
        return $this->dbSanitizer->like($search); // Escapes %, _, and \
    }

    public function sanitizeIdentifier(string $column): string
    {
        return $this->dbSanitizer->identifier($column); // Only alphanumeric and underscore
    }
}
```

### バッチ処理

```php
use WpPack\Component\Sanitize\BatchSanitizer;

$rules = [
    'username' => 'text|trim|lowercase',
    'email' => 'email',
    'role' => 'text|in:subscriber,contributor,author'
];

$results = $this->batchSanitizer->process($users, $rules, [
    'batch_size' => 100,
    'stop_on_error' => false,
    'collect_errors' => true
]);

$errors = $this->batchSanitizer->getErrors();
$processed = $this->batchSanitizer->getProcessedCount();
```

## Named Hook アトリビュート

### メタサニタイズ

```php
#[SanitizePostMetaFilter(priority?: int = 10)]       // 投稿メタをサニタイズ
#[SanitizeUserMetaFilter(priority?: int = 10)]       // ユーザーメタをサニタイズ
#[SanitizeTermMetaFilter(priority?: int = 10)]       // タームメタをサニタイズ
#[SanitizeCommentMetaFilter(priority?: int = 10)]    // コメントメタをサニタイズ
```

### フィールドサニタイズ

```php
#[SanitizeTextFieldFilter(priority?: int = 10)]      // テキストフィールドをサニタイズ
#[SanitizeTitleFilter(priority?: int = 10)]          // タイトル/スラッグをサニタイズ
#[SanitizeFileNameFilter(priority?: int = 10)]       // ファイル名をサニタイズ
#[SanitizeEmailFilter(priority?: int = 10)]          // メールアドレスをサニタイズ
#[SanitizeKeyFilter(priority?: int = 10)]            // キーをサニタイズ
```

### 出力エスケープ

```php
#[EscHtmlFilter(priority?: int = 10)]                // HTML をエスケープ
#[EscAttrFilter(priority?: int = 10)]                // 属性をエスケープ
#[CleanUrlFilter(priority?: int = 10)]                // URL をエスケープ（esc_url）
#[JsEscapeFilter(priority?: int = 10)]               // JavaScript をエスケープ（esc_js）
```

### バリデーション

```php
#[PreCommentApprovedFilter(priority?: int = 10)]     // コメントをバリデーション
#[PreInsertTermFilter(priority?: int = 10)]          // タームをバリデーション
#[PreUserLoginFilter(priority?: int = 10)]           // ログインをバリデーション
```

### 使用例：投稿メタサニタイズ

```php
use WpPack\Component\Sanitizer\Attribute\SanitizePostMetaFilter;

class PostMetaSanitizer
{
    public function __construct(private SanitizerInterface $sanitizer) {}

    #[SanitizePostMetaFilter(metaKey: 'price')]
    public function sanitizePrice($meta_value, string $meta_key, string $object_type)
    {
        return $this->sanitizer->decimal($meta_value, 2);
    }

    #[SanitizePostMetaFilter(metaKey: 'email')]
    public function sanitizeEmail($meta_value, string $meta_key, string $object_type)
    {
        return $this->sanitizer->email($meta_value);
    }

    #[SanitizePostMetaFilter(metaKey: 'product_data')]
    public function sanitizeProductData($meta_value, string $meta_key, string $object_type)
    {
        if (!is_array($meta_value)) {
            return [];
        }

        return [
            'sku' => sanitize_text_field($meta_value['sku'] ?? ''),
            'stock' => $this->sanitizer->integer($meta_value['stock'] ?? 0),
            'weight' => $this->sanitizer->decimal($meta_value['weight'] ?? 0, 3),
        ];
    }
}
```

## クイックリファレンス

### 一般的なルール

```php
'text|trim|min:3|max:100'
'email'
'int|min:0|max:100'
'float|min:0.01|max:999.99'
'array|min:1|max:10'
'bool'
'url|validate_protocol:https'
```

### 組み込みサニタイザー

```php
$sanitizer->text($value);        // プレーンテキスト
$sanitizer->email($value);       // メールアドレス
$sanitizer->url($value);         // URL
$sanitizer->int($value);         // 整数
$sanitizer->float($value);       // 浮動小数点数
$sanitizer->bool($value);        // 真偽値
$sanitizer->array($value);       // 配列
$sanitizer->json($value);        // JSON
$sanitizer->slug($value);        // URL スラッグ
$sanitizer->filename($value);    // ファイル名
```

## このコンポーネントの使用場面

**最適な用途：**
- ユーザー入力を処理するアプリケーション
- データサニタイズが必要なフォーム
- API 入力のサニタイズ
- コンテンツ管理システム
- EC アプリケーション
- セキュリティ重視のアプリケーション

**代替を検討すべき場合：**
- テキストのみのシンプルなフォーム
- WordPress の基本関数で十分な場合
- 読み取り専用のアプリケーション

## 依存関係

### 必須
なし - Sanitizer コンポーネントはスタンドアロンです。

### 推奨
- **Validator コンポーネント** - サニタイズ前後のデータバリデーション
- **Security コンポーネント** - 強化されたセキュリティバリデーション
- **Database コンポーネント** - データベース操作
- **Hook コンポーネント** - WordPress 統合
