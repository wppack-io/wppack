# User コンポーネント

**パッケージ:** `wppack/user`
**名前空間:** `WpPack\Component\User\`
**レイヤー:** Application

WordPress ユーザー関連フックを Named Hook アトリビュートで型安全に利用するためのコンポーネントです。

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

### After（WpPack）

```php
use WpPack\Component\User\Attribute\UserRegisterAction;
use WpPack\Component\User\Attribute\RegistrationErrorsFilter;
use WpPack\Component\User\Attribute\ShowUserProfileAction;

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

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](./hook/user.md) を参照してください。
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

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress ユーザーフック用

### 推奨
- **Security コンポーネント** - 認証とバリデーション用
- **Event Dispatcher コンポーネント** - ユーザーイベント用
- **Mailer コンポーネント** - ユーザー通知用
- **Cache コンポーネント** - ユーザーデータのキャッシュ用
