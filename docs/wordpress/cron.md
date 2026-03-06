# WordPress WP-Cron API 仕様

## 1. 概要

WP-Cron は WordPress の擬似 cron（スケジューラー）システムです。OS の cron とは異なり、HTTP リクエストをトリガーとして動作します。ページアクセス時にスケジュール済みのタスクを確認し、実行期限が過ぎたイベントをバックグラウンドで実行します。

WP-Cron のデータは `cron` オプション（`wp_options` テーブル）に保存されます。

### グローバル変数・定数

| 定数 / 変数 | 型 | 説明 |
|---|---|---|
| `DISABLE_WP_CRON` | `bool` | `true` で WP-Cron のフロントエンドトリガーを無効化 |
| `ALTERNATE_WP_CRON` | `bool` | `true` で代替 cron メカニズム（リダイレクト方式）を使用 |
| `WP_CRON_LOCK_TIMEOUT` | `int` | cron ロックのタイムアウト秒数（デフォルト: 60） |

## 2. データ構造

### `cron` オプションの構造

`get_option('cron')` で取得される配列の構造:

```php
$cron = [
    // タイムスタンプをキーとしたイベント群
    1609459200 => [           // Unix タイムスタンプ（実行予定時刻）
        'hook_name' => [      // フック名
            'unique_key' => [ // md5(serialize($args)) で生成
                'schedule' => 'hourly',     // スケジュール名（単発は false）
                'args'     => [],           // コールバックに渡す引数
                'interval' => 3600,         // 繰り返し間隔（秒）。単発イベントには存在しない
            ],
        ],
    ],
    1609462800 => [
        'another_hook' => [
            // ...
        ],
    ],
    // バージョン情報（配列の末尾）
    'version' => 2,
];
```

### スケジュール定義

WordPress コアでは以下のスケジュールが定義されています:

| スケジュール名 | 間隔（秒） | 表示名 |
|---|---|---|
| `hourly` | 3600 | Once Hourly |
| `twicedaily` | 43200 | Twice Daily |
| `daily` | 86400 | Once Daily |
| `weekly` | 604800 | Once Weekly |

スケジュールは `cron_schedules` フィルターで追加できます:

```php
add_filter('cron_schedules', function (array $schedules): array {
    $schedules['every_five_minutes'] = [
        'interval' => 300,
        'display'  => 'Every Five Minutes',
    ];
    return $schedules;
});
```

## 3. API リファレンス

### イベント登録

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_schedule_event()` | `(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false): bool\|WP_Error` | 繰り返しイベントを登録 |
| `wp_schedule_single_event()` | `(int $timestamp, string $hook, array $args = [], bool $wp_error = false): bool\|WP_Error` | 単発イベントを登録 |
| `wp_reschedule_event()` | `(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false): bool\|WP_Error` | イベントを再スケジュール（内部使用） |

```php
// 繰り返しイベントの登録
wp_schedule_event(time(), 'hourly', 'my_hourly_hook', ['arg1', 'arg2']);

// 単発イベントの登録（10分後に実行）
wp_schedule_single_event(time() + 600, 'my_single_hook', ['arg1']);

// コールバックの登録
add_action('my_hourly_hook', function (string $arg1, string $arg2): void {
    // 毎時実行される処理
});
```

### イベント削除

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_unschedule_event()` | `(int $timestamp, string $hook, array $args = [], bool $wp_error = false): bool\|WP_Error` | 特定のイベントを削除 |
| `wp_clear_scheduled_hook()` | `(string $hook, array $args = [], bool $wp_error = false): int\|false\|WP_Error` | 指定フックの全イベントを削除 |
| `wp_unschedule_hook()` | `(string $hook): int\|false` | 指定フックの全イベントを引数関係なく削除 |

```php
// 特定のイベントを削除（タイムスタンプと引数が必要）
$timestamp = wp_next_scheduled('my_hourly_hook', ['arg1', 'arg2']);
wp_unschedule_event($timestamp, 'my_hourly_hook', ['arg1', 'arg2']);

// フックの全イベントを削除（同じ引数のもの）
wp_clear_scheduled_hook('my_hourly_hook', ['arg1', 'arg2']);

// フックの全イベントを引数関係なく削除
wp_unschedule_hook('my_hourly_hook');
```

### イベント照会

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_next_scheduled()` | `(string $hook, array $args = []): int\|false` | 次回実行予定のタイムスタンプを取得 |
| `wp_get_scheduled_event()` | `(string $hook, array $args = [], ?int $timestamp = null): object\|false` | スケジュール済みイベントを取得 |
| `wp_get_schedules()` | `(): array` | 全スケジュール定義を取得 |
| `wp_get_schedule()` | `(string $hook, array $args = []): string\|false` | イベントのスケジュール名を取得 |

`wp_get_scheduled_event()` が返すオブジェクト:

```php
(object) [
    'hook'      => 'my_hourly_hook',  // フック名
    'timestamp' => 1609459200,         // 次回実行タイムスタンプ
    'schedule'  => 'hourly',           // スケジュール名（単発は false）
    'args'      => ['arg1', 'arg2'],   // 引数
    'interval'  => 3600,               // 間隔（秒）。単発にはなし
]
```

### Cron 実行

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_cron()` | `(): void` | 実行期限の過ぎた cron イベントを実行 |
| `spawn_cron()` | `(int $gmt_time = 0): bool` | cron プロセスをスポーン（HTTP リクエストで非同期実行） |
| `wp_doing_cron()` | `(): bool` | 現在 cron 実行中か判定 |

### 内部関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `_get_cron_array()` | `(): array\|false` | cron 配列を取得（内部使用） |
| `_set_cron_array()` | `(array $cron, bool $wp_error = false): bool\|WP_Error` | cron 配列を保存（内部使用） |
| `_get_cron_lock()` | `(): string\|false` | cron ロックの値を取得 |

## 4. 実行フロー

### ページロード時の cron トリガーフロー

```
WordPress ページロード
│
├── wp-settings.php
│   └── wp-includes/default-filters.php
│       └── add_action('init', 'wp_cron')
│
├── init アクション
│   └── wp_cron()
│       │
│       ├── DISABLE_WP_CRON === true → 即座に return
│       │
│       ├── $crons = _get_cron_array()
│       │   └── 空なら return
│       │
│       ├── $gmt_time = microtime(true) // 現在時刻
│       │
│       ├── 最初のイベントのタイムスタンプ > $gmt_time
│       │   └── まだ実行時刻でない → return
│       │
│       ├── $schedules = wp_get_schedules()
│       │
│       ├── 実行期限の過ぎたイベントを走査
│       │   ├── foreach ($crons as $timestamp => $cronhooks)
│       │   │   ├── $timestamp > $gmt_time → break（時系列順なので残りも未来）
│       │   │   └── foreach ($cronhooks as $hook => $keys)
│       │   │       └── foreach ($keys as $key => $event)
│       │   │           └── $event をスケジュール種別に基づき処理待ちリストに追加
│       │   │
│       │   └── 処理待ちイベントが存在する場合
│       │       └── spawn_cron() を呼び出し
│       │
│       └── return
```

### spawn_cron() の動作

```
spawn_cron($gmt_time)
│
├── cron ロックの確認
│   ├── $lock = _get_cron_lock()
│   └── ロック中（WP_CRON_LOCK_TIMEOUT 秒以内）→ return false
│
├── ALTERNATE_WP_CRON === true の場合
│   ├── Location ヘッダーでクライアントをリダイレクト
│   ├── header('Location: ' . untrailingslashit(home_url()) . '/?' . $query)
│   └── cron 処理を同一リクエスト内で実行
│
├── 通常モード
│   ├── cron ロックを設定（transient）
│   ├── $cron_request = apply_filters('cron_request', [...])
│   │   └── デフォルト:
│   │       'url'  => site_url('wp-cron.php?doing_wp_cron=' . $lock)
│   │       'args' => [
│   │           'timeout'   => 0.01,
│   │           'blocking'  => false,
│   │           'sslverify' => false,
│   │       ]
│   └── wp_remote_post($cron_request['url'], $cron_request['args'])
│       └── 非ブロッキングリクエスト（fire-and-forget）
│
└── return true/false
```

### wp-cron.php の処理フロー

```
wp-cron.php（非同期 HTTP リクエストで呼び出される）
│
├── DOING_CRON 定数を定義（true）
│
├── cron ロックを検証
│   └── $_GET['doing_wp_cron'] がロック値と一致するか確認
│
├── cron ロックを更新
│
├── $crons = _get_cron_array()
│
├── 期限切れイベントを走査
│   └── foreach ($crons as $timestamp => $cronhooks)
│       ├── $timestamp > time() → break
│       └── foreach ($cronhooks as $hook => $args)
│           │
│           ├── 繰り返しイベントの場合
│           │   ├── wp_reschedule_event($timestamp, $schedule, $hook, $args)
│           │   │   └── 次回実行時刻 = $timestamp + $interval
│           │   │       （過去の場合は現在時刻 + $interval）
│           │   └── wp_unschedule_event($timestamp, $hook, $args)
│           │       └── 元のイベントを削除
│           │
│           ├── 単発イベントの場合
│           │   └── wp_unschedule_event($timestamp, $hook, $args)
│           │       └── イベントを削除
│           │
│           └── do_action_ref_array($hook, $args)
│               └── 登録済みコールバックを実行
│
└── cron ロックを解除
```

### イベント登録フロー

```
wp_schedule_event($timestamp, $recurrence, $hook, $args)
│
├── apply_filters('schedule_event', $event)
│   └── false が返されたら登録中止
│
├── スケジュール名の妥当性確認
│   └── wp_get_schedules() に存在するか
│
├── $event オブジェクトを構築
│   └── (object) ['hook', 'timestamp', 'schedule', 'args', 'interval']
│
├── apply_filters('pre_schedule_event', null, $event, $wp_error)
│   └── null 以外が返されたら早期リターン
│
├── _get_cron_array() で既存配列を取得
│
├── $cron[$timestamp][$hook][$key] にイベントを格納
│   └── $key = md5(serialize($args))
│
├── ksort($cron) でタイムスタンプ順にソート
│
└── _set_cron_array($cron) で保存
```

## 5. WP-Cron の制限事項

### タイミングの不正確さ

WP-Cron は HTTP リクエスト駆動のため、以下の制限があります:

- サイトにアクセスがなければ実行されない
- 実行時刻は「予定時刻以降の最初のページリクエスト」に依存
- 高トラフィックサイトでは問題にならないが、低トラフィックでは遅延が発生

### OS cron との併用

`DISABLE_WP_CRON` を `true` に設定し、OS の cron で `wp-cron.php` を定期的に呼び出す方式が推奨されます:

```bash
# 毎分 wp-cron.php を実行
* * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1

# または WP-CLI を使用
* * * * * cd /path/to/wordpress && wp cron event run --due-now > /dev/null 2>&1
```

### 同時実行制御

`spawn_cron()` は transient ベースのロックを使用して同時実行を防ぎます:

- ロック値: `sprintf('%.22F', microtime(true))`（マイクロ秒精度のタイムスタンプ）
- ロックタイムアウト: `WP_CRON_LOCK_TIMEOUT`（デフォルト 60 秒）
- ロック中に別のリクエストが来た場合は cron のスポーンをスキップ

## 6. コア定義済みイベント

WordPress コアが登録する cron イベント:

| フック名 | スケジュール | 説明 |
|---|---|---|
| `wp_version_check` | `twicedaily` | WordPress 本体の更新チェック |
| `wp_update_plugins` | `twicedaily` | プラグインの更新チェック |
| `wp_update_themes` | `twicedaily` | テーマの更新チェック |
| `wp_scheduled_delete` | `daily` | ゴミ箱の定期削除 |
| `wp_scheduled_auto_draft_delete` | `daily` | 自動下書きの定期削除 |
| `delete_expired_transients` | `daily` | 期限切れ transient の削除 |
| `wp_privacy_delete_old_export_files` | `hourly` | 古いエクスポートファイルの削除 |
| `wp_site_health_scheduled_check` | `weekly` | サイトヘルスチェック |
| `recovery_mode_clean_expired_keys` | `daily` | リカバリーモードキーの削除 |

## 7. フック一覧

### Filter

| フック名 | 引数 | 説明 |
|---|---|---|
| `cron_schedules` | `(array $schedules)` | カスタムスケジュールを追加 |
| `schedule_event` | `(object\|false $event)` | イベント登録前にフィルター。`false` で登録を阻止 |
| `pre_schedule_event` | `(null\|bool\|WP_Error $pre, object $event, bool $wp_error)` | イベント登録前の事前チェック |
| `pre_reschedule_event` | `(null\|bool\|WP_Error $pre, object $event, bool $wp_error)` | 再スケジュール前の事前チェック |
| `pre_unschedule_event` | `(null\|bool\|WP_Error $pre, int $timestamp, string $hook, array $args, bool $wp_error)` | イベント削除前の事前チェック |
| `pre_clear_scheduled_hook` | `(null\|int\|false\|WP_Error $pre, string $hook, array $args, bool $wp_error)` | フックの全イベント削除前の事前チェック |
| `pre_unschedule_hook` | `(null\|int\|false $pre, string $hook)` | フックの全イベント（引数関係なく）削除前の事前チェック |
| `pre_get_scheduled_event` | `(null\|false\|object $pre, string $hook, array $args, ?int $timestamp)` | イベント取得前の事前チェック |
| `cron_request` | `(array $cron_request_array)` | `spawn_cron()` の HTTP リクエストパラメータをフィルター |
| `cron_array` | `(array\|false $cron)` | cron 配列の取得時にフィルター。`_get_cron_array()` から呼ばれる |
| `pre_set_cron_array` | `(array $cron)` | cron 配列の保存前にフィルター |
