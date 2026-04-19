## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Nonce/Subscriber/`

### Nonce 生成フック

```php
#[NonceUserLoggedOutFilter(priority: 10)]     // ログアウトユーザーの nonce UID
#[NonceLifeFilter(priority: 10)]              // nonce の有効期間（デフォルト: 1日）
```

### 使用例：nonce の有効期間をカスタマイズ

```php
use WPPack\Component\Hook\Attribute\Nonce\NonceLifeFilter;

class NonceLifetimeCustomizer
{
    #[NonceLifeFilter(priority: 10)]
    public function customizeLifetime(int $seconds): int
    {
        // デフォルトの1日（86400秒）を4時間に短縮
        return HOUR_IN_SECONDS * 4;
    }
}
```
