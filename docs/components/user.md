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

#### #[RegisterFormAction]

**WordPress フック:** `register_form`

```php
use WpPack\Component\User\Attribute\RegisterFormAction;

class RegistrationForm
{
    #[RegisterFormAction]
    public function addCustomRegistrationFields(): void
    {
        ?>
        <p>
            <label for="phone"><?php _e('Phone Number', 'wppack'); ?></label>
            <input type="tel" name="phone" id="phone" class="input"
                   value="<?php echo esc_attr($_POST['phone'] ?? ''); ?>" />
        </p>
        <?php
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

自分のプロフィール編集画面にカスタムフィールドを表示します。

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

#### #[EditUserProfileAction]

**WordPress フック:** `edit_user_profile`

他のユーザーのプロフィール編集画面にカスタムフィールドを表示します。

```php
use WpPack\Component\User\Attribute\EditUserProfileAction;
use WP_User;

class AdminUserProfileFields
{
    #[EditUserProfileAction]
    public function addAdminProfileFields(WP_User $user): void
    {
        ?>
        <h3><?php _e('Admin Notes', 'wppack'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="admin_notes"><?php _e('Notes', 'wppack'); ?></label></th>
                <td>
                    <textarea name="admin_notes" id="admin_notes" rows="5" cols="30"><?php
                        echo esc_textarea(get_user_meta($user->ID, 'admin_notes', true));
                    ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }
}
```

#### #[PersonalOptionsUpdateAction]

**WordPress フック:** `personal_options_update`

自分のプロフィールのカスタムフィールドを保存します。

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

#### #[EditUserProfileUpdateAction]

**WordPress フック:** `edit_user_profile_update`

他のユーザーのプロフィールのカスタムフィールドを保存します。

```php
use WpPack\Component\User\Attribute\EditUserProfileUpdateAction;

class SaveAdminProfileFields
{
    #[EditUserProfileUpdateAction]
    public function saveAdminProfileFields(int $user_id): void
    {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['admin_notes'])) {
            update_user_meta($user_id, 'admin_notes', sanitize_textarea_field($_POST['admin_notes']));
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

### マルチサイトフック

#### #[RemoveUserFromBlogAction]

**WordPress フック:** `remove_user_from_blog`

```php
use WpPack\Component\User\Attribute\RemoveUserFromBlogAction;

class MultiSiteUserHandler
{
    #[RemoveUserFromBlogAction]
    public function onRemoveUserFromBlog(int $user_id, int $blog_id): void
    {
        $this->cleanupBlogSpecificData($user_id, $blog_id);
        $this->notifyBlogAdmin($blog_id, $user_id);
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

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress ユーザーフック用

### 推奨
- **Security コンポーネント** - 認証とバリデーション用
- **Event Dispatcher コンポーネント** - ユーザーイベント用
- **Mailer コンポーネント** - ユーザー通知用
- **Cache コンポーネント** - ユーザーデータのキャッシュ用
