# Named Hook 連携規約

Named Hook アトリビュートは、WordPress フック名を文字列で指定する代わりに、型安全なクラスとして提供する仕組みです。本ドキュメントでは、各コンポーネントが named hook を定義する際の規約を定めます。

## 所有権ルール

### Hook コンポーネント（Infrastructure）

Hook コンポーネントは **WordPress ライフサイクルフック** のみを所有します:

- `InitAction` (`init`)
- `AdminInitAction` (`admin_init`)
- `PluginsLoadedAction` (`plugins_loaded`)
- `AfterSetupThemeAction` (`after_setup_theme`)
- `WpLoadedAction` (`wp_loaded`)

加えて、汎用基底クラス `Action` / `Filter` を提供します。

### 各コンポーネント（ドメイン固有フック）

ドメイン固有の WordPress フックは、対応するコンポーネントが所有します:

- **PostType** → `SavePostAction`, `DeletePostAction`, `TransitionPostStatusAction`
- **Admin** → `AdminMenuAction`, `AdminEnqueueScriptsAction`, `AdminNoticesAction` 等
- **Theme** → `WpEnqueueScriptsAction`, `WpHeadAction`, `WpFooterAction`, `BodyClassFilter` 等
- **Templating** → `TheContentFilter`, `TheTitleFilter`
- **Query** → `PreGetPostsAction`, `PostsWhereFilter`, `PostsJoinFilter` 等
- **REST** → `RestApiInitAction` 等
- **Widget** → `WidgetsInitAction` 等
- **Media** → `UploadMimesFilter` 等
- **Mailer** → `WpMailFilter` 等
- **Ajax** → `WpAjaxAction`, `WpAjaxNoprivAction`

## 名前空間・ディレクトリ規約

### 名前空間

```
WpPack\Component\{Name}\Attribute\Action\{HookName}Action
WpPack\Component\{Name}\Attribute\Filter\{HookName}Filter
```

### ディレクトリ構造

```
src/Component/{Name}/
├── src/
│   ├── Attribute/
│   │   ├── Action/
│   │   │   └── {HookName}Action.php
│   │   └── Filter/
│   │       └── {HookName}Filter.php
│   └── ...
└── ...
```

### 命名規則

- アクション: `{HookName}Action`（例: `SavePostAction`, `AdminMenuAction`）
- フィルター: `{HookName}Filter`（例: `PostsWhereFilter`, `BodyClassFilter`）
- フック名はパスカルケースに変換（`save_post` → `SavePost`, `admin_menu` → `AdminMenu`）

## クラステンプレート

### 基本アクション

```php
<?php

declare(strict_types=1);

namespace WpPack\Component\PostType\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SavePostAction extends Action
{
    public function __construct(
        public readonly ?string $postType = null,
        int $priority = 10,
    ) {
        parent::__construct(
            $this->postType !== null ? "save_post_{$this->postType}" : 'save_post',
            $priority,
        );
    }
}
```

### 基本フィルター

```php
<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PostsWhereFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('posts_where', $priority);
    }
}
```

### 動的フック名の例

動的フック名（WordPress の `{$hook}_{$suffix}` パターン）は、コンストラクタパラメータで対応します:

```php
// 使用例: 全投稿タイプ
#[SavePostAction]
public function onSavePost(int $postId): void { /* ... */ }

// 使用例: 特定の投稿タイプ
#[SavePostAction(postType: 'product')]
public function onSaveProduct(int $postId): void { /* ... */ }
```

## 自動検出メカニズム

Named hook アトリビュートは `ReflectionAttribute::IS_INSTANCEOF` により自動検出されます。

```php
// HookDiscovery は Action / Filter の子クラスを自動検出
$attributes = $method->getAttributes(
    Action::class,
    ReflectionAttribute::IS_INSTANCEOF
);
```

この仕組みにより:

1. 新しい named hook アトリビュートは `Action` または `Filter` を継承するだけで自動的に検出対象となる
2. Hook コンポーネント側の設定変更は不要
3. 任意のパッケージで定義された named hook でも、`IS_INSTANCEOF` で検出される
4. サードパーティプラグイン用のカスタムフックアトリビュートも同様に動作する

## composer.json 依存パターン

Named hook アトリビュートを定義するコンポーネントは、Hook コンポーネントを `require-dev` に含めます:

```json
{
    "require-dev": {
        "wppack/hook": "^1.0"
    }
}
```

利用者が named hook アトリビュートを使用する場合は、各コンポーネントのドキュメントに従い明示的にインストールが必要です:

```bash
composer require wppack/hook
```

## 所有権マッピング一覧

各コンポーネントが所有する named hook の一覧です。詳細は [attributes.md](../../architecture/attributes.md) の Section 4 を参照してください。

| コンポーネント | Named Hook アトリビュート |
|--------------|------------------------|
| **Hook** | `InitAction`, `AdminInitAction`, `PluginsLoadedAction`, `AfterSetupThemeAction`, `WpLoadedAction` |
| **PostType** | `SavePostAction`, `DeletePostAction`, `TransitionPostStatusAction` |
| **Query** | `PreGetPostsAction`, `ParseQueryAction`, `PostsWhereFilter`, `PostsJoinFilter`, `PostsOrderbyFilter` 等 |
| **Admin** | `AdminMenuAction`, `AdminEnqueueScriptsAction`, `AdminNoticesAction`, `AdminHeadAction`, `CheckAdminRefererAction` 等 |
| **Theme** | `WpEnqueueScriptsAction`, `WpHeadAction`, `WpFooterAction`, `BodyClassFilter`, `PostClassFilter` 等 |
| **Templating** | `TheContentFilter`, `TheTitleFilter` |
| **REST** | `RestApiInitAction`, `RestAuthenticationErrorsFilter` 等 |
| **Widget** | `WidgetsInitAction`, `DynamicSidebarBeforeAction` 等 |
| **Media** | `UploadMimesFilter`, `WpHandleUploadFilter` 等 |
| **Mailer** | `WpMailFilter`, `WpMailFromFilter` 等 |
| **Ajax** | `WpAjaxAction`, `WpAjaxNoprivAction`, `CheckAjaxRefererAction` |
| **Option** | `PreOptionFilter`, `OptionFilter` 等 |
| **Transient** | `PreTransientFilter`, `TransientFilter` 等 |
| **Taxonomy** | `CreateTermAction`, `EditTermAction` 等 |
| **Block** | `EnqueueBlockEditorAssetsAction`, `RenderBlockFilter` 等 |
| **Security** | `WpLoginAction`, `AuthenticateFilter`, `DetermineCurrentUserFilter`, `CheckPasswordFilter` 等 |
| **Nonce** | `NonceLifeFilter`, `NonceUserLoggedOutFilter` |
| **Routing** | `RewriteRulesArrayFilter`, `TemplateRedirectAction`, `ParseRequestAction`, `QueryVarsFilter` 等 |
| **Scheduler** | `CronSchedulesFilter`, `ScheduledEventAction` 等 |
| **Database** | `QueryFilter`, `DbDeltaQueriesFilter` 等 |
| **Filesystem** | `UploadDirFilter`, `WpDeleteFileFilter` 等 |
| **Sanitizer** | `SanitizeTextFieldFilter`, `EscHtmlFilter` 等 |
| **HttpClient** | `PreHttpRequestFilter`, `HttpResponseFilter` 等 |
| **User** | `UserRegisterAction`, `ProfileUpdateAction` 等 |
| **Role** | `UserHasCapFilter`, `MapMetaCapFilter` 等 |
| **Plugin** | `ActivatedPluginAction`, `PluginActionLinksFilter` 等 |
| **NavigationMenu** | `WpNavMenuArgsFilter`, `NavMenuCssClassFilter` 等 |
| **Comment** | `PreCommentApprovedFilter`, `CommentPostAction`, `TransitionCommentStatusAction` 等 |
| **Feed** | `TheContentFeedFilter`, `RssChannelAction` 等 |
| **OEmbed** | `OembedProvidersFilter`, `OembedResultFilter` 等 |
| **SiteHealth** | `SiteHealthTestsFilter`, `SiteHealthCheckCompleteAction` 等 |
| **DashboardWidget** | `WpDashboardSetupAction`, `WpNetworkDashboardSetupAction` 等 |
| **Setting** | `SettingsPageAction` 等 |

> **Note:** 完全な一覧は [docs/architecture/attributes.md](../../architecture/attributes.md) の Section 4「Named Hook Attributes（コンポーネント別）」を参照してください。
