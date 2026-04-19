# Role Component

**パッケージ:** `wppack/role`
**名前空間:** `WPPack\Component\Role\`
**レイヤー:** Infrastructure

WordPress のロール・権限管理関数（`add_role()` / `add_cap()` / `current_user_can()`）をアトリビュートベースでラップし、型安全なロール定義と権限チェックを提供するコンポーネントです。

`#[IsGranted]` アトリビュートと `IsGrantedChecker` もこのコンポーネントで提供しており、Admin / Setting / DashboardWidget / Ajax / Routing / Rest など軽量コンポーネントが Security コンポーネントに依存せずに認可チェックを行えます。

## インストール

```bash
composer require wppack/role
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - 手続き型のロール管理
add_action('init', function () {
    add_role('shop_manager', 'Shop Manager', [
        'read' => true,
        'edit_posts' => true,
        'manage_products' => true,
    ]);
});

if (current_user_can('manage_products')) {
    // 商品管理を許可
}
```

### After（WPPack）

```php
use WPPack\Component\Role\Attribute\AsRole;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Role\RoleManager;

// ロール定義
#[AsRole(
    name: 'shop_manager',
    label: 'Shop Manager',
    capabilities: ['read', 'edit_posts', 'manage_products'],
)]
final class ShopManagerRole {}

// ロール同期
$manager = new RoleManager();
$manager->add(ShopManagerRole::class);
$manager->synchronize();

// コントローラーやサービスでの権限チェック
#[IsGranted('manage_products')]
final class ProductController
{
    // manage_products 権限が必要
}
```

## ロール定義

### `#[AsRole]` アトリビュート

```php
use WPPack\Component\Role\Attribute\AsRole;

#[AsRole(
    name: 'shop_manager',
    label: 'Shop Manager',
    capabilities: ['read', 'edit_posts', 'manage_products', 'view_orders'],
)]
final class ShopManagerRole {}
```

パラメータ:
- `name` — WordPress のロール識別子（`add_role()` の第1引数）
- `label` — 表示名
- `capabilities` — このロールに付与する権限の配列

### `RoleDefinition` 値オブジェクト

プログラム的にロール定義を作成する場合:

```php
use WPPack\Component\Role\RoleDefinition;

$definition = new RoleDefinition(
    name: 'shop_manager',
    label: 'Shop Manager',
    capabilities: ['read', 'edit_posts', 'manage_products'],
);
```

## RoleManager

`RoleManager` はロール定義を管理し、WordPress のロールシステムと同期します。

```php
use WPPack\Component\Role\RoleManager;

$manager = new RoleManager();

// #[AsRole] クラスからロール追加
$manager->add(ShopManagerRole::class);

// RoleDefinition から追加
$manager->addDefinition(new RoleDefinition('viewer', 'Viewer', ['read']));

// 登録済みのロール定義を取得
$definitions = $manager->all(); // array<string, RoleDefinition>

// WordPress DB と同期（差分適用）
$manager->synchronize();

// ロール削除
$manager->unregister('shop_manager');
```

### `synchronize()` の動作

`synchronize()` は PHP 定義と WordPress の `wp_user_roles` オプションを比較し、差分のみを適用します:

- 新規ロール → `add_role()` で追加
- 既存ロールに新しい権限 → `add_cap()` で追加
- 定義から削除された権限 → `remove_cap()` で削除

> [!IMPORTANT]
> `synchronize()` は毎リクエストではなく、プラグインの activation 時や管理画面アクションで呼ぶ想定です。

## 権限チェック（IsGranted）

### `#[IsGranted]` アトリビュート

クラスまたはメソッドに付与して、宣言的に権限チェックを行います。複数指定で AND（すべて通過が必要）。

```php
use WPPack\Component\Role\Attribute\IsGranted;

#[IsGranted('edit_posts')]
final class PostController
{
    #[IsGranted('publish_posts')]
    public function publish(): void
    {
        // edit_posts AND publish_posts が必要
    }
}
```

パラメータ:
- `attribute` — 権限文字列（`current_user_can()` に渡される）
- `subject` — 対象オブジェクト（省略可、`current_user_can()` の第2引数）
- `message` — 拒否時のメッセージ（デフォルト: `'Access Denied.'`）
- `statusCode` — 拒否時のステータスコード（デフォルト: `403`）

### `IsGrantedChecker`

`#[IsGranted]` アトリビュートの解決とチェックを行うサービスです。

```php
use WPPack\Component\Role\Authorization\IsGrantedChecker;

// アトリビュートの解決
$grants = IsGrantedChecker::resolve($reflectionClass, $reflectionMethod);

// チェック（AccessDeniedException をスロー）
$checker = new IsGrantedChecker();
$checker->check($grants);

// クラスレベルの最初の権限を取得（デフォルト: 'manage_options'）
$capability = IsGrantedChecker::extractCapability($reflectionClass);
```

Security コンポーネントと併用する場合、`AuthorizationCheckerInterface` を注入して Voter ベースの認可チェックを利用できます:

```php
use WPPack\Component\Role\Authorization\AuthorizationCheckerInterface;

$checker = new IsGrantedChecker($authorizationChecker);
```

Security が利用できない場合は `current_user_can()` にフォールバックします。

### コンポーネントごとのチェック方式

| コンポーネント | チェック方式 |
|--------------|------------|
| Admin / Setting | `IsGrantedChecker::extractCapability()` で文字列を取り出し `add_menu_page()` に渡す（WordPress が制御） |
| Ajax / Routing | ハンドラー実行前に `IsGrantedChecker::check()` でランタイムチェック |
| Rest | `permission_callback` クロージャを生成して `current_user_can()` チェック |
| DashboardWidget | `register()` 内で `current_user_can()` チェック |

## 例外

| クラス | 説明 |
|-------|------|
| `Exception\ExceptionInterface` | コンポーネント例外の基底インターフェース |
| `Exception\AccessDeniedException` | 権限チェック失敗時にスローされる例外 |

Security コンポーネントの `AccessDeniedException` は Role の `AccessDeniedException` を拡張しているため、`catch (Role\Exception\AccessDeniedException)` で両方をキャッチできます。

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Attribute\AsRole` | ロール定義アトリビュート |
| `Attribute\IsGranted` | 認可チェックアトリビュート |
| `RoleDefinition` | ロール定義の値オブジェクト |
| `RoleManager` | ロールの登録・同期・削除 |
| `Authorization\AuthorizationCheckerInterface` | 認可チェッカーインターフェース |
| `Authorization\IsGrantedChecker` | IsGranted アトリビュートの解決・チェック |
| `Exception\AccessDeniedException` | アクセス拒否例外 |

## 利用シーン

**最適なケース:**
- カスタムロールと権限の定義が必要なプラグイン
- `#[IsGranted]` で宣言的に権限チェックを行いたい場合
- ロールベースのアクセス制御を体系的に管理したい場合
- Security コンポーネントなしで軽量な認可チェックが必要な場合

**代替を検討すべきケース:**
- WordPress 標準のロールで十分な場合
- Voter パターンによる高度な認可が必要な場合（→ Security コンポーネント）
