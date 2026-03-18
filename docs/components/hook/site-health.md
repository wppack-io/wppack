## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/SiteHealth/Subscriber/`

### #[SiteStatusTestsFilter(priority?: int = 10)]

**WordPress フック:** `site_status_tests`

テストの一覧を変更します（テストの追加・削除・変更）。

```php
use WpPack\Component\Hook\Attribute\SiteHealth\SiteStatusTestsFilter;

class HealthCheckManager
{
    #[SiteStatusTestsFilter]
    public function modifyTests(array $tests): array
    {
        // 不要なコアテストを除外
        unset($tests['direct']['php_extensions']);

        return $tests;
    }
}
```

### #[SiteStatusTestResultFilter(priority?: int = 10)]

**WordPress フック:** `site_status_test_result`

個別テストの結果を変更します。

```php
use WpPack\Component\Hook\Attribute\SiteHealth\SiteStatusTestResultFilter;

class HealthCheckModifier
{
    #[SiteStatusTestResultFilter]
    public function modifyTestResult(array $result): array
    {
        if ($result['test'] === 'php_version' && $result['status'] === 'recommended') {
            $result['actions'] .= '<p>' . __('Contact hosting provider for PHP upgrade.', 'my-plugin') . '</p>';
        }

        return $result;
    }
}
```

### #[DebugInformationFilter(priority?: int = 10)]

**WordPress フック:** `debug_information`

サイトヘルス情報タブのデバッグ情報を変更します。

```php
use WpPack\Component\Hook\Attribute\SiteHealth\DebugInformationFilter;

class DebugInfoProvider
{
    #[DebugInformationFilter]
    public function addDebugInfo(array $info): array
    {
        $info['my-plugin'] = [
            'label' => __('My Plugin', 'my-plugin'),
            'fields' => [
                'version' => [
                    'label' => 'Version',
                    'value' => MY_PLUGIN_VERSION,
                ],
            ],
        ];

        return $info;
    }
}
```

## クイックリファレンス

```php
// テスト管理
#[SiteStatusTestsFilter(priority?: int = 10)]        // site_status_tests — テスト一覧の変更
#[SiteStatusTestResultFilter(priority?: int = 10)]    // site_status_test_result — テスト結果の変更

// デバッグ情報
#[DebugInformationFilter(priority?: int = 10)]        // debug_information — デバッグ情報の追加

// サイトヘルスナビゲーション
#[SiteHealthNavigationTabsFilter(priority?: int = 10)] // site_health_navigation_tabs — タブの変更
```
