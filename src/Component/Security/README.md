# WpPack Security

WordPress 上でプラガブルな認証・認可フレームワークを提供するコンポーネント。Authenticator パターンによるリクエストベース認証、Passport/Badge による認証要件の値オブジェクト化、Voter ベースの認可チェックをサポート。

## インストール

```bash
composer require wppack/security
```

## 使い方

### 認証（Authentication）

Authenticator パターンでリクエストを認証:

```php
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;

final class MyAuthenticator implements AuthenticatorInterface
{
    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === '/my-login';
    }

    public function authenticate(Request $request): Passport
    {
        return new Passport(
            new UserBadge($request->post->getString('email')),
            new CredentialsBadge($request->post->getString('password')),
        );
    }
    // ...
}
```

### 認可（Authorization）

Voter パターンで権限チェック:

```php
use WpPack\Component\Security\Security;

$security->denyAccessUnlessGranted('edit_posts');

if ($security->isGranted('ROLE_ADMINISTRATOR')) {
    // 管理者のみ
}
```

### Named Hook アトリビュート

```php
use WpPack\Component\Security\Attribute\Action\WpLoginAction;
use WpPack\Component\Security\Attribute\Filter\AuthenticateFilter;

class SecuritySubscriber
{
    #[AuthenticateFilter(priority: 5)]
    public function onAuthenticate($user, string $username, string $password)
    {
        // カスタム認証ロジック
        return $user;
    }

    #[WpLoginAction]
    public function onLogin(string $userLogin, \WP_User $user): void
    {
        // ログイン後の処理
    }
}
```

### マルチサイト

Super Admin チェックとブログコンテキストをサポート:

```php
// Super Admin チェック
if ($security->isGranted('ROLE_SUPER_ADMIN')) {
    // ネットワーク管理者のみ
}

// トークンから認証時のブログ ID を取得
$blogId = $security->getToken()->getBlogId();
```

## ドキュメント

詳細は [docs/components/security.md](../../docs/components/security.md) を参照してください。

## リソース

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

メインリポジトリ [wppack-io/wppack](https://github.com/wppack-io/wppack) で開発しています。

## ライセンス

MIT
