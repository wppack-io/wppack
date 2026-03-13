# データコレクター一覧

データコレクターは、リクエスト処理中にプロファイリングデータを収集し、ツールバーに表示するための仕組みです。

## DataCollectorInterface

すべてのコレクターが実装するインターフェース:

```php
interface DataCollectorInterface
{
    public function getName(): string;
    public function collect(): void;
    /** @return array<string, mixed> */
    public function getData(): array;
    public function getLabel(): string;
    public function getIndicatorValue(): string;
    public function getIndicatorColor(): string;
    public function reset(): void;
}
```

| メソッド | 説明 |
|---------|------|
| `getName()` | コレクターの一意な識別名（例: `'request'`, `'database'`） |
| `collect()` | データ収集を実行（`shutdown` 時に呼ばれる） |
| `getData()` | 収集済みデータの連想配列を返す |
| `getLabel()` | ツールバー表示用のラベル（デフォルト: `ucfirst(getName())`) |
| `getIndicatorValue()` | インジケーターに表示する値（例: クエリ数、メモリ量） |
| `getIndicatorColor()` | インジケーターの色（`'green'`, `'yellow'`, `'red'`, `'default'`） |
| `reset()` | 収集データをリセット |

## AbstractDataCollector

デフォルト実装を提供する抽象基底クラス:

- `getData()` — `$this->data` 配列を返す
- `getLabel()` — `ucfirst($this->getName())` を返す
- `getIndicatorValue()` — 空文字列を返す
- `getIndicatorColor()` — `'default'` を返す
- `reset()` — `$this->data` を空配列にリセット

カスタムコレクターはこのクラスを継承し、`getName()` と `collect()` のみ実装すればよい。

## `#[AsDataCollector]` アトリビュート

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataCollector
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0,
    ) {}
}
```

- `name` — コレクターの識別名（`getName()` の戻り値と一致させる）
- `priority` — 登録順序（大きいほど先に登録される）。`RegisterDataCollectorsPass` が降順ソートして `Profile` に注入

## 全コレクター一覧

priority 降順:

| コレクター | 名前 | Priority | Indicator 表示 | Indicator 色の基準 |
|-----------|------|----------|---------------|-------------------|
| `RequestDataCollector` | request | 255 | Method + Status | green(2xx) / yellow(3xx) / red(4xx+) |
| `StopwatchDataCollector` | stopwatch | 250 | Total time | green(<200ms) / yellow(<1s) / red(>=1s) |
| `MemoryDataCollector` | memory | 245 | Peak memory | green(<70%) / yellow(<90%) / red(>=90%) |
| `DatabaseDataCollector` | database | 200 | クエリ数 | green(<20) / yellow(<50) / red(>=50) |
| `CacheDataCollector` | cache | 195 | Hit rate | green(>=80%) / yellow(>=50%) / red(<50%) |
| `HttpClientDataCollector` | http_client | 190 | リクエスト数 | green / yellow(slow) / red(error) |
| `RouterDataCollector` | router | 150 | テンプレート名 | red(404) / green(match) / default |
| `PluginDataCollector` | plugin | 145 | プラグイン数 | — |
| `ThemeDataCollector` | theme | 140 | テーマ名 | — |
| `EventDataCollector` | event | 135 | フック発火数 | green(<500) / yellow(<1000) / red(>=1000) |
| `AjaxDataCollector` | ajax | 130 | AJAX 数 | — |
| `RestDataCollector` | rest | 125 | エンドポイント数 | — |
| `AssetDataCollector` | asset | 120 | アセット数 | — |
| `AdminDataCollector` | admin | 115 | 管理ページ | — |
| `LoggerDataCollector` | logger | 100 | ログ数 | red(error+) / yellow(warning) / green |
| `DumpDataCollector` | dump | 95 | dump 数 | yellow(dump あり) / default(0) |
| `MailDataCollector` | mail | 90 | メール数 | green(成功) / yellow(保留) / red(失敗) |
| `SecurityDataCollector` | security | 85 | ユーザー名 | green(認証済) / yellow(super_admin) / default(匿名) |
| `WidgetDataCollector` | widget | 80 | ウィジェット数 | — |
| `ContainerDataCollector` | container | 75 | サービス数 | — |
| `ShortcodeDataCollector` | shortcode | 70 | ショートコード数 | — |
| `FeedDataCollector` | feed | 65 | フィード数 | — |
| `EnvironmentDataCollector` | environment | 50 | 環境タイプ | — |
| `SchedulerDataCollector` | scheduler | 50 | タスク数 | — |
| `TranslationDataCollector` | translation | 45 | 未翻訳数 | red(>20) / yellow(>0) / green(0) |
| `WordPressDataCollector` | wordpress | 40 | WP version | — |

### アダプター

| アダプター | 名前 | Priority | 説明 |
|-----------|------|----------|------|
| `DebugBarPanelAdapter` | debug_bar | -100 | Debug Bar パネル拡張を取り込み |

## カテゴリ別詳細

### Infrastructure

#### StopwatchDataCollector（priority: 250）

WordPress ライフサイクルフェーズ別のタイミングを自動計測。

計測フェーズ:
`muplugins_loaded` → `plugins_loaded` → `setup_theme` → `after_setup_theme` → `init` → `wp_loaded` → `template_redirect` → `wp_footer`

- [Stopwatch コンポーネント](../stopwatch/)を注入して使用
- `$_SERVER['REQUEST_TIME_FLOAT']` からのトータル時間
- Indicator 色: green（<200ms）、yellow（<1000ms）、red（>=1000ms）

#### MemoryDataCollector（priority: 245）

メモリ使用量をスナップショットベースで計測。

```php
use WpPack\Component\Debug\DataCollector\MemoryDataCollector;

$collector = new MemoryDataCollector();

// 手動スナップショット
$collector->takeSnapshot('before_heavy_operation');
// ... heavy operation ...
$collector->takeSnapshot('after_heavy_operation');

// WordPress フック経由の自動スナップショット:
// wp_loaded, template_redirect, wp_footer, shutdown
```

- Indicator 色: green（<70% of limit）、yellow（<90%）、red（>=90%）

### Request & Routing

#### RequestDataCollector（priority: 255）

HTTP リクエスト/レスポンス情報を収集。

- `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` を収集
- `status_header` フィルターでステータスコードをキャプチャ
- `wp_headers` フィルターでレスポンスヘッダーをキャプチャ
- `http_api_debug` アクションで外部 HTTP API 呼び出しも追跡
- 機密データ自動マスク: `$_POST` / `$_COOKIE` 内のパスワード・トークン・API キー等、`Authorization` / `Cookie` 等のヘッダー
- Indicator 色: green（2xx）、yellow（3xx）、red（4xx/5xx）

#### RouterDataCollector（priority: 150）

WordPress ルーティング情報を収集。FSE（ブロックテーマ）とクラシックテーマの両方に対応。

- `parse_request` アクションでマッチしたリライトルールをキャプチャ
- `template_include` フィルターでテンプレートファイルをキャプチャ（クラシックテーマ）
- `wp_is_block_theme()` でブロックテーマを検出し、`$_wp_current_template_id`（WP 6.4+）+ `get_block_template()` でブロックテンプレート情報を収集（FSE）
- テンプレートコンテンツから `wp:template-part` を正規表現で抽出し、各パーツの slug/area/source を表示
- `is_front_page()`, `is_singular()`, `is_archive()`, `is_404()` 等の条件タグ
- Indicator 表示: クラシックテーマは PHP ファイル名（例: `single.php`）、FSE はテンプレートスラッグ（例: `single`）
- Indicator 色: red（404）、green（ルールマッチ）、default（その他）

#### RestDataCollector（priority: 125）

WordPress REST API エンドポイントの情報を収集。

- 登録済み REST ルートの一覧
- リクエストされたエンドポイントの情報

#### AjaxDataCollector（priority: 130）

WordPress AJAX リクエストの追跡。

- `wp_ajax_*` / `wp_ajax_nopriv_*` アクションの監視
- AJAX リクエストのアクション名・パラメータ

#### HttpClientDataCollector（priority: 190）

外部 HTTP リクエスト（WP_Http）を追跡。

- `pre_http_request` フィルターでリクエスト開始時刻を記録
- `http_api_debug` アクションでレスポンスをキャプチャ
- リクエスト/レスポンスヘッダーの機密情報マスク
- Indicator 色: red（エラーあり or 合計 >5000ms）、yellow（スロー >1000ms あり）、green（その他）

### WordPress Core

#### WordPressDataCollector（priority: 40）

WordPress 環境情報を収集。

- WordPress バージョン、PHP バージョン
- アクティブテーマ・プラグイン
- デバッグ定数（`WP_DEBUG`, `SAVEQUERIES` 等）

#### PluginDataCollector（priority: 145）

アクティブプラグインの情報を収集。

- プラグイン一覧（名前、バージョン、作者）
- MU プラグイン、ドロップイン

#### ThemeDataCollector（priority: 140）

アクティブテーマの情報を収集。

- テーマ名、バージョン、テンプレート
- 親テーマ（子テーマの場合）
- ブロックテーマかクラシックテーマかの判定

#### AdminDataCollector（priority: 115）

管理画面の情報を収集。

- 現在の管理ページ情報
- 管理メニュー構造

#### SecurityDataCollector（priority: 85）

現在のユーザー情報を収集。

- `wp_get_current_user()` でユーザー情報取得
- ロール・権限一覧、認証方法（cookie / application_password）
- メールアドレスはマスク表示（`***@example.com`）
- Indicator 色: green（ログイン済み）、yellow（super_admin）、default（匿名）

### Data & Cache

#### DatabaseDataCollector（priority: 200）

WordPress クエリログを分析。`SAVEQUERIES` 有効時に動作。

- `log_query_custom_data` フィルターでリアルタイム収集
- `$wpdb->queries` からのフォールバック一括収集
- 重複クエリ検出（同一 SQL が複数回実行）
- スロークエリ検出（>100ms）
- 最適化サジェスチョン自動生成
- Indicator 色: green（<20）、yellow（<50）、red（>=50）

#### CacheDataCollector（priority: 195）

WordPress オブジェクトキャッシュの統計を収集。

- `$wp_object_cache->cache_hits` / `cache_misses` から取得
- `setted_transient`, `deleted_transient` フックでトランジェント操作も追跡
- Indicator 色: green（>=80%）、yellow（>=50%）、red（<50%）

### Assets & UI

#### AssetDataCollector（priority: 120）

登録・エンキューされたスクリプト/スタイルシートの情報を収集。

- `wp_scripts()` / `wp_styles()` から登録済みアセットを取得
- エンキュー済み / 出力済みのハンドル一覧

#### WidgetDataCollector（priority: 80）

登録済みウィジェットの情報を収集。

- `$wp_widget_factory->widgets` から一覧取得
- アクティブなサイドバーとウィジェットインスタンス

#### ShortcodeDataCollector（priority: 70）

登録済みショートコードの情報を収集。

- `$shortcode_tags` グローバルから一覧取得
- ショートコードのコールバック情報

### Events & Logging

#### EventDataCollector（priority: 135）

WordPress フック（アクション/フィルター）の発火を監視。

- `all` フックでリアルタイム監視
- フック発火回数、ユニークフック数、リスナー数
- 上位 20 フック一覧
- 孤立フック（リスナーなしで発火）検出
- Indicator 色: green（<500）、yellow（<1000）、red（>=1000）

#### LoggerDataCollector（priority: 100）

ログメッセージと WordPress 非推奨警告を収集。

- `log()` メソッドで外部からログ注入可能
- `deprecated_function_run`, `deprecated_argument_run`, `deprecated_hook_run`, `doing_it_wrong_run` アクションで非推奨警告をキャプチャ
- ログレベル別集計
- Indicator 色: red（error 以上あり）、yellow（warning あり）、green（info/debug のみ）

### Communication

#### MailDataCollector（priority: 90）

`wp_mail()` で送信されたメールを追跡。

- `wp_mail` フィルターでメール送信をキャプチャ
- `wp_mail_succeeded` / `wp_mail_failed` アクションで送信結果を記録
- 宛先アドレスのマスク、メッセージ本文の切り詰め
- Indicator 色: green（全件成功）、yellow（保留あり）、red（失敗あり）

#### TranslationDataCollector（priority: 45）

翻訳（i18n）の使用状況を監視。

- `gettext`, `gettext_with_context`, `ngettext` フィルターで翻訳ルックアップを追跡
- `load_textdomain`, `unload_textdomain` アクションでドメインロードを監視
- 未翻訳文字列の検出（原文 === 翻訳文）
- Indicator 色: red（>20 未翻訳）、yellow（>0 未翻訳）、green（0）

### Scheduling

#### SchedulerDataCollector（priority: 50）

スケジュールタスクの情報を収集。

- WP-Cron イベントの一覧
- スケジュール間隔、次回実行時刻

### Environment

#### EnvironmentDataCollector（priority: 50）

PHP / サーバー環境の詳細情報を収集。

- PHP バージョン、SAPI、拡張モジュール
- サーバーソフトウェア、OS 情報
- PHP 設定値（`memory_limit`, `max_execution_time` 等）

### Development

#### DumpDataCollector（priority: 95）

`dump()` 呼び出しをキャプチャしツールバーに表示。

- `capture()` メソッドで変数ダンプを記録
- `debug_backtrace()` で呼び出し元ファイル・行番号を取得
- 長い出力は自動切り詰め
- Indicator 色: yellow（dump あり — 本番前に削除推奨）、default（0）

#### ContainerDataCollector（priority: 75）

DI コンテナのサービス情報を収集。

- 登録済みサービスの一覧
- サービスプロバイダー情報

#### FeedDataCollector（priority: 65）

RSS / Atom フィードの情報を収集。

- 登録済みフィードの一覧
- フィード URL とタイプ

### Adapter

#### DebugBarPanelAdapter（priority: -100）

Debug Bar プラグインのサードパーティ拡張パネルを `DataCollectorInterface` に変換。

- `class_exists('Debug_Bar_Panel')` ガードで安全に動作
- `debug_bar_panels` フィルターから登録済みパネルを取得
- Debug Bar プラグイン自体の UI には表示せず、WpPack ツールバーに統合

## カスタムコレクターの作成

```php
use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\DataCollector\AbstractDataCollector;

#[AsDataCollector(name: 'api_calls', priority: 40)]
final class ApiCallsDataCollector extends AbstractDataCollector
{
    /** @var list<array{url: string, method: string, time: float, status: int}> */
    private array $calls = [];

    public function getName(): string
    {
        return 'api_calls';
    }

    public function trackCall(string $url, string $method, float $time, int $status): void
    {
        $this->calls[] = compact('url', 'method', 'time', 'status');
    }

    public function collect(): void
    {
        $totalTime = array_sum(array_column($this->calls, 'time'));

        $this->data = [
            'calls' => $this->calls,
            'total_count' => count($this->calls),
            'total_time' => $totalTime,
        ];
    }

    public function getIndicatorValue(): string
    {
        return (string) ($this->data['total_count'] ?? 0);
    }

    public function getIndicatorColor(): string
    {
        $count = $this->data['total_count'] ?? 0;

        return match (true) {
            $count < 5 => 'green',
            $count < 15 => 'yellow',
            default => 'red',
        };
    }
}
```

DI コンテナ使用時は `RegisterDataCollectorsPass` が `#[AsDataCollector]` を自動検出し、priority 順で `Profile` に登録します。
