# NavigationMenu コンポーネント

NavigationMenu コンポーネントは、メニューレンダリング、カスタムウォーカー、メニューアイテム操作などの拡張機能を備えた、WordPress メニュー管理へのモダンなオブジェクト指向アプローチを提供します。

## このコンポーネントの機能

NavigationMenu コンポーネントは、WordPress メニュー管理を以下の機能で変革します：

- **オブジェクト指向メニュー管理** - クラスベースのメニュー
- **アトリビュートベースのメニュー登録** - 宣言的なセットアップ
- **カスタムメニューウォーカーサポート** - 簡単なカスタマイズ
- **メニューアイテムメタデータ** - リッチなメニュー機能
- **動的メニュー生成** - ユーザー状態に基づく
- **メニューキャッシュ** - パフォーマンスの向上
- **アクセシビリティ改善** - 組み込み対応
- **JSON メニュー出力** - ヘッドレスアプリケーション用

## クイック例

従来の WordPress メニュー処理の代わりに：

```php
// Traditional WordPress - procedural and scattered
add_action('init', function() {
    register_nav_menus([
        'primary' => __('Primary Menu', 'my-theme'),
        'footer' => __('Footer Menu', 'my-theme')
    ]);
});

// In template
wp_nav_menu([
    'theme_location' => 'primary',
    'container' => 'nav',
    'container_class' => 'primary-navigation',
    'menu_class' => 'menu',
    'fallback_cb' => false,
    'depth' => 2,
    'walker' => new Custom_Walker_Nav_Menu()
]);

// Custom walker class with complex string concatenation
class Custom_Walker_Nav_Menu extends Walker_Nav_Menu {
    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        // Complex string concatenation
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $classes[] = 'menu-item-' . $item->ID;

        $class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args));
        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';

        $output .= '<li' . $class_names . '>';

        // More string building...
    }
}

// No built-in:
// - Menu caching
// - Easy menu item manipulation
// - Type-safe menu data
```

このモダンな WpPack アプローチを使用します：

```php
use WpPack\Component\NavigationMenu\AbstractMenu;
use WpPack\Component\NavigationMenu\Attribute\Menu;
use WpPack\Component\NavigationMenu\Attribute\MenuLocation;
use WpPack\Component\NavigationMenu\Walker\BootstrapWalker;

#[Menu(
    id: 'primary',
    name: 'Primary Navigation',
    description: 'The main navigation menu'
)]
#[MenuLocation('header')]
#[MenuLocation('mobile')]
class PrimaryMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->setDepth(3)
            ->enableCache(3600)
            ->setWalker(BootstrapWalker::class)
            ->addItemClass('nav-item')
            ->addLinkClass('nav-link');
    }

    protected function shouldShowItem(MenuItem $item): bool
    {
        // Custom logic for showing/hiding items
        if ($item->hasClass('members-only')) {
            return is_user_logged_in();
        }

        return parent::shouldShowItem($item);
    }

    protected function modifyItem(MenuItem $item): MenuItem
    {
        // Add icons to menu items
        if ($icon = $item->getMeta('icon')) {
            $item->prependToTitle("<i class='{$icon}'></i> ");
        }

        // Add badges
        if ($item->getTitle() === 'Cart' && class_exists('WooCommerce')) {
            $count = WC()->cart->get_cart_contents_count();
            if ($count > 0) {
                $item->appendToTitle(" <span class='badge'>{$count}</span>");
            }
        }

        return $item;
    }
}
```

## コア機能

### クラスベースのメニュー

明確な構造を持つクラスとしてメニューを定義します：

```php
#[Menu(
    id: 'footer',
    name: 'Footer Menu',
    description: 'Links in the footer'
)]
#[MenuLocation('footer')]
class FooterMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->setDepth(1)
            ->setFallback([$this, 'generateFallback'])
            ->addContainerClass('footer-nav')
            ->withMenuClass('footer-links');
    }

    protected function generateFallback(): array
    {
        // Generate default menu items if no menu is assigned
        return [
            MenuItem::create('Home', home_url('/')),
            MenuItem::create('About', home_url('/about')),
            MenuItem::create('Contact', home_url('/contact')),
            MenuItem::create('Privacy Policy', home_url('/privacy-policy'))
        ];
    }
}
```

### カスタムメニューウォーカー

フレームワーク固有のウォーカーを簡単に作成できます：

```php
use WpPack\Component\NavigationMenu\Walker\AbstractWalker;

class TailwindWalker extends AbstractWalker
{
    protected function itemClasses(MenuItem $item, int $depth): array
    {
        $classes = parent::itemClasses($item, $depth);

        if ($depth === 0) {
            $classes[] = 'relative';
        }

        if ($item->hasChildren()) {
            $classes[] = 'group';
        }

        if ($item->isCurrent()) {
            $classes[] = 'bg-gray-900';
        }

        return $classes;
    }

    protected function linkClasses(MenuItem $item, int $depth): array
    {
        return [
            'block',
            'px-3',
            'py-2',
            'text-sm',
            'font-medium',
            $item->isCurrent() ? 'text-white' : 'text-gray-300',
            'hover:bg-gray-700',
            'hover:text-white'
        ];
    }
}
```

### 動的メニュー生成

アプリケーション状態に基づいてメニューアイテムを生成します：

```php
#[Menu(id: 'user', name: 'User Menu')]
class UserMenu extends AbstractMenu
{
    protected function additionalItems(): array
    {
        if (!is_user_logged_in()) {
            return [
                MenuItem::create('Login', wp_login_url()),
                MenuItem::create('Register', wp_registration_url())
            ];
        }

        $user = wp_get_current_user();
        $items = [
            MenuItem::create('My Account', home_url('/account'))
                ->addChild('Profile', home_url('/account/profile'))
                ->addChild('Settings', home_url('/account/settings'))
        ];

        if (current_user_can('edit_posts')) {
            $items[] = MenuItem::create('Dashboard', admin_url());
        }

        $items[] = MenuItem::create('Logout', wp_logout_url());

        return $items;
    }
}
```

### メニューキャッシュ

インテリジェントなキャッシュでパフォーマンスを向上させます：

```php
#[Menu(id: 'main', name: 'Main Menu')]
class MainMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->enableCache(3600) // Cache for 1 hour
            ->setCacheKey(function() {
                // Vary cache by user role
                return 'main_menu_' . (is_user_logged_in() ? get_user_role() : 'guest');
            });
    }

    protected function shouldInvalidateCache(): bool
    {
        // Invalidate cache when menu is updated
        return did_action('wp_update_nav_menu') > 0;
    }
}
```

## このコンポーネントの使用場面

**最適な用途：**
- カスタムテーマ開発
- 複雑なナビゲーション要件
- マルチレベルメニュー構造
- 動的メニュー生成
- ヘッドレス WordPress アプリケーション
- パフォーマンス要件のあるサイト
- アクセシビリティ重視のプロジェクト

**代替を検討すべき場合：**
- 基本的なメニューで十分なシンプルなブログ
- デフォルトの WordPress メニューで事足りる場合
- カスタム要件のないプロジェクト
- 簡易プロトタイプ

## WordPress 統合

このコンポーネントは WordPress のメニュー機能を拡張します：

- **メニュー登録** - register_nav_menus() を使用
- **メニューレンダリング** - wp_nav_menu() を拡張
- **ウォーカークラス** - Walker_Nav_Menu を改善
- **メニューアイテム** - wp_posts テーブルと連携
- **メニューロケーション** - テーマロケーションと統合
- **管理画面インターフェース** - メニューエディタと互換

## 高度な機能

### アクセシビリティ強化

組み込みのアクセシビリティ機能：

```php
class AccessibleMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->enableAria()
            ->setAriaLabel('Main navigation')
            ->addLinkAttribute('role', 'menuitem');
    }

    protected function modifyItem(MenuItem $item): MenuItem
    {
        if ($item->hasChildren()) {
            $item->setAttribute('aria-haspopup', 'true')
                ->setAttribute('aria-expanded', 'false');
        }

        if ($item->isCurrent()) {
            $item->setAttribute('aria-current', 'page');
        }

        return $item;
    }
}
```

### JSON 出力

ヘッドレスアプリケーションをサポートします：

```php
#[RestController('/api/v1/menus')]
class MenuController
{
    #[RestRoute('/{location}', methods: ['GET'])]
    public function getMenu(string $location): JsonResponse
    {
        $menu = $this->menus->getByLocation($location);

        if (!$menu) {
            return new JsonResponse(['error' => 'Menu not found'], 404);
        }

        return new JsonResponse([
            'id' => $menu->getId(),
            'name' => $menu->getName(),
            'items' => $menu->toArray()
        ]);
    }
}
```

## インストール

```bash
composer require wppack/navigation-menu
```

詳細なインストール手順については、[インストールガイド](../../guides/installation.md) を参照してください。

## はじめに

1. **[NavigationMenu クイックスタート](quick-start.md)** - 5分で最初のメニューを作成
2. **[コンポーネント概要](../README.md)** - 他の WpPack コンポーネントを探索
3. **[WordPress 統合](../../guides/wordpress-integration.md)** - WordPress パターン

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress メニュー登録フック用

### 推奨
- **Cache コンポーネント** - メニューキャッシュ用
- **Database コンポーネント** - カスタムメニュークエリ用
- **Security コンポーネント** - メニューアイテム権限用

## パフォーマンス機能

- **メニューキャッシュ** - レンダリング出力をキャッシュ
- **遅延読み込み** - オンデマンドでメニューアイテムを読み込み
- **クエリ最適化** - 効率的なデータベースクエリ

## アクセシビリティ機能

- **ARIA 属性** - 適切なラベリング
- **スクリーンリーダーサポート** - セマンティックマークアップ
- **フォーカス管理** - 適切なフォーカスハンドリング
- **スキップリンク** - ナビゲーションショートカット

## 次のステップ

- **[NavigationMenu クイックスタート](quick-start.md)** - 最初のメニューを構築
- **[コンポーネント概要](../README.md)** - 他の WpPack コンポーネントを探索
- **[WordPress 統合](../../guides/wordpress-integration.md)** - WordPress パターン

# NavigationMenu クイックスタート

WpPack NavigationMenu コンポーネントを5分で始めましょう。このガイドでは、拡張機能を備えたモダンなオブジェクト指向ナビゲーションメニューの作成方法を説明します。

## インストールとセットアップ

### 1. コンポーネントのインストール

```bash
composer require wppack/navigation-menu
```

### 2. 最初のメニューを作成

```php
use WpPack\Component\NavigationMenu\AbstractMenu;
use WpPack\Component\NavigationMenu\Attribute\Menu;
use WpPack\Component\NavigationMenu\Attribute\MenuLocation;

#[Menu(
    id: 'primary',
    name: 'Primary Navigation',
    description: 'Main site navigation'
)]
#[MenuLocation('header')]
class PrimaryMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->setDepth(2)
            ->addContainerClass('primary-nav')
            ->addMenuClass('nav-menu')
            ->addItemClass('nav-item')
            ->addLinkClass('nav-link');
    }
}
```

### 3. メニューの登録と表示

```php
// In your theme's functions.php or plugin
$container->get(MenuRegistry::class)->register(PrimaryMenu::class);

// In your template
<?php
$menu = $container->get(PrimaryMenu::class);
echo $menu->render();
?>
```

## Bootstrap ナビゲーション例

### 1. Bootstrap メニューの作成

```php
use WpPack\Component\NavigationMenu\Walker\BootstrapWalker;

#[Menu(id: 'main', name: 'Main Menu')]
#[MenuLocation('primary')]
class BootstrapMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->setWalker(BootstrapWalker::class)
            ->setDepth(3)
            ->addContainerClass('navbar-nav')
            ->addItemClass('nav-item')
            ->addLinkClass('nav-link');
    }

    protected function modifyItem(MenuItem $item): MenuItem
    {
        // Add active class to current item
        if ($item->isCurrent()) {
            $item->addClass('active');
        }

        // Add dropdown classes for items with children
        if ($item->hasChildren()) {
            $item->addClass('dropdown');
            $item->getLinkElement()
                ->addClass('dropdown-toggle')
                ->setAttribute('data-bs-toggle', 'dropdown')
                ->setAttribute('aria-expanded', 'false');
        }

        return $item;
    }
}
```

### 2. ヘッダーでの表示

```php
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="<?php echo home_url(); ?>">
            <?php bloginfo('name'); ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <?php echo $container->get(BootstrapMenu::class)->render(); ?>
        </div>
    </div>
</nav>
```

## Tailwind CSS メニュー例

### 1. Tailwind メニューウォーカーの作成

```php
use WpPack\Component\NavigationMenu\Walker\AbstractWalker;

class TailwindWalker extends AbstractWalker
{
    protected function itemClasses(MenuItem $item, int $depth): array
    {
        $classes = [];

        if ($depth === 0) {
            $classes[] = 'relative';
        }

        if ($item->hasChildren()) {
            $classes[] = 'group';
        }

        return $classes;
    }

    protected function linkClasses(MenuItem $item, int $depth): array
    {
        $classes = [
            'block',
            'px-4',
            'py-2',
            'text-sm'
        ];

        if ($depth === 0) {
            $classes[] = $item->isCurrent()
                ? 'text-gray-900 font-medium'
                : 'text-gray-700 hover:text-gray-900';
        } else {
            $classes[] = 'text-gray-600 hover:bg-gray-50';
        }

        return $classes;
    }

    protected function submenuClasses(int $depth): array
    {
        return [
            'absolute',
            'left-0',
            'mt-2',
            'w-48',
            'rounded-md',
            'shadow-lg',
            'bg-white',
            'ring-1',
            'ring-black',
            'ring-opacity-5',
            'opacity-0',
            'invisible',
            'group-hover:opacity-100',
            'group-hover:visible',
            'transition',
            'ease-out',
            'duration-200'
        ];
    }
}
```

### 2. Tailwind メニューの使用

```php
#[Menu(id: 'tailwind', name: 'Tailwind Menu')]
class TailwindMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->setWalker(TailwindWalker::class)
            ->setContainerElement('div')
            ->addContainerClass('hidden md:block')
            ->addMenuClass('flex space-x-8');
    }
}
```

## 動的ユーザーメニュー例

### 1. ユーザー対応メニューの作成

```php
#[Menu(id: 'user', name: 'User Menu')]
#[MenuLocation('user-nav')]
class UserMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->setDepth(2)
            ->enableCache(300) // Cache for 5 minutes
            ->setCacheKey(function() {
                return 'user_menu_' . get_current_user_id();
            });
    }

    protected function additionalItems(): array
    {
        $items = [];

        if (!is_user_logged_in()) {
            $items[] = MenuItem::create('Login', wp_login_url())
                ->setIcon('login')
                ->setAttribute('data-modal', 'login');

            $items[] = MenuItem::create('Register', wp_registration_url())
                ->setIcon('user-plus');

            return $items;
        }

        // Logged in user menu
        $user = wp_get_current_user();

        $accountItem = MenuItem::create('My Account', get_permalink(get_option('woocommerce_myaccount_page_id')))
            ->setIcon('user')
            ->setAttribute('data-user', $user->ID);

        // Add submenu items
        $accountItem->addChild('Dashboard', wc_get_account_endpoint_url('dashboard'))
            ->addChild('Orders', wc_get_account_endpoint_url('orders'))
            ->addChild('Downloads', wc_get_account_endpoint_url('downloads'))
            ->addChild('Edit Account', wc_get_account_endpoint_url('edit-account'));

        $items[] = $accountItem;

        if (current_user_can('manage_options')) {
            $items[] = MenuItem::create('Admin', admin_url())
                ->setIcon('cog')
                ->setAttribute('target', '_blank');
        }

        $items[] = MenuItem::create('Logout', wp_logout_url())
            ->setIcon('logout')
            ->addClass('text-red-600');

        return $items;
    }
}
```

### 2. ユーザーメニューの表示

```php
<div class="user-navigation">
    <?php if (is_user_logged_in()) : ?>
        <span class="welcome-message">
            Welcome, <?php echo wp_get_current_user()->display_name; ?>!
        </span>
    <?php endif; ?>

    <?php echo $container->get(UserMenu::class)->render(); ?>
</div>
```

## モバイルメニュー例

### 1. モバイル最適化メニューの作成

```php
#[Menu(id: 'mobile', name: 'Mobile Menu')]
#[MenuLocation('mobile')]
class MobileMenu extends AbstractMenu
{
    protected function configure(): void
    {
        $this->setWalker(MobileWalker::class)
            ->setContainerElement('nav')
            ->addContainerClass('mobile-menu')
            ->setAttribute('data-menu', 'mobile');
    }

    public function renderToggle(): string
    {
        return '
            <button class="mobile-menu-toggle" aria-label="Menu" aria-expanded="false">
                <span class="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        ';
    }

    public function renderWithWrapper(): string
    {
        return sprintf(
            '<div class="mobile-menu-wrapper">
                %s
                <div class="mobile-menu-container">
                    %s
                </div>
            </div>',
            $this->renderToggle(),
            $this->render()
        );
    }
}
```

### 2. モバイルメニュー JavaScript

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.querySelector('.mobile-menu-toggle');
    const menu = document.querySelector('.mobile-menu-container');

    toggle.addEventListener('click', function() {
        const isOpen = toggle.getAttribute('aria-expanded') === 'true';

        toggle.setAttribute('aria-expanded', !isOpen);
        menu.classList.toggle('is-open');
        document.body.classList.toggle('menu-open');
    });

    // Close on escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && menu.classList.contains('is-open')) {
            toggle.click();
        }
    });
});
```

## アイコン付きフッターメニュー

### 1. ソーシャルリンクメニューの作成

```php
#[Menu(id: 'social', name: 'Social Links')]
#[MenuLocation('footer-social')]
class SocialMenu extends AbstractMenu
{
    private array $socialIcons = [
        'facebook.com' => 'fab fa-facebook-f',
        'twitter.com' => 'fab fa-twitter',
        'instagram.com' => 'fab fa-instagram',
        'linkedin.com' => 'fab fa-linkedin-in',
        'youtube.com' => 'fab fa-youtube',
        'github.com' => 'fab fa-github'
    ];

    protected function configure(): void
    {
        $this->setDepth(1)
            ->addMenuClass('social-links')
            ->addItemClass('social-item');
    }

    protected function modifyItem(MenuItem $item): MenuItem
    {
        $url = $item->getUrl();

        // Find matching social platform
        foreach ($this->socialIcons as $domain => $icon) {
            if (strpos($url, $domain) !== false) {
                $platform = str_replace(['fab fa-', '-f'], '', $icon);

                // Replace text with icon
                $item->setTitle(sprintf('<i class="%s"></i>', $icon))
                    ->setAttribute('aria-label', ucfirst($platform))
                    ->addClass('social-' . $platform);

                break;
            }
        }

        return $item;
    }
}
```

### 2. ソーシャルメニューの表示

```php
<footer class="site-footer">
    <div class="footer-social">
        <h3>Follow Us</h3>
        <?php echo $container->get(SocialMenu::class)->render(); ?>
    </div>
</footer>
```

## カスタムフィールド付きメニュー

### 1. カスタムメニューフィールドの追加

```php
use WpPack\Component\NavigationMenu\Attribute\MenuField;

class MenuFieldsService
{
    #[Action('wp_nav_menu_item_custom_fields')]
    public function addCustomFields(int $itemId, object $item): void
    {
        $icon = get_post_meta($itemId, '_menu_item_icon', true);
        $badge = get_post_meta($itemId, '_menu_item_badge', true);
        ?>
        <p class="field-icon description description-wide">
            <label for="edit-menu-item-icon-<?php echo $itemId; ?>">
                Icon Class<br>
                <input type="text"
                       id="edit-menu-item-icon-<?php echo $itemId; ?>"
                       class="widefat"
                       name="menu-item-icon[<?php echo $itemId; ?>]"
                       value="<?php echo esc_attr($icon); ?>">
            </label>
        </p>
        <p class="field-badge description description-wide">
            <label for="edit-menu-item-badge-<?php echo $itemId; ?>">
                Badge Text<br>
                <input type="text"
                       id="edit-menu-item-badge-<?php echo $itemId; ?>"
                       class="widefat"
                       name="menu-item-badge[<?php echo $itemId; ?>]"
                       value="<?php echo esc_attr($badge); ?>">
            </label>
        </p>
        <?php
    }

    #[Action('wp_update_nav_menu_item')]
    public function saveCustomFields(int $menuId, int $itemId): void
    {
        if (isset($_POST['menu-item-icon'][$itemId])) {
            update_post_meta($itemId, '_menu_item_icon', sanitize_text_field($_POST['menu-item-icon'][$itemId]));
        }

        if (isset($_POST['menu-item-badge'][$itemId])) {
            update_post_meta($itemId, '_menu_item_badge', sanitize_text_field($_POST['menu-item-badge'][$itemId]));
        }
    }
}
```

### 2. メニューでのカスタムフィールドの使用

```php
#[Menu(id: 'enhanced', name: 'Enhanced Menu')]
class EnhancedMenu extends AbstractMenu
{
    protected function modifyItem(MenuItem $item): MenuItem
    {
        // Add icon if set
        if ($icon = $item->getMeta('icon')) {
            $item->prependToTitle(sprintf('<i class="%s"></i> ', $icon));
        }

        // Add badge if set
        if ($badge = $item->getMeta('badge')) {
            $badgeClass = is_numeric($badge) ? 'badge-count' : 'badge-text';
            $item->appendToTitle(sprintf(' <span class="badge %s">%s</span>', $badgeClass, $badge));
        }

        return $item;
    }
}
```

## メニューのテスト

### 1. メニューのユニットテスト

```php
use PHPUnit\Framework\TestCase;

class MenuTest extends TestCase
{
    private PrimaryMenu $menu;

    protected function setUp(): void
    {
        $this->menu = new PrimaryMenu();
    }

    public function testMenuConfiguration(): void
    {
        $this->assertEquals(2, $this->menu->getDepth());
        $this->assertContains('nav-menu', $this->menu->getMenuClasses());
        $this->assertContains('nav-item', $this->menu->getItemClasses());
    }

    public function testMenuItemModification(): void
    {
        $item = MenuItem::create('Test', '/test');
        $item->setMeta('icon', 'fas fa-test');

        $modified = $this->menu->modifyItem($item);

        $this->assertStringContainsString('fa-test', $modified->getTitle());
    }
}
```

## 次のステップ

基本を学んだので、次に進みましょう：

1. **[NavigationMenu 概要を探索](overview.md)** - 高度な機能とウォーカー
2. **[コンポーネント概要](../README.md)** - 他の WpPack コンポーネントを探索
3. **[WordPress 統合](../../guides/wordpress-integration.md)** - WordPress パターン

## クイックリファレンス

### メニューアトリビュート

```php
#[Menu(id: 'menu-id', name: 'Menu Name', description: 'Optional description')]
#[MenuLocation('theme-location')]
#[MenuCache(duration: 3600, key: 'cache-key')]
```

### メニュー設定

```php
$this->setDepth(3)
     ->setWalker(CustomWalker::class)
     ->enableCache(3600)
     ->addContainerClass('nav-container')
     ->addMenuClass('nav-menu')
     ->addItemClass('nav-item')
     ->addLinkClass('nav-link')
     ->setAttribute('aria-label', 'Main navigation');
```

### MenuItem メソッド

```php
MenuItem::create('Title', '/url')
    ->addChild('Child', '/child-url')
    ->setIcon('icon-class')
    ->setAttribute('data-custom', 'value')
    ->addClass('custom-class')
    ->setMeta('key', 'value');
```

### 共通メソッド

```php
// Menu methods
$menu->render($args);
$menu->toArray();
$menu->toJson();
$menu->getItems();

// MenuItem methods
$item->hasChildren();
$item->isCurrent();
$item->isAncestor();
$item->getDepth();
$item->getParent();
```

# NavigationMenu コンポーネント Named Hook アトリビュート

NavigationMenu コンポーネントは、WordPress ナビゲーションメニュー機能のための Named Hook アトリビュートを提供します。これらのアトリビュートにより、型安全性とモダンな PHP 機能を活用して、メニューの登録、レンダリング、カスタマイズを管理できます。

## メニュー登録フック

### #[AfterSetupThemeAction(priority?: int = 10)]

**WordPress フック:** `after_setup_theme`
**使用場面:** ナビゲーションメニューロケーションを登録する場合。

```php
use WpPack\Component\Hook\Attribute\AfterSetupThemeAction;
use WpPack\Component\NavigationMenu\MenuRegistry;

class MenuManager
{
    private MenuRegistry $menus;

    public function __construct(MenuRegistry $menus)
    {
        $this->menus = $menus;
    }

    #[AfterSetupThemeAction]
    public function registerMenuLocations(): void
    {
        // Register primary navigation locations
        $this->menus->registerLocation('primary', __('Primary Navigation', 'wppack'));
        $this->menus->registerLocation('secondary', __('Secondary Navigation', 'wppack'));
        $this->menus->registerLocation('footer', __('Footer Menu', 'wppack'));

        // Register mobile-specific locations
        $this->menus->registerLocation('mobile', __('Mobile Navigation', 'wppack'));
        $this->menus->registerLocation('mobile_footer', __('Mobile Footer', 'wppack'));

        // Register context-specific menus
        $this->menus->registerLocation('account', __('Account Menu', 'wppack'));
        $this->menus->registerLocation('social', __('Social Links', 'wppack'));
    }
}
```

## メニュー表示フック

### #[WpNavMenuArgsFilter(priority?: int = 10)]

**WordPress フック:** `wp_nav_menu_args`
**使用場面:** 表示前にナビゲーションメニュー引数を変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuArgsFilter;

class MenuArgumentsCustomizer
{
    #[WpNavMenuArgsFilter]
    public function customizeMenuArgs(array $args): array
    {
        // Add default container classes
        if (empty($args['container_class'])) {
            $args['container_class'] = 'wppack-menu-container';
        }

        // Add BEM classes based on theme location
        if (!empty($args['theme_location'])) {
            $location = $args['theme_location'];
            $args['menu_class'] = "wppack-menu wppack-menu--{$location}";
            $args['container_class'] .= " wppack-menu-container--{$location}";
        }

        // Custom walker for specific menus
        if ($args['theme_location'] === 'primary') {
            $args['walker'] = new \WpPackPrimaryMenuWalker();
            $args['depth'] = 3;
        }

        // Mobile menu modifications
        if ($this->isMobileRequest() && $args['theme_location'] === 'primary') {
            $args['theme_location'] = 'mobile';
            $args['menu_class'] .= ' wppack-menu--mobile';
        }

        return $args;
    }

    private function isMobileRequest(): bool
    {
        return wp_is_mobile() || (isset($_SERVER['HTTP_X_MOBILE']) && $_SERVER['HTTP_X_MOBILE'] === '1');
    }
}
```

### #[WpNavMenuItemsFilter(priority?: int = 10)]

**WordPress フック:** `wp_nav_menu_items`
**使用場面:** メニュー HTML 出力を変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuItemsFilter;

class MenuItemsEnhancer
{
    #[WpNavMenuItemsFilter]
    public function enhanceMenuItems(string $items, \stdClass $args): string
    {
        // Add search form to primary menu
        if ($args->theme_location === 'primary') {
            $search_form = $this->getSearchForm();
            $items .= '<li class="menu-item menu-item-search">' . $search_form . '</li>';
        }

        // Add login/logout link to account menu
        if ($args->theme_location === 'account') {
            $items = $this->addAuthenticationLink($items);
        }

        // Add icons to social menu
        if ($args->theme_location === 'social') {
            $items = $this->addSocialIcons($items);
        }

        // Add cart count to shop menus
        if (in_array($args->theme_location, ['primary', 'mobile'])) {
            $items = $this->addCartCount($items);
        }

        return $items;
    }

    private function addAuthenticationLink(string $items): string
    {
        if (is_user_logged_in()) {
            $logout_link = sprintf(
                '<li class="menu-item menu-item-logout"><a href="%s">%s</a></li>',
                wp_logout_url(home_url()),
                __('Logout', 'wppack')
            );
            $items .= $logout_link;
        } else {
            $login_link = sprintf(
                '<li class="menu-item menu-item-login"><a href="%s">%s</a></li>',
                wp_login_url(get_permalink()),
                __('Login', 'wppack')
            );
            $items .= $login_link;
        }

        return $items;
    }
}
```

### #[WpNavMenuObjectsFilter(priority?: int = 10)]

**WordPress フック:** `wp_nav_menu_objects`
**使用場面:** レンダリング前にメニューアイテムオブジェクトを変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuObjectsFilter;

class MenuObjectProcessor
{
    #[WpNavMenuObjectsFilter]
    public function processMenuObjects(array $sorted_menu_items, \stdClass $args): array
    {
        foreach ($sorted_menu_items as &$item) {
            // Add custom classes based on conditions
            if ($this->isCurrentSection($item)) {
                $item->classes[] = 'current-section';
            }

            // Add data attributes
            if ($item->type === 'taxonomy') {
                $count = $this->getTermPostCount($item->object_id);
                $item->classes[] = 'has-count';
                $item->title .= sprintf(' <span class="count">(%d)</span>', $count);
            }

            // Mark external links
            if ($this->isExternalLink($item->url)) {
                $item->classes[] = 'external-link';
                $item->target = '_blank';
                $item->xfn = 'noopener noreferrer';
            }

            // Add icon support
            if ($icon = get_post_meta($item->ID, '_menu_item_icon', true)) {
                $item->classes[] = 'has-icon';
                $item->title = sprintf('<i class="icon-%s"></i> %s', esc_attr($icon), $item->title);
            }
        }

        return $sorted_menu_items;
    }

    private function isCurrentSection($item): bool
    {
        if ($item->type === 'post_type' && $item->object === 'page') {
            $current_id = get_queried_object_id();
            $ancestors = get_post_ancestors($current_id);
            return in_array($item->object_id, $ancestors);
        }
        return false;
    }
}
```

## メニューアイテムフック

### #[WpNavMenuItemCustomFieldsAction(priority?: int = 10)]

**WordPress フック:** `wp_nav_menu_item_custom_fields`
**使用場面:** 管理画面でメニューアイテムにカスタムフィールドを追加する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuItemCustomFieldsAction;

class MenuItemFields
{
    #[WpNavMenuItemCustomFieldsAction]
    public function addCustomFields(int $item_id, \WP_Post $item, int $depth, \stdClass $args): void
    {
        // Add icon selector
        $icon = get_post_meta($item_id, '_menu_item_icon', true);
        ?>
        <p class="field-icon description description-wide">
            <label for="edit-menu-item-icon-<?php echo $item_id; ?>">
                <?php _e('Icon', 'wppack'); ?><br />
                <select name="menu-item-icon[<?php echo $item_id; ?>]" id="edit-menu-item-icon-<?php echo $item_id; ?>" class="widefat">
                    <option value=""><?php _e('None', 'wppack'); ?></option>
                    <?php foreach ($this->getAvailableIcons() as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($icon, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>

        // Add visibility options
        $visibility = get_post_meta($item_id, '_menu_item_visibility', true);
        ?>
        <p class="field-visibility description description-wide">
            <label for="edit-menu-item-visibility-<?php echo $item_id; ?>">
                <?php _e('Visibility', 'wppack'); ?><br />
                <select name="menu-item-visibility[<?php echo $item_id; ?>]" id="edit-menu-item-visibility-<?php echo $item_id; ?>" class="widefat">
                    <option value=""><?php _e('Always visible', 'wppack'); ?></option>
                    <option value="logged-in" <?php selected($visibility, 'logged-in'); ?>><?php _e('Logged in users only', 'wppack'); ?></option>
                    <option value="logged-out" <?php selected($visibility, 'logged-out'); ?>><?php _e('Logged out users only', 'wppack'); ?></option>
                    <option value="mobile" <?php selected($visibility, 'mobile'); ?>><?php _e('Mobile only', 'wppack'); ?></option>
                    <option value="desktop" <?php selected($visibility, 'desktop'); ?>><?php _e('Desktop only', 'wppack'); ?></option>
                </select>
            </label>
        </p>
        <?php
    }

    private function getAvailableIcons(): array
    {
        return [
            'home' => __('Home', 'wppack'),
            'user' => __('User', 'wppack'),
            'cart' => __('Cart', 'wppack'),
            'search' => __('Search', 'wppack'),
            'menu' => __('Menu', 'wppack'),
            'arrow-right' => __('Arrow Right', 'wppack'),
            'external' => __('External Link', 'wppack'),
        ];
    }
}
```

### #[WpUpdateNavMenuItemAction(priority?: int = 10)]

**WordPress フック:** `wp_update_nav_menu_item`
**使用場面:** カスタムメニューアイテムデータを保存する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\WpUpdateNavMenuItemAction;

class MenuItemSaver
{
    #[WpUpdateNavMenuItemAction]
    public function saveCustomFields(int $menu_id, int $menu_item_db_id, array $args): void
    {
        // Save icon
        if (isset($_POST['menu-item-icon'][$menu_item_db_id])) {
            $icon = sanitize_text_field($_POST['menu-item-icon'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_icon', $icon);
        }

        // Save visibility
        if (isset($_POST['menu-item-visibility'][$menu_item_db_id])) {
            $visibility = sanitize_text_field($_POST['menu-item-visibility'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_visibility', $visibility);
        }

        // Save custom CSS classes
        if (isset($_POST['menu-item-classes'][$menu_item_db_id])) {
            $classes = array_map('sanitize_html_class', $_POST['menu-item-classes'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_classes', $classes);
        }

        // Clear menu cache
        $this->clearMenuCache($menu_id);
    }

    private function clearMenuCache(int $menu_id): void
    {
        $locations = get_nav_menu_locations();
        foreach ($locations as $location => $id) {
            if ($id === $menu_id) {
                delete_transient('wppack_menu_' . $location);
            }
        }
    }
}
```

## メニューウォーカーフック

### #[NavMenuCssClassFilter(priority?: int = 10)]

**WordPress フック:** `nav_menu_css_class`
**使用場面:** メニューアイテムの CSS クラスを変更する場合。

```php
use WpPack\Component\NavigationMenu\Attribute\NavMenuCssClassFilter;

class MenuClassCustomizer
{
    #[NavMenuCssClassFilter]
    public function customizeItemClasses(array $classes, \WP_Post $item, \stdClass $args, int $depth): array
    {
        // Add depth class
        $classes[] = 'menu-item-depth-' . $depth;

        // Add post type class
        if ($item->type === 'post_type') {
            $classes[] = 'menu-item-' . $item->object;
        }

        // Add visibility classes
        $visibility = get_post_meta($item->ID, '_menu_item_visibility', true);
        if ($visibility) {
            $classes[] = 'menu-item-visibility-' . $visibility;
        }

        // Add custom state classes
        if ($this->hasChildren($item, $args)) {
            $classes[] = 'has-children';
        }

        if ($this->isActive($item)) {
            $classes[] = 'is-active';
        }

        return array_unique(array_filter($classes));
    }

    private function hasChildren(\WP_Post $item, \stdClass $args): bool
    {
        if (!empty($args->has_children)) {
            return true;
        }

        // Check if item has children in menu structure
        $menu_items = wp_get_nav_menu_items($args->menu->term_id);
        foreach ($menu_items as $menu_item) {
            if ($menu_item->menu_item_parent == $item->ID) {
                return true;
            }
        }

        return false;
    }
}
```

## 実践的な例

### 完全なナビゲーションシステム

```php
use WpPack\Component\Hook\Attribute\InitAction;
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuArgsFilter;
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuObjectsFilter;
use WpPack\Component\NavigationMenu\MenuService;
use WpPack\Component\NavigationMenu\MenuCache;

class WpPackNavigationSystem
{
    private MenuService $service;
    private MenuCache $cache;
    private Logger $logger;

    public function __construct(
        MenuService $service,
        MenuCache $cache,
        Logger $logger
    ) {
        $this->service = $service;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    #[InitAction]
    public function initializeNavigation(): void
    {
        // Register menu locations
        $this->registerMenuLocations();

        // Set up menu caching
        $this->configureCaching();

        // Register custom walkers
        $this->registerWalkers();
    }

    #[WpNavMenuArgsFilter]
    public function enhanceMenuArgs(array $args): array
    {
        // Check cache first
        if ($cached = $this->getCachedMenu($args)) {
            $args['echo'] = false;
            add_filter('pre_wp_nav_menu', function() use ($cached) {
                return $cached;
            });
            return $args;
        }

        // Mobile optimization
        if (wp_is_mobile()) {
            $args = $this->optimizeForMobile($args);
        }

        // Accessibility enhancements
        $args['items_wrap'] = '<ul id="%1$s" class="%2$s" role="navigation" aria-label="' . esc_attr($args['theme_location']) . '">%3$s</ul>';

        // Performance optimization
        $args['depth'] = $this->getOptimalDepth($args['theme_location']);

        return $args;
    }

    #[WpNavMenuObjectsFilter]
    public function processMenuItems(array $sorted_menu_items, \stdClass $args): array
    {
        // Apply visibility rules
        $sorted_menu_items = $this->applyVisibilityRules($sorted_menu_items);

        // Add dynamic content
        $sorted_menu_items = $this->addDynamicContent($sorted_menu_items);

        // Optimize menu structure
        $sorted_menu_items = $this->optimizeStructure($sorted_menu_items);

        // Cache processed items
        $this->cacheProcessedItems($sorted_menu_items, $args);

        return $sorted_menu_items;
    }

    private function applyVisibilityRules(array $items): array
    {
        return array_filter($items, function($item) {
            $visibility = get_post_meta($item->ID, '_menu_item_visibility', true);

            switch ($visibility) {
                case 'logged-in':
                    return is_user_logged_in();
                case 'logged-out':
                    return !is_user_logged_in();
                case 'mobile':
                    return wp_is_mobile();
                case 'desktop':
                    return !wp_is_mobile();
                default:
                    return true;
            }
        });
    }

    private function addDynamicContent(array $items): array
    {
        foreach ($items as &$item) {
            // Add notification badges
            if ($item->object === 'page' && $item->object_id == get_option('page_for_posts')) {
                $count = wp_count_posts()->publish;
                $item->title .= sprintf(' <span class="badge">%d</span>', $count);
            }

            // Add user info to account menu items
            if (is_user_logged_in() && strpos($item->url, 'account') !== false) {
                $user = wp_get_current_user();
                $item->title = str_replace('{username}', $user->display_name, $item->title);
            }

            // Add cart info
            if (strpos($item->url, 'cart') !== false && function_exists('WC')) {
                $count = WC()->cart->get_cart_contents_count();
                if ($count > 0) {
                    $item->title .= sprintf(' <span class="cart-count">%d</span>', $count);
                }
            }
        }

        return $items;
    }
}
```

## Hook アトリビュートリファレンス

### 利用可能な Hook アトリビュート

```php
// メニュー登録
// メニューロケーション登録には Hook コンポーネントの #[AfterSetupThemeAction(priority?: int = 10)] を使用

// メニュー表示
#[WpNavMenuArgsFilter(priority?: int = 10)]          // メニュー引数の変更
#[WpNavMenuItemsFilter(priority?: int = 10)]         // メニュー HTML の変更
#[WpNavMenuObjectsFilter(priority?: int = 10)]       // メニューオブジェクトの処理
#[PreWpNavMenuFilter(priority?: int = 10)]           // メニュー出力のオーバーライド

// メニューアイテム
#[WpNavMenuItemCustomFieldsAction(priority?: int = 10)] // カスタムフィールドの追加
#[WpUpdateNavMenuItemAction(priority?: int = 10)]    // メニューアイテムデータの保存
#[WpSetupNavMenuItemFilter(priority?: int = 10)]     // メニューアイテムのセットアップ

// CSS クラス
#[NavMenuCssClassFilter(priority?: int = 10)]        // メニューアイテムクラス
#[NavMenuItemIdFilter(priority?: int = 10)]          // メニューアイテム ID
#[NavMenuLinkAttributesFilter(priority?: int = 10)]  // リンク属性

// メニュー管理
#[WpCreateNavMenuAction(priority?: int = 10)]        // メニュー作成時
#[WpUpdateNavMenuAction(priority?: int = 10)]        // メニュー更新時
#[WpDeleteNavMenuAction(priority?: int = 10)]        // メニュー削除時
```

## 従来の WordPress vs WpPack

### Before（従来の WordPress）
```php
// Traditional menu registration
function register_menus() {
    register_nav_menus(array(
        'primary' => 'Primary Menu',
        'footer' => 'Footer Menu'
    ));
}
add_action('after_setup_theme', 'register_menus');

// Modify menu output
add_filter('wp_nav_menu_items', function($items, $args) {
    if ($args->theme_location === 'primary') {
        $items .= '<li class="menu-search">' . get_search_form(false) . '</li>';
    }
    return $items;
}, 10, 2);
```

### After（WpPack）
```php
use WpPack\Component\Hook\Attribute\AfterSetupThemeAction;
use WpPack\Component\NavigationMenu\Attribute\WpNavMenuItemsFilter;
use WpPack\Component\NavigationMenu\MenuRegistry;

class NavigationManager
{
    private MenuRegistry $menus;

    public function __construct(MenuRegistry $menus)
    {
        $this->menus = $menus;
    }

    #[AfterSetupThemeAction]
    public function registerMenus(): void
    {
        $this->menus->register([
            'primary' => __('Primary Menu', 'wppack'),
            'footer' => __('Footer Menu', 'wppack'),
        ]);
    }

    #[WpNavMenuItemsFilter]
    public function addSearchForm(string $items, \stdClass $args): string
    {
        if ($args->theme_location === 'primary') {
            $items .= $this->menus->renderSearchItem();
        }
        return $items;
    }
}
```

### メリット
- **型安全性** - すべてのパラメータが型付き
- **サービス統合** - メニューサービスの簡単なインジェクション
- **整理** - メニューロジックがまとまっている
- **テスト容易性** - メニューメソッドのテストが可能
- **キャッシュ** - 組み込みのパフォーマンス最適化

## ベストプラクティス

1. **パフォーマンス**
   - レンダリングされたメニューをキャッシュする
   - メニューの深さを制限する
   - データベースクエリを最適化する

2. **アクセシビリティ**
   - セマンティック HTML を使用する
   - ARIA ラベルを追加する
   - キーボードナビゲーションをサポートする
   - 適切なフォーカス管理を確保する

3. **モバイル最適化**
   - モバイル専用メニューを分離する
   - タッチフレンドリーなターゲット
   - 簡素化された構造
   - パフォーマンス最適化

4. **拡張性**
   - カスタムウォーカーを使用する
   - アクションフックを追加する
   - メニューメタデータをサポートする
   - 簡単なカスタマイズを可能にする

## 次のステップ

- **[NavigationMenu コンポーネント概要](overview.md)** - メニュー機能について学ぶ
- **[NavigationMenu クイックスタート](quick-start.md)** - WpPack でメニューを構築
- **[Hook コンポーネント](../hook/overview.md)** - 一般的な WordPress フック管理
