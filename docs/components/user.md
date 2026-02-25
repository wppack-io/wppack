# User コンポーネント

**パッケージ:** `wppack/user`
**名前空間:** `WpPack\Component\User\`
**レイヤー:** Application

型安全なユーザーモデル、カスタムフィールド、ロール管理を備えた、WordPress ユーザー管理へのモダンなオブジェクト指向アプローチです。

## インストール

```bash
composer require wppack/user
```

## 基本コンセプト

### 従来の WordPress コード

```php
$user_id = wp_create_user('john_doe', 'password123', 'john@example.com');
update_user_meta($user_id, 'first_name', 'John');
update_user_meta($user_id, 'last_name', 'Doe');

function handle_user_login($username) {
    $user = get_user_by('login', $username);
    // Manual user processing...
}
```

### WpPack コード

```php
use WpPack\Component\User\Attribute\UserModel;
use WpPack\Component\User\Attribute\UserField;
use WpPack\Component\User\Attribute\UserRole;
use WpPack\Component\User\AbstractUser;

#[UserModel]
class Customer extends AbstractUser
{
    #[UserField('first_name', type: 'string', required: true)]
    protected string $firstName;

    #[UserField('last_name', type: 'string', required: true)]
    protected string $lastName;

    #[UserField('phone', type: 'string')]
    protected string $phone;

    #[UserRole('customer')]
    protected string $role = 'customer';

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}

// 依存性注入を使用した利用例
$customer = $this->userRepository->create([
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'firstName' => 'John',
    'lastName' => 'Doe'
]);
```

## 機能

- **オブジェクト指向のユーザー管理** - 型安全なユーザーモデルによる管理
- **拡張されたユーザープロフィール** - カスタムフィールドとメタデータに対応
- **高度なロールと権限管理** - きめ細かなパーミッション制御
- **ユーザー登録とオンボーディング** - カスタマイズ可能なワークフロー

## クイックスタート

### カスタムユーザーモデル

```php
<?php
use WpPack\Component\User\Attribute\UserModel;
use WpPack\Component\User\Attribute\UserField;
use WpPack\Component\User\Attribute\UserRole;
use WpPack\Component\User\AbstractUser;

#[UserModel]
class Customer extends AbstractUser
{
    #[UserField('first_name', type: 'string', required: true)]
    protected string $firstName;

    #[UserField('last_name', type: 'string', required: true)]
    protected string $lastName;

    #[UserField('phone', type: 'string')]
    protected string $phone = '';

    #[UserField('date_of_birth', type: 'date')]
    protected ?\DateTime $dateOfBirth = null;

    #[UserField('customer_type', type: 'string', enum: ['individual', 'business'])]
    protected string $customerType = 'individual';

    #[UserField('company_name', type: 'string')]
    protected string $companyName = '';

    #[UserField('billing_address', type: 'array')]
    protected array $billingAddress = [];

    #[UserField('shipping_address', type: 'array')]
    protected array $shippingAddress = [];

    #[UserField('total_orders', type: 'number', default: 0)]
    protected int $totalOrders = 0;

    #[UserField('total_spent', type: 'number', default: 0)]
    protected float $totalSpent = 0.0;

    #[UserField('loyalty_points', type: 'number', default: 0)]
    protected int $loyaltyPoints = 0;

    #[UserField('email_verified', type: 'boolean', default: false)]
    protected bool $emailVerified = false;

    #[UserRole('customer')]
    protected string $role = 'customer';

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getDisplayName(): string
    {
        if ($this->customerType === 'business' && $this->companyName) {
            return $this->companyName . ' (' . $this->getFullName() . ')';
        }

        return $this->getFullName();
    }

    public function isVIP(): bool
    {
        return $this->totalSpent >= 1000 || $this->totalOrders >= 10;
    }

    public function getTier(): string
    {
        if ($this->totalSpent >= 5000) {
            return 'platinum';
        } elseif ($this->totalSpent >= 2000) {
            return 'gold';
        } elseif ($this->totalSpent >= 500) {
            return 'silver';
        }

        return 'bronze';
    }

    public function addOrder(float $amount): void
    {
        $this->totalOrders++;
        $this->totalSpent += $amount;
        $this->loyaltyPoints += (int) floor($amount);

        if ($this->isVIP()) {
            $this->loyaltyPoints += (int) floor($amount * 0.1);
        }
    }
}
```

### 登録サービス

```php
<?php
use WpPack\Component\User\UserRepository;
use WpPack\Component\User\UserValidator;

class CustomerRegistrationService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserValidator $userValidator,
        private MailerInterface $mailer
    ) {}

    public function registerCustomer(array $data): Customer
    {
        $this->validateRegistrationData($data);

        if ($this->userRepository->findByEmail($data['email'])) {
            throw new \Exception('Email address already exists');
        }

        $customer = new Customer();
        $customer->setUsername($data['username']);
        $customer->setEmail($data['email']);
        $customer->setFirstName($data['first_name']);
        $customer->setLastName($data['last_name']);
        $customer->setPhone($data['phone'] ?? '');
        $customer->setCustomerType($data['customer_type'] ?? 'individual');

        $this->userValidator->validate($customer);
        $this->userRepository->save($customer);
        $this->sendWelcomeEmail($customer);

        do_action('customer_registered', $customer);

        return $customer;
    }
}
```

### プロフィール管理

```php
<?php
class CustomerProfileService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserValidator $userValidator
    ) {}

    public function updateProfile(Customer $customer, array $data): Customer
    {
        if (isset($data['first_name'])) {
            $customer->setFirstName($data['first_name']);
        }

        if (isset($data['billing_address'])) {
            $customer->setBillingAddress($this->validateAddress($data['billing_address']));
        }

        if (isset($data['preferences'])) {
            $customer->updatePreferences($data['preferences']);
        }

        $this->userValidator->validate($customer);
        $this->userRepository->save($customer);

        do_action('customer_profile_updated', $customer, $data);

        return $customer;
    }

    public function getDashboardData(Customer $customer): array
    {
        return [
            'profile' => [
                'name' => $customer->getFullName(),
                'email' => $customer->getEmail(),
                'tier' => $customer->getTier(),
                'is_vip' => $customer->isVIP(),
            ],
            'statistics' => [
                'total_orders' => $customer->getTotalOrders(),
                'total_spent' => $customer->getTotalSpent(),
                'loyalty_points' => $customer->getLoyaltyPoints(),
            ],
        ];
    }
}
```

### 登録フォームハンドラー

```php
<?php
use WpPack\Component\Hook\Attribute\Action;

class RegistrationFormHandler
{
    public function __construct(
        private CustomerRegistrationService $registrationService
    ) {}

    #[Action('wp_ajax_register_customer')]
    #[Action('wp_ajax_nopriv_register_customer')]
    public function onWpAjaxRegisterCustomer(): void
    {
        try {
            if (!wp_verify_nonce($_POST['nonce'], 'customer_registration')) {
                throw new \Exception('Security check failed');
            }

            $customer = $this->registrationService->registerCustomer($_POST);

            wp_set_current_user($customer->getId());
            wp_set_auth_cookie($customer->getId());

            wp_send_json_success([
                'message' => 'Registration successful!',
                'redirect' => home_url('/account/dashboard')
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
```

### ユーザーコンポーネントの登録

```php
<?php
add_action('init', function() {
    $container = new WpPack\Container();

    $container->register([
        Customer::class,
        CustomerRegistrationService::class,
        CustomerProfileService::class,
        RegistrationFormHandler::class
    ]);

    $userManager = $container->get(WpPack\Component\User\UserManager::class);
    $userManager->discoverUserModels();

    add_role('customer', 'Customer', [
        'read' => true,
        'edit_own_profile' => true,
        'view_orders' => true,
        'manage_addresses' => true
    ]);
});
```

### 型安全なユーザーモデル

```php
#[UserModel]
class Employee extends AbstractUser
{
    #[UserField('employee_id', type: 'string', unique: true)]
    protected string $employeeId;

    #[UserField('department', type: 'string', required: true)]
    protected string $department;

    #[UserField('hire_date', type: 'date')]
    protected \DateTime $hireDate;

    #[UserField('salary', type: 'number', min: 0)]
    protected float $salary;

    #[UserCapability('edit_employees')]
    protected bool $canEditEmployees = false;

    public function isManager(): bool
    {
        return $this->hasCapability('manage_department');
    }
}
```

## Named Hook アトリビュート

### ユーザー登録フック

#### #[UserRegisterAction]

**WordPress フック:** `user_register`

```php
use WpPack\Component\User\Attribute\UserRegisterAction;

class UserRegistration
{
    #[UserRegisterAction]
    public function onUserRegister(int $user_id): void
    {
        $user = get_user_by('id', $user_id);

        update_user_meta($user_id, 'email_notifications', 'yes');
        update_user_meta($user_id, 'display_preferences', [
            'theme' => 'light',
            'language' => get_locale(),
            'timezone' => wp_timezone_string(),
        ]);

        $this->sendWelcomeEmail($user);

        if (isset($_POST['subscribe_newsletter'])) {
            $this->subscribeToNewsletter($user->user_email);
        }
    }
}
```

#### #[RegistrationErrorsFilter]

**WordPress フック:** `registration_errors`

```php
use WpPack\Component\User\Attribute\RegistrationErrorsFilter;
use WP_Error;

class RegistrationValidation
{
    #[RegistrationErrorsFilter]
    public function validateRegistration(WP_Error $errors, string $sanitized_user_login, string $user_email): WP_Error
    {
        if (strlen($sanitized_user_login) < 3) {
            $errors->add('username_too_short', __('Username must be at least 3 characters long.', 'wppack'));
        }

        $blocked_usernames = ['admin', 'administrator', 'root', 'test'];
        if (in_array(strtolower($sanitized_user_login), $blocked_usernames)) {
            $errors->add('username_blocked', __('This username is not allowed.', 'wppack'));
        }

        $email_domain = substr(strrchr($user_email, '@'), 1);
        if ($this->isBlockedDomain($email_domain)) {
            $errors->add('email_domain_blocked', __('Registration from this email domain is not allowed.', 'wppack'));
        }

        return $errors;
    }
}
```

### ユーザープロフィールフック

#### #[ProfileUpdateAction]

**WordPress フック:** `profile_update`

```php
use WpPack\Component\User\Attribute\ProfileUpdateAction;
use WP_User;

class UserProfile
{
    #[ProfileUpdateAction]
    public function onProfileUpdate(int $user_id, WP_User $old_user_data, array $userdata): void
    {
        if ($old_user_data->user_email !== $userdata['user_email']) {
            $this->sendEmailChangeNotification($user_id, $old_user_data->user_email, $userdata['user_email']);
            $this->logProfileChange($user_id, 'email', $old_user_data->user_email, $userdata['user_email']);
        }

        clean_user_cache($user_id);
        $this->updateProfileCompleteness($user_id);
    }
}
```

#### #[ShowUserProfileAction]

**WordPress フック:** `show_user_profile`

```php
use WpPack\Component\User\Attribute\ShowUserProfileAction;
use WP_User;

class UserProfileFields
{
    #[ShowUserProfileAction]
    public function addCustomProfileFields(WP_User $user): void
    {
        ?>
        <h3><?php _e('Additional Profile Information', 'wppack'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="bio_extended"><?php _e('Extended Bio', 'wppack'); ?></label></th>
                <td>
                    <textarea name="bio_extended" id="bio_extended" rows="5" cols="30"><?php
                        echo esc_textarea(get_user_meta($user->ID, 'bio_extended', true));
                    ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="social_links"><?php _e('Social Media', 'wppack'); ?></label></th>
                <td>
                    <?php $social = get_user_meta($user->ID, 'social_links', true) ?: []; ?>
                    <input type="url" name="social_links[twitter]" placeholder="Twitter URL"
                           value="<?php echo esc_url($social['twitter'] ?? ''); ?>" class="regular-text" /><br>
                    <input type="url" name="social_links[linkedin]" placeholder="LinkedIn URL"
                           value="<?php echo esc_url($social['linkedin'] ?? ''); ?>" class="regular-text" /><br>
                    <input type="url" name="social_links[github]" placeholder="GitHub URL"
                           value="<?php echo esc_url($social['github'] ?? ''); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }
}
```

#### #[PersonalOptionsUpdateAction]

**WordPress フック:** `personal_options_update`

```php
use WpPack\Component\User\Attribute\PersonalOptionsUpdateAction;

class SaveProfileFields
{
    #[PersonalOptionsUpdateAction]
    public function saveCustomProfileFields(int $user_id): void
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['bio_extended'])) {
            update_user_meta($user_id, 'bio_extended', sanitize_textarea_field($_POST['bio_extended']));
        }

        if (isset($_POST['social_links']) && is_array($_POST['social_links'])) {
            $social_links = array_map('esc_url_raw', $_POST['social_links']);
            update_user_meta($user_id, 'social_links', $social_links);
        }
    }
}
```

### ユーザー削除フック

#### #[DeleteUserAction]

**WordPress フック:** `delete_user`

```php
use WpPack\Component\User\Attribute\DeleteUserAction;

class UserDeletion
{
    #[DeleteUserAction]
    public function beforeUserDelete(int $user_id, ?int $reassign_id): void
    {
        $user = get_user_by('id', $user_id);

        $this->backupUserData($user);
        $this->cleanupUserData($user_id);

        if ($user && $user->user_email) {
            $this->sendAccountDeletionEmail($user);
        }
    }
}
```

#### #[DeletedUserAction]

**WordPress フック:** `deleted_user`

```php
use WpPack\Component\User\Attribute\DeletedUserAction;

class PostUserDeletion
{
    #[DeletedUserAction]
    public function afterUserDelete(int $user_id, ?int $reassign_id): void
    {
        $this->clearUserCaches($user_id);
        $this->removeFromMailingList($user_id);
        $this->removeFromCRM($user_id);
        $this->updateUserStatistics('user_deleted');
    }
}
```

## Hook アトリビュートリファレンス

```php
// 登録
#[UserRegisterAction(priority?: int = 10)]         // ユーザー登録後
#[RegistrationErrorsFilter(priority?: int = 10)]   // 登録バリデーション
#[RegisterFormAction(priority?: int = 10)]          // 登録フォームの変更

// プロフィール管理
#[ProfileUpdateAction(priority?: int = 10)]        // プロフィール更新後
#[ShowUserProfileAction(priority?: int = 10)]      // 自分のプロフィールフィールド表示
#[EditUserProfileAction(priority?: int = 10)]      // 他のユーザーのプロフィールフィールド表示
#[PersonalOptionsUpdateAction(priority?: int = 10)] // 自分のプロフィールフィールド保存
#[EditUserProfileUpdateAction(priority?: int = 10)] // 他のユーザーのプロフィールフィールド保存

// ユーザー削除
#[DeleteUserAction(priority?: int = 10)]           // ユーザー削除前
#[DeletedUserAction(priority?: int = 10)]          // ユーザー削除後
#[RemoveUserFromBlogAction(priority?: int = 10)]   // マルチサイトブログからの削除
```

## このコンポーネントの使用場面

**最適な用途：**
- カスタムユーザー管理システム
- EC サイトの顧客管理
- 従業員管理システム
- 会員制サイト
- マルチロールアプリケーション

**代替を検討すべき場合：**
- シンプルな WordPress ユーザー機能で十分な場合（WordPress コアを使用）
- 単一ロールのシンプルなサイト
- 基本的なユーザー登録のみの場合

## WordPress 統合

- WordPress のユーザーテーブルとメタデータを使用
- WordPress のロールと権限に対応
- WordPress の認証システムと連携
- マルチサイトのユーザー管理をサポート
- WordPress のプロフィールページと統合

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress ユーザーフック用

### 推奨
- **Security コンポーネント** - 認証とバリデーション用
- **Event Dispatcher コンポーネント** - ユーザーイベント用
- **Mailer コンポーネント** - ユーザー通知用
- **Cache コンポーネント** - ユーザーデータのキャッシュ用
