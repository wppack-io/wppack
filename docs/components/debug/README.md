# Debug コンポーネント

> [!WARNING]
> **このコンポーネントは開発環境専用です。**
>
> - **本番環境での利用は絶対に避けてください。** `wp_get_environment_type() === 'production'` の場合は自動的に無効化されます。
> - 収集データにはリクエストヘッダー、POST パラメータ、データベースクエリ等の**機密情報が含まれる可能性**があります。パスワードやトークン等の既知の機密キーは自動的にマスクされますが、すべてのケースをカバーするものではありません。
> - `ipWhitelist` と `roleWhitelist` を適切に設定し、アクセスを制限してください。
> - ステージング環境では `enabled: false` をデフォルトとし、必要時のみ有効化することを推奨します。

Debug コンポーネントは、Symfony スタイルの Web デバッグツールバーと美麗なエラーページを WordPress 環境で提供します。完全オリジナルの UI を `wp_footer` にレンダリングし、拡張可能なコレクター・パネルレンダラーシステムによるプロファイリング・モニタリング機能を備えています。

## このコンポーネントの機能

- **オリジナル Web デバッグツールバー** — ページ下部に固定表示、インライン CSS/JS で外部アセット不要
- **美麗なエラーページ** — ダークテーマ、コードコンテキスト付きスタックトレース、リクエスト/環境情報タブ
- **データベースクエリ分析** — クエリ数、合計時間、重複・スロークエリ検出、最適化サジェスチョン
- **メモリ使用量トラッキング** — スナップショットベースの計測、メモリリミットとの比率表示
- **実行時間計測** — WordPress ライフサイクルフェーズ別タイミング、Stopwatch によるカスタム計測
- **キャッシュ統計** — オブジェクトキャッシュのヒット率、トランジェント操作追跡
- **拡張可能なコレクターシステム** — `#[AsDataCollector]` アトリビュートによるカスタムコレクター登録
- **パネルレンダラーシステム** — `#[AsPanelRenderer]` アトリビュートによるカスタムパネル登録
- **サードパーティ拡張統合** — Debug Bar パネル拡張をアダプター経由で取り込み表示

## インストール

```bash
composer require wppack/debug
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 限定的な情報での基本的なデバッグ
define('WP_DEBUG', true);
define('SAVEQUERIES', true);

global $wpdb;
print_r($wpdb->queries);

echo 'Memory: ' . memory_get_usage(true);

$start = microtime(true);
// Some code
echo 'Time: ' . (microtime(true) - $start);
```

### After（WpPack Debug）

```php
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Stopwatch\Stopwatch;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;

// 設定 — 環境変数 or コンストラクタ引数
$config = new DebugConfig(enabled: true, showToolbar: true);

// Stopwatch で任意の処理を計測
$stopwatch = new Stopwatch();
$stopwatch->start('my_operation', 'business');
// ... some work ...
$event = $stopwatch->stop('my_operation');
echo $event->duration; // ms

// Profiler でクロージャをプロファイリング
$profiler = new Profiler($stopwatch);
$result = $profiler->profile('order.create', function () {
    return createOrder($data);
});

// ツールバーは wp_footer で自動レンダリング
// エラーページは set_exception_handler で自動表示
```

## コア機能

### デバッグ設定（DebugConfig）

```php
use WpPack\Component\Debug\DebugConfig;

$config = new DebugConfig(
    enabled: true,                                  // デバッグ有効化
    showToolbar: true,                              // ツールバー表示
    ipWhitelist: ['127.0.0.1', '::1'],             // 許可 IP
    roleWhitelist: ['administrator'],               // 許可ロール
);

$config->isEnabled();           // enabled + WP_DEBUG + !production チェック
$config->isAccessAllowed();    // isEnabled + IP ホワイトリスト + ロールホワイトリスト
$config->shouldShowToolbar();   // isAccessAllowed + showToolbar + !ajax/cron/REST
$config->isAllowedIp($ip);     // IP ホワイトリストチェック
$config->isAllowedRole();      // ロールホワイトリストチェック
```

### Stopwatch（タイマー計測）

[Stopwatch コンポーネント](../stopwatch/)が提供するタイマー機能を使用します。

```php
use WpPack\Component\Stopwatch\Stopwatch;

$stopwatch = new Stopwatch();

// 計測開始・停止
$stopwatch->start('database.query', 'database');
// ... execute query ...
$event = $stopwatch->stop('database.query');

echo $event->name;       // 'database.query'
echo $event->category;   // 'database'
echo $event->duration;   // ms（float）
echo $event->memory;     // bytes（stop 時点）

// 複数の計測を並行実行
$stopwatch->start('cache.get');
$stopwatch->start('template.render');
$stopwatch->stop('cache.get');
$stopwatch->stop('template.render');

// 全イベント取得
$events = $stopwatch->getEvents();
```

### Profiler（クロージャプロファイリング）

```php
use WpPack\Component\Debug\Profiler\Profiler;
use WpPack\Component\Stopwatch\Stopwatch;

$profiler = new Profiler(new Stopwatch());

// 戻り値はクロージャの結果
$user = $profiler->profile('user.fetch', function () use ($id) {
    return $database->find('users', $id);
});

// ネストも可能
$result = $profiler->profile('order.create', function () use ($profiler, $data) {
    $user = $profiler->profile('order.user_lookup', function () use ($data) {
        return findUser($data['user_id']);
    });

    return $profiler->profile('order.save', function () use ($data) {
        return saveOrder($data);
    });
});

// Stopwatch イベントを取得
$events = $profiler->getStopwatch()->getEvents();
```

### Profile（リクエストプロファイルデータ）

```php
use WpPack\Component\Debug\Profiler\Profile;

$profile = new Profile(token: 'abc123');
$profile->setUrl('/wp-admin/edit.php');
$profile->setMethod('GET');
$profile->setStatusCode(200);

// コレクターを追加
$profile->addCollector($requestCollector);
$profile->addCollector($databaseCollector);

// 経過時間（REQUEST_TIME_FLOAT からの ms）
echo $profile->getTime();
```

## データコレクター

### 組み込みコレクター

| コレクター | 名前 | Indicator 表示 | 説明 |
|-----------|------|---------------|------|
| `RequestDataCollector` | request | Method + Status | HTTP リクエスト/レスポンス情報 |
| `StopwatchDataCollector` | stopwatch | Total time | WP ライフサイクルフェーズ別タイミング |
| `MemoryDataCollector` | memory | Peak memory | メモリ使用量スナップショット |
| `DatabaseDataCollector` | database | 合計秒数 | クエリ分析（重複/スロー検出） |
| `CacheDataCollector` | cache | Hit rate | オブジェクトキャッシュ統計 |
| `HttpClientDataCollector` | http_client | リクエスト数 | 外部 HTTP リクエスト（タイミング/ステータス） |
| `RouterDataCollector` | router | テンプレート名 | マッチしたルール・テンプレート・クエリ変数 |
| `PluginDataCollector` | plugin | プラグイン数 | アクティブプラグイン情報 |
| `ThemeDataCollector` | theme | テーマ名 | アクティブテーマ情報 |
| `EventDataCollector` | event | フック発火数 | WordPress フックモニタリング |
| `AjaxDataCollector` | ajax | AJAX 数 | WordPress AJAX リクエスト追跡 |
| `RestDataCollector` | rest | エンドポイント数 | REST API エンドポイント情報 |
| `AssetDataCollector` | asset | アセット数 | スクリプト/スタイルシートの登録・出力状況 |
| `AdminDataCollector` | admin | 管理ページ | 管理画面情報 |
| `LoggerDataCollector` | logger | ログ数 | ログメッセージ・PHP エラー・非推奨警告 |
| `DumpDataCollector` | dump | dump 数 | dump() 呼び出しキャプチャ |
| `MailDataCollector` | mail | メール数 | wp_mail() 送信メール追跡 |
| `SecurityDataCollector` | security | ユーザー名 | 現在のユーザー・ロール・権限 |
| `WidgetDataCollector` | widget | ウィジェット数 | 登録済みウィジェット情報 |
| `ContainerDataCollector` | container | サービス数 | DI コンテナのサービス情報 |
| `ShortcodeDataCollector` | shortcode | ショートコード数 | 登録済みショートコード情報 |
| `FeedDataCollector` | feed | フィード数 | RSS/Atom フィード情報 |
| `EnvironmentDataCollector` | environment | 環境タイプ | PHP/サーバー環境情報 |
| `SchedulerDataCollector` | scheduler | タスク数 | スケジュールタスク情報 |
| `TranslationDataCollector` | translation | 未翻訳数 | テキストドメイン・翻訳漏れ検出 |
| `WordPressDataCollector` | wordpress | WP version | 環境情報（PHP, WP, プラグイン） |

> 各コレクターの詳細（収集データ、Indicator 色の基準、WordPress フック等）は [collectors.md](./collectors.md) を参照してください。

### 代表的なコレクター

#### RequestDataCollector

HTTP リクエスト/レスポンス情報を収集。

- `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` を収集
- `status_header` フィルターでステータスコードをキャプチャ
- `wp_headers` フィルターでレスポンスヘッダーをキャプチャ
- `http_api_debug` アクションで外部 HTTP API 呼び出しも追跡
- 機密データ自動マスク: `$_POST` / `$_COOKIE` 内のパスワード・トークン・API キー等、`Authorization` / `Cookie` 等のヘッダー
- Indicator 色: green（2xx）、yellow（3xx）、red（4xx/5xx）

#### DatabaseDataCollector

WordPress クエリログを分析。`SAVEQUERIES` 有効時に動作。

- `log_query_custom_data` フィルターでリアルタイム収集
- `$wpdb->queries` からのフォールバック一括収集
- 重複クエリ検出（同一 SQL が複数回実行）
- スロークエリ検出（>100ms）
- 最適化サジェスチョン自動生成
- Indicator 色: green（<0.5s）、yellow（<1s）、red（>=1s）

#### MemoryDataCollector

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

#### CacheDataCollector

WordPress オブジェクトキャッシュの統計を収集。

- `$wp_object_cache->cache_hits` / `cache_misses` から取得
- `setted_transient`, `deleted_transient` フックでトランジェント操作も追跡
- Indicator 色: green（>=80%）、yellow（>=50%）、red（<50%）

### カスタムコレクターの作成

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

## パネルレンダラー

ツールバーの各パネル（インジケーター + サイドバーパネル）は、`RendererInterface` を実装するパネルレンダラーが描画します。`AbstractPanelRenderer` を継承することで、テーブル・パフォーマンスカード・タイムライン等の豊富な UI ヘルパーを利用できます。

```
DataCollector → Profile → PanelRenderer → ToolbarRenderer → HTML
```

組み込みパネルレンダラーは 28 個。`PerformancePanelRenderer` のように複数コレクターのデータを集約するパネルや、`GenericPanelRenderer`（専用レンダラーがないコレクターのフォールバック）もあります。

カスタムパネルレンダラーの作成方法やアーキテクチャの詳細は [toolbar.md](./toolbar.md) を参照してください。

## エラーハンドラー

Debug コンポーネントは 4 つのエラーハンドラーを提供します。すべてのハンドラーは「統一アーキテクチャ」を採用しており、DI 依存をコンストラクタで nullable にすることで、ドロップインによる早期登録（DI なし軽量モード）と `DebugPlugin::boot()` による DI 版置換の両方に対応します。

### ExceptionHandler

`set_exception_handler()` で未キャッチ例外をインターセプトし、ダークテーマの HTML エラーページを表示:

1. 例外クラス名 + メッセージ + ファイル:行
2. コードスニペット（エラー行ハイライト、±10行コンテキスト）
3. 折りたたみ可能なスタックトレース（各フレームにコードスニペット）
4. Previous exception チェーン
5. Request タブ（URL, method, headers, GET, POST, cookies, server vars）
6. Environment タブ（PHP, WP, extensions, constants）
7. Performance タブ（メモリ/時間情報）

```php
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;

$config = new DebugConfig(enabled: true);
$renderer = new ErrorRenderer();
$handler = new ExceptionHandler($renderer, $config);

// PHP 例外ハンドラーとして登録
$handler->register();

// Routing コンポーネントの wppack_routing_exception アクションにもフック可能
add_action('wppack_routing_exception', [$handler, 'onRoutingException']);
```

本番環境（`isEnabled() === false`）では WordPress デフォルトに委譲。

### WpDieHandler

`wp_die_handler` / `wp_die_ajax_handler` / `wp_die_json_handler` フィルタで `wp_die()` をインターセプトし、コンテキストに応じた 3 つのハンドラバリアントで整形表示:

- **HTML ハンドラ** — 美麗なエラーページ（ExceptionHandler と同じ UI）をレンダリング
- **Ajax ハンドラ** — JSON レスポンスで返却
- **JSON ハンドラ** — JSON レスポンスで返却

`wp_die()` の呼び出し元をバックトレースから特定し、正確なファイル:行をエラーページに表示します。`WP_Error` が渡された場合はエラーコード・データも収集します。

### RedirectHandler

`wp_redirect` フィルタ（priority: `PHP_INT_MAX`）でリダイレクトをインターセプトし、プロファイルデータ付きの中間ページを表示:

- shutdown function ベースで中間ページをレンダリング
- DI 版（フルブート後）ではツールバー付きの中間ページを表示
- 軽量モード（ドロップイン経由、DI なし）ではツールバーなしのシンプルな中間ページを表示
- POST → redirect → GET パターンでのリクエストプロファイリングに有用

### FatalErrorHandler

`WP_Fatal_Error_Handler` インターフェースの実装。致命的な PHP エラー（`E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_USER_ERROR`）をシャットダウン時にキャッチし、`ErrorRenderer` で整形表示します。`fatal-error-handler.php` ドロップインから return されることで WordPress に登録されます。

### ドロップインによる二段階アーキテクチャ

`fatal-error-handler.php` ドロップインは、DI コンテナ起動前（WordPress の `mu-plugins` ロード前）に以下を早期登録します:

1. **ExceptionHandler**（軽量モード） — `set_exception_handler()` で登録
2. **RedirectHandler**（軽量モード） — `wp_redirect` フィルタで登録
3. **FatalErrorHandler** — `WP_Fatal_Error_Handler` として return

`DebugPlugin::boot()` 実行後、DI コンテナから取得した完全版のハンドラーが `register()` で上書きします。これにより、プラグインロード中や DI コンテナコンパイル中の例外・リダイレクトもキャッチできます。

### FlattenException

例外をレンダリング可能な形式に変換:

```php
use WpPack\Component\Debug\ErrorHandler\FlattenException;

$flat = FlattenException::createFromThrowable($exception);

$flat->getClass();      // 例外クラス名
$flat->getMessage();    // メッセージ
$flat->getFile();       // ファイルパス
$flat->getLine();       // 行番号
$flat->getStatusCode(); // HTTP ステータスコード（HttpException 対応）
$flat->getTrace();      // コードコンテキスト付きトレース
$flat->getChain();      // Previous exception チェーン
```

## ツールバー

### ToolbarRenderer

ページ下部に固定表示するオリジナルデバッグツールバー:

- **インジケーターバー**: 各コレクターのインジケーター（アイコン + 値）を横並び表示
- **サイドバーパネル**: インジケータークリックで展開するパネル
- インライン CSS/JS（外部アセット不要）
- CSS は `#wppack-debug` スコープで名前衝突回避
- z-index: 99999 で常に最前面
- ダークテーマ（#1e1e2e 背景）

### ToolbarSubscriber

WordPress フックと統合して自動表示。`wp_footer` / `admin_footer`（priority: 9999）にフックし、ページ下部にツールバーをレンダリングします:

```php
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarSubscriber;

$subscriber = new ToolbarSubscriber(
    config: $config,
    renderer: new ToolbarRenderer(),
    profile: new Profile(),
    collectors: $collectors,  // iterable<DataCollectorInterface>
);

// wp_footer / admin_footer (priority: 9999) にフック
$subscriber->register();
```

リダイレクト処理は `RedirectHandler` が担当します（`wp_redirect` フィルタ経由）。

## アダプター（サードパーティ拡張統合）

Debug Bar プラグイン自体の UI には出さず（競合関係）、サードパーティ製の拡張パネルをアダプター経由で WpPack ツールバーに取り込み表示。

### DebugBarPanelAdapter

```php
// Debug_Bar_Panel を継承したサードパーティクラスを DataCollectorInterface に変換
// class_exists('Debug_Bar_Panel') ガードで安全に動作
// debug_bar_panels フィルターから登録済みパネルを取得
```

## DI 統合

### DebugServiceProvider

```php
use WpPack\Component\Debug\DependencyInjection\DebugServiceProvider;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\DependencyInjection\RegisterPanelRenderersPass;
use WpPack\Component\DependencyInjection\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->addServiceProvider(new DebugServiceProvider());
$builder->addCompilerPass(new RegisterDataCollectorsPass());
$builder->addCompilerPass(new RegisterPanelRenderersPass());
$container = $builder->compile();
```

### RegisterDataCollectorsPass

`#[AsDataCollector]` アトリビュートまたは `debug.data_collector` タグを持つサービスを自動検出し、`Profile` に priority 順で注入。

### RegisterPanelRenderersPass

`#[AsPanelRenderer]` アトリビュートまたは `debug.panel_renderer` タグを持つサービスを自動検出し、`ToolbarRenderer` に priority 順で注入。

## テスト

```php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Stopwatch\Stopwatch;

final class StopwatchTest extends TestCase
{
    #[Test]
    public function startAndStop(): void
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('test');

        $event = $stopwatch->stop('test');

        self::assertGreaterThan(0, $event->duration);
        self::assertSame('test', $event->name);
        self::assertSame('default', $event->category);
    }
}
```

## 設定リファレンス

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|-----------|------|
| `enabled` | `bool` | `false` | デバッグ有効化 |
| `showToolbar` | `bool` | `false` | ツールバー表示 |
| `ipWhitelist` | `list<string>` | `['127.0.0.1', '::1']` | 許可 IP リスト |
| `roleWhitelist` | `list<string>` | `['administrator']` | 許可ロールリスト |

## このコンポーネントの使用場面

**最適な用途：**
- Symfony スタイルのデバッグ体験を WordPress で実現したい場合
- パフォーマンスプロファイリングが必要な開発環境
- データベースクエリの最適化分析
- カスタムコレクター・パネルレンダラーによる独自のデバッグ情報収集
- サードパーティ Debug Bar 拡張の統合表示

**代替を検討すべき場合：**
- 基本的な `WP_DEBUG` で十分なシンプルなサイト
- 本番環境のみのデプロイ（開発環境でのみ使用推奨）

## 依存関係

### 必須
- PHP 8.2+
- **Stopwatch コンポーネント** — タイマー計測・ライフサイクルプロファイリング用

### 開発時推奨
- **Hook コンポーネント** — WordPress アクション/フィルター登録用
- **DependencyInjection コンポーネント** — サービスコンテナと `#[AsDataCollector]` / `#[AsPanelRenderer]` 自動検出用
- **HttpFoundation コンポーネント** — `HttpException` ステータスコード取得用

### オプション
- **Logger コンポーネント** — PHP エラーキャプチャ、チャンネル自動解決、ログパイプライン統合
- **Database コンポーネント** — データベースクエリプロファイリング用
- **Cache コンポーネント** — キャッシュ操作モニタリング用

## Logger コンポーネント統合

Logger コンポーネント（`wppack/logger`）がインストールされている場合、以下の統合が自動的に有効化されます:

### PHP エラーキャプチャ

Logger の `ErrorHandler` が `set_error_handler()` で PHP エラー（`E_WARNING`, `E_DEPRECATED`, `E_NOTICE` 等）をキャプチャし、PSR-3 ログに変換します。ログは `ErrorLogHandler`（`error_log()` 出力）と `DebugHandler`（ツールバー表示）の両方に流れます。

### チャンネル自動解決

Logger の `WordPressChannelResolver` がエラー発生元のファイルパスからプラグイン/テーマ名をチャンネルとして自動解決します（詳細は [Logger ドキュメント](../logger/) を参照）:

```
WP_PLUGIN_DIR/akismet/...              → チャンネル "plugin:akismet"
WPMU_PLUGIN_DIR/custom-mu/...          → チャンネル "plugin:custom-mu"
ABSPATH/wp-content/themes/mytheme/...  → チャンネル "theme:mytheme"
ABSPATH/wp-includes/... or wp-admin/... → チャンネル "wordpress"
その他                                  → チャンネル "php"
```

### WordPress 非推奨警告の Logger 統合

`LoggerDataCollector` はコンストラクタで `LoggerFactory` を必須注入し、WordPress deprecation キャプチャ（`deprecated_function_run` 等）を Logger パイプライン経由で `notice` レベルとして処理します。これにより `error_log()` への出力とツールバー表示が統一されます。

### データフロー

```
PHP Error (E_WARNING, E_DEPRECATED, etc.)
  → ErrorHandler (Logger)
    → WordPressChannelResolver → "akismet"
    → LoggerFactory::create("akismet")->warning(...)
      → ErrorLogHandler → error_log()
      → DebugHandler → LoggerDataCollector → ツールバー

WordPress deprecation hook
  → LoggerDataCollector::captureDeprecation()
    → LoggerFactory::create("wordpress")->notice(...)
      → ErrorLogHandler → error_log()
      → DebugHandler → LoggerDataCollector → ツールバー

Application code: $logger->info("...")
  → ErrorLogHandler → error_log()
  → DebugHandler → LoggerDataCollector → ツールバー
```

### DI 設定

`DebugServiceProvider` が Logger 利用可能時に自動で以下を行います:

1. `LoggerDataCollector` をコンストラクタ注入（autowire）で登録
2. `LoggerPanelRenderer` を条件付きで登録
3. `ErrorHandler::register()` を呼び出して PHP エラーハンドラーを登録

## 関連ドキュメント

- [データコレクター一覧](./collectors.md) — 全 26 コレクターの詳細リファレンス
- [ツールバー・パネルレンダラー](./toolbar.md) — ToolbarRenderer アーキテクチャとカスタムパネルの作成方法
