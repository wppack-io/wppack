# Debug コンポーネント

> [!WARNING]
> **このコンポーネントは開発環境専用です。**
>
> - **本番環境での利用は絶対に避けてください。** `wp_get_environment_type() === 'production'` の場合は自動的に無効化されます。
> - 収集データにはリクエストヘッダー、POST パラメータ、データベースクエリ等の**機密情報が含まれる可能性**があります。パスワードやトークン等の既知の機密キーは自動的にマスクされますが、すべてのケースをカバーするものではありません。
> - `ipWhitelist` と `roleWhitelist` を適切に設定し、アクセスを制限してください。
> - ステージング環境では `enabled: false` をデフォルトとし、必要時のみ有効化することを推奨します。

Debug コンポーネントは、Symfony スタイルの Web デバッグツールバーと美麗なエラーページを WordPress 環境で提供します。完全オリジナルの UI を `wp_footer` にレンダリングし、拡張可能なコレクターシステムによるプロファイリング・モニタリング機能を備えています。

## このコンポーネントの機能

- **オリジナル Web デバッグツールバー** — ページ下部に固定表示、インライン CSS/JS で外部アセット不要
- **美麗なエラーページ** — ダークテーマ、コードコンテキスト付きスタックトレース、リクエスト/環境情報タブ
- **データベースクエリ分析** — クエリ数、合計時間、重複・スロークエリ検出、最適化サジェスチョン
- **メモリ使用量トラッキング** — スナップショットベースの計測、メモリリミットとの比率表示
- **実行時間計測** — WordPress ライフサイクルフェーズ別タイミング、Stopwatch によるカスタム計測
- **キャッシュ統計** — オブジェクトキャッシュのヒット率、トランジェント操作追跡
- **拡張可能なコレクターシステム** — `#[AsDataCollector]` アトリビュートによるカスタムコレクター登録
- **サードパーティ拡張統合** — Debug Bar パネル拡張・QM コレクター拡張をアダプター経由で取り込み表示

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
use WpPack\Component\Debug\Profiler\Stopwatch;
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

```php
use WpPack\Component\Debug\Profiler\Stopwatch;

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
use WpPack\Component\Debug\Profiler\Stopwatch;

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

| コレクター | 名前 | アイコン | Badge 表示 | 説明 |
|-----------|------|---------|-----------|------|
| `RequestDataCollector` | request | 🌐 | Method + Status | HTTP リクエスト/レスポンス情報 |
| `HttpClientDataCollector` | http_client | 🔗 | リクエスト数 | 外部 HTTP リクエスト（タイミング/ステータス） |
| `DatabaseDataCollector` | database | 💾 | クエリ数 | クエリ分析（重複/スロー検出） |
| `EventDataCollector` | event | 🔔 | フック発火数 | WordPress フックモニタリング |
| `MemoryDataCollector` | memory | 📊 | Peak memory | メモリ使用量スナップショット |
| `MailDataCollector` | mail | ✉️ | メール数 | wp_mail() 送信メール追跡 |
| `TimeDataCollector` | time | ⏱️ | Total time | WP ライフサイクルフェーズ別タイミング |
| `UserDataCollector` | user | 👤 | ユーザー名 | 現在のユーザー・ロール・権限 |
| `CacheDataCollector` | cache | 📦 | Hit rate | オブジェクトキャッシュ統計 |
| `RouterDataCollector` | router | 🛤️ | テンプレート名 | マッチしたルール・テンプレート・クエリ変数（FSE/クラシック両対応） |
| `WordPressDataCollector` | wordpress | ⚙️ | WP version | 環境情報（PHP, WP, プラグイン） |
| `LoggerDataCollector` | logger | 📝 | ログ数 | ログメッセージ・非推奨警告 |
| `TranslationDataCollector` | translation | 🔠 | 未翻訳数 | テキストドメイン・翻訳漏れ検出 |
| `DumpDataCollector` | dump | 📌 | dump数 | dump() 呼び出しキャプチャ |

### RequestDataCollector

HTTP リクエスト/レスポンス情報を収集。

- `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE` を収集
- `status_header` フィルターでステータスコードをキャプチャ
- `wp_headers` フィルターでレスポンスヘッダーをキャプチャ
- `http_api_debug` アクションで外部 HTTP API 呼び出しも追跡
- 機密データ自動マスク: `$_POST` / `$_COOKIE` 内のパスワード・トークン・API キー等、`Authorization` / `Cookie` 等のヘッダー
- Badge 色: green（2xx）、yellow（3xx）、red（4xx/5xx）

### DatabaseDataCollector

WordPress クエリログを分析。`SAVEQUERIES` 有効時に動作。

- `log_query_custom_data` フィルターでリアルタイム収集
- `$wpdb->queries` からのフォールバック一括収集
- 重複クエリ検出（同一 SQL が複数回実行）
- スロークエリ検出（>100ms）
- 最適化サジェスチョン自動生成
- Badge 色: green（<20）、yellow（<50）、red（>=50）

### MemoryDataCollector

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

- Badge 色: green（<70% of limit）、yellow（<90%）、red（>=90%）

### TimeDataCollector

WordPress ライフサイクルフェーズ別のタイミングを自動計測。

計測フェーズ:
`muplugins_loaded` → `plugins_loaded` → `setup_theme` → `after_setup_theme` → `init` → `wp_loaded` → `template_redirect` → `wp_footer`

- Stopwatch を注入して使用
- `$_SERVER['REQUEST_TIME_FLOAT']` からのトータル時間
- Badge 色: green（<200ms）、yellow（<1000ms）、red（>=1000ms）

### CacheDataCollector

WordPress オブジェクトキャッシュの統計を収集。

- `$wp_object_cache->cache_hits` / `cache_misses` から取得
- `setted_transient`, `deleted_transient` フックでトランジェント操作も追跡
- Badge 色: green（>=80%）、yellow（>=50%）、red（<50%）

### UserDataCollector

現在のユーザー情報を収集。Symfony SecurityDataCollector に相当。

- `wp_get_current_user()` でユーザー情報取得
- ロール・権限一覧、認証方法（cookie / application_password）
- メールアドレスはマスク表示（`***@example.com`）
- Badge 色: green（ログイン済み）、yellow（super_admin）、default（匿名）

### MailDataCollector

`wp_mail()` で送信されたメールを追跡。Symfony MessageDataCollector に相当。

- `wp_mail` フィルターでメール送信をキャプチャ
- `wp_mail_succeeded` / `wp_mail_failed` アクションで送信結果を記録
- 宛先アドレスのマスク、メッセージ本文の切り詰め
- Badge 色: green（全件成功）、yellow（保留あり）、red（失敗あり）

### EventDataCollector

WordPress フック（アクション/フィルター）の発火を監視。Symfony EventDataCollector に相当。

- `all` フックでリアルタイム監視
- フック発火回数、ユニークフック数、リスナー数
- 上位 20 フック一覧
- 孤立フック（リスナーなしで発火）検出
- Badge 色: green（<500）、yellow（<1000）、red（>=1000）

### LoggerDataCollector

ログメッセージと WordPress 非推奨警告を収集。

- `log()` メソッドで外部からログ注入可能
- `deprecated_function_run`, `deprecated_argument_run`, `deprecated_hook_run`, `doing_it_wrong_run` アクションで非推奨警告をキャプチャ
- ログレベル別集計
- Badge 色: red（error 以上あり）、yellow（warning あり）、green（info/debug のみ）

### RouterDataCollector

WordPress ルーティング情報を収集。Symfony RouterDataCollector に相当。FSE（ブロックテーマ）とクラシックテーマの両方に対応。

- `parse_request` アクションでマッチしたリライトルールをキャプチャ
- `template_include` フィルターでテンプレートファイルをキャプチャ（クラシックテーマ）
- `wp_is_block_theme()` でブロックテーマを検出し、`$_wp_current_template_id`（WP 6.4+）+ `get_block_template()` でブロックテンプレート情報を収集（FSE）
- テンプレートコンテンツから `wp:template-part` を正規表現で抽出し、各パーツの slug/area/source を表示
- `is_front_page()`, `is_singular()`, `is_archive()`, `is_404()` 等の条件タグ
- Badge 表示: クラシックテーマは PHP ファイル名（例: `single.php`）、FSE はテンプレートスラッグ（例: `single`）
- Badge 色: red（404）、green（ルールマッチ）、default（その他）

### HttpClientDataCollector

外部 HTTP リクエスト（WP_Http）を追跡。Symfony HttpClientDataCollector に相当。

- `pre_http_request` フィルターでリクエスト開始時刻を記録
- `http_api_debug` アクションでレスポンスをキャプチャ
- リクエスト/レスポンスヘッダーの機密情報マスク
- Badge 色: red（エラーあり or 合計 >5000ms）、yellow（スロー >1000ms あり）、green（その他）

### TranslationDataCollector

翻訳（i18n）の使用状況を監視。Symfony TranslationDataCollector に相当。

- `gettext`, `gettext_with_context`, `ngettext` フィルターで翻訳ルックアップを追跡
- `load_textdomain`, `unload_textdomain` アクションでドメインロードを監視
- 未翻訳文字列の検出（原文 === 翻訳文）
- Badge 色: red（>20 未翻訳）、yellow（>0 未翻訳）、green（0）

### DumpDataCollector

`dump()` 呼び出しをキャプチャしツールバーに表示。Symfony VarDumper 統合に相当。

- `capture()` メソッドで変数ダンプを記録
- `debug_backtrace()` で呼び出し元ファイル・行番号を取得
- 長い出力は自動切り詰め
- Badge 色: yellow（dump あり — 本番前に削除推奨）、default（0）

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

    public function getBadgeValue(): string
    {
        return (string) ($this->data['total_count'] ?? 0);
    }

    public function getBadgeColor(): string
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

## エラーハンドラー

### 美麗なエラーページ

`WP_DEBUG` 有効時、例外発生時にダークテーマの HTML エラーページを表示:

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

- **サマリーバー**: 各コレクターのバッジ（アイコン + 値）を横並び表示
- **パネル**: バッジクリックで展開するドロップアップパネル
- インライン CSS/JS（外部アセット不要）
- CSS は `#wppack-debug` スコープで名前衝突回避
- z-index: 99999 で常に最前面
- ダークテーマ（#1e1e2e 背景）

### ToolbarSubscriber

WordPress フックと統合して自動表示:

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

// wp_footer (priority: 9999) にフック
$subscriber->register();
```

## アダプター（サードパーティ拡張統合）

Debug Bar / Query Monitor プラグイン自体の UI には出さず（競合関係）、サードパーティ製の拡張パネル/コレクターをアダプター経由で WpPack ツールバーに取り込み表示。

### DebugBarPanelAdapter

```php
// Debug_Bar_Panel を継承したサードパーティクラスを DataCollectorInterface に変換
// class_exists('Debug_Bar_Panel') ガードで安全に動作
// debug_bar_panels フィルターから登録済みパネルを取得
```

### QueryMonitorCollectorAdapter

```php
// QM_Collector を継承したサードパーティクラスを DataCollectorInterface に変換
// class_exists('QM_Collector') ガードで安全に動作
// qm/collectors フィルターから登録済みコレクターを取得
```

## DI 統合

### DebugServiceProvider

```php
use WpPack\Component\Debug\DependencyInjection\DebugServiceProvider;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\DependencyInjection\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->addServiceProvider(new DebugServiceProvider());
$builder->addCompilerPass(new RegisterDataCollectorsPass());
$container = $builder->compile();
```

### RegisterDataCollectorsPass

`#[AsDataCollector]` アトリビュートまたは `debug.data_collector` タグを持つサービスを自動検出し、`Profile` に priority 順で注入。

## テスト

```php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\Profiler\Stopwatch;

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
- カスタムコレクターによる独自のデバッグ情報収集
- サードパーティ Debug Bar / QM 拡張の統合表示

**代替を検討すべき場合：**
- 基本的な `WP_DEBUG` で十分なシンプルなサイト
- 本番環境のみのデプロイ（開発環境でのみ使用推奨）

## 依存関係

### 必須
- PHP 8.2+

### 開発時推奨
- **Hook コンポーネント** — WordPress アクション/フィルター登録用
- **DependencyInjection コンポーネント** — サービスコンテナと `#[AsDataCollector]` 自動検出用
- **HttpFoundation コンポーネント** — `HttpException` ステータスコード取得用

### オプション
- **Database コンポーネント** — データベースクエリプロファイリング用
- **Cache コンポーネント** — キャッシュ操作モニタリング用
