# Wpress コンポーネント

**パッケージ:** `wppack/wpress`
**名前空間:** `WPPack\Component\Wpress\`
**Category:** Utility

All-in-One WP Migration が使用する `.wpress` アーカイブ形式を操作するコンポーネントです。`ZipArchive` と同様のインターフェースで `.wpress` ファイルの読み書き・エントリの追加・削除を行えます。

> **著作権・帰属**
> `.wpress` フォーマットおよび All-in-One WP Migration は ServMask Inc. が開発・著作権を保有するソフトウェアです（Copyright (C) 2014-2025 ServMask Inc.）。オリジナルのプラグインは GPLv3 ライセンスで配布されています。本コンポーネントは `.wpress` フォーマットの独自実装であり、ServMask Inc. の公式ソフトウェアまたは関連製品ではありません。

## インストール

```bash
composer require wppack/wpress
```

外部依存なし。PHP 8.2+ の標準関数のみで動作します（暗号化利用時は `ext-openssl`、bzip2 圧縮利用時は `ext-bz2` が必要）。

## 基本コンセプト

### Before（従来のアプローチ）

```php
// .wpress ファイルの読み取り — バイナリフォーマットを手動でパース
$handle = fopen('backup.wpress', 'rb');
while (!feof($handle)) {
    $data = fread($handle, 4377);
    $header = unpack('a255name/a14size/a12mtime/a4096prefix', $data);
    $header = array_map('trim', $header);
    if ($header['name'] === '') break;

    $content = fread($handle, (int) $header['size']);
    file_put_contents("/dest/{$header['prefix']}/{$header['name']}", $content);
}
fclose($handle);
```

### After（WPPack）

```php
use WPPack\Component\Wpress\WpressArchive;

$archive = new WpressArchive('backup.wpress');
$archive->extractTo('/dest');
$archive->close();
```

## `.wpress` フォーマットを採用する理由

### オープンソースのアーカイブフォーマット

`.wpress` は All-in-One WP Migration（AI1WM）の開発元である ServMask Inc. が公式サイトで「our open source archive format」と位置づけているオープンソースのアーカイブフォーマットです。

> "Export your site into one tidy bundle using WPRESS, our open source archive format."
> — [servmask.com](https://www.servmask.com/)

AI1WM プラグイン自体が GPLv3 で公開されており、フォーマット仕様はソースコードから完全に読み取れます。Go（[yani-/wpress](https://github.com/yani-/wpress)）、Python（[kugland/wpressarc](https://github.com/kugland/wpressarc)）、Rust（wpress-oxide）等の複数言語で独立した実装が存在するエコシステムが形成されています。

### WordPress マイグレーションのデファクト標準

AI1WM は 500 万以上のアクティブインストールを持つ、最も広く利用されている WordPress マイグレーションプラグインです。`.wpress` フォーマットを直接操作できることで、以下が可能になります：

- **既存資産の活用**: 世界中で蓄積されている `.wpress` バックアップを、AI1WM プラグインに依存せずに読み取り・展開できる
- **ベンダーロックインの回避**: バックアップの作成・編集・検査をプログラマブルに行える
- **ツールチェーンの構築**: CI/CD パイプラインやカスタムスクリプトに `.wpress` 操作を組み込める

### 低メモリ環境に最適化された設計

`.wpress` は共有ホスティング等の制約されたPHP環境で大規模サイトをストリーミング処理できるよう設計されています：

- **シーケンシャル構造**: tar ライクな「ヘッダー + コンテンツ」の繰り返しで、先頭から順次読み取りだけで処理が完結する（ZIP のようにセントラルディレクトリを末尾に持たない）
- **チャンク単位の暗号化・圧縮**: 512KB チャンクで AES-256-CBC 暗号化 / gzip・bzip2 圧縮を行うため、大ファイルでもメモリ消費が一定
- **WordPress 専用メタデータ**: `package.json` にサイト URL、WordPress バージョン、データベース情報等を格納し、マイグレーションに必要な情報を一箇所に集約

## `.wpress` ファイル形式

`.wpress` は All-in-One WP Migration 独自のバイナリアーカイブ形式です。tar に近いシーケンシャル構造で、固定長ヘッダー（4377 bytes）+ ファイルコンテンツの繰り返しで構成されます。マジックナンバーやグローバルヘッダーはありません。

```
archive.wpress
├── package.json          ← メタデータ（サイト情報、暗号化設定）
├── database.sql          ← データベースダンプ
├── wp-content/uploads/   ← メディアファイル
├── wp-content/plugins/   ← プラグインファイル
├── wp-content/themes/    ← テーマファイル
└── [EOF Block]           ← 4377 bytes の NUL
```

### ヘッダー構造

| フィールド | サイズ | 内容 |
|-----------|--------|------|
| `name` | 255 bytes | ファイル名（ベース名のみ） |
| `size` | 14 bytes | ファイルサイズ（10進ASCII） |
| `mtime` | 12 bytes | Unix タイムスタンプ（10進ASCII） |
| `prefix` | 4096 bytes | ディレクトリパス（ルートは `.`） |

> [!NOTE]
> 詳細なバイナリフォーマット仕様は [.wpress ファイルフォーマット仕様](../../specifications/wpress-format.md) を参照してください。

## WpressArchive

`.wpress` ファイルを操作するメインクラスです。`ZipArchive` と同様に、開く・読む・追加・削除を1つのクラスで行います。`\Countable` を実装しており、`count()` でエントリ数を取得できます。

### アーカイブを開く

```php
use WPPack\Component\Wpress\WpressArchive;

// 既存のアーカイブを開く
$archive = new WpressArchive('/path/to/backup.wpress');

// 新規作成
$archive = new WpressArchive('/path/to/new.wpress', WpressArchive::CREATE);

// パスワード付き（暗号化されたアーカイブ）
$archive = new WpressArchive('/path/to/encrypted.wpress', password: 'secret');
```

| パラメータ | 型 | デフォルト | 説明 |
|-----------|------|----------|------|
| `$path` | `string` | — | アーカイブファイルパス |
| `$flags` | `int` | `0` | `WpressArchive::CREATE` で新規作成 |
| `$password` | `?string` | `null` | 暗号化パスワード |

### エントリの読み取り

#### エントリ一覧（ジェネレータ）

`getEntries()` はジェネレータを返します。数万エントリを含むアーカイブでも、1エントリずつ yield するためメモリ消費を抑えられます：

```php
$archive = new WpressArchive('/path/to/backup.wpress');

foreach ($archive->getEntries() as $entry) {
    echo $entry->getPath();     // wp-content/uploads/2024/01/image.jpg
    echo $entry->getName();     // image.jpg
    echo $entry->getPrefix();   // wp-content/uploads/2024/01
    echo $entry->getSize();     // 102400
    echo $entry->getMTime();    // 1706140800
}
```

#### 特定エントリの取得

`getEntry()` は遅延インデックスを使ってランダムアクセスを実現します。初回呼び出し時にアーカイブを走査してヘッダーオフセットのインデックスを構築し、以降はインデックスを再利用します：

```php
$entry = $archive->getEntry('database.sql');
$sql = $entry->getContents();

// エントリが存在しない場合は EntryNotFoundException
try {
    $entry = $archive->getEntry('nonexistent.txt');
} catch (\WPPack\Component\Wpress\Exception\EntryNotFoundException $e) {
    // エントリが見つからない
}
```

#### エントリ数

```php
echo count($archive); // 1234
echo $archive->count(); // 同じ結果
```

### WpressEntry

アーカイブ内の個別エントリを表すクラスです。コンテンツへのアクセスは遅延評価されます。

```php
$entry = $archive->getEntry('database.sql');

// メタデータ（即座に取得可能）
$entry->getPath();    // 'database.sql'
$entry->getName();    // 'database.sql'（ベース名）
$entry->getPrefix();  // '.'（ルート直下の場合）
$entry->getSize();    // 暗号化/圧縮後のバイト数
$entry->getMTime();   // Unix タイムスタンプ

// コンテンツ読み取り（アクセス時にファイルハンドルから読み取り）
$contents = $entry->getContents();  // 文字列として取得（自動復号・伸張）

// ストリームとして取得（大きなファイルの処理に有用）
$stream = $entry->getStream();      // php://temp ストリームリソース
while (!feof($stream)) {
    $chunk = fread($stream, 8192);
    // ... 処理
}
fclose($stream);
```

> [!NOTE]
> `getContents()` は同じエントリに対して複数回呼び出し可能です。毎回ファイルハンドルを seek して読み取ります。暗号化/圧縮されたアーカイブの場合、`package.json` と `multisite.json` は常に平文で読み取られます。

### エントリの展開

```php
$archive = new WpressArchive('/path/to/backup.wpress');

// 全エントリを展開
$archive->extractTo('/path/to/destination');

// 特定のパス配下のみ展開（プレフィックスマッチ）
$archive->extractTo('/path/to/destination', filter: 'wp-content/uploads/');

// テーマだけ展開
$archive->extractTo('/path/to/themes', filter: 'wp-content/themes/');
```

展開時の動作:

- ディレクトリ構造を自動作成（`mkdir` recursive）
- `stream_copy_to_stream()` でチャンクコピー（ファイル全体をメモリに載せない）
- 元の更新日時（mtime）を復元
- 暗号化/圧縮されたエントリは自動的に復号・伸張

### エントリの追加

```php
$archive = new WpressArchive('/path/to/backup.wpress');

// ファイルを追加
$archive->addFile('/absolute/path/to/image.jpg', 'wp-content/uploads/2024/01/image.jpg');

// ディレクトリを一括追加（再帰的に走査）
$archive->addDirectory('/var/www/html/wp-content/uploads/', 'wp-content/uploads');

// 文字列からエントリを追加（package.json 等の小さなデータ向け）
$archive->addFromString('package.json', json_encode($meta));

// 保存（close() を呼ぶまで変更は確定しない）
$archive->close();
```

#### 追記の最適化

追加のみ（削除なし）の場合、EOF マーカーの位置まで seek して上書きするため、既存エントリの再書き込みは発生しません。

### エントリの削除

```php
$archive = new WpressArchive('/path/to/backup.wpress');

// 特定のエントリを削除
$archive->deleteEntry('wp-content/debug.log');

// パターンで一括削除（fnmatch 形式）
$archive->deleteEntries('wp-content/cache/*');

// 複数パターンの削除
$archive->deleteEntries('*.log');
$archive->deleteEntries('wp-content/upgrade/*');

$archive->close();
```

削除は遅延リライト方式で実装されています。`deleteEntry()` / `deleteEntries()` は削除対象をマークするだけで、実際のリライトは `close()` 時に行われます:

1. 一時ファイルに削除対象以外のエントリをコピー
2. 新規追加エントリがあればそれも書き込み
3. `rename()` でアトミックに置換

### ライフサイクル

```php
$archive = new WpressArchive('backup.wpress');

// 読み取りのみの場合も close() で後片付け
$archive->close();

// 変更がない場合、close() はファイルハンドルを閉じるだけ（I/O なし）
```

> [!IMPORTANT]
> `close()` を呼ばないと、追加・削除の変更がファイルに反映されません。

## 暗号化・圧縮

All-in-One WP Migration のプレミアム版でエクスポートされた暗号化/圧縮アーカイブに対応しています。

### 暗号化されたアーカイブの読み取り

```php
// パスワードを指定してアーカイブを開く
$archive = new WpressArchive('encrypted-backup.wpress', password: 'my-secret');

// エントリは自動的に復号される
$entry = $archive->getEntry('database.sql');
$sql = $entry->getContents(); // 平文のSQL

// package.json と multisite.json は常に平文（パスワードなしでも読める）
$archive2 = new WpressArchive('encrypted-backup.wpress');
$meta = $archive2->getEntry('package.json')->getContents(); // OK
```

### 暗号化されたアーカイブの作成

```php
$archive = new WpressArchive('encrypted.wpress', WpressArchive::CREATE, password: 'secret');

// package.json は常に平文で書き込まれる（仕様上の制約）
$archive->addFromString('package.json', json_encode([
    'SiteURL' => 'https://example.com',
    'HomeURL' => 'https://example.com',
    'Encrypted' => true,
]));

// その他のエントリは自動的に暗号化される
$archive->addFile('/path/to/dump.sql', 'database.sql');
$archive->close();
```

### 暗号化/圧縮の4パターン

`package.json` の `Encrypted` と `Compression` フィールドに基づいて、自動的に適切な ContentProcessor が選択されます：

| `Encrypted` | `Compression.Enabled` | 処理 | チャンクフォーマット |
|-------------|----------------------|------|------------------|
| `false` | `false` | なし（平文） | 生データ |
| `true` | `false` | AES-256-CBC | `[IV][暗号化データ]` |
| `false` | `true` | gzip / bzip2 | `[サイズ][圧縮データ]` |
| `true` | `true` | 圧縮→暗号化 | `[サイズ][IV][暗号化された圧縮データ]` |

- **暗号化**: AES-256-CBC、鍵は `substr(sha1($password, true), 0, 16)`、チャンクごとにランダム IV
- **圧縮**: `gzcompress()` / `bzcompress()` レベル 9
- **チャンクサイズ**: 512KB（524,288 bytes）単位で処理
- **対象外**: `package.json` と `multisite.json` は常に平文

## Metadata 値オブジェクト

`package.json` と `multisite.json` の構造を型安全に扱うための値オブジェクト群です。すべて immutable で `\JsonSerializable` を実装しています。JSON のキーは PascalCase（AI1WM 互換）です。

### PackageMetadata（package.json）

アーカイブのメタデータを表します。サイト URL、WordPress バージョン、データベース情報、暗号化設定などを含みます：

```php
use WPPack\Component\Wpress\Metadata\PackageMetadata;
use WPPack\Component\Wpress\Metadata\WordPressInfo;
use WPPack\Component\Wpress\Metadata\DatabaseInfo;
use WPPack\Component\Wpress\Metadata\PhpInfo;
use WPPack\Component\Wpress\Metadata\PluginInfo;

// アーカイブから読み取り
$archive = new WpressArchive('backup.wpress');
$json = $archive->getEntry('package.json')->getContents();
$meta = PackageMetadata::fromJson($json);

echo $meta->siteUrl;                    // https://example.com
echo $meta->homeUrl;                    // https://example.com
echo $meta->wordPress->version;         // 6.4.2
echo $meta->wordPress->absolute;        // /var/www/html/
echo $meta->database->charset;          // utf8mb4
echo $meta->database->prefix;           // wp_
echo $meta->php->version;               // 8.1.0
echo $meta->plugin->version;            // 7.102（AI1WM バージョン）
echo $meta->template;                   // flavor（アクティブテーマ）
echo $meta->stylesheet;                 // flavor-child

// 暗号化/圧縮情報
if ($meta->encrypted) {
    echo "暗号化あり: " . $meta->encryptedSignature;
}
if ($meta->compression?->enabled) {
    echo "圧縮: " . $meta->compression->type; // gzip or bzip2
}
```

#### PackageMetadata の構築

```php
use WPPack\Component\Wpress\Metadata\PackageMetadata;
use WPPack\Component\Wpress\Metadata\WordPressInfo;
use WPPack\Component\Wpress\Metadata\DatabaseInfo;
use WPPack\Component\Wpress\Metadata\PhpInfo;
use WPPack\Component\Wpress\Metadata\PluginInfo;
use WPPack\Component\Wpress\Metadata\ServerInfo;

$meta = new PackageMetadata(
    siteUrl: get_site_url(),
    homeUrl: get_home_url(),
    internalSiteUrl: get_option('siteurl'),
    internalHomeUrl: get_option('home'),
    plugin: new PluginInfo(version: '7.102'),
    wordPress: new WordPressInfo(
        version: get_bloginfo('version'),
        absolute: ABSPATH,
        content: WP_CONTENT_DIR,
        plugins: WP_PLUGIN_DIR,
        themes: [get_theme_root()],
        uploads: wp_upload_dir()['basedir'],
        uploadsUrl: wp_upload_dir()['baseurl'],
    ),
    database: new DatabaseInfo(
        version: $wpdb->db_version(),
        charset: $wpdb->charset,
        collate: $wpdb->collate,
        prefix: $wpdb->prefix,
    ),
    php: new PhpInfo(
        version: PHP_VERSION,
        system: PHP_OS,
        integer: PHP_INT_SIZE,
    ),
    plugins: get_option('active_plugins', []),
    template: get_option('template'),
    stylesheet: get_option('stylesheet'),
    server: new ServerInfo(
        htaccess: file_exists(ABSPATH . '.htaccess')
            ? base64_encode(file_get_contents(ABSPATH . '.htaccess'))
            : null,
    ),
);

// JSON にシリアライズ
$json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// アーカイブに書き込み
$archive->addFromString('package.json', $json);
```

#### PackageMetadata フィールド一覧

| プロパティ | 型 | JSON キー | 説明 |
|-----------|------|----------|------|
| `siteUrl` | `string` | `SiteURL` | `site_url()` |
| `homeUrl` | `string` | `HomeURL` | `home_url()` |
| `internalSiteUrl` | `?string` | `InternalSiteURL` | DB 直接値 |
| `internalHomeUrl` | `?string` | `InternalHomeURL` | DB 直接値 |
| `replace` | `?ReplaceInfo` | `Replace` | ユーザー定義置換値 |
| `noSpamComments` | `?bool` | `NoSpamComments` | スパムコメント除外 |
| `noPostRevisions` | `?bool` | `NoPostRevisions` | リビジョン除外 |
| `noMedia` | `?bool` | `NoMedia` | メディア除外 |
| `noThemes` | `?bool` | `NoThemes` | テーマ除外 |
| `noInactiveThemes` | `?bool` | `NoInactiveThemes` | 非アクティブテーマ除外 |
| `noMustUsePlugins` | `?bool` | `NoMustUsePlugins` | MU プラグイン除外 |
| `noPlugins` | `?bool` | `NoPlugins` | プラグイン除外 |
| `noInactivePlugins` | `?bool` | `NoInactivePlugins` | 非アクティブプラグイン除外 |
| `noCache` | `?bool` | `NoCache` | キャッシュ除外 |
| `noDatabase` | `?bool` | `NoDatabase` | データベース除外 |
| `noEmailReplace` | `?bool` | `NoEmailReplace` | メールドメイン置換しない |
| `plugin` | `?PluginInfo` | `Plugin` | AI1WM バージョン情報 |
| `wordPress` | `?WordPressInfo` | `WordPress` | WordPress 環境情報 |
| `database` | `?DatabaseInfo` | `Database` | データベース情報 |
| `php` | `?PhpInfo` | `PHP` | PHP 環境情報 |
| `plugins` | `?list<string>` | `Plugins` | アクティブプラグイン一覧 |
| `template` | `?string` | `Template` | アクティブテンプレート |
| `stylesheet` | `?string` | `Stylesheet` | アクティブスタイルシート |
| `uploads` | `?string` | `Uploads` | `upload_path` オプション |
| `uploadsUrl` | `?string` | `UploadsURL` | `upload_url_path` オプション |
| `server` | `?ServerInfo` | `Server` | .htaccess / web.config（base64） |
| `encrypted` | `?bool` | `Encrypted` | 暗号化フラグ |
| `encryptedSignature` | `?string` | `EncryptedSignature` | パスワード検証用シグネチャ |
| `compression` | `?CompressionInfo` | `Compression` | 圧縮設定 |

> [!NOTE]
> `null` のフィールドは `json_encode()` 時に省略されます。AI1WM のフォーマットと互換性を維持するための仕様です。

#### サブオブジェクト

**WordPressInfo**:

| プロパティ | JSON キー | 説明 |
|-----------|----------|------|
| `version` | `Version` | WordPress バージョン |
| `absolute` | `Absolute` | `ABSPATH` |
| `content` | `Content` | `WP_CONTENT_DIR` |
| `plugins` | `Plugins` | `WP_PLUGIN_DIR` |
| `themes` | `Themes` | テーマルートパス配列 |
| `uploads` | `Uploads` | アップロードディレクトリ |
| `uploadsUrl` | `UploadsURL` | アップロード URL |

**DatabaseInfo**:

| プロパティ | JSON キー | 説明 |
|-----------|----------|------|
| `version` | `Version` | MySQL/MariaDB バージョン |
| `charset` | `Charset` | 文字セット |
| `collate` | `Collate` | 照合順序 |
| `prefix` | `Prefix` | テーブルプレフィックス |
| `excludedTables` | `ExcludedTables` | 除外テーブル |
| `includedTables` | `IncludedTables` | 包含テーブル |

**PhpInfo**:

| プロパティ | JSON キー | 説明 |
|-----------|----------|------|
| `version` | `Version` | PHP バージョン |
| `system` | `System` | OS 名（`PHP_OS`） |
| `integer` | `Integer` | 整数サイズ（`PHP_INT_SIZE`） |

**ReplaceInfo**:

| プロパティ | JSON キー | 説明 |
|-----------|----------|------|
| `oldValues` | `OldValues` | 置換元の値リスト |
| `newValues` | `NewValues` | 置換先の値リスト |

**CompressionInfo**:

| プロパティ | JSON キー | 説明 |
|-----------|----------|------|
| `enabled` | `Enabled` | 圧縮有効フラグ |
| `type` | `Type` | `gzip` または `bzip2` |

### MultisiteMetadata（multisite.json）

マルチサイト構成のメタデータを表します：

```php
use WPPack\Component\Wpress\Metadata\MultisiteMetadata;

// アーカイブから読み取り
$archive = new WpressArchive('multisite-backup.wpress');
$json = $archive->getEntry('multisite.json')->getContents();
$multisite = MultisiteMetadata::fromJson($json);

echo $multisite->network;   // true
echo count($multisite->sites);  // サイト数

foreach ($multisite->sites as $site) {
    echo $site->blogId;     // 1, 2, 3, ...
    echo $site->siteUrl;    // https://example.com
    echo $site->domain;     // example.com
    echo $site->path;       // /
    echo $site->template;   // テーマ名
    echo $site->wordPress->uploads;     // アップロードパス
    echo $site->wordPress->uploadsUrl;  // アップロード URL
}

// ネットワーク有効化プラグイン
foreach ($multisite->plugins as $plugin) {
    echo $plugin; // sitewide-plugin/sitewide-plugin.php
}

// スーパー管理者
foreach ($multisite->admins as $admin) {
    echo $admin; // admin, superadmin
}
```

#### MultisiteMetadata の構築

```php
use WPPack\Component\Wpress\Metadata\MultisiteMetadata;
use WPPack\Component\Wpress\Metadata\SiteInfo;
use WPPack\Component\Wpress\Metadata\SiteWordPressInfo;

$multisite = new MultisiteMetadata(
    network: true,
    sites: [
        new SiteInfo(
            blogId: 1,
            siteId: 1,
            langId: 0,
            siteUrl: 'https://example.com',
            homeUrl: 'https://example.com',
            domain: 'example.com',
            path: '/',
            plugins: ['akismet/akismet.php'],
            template: 'flavor',
            stylesheet: 'flavor-child',
            wordPress: new SiteWordPressInfo(
                uploads: '/var/www/html/wp-content/uploads',
                uploadsUrl: 'https://example.com/wp-content/uploads',
            ),
        ),
        new SiteInfo(
            blogId: 2,
            siteId: 1,
            siteUrl: 'https://example.com/blog',
            homeUrl: 'https://example.com/blog',
            domain: 'example.com',
            path: '/blog/',
            wordPress: new SiteWordPressInfo(
                uploads: '/var/www/html/wp-content/uploads/sites/2',
                uploadsUrl: 'https://example.com/wp-content/uploads/sites/2',
            ),
        ),
    ],
    plugins: ['sitewide-plugin/sitewide-plugin.php'],
    admins: ['admin', 'superadmin'],
);

$json = json_encode($multisite, JSON_PRETTY_PRINT);
$archive->addFromString('multisite.json', $json);
```

#### SiteInfo フィールド一覧

| プロパティ | JSON キー | 説明 |
|-----------|----------|------|
| `blogId` | `BlogID` | ブログ ID（メインサイト = 1） |
| `siteId` | `SiteID` | ネットワーク ID |
| `langId` | `LangID` | 言語 ID |
| `siteUrl` | `SiteURL` | サイト URL |
| `homeUrl` | `HomeURL` | ホーム URL |
| `domain` | `Domain` | ドメイン |
| `path` | `Path` | パス |
| `plugins` | `Plugins` | サイト個別の有効プラグイン |
| `template` | `Template` | アクティブテンプレート |
| `stylesheet` | `Stylesheet` | アクティブスタイルシート |
| `uploads` | `Uploads` | `upload_path` オプション |
| `uploadsUrl` | `UploadsURL` | `upload_url_path` オプション |
| `wordPress` | `WordPress` | `SiteWordPressInfo`（Uploads / UploadsURL） |

## ユースケース

### バックアップアーカイブの内容検査

```php
use WPPack\Component\Wpress\WpressArchive;
use WPPack\Component\Wpress\Metadata\PackageMetadata;

$archive = new WpressArchive('backup.wpress');

// メタデータの確認
$meta = PackageMetadata::fromJson(
    $archive->getEntry('package.json')->getContents()
);

echo "Site: {$meta->siteUrl}\n";
echo "WP: {$meta->wordPress?->version}\n";
echo "PHP: {$meta->php?->version}\n";
echo "DB: {$meta->database?->version} ({$meta->database?->charset})\n";
echo "Entries: " . count($archive) . "\n";

if ($meta->encrypted) {
    echo "⚠ 暗号化されています\n";
}
if ($meta->compression?->enabled) {
    echo "圧縮: {$meta->compression->type}\n";
}

// サイズ上位のエントリを一覧表示
$entries = [];
foreach ($archive->getEntries() as $entry) {
    $entries[] = ['path' => $entry->getPath(), 'size' => $entry->getSize()];
}
usort($entries, fn($a, $b) => $b['size'] <=> $a['size']);

echo "\nTop 10 largest entries:\n";
foreach (array_slice($entries, 0, 10) as $e) {
    printf("  %s (%s)\n", $e['path'], number_format($e['size']));
}

$archive->close();
```

### アーカイブからの選択的復元

```php
use WPPack\Component\Wpress\WpressArchive;

$archive = new WpressArchive('backup.wpress');

// テーマだけ復元
$archive->extractTo(WP_CONTENT_DIR, filter: 'wp-content/themes/');

// データベースダンプだけ取得
$sql = $archive->getEntry('database.sql')->getContents();
file_put_contents('/tmp/restore.sql', $sql);

$archive->close();
```

### アーカイブの編集（不要ファイルの除去）

```php
use WPPack\Component\Wpress\WpressArchive;

$archive = new WpressArchive('backup.wpress');

// キャッシュやログを除去してアーカイブを軽量化
$archive->deleteEntries('wp-content/cache/*');
$archive->deleteEntries('*.log');
$archive->deleteEntry('wp-content/debug.log');

$archive->close();
```

### 新規アーカイブの完全な作成

```php
use WPPack\Component\Wpress\WpressArchive;
use WPPack\Component\Wpress\Metadata\PackageMetadata;
use WPPack\Component\Wpress\Metadata\WordPressInfo;
use WPPack\Component\Wpress\Metadata\DatabaseInfo;
use WPPack\Component\Wpress\Metadata\PhpInfo;
use WPPack\Component\Wpress\Metadata\PluginInfo;

// 1. package.json を生成
$meta = new PackageMetadata(
    siteUrl: get_site_url(),
    homeUrl: get_home_url(),
    plugin: new PluginInfo(version: '1.0.0'),
    wordPress: new WordPressInfo(
        version: get_bloginfo('version'),
        absolute: ABSPATH,
        content: WP_CONTENT_DIR,
    ),
    database: new DatabaseInfo(
        version: $wpdb->db_version(),
        charset: $wpdb->charset,
        prefix: $wpdb->prefix,
    ),
    php: new PhpInfo(version: PHP_VERSION),
);

// 2. アーカイブを作成
$archive = new WpressArchive('/tmp/export.wpress', WpressArchive::CREATE);

// 3. package.json を先頭に書き込み（AI1WM の慣例）
$archive->addFromString('package.json', json_encode($meta, JSON_PRETTY_PRINT));

// 4. wp-content 配下を追加
$archive->addDirectory(WP_CONTENT_DIR . '/uploads/', 'wp-content/uploads');
$archive->addDirectory(WP_CONTENT_DIR . '/plugins/', 'wp-content/plugins');
$archive->addDirectory(WP_CONTENT_DIR . '/themes/', 'wp-content/themes');

// 5. データベースダンプを追加
$archive->addFile('/tmp/database.sql', 'database.sql');

// 6. 保存
$archive->close();
```

## エラーハンドリング

すべての例外は `ExceptionInterface` を実装しています：

```php
use WPPack\Component\Wpress\Exception\ExceptionInterface;
use WPPack\Component\Wpress\Exception\ArchiveException;
use WPPack\Component\Wpress\Exception\EntryNotFoundException;
use WPPack\Component\Wpress\Exception\EncryptionException;
use WPPack\Component\Wpress\Exception\InvalidArgumentException;

try {
    $archive = new WpressArchive('backup.wpress', password: 'wrong-password');
    $entry = $archive->getEntry('database.sql');
    $content = $entry->getContents(); // ここで EncryptionException
} catch (EncryptionException $e) {
    echo "パスワードが正しくありません: " . $e->getMessage();
} catch (EntryNotFoundException $e) {
    echo "エントリが見つかりません: " . $e->getMessage();
} catch (ArchiveException $e) {
    echo "アーカイブエラー: " . $e->getMessage();
} catch (ExceptionInterface $e) {
    // すべての Wpress 例外をキャッチ
}
```

| 例外 | 発生条件 |
|------|---------|
| `ArchiveException` | ファイルが開けない、書き込み失敗、フォーマット不正 |
| `EntryNotFoundException` | `getEntry()` で指定パスが見つからない |
| `EncryptionException` | 復号失敗（パスワード不正）、暗号化失敗 |
| `InvalidArgumentException` | ファイル名やパスが制限を超えている |

## 設計上の特徴

### メモリ効率

典型的な WordPress サイトのアーカイブには数千〜数万ファイルが含まれます。共有ホスティング等の低メモリ環境でも動作するよう、以下の設計を採用しています：

| 操作 | 方針 |
|------|------|
| `getEntries()` | **ジェネレータ**: エントリを1つずつ yield。全インデックスをメモリに保持しない |
| `getEntry()` / `count()` | 遅延インデックス構築（ヘッダーのみ走査、コンテンツは読まない） |
| `extractTo()` | `stream_copy_to_stream()` でチャンクコピー |
| `addFile()` | 平文時は 8KB チャンクでストリーミング書き込み |
| `addDirectory()` | `RecursiveDirectoryIterator` でイテレータのまま走査 |
| 暗号化/圧縮 | 512KB チャンク単位で処理。チャンクごとに完結 |

### 書き込み最適化

| 操作 | 動作 |
|------|------|
| 追加のみ | EOF マーカー位置まで seek → 上書き（全体リライト不要） |
| 削除あり | `close()` 時に temp ファイルへコピー → `rename()` でアトミック置換 |
| 変更なし | `close()` はファイルハンドルを閉じるだけ |

## 主要クラス

| クラス | 説明 |
|-------|------|
| `WpressArchive` | `.wpress` アーカイブの読み書き・操作（メインファサード） |
| `WpressEntry` | アーカイブ内の個別エントリ（遅延コンテンツアクセス） |
| `Header` | 4377 bytes バイナリヘッダーの pack/unpack（`@internal`） |
| `ContentProcessor\ContentProcessorInterface` | 暗号化/圧縮の戦略インターフェース |
| `ContentProcessor\PlainContentProcessor` | パススルー（デフォルト） |
| `ContentProcessor\EncryptedContentProcessor` | AES-256-CBC 暗号化/復号 |
| `ContentProcessor\CompressedContentProcessor` | gzip/bzip2 圧縮/伸張 |
| `ContentProcessor\ChainContentProcessor` | 圧縮→暗号化の合成処理 |
| `Metadata\PackageMetadata` | `package.json` の型安全な値オブジェクト |
| `Metadata\MultisiteMetadata` | `multisite.json` の型安全な値オブジェクト |
| `Metadata\WordPressInfo` | WordPress 環境情報 |
| `Metadata\DatabaseInfo` | データベース情報 |
| `Metadata\PhpInfo` | PHP 環境情報 |
| `Metadata\PluginInfo` | AI1WM プラグインバージョン |
| `Metadata\ServerInfo` | .htaccess / web.config |
| `Metadata\ReplaceInfo` | URL 置換設定 |
| `Metadata\CompressionInfo` | 圧縮設定 |
| `Metadata\SiteInfo` | マルチサイトの個別サイト情報 |
| `Metadata\SiteWordPressInfo` | サイト別 WordPress 情報 |

## WpressArchive API リファレンス

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `__construct($path, $flags, $password)` | — | アーカイブを開く / 新規作成 |
| `getEntries()` | `Generator<WpressEntry>` | 全エントリをジェネレータで返す |
| `getEntry($path)` | `WpressEntry` | 指定パスのエントリを取得 |
| `count()` | `int` | エントリ数 |
| `addFile($sourcePath, $archivePath)` | `void` | ファイルを追加 |
| `addDirectory($sourceDir, $archivePrefix)` | `void` | ディレクトリを再帰的に追加 |
| `addFromString($archivePath, $content)` | `void` | 文字列からエントリを追加 |
| `deleteEntry($path)` | `void` | 指定エントリを削除マーク |
| `deleteEntries($pattern)` | `void` | パターンに一致するエントリを削除マーク |
| `extractTo($destination, $filter)` | `void` | エントリをディスクに展開 |
| `close()` | `void` | 変更を保存しファイルを閉じる |

## WpressEntry API リファレンス

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `getPath()` | `string` | フルパス（`prefix/name`） |
| `getName()` | `string` | ファイル名（ベース名のみ） |
| `getPrefix()` | `string` | ディレクトリパス |
| `getSize()` | `int` | コンテンツサイズ（暗号化/圧縮後） |
| `getMTime()` | `int` | 最終更新 Unix タイムスタンプ |
| `getContents()` | `string` | コンテンツを文字列として取得（自動復号・伸張） |
| `getStream()` | `resource` | コンテンツを `php://temp` ストリームとして取得 |

## 関連仕様書

- [.wpress ファイルフォーマット仕様](../../specifications/wpress-format.md) — バイナリ形式の詳細
- [All-in-One WP Migration バックアップ仕様](../../specifications/all-in-one-wp-migration/all-in-one-wp-migration.md) — package.json / DB ダンプ仕様
- [マルチサイトシナリオ別仕様](../../specifications/all-in-one-wp-migration/multisite-scenarios.md) — multisite.json / シナリオ別仕様

## 依存関係

### 必須
なし（PHP 標準関数のみ）

### オプション（暗号化/圧縮利用時）
- `ext-openssl` — AES-256-CBC 暗号化
- `ext-bz2` — bzip2 圧縮（gzip は標準で利用可能）
