## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Setting/Subscriber/`

### #[SettingsPageAction(page: string, priority?: int)]

**WordPress フック:** `load-{$page}`

設定ページの読み込み時に実行されるアクション。

```php
use WPPack\Component\Hook\Attribute\Setting\Action\SettingsPageAction;

class SettingsPageManager
{
    #[SettingsPageAction(page: 'settings_page_my-plugin')]
    public function onSettingsPageLoad(): void
    {
        // ヘルプタブの追加、スクリーンオプションの設定など
    }
}
```

### #[SettingsErrorsAction(priority?: int)]

**WordPress フック:** `settings_errors`

設定エラー表示時に実行されるアクション。
