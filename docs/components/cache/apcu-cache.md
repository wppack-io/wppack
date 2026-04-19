# APCu Cache Bridge

**パッケージ:** `wppack/apcu-cache`
**名前空間:** `WPPack\Component\Cache\Bridge\Apcu\`

APCu（Alternative PHP Cache user-data cache）を Object Cache バックエンドとして利用するための Bridge パッケージです。

## 概要

APCu は PHP プロセスの共有メモリ上にデータをキャッシュする拡張です。外部サーバーが不要で、最も低レイテンシなキャッシュバックエンドとして動作します。単一サーバー環境や開発環境に最適です。

## 前提条件

- PHP 8.2 以上
- `ext-apcu`
- `apc.enabled=1`（php.ini）

### ext-apcu のインストール

```bash
# Ubuntu / Debian
sudo apt-get install php-apcu

# macOS (Homebrew)
pecl install apcu
```

### php.ini 設定

```ini
; APCu を有効化
apc.enabled=1

; 共有メモリサイズ（デフォルト 32M）
apc.shm_size=64M

; CLI で使用する場合（WP-CLI 等）
apc.enable_cli=1
```

## インストール

```bash
composer require wppack/apcu-cache
```

## 設定方法

### wp-config.php

```php
define('CACHE_DSN', 'apcu://');

// プレフィックス（オプション、デフォルト 'wp:'）
define('WPPACK_CACHE_PREFIX', 'wp:');
```

### DSN 形式

APCu はローカルメモリのためホスト指定は不要です:

```php
'apcu://'
```

> [!NOTE]
> Symfony は APCu に DSN を使いませんが、WPPack は `CACHE_DSN` で統一的にバックエンドを選択するため `apcu://` スキームを採用しています。

## CLI での利用

APCu は Web リクエストと CLI で異なるキャッシュストアを持ちます。WP-CLI でキャッシュにアクセスするには `apc.enable_cli=1` が必要です。

```ini
; php.ini
apc.enable_cli=1
```

> [!WARNING]
> CLI モードでの APCu はプロセスごとにメモリが分離されるため、Web リクエストのキャッシュにはアクセスできません。CLI では主にテストやデバッグ目的で使用します。

## マルチサイト対応

マルチサイト環境では `WPPACK_CACHE_PREFIX` でサイトごとのプレフィックスを設定:

```php
define('WPPACK_CACHE_PREFIX', 'site1:');
```

## flush の仕組み

### プレフィックス付き flush

`APCUIterator` を使用して効率的にプレフィックスマッチしたキーを削除します（Symfony Cache と同じアプローチ）:

```php
// 内部実装
apcu_delete(new \APCUIterator('/^prefix:/'));
```

`APCUIterator` が利用できない場合は `apcu_clear_cache()` にフォールバックします。

### 全体 flush

```php
apcu_clear_cache()
```

## メモリ管理

### shm_size（共有メモリサイズ）

```ini
; デフォルトは 32M。WordPress 環境では 64M〜128M を推奨
apc.shm_size=128M
```

### フラグメンテーション

APCu は長期間運用するとメモリフラグメンテーションが発生する可能性があります。以下で確認できます:

```php
$info = apcu_sma_info();
// 'num_seg', 'seg_size', 'avail_mem' を確認
```

### GC（ガベージコレクション）

APCu の TTL 付きエントリは、メモリが不足した際に GC が実行されて期限切れエントリが削除されます。十分なメモリサイズを確保することが重要です。

## 制限事項

- **ローカルのみ**: APCu はプロセスの共有メモリに保存するため、サーバー間でキャッシュを共有できません
- **CLI モード**: Web リクエストとは別のキャッシュストアが使用されます
- **永続化なし**: PHP プロセスの再起動でキャッシュは失われます
- **マルチプロセス**: 同一サーバー上の PHP-FPM ワーカー間ではキャッシュが共有されます

## ユースケース

**APCu が適しているケース:**
- 単一サーバー環境（ロードバランサーなし）
- 開発・ステージング環境
- Redis / Memcached を導入できない低コスト環境
- 外部依存を最小限にしたい場合
- 最低レイテンシが求められるケース

**他のバックエンドが適しているケース:**
- 複数サーバー構成 → Redis / Memcached
- 永続化が必要 → Redis / DynamoDB
- 高可用性が必要 → Redis Cluster / DynamoDB

## トラブルシューティング

### APCu が有効にならない

```
Warning: apcu_store(): Unable to allocate memory
```

`apc.shm_size` を増やしてください:

```ini
apc.shm_size=128M
```

### CLI でキャッシュが使えない

```
APCu is not available (ext-apcu required with apc.enable_cli=1).
```

php.ini に以下を追加:

```ini
apc.enable_cli=1
```

### キャッシュヒット率が低い

APCu の状態を確認:

```php
$info = apcu_cache_info();
echo 'Hits: ' . $info['num_hits'] . PHP_EOL;
echo 'Misses: ' . $info['num_misses'] . PHP_EOL;
echo 'Entries: ' . $info['num_entries'] . PHP_EOL;
```

メモリ不足で頻繁に GC が発生している場合は `shm_size` を増やしてください。

### サーバーが利用不可

ドロップインはアダプタが `null` の場合ランタイム配列のみで動作するため、APCu が無効でもサイトはダウンしません（グレースフルデグラデーション）。
