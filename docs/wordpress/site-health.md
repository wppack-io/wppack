# WordPress Site Health API 仕様

## 1. 概要

WordPress の Site Health API（WordPress 5.1+）は、サイトの健全性チェックとデバッグ情報の収集を行うための仕組みです。管理画面の「ツール > サイトヘルス」ページに表示されるテスト結果とサイト情報を、プラグインやテーマから拡張できます。

Site Health は以下の 2 つの主要機能で構成されます:

| 機能 | ページ | 説明 |
|---|---|---|
| ステータス（テスト） | サイトヘルスステータス | サイトの健全性テストを実行し、結果を表示 |
| 情報 | サイトヘルス情報 | サイトの技術情報（PHP バージョン、DB サイズ等）を収集・表示 |

### `WP_Site_Health` クラス

`WP_Site_Health` はシングルトンパターンで実装されたクラスで、コアのヘルスチェックテストを提供します。

```php
class WP_Site_Health {
    private static $instance = null;

    public static function get_instance(): WP_Site_Health {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

| プロパティ | 型 | 説明 |
|---|---|---|
| `$mysql_min_version_check` | `string` | 必要最小 MySQL バージョン |
| `$mysql_rec_version_check` | `string` | 推奨 MySQL バージョン |
| `$php_min_version_check` | `string` | 必要最小 PHP バージョン |
| `$php_rec_version_check` | `string` | 推奨 PHP バージョン |
| `$is_mariadb` | `bool` | MariaDB を使用しているか |

## 2. データ構造

### テスト結果の構造

各テストは以下の構造の連想配列を返します:

```php
[
    'label'       => 'テスト結果のラベル',              // 結果の見出し（HTML 不可）
    'status'      => 'good',                          // 'good', 'recommended', 'critical'
    'badge'       => [
        'label' => 'パフォーマンス',                   // バッジのラベル
        'color' => 'blue',                            // 'blue', 'green', 'red', 'orange', 'purple', 'gray'
    ],
    'description' => '<p>テストの詳細説明</p>',        // HTML 許可
    'actions'     => '<a href="...">修正方法</a>',     // HTML 許可。対処法のリンク等
    'test'        => 'test_identifier',               // テスト識別子（キャッシュキーに使用）
]
```

### テストのステータス

| ステータス | 意味 | UI 表示 |
|---|---|---|
| `good` | 問題なし | 緑のチェックマーク |
| `recommended` | 改善推奨 | オレンジの注意マーク |
| `critical` | 重大な問題 | 赤の警告マーク |

ステータスはサイトヘルスのスコア計算に影響します。スコアはステータスページ上部のメーターで視覚化されます。

### テスト登録の構造

テストは「直接テスト」と「非同期テスト」の 2 種類があります:

```php
// site_status_tests フィルターで返される配列
[
    'direct' => [
        'test_identifier' => [
            'label' => 'テスト名',              // テスト一覧での表示名
            'test'  => callable|string,         // テスト実行関数またはメソッド名
            'async_direct_test' => false,        // WordPress 6.1+: 非同期実行のダイレクトテスト
            'has_rest' => false,                // REST API エンドポイントがあるか
        ],
    ],
    'async' => [
        'test_identifier' => [
            'label'             => 'テスト名',
            'test'              => 'rest_route', // REST API ルート名
            'has_rest'          => true,
            'async_direct_test' => false,
            'headers'           => [],          // REST API リクエストヘッダー
        ],
    ],
]
```

### 直接テスト vs 非同期テスト

| 種類 | 実行タイミング | 用途 |
|---|---|---|
| 直接テスト（`direct`） | ページロード時に PHP で即座に実行 | 高速なチェック（PHP バージョン確認、設定値の検証等） |
| 非同期テスト（`async`） | ページロード後に JavaScript から REST API 経由で実行 | 時間のかかるチェック（外部サーバーへの接続確認、DNS ルックアップ等） |

### サイト情報の構造

```php
// site_status_test_result フィルター等で使用
[
    'section_id' => [
        'label'       => 'セクション名',
        'description' => 'セクションの説明',
        'show_count'  => true,                // フィールド数を表示するか
        'fields'      => [
            'field_id' => [
                'label'   => 'フィールド名',
                'value'   => '値',            // 文字列または配列
                'debug'   => '値（デバッグ用）', // エクスポート時に使用される値
                'private' => false,           // true の場合、エクスポートから除外
            ],
        ],
    ],
]
```

## 3. API リファレンス

### テスト登録

テストの登録は `site_status_tests` フィルターを通じて行います:

```php
add_filter('site_status_tests', function (array $tests): array {
    // 直接テストの追加
    $tests['direct']['my_test'] = [
        'label' => 'カスタムチェック',
        'test'  => 'my_custom_test_function',
    ];

    // 非同期テストの追加
    $tests['async']['my_async_test'] = [
        'label'    => 'カスタム非同期チェック',
        'test'     => 'my-plugin/v1/health-check',
        'has_rest' => true,
    ];

    return $tests;
});
```

### テスト関数の実装

```php
function my_custom_test_function(): array {
    $result = [
        'label'       => 'カスタムチェック合格',
        'status'      => 'good',
        'badge'       => [
            'label' => 'セキュリティ',
            'color' => 'blue',
        ],
        'description' => '<p>このチェックは問題なく合格しました。</p>',
        'actions'     => '',
        'test'        => 'my_test',
    ];

    // 条件チェック
    if (!my_check_passes()) {
        $result['label']       = 'カスタムチェック不合格';
        $result['status']      = 'critical';
        $result['description'] = '<p>問題が検出されました。修正が必要です。</p>';
        $result['actions']     = sprintf(
            '<p><a href="%s">設定を確認する</a></p>',
            esc_url(admin_url('options-general.php'))
        );
    }

    return $result;
}
```

### サイト情報の追加

```php
add_filter('debug_information', function (array $info): array {
    $info['my_plugin'] = [
        'label'       => 'My Plugin',
        'description' => 'My Plugin のデバッグ情報',
        'show_count'  => true,
        'fields'      => [
            'version' => [
                'label' => 'バージョン',
                'value' => '1.0.0',
            ],
            'api_key' => [
                'label'   => 'API キー',
                'value'   => '設定済み',
                'debug'   => 'REDACTED',
                'private' => true,
            ],
            'cache_status' => [
                'label' => 'キャッシュ',
                'value' => wp_using_ext_object_cache() ? '有効' : '無効',
            ],
        ],
    ];
    return $info;
});
```

### `WP_Site_Health` の主要メソッド

| メソッド | 戻り値 | 説明 |
|---|---|---|
| `get_instance()` | `WP_Site_Health` | シングルトンインスタンスを取得 |
| `get_tests()` | `array` | 全テストの登録情報を取得 |
| `get_test_php_version()` | `array` | PHP バージョンテスト結果 |
| `get_test_sql_server()` | `array` | DB サーバーテスト結果 |
| `get_test_php_extensions()` | `array` | PHP 拡張テスト結果 |
| `get_test_utf8mb4_support()` | `array` | UTF8MB4 サポートテスト結果 |
| `get_test_https_status()` | `array` | HTTPS テスト結果 |
| `get_test_ssl_support()` | `array` | SSL サポートテスト結果 |
| `get_test_scheduled_events()` | `array` | Cron イベントテスト結果 |
| `get_test_plugin_version()` | `array` | プラグイン更新テスト結果 |
| `get_test_theme_version()` | `array` | テーマ更新テスト結果 |
| `get_test_wordpress_version()` | `array` | WordPress 更新テスト結果 |
| `get_test_persistent_object_cache()` | `array` | 永続オブジェクトキャッシュテスト結果 |
| `get_test_page_cache()` | `array` | ページキャッシュテスト結果（WordPress 6.1+） |

## 4. 実行フロー

### ステータスページのテスト実行フロー

```
/wp-admin/site-health.php ページロード
│
├── WP_Site_Health::get_instance() でインスタンス取得
│
├── get_tests() でテスト一覧を取得
│   └── apply_filters('site_status_tests', $tests)
│       └── プラグインがテストを追加・削除・変更
│
├── 直接テストの実行
│   ├── 各 $tests['direct'] に対して:
│   │   ├── callable($test['test']) を実行
│   │   ├── テスト結果を取得
│   │   └── apply_filters('site_status_test_result', $result)
│   │       └── 結果のフィルタリング
│   └── 結果を HTML にレンダリング
│
├── 非同期テストの設定
│   ├── 各 $tests['async'] に対して:
│   │   └── JavaScript にテスト情報を wp_localize_script() で渡す
│   │
│   └── JavaScript（site-health.js）がページロード後に実行
│       ├── 各非同期テストに対して:
│       │   ├── REST API エンドポイントに fetch() リクエスト
│       │   │   └── /wp-json/{test['test']}/
│       │   ├── レスポンスのテスト結果を取得
│       │   └── DOM を更新して結果を表示
│       └── 全テスト完了後
│           └── サイトヘルススコアを再計算
│
└── ステータスの永続化
    └── site_transient 'health-check-site-status-result' に保存
```

### 非同期テストの REST API フロー

```
JavaScript → REST API エンドポイント
│
├── /wp-json/wp-site-health/v1/tests/{test_name}
│
├── 認証チェック
│   └── current_user_can('view_site_health_checks')
│
├── テスト関数の実行
│   └── WP_Site_Health::get_test_{test_name}()
│
├── apply_filters('site_status_test_result', $result)
│
└── JSON レスポンスとしてテスト結果を返す
```

### サイト情報ページのフロー

```
/wp-admin/site-health.php?tab=debug ページロード
│
├── WP_Debug_Data::debug_data()
│   ├── コア情報の収集
│   │   ├── WordPress 環境
│   │   ├── サーバー環境
│   │   ├── データベース
│   │   ├── テーマ・プラグイン
│   │   └── メディア・ファイルシステム
│   │
│   └── apply_filters('debug_information', $info)
│       └── プラグインが情報を追加
│
├── HTML テーブルにレンダリング
│   ├── 各セクションをアコーディオンで表示
│   └── 「サイト情報をクリップボードにコピー」ボタン
│
└── コピー機能
    └── private=true のフィールドは除外
    └── debug 値がある場合はそちらを使用
```

### サイトヘルススコアの計算

```
サイトヘルススコアの計算
│
├── 全テスト結果を集計
│   ├── critical の数をカウント
│   └── recommended の数をカウント
│
├── スコア判定
│   ├── critical が 1 つ以上
│   │   └── 「改善が必要です」（赤）
│   ├── recommended が一定数以上
│   │   └── 「改善の余地があります」（オレンジ）
│   └── それ以外
│       └── 「良好です」（緑）
│
└── site_transient 'health-check-site-status-result' に保存
    └── ダッシュボードウィジェットで参照
```

## 5. コア標準テスト一覧

### 直接テスト

| テスト ID | 説明 | チェック内容 |
|---|---|---|
| `wordpress_version` | WordPress バージョン | 最新バージョンかどうか |
| `plugin_version` | プラグインの更新 | 更新可能なプラグインの有無 |
| `theme_version` | テーマの更新 | 更新可能なテーマの有無 |
| `php_version` | PHP バージョン | 最小/推奨バージョン以上か |
| `php_extensions` | PHP 拡張 | 必要な拡張がインストールされているか |
| `php_default_timezone` | PHP タイムゾーン | `date.timezone` が UTC か |
| `php_sessions` | PHP セッション | セッションが開始されていないか |
| `sql_server` | データベースサーバー | MySQL/MariaDB のバージョン |
| `utf8mb4_support` | UTF8MB4 サポート | データベースが utf8mb4 をサポートしているか |
| `https_status` | HTTPS | サイトが HTTPS で提供されているか |
| `ssl_support` | SSL サポート | PHP の OpenSSL 拡張が有効か |
| `scheduled_events` | スケジュールイベント | WP-Cron が正常に動作しているか |
| `persistent_object_cache` | オブジェクトキャッシュ | 永続オブジェクトキャッシュの推奨 |
| `debug_enabled` | デバッグモード | WP_DEBUG が本番で無効か |
| `file_uploads` | ファイルアップロード | `file_uploads` が有効か |

### 非同期テスト

| テスト ID | 説明 | チェック内容 |
|---|---|---|
| `dotorg_communication` | WordPress.org 通信 | WordPress.org API への接続確認 |
| `background_updates` | バックグラウンド更新 | 自動更新が正常に動作するか |
| `loopback_requests` | ループバックリクエスト | サイトが自分自身に HTTP リクエストできるか |
| `authorization_header` | Authorization ヘッダー | REST API の認証ヘッダーが正常か |
| `page_cache` | ページキャッシュ | ページキャッシュが有効か（WordPress 6.1+） |

## 6. 権限と REST API

### 必要な権限

| 操作 | 必要な権限 |
|---|---|
| ステータスページの閲覧 | `view_site_health_checks` |
| 情報ページの閲覧 | `view_site_health_checks` |
| REST API テスト実行 | `view_site_health_checks` |

`view_site_health_checks` は `install_plugins` 権限を持つロール（通常は管理者のみ）にマッピングされます。

### REST API エンドポイント

| メソッド | エンドポイント | 説明 |
|---|---|---|
| `GET` | `/wp-site-health/v1/tests/background-updates` | バックグラウンド更新テスト |
| `GET` | `/wp-site-health/v1/tests/loopback-requests` | ループバックリクエストテスト |
| `GET` | `/wp-site-health/v1/tests/dotorg-communication` | WordPress.org 通信テスト |
| `GET` | `/wp-site-health/v1/tests/authorization-header` | Authorization ヘッダーテスト |
| `GET` | `/wp-site-health/v1/tests/page-cache` | ページキャッシュテスト |
| `GET` | `/wp-site-health/v1/directory-sizes` | ディレクトリサイズ情報 |

## 7. フック一覧

### Filter

| フック名 | パラメータ | 説明 |
|---|---|---|
| `site_status_tests` | `(array $tests)` | テスト一覧を変更。テストの追加・削除・変更が可能 |
| `site_status_test_result` | `(array $result)` | 個別テスト結果をフィルタリング |
| `debug_information` | `(array $info)` | サイト情報（情報タブ）に項目を追加 |
| `site_status_should_suggest_persistent_object_cache` | `(bool $should_suggest)` | 永続オブジェクトキャッシュの推奨を制御 |
| `site_status_persistent_object_cache_thresholds` | `(array $thresholds)` | オブジェクトキャッシュ推奨の閾値を変更 |
| `site_status_page_cache_supported_cache_headers` | `(array $cache_headers)` | ページキャッシュ検出に使うヘッダーを変更 |
| `site_health_navigation_tabs` | `(array $tabs)` | サイトヘルスページのナビゲーションタブを変更 |
| `site_health_default_tab` | `(string $default_tab)` | デフォルトのアクティブタブを変更 |

### Action

| フック名 | パラメータ | 説明 |
|---|---|---|
| `site_health_tab_content` | `(string $tab)` | カスタムタブのコンテンツ出力 |
| `wp_site_health_scheduled_check` | なし | Cron による定期ヘルスチェック実行時 |

### Cron イベント

`wp_site_health_scheduled_check` アクションは週 1 回の Cron イベントとして登録されています。このイベントは `WP_Site_Health::wp_cron_scheduled_check()` を実行し、テスト結果を `site_transient` に保存します。ダッシュボードの「一目でわかる」ウィジェットはこの Transient からステータスを表示します。

## 8. `WP_Debug_Data` クラス

`WP_Debug_Data` はサイト情報の収集とフォーマットを担当するユーティリティクラスです。

### 主要メソッド

| メソッド | 戻り値 | 説明 |
|---|---|---|
| `debug_data()` | `array` | 全デバッグ情報を収集して返す |
| `format()` | `string` | デバッグ情報をテキスト形式にフォーマット（クリップボードコピー用） |
| `get_mysql_var()` | `string` | MySQL 変数を取得 |

### コア情報セクション

`debug_data()` が返す標準セクション:

| セクション ID | ラベル | 主な情報 |
|---|---|---|
| `wp-core` | WordPress | バージョン、サイト URL、パーマリンク構造、HTTPS、マルチサイト |
| `wp-paths-sizes` | ディレクトリとサイズ | WordPress / アップロード / テーマ / プラグインのパスとサイズ |
| `wp-active-theme` | アクティブテーマ | テーマ名、バージョン、テンプレート |
| `wp-parent-theme` | 親テーマ | 子テーマ使用時の親テーマ情報 |
| `wp-plugins-active` | 有効なプラグイン | 有効プラグインの一覧 |
| `wp-plugins-inactive` | 無効なプラグイン | 無効プラグインの一覧 |
| `wp-media` | メディア処理 | 画像エディタ、GD/Imagick サポート |
| `wp-server` | サーバー | PHP バージョン、サーバーソフトウェア、cURL バージョン |
| `wp-database` | データベース | MySQL/MariaDB バージョン、DB サイズ |
| `wp-constants` | WordPress 定数 | WP_DEBUG、WP_CACHE、ABSPATH 等 |
| `wp-filesystem` | ファイルシステム権限 | ディレクトリの書き込み可否 |
