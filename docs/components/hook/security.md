## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Security/Subscriber/`

### 認証フック

```php
#[WpLoginAction(priority: 10)]              // ログイン成功後
#[WpLoginFailedAction(priority: 10)]        // ログイン失敗後
#[AuthenticateFilter(priority: 10)]         // 認証プロセスの変更
#[WpLogoutAction(priority: 10)]             // ログアウト前
#[DetermineCurrentUserFilter(priority: 10)] // 現在のユーザーをフィルタ
```

### パスワードフック

```php
#[PasswordResetAction(priority: 10)]        // パスワードリセット後
#[RetrievePasswordAction(priority: 10)]     // パスワードリセット要求時
#[CheckPasswordFilter(priority: 10)]        // パスワード強度の検証
```

### 権限フック

```php
#[UserHasCapFilter(priority: 10)]           // ユーザー権限のフィルタ
#[MapMetaCapFilter(priority: 10)]           // メタ権限のマッピング
```

### 使用例：認証セキュリティシステム

```php
use WPPack\Component\Hook\Attribute\Security\Action\WpLoginAction;
use WPPack\Component\Hook\Attribute\Security\Action\WpLoginFailedAction;
use WPPack\Component\Hook\Attribute\Security\Filter\AuthenticateFilter;
use WPPack\Component\Hook\Attribute\Security\Filter\UserHasCapFilter;

class SecuritySystem
{
    #[AuthenticateFilter(priority: 10)]
    public function enforceRateLimiting($user, string $username, string $password)
    {
        $ip = $this->getClientIp();
        if ($this->rateLimiter->isBlocked($ip)) {
            return new \WP_Error('rate_limit', 'Too many login attempts.');
        }
        return $user;
    }

    #[WpLoginAction(priority: 10)]
    public function onSuccessfulLogin(string $userLogin, \WP_User $user): void
    {
        $this->rateLimiter->clear($this->getClientIp());
    }

    #[WpLoginFailedAction(priority: 10)]
    public function onFailedLogin(string $username, \WP_Error $error): void
    {
        $this->rateLimiter->recordFailure($this->getClientIp());
    }

    #[UserHasCapFilter(priority: 10)]
    public function enforceCapabilitySecurity(
        array $allcaps,
        array $caps,
        array $args,
        \WP_User $user,
    ): array {
        $sensitiveCaps = ['delete_plugins', 'delete_themes', 'edit_users'];
        if (array_intersect($caps, $sensitiveCaps)) {
            if (!$this->hasRecentVerification($user->ID)) {
                return [];
            }
        }
        return $allcaps;
    }
}
```
