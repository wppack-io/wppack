# WordPress Filesystem API 仕様

## 1. 概要

WordPress の Filesystem API は、ファイルシステム操作を抽象化するレイヤーです。直接的な PHP ファイル関数の代わりに、`WP_Filesystem_Base` を基底クラスとする複数のトランスポート実装（Direct / FTP / FTP Sockets / SSH2）を通じてファイル操作を行います。

この API は主にプラグイン・テーマのインストール／アップグレード処理で使用されます。ファイル書き込み権限の問題を回避するため、必要に応じて FTP / SSH2 経由でファイル操作を行う設計です。

### 主要クラス

| クラス | ファイル | 説明 |
|---|---|---|
| `WP_Filesystem_Base` | `class-wp-filesystem-base.php` | 抽象基底クラス。全トランスポートが継承 |
| `WP_Filesystem_Direct` | `class-wp-filesystem-direct.php` | PHP 標準関数による直接操作 |
| `WP_Filesystem_FTPext` | `class-wp-filesystem-ftpext.php` | PHP FTP 拡張（`ext-ftp`）による操作 |
| `WP_Filesystem_ftpsockets` | `class-wp-filesystem-ftpsockets.php` | 純 PHP FTP ソケット実装 |
| `WP_Filesystem_SSH2` | `class-wp-filesystem-ssh2.php` | PHP SSH2 拡張（`ext-ssh2`）による SFTP 操作 |

### グローバル変数

| 変数 | 型 | 説明 |
|---|---|---|
| `$wp_filesystem` | `WP_Filesystem_Base` | 初期化済みファイルシステムインスタンス |

### 関連定数

| 定数 | デフォルト | 説明 |
|---|---|---|
| `FS_CHMOD_DIR` | `0755` | ディレクトリ作成時のパーミッション |
| `FS_CHMOD_FILE` | `0644` | ファイル作成時のパーミッション |
| `FTP_BASE` | `ABSPATH` | FTP ルートからの WordPress ベースパス |
| `FTP_CONTENT_DIR` | `WP_CONTENT_DIR` | FTP ルートからのコンテンツディレクトリ |
| `FTP_PLUGIN_DIR` | `WP_PLUGIN_DIR` | FTP ルートからのプラグインディレクトリ |
| `FTP_LANG_DIR` | `WP_LANG_DIR` | FTP ルートからの言語ディレクトリ |
| `FS_CONNECT_TIMEOUT` | `30` | 接続タイムアウト（秒） |
| `FS_TIMEOUT` | `30` | 操作タイムアウト（秒） |
| `FS_METHOD` | (自動選択) | 強制的に使用するトランスポート（`'direct'`, `'ssh2'`, `'ftpext'`, `'ftpsockets'`） |

## 2. データ構造

### WP_Filesystem_Base クラス

```php
abstract class WP_Filesystem_Base {
    public $verbose = false;     // 詳細ログ出力フラグ
    public $cache   = [];        // ファイル情報キャッシュ
    public $method  = '';        // トランスポート名 ('direct', 'ssh2', 'ftpext', 'ftpsockets')
    public $errors  = null;      // WP_Error インスタンス
    public $options = [];        // 接続オプション
}
```

### トランスポート別の接続情報

#### WP_Filesystem_Direct

接続情報不要。PHP プロセスの権限でファイル操作を行います。

#### WP_Filesystem_FTPext / WP_Filesystem_ftpsockets

| オプション | 型 | 説明 |
|---|---|---|
| `hostname` | `string` | FTP ホスト名 |
| `port` | `int` | FTP ポート（デフォルト: `21`） |
| `username` | `string` | FTP ユーザー名 |
| `password` | `string` | FTP パスワード |
| `ssl` | `bool` | FTPS（FTP over SSL）の使用 |

#### WP_Filesystem_SSH2

| オプション | 型 | 説明 |
|---|---|---|
| `hostname` | `string` | SSH ホスト名 |
| `port` | `int` | SSH ポート（デフォルト: `22`） |
| `username` | `string` | SSH ユーザー名 |
| `password` | `string` | SSH パスワード |
| `public_key` | `string` | 公開鍵ファイルパス |
| `private_key` | `string` | 秘密鍵ファイルパス |

## 3. API リファレンス

### 初期化 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `WP_Filesystem()` | `(array\|false $args = false, string\|false $context = false, bool $allow_relaxed_file_ownership = false): bool\|null` | ファイルシステムの初期化。`$wp_filesystem` グローバルを設定 |
| `request_filesystem_credentials()` | `(string $form_post, string $type = '', bool\|WP_Error $error = false, string $context = '', array $extra_fields = null, bool $allow_relaxed_file_ownership = false): bool\|array` | FTP/SSH 認証情報のリクエスト（フォーム表示） |
| `get_filesystem_method()` | `(array $args = [], string $context = '', bool $allow_relaxed_file_ownership = false): string` | 使用するトランスポートを決定 |

### WP_Filesystem_Base メソッド（共通インターフェース）

#### ファイル読み書き

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `get_contents()` | `(string $file): string\|false` | ファイルの内容を文字列で取得 |
| `get_contents_array()` | `(string $file): array\|false` | ファイルの内容を行配列で取得 |
| `put_contents()` | `(string $file, string $contents, int\|false $mode = false): bool` | ファイルに書き込み |
| `exists()` | `(string $file): bool` | ファイル/ディレクトリの存在チェック |
| `is_file()` | `(string $file): bool` | ファイルかどうか |
| `is_dir()` | `(string $path): bool` | ディレクトリかどうか |
| `is_readable()` | `(string $file): bool` | 読み取り可能か |
| `is_writable()` | `(string $file): bool` | 書き込み可能か |
| `size()` | `(string $file): int\|false` | ファイルサイズ（バイト） |
| `atime()` | `(string $file): int\|false` | 最終アクセス時刻（Unix タイムスタンプ） |
| `mtime()` | `(string $file): int\|false` | 最終更新時刻（Unix タイムスタンプ） |
| `touch()` | `(string $file, int $time = 0, int $atime = 0): bool` | タイムスタンプを更新 |

#### ファイル操作

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `copy()` | `(string $source, string $destination, bool $overwrite = false, int\|false $mode = false): bool` | ファイルをコピー |
| `move()` | `(string $source, string $destination, bool $overwrite = false): bool` | ファイルを移動 |
| `delete()` | `(string $file, bool $recursive = false, string\|false $type = false): bool` | ファイル/ディレクトリを削除 |
| `chmod()` | `(string $file, int\|false $mode = false, bool $recursive = false): bool` | パーミッションを変更 |
| `chown()` | `(string $file, string\|int $owner, bool $recursive = false): bool` | オーナーを変更 |
| `chgrp()` | `(string $file, string\|int $group, bool $recursive = false): bool` | グループを変更 |
| `owner()` | `(string $file): string\|false` | オーナーを取得 |
| `group()` | `(string $file): string\|false` | グループを取得 |
| `getchmod()` | `(string $file): string` | パーミッション文字列を取得 |
| `getnumchmodfromh()` | `(string $mode): int` | 人間可読パーミッションを数値に変換 |

#### ディレクトリ操作

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `mkdir()` | `(string $path, int\|false $chmod = false, string\|int\|false $chown = false, string\|int\|false $chgrp = false): bool` | ディレクトリを作成 |
| `rmdir()` | `(string $path, bool $recursive = false): bool` | ディレクトリを削除 |
| `dirlist()` | `(string $path, bool $include_hidden = true, bool $recursive = false): array\|false` | ディレクトリ内容を一覧 |

#### パス操作

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `abspath()` | `(): string` | WordPress ルートの絶対パス |
| `wp_content_dir()` | `(): string` | `wp-content` ディレクトリのパス |
| `wp_plugins_dir()` | `(): string` | プラグインディレクトリのパス |
| `wp_themes_dir()` | `(string\|false $theme = false): string` | テーマディレクトリのパス |
| `wp_lang_dir()` | `(): string` | 言語ファイルディレクトリのパス |
| `find_folder()` | `(string $folder): string\|false` | ローカルパスに対応するリモートパスを検索 |
| `search_for_folder()` | `(string $folder, string $base = '.', bool $loop = false): string\|false` | フォルダをリモートファイルシステム上で検索 |

### `dirlist()` の戻り値構造

```php
[
    'filename' => [
        'name'          => string,   // ファイル名
        'perms'         => string,   // パーミッション文字列 ('rw-r--r--')
        'permsn'        => string,   // 数値パーミッション ('0644')
        'number'        => false,    // ハードリンク数（FTP のみ）
        'owner'         => string,   // オーナー名
        'group'         => string,   // グループ名
        'size'          => int,      // バイト数
        'lastmod'       => string,   // 最終更新日
        'lastmodunix'   => int,      // 最終更新 Unix タイムスタンプ
        'time'          => string,   // 最終更新時刻
        'type'          => string,   // 'f' (file) or 'd' (directory)
        'files'         => array,    // type='d' かつ recursive=true の場合、子要素
    ],
    ...
]
```

### `file.php` のユーティリティ関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_tempnam()` | `(string $filename = '', string $dir = ''): string` | 一時ファイルを作成 |
| `wp_mkdir_p()` | `(string $target): bool` | ディレクトリを再帰的に作成（`mkdir -p` 相当） |
| `wp_is_writable()` | `(string $path): bool` | 書き込み可能か（Windows 対応） |
| `get_temp_dir()` | `(): string` | 一時ディレクトリパスを取得 |
| `get_home_path()` | `(): string` | WordPress インストールのルートパス |
| `unzip_file()` | `(string $file, string $to): true\|WP_Error` | ZIP ファイルを展開 |
| `copy_dir()` | `(string $from, string $to, string[] $skip_list = []): true\|WP_Error` | ディレクトリを再帰的にコピー |
| `wp_handle_upload()` | `(array &$file, array\|false $overrides = false, string $time = null): array` | ファイルアップロード処理 |
| `wp_handle_sideload()` | `(array &$file, array\|false $overrides = false, string $time = null): array` | サイドロード（非フォームアップロード）処理 |
| `download_url()` | `(string $url, int $timeout = 300, bool $signature_verification = false): string\|WP_Error` | URL からファイルをダウンロードし一時ファイルに保存 |
| `verify_file_signature()` | `(string $filename, string\|array $signatures, string $filename_for_errors = false): bool\|WP_Error` | ファイルの Ed25519 署名を検証 |
| `wp_zip_file_is_valid()` | `(string $file): bool` | ZIP ファイルの妥当性チェック |

## 4. 実行フロー

### `WP_Filesystem()` の初期化フロー

```
WP_Filesystem($args, $context, $allow_relaxed_file_ownership)
│
├── require_once: class-wp-filesystem-base.php
│
├── get_filesystem_method($args, $context, $allow_relaxed_file_ownership)
│   │
│   ├── 【フィルター】 filesystem_method_file ($wp_dir . '/temp-write-test-xxx')
│   │   └── 書き込みテスト用の一時ファイルパスを変更可能
│   │
│   ├── FS_METHOD 定数が定義されている場合 → その値を使用
│   │
│   ├── 書き込みテスト
│   │   ├── $context ディレクトリに一時ファイルを作成
│   │   ├── ファイルオーナーとディレクトリオーナーを比較
│   │   ├── 一致 → 'direct' を選択
│   │   ├── allow_relaxed_file_ownership かつ書き込み可能 → 'direct'
│   │   └── 不一致 → FTP/SSH を検討
│   │
│   ├── FTP/SSH の利用可否チェック
│   │   ├── ext-ssh2 が利用可能 → 'ssh2'
│   │   ├── ext-ftp が利用可能 → 'ftpext'
│   │   └── フォールバック → 'ftpsockets'
│   │
│   └── 【フィルター】 filesystem_method ($method, $args, $context, $allow_relaxed_file_ownership)
│       └── トランスポート方式を上書き可能
│
├── require_once: class-wp-filesystem-{method}.php
│
├── 【フィルター】 filesystem_method_file ($method, $args, $context, $allow_relaxed_file_ownership)
│
├── $wp_filesystem = new WP_Filesystem_{Method}($args)
│
├── $wp_filesystem->connect()
│   ├── Direct: 何もしない（常に true）
│   ├── FTPext: ftp_connect() + ftp_login()
│   ├── ftpsockets: ソケット接続 + ログイン
│   └── SSH2: ssh2_connect() + 認証 + ssh2_sftp()
│
├── 接続失敗時
│   └── return false + $wp_filesystem->errors に WP_Error
│
└── return true
```

### トランスポート選択のフロー

```
get_filesystem_method()
│
├── FS_METHOD 定数が定義済み?
│   ├── YES → return FS_METHOD の値
│   └── NO ↓
│
├── $context ディレクトリに一時ファイル作成テスト
│   │
│   ├── テンポラリファイルを作成
│   ├── ファイルのオーナー UID を取得
│   ├── $context ディレクトリのオーナー UID を取得
│   │
│   ├── UID が一致?
│   │   └── YES → 'direct'
│   │
│   ├── $allow_relaxed_file_ownership && ディレクトリ書き込み可能?
│   │   └── YES → 'direct'
│   │
│   └── テンポラリファイル削除
│
├── FTP/SSH 認証情報が提供されている?
│   ├── SSH2 拡張が利用可能 → 'ssh2'
│   ├── FTP 拡張が利用可能 → 'ftpext'
│   └── フォールバック → 'ftpsockets'
│
└── 【フィルター】 filesystem_method で最終決定
```

### `request_filesystem_credentials()` のフロー

```
request_filesystem_credentials($form_post, $type, $error, $context, $extra_fields)
│
├── 【フィルター】 request_filesystem_credentials ($credentials, ...)
│   └── false 以外を返すと認証情報フォームをスキップ
│
├── $method = get_filesystem_method($args, $context)
│
├── $method === 'direct' ?
│   └── YES → return true（認証情報不要）
│
├── 定数（FTP_HOST 等）/ $_POST から認証情報を取得
│
├── 認証情報が揃っている?
│   └── YES → return $credentials 配列
│
├── 認証情報が不足
│   ├── FTP/SSH 認証フォームを HTML 出力
│   └── return false
│
└── ※ 呼び出し元は false 時に処理を中断する
```

### `unzip_file()` のフロー

```
unzip_file($file, $to)
│
├── 【フィルター】 pre_unzip_file (null, $file, $to)
│   └── null 以外を返すとショートサーキット
│
├── ZipArchive 拡張が利用可能?
│   ├── YES → _unzip_file_ziparchive($file, $to, $needed_dirs)
│   │   └── ZipArchive クラスで展開
│   └── NO → _unzip_file_pclzip($file, $to, $needed_dirs)
│       └── PclZip ライブラリで展開
│
├── ディレクトリの作成（$needed_dirs をソートして再帰作成）
│
├── 各ファイルの展開・書き込み
│   └── $wp_filesystem->put_contents($to . $file, $contents, FS_CHMOD_FILE)
│
└── 【フィルター】 unzip_file_use_ziparchive ($use, $file)
    └── ZipArchive の使用を強制/無効化
```

## 5. フック一覧

### フィルター

| フック名 | パラメータ | 説明 |
|---|---|---|
| `filesystem_method` | `(string $method, array $args, string $context, bool $allow_relaxed_file_ownership)` | ファイルシステムトランスポートの選択を上書き |
| `filesystem_method_file` | `(string $path)` | 書き込みテスト用一時ファイルパスの変更 |
| `request_filesystem_credentials` | `(mixed $credentials, string $form_post, string $type, bool\|WP_Error $error, string $context, array $extra_fields, bool $allow_relaxed_file_ownership)` | 認証情報リクエストのショートサーキット |
| `pre_unzip_file` | `(null $result, string $file, string $to)` | ZIP 展開前のショートサーキット |
| `unzip_file_use_ziparchive` | `(bool $use, string $file)` | ZipArchive の使用可否 |
| `upload_dir` | `(array $uploads)` | アップロードディレクトリ情報の配列を変更 |
| `wp_handle_upload_prefilter` | `(array $file)` | アップロード前のファイル情報フィルタリング |
| `wp_handle_upload` | `(array $upload, string $context)` | アップロード後の結果フィルタリング |
| `wp_handle_sideload_prefilter` | `(array $file)` | サイドロード前のファイル情報フィルタリング |
| `wp_handle_sideload` | `(array $sideload, string $context)` | サイドロード後の結果フィルタリング |
| `wp_upload_bits` | `(array $upload_bits)` | `wp_upload_bits()` の引数フィルタリング |
| `wp_unique_filename` | `(string $filename, string $ext, string $dir, callable\|null $unique_filename_callback)` | ユニークファイル名の生成 |
| `wp_check_filetype_and_ext` | `(array $data, string $file, string $filename, string[] $mimes, string $real_mime)` | ファイルタイプ検証結果のフィルタリング |

### アクション

| フック名 | パラメータ | 説明 |
|---|---|---|
| `wp_handle_upload_overrides` | `(array $overrides, array $file)` | アップロードオーバーライド設定 |

## 6. セキュリティ上の注意

### ファイルオーナーの検証

`get_filesystem_method()` は、PHP プロセスのオーナーとファイルシステムのオーナーを比較します。共有ホスティング環境では、PHP が `apache` / `www-data` ユーザーで実行される場合、ファイルオーナー（FTP ユーザー）と一致しないため、FTP/SSH トランスポートが選択されます。

### `reject_unsafe_urls` との関係

`download_url()` は内部的に `wp_safe_remote_get()` を使用し、SSRF 対策が適用されます。ローカルファイルの読み込みやプライベート IP へのアクセスはブロックされます。

### 署名検証

WordPress 5.2 以降、`verify_file_signature()` で Ed25519 署名検証が利用可能です。コアの自動更新パッケージに対する署名検証に使用されます。
