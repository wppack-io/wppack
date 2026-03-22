# Site コンポーネント

**Package:** `wppack/site`
**Namespace:** `WpPack\Component\Site\`
**Layer:** Infrastructure

WordPress マルチサイトの状態照会・ブログ切替・サイト検索をオブジェクト指向で提供するコンポーネント。シングルサイト環境でもグレースフルにフォールバックする。

## インストール

```bash
composer require wppack/site
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 状態の直接参照
$blogId = get_current_blog_id();
$isMultisite = is_multisite();

// 手動でのブログ切替 — restore 忘れや例外時のリスク
switch_to_blog(2);
$name = get_option('blogname');
restore_current_blog();

// サイト検索
$sites = get_sites();
$site = get_blog_details(2);
$blogId = get_blog_id_from_url('example.com');
```

### After（WpPack）

```php
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\Site\BlogSwitcherInterface;
use WpPack\Component\Site\SiteRepositoryInterface;

public function __construct(
    private readonly BlogContextInterface $blogContext,
    private readonly BlogSwitcherInterface $blogSwitcher,
    private readonly SiteRepositoryInterface $siteRepository,
) {}

// 状態照会 — インターフェース経由
$blogId = $this->blogContext->getCurrentBlogId();

// 安全なブログ切替 — try/finally 自動復帰
$name = $this->blogSwitcher->runInBlog(2, fn () => get_option('blogname'));

// サイト検索 — null 安全な戻り値
$site = $this->siteRepository->find(2);
```

## BlogContext

現在のマルチサイト状態を読み取り専用で提供する。

### メソッド一覧

| メソッド | 説明 | WordPress 関数 |
|---------|------|----------------|
| `getCurrentBlogId(): int` | 現在のブログ ID を取得 | `get_current_blog_id()` |
| `isMultisite(): bool` | マルチサイト環境かどうか | `is_multisite()` |
| `isSwitched(): bool` | 別ブログに切替中かどうか | `ms_is_switched()` |

### 使用例

```php
use WpPack\Component\Site\BlogContext;

$context = new BlogContext();

$blogId = $context->getCurrentBlogId();   // 1
$context->isMultisite();                  // true / false
$context->isSwitched();                   // false（切替中でなければ）
```

> [!NOTE]
> `isSwitched()` は `ms_is_switched()` が存在しない環境（シングルサイト）では常に `false` を返す。

## BlogSwitcher

コールバックベースでブログコンテキストを安全に切り替える。`try/finally` により、例外発生時も確実に元のブログに復帰する。

### メソッド一覧

| メソッド | 説明 |
|---------|------|
| `switchToBlog(int $blogId): void` | ブログコンテキストを切り替える |
| `restoreCurrentBlog(): void` | 直前のブログコンテキストに復帰する |
| `runInBlog(int $blogId, callable $callback): mixed` | 常にブログを切り替えてコールバックを実行 |
| `runInBlogIfNeeded(int $blogId, callable $callback): mixed` | 対象ブログが現在のブログと異なる場合のみ切り替え |

### `runInBlog` vs `runInBlogIfNeeded`

| | `runInBlog` | `runInBlogIfNeeded` |
|---|---|---|
| 現在のブログ = 対象ブログ | 切り替え**する** | 切り替え**しない** |
| 現在のブログ ≠ 対象ブログ | 切り替える | 切り替える |
| 用途 | 常にクリーンなコンテキストが必要な場合 | パフォーマンス重視（不要な切替を回避） |

`runInBlogIfNeeded` は内部で `BlogContextInterface::getCurrentBlogId()` を呼び出し、一致すればコールバックを直接実行する。非同期メッセージ処理のように対象ブログが動的に変わるケースで有用。

### 使用例

基本的な使い方:

```php
use WpPack\Component\Site\BlogSwitcher;

$switcher = new BlogSwitcher();

// ブログ 2 のオプションを取得
$name = $switcher->runInBlog(2, fn () => get_option('blogname'));

// 現在のブログが 2 ならスキップ、異なれば切り替え
$name = $switcher->runInBlogIfNeeded(2, fn () => get_option('blogname'));
```

例外時の安全性:

```php
// 例外が発生しても restore_current_blog() が自動で呼ばれる
try {
    $switcher->runInBlog(3, function () {
        // 何らかの処理...
        throw new \RuntimeException('error');
    });
} catch (\RuntimeException) {
    // ここでは元のブログに復帰済み
}
```

直接 switch/restore:

```php
// コールバックに収まらないユースケース向け
// 必ず try/finally で restoreCurrentBlog() を呼ぶこと
$switcher->switchToBlog(2);
try {
    $name = get_option('blogname');
    $url = get_option('siteurl');
    // 複数のメソッド呼び出しにまたがる処理...
} finally {
    $switcher->restoreCurrentBlog();
}
```

> [!WARNING]
> `switchToBlog()` / `restoreCurrentBlog()` を直接使う場合は、必ず `try/finally` で `restoreCurrentBlog()` の呼び出しを保証してください。restore 忘れはグローバル状態の汚染を引き起こします。コールバックで完結する場合は `runInBlog()` / `runInBlogIfNeeded()` を推奨します。

シングルサイト環境:

```php
// switch_to_blog() が存在しない場合、コールバックをそのまま実行
$result = $switcher->runInBlog(1, fn () => 'ok');
// => 'ok'
```

## SiteRepository

マルチサイト環境のサイト情報を照会する。戻り値は null 安全。

### メソッド一覧

| メソッド | 説明 | WordPress 関数 |
|---------|------|----------------|
| `findAll(array $args = []): array` | 全サイトを取得（`get_sites()` 引数対応） | `get_sites()` |
| `find(int $blogId): ?WP_Site` | ブログ ID でサイトを取得 | `get_blog_details()` |
| `findByUrl(string $domain, string $path = '/'): ?WP_Site` | ドメイン + パスからサイトを取得 | `get_blog_id_from_url()` + `get_blog_details()` |
| `findBySlug(string $slug): ?WP_Site` | スラッグからサイトを取得 | `get_id_from_blogname()` + `get_blog_details()` |
| `getAllDomains(): array` | 全サイトのユニークなドメイン一覧 | `get_sites()` + 重複排除 |
| `getMeta(int $blogId, string $key, bool $single): mixed` | サイトメタを取得 | `get_site_meta()` |
| `addMeta(int $blogId, string $key, mixed $value, bool $unique): int\|false` | サイトメタを追加 | `add_site_meta()` |
| `updateMeta(int $blogId, string $key, mixed $value, mixed $previousValue): int\|bool` | サイトメタを更新 | `update_site_meta()` |
| `deleteMeta(int $blogId, string $key, mixed $value): bool` | サイトメタを削除 | `delete_site_meta()` |

### 使用例

```php
use WpPack\Component\Site\SiteRepository;

$repository = new SiteRepository();

// 全サイト取得
$sites = $repository->findAll();

// 条件付き取得
$sites = $repository->findAll(['public' => 1, 'number' => 10]);

// ID で取得
$site = $repository->find(2);
// => WP_Site | null

// URL からサイトを取得
$site = $repository->findByUrl('example.com', '/blog/');
// => WP_Site | null

// スラッグからサイトを取得
$site = $repository->findBySlug('myblog');
// => WP_Site | null

// 全ドメイン一覧
$domains = $repository->getAllDomains();
// => ['example.com', 'sub.example.com']

// メタデータ操作
$repository->addMeta($blogId, 'custom_key', 'value');
$value = $repository->getMeta($blogId, 'custom_key', single: true);
$repository->updateMeta($blogId, 'custom_key', 'new_value');
$repository->deleteMeta($blogId, 'custom_key');
```

## DI パターン

Site コンポーネントのクラスは 2 つの DI パターンで利用される。

### 1. スタンドアロン（デフォルト値パターン）

コンストラクタ引数にデフォルト値を指定し、DI コンテナなしでも動作する。外部コンポーネントのほとんどがこのパターンを採用している。

```php
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;

final readonly class AddMultisiteStampMiddleware
{
    public function __construct(
        private BlogContextInterface $blogContext = new BlogContext(),
    ) {}
}
```

テスト時にはモックを注入できる:

```php
$mock = $this->createMock(BlogContextInterface::class);
$mock->method('getCurrentBlogId')->willReturn(3);

$middleware = new AddMultisiteStampMiddleware($mock);
```

### 2. DI コンテナ管理（必須引数パターン）

Plugin パッケージなど、DI コンテナが常に利用可能な環境ではインターフェースを必須引数にする。

```php
use WpPack\Component\Site\BlogSwitcherInterface;

final readonly class GenerateThumbnailsHandler
{
    public function __construct(
        private BlogSwitcherInterface $blogSwitcher,
        // ... 他の依存
    ) {}
}
```

### 使い分け

| パターン | 使用場面 | 例 |
|---------|---------|---|
| デフォルト値 | Component パッケージ（DI コンテナなしでも動作すべき） | Messenger, Security, Scheduler |
| 必須引数 | Plugin パッケージ（DI コンテナ前提） | S3StoragePlugin |

## 利用コンポーネント

Site コンポーネントは複数のパッケージから利用されている。

### BlogContextInterface の利用

| コンポーネント | クラス | 用途 |
|---------------|-------|------|
| Messenger | `AddMultisiteStampMiddleware` | メッセージに現在のブログ ID をスタンプ |
| Security | `CookieAuthenticator` | 認証トークンにブログ ID を関連付け |
| Scheduler | `MultisiteScheduleGroupResolver` | ブログごとの EventBridge グループ名を解決 |
| Media | `UploadDirSubscriber` | マルチサイトのアップロードパス構造を判定 |
| Security/OAuth | `CrossSiteRedirector` | クロスサイト OAuth リダイレクトの判定 |
| Security/SAML | `CrossSiteRedirector` | クロスサイト SAML リダイレクトの判定 |

### BlogSwitcherInterface の利用

| コンポーネント | クラス | 用途 |
|---------------|-------|------|
| S3StoragePlugin | `GenerateThumbnailsHandler` | 正しいブログコンテキストでサムネイル生成 |
| S3StoragePlugin | `AttachmentRegistrar` | 正しいブログコンテキストで添付ファイル登録 |
| SqsMessenger | `SqsEventHandler` | Lambda でメッセージを正しいブログコンテキストで処理 |

### SiteRepositoryInterface の利用

| コンポーネント | クラス | 用途 |
|---------------|-------|------|
| Security/OAuth | `CrossSiteRedirector` | URL からブログ ID を解決、許可ドメイン判定 |
| Security/SAML | `CrossSiteRedirector` | URL からブログ ID を解決、許可ドメイン判定 |

## 主要クラス

| インターフェース | 実装 | 説明 |
|----------------|------|------|
| `BlogContextInterface` | `BlogContext` | マルチサイト状態の読み取り専用クエリ |
| `BlogSwitcherInterface` | `BlogSwitcher` | ブログコンテキスト切替（直接 / コールバック） |
| `SiteRepositoryInterface` | `SiteRepository` | サイト情報の照会・検索 |

## シングルサイト環境での動作

すべてのクラスは `function_exists()` ガードにより、シングルサイト環境でもエラーなく動作する。

| クラス | シングルサイトでの動作 |
|-------|---------------------|
| `BlogContext` | `getCurrentBlogId()` → `1`、`isMultisite()` → `false`、`isSwitched()` → `false` |
| `BlogSwitcher` | `switch_to_blog()` が存在しなければ `switchToBlog()` は何もしない、`restoreCurrentBlog()` も何もしない、コールバック版はそのまま実行 |
| `SiteRepository` | マルチサイト関数が存在しなければ空配列 / `null` を返す |
