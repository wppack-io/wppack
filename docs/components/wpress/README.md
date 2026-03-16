# Wpress コンポーネント

**パッケージ:** `wppack/wpress`
**名前空間:** `WpPack\Component\Wpress\`
**レイヤー:** Feature

All-in-One WP Migration が使用する `.wpress` アーカイブ形式を操作するコンポーネントです。`ZipArchive` と同様のインターフェースで `.wpress` ファイルの読み書き・エントリの追加・削除を行えます。

## インストール

```bash
composer require wppack/wpress
```

## `.wpress` ファイル形式

`.wpress` は All-in-One WP Migration 独自のアーカイブ形式です。各エントリはヘッダー（ファイル名、サイズ、パス等）+ データのバイナリシーケンスで構成されます。

```
archive.wpress
├── database.sql
├── package.json
├── wp-content/uploads/2024/01/image.jpg
├── wp-content/plugins/my-plugin/...
└── ...
```

## WpressArchive

`.wpress` ファイルを操作する単一のクラスです。`ZipArchive` と同様に、開く・読む・追加・削除を1つのクラスで行います。

### アーカイブを開く

```php
use WpPack\Component\Wpress\WpressArchive;

// 既存のアーカイブを開く
$archive = new WpressArchive('/path/to/backup.wpress');

// 新規作成
$archive = new WpressArchive('/path/to/new.wpress', WpressArchive::CREATE);
```

### エントリの読み取り

```php
$archive = new WpressArchive('/path/to/backup.wpress');

// エントリ一覧
foreach ($archive->getEntries() as $entry) {
    echo $entry->getPath();     // wp-content/uploads/2024/01/image.jpg
    echo $entry->getSize();     // 102400
    echo $entry->getMTime();    // 1706140800
}

// 特定のエントリを取得
$entry = $archive->getEntry('database.sql');
$sql = $entry->getContents();

// ストリームとして取得（大きなファイル向け）
$stream = $entry->getStream();

// エントリ数
echo $archive->count(); // 1234

// メタ情報（package.json はエントリとして取得し json_decode する）
$entry = $archive->getEntry('package.json');
$meta = json_decode($entry->getContents(), true);
echo $meta['SiteURL'];          // https://example.com
echo $meta['WordPress']['Version']; // 6.4.2
```

### エントリの展開

```php
$archive = new WpressArchive('/path/to/backup.wpress');

// 全エントリを展開
$archive->extractTo('/path/to/destination');

// 特定のパス配下のみ展開
$archive->extractTo('/path/to/destination', filter: 'wp-content/uploads/');
```

### エントリの追加

```php
$archive = new WpressArchive('/path/to/backup.wpress');

// ファイルを追加
$archive->addFile('/absolute/path/to/image.jpg', 'wp-content/uploads/2024/01/image.jpg');

// ディレクトリを一括追加
$archive->addDirectory(WP_CONTENT_DIR . '/uploads/', 'wp-content/uploads/');

// 文字列からエントリを追加
$archive->addFromString('package.json', json_encode($meta));

// 保存
$archive->close();
```

### エントリの削除

```php
$archive = new WpressArchive('/path/to/backup.wpress');

// 特定のエントリを削除
$archive->deleteEntry('wp-content/debug.log');

// パターンで一括削除
$archive->deleteEntries('wp-content/cache/*');

$archive->close();
```

### 新規アーカイブの作成

```php
$archive = new WpressArchive('/path/to/new.wpress', WpressArchive::CREATE);

$archive->addFromString('package.json', json_encode([
    'SiteURL' => get_site_url(),
    'HomeURL' => get_home_url(),
    'WordPress' => ['Version' => get_bloginfo('version')],
    'PHP' => ['Version' => PHP_VERSION],
]));

$archive->addFile('/path/to/dump.sql', 'database.sql');
$archive->addDirectory(WP_CONTENT_DIR . '/uploads/', 'wp-content/uploads/');
$archive->addDirectory(WP_CONTENT_DIR . '/themes/my-theme/', 'wp-content/themes/my-theme/');

$archive->close();
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `WpressArchive` | `.wpress` アーカイブの読み書き・操作 |
| `WpressEntry` | アーカイブ内の個別エントリ（name, size, mtime, prefix, コンテンツ） |

## 関連仕様書

- [.wpress ファイルフォーマット仕様](../specifications/wpress-format.md) — バイナリ形式の詳細
- [All-in-One WP Migration バックアップ仕様](../specifications/all-in-one-wp-migration/all-in-one-wp-migration.md) — エクスポート/インポートの動作仕様

## 依存関係

### 必須
なし
