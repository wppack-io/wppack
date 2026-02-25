# Validator コンポーネント

Validator コンポーネントは、WordPress アプリケーション向けに、組み込みルール、カスタムバリデーター、フォーム統合を備えた包括的で型安全なバリデーションシステムを提供します。

## このコンポーネントの機能

Validator コンポーネントは、WordPress のデータバリデーションを以下の機能で変革します：

- **Fluent バリデーション API** - メソッドチェーンによる記述
- **組み込みバリデーションルール** - 一般的なシナリオに対応
- **カスタムバリデーターサポート** - 複雑なロジックに対応
- **フォームバリデーション統合** - エラーハンドリング付き
- **配列・ネストされたデータバリデーション** - 複雑な構造に対応
- **条件付きバリデーション** - 他のフィールドに基づく条件分岐
- **バリデーショングループ** - 異なるコンテキストに対応
- **エラーメッセージのカスタマイズ** - 翻訳対応
- **型安全なバリデーション** - PHP 8 アトリビュートを使用
- **WordPress 固有のバリデーター** - 投稿、ユーザー、タームに対応

## インストール

```bash
composer require wppack/validator
```

## 従来の WordPress vs WpPack

### Before（従来の WordPress）

```php
$email = $_POST['email'] ?? '';
$age = $_POST['age'] ?? '';

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!is_email($email)) {
    $errors[] = 'Invalid email address';
}

if (empty($age)) {
    $errors[] = 'Age is required';
} elseif (!is_numeric($age) || $age < 18 || $age > 100) {
    $errors[] = 'Age must be between 18 and 100';
}
```

### After（WpPack）

```php
use WpPack\Component\Validator\Validator;
use WpPack\Component\Validator\Attribute\Validate;

class UserRegistration
{
    public function __construct(private Validator $validator) {}

    #[Validate([
        'email' => 'required|email|unique:users,email',
        'age' => 'required|integer|between:18,100',
        'password' => 'required|min:8|confirmed',
        'terms' => 'required|accepted'
    ])]
    public function register(array $data): User
    {
        $validated = $this->validator->validate($data);
        return User::create($validated);
    }
}
```

## コア機能

### Fluent バリデーション API

```php
use WpPack\Component\Validator\Validator;

$validator = new Validator();

$result = $validator->make($data, [
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users',
    'age' => 'required|integer|min:18',
    'website' => 'nullable|url',
    'bio' => 'nullable|string|max:500'
]);

if ($result->fails()) {
    $errors = $result->errors();
    $firstError = $result->errors()->first();
}

$validated = $result->validated();
```

### 組み込みバリデーションルール

```php
// 文字列バリデーション
'name' => 'required|string|min:3|max:50|alpha_dash',
'slug' => 'required|slug|unique:posts,post_name',

// 数値バリデーション
'price' => 'required|numeric|min:0|max:99999.99',
'quantity' => 'required|integer|between:1,100',

// 日付バリデーション
'start_date' => 'required|date|after:today',
'end_date' => 'required|date|after:start_date',

// ファイルバリデーション
'avatar' => 'required|file|image|max:2048',
'document' => 'required|file|mimes:pdf,doc,docx|max:10240',

// WordPress 固有
'post_id' => 'required|exists:posts,ID|post_type:product',
'user_id' => 'required|exists:users,ID|user_can:edit_posts',
'term_id' => 'required|exists:terms,term_id|taxonomy:category'
```

### カスタムバリデーター

```php
use WpPack\Component\Validator\Rule;

class PhoneNumber extends Rule
{
    public function passes($attribute, $value): bool
    {
        return preg_match('/^\+?[1-9]\d{1,14}$/', $value);
    }

    public function message(): string
    {
        return 'The :attribute must be a valid phone number.';
    }
}

$validator->extend('phone', PhoneNumber::class);

$validator->make($data, [
    'phone' => 'required|phone'
]);
```

### ネストされたデータバリデーション

```php
$validator->make($data, [
    'user.name' => 'required|string',
    'user.email' => 'required|email',
    'user.profile.bio' => 'nullable|string|max:500',
    'user.profile.website' => 'nullable|url',

    'addresses' => 'required|array|min:1',
    'addresses.*.street' => 'required|string',
    'addresses.*.city' => 'required|string',
    'addresses.*.zip' => 'required|regex:/^\d{5}$/',

    'tags' => 'array',
    'tags.*' => 'string|distinct|exists:terms,name'
]);
```

### 条件付きバリデーション

```php
$validator->make($data, [
    'type' => 'required|in:personal,business',
    'first_name' => 'required_if:type,personal',
    'last_name' => 'required_if:type,personal',
    'company_name' => 'required_if:type,business',
    'tax_id' => 'required_if:type,business|string',
    'shipping_address' => 'required_unless:pickup,true',
    'pickup_location' => 'required_if:pickup,true'
]);
```

## クイックスタート

### コンタクトフォームバリデーション

```php
use WpPack\Component\Validator\Validator;

class ContactFormHandler
{
    public function __construct(private Validator $validator) {}

    public function handleSubmission(array $data): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|min:10|max:1000'
        ];

        $result = $this->validator->make($data, $rules);

        if ($result->fails()) {
            return [
                'success' => false,
                'errors' => $result->errors()->all()
            ];
        }

        $this->sendEmail($result->validated());

        return [
            'success' => true,
            'message' => 'Your message has been sent!'
        ];
    }
}
```

### フォームバリデータークラス

```php
use WpPack\Component\Validator\FormValidator;

class RegistrationValidator extends FormValidator
{
    protected function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:20|alpha_dash|unique:users,user_login',
            'email' => 'required|email|unique:users,user_email',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'terms' => 'required|accepted'
        ];
    }

    protected function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'An account with this email already exists.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'terms.accepted' => 'You must accept the terms and conditions.'
        ];
    }
}
```

### 設定ページバリデーション

```php
class PluginSettings
{
    public function __construct(private Validator $validator) {}

    public function validateSettings(array $input): array
    {
        $rules = [
            'api_key' => 'required|string|size:32|alpha_num',
            'api_url' => 'required|url',
            'cache_duration' => 'required|integer|min:0|max:86400',
            'enable_logging' => 'boolean',
            'log_level' => 'required_if:enable_logging,true|in:debug,info,warning,error',
            'allowed_roles' => 'array',
            'allowed_roles.*' => 'string|in:administrator,editor,author'
        ];

        $result = $this->validator->make($input, $rules);

        if ($result->fails()) {
            foreach ($result->errors()->all() as $field => $messages) {
                foreach ($messages as $message) {
                    add_settings_error('plugin_settings', $field, $message, 'error');
                }
            }
            return get_option('plugin_settings', []);
        }

        return $result->validated();
    }
}
```

### カスタム WordPress 投稿存在ルール

```php
class PostExistsRule extends Rule
{
    public function __construct(private string $postType = 'post') {}

    public function passes($attribute, $value): bool
    {
        $post = get_post($value);
        return $post && $post->post_type === $this->postType;
    }

    public function message(): string
    {
        return 'The selected :attribute is invalid.';
    }
}

$validator->make($data, [
    'product_id' => ['required', new PostExistsRule('product')]
]);
```

### バリデーショングループ

```php
use WpPack\Component\Validator\ValidationGroup;

class UserValidator extends ValidationGroup
{
    public function registration(): array
    {
        return [
            'username' => 'required|unique:users,user_login',
            'email' => 'required|email|unique:users,user_email',
            'password' => 'required|min:8|confirmed'
        ];
    }

    public function update(): array
    {
        return [
            'email' => 'required|email|unique:users,user_email,' . $this->userId,
            'display_name' => 'nullable|string|max:250'
        ];
    }

    public function passwordReset(): array
    {
        return [
            'password' => 'required|min:8|confirmed|different:current_password'
        ];
    }
}
```

### カスタムエラーメッセージ

```php
$validator->make($data, $rules, [
    'email.required' => __('We need your email address', 'textdomain'),
    'email.unique' => __('This email is already registered', 'textdomain'),
    'age.min' => __('You must be at least :min years old', 'textdomain'),
    'terms.accepted' => __('You must accept the terms of service', 'textdomain')
]);

$validator->setAttributeNames([
    'email' => __('email address', 'textdomain'),
    'dob' => __('date of birth', 'textdomain')
]);
```

### AJAX フィールドバリデーション

```php
class AjaxValidator
{
    public function __construct(private Validator $validator) {}

    #[Action('wp_ajax_validate_field')]
    #[Action('wp_ajax_nopriv_validate_field')]
    public function validateField(): void
    {
        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = $_POST['value'] ?? '';
        $rules = $_POST['rules'] ?? '';

        $result = $this->validator->make(
            [$field => $value],
            [$field => $rules]
        );

        if ($result->fails()) {
            wp_send_json_error([
                'field' => $field,
                'errors' => $result->errors()->first($field)
            ]);
        }

        wp_send_json_success(['field' => $field, 'valid' => true]);
    }
}
```

## テスト

```php
use PHPUnit\Framework\TestCase;

class ContactFormValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testValidContactForm(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message.'
        ];

        $rules = [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|min:10'
        ];

        $result = $this->validator->make($data, $rules);

        $this->assertTrue($result->passes());
        $this->assertEmpty($result->errors()->all());
    }

    public function testInvalidEmail(): void
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => 'required|email'];

        $result = $this->validator->make($data, $rules);

        $this->assertTrue($result->fails());
        $this->assertArrayHasKey('email', $result->errors()->toArray());
    }
}
```

## クイックリファレンス

### 一般的なバリデーションルール

```php
// 必須と型
'field' => 'required'
'field' => 'nullable'
'field' => 'string|integer|numeric|boolean|array'

// 文字列ルール
'field' => 'min:3|max:255'
'field' => 'email|url|ip|json'
'field' => 'alpha|alpha_num|alpha_dash'
'field' => 'regex:/^[A-Z]+$/'

// 数値ルール
'field' => 'min:0|max:100'
'field' => 'between:1,10'
'field' => 'gt:0|gte:0|lt:100|lte:100'

// 日付ルール
'field' => 'date|date_format:Y-m-d'
'field' => 'before:tomorrow|after:yesterday'

// 配列ルール
'field' => 'array|min:1|max:10'
'field.*' => 'string|distinct'

// データベースルール
'field' => 'exists:users,user_login'
'field' => 'unique:posts,post_name'

// 条件付きルール
'field' => 'required_if:other_field,value'
'field' => 'required_unless:other_field,value'
'field' => 'required_with:field1,field2'
'field' => 'required_without:field1,field2'

// ファイルルール
'field' => 'file|image|mimes:jpg,png|max:2048'
```

## このコンポーネントの使用場面

**最適な用途：**
- フォームバリデーションと処理
- API 入力バリデーション
- ユーザー登録とプロフィール
- 設定と構成のバリデーション
- インポートデータのバリデーション
- カスタム投稿タイプのバリデーション
- EC サイトのチェックアウトバリデーション

**代替を検討すべき場合：**
- 単純なブールチェック（ネイティブ PHP を使用）
- データベーススキーマバリデーション（マイグレーションを使用）
- HTML バリデーション（DOMDocument を使用）

## 依存関係

### 必須
なし - WordPress のバリデーション関数と連携して動作します

### 推奨
- **Sanitizer コンポーネント** - バリデーション前の入力サニタイズ
- **Translation コンポーネント** - ローカライズされたエラーメッセージ
- **HTTP Foundation コンポーネント** - リクエストバリデーション
- **DependencyInjection コンポーネント** - サービス統合
