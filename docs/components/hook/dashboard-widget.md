## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/DashboardWidget/Subscriber/`

```php
// ダッシュボードセットアップ
#[WpDashboardSetupAction(priority?: int = 10)]           // wp_dashboard_setup — ウィジェット登録
#[WpNetworkDashboardSetupAction(priority?: int = 10)]    // wp_network_dashboard_setup — ネットワークダッシュボード

// ダッシュボードウィジェット
#[DashboardGlanceItemsFilter(priority?: int = 10)]       // dashboard_glance_items — 概要アイテム
#[ActivityBoxEndAction(priority?: int = 10)]              // activity_box_end — アクティビティボックス末尾
```
