# ツールバー・パネルレンダラー

## 概要

Debug コンポーネントのツールバーは、以下のパイプラインで HTML を生成します:

```
DataCollector → Profile → PanelRenderer → ToolbarRenderer → HTML
```

1. **DataCollector** がリクエスト処理中にデータを収集
2. **Profile** がコレクターのデータを保持
3. **PanelRenderer** が各コレクターのデータをインジケーター + パネル HTML に変換
4. **ToolbarRenderer** が全パネルを統合し、インジケーターバーとサイドバーを描画

## ToolbarRenderer

`ToolbarRenderer` はツールバー全体のレイアウトを管理します。

### インジケーターバー

ページ下部に固定表示されるバーに、以下の順序でインジケーターを配置:

```
plugin → theme → performance → request → router → rest → ajax → http_client →
stopwatch → memory → database → cache → event → security → logger → container →
asset → widget → shortcode → admin → mail → scheduler → translation → feed → dump
```

- 左端に WordPress アイコン（WordPress パネルへのリンク）
- 右端に Environment インジケーター（環境タイプ表示）
- 上記以外のカスタムコレクターは末尾に自動追加

### サイドバー

インジケータークリックで開くサイドバーは、以下のグループでパネルを配置:

| グループ | パネル |
|---------|--------|
| WordPress | `wordpress`, `plugin`, `theme` |
| Performance | `performance` |
| Request & Routing | `request`, `router`, `rest`, `ajax`, `http_client` |
| Performance Metrics | `stopwatch`, `memory`, `database`, `cache` |
| Events & Security | `event`, `security`, `logger`, `container` |
| Content | `asset`, `widget`, `shortcode`, `admin` |
| Communication | `mail`, `scheduler`, `translation`, `feed` |
| Environment | `environment` |
| Debug | `dump` |

グループ間はディバイダーで区切られます。カスタムパネルはグループ外として末尾に追加されます。

### デフォルトパネル

サイドバーを開いた際に最初に表示されるパネルは `wordpress`（利用可能な場合）、なければ `performance`。

### GenericPanelRenderer

専用のパネルレンダラーが登録されていないコレクターには、`GenericPanelRenderer` がフォールバックとして使用されます。収集データをキー/値テーブルとして汎用的に表示します。

## RendererInterface

すべてのパネルレンダラーが実装するインターフェース:

```php
interface RendererInterface
{
    public function getName(): string;
    public function renderPanel(Profile $profile): string;
    public function renderIndicator(Profile $profile): string;
}
```

| メソッド | 説明 |
|---------|------|
| `getName()` | パネルの識別名（対応するコレクターの `getName()` と一致させる） |
| `renderPanel(Profile $profile)` | サイドバーに表示するパネル HTML を返す |
| `renderIndicator(Profile $profile)` | インジケーターバーに表示するボタン HTML を返す |

## AbstractPanelRenderer

パネルレンダラーの基底クラス。豊富な UI ヘルパーメソッドを提供します。

### インジケーターレンダリング

`renderIndicator()` のデフォルト実装は、対応するコレクターの `getLabel()`、`getIndicatorValue()`、`getIndicatorColor()` を使用してインジケーターボタンを自動生成します。

#### INDICATOR_COLORS 定数

| 色キー | 用途 |
|-------|------|
| `green` | 正常 |
| `yellow` | 警告 |
| `red` | エラー/異常 |
| `default` | 中立（色なし） |

各色は CSS カスタムプロパティ（`--bg`, `--fg`）にマッピングされます。

### UI ヘルパーメソッド

| メソッド | 説明 |
|---------|------|
| `badge(string $label, string $color)` | カラーバッジ HTML を生成 |
| `renderTableRow(string $key, string $value, string $valueClass)` | テーブル行 HTML を生成 |
| `renderKeyValueSection(string $title, array $items)` | タイトル付きキー/値セクションを生成 |
| `renderPerfCard(string $label, string $value, string $unit, string $sub)` | パフォーマンスカード（大きな数値表示）を生成 |
| `renderTimelineRow(array $entry, string $color, float $totalTime)` | タイムラインバー行を生成 |
| `renderAssetTables(array $styleHandles, array $scriptHandles, array $allStyles, array $allScripts)` | スクリプト/スタイル一覧テーブルを生成 |

### フォーマッターメソッド

| メソッド | 説明 |
|---------|------|
| `formatMs(float $ms)` | ミリ秒を文字列にフォーマット（例: `"123.4 ms"`, `"1.23 s"`） |
| `formatBytes(int $bytes)` | バイト数を文字列にフォーマット（例: `"1.23 MB"`） |
| `formatMsCard(float $ms)` | `[value, unit]` 配列を返す（パフォーマンスカード用） |
| `formatBytesCard(int $bytes)` | `[value, unit]` 配列を返す（パフォーマンスカード用） |
| `formatValue(mixed $value)` | 任意の値を HTML 表示用にフォーマット |
| `formatRelativeTime(float $absoluteTimestamp)` | リクエスト開始からの相対時間を表示 |
| `esc(string $value)` | HTML エスケープ |

### データアクセス

```php
$data = $this->getCollectorData($profile, 'collector_name');
```

`Profile` からコレクターデータを安全に取得。コレクターが存在しない場合は空配列を返します。

## `#[AsPanelRenderer]` アトリビュート

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsPanelRenderer
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0,
    ) {}
}
```

- `name` — パネルの識別名（`getName()` の戻り値と一致させる）
- `priority` — 登録順序（大きいほど先に登録される）。`RegisterPanelRenderersPass` が降順ソートして `ToolbarRenderer` に注入

## 組み込みパネルレンダラー一覧

| パネルレンダラー | 名前 | 対応コレクター | 説明 |
|----------------|------|--------------|------|
| `RequestPanelRenderer` | request | RequestDataCollector | リクエスト/レスポンス詳細 |
| `DatabasePanelRenderer` | database | DatabaseDataCollector | クエリ一覧・重複/スロー分析 |
| `StopwatchPanelRenderer` | stopwatch | StopwatchDataCollector | タイムライン・イベント一覧 |
| `MemoryPanelRenderer` | memory | MemoryDataCollector | メモリスナップショット |
| `CachePanelRenderer` | cache | CacheDataCollector | ヒット率・トランジェント |
| `RouterPanelRenderer` | router | RouterDataCollector | ルーティング・テンプレート |
| `HttpClientPanelRenderer` | http_client | HttpClientDataCollector | 外部 HTTP リクエスト |
| `EventPanelRenderer` | event | EventDataCollector | フック発火・リスナー |
| `SecurityPanelRenderer` | security | SecurityDataCollector | ユーザー・ロール・権限 |
| `LoggerPanelRenderer` | logger | LoggerDataCollector | ログメッセージ |
| `MailPanelRenderer` | mail | MailDataCollector | メール送信 |
| `TranslationPanelRenderer` | translation | TranslationDataCollector | 翻訳・未翻訳 |
| `DumpPanelRenderer` | dump | DumpDataCollector | dump() 出力 |
| `WordPressPanelRenderer` | wordpress | WordPressDataCollector | WP + Plugin + Theme 集約 |
| `PluginPanelRenderer` | plugin | PluginDataCollector | プラグイン一覧 |
| `ThemePanelRenderer` | theme | ThemeDataCollector | テーマ情報 |
| `AdminPanelRenderer` | admin | AdminDataCollector | 管理画面 |
| `RestPanelRenderer` | rest | RestDataCollector | REST API エンドポイント |
| `AjaxPanelRenderer` | ajax | AjaxDataCollector | AJAX リクエスト |
| `AssetPanelRenderer` | asset | AssetDataCollector | スクリプト/スタイル |
| `WidgetPanelRenderer` | widget | WidgetDataCollector | ウィジェット |
| `ShortcodePanelRenderer` | shortcode | ShortcodeDataCollector | ショートコード |
| `ContainerPanelRenderer` | container | ContainerDataCollector | DI コンテナ |
| `SchedulerPanelRenderer` | scheduler | SchedulerDataCollector | スケジュールタスク |
| `FeedPanelRenderer` | feed | FeedDataCollector | フィード |
| `EnvironmentPanelRenderer` | environment | EnvironmentDataCollector | 環境情報（ツールバー右端） |
| `PerformancePanelRenderer` | performance | *(複数)* | Stopwatch + Memory + Database を集約 |
| `GenericPanelRenderer` | generic | *(任意)* | 専用レンダラーなしのフォールバック |

## 特殊パネル

### PerformancePanelRenderer

対応するコレクターを持たない集約パネル。`StopwatchDataCollector`、`MemoryDataCollector`、`DatabaseDataCollector` のデータをまとめてパフォーマンスサマリーを表示します。

### EnvironmentPanelRenderer

ツールバー右端に環境タイプ（`local` / `development` / `staging`）を表示する特殊なインジケーター。

### WordPressPanelRenderer

`WordPressDataCollector`、`PluginDataCollector`、`ThemeDataCollector` のデータを集約し、WordPress 環境の全体像を 1 つのパネルに表示します。

## カスタムパネルレンダラーの作成

### ステップ 1: DataCollector を作成

```php
use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\DataCollector\AbstractDataCollector;

#[AsDataCollector(name: 'api_calls', priority: 40)]
final class ApiCallsDataCollector extends AbstractDataCollector
{
    /** @var list<array{url: string, time: float, status: int}> */
    private array $calls = [];

    public function getName(): string
    {
        return 'api_calls';
    }

    public function trackCall(string $url, float $time, int $status): void
    {
        $this->calls[] = compact('url', 'time', 'status');
    }

    public function collect(): void
    {
        $this->data = [
            'calls' => $this->calls,
            'total_count' => count($this->calls),
            'total_time' => array_sum(array_column($this->calls, 'time')),
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

### ステップ 2: PanelRenderer を作成

```php
use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;

#[AsPanelRenderer(name: 'api_calls')]
final class ApiCallsPanelRenderer extends AbstractPanelRenderer
{
    public function getName(): string
    {
        return 'api_calls';
    }

    public function renderPanel(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, 'api_calls');
        $calls = $data['calls'] ?? [];
        $totalTime = $data['total_time'] ?? 0;

        $html = '<div class="wpd-panel-content">';

        // パフォーマンスカード
        [$timeVal, $timeUnit] = $this->formatMsCard($totalTime);
        $html .= '<div class="wpd-perf-grid">';
        $html .= $this->renderPerfCard('Total', (string) count($calls), 'calls', '');
        $html .= $this->renderPerfCard('Time', $timeVal, $timeUnit, '');
        $html .= '</div>';

        // 呼び出し一覧テーブル
        $html .= '<table class="wpd-table"><thead><tr>';
        $html .= '<th>URL</th><th>Status</th><th>Time</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($calls as $call) {
            $color = $call['status'] >= 400 ? 'red' : 'green';
            $html .= '<tr>';
            $html .= '<td>' . $this->esc($call['url']) . '</td>';
            $html .= '<td>' . $this->badge((string) $call['status'], $color) . '</td>';
            $html .= '<td>' . $this->formatMs($call['time']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }
}
```

### ステップ 3: DI に登録

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;

$builder = new ContainerBuilder();

// コレクターとレンダラーを登録
$builder->register(ApiCallsDataCollector::class);
$builder->register(ApiCallsPanelRenderer::class);

// コンパイラーパスが #[AsDataCollector] / #[AsPanelRenderer] を自動検出
$builder->addCompilerPass(new RegisterDataCollectorsPass());
$builder->addCompilerPass(new RegisterPanelRenderersPass());
```

## DI 統合

### RegisterPanelRenderersPass

`#[AsPanelRenderer]` アトリビュートまたは `debug.panel_renderer` タグを持つサービスを自動検出し、`ToolbarRenderer` に priority 順で注入するコンパイラーパス。

```php
use WpPack\Component\Debug\DependencyInjection\DebugServiceProvider;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;

$builder->addServiceProvider(new DebugServiceProvider());
$builder->addCompilerPass(new RegisterDataCollectorsPass());
$builder->addCompilerPass(new RegisterPanelRenderersPass());
```
