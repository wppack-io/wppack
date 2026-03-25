# RedisCachePlugin

WordPress の Object Cache を Redis バックエンドで動かすプラグイン。`wppack/cache` の `object-cache.php` ドロップインのインストール管理と、DI コンテナへの `ObjectCache` / `CacheManager` サービス登録を担う。ElastiCache IAM 認証にも対応する。

## 概要

RedisCachePlugin は `wppack/cache` と `wppack/redis-cache` の薄い統合レイヤーです:

- **ドロップイン管理**: プラグイン有効化で `object-cache.php` を `wp-content/` にコピー、無効化で削除
- **環境変数ベースの設定**: `WPPACK_CACHE_DSN` で Redis 接続を構成
- **DI サービス登録**: `ObjectCache`、`CacheManager`、`RedisCacheConfiguration` をコンテナに登録
- **ElastiCache IAM 認証**: DSN クエリパラメータで自動有効化

## アーキテクチャ

### パッケージ構成

```
wppack/cache                ← Object Cache 基盤（ObjectCache, AdapterInterface, drop-in）
    ↑
wppack/redis-cache          ← Redis アダプタ（RedisAdapterFactory, RedisAdapter）
    ↑
wppack/elasticache-auth     ← ElastiCache IAM トークン生成
    ↑
wppack/redis-cache-plugin   ← WordPress 統合（ドロップイン管理, 設定, DI）
```

### レイヤー構成

```
src/Plugin/RedisCachePlugin/
├── redis-cache-plugin.php                        ← Bootstrap（Kernel::registerPlugin）
├── src/
│   ├── RedisCachePlugin.php                      ← PluginInterface 実装
│   ├── Configuration/
│   │   └── RedisCacheConfiguration.php           ← 設定 VO（WPPACK_CACHE_DSN）
│   └── DependencyInjection/
│       └── RedisCachePluginServiceProvider.php    ← サービス登録
└── tests/
```

### Object Cache フロー

```
┌─ WordPress 起動 ──────────────────────────────┐
│                                                │
│  wp-content/object-cache.php                   │
│    → Composer autoloader                       │
│    → WPPACK_CACHE_DSN から DSN を取得          │
│    → RedisAdapterFactory::create($dsn)         │
│      → RedisAdapter (ext-redis / Predis)       │
│    → new ObjectCache($adapter, $config)        │
│    → $GLOBALS['wp_object_cache'] = $objectCache│
│                                                │
└────────────────────────────────────────────────┘
            ↓ WordPress のキャッシュ API
┌─ キャッシュ操作 ──────────────────────────────┐
│                                                │
│  wp_cache_get/set/delete/flush()               │
│    → ObjectCache->get/set/delete/flush()       │
│      → RedisAdapter->get/set/delete/flush()    │
│        → Redis サーバー                        │
│                                                │
└────────────────────────────────────────────────┘
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/cache | Object Cache 基盤（ObjectCache, ObjectCacheConfig, drop-in） |
| wppack/redis-cache | Redis アダプタ（RedisAdapterFactory, RedisAdapter） |
| wppack/elasticache-auth | ElastiCache IAM 認証（ElastiCacheIamTokenGenerator） |
| wppack/dependency-injection | DI コンテナ |
| wppack/kernel | プラグインブートストラップ（PluginInterface） |
| wppack/hook | WordPress フック統合 |

## 名前空間

```
WpPack\Plugin\RedisCachePlugin\
```

## 設定

`wp-config.php` で `WPPACK_CACHE_DSN` を定義して Redis を有効にします。

```php
// wp-config.php

// 必須: Redis 接続 DSN
define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');

// オプション
define('WPPACK_CACHE_PREFIX', 'wp:');              // キープレフィックス（デフォルト: 'wp:'）
define('WPPACK_CACHE_MAX_TTL', 86400);             // 最大 TTL（秒）
define('WPPACK_CACHE_HASH_ALLOPTIONS', true);      // alloptions を Redis HASH で管理
define('WPPACK_CACHE_ASYNC_FLUSH', true);           // DEL の代わりに UNLINK を使用
define('WPPACK_CACHE_COMPRESSION', 'zstd');         // 圧縮: 'none', 'zstd', 'lz4', 'lzf'
```

### DSN フォーマット

| スキーム | 説明 |
|---------|------|
| `redis://` | Redis（非 TLS） |
| `rediss://` | Redis（TLS） |
| `valkey://` | Valkey（非 TLS） |
| `valkeys://` | Valkey（TLS） |

### ElastiCache IAM 認証

DSN クエリパラメータで IAM 認証を有効化します:

```php
define('WPPACK_CACHE_DSN', 'rediss://clustername.cache.amazonaws.com:6379?iam_auth=1&iam_region=ap-northeast-1&iam_user_id=my-user');
```

IAM 認証の仕組み:

1. `RedisAdapterFactory` が `iam_auth=1` を検出
2. `ElastiCacheIamTokenGenerator` を生成
3. `createProvider($host)` で credential_provider クロージャを作成
4. アダプタの `resolvePassword()` がトークンを動的に取得

- TLS 必須（`rediss://` / `valkeys://`）
- トークンは自動的にリフレッシュされる

## 主要クラス

### RedisCachePlugin

`PluginInterface` 実装。`Kernel::registerPlugin()` で登録される。

```php
namespace WpPack\Plugin\RedisCachePlugin;

final class RedisCachePlugin extends AbstractPlugin
{
    public function register(ContainerBuilder $builder): void;
    public function getCompilerPasses(): array;  // RegisterHookSubscribersPass
    public function onActivate(): void;          // object-cache.php をコピー
    public function onDeactivate(): void;        // object-cache.php を削除（WpPack 製のみ）
}
```

### Configuration\RedisCacheConfiguration

設定 VO。環境変数と PHP 定数の両方に対応。

```php
namespace WpPack\Plugin\RedisCachePlugin\Configuration;

final readonly class RedisCacheConfiguration
{
    public function __construct(
        public string $dsn,              // WPPACK_CACHE_DSN（必須）
        public string $prefix = 'wp:',   // WPPACK_CACHE_PREFIX
        public ?int $maxTtl = null,      // WPPACK_CACHE_MAX_TTL
        public bool $hashAlloptions = false,  // WPPACK_CACHE_HASH_ALLOPTIONS
        public bool $asyncFlush = false,      // WPPACK_CACHE_ASYNC_FLUSH
        public string $compression = 'none',  // WPPACK_CACHE_COMPRESSION
    );

    public static function fromEnvironment(): self;
}
```

### DependencyInjection\RedisCachePluginServiceProvider

DI サービスプロバイダ。以下のサービスを登録します:

| サービス | 説明 |
|---------|------|
| `RedisCacheConfiguration` | `fromEnvironment()` ファクトリで環境変数から設定を読み込み |
| `ObjectCache` | `$GLOBALS['wp_object_cache']` を返すファクトリ（ドロップインが初期化済み） |
| `CacheManager` | WordPress キャッシュ API ラッパー |

## ドロップイン管理

### 有効化時（onActivate）

1. `InstalledVersions::getInstallPath('wppack/cache')` でソースパスを検出
2. モノレポ環境では `dirname(__DIR__, 3) . '/Component/Cache'` にフォールバック
3. `drop-in/object-cache.php` を `WP_CONTENT_DIR/object-cache.php` にコピー

### 無効化時（onDeactivate）

1. `WP_CONTENT_DIR/object-cache.php` が存在するか確認
2. ファイル先頭 512 バイトに `WpPack Object Cache Drop-in` シグネチャがあるか確認
3. WpPack 製のドロップインのみ削除（他のプラグインのドロップインは保護）

## 圧縮

`WPPACK_CACHE_COMPRESSION` で Redis に格納するデータの圧縮アルゴリズムを選択できます:

| 値 | 説明 | 要件 |
|---|------|------|
| `none` | 圧縮なし（デフォルト） | — |
| `zstd` | Zstandard | `ext-zstd` または Redis 拡張の zstd サポート |
| `lz4` | LZ4 | Redis 拡張の lz4 サポート |
| `lzf` | LZF | `ext-lzf` |

## Hash 戦略（alloptions）

`WPPACK_CACHE_HASH_ALLOPTIONS=true` を設定すると、WordPress の `alloptions`（`wp_options` テーブルの `autoload=yes` 全レコード）を Redis の HASH データ型で管理します:

- **メリット**: オプション単位の更新が可能になり、メモリ効率と書き込みパフォーマンスが向上
- **要件**: Redis アダプタが `HashableAdapterInterface` を実装していること
