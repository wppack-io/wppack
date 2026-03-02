# Memcached Cache Bridge

**パッケージ:** `wppack/memcached-cache`
**名前空間:** `WpPack\Component\Cache\Bridge\Memcached\`

Memcached サーバーを Object Cache バックエンドとして利用するための Bridge パッケージです。

## 概要

Memcached は分散メモリキャッシュシステムで、複数のウェブサーバー間でキャッシュを共有できます。シンプルな Key-Value ストアとして高速に動作し、WordPress のオブジェクトキャッシュバックエンドとして広く利用されています。

## 前提条件

- PHP 8.2 以上
- `ext-memcached`（PHP Memcached 拡張）
- Memcached サーバー

### ext-memcached のインストール

```bash
# Ubuntu / Debian
sudo apt-get install php-memcached

# macOS (Homebrew)
pecl install memcached
```

## インストール

```bash
composer require wppack/memcached-cache
```

## 設定方法

### wp-config.php

```php
// Standalone
define('WPPACK_CACHE_DSN', 'memcached://127.0.0.1:11211');

// プレフィックス（オプション、デフォルト 'wp:'）
define('WPPACK_CACHE_PREFIX', 'wp:');

// オプション配列（オプション）
define('WPPACK_CACHE_OPTIONS', [
    'timeout' => 2000,      // 接続タイムアウト (ms)
    'persistent_id' => 'wp', // 永続接続 ID
]);
```

### DSN 形式

Symfony Cache 互換の DSN 形式をサポートします。

```php
// Standalone
'memcached://127.0.0.1:11211'

// 複数サーバー
'memcached:?host[10.0.0.1:11211]&host[10.0.0.2:11211]'

// SASL 認証
'memcached://user:password@127.0.0.1:11211'

// Unix ソケット
'memcached:///var/run/memcached.sock'

// オプション付き
'memcached://127.0.0.1:11211?weight=100'
```

## 接続オプション

| パラメータ | デフォルト | 説明 |
|-----------|-----------|------|
| `host` | `127.0.0.1` | サーバーホスト |
| `port` | `11211` | サーバーポート |
| `weight` | `0` | サーバーウェイト（分散時の重み付け） |
| `persistent_id` | — | 永続接続 ID |
| `username` | — | SASL 認証ユーザー名 |
| `password` | — | SASL 認証パスワード |
| `timeout` | — | 接続タイムアウト (ms) |
| `retry_timeout` | — | リトライタイムアウト (秒) |
| `tcp_nodelay` | `true` | Nagle アルゴリズム無効化 |
| `no_block` | `true` | 非同期 I/O |
| `binary_protocol` | `true` | バイナリプロトコル使用 |
| `libketama_compatible` | `true` | 一貫性ハッシュ |

### デフォルト Memcached オプション

以下のオプションがデフォルトで設定されます（Symfony Cache と同じ）:

```php
OPT_BINARY_PROTOCOL     => true   // バイナリプロトコル有効
OPT_NO_BLOCK            => true   // 非同期 I/O
OPT_TCP_NODELAY         => true   // Nagle 無効化
OPT_LIBKETAMA_COMPATIBLE => true  // 一貫性ハッシュ
OPT_SERIALIZER          => SERIALIZER_NONE  // WpPack は生文字列を扱う
```

## マルチサーバー構成

```php
// DSN で複数サーバーを指定
define('WPPACK_CACHE_DSN', 'memcached:?host[10.0.0.1:11211]&host[10.0.0.2:11211]&host[10.0.0.3:11211]');

// weight を使った重み付け分散
define('WPPACK_CACHE_DSN', 'memcached:?host[10.0.0.1:11211]&host[10.0.0.2:11211]');
define('WPPACK_CACHE_OPTIONS', ['weight' => 100]);
```

`libketama_compatible` がデフォルトで有効なため、サーバーの追加・削除時にキーの再分散が最小化されます。

## SASL 認証

Amazon ElastiCache (Memcached) や他のマネージドサービスで SASL 認証が必要な場合:

```php
define('WPPACK_CACHE_DSN', 'memcached://user:password@my-cluster.xxxxx.cfg.apne1.cache.amazonaws.com:11211');
```

> [!NOTE]
> SASL 認証にはバイナリプロトコル（`OPT_BINARY_PROTOCOL`）が必要です。デフォルトで有効になっています。

## マルチサイト対応

マルチサイト環境では `WPPACK_CACHE_PREFIX` でサイトごとのプレフィックスを設定:

```php
define('WPPACK_CACHE_PREFIX', 'site1:');
```

## flush の制限事項

Memcached には prefix-based deletion のネイティブサポートがありません。`flush($prefix)` は以下のフォールバック戦略を使用します:

1. `getAllKeys()` でキー一覧を取得し、プレフィックスでフィルタして `deleteMulti()` で削除
2. `getAllKeys()` が使えない場合（一部の Memcached 設定やクラスタ環境）は `flush()` にフォールバック

> [!WARNING]
> prefix flush は完全性を保証しません。高負荷環境では一部のキーが削除されない可能性があります。完全なプレフィックス削除が必要な場合は Redis の使用を検討してください。

## パフォーマンス考慮事項

- **バイナリプロトコル**: テキストプロトコルより効率的。デフォルトで有効
- **非同期 I/O（`OPT_NO_BLOCK`）**: 書き込み操作のレイテンシを低減
- **一貫性ハッシュ（`OPT_LIBKETAMA_COMPATIBLE`）**: サーバー変更時のキーの再分散を最小化
- **永続接続（`persistent_id`）**: 接続のオーバーヘッドを削減

## Redis との使い分け

| 項目 | Memcached | Redis |
|------|-----------|-------|
| データ型 | 文字列のみ | 文字列、リスト、セット、ハッシュ等 |
| 永続化 | なし（メモリのみ） | RDB / AOF |
| Pub/Sub | なし | あり |
| クラスター | クライアント側分散 | Redis Cluster（サーバー側） |
| メモリ効率 | 高い（マルチスレッド） | 良い（シングルスレッド） |
| プレフィックス削除 | 制限あり | SCAN ベースで可能 |
| AWS マネージドサービス | Amazon ElastiCache (Memcached) | Amazon ElastiCache (Redis), Amazon MemoryDB |

**Memcached を選ぶケース:**
- シンプルな Key-Value キャッシュのみ必要
- マルチスレッドによる高スループットが必要
- 既存の Memcached インフラがある

**Redis を選ぶケース:**
- 永続化が必要
- プレフィックス削除の信頼性が重要
- Pub/Sub やその他の高度なデータ構造が必要

## トラブルシューティング

### ext-memcached がインストールされていない

```
PHP Fatal error: Class 'Memcached' not found
```

`ext-memcached` をインストールしてください。`ext-memcache`（末尾の d なし）は別の拡張です。

### SASL 認証が失敗する

```
Memcached: AUTHENTICATION_FAILURE
```

- バイナリプロトコルが有効か確認（デフォルトで有効）
- ユーザー名・パスワードが正しいか確認
- `ext-memcached` が SASL サポート付きでビルドされているか確認（`php -i | grep memcached` で確認）

### 接続タイムアウト

```php
// タイムアウトを延長
define('WPPACK_CACHE_OPTIONS', ['timeout' => 5000]);
```

### サーバーが利用不可

ドロップインはアダプタが `null` の場合ランタイム配列のみで動作するため、Memcached サーバーが停止してもサイトはダウンしません（グレースフルデグラデーション）。
