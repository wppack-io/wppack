# User コンポーネント

**パッケージ:** `wppack/user`
**名前空間:** `WPPack\Component\User\`
**レイヤー:** Application

WordPress ユーザー関連フックを Named Hook アトリビュートで型安全に利用するためのコンポーネントです。

> [!NOTE]
> このコンポーネントは設計段階です。Repository（`UserRepository`）は実装済みです。Hook アトリビュート等の機能は設計仕様として参照してください。

## インストール

```bash
composer require wppack/user
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('user_register', 'handle_user_register');
function handle_user_register(int $user_id): void {
    update_user_meta($user_id, 'email_notifications', 'yes');
}

add_filter('registration_errors', 'validate_registration', 10, 3);
function validate_registration(WP_Error $errors, string $login, string $email): WP_Error {
    if (strlen($login) < 3) {
        $errors->add('username_too_short', 'Username must be at least 3 characters.');
    }
    return $errors;
}

add_action('show_user_profile', 'add_custom_fields');
function add_custom_fields(WP_User $user): void {
    // Display custom profile fields
}
```

### After（WPPack）

```php
use WPPack\Component\User\Attribute\UserRegisterAction;
use WPPack\Component\User\Attribute\RegistrationErrorsFilter;
use WPPack\Component\User\Attribute\ShowUserProfileAction;

class UserHandler
{
    #[UserRegisterAction]
    public function onUserRegister(int $user_id): void
    {
        update_user_meta($user_id, 'email_notifications', 'yes');
    }

    #[RegistrationErrorsFilter]
    public function validateRegistration(WP_Error $errors, string $login, string $email): WP_Error
    {
        if (strlen($login) < 3) {
            $errors->add('username_too_short', 'Username must be at least 3 characters.');
        }
        return $errors;
    }

    #[ShowUserProfileAction]
    public function addCustomFields(WP_User $user): void
    {
        // Display custom profile fields
    }
}
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — User](../hook/user.md) を参照してください。

## Repository

`UserRepositoryInterface` / `UserRepository` は、WordPress ユーザーの CRUD 操作とメタデータ操作を提供します。

```php
use WPPack\Component\User\UserRepository;
use WPPack\Component\User\UserRepositoryInterface;

$repository = new UserRepository();

// ユーザーの取得
$users = $repository->findAll(['role' => 'editor']);  // list<WP_User>
$user = $repository->find($userId);              // WP_User|null
$user = $repository->findByEmail('user@example.com');
$user = $repository->findByLogin('username');
$user = $repository->findBySlug('user-slug');

// ユーザーの作成・更新・削除
$newId = $repository->insert([
    'user_login' => 'newuser',
    'user_pass' => 'password',
    'user_email' => 'new@example.com',
]);
$repository->update(['ID' => $newId, 'display_name' => 'New Name']);
$repository->delete($newId, reassignTo: 1);

// メタデータ操作
$repository->addMeta($userId, 'custom_key', 'value');
$value = $repository->getMeta($userId, 'custom_key', single: true);
$repository->updateMeta($userId, 'custom_key', 'new_value');
$repository->deleteMeta($userId, 'custom_key');
```

## 依存関係

### 推奨
- **Security コンポーネント** - 認証とバリデーション用
- **Event Dispatcher コンポーネント** - ユーザーイベント用
- **Mailer コンポーネント** - ユーザー通知用
- **Cache コンポーネント** - ユーザーデータのキャッシュ用
