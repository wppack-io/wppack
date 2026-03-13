# Stopwatch

コード実行時間を計測するストップウォッチコンポーネント。名前付きタイマーの開始・停止とタイミングデータの収集を行います。

## インストール

```bash
composer require wppack/stopwatch
```

## 基本的な使い方

```php
use WpPack\Component\Stopwatch\Stopwatch;

$stopwatch = new Stopwatch();

// タイマーを開始
$stopwatch->start('my_operation', 'app');

// ... 重い処理 ...

// タイマーを停止してイベントを取得
$event = $stopwatch->stop('my_operation');

echo $event->duration; // ミリ秒
echo $event->memory;   // バイト（停止時の memory_get_usage）
echo $event->category; // 'app'
```

## 複数タイマーの並行実行

複数のタイマーを同時に実行できます:

```php
$stopwatch->start('db_query', 'database');
$stopwatch->start('template', 'rendering');

$stopwatch->stop('db_query');
$stopwatch->stop('template');

// 完了したすべてのイベントを取得
$events = $stopwatch->getEvents();
```

## Debug コンポーネントとの統合

`wppack/debug` の `StopwatchDataCollector` は Stopwatch のデータを自動的に収集し、デバッグツールバーのパフォーマンスパネルに表示します。

```php
use WpPack\Component\Stopwatch\Stopwatch;
use WpPack\Component\Debug\Profiler\Profiler;

$stopwatch = new Stopwatch();
$profiler = new Profiler($stopwatch);

// profile() でコールバックの実行時間を自動計測
$result = $profiler->profile('my_operation', function () {
    // 処理
    return 'done';
}, 'app');
```

## API リファレンス

### Stopwatch

| メソッド | 説明 |
|---------|------|
| `start(string $name, string $category = 'default'): void` | 名前付きタイマーを開始 |
| `stop(string $name): StopwatchEvent` | タイマーを停止しイベントを返す |
| `isStarted(string $name): bool` | タイマーが実行中か確認 |
| `getEvent(string $name): StopwatchEvent` | 完了したイベントを取得 |
| `getEvents(): array<string, StopwatchEvent>` | すべての完了イベントを取得 |
| `reset(): void` | すべてのタイマーとイベントをクリア |

### StopwatchEvent（readonly）

| プロパティ | 型 | 説明 |
|-----------|------|------|
| `name` | `string` | タイマー名 |
| `category` | `string` | カテゴリラベル |
| `duration` | `float` | 実行時間（ミリ秒） |
| `memory` | `int` | 停止時のメモリ使用量（バイト） |
| `startTime` | `float` | 開始時刻（ミリ秒、hrtime ベース） |
| `endTime` | `float` | 終了時刻（ミリ秒、hrtime ベース） |

## 要件

- PHP 8.2+
