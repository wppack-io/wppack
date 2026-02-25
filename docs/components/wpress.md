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
    echo $entry->isDirectory(); // false
}

// 特定のエントリを取得
$entry = $archive->getEntry('database.sql');
$sql = $entry->getContents();

// ストリームとして取得（大きなファイル向け）
$stream = $entry->getStream();

// エントリ数
echo $archive->count(); // 1234

// メタ情報（package.json）
$meta = $archive->getMeta();
echo $meta->getSiteUrl();
echo $meta->getWpVersion();
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
    'SiteUrl' => get_site_url(),
    'HomeUrl' => get_home_url(),
    'WPVersion' => get_bloginfo('version'),
    'PHPVersion' => PHP_VERSION,
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
| `WpressEntry` | アーカイブ内の個別エントリ |
| `WpressMeta` | `package.json` のメタ情報 |

## 依存関係

### 必須
なし
