# Role Component

**パッケージ:** `wppack/role`
**名前空間:** `WpPack\Component\Role\`
**レイヤー:** Infrastructure

WordPress のロール・権限管理関数（`add_role()` / `add_cap()` / `current_user_can()`）をアトリビュートベースでラップし、型安全なロール定義と権限チェックを提供するコンポーネントです。

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

### After（WpPack）

```php
use WpPack\Component\Role\AbstractRole;
use WpPack\Component\Role\Attribute\Role;
use WpPack\Component\Role\Attribute\Capability;
use WpPack\Component\Role\Attribute\RequiresCapability;

#[Role(
    name: 'shop_manager',
    label: 'Shop Manager',
    capabilities: ['read', 'edit_posts', 'manage_products'],
)]
class ShopManagerRole extends AbstractRole
{
    #[Capability('manage_inventory')]
    protected bool $canManageInventory = true;

    #[Capability('view_sales_reports')]
    protected bool $canViewSalesReports = false;
}

// 権限で保護されたサービスメソッド
class ProductService
{
    #[RequiresCapability('manage_products')]
    public function createProduct(array $data): Product
    {
        return $this->productRepository->create($data);
    }
}
```

## ロール定義

### シンプルなロール

```php
use WpPack\Component\Role\AbstractRole;
use WpPack\Component\Role\Attribute\Role;
use WpPack\Component\Role\Attribute\Capability;

#[Role(
    name: 'shop_manager',
    label: 'Shop Manager',
    capabilities: ['read', 'edit_posts'],
)]
class ShopManagerRole extends AbstractRole
{
    #[Capability('manage_products')]
    protected bool $canManageProducts = true;

    #[Capability('view_orders')]
    protected bool $canViewOrders = true;

    #[Capability('manage_inventory')]
    protected bool $canManageInventory = true;

    #[Capability('view_sales_reports')]
    protected bool $canViewSalesReports = false; // デフォルトで無効

    public function getDescription(): string
    {
        return 'Manages products, inventory, and customer orders';
    }
}
```

### ロールの登録

```php
use WpPack\Component\Role\RoleManager;

class RoleManagementService
{
    public function __construct(
        private readonly RoleManager $roleManager,
    ) {}

    #[Action('init', priority: 10)]
    public function onInit(): void
    {
        // #[Role] アトリビュートを持つクラスを自動検出
        $this->roleManager->discoverRoles();
    }

    public function assignShopManagerRole(int $userId): void
    {
        $user = get_user_by('id', $userId);
        if ($user) {
            $this->roleManager->assignRole($user, 'shop_manager');
        }
    }
}
```

## 権限で保護されたサービス

### アトリビュートベースの保護

`#[RequiresCapability]` アトリビュートを使用して、メソッドレベルで `current_user_can()` チェックを自動適用します。

```php
use WpPack\Component\Role\Attribute\RequiresCapability;

class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    #[RequiresCapability('manage_products')]
    public function createProduct(array $data): Product
    {
        return $this->productRepository->create($data);
    }

    #[RequiresCapability('manage_products')]
    public function updateProduct(int $productId, array $data): Product
    {
        return $this->productRepository->update($productId, $data);
    }

    #[RequiresCapability('view_orders')]
    public function getProductOrders(int $productId): array
    {
        return $this->productRepository->getOrders($productId);
    }

    // パブリックメソッド - 権限チェック不要
    public function getPublicProducts(): array
    {
        return $this->productRepository->getPublished();
    }
}
```

### 手動での権限チェック

```php
use WpPack\Component\Role\PermissionChecker;

class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly PermissionChecker $permissions,
    ) {}

    public function processOrder(Order $order): void
    {
        $user = wp_get_current_user();

        if (!$this->permissions->userCan($user, 'manage_orders')) {
            throw new UnauthorizedException('Cannot manage orders');
        }

        if ($order->getValue() > 1000 && !$this->permissions->userCan($user, 'process_high_value_orders')) {
            throw new UnauthorizedException('Cannot process high-value orders');
        }

        $this->orderRepository->markAsProcessed($order);
    }
}
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](./hook/role.md) を参照してください。
## 主要クラス

| クラス | 説明 |
|-------|------|
| `AbstractRole` | ロール定義の基底クラス |
| `RoleManager` | ロールの登録・管理 |
| `PermissionChecker` | 権限チェックサービス |
| `Attribute\Role` | ロール定義アトリビュート |
| `Attribute\Capability` | 権限宣言アトリビュート |
| `Attribute\RequiresCapability` | メソッドレベルの権限ガード |
| `Attribute\UserHasCapFilter` | 権限の動的フィルタフック |
| `Attribute\MapMetaCapFilter` | メタ権限マッピングフック |
| `Attribute\SetUserRoleAction` | ロール変更アクションフック |

## 利用シーン

**最適なケース:**
- カスタムロールと権限の定義が必要なプラグイン
- `current_user_can()` をアトリビュートで宣言的に使いたい場合
- ロールベースのアクセス制御を体系的に管理したい場合

**代替を検討すべきケース:**
- WordPress 標準のロールで十分な場合
- シンプルな権限チェックのみが必要な場合
