# Named Hook 連携規約

Named Hook アトリビュートは、WordPress フック名を文字列で指定する代わりに、型安全なクラスとして提供する仕組みです。本ドキュメントでは、各コンポーネントが named hook を定義する際の規約を定めます。

## Hook コンポーネントによる一元管理

すべての Named Hook アトリビュートは **Hook コンポーネントが一元管理** します。ライフサイクルフックもドメイン固有フックも、すべて Hook コンポーネントの名前空間内にコンポーネント別サブディレクトリとして配置されます。

### ライフサイクルフック（Hook 直下）

- `InitAction` (`init`)
- `AdminInitAction` (`admin_init`)
- `PluginsLoadedAction` (`plugins_loaded`)
- `AfterSetupThemeAction` (`after_setup_theme`)
- `WpLoadedAction` (`wp_loaded`)

加えて、汎用基底クラス `Action` / `Filter` を提供します。

### ドメイン固有フック（コンポーネント別サブディレクトリ）

ドメイン固有の WordPress フックは、Hook コンポーネント内のコンポーネント別サブディレクトリに配置されます:

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
WpPack\Component\Hook\Attribute\{Name}\Action\{HookName}Action
WpPack\Component\Hook\Attribute\{Name}\Filter\{HookName}Filter
```

ライフサイクルフック（Hook 直下）の場合:

```
WpPack\Component\Hook\Attribute\Action\{HookName}Action
```

### ディレクトリ構造

```
src/Component/Hook/
├── src/
│   ├── Attribute/
│   │   ├── Action.php              # 汎用基底クラス
│   │   ├── Filter.php              # 汎用基底クラス
│   │   ├── Action/                 # ライフサイクルフック（Hook 直下）
│   │   │   ├── InitAction.php
│   │   │   └── ...
│   │   ├── {Name}/                 # コンポーネント別サブディレクトリ
│   │   │   ├── Action/
│   │   │   │   └── {HookName}Action.php
│   │   │   └── Filter/
│   │   │       └── {HookName}Filter.php
│   │   ├── PostType/
│   │   │   └── Action/
│   │   │       └── SavePostAction.php
│   │   ├── Admin/
│   │   │   ├── Action/
│   │   │   └── Filter/
│   │   └── ...
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

namespace WpPack\Component\Hook\Attribute\PostType\Action;

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

namespace WpPack\Component\Hook\Attribute\Query\Filter;

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

すべての Named Hook アトリビュートは Hook コンポーネントに統合されているため、`wppack/hook` をインストールするだけでライフサイクルフック・ドメイン固有フックの両方が利用可能です:

```bash
composer require wppack/hook
```

## コンポーネント別 Named Hook 一覧

全ての Named Hook アトリビュートは `priority?: int = 10` パラメータを持ちます（Hook 基底クラスから継承）。

### Hook コンポーネント（ライフサイクル）

`Hook\Attribute\Action\` 名前空間で提供されるライフサイクルフック。

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[InitAction]` | - | `init` |
| `#[AdminInitAction]` | - | `admin_init` |
| `#[PluginsLoadedAction]` | - | `plugins_loaded` |
| `#[AfterSetupThemeAction]` | - | `after_setup_theme` |
| `#[WpLoadedAction]` | - | `wp_loaded` |

### PostType（`Hook\Attribute\PostType\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[SavePostAction]` | `postType?: string` | `save_post` / `save_post_{post_type}` |
| `#[DeletePostAction]` | - | `delete_post` |
| `#[TransitionPostStatusAction]` | - | `transition_post_status` |

### Templating（`Hook\Attribute\Templating\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[TheContentFilter]` | `the_content` |
| `#[TheTitleFilter]` | `the_title` |

### Admin（`Hook\Attribute\Admin\`）

#### アクション

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[AdminMenuAction]` | `admin_menu` |
| `#[NetworkAdminMenuAction]` | `network_admin_menu` |
| `#[UserAdminMenuAction]` | `user_admin_menu` |
| `#[CurrentScreenAction]` | `current_screen` |
| `#[AdminEnqueueScriptsAction]` | `admin_enqueue_scripts` |
| `#[AdminPrintStylesAction]` | `admin_print_styles` |
| `#[AdminPrintScriptsAction]` | `admin_print_scripts` |
| `#[AdminNoticesAction]` | `admin_notices` |
| `#[NetworkAdminNoticesAction]` | `network_admin_notices` |
| `#[UserAdminNoticesAction]` | `user_admin_notices` |
| `#[AllAdminNoticesAction]` | `all_admin_notices` |
| `#[AdminHeadAction]` | `admin_head` |
| `#[AdminFooterAction]` | `admin_footer` |
| `#[AdminPrintFooterScriptsAction]` | `admin_print_footer_scripts` |
| `#[ManagePostsCustomColumnAction]` | `manage_posts_custom_column` |
| `#[AdminBarMenuAction]` | `admin_bar_menu` |
| `#[WpBeforeAdminBarRenderAction]` | `wp_before_admin_bar_render` |
| `#[CheckAdminRefererAction]` | `check_admin_referer` |

#### フィルター

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[AdminBodyClassFilter]` | `admin_body_class` |
| `#[AdminFooterTextFilter]` | `admin_footer_text` |
| `#[AdminTitleFilter]` | `admin_title` |
| `#[ManagePostsColumnsFilter]` | `manage_posts_columns` |
| `#[ManagePagesColumnsFilter]` | `manage_pages_columns` |
| `#[ManageUsersColumnsFilter]` | `manage_users_columns` |

### Ajax（`Hook\Attribute\Ajax\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[WpAjaxAction]` | `action: string` | `wp_ajax_{action}` |
| `#[WpAjaxNoprivAction]` | `action: string` | `wp_ajax_nopriv_{action}` |
| `#[CheckAjaxRefererAction]` | - | `check_ajax_referer` |

### Option（`Hook\Attribute\Option\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[PreOptionFilter]` | `optionName: string` | `pre_option_{option}` |
| `#[OptionFilter]` | `optionName: string` | `option_{option}` |
| `#[DefaultOptionFilter]` | `optionName: string` | `default_option_{option}` |
| `#[PreUpdateOptionFilter]` | `optionName: string` | `pre_update_option_{option}` |
| `#[UpdateOptionAction]` | `optionName: string` | `update_option_{option}` |
| `#[AddOptionAction]` | `optionName: string` | `add_option_{option}` |
| `#[DeleteOptionAction]` | `optionName: string` | `delete_option_{option}` |
| `#[PreSiteOptionFilter]` | `optionName: string` | `pre_site_option_{option}` |
| `#[SiteOptionFilter]` | `optionName: string` | `site_option_{option}` |
| `#[UpdateSiteOptionAction]` | `optionName: string` | `update_site_option_{option}` |

### Transient（`Hook\Attribute\Transient\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[PreTransientFilter]` | `name: string` | `pre_transient_{transient}` |
| `#[TransientFilter]` | `name: string` | `transient_{transient}` |
| `#[PreSetTransientFilter]` | `name: string` | `pre_set_transient_{transient}` |
| `#[TransientTimeoutFilter]` | `name: string` | `expiration_of_transient_{transient}` |
| `#[SetTransientAction]` | `name: string` | `set_transient_{transient}` |
| `#[DeletedTransientAction]` | - | `deleted_transient` |
| `#[PreSiteTransientFilter]` | `name: string` | `pre_site_transient_{transient}` |
| `#[SiteTransientFilter]` | `name: string` | `site_transient_{transient}` |
| `#[SetSiteTransientAction]` | `name: string` | `set_site_transient_{transient}` |

### Taxonomy（`Hook\Attribute\Taxonomy\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[RegisteredTaxonomyAction]` | - | `registered_taxonomy` |
| `#[CreateTermAction]` | `taxonomy: string` | `create_{taxonomy}` |
| `#[EditTermAction]` | `taxonomy: string` | `edit_{taxonomy}` |
| `#[DeleteTermAction]` | `taxonomy: string` | `delete_{taxonomy}` |
| `#[PreGetTermsAction]` | - | `pre_get_terms` |
| `#[TermsClausesFilter]` | - | `terms_clauses` |
| `#[TermLinkFilter]` | - | `term_link` |
| `#[GetTermsFilter]` | - | `get_terms` |

### Block（`Hook\Attribute\Block\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[RegisterBlockTypeArgsFilter]` | `register_block_type_args` |
| `#[EnqueueBlockEditorAssetsAction]` | `enqueue_block_editor_assets` |
| `#[EnqueueBlockAssetsAction]` | `enqueue_block_assets` |
| `#[BlockCategoriesAllFilter]` | `block_categories_all` |
| `#[BlockEditorSettingsAllFilter]` | `block_editor_settings_all` |
| `#[RenderBlockFilter]` | `render_block` |
| `#[PreRenderBlockFilter]` | `pre_render_block` |
| `#[RenderBlockDataFilter]` | `render_block_data` |
| `#[BlockTypeMetadataFilter]` | `block_type_metadata` |
| `#[BlockTypeMetadataSettingsFilter]` | `block_type_metadata_settings` |
| `#[RestPreInsertBlockFilter]` | `rest_pre_insert_block` |
| `#[RestPrepareBlockFilter]` | `rest_prepare_block` |

### Widget（`Hook\Attribute\Widget\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[WidgetsInitAction]` | `widgets_init` |
| `#[DynamicSidebarBeforeAction]` | `dynamic_sidebar_before` |
| `#[DynamicSidebarAfterAction]` | `dynamic_sidebar_after` |
| `#[DynamicSidebarParamsFilter]` | `dynamic_sidebar_params` |
| `#[DynamicSidebarHasWidgetsFilter]` | `dynamic_sidebar_has_widgets` |
| `#[WidgetUpdateCallbackFilter]` | `widget_update_callback` |
| `#[WidgetFormCallbackFilter]` | `widget_form_callback` |
| `#[WidgetDisplayCallbackFilter]` | `widget_display_callback` |
| `#[WidgetTitleFilter]` | `widget_title` |
| `#[WidgetTextFilter]` | `widget_text` |
| `#[WidgetContentFilter]` | `widget_content` |
| `#[WidgetsPrefetchingFilter]` | `widgets_prefetching` |
| `#[WidgetAreaPreviewFilter]` | `widget_area_preview` |
| `#[RegisterSidebarFilter]` | `register_sidebar` |

### Query（`Hook\Attribute\Query\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[PreGetPostsAction]` | `pre_get_posts` |
| `#[ParseQueryAction]` | `parse_query` |
| `#[PostsWhereFilter]` | `posts_where` |
| `#[PostsJoinFilter]` | `posts_join` |
| `#[PostsOrderbyFilter]` | `posts_orderby` |
| `#[PostsFieldsFilter]` | `posts_fields` |
| `#[PostsGroupbyFilter]` | `posts_groupby` |
| `#[PostsDistinctFilter]` | `posts_distinct` |
| `#[ThePostsFilter]` | `the_posts` |
| `#[PostsResultsFilter]` | `posts_results` |
| `#[FoundPostsFilter]` | `found_posts` |
| `#[FoundPostsQueryFilter]` | `found_posts_query` |
| `#[PostsRequestFilter]` | `posts_request` |
| `#[PostsClausesFilter]` | `posts_clauses` |
| `#[PostsWherePagedFilter]` | `posts_where_paged` |
| `#[PostsSearchFilter]` | `posts_search` |
| `#[PostsSearchOrderbyFilter]` | `posts_search_orderby` |
| `#[PostsSearchColumnsFilter]` | `posts_search_columns` |
| `#[PostsCacheResultsFilter]` | `posts_cache_results` |
| `#[UpdatePostMetaCacheFilter]` | `update_post_meta_cache` |
| `#[UpdatePostTermCacheFilter]` | `update_post_term_cache` |

### Security（`Hook\Attribute\Security\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[WpLoginAction]` | `wp_login` |
| `#[WpLoginFailedAction]` | `wp_login_failed` |
| `#[AuthenticateFilter]` | `authenticate` |
| `#[WpLogoutAction]` | `wp_logout` |
| `#[DetermineCurrentUserFilter]` | `determine_current_user` |
| `#[PasswordResetAction]` | `password_reset` |
| `#[RetrievePasswordAction]` | `retrieve_password` |
| `#[CheckPasswordFilter]` | `check_password` |

### Nonce（`Hook\Attribute\Nonce\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[NonceUserLoggedOutFilter]` | `nonce_user_logged_out` |
| `#[NonceLifeFilter]` | `nonce_life` |

### Rest（`Hook\Attribute\Rest\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[RestApiInitAction]` | `rest_api_init` |
| `#[RestAuthenticationErrorsFilter]` | `rest_authentication_errors` |
| `#[RestPreparePostFilter]` | `rest_prepare_post` |
| `#[RestPreServeRequestFilter]` | `rest_pre_serve_request` |
| `#[RestPreDispatchFilter]` | `rest_pre_dispatch` |
| `#[RestRequestAfterCallbacksFilter]` | `rest_request_after_callbacks` |

### Routing（`Hook\Attribute\Routing\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[RewriteRulesArrayFilter]` | `rewrite_rules_array` |
| `#[RootRewriteRulesFilter]` | `root_rewrite_rules` |
| `#[PostRewriteRulesFilter]` | `post_rewrite_rules` |
| `#[PageRewriteRulesFilter]` | `page_rewrite_rules` |
| `#[QueryVarsFilter]` | `query_vars` |
| `#[RequestFilter]` | `request` |
| `#[ParseRequestAction]` | `parse_request` |
| `#[TemplateRedirectAction]` | `template_redirect` |
| `#[TemplateIncludeFilter]` | `template_include` |

### Scheduler（`Hook\Attribute\Scheduler\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[CronSchedulesFilter]` | - | `cron_schedules` |
| `#[ScheduledEventAction]` | `event: string` | `{event}` |
| `#[PreScheduleEventFilter]` | - | `pre_schedule_event` |
| `#[PreUnscheduleEventFilter]` | - | `pre_unschedule_event` |
| `#[WpCronAction]` | - | `wp_cron` |
| `#[PreDoEventFilter]` | - | `pre_do_event` |
| `#[ScheduleEventFilter]` | - | `schedule_event` |
| `#[GetScheduleFilter]` | - | `get_schedule` |

### Database（`Hook\Attribute\Database\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[QueryFilter]` | `query` |
| `#[DbprepareFilter]` | `dbprepare` |
| `#[WpUpgradeAction]` | `wp_upgrade` |
| `#[DbDeltaQueriesFilter]` | `dbdelta_queries` |
| `#[DbDeltaCreateQueriesFilter]` | `dbdelta_create_queries` |
| `#[DbDeltaInsertQueriesFilter]` | `dbdelta_insert_queries` |

### Mailer（`Hook\Attribute\Mailer\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[WpMailFromFilter]` | `wp_mail_from` |
| `#[WpMailFromNameFilter]` | `wp_mail_from_name` |
| `#[WpMailContentTypeFilter]` | `wp_mail_content_type` |
| `#[WpMailCharsetFilter]` | `wp_mail_charset` |
| `#[WpMailFilter]` | `wp_mail` |
| `#[PreWpMailFilter]` | `pre_wp_mail` |
| `#[WpMailSucceededAction]` | `wp_mail_succeeded` |
| `#[WpMailFailedAction]` | `wp_mail_failed` |
| `#[PhpMailerInitAction]` | `phpmailer_init` |

### HttpClient（`Hook\Attribute\HttpClient\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[PreHttpRequestFilter]` | `pre_http_request` |
| `#[HttpRequestArgsFilter]` | `http_request_args` |
| `#[HttpRequestTimeoutFilter]` | `http_request_timeout` |
| `#[HttpRequestRedirectCountFilter]` | `http_request_redirect_count` |
| `#[HttpResponseFilter]` | `http_response` |
| `#[HttpApiDebugAction]` | `http_api_debug` |
| `#[HttpApiTransportsFilter]` | `http_api_transports` |
| `#[HttpApiCurlFilter]` | `http_api_curl` |
| `#[HttpLocalRequestFilter]` | `http_local_request` |
| `#[HttpRequestHostIsExternalFilter]` | `http_request_host_is_external` |
| `#[HttpsSslVerifyFilter]` | `https_ssl_verify` |
| `#[HttpsLocalSslVerifyFilter]` | `https_local_ssl_verify` |

### Filesystem（`Hook\Attribute\Filesystem\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[FilesystemMethodFilter]` | `filesystem_method` |
| `#[FilesystemMethodFileFilter]` | `filesystem_method_file` |
| `#[UploadDirFilter]` | `upload_dir` |
| `#[WpUniqueFilenameFilter]` | `wp_unique_filename` |
| `#[WpHandleSideloadPrefilterFilter]` | `wp_handle_sideload_prefilter` |
| `#[WpMkdirModeFilter]` | `wp_mkdir_mode` |
| `#[WpFilesystemInitAction]` | `wp_filesystem_init` |
| `#[WpDeleteFileFilter]` | `wp_delete_file` |
| `#[FileIsDisplayableImageFilter]` | `file_is_displayable_image` |
| `#[WpUploadBitsFilter]` | `wp_upload_bits` |
| `#[LoadImageToEditPathFilter]` | `load_image_to_edit_path` |
| `#[PreWpUniqueFilenameFileListFilter]` | `pre_wp_unique_filename_file_list` |

### Sanitizer（`Hook\Attribute\Sanitizer\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[SanitizePostMetaFilter]` | `metaKey: string` | `sanitize_post_meta_{$meta_key}` |
| `#[SanitizeCommentMetaFilter]` | `metaKey: string` | `sanitize_comment_meta_{$meta_key}` |
| `#[SanitizeTermMetaFilter]` | `metaKey: string` | `sanitize_term_meta_{$meta_key}` |
| `#[SanitizeUserMetaFilter]` | `metaKey: string` | `sanitize_user_meta_{$meta_key}` |
| `#[SanitizeTextFieldFilter]` | - | `sanitize_text_field` |
| `#[SanitizeTitleFilter]` | - | `sanitize_title` |
| `#[SanitizeFileNameFilter]` | - | `sanitize_file_name` |
| `#[SanitizeEmailFilter]` | - | `sanitize_email` |
| `#[SanitizeKeyFilter]` | - | `sanitize_key` |
| `#[PreInsertTermFilter]` | - | `pre_insert_term` |
| `#[PreUserLoginFilter]` | - | `pre_user_login` |

### Escaper（`Hook\Attribute\Escaper\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[EscHtmlFilter]` | `esc_html` |
| `#[EscAttrFilter]` | `esc_attr` |
| `#[EscUrlFilter]` | `esc_url` |
| `#[EscJsFilter]` | `esc_js` |

### Media（`Hook\Attribute\Media\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[DeleteAttachmentAction]` | `delete_attachment` |
| `#[WpHandleUploadFilter]` | `wp_handle_upload` |
| `#[WpHandleUploadPrefilterFilter]` | `wp_handle_upload_prefilter` |
| `#[UploadMimesFilter]` | `upload_mimes` |
| `#[WpGenerateAttachmentMetadataFilter]` | `wp_generate_attachment_metadata` |
| `#[IntermediateSizesAdvancedFilter]` | `intermediate_image_sizes_advanced` |
| `#[WpImageEditorsFilter]` | `wp_image_editors` |
| `#[AjaxQueryAttachmentsArgsFilter]` | `ajax_query_attachments_args` |
| `#[MediaUploadTabsFilter]` | `media_upload_tabs` |
| `#[WpGetAttachmentImageAttributesFilter]` | `wp_get_attachment_image_attributes` |
| `#[GetAttachedFileFilter]` | `get_attached_file` |
| `#[WpGetAttachmentUrlFilter]` | `wp_get_attachment_url` |
| `#[WpReadImageMetadataFilter]` | `wp_read_image_metadata` |
| `#[WpResourceHintsFilter]` | `wp_resource_hints` |

### User（`Hook\Attribute\User\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[UserRegisterAction]` | `user_register` |
| `#[RegistrationErrorsFilter]` | `registration_errors` |
| `#[ProfileUpdateAction]` | `profile_update` |
| `#[ShowUserProfileAction]` | `show_user_profile` |
| `#[EditUserProfileAction]` | `edit_user_profile` |
| `#[PersonalOptionsUpdateAction]` | `personal_options_update` |
| `#[EditUserProfileUpdateAction]` | `edit_user_profile_update` |
| `#[DeleteUserAction]` | `delete_user` |
| `#[DeletedUserAction]` | `deleted_user` |
| `#[RemoveUserFromBlogAction]` | `remove_user_from_blog` |

### Role（`Hook\Attribute\Role\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[UserHasCapFilter]` | `user_has_cap` |
| `#[MapMetaCapFilter]` | `map_meta_cap` |
| `#[SetUserRoleAction]` | `set_user_role` |
| `#[GrantSuperAdminAction]` | `grant_super_admin` |
| `#[RevokeSuperAdminAction]` | `revoke_super_admin` |

### Plugin（`Hook\Attribute\Plugin\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[NetworkPluginsLoadedAction]` | - | `network_plugins_loaded` |
| `#[MuPluginsLoadedAction]` | - | `muplugins_loaded` |
| `#[ActivatedPluginAction]` | - | `activated_plugin` |
| `#[DeactivatedPluginAction]` | - | `deactivated_plugin` |
| `#[PluginActionLinksFilter]` | `plugin: string` | `plugin_action_links_{plugin}` |
| `#[PluginRowMetaFilter]` | - | `plugin_row_meta` |
| `#[NetworkPluginActionLinksFilter]` | `plugin: string` | `network_admin_plugin_action_links_{plugin}` |
| `#[UpgraderProcessCompleteAction]` | - | `upgrader_process_complete` |
| `#[PreSetSiteTransientUpdatePluginsFilter]` | - | `pre_set_site_transient_update_plugins` |
| `#[PluginsApiFilter]` | - | `plugins_api` |
| `#[PluginLoadedAction]` | - | `plugin_loaded` |
| `#[AfterPluginRowAction]` | - | `after_plugin_row` |

### Theme（`Hook\Attribute\Theme\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[WpEnqueueScriptsAction]` | `wp_enqueue_scripts` |
| `#[WpPrintStylesAction]` | `wp_print_styles` |
| `#[WpPrintScriptsAction]` | `wp_print_scripts` |
| `#[WpHeadAction]` | `wp_head` |
| `#[WpFooterAction]` | `wp_footer` |
| `#[WpBodyOpenAction]` | `wp_body_open` |
| `#[CustomizeRegisterAction]` | `customize_register` |
| `#[CustomizePreviewInitAction]` | `customize_preview_init` |
| `#[BodyClassFilter]` | `body_class` |
| `#[PostClassFilter]` | `post_class` |
| `#[ScriptLoaderTagFilter]` | `script_loader_tag` |
| `#[StyleLoaderTagFilter]` | `style_loader_tag` |

### NavigationMenu（`Hook\Attribute\NavigationMenu\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[WpNavMenuArgsFilter]` | `wp_nav_menu_args` |
| `#[WpNavMenuItemsFilter]` | `wp_nav_menu_items` |
| `#[WpNavMenuObjectsFilter]` | `wp_nav_menu_objects` |
| `#[PreWpNavMenuFilter]` | `pre_wp_nav_menu` |
| `#[WpNavMenuItemCustomFieldsAction]` | `wp_nav_menu_item_custom_fields` |
| `#[WpUpdateNavMenuItemAction]` | `wp_update_nav_menu_item` |
| `#[NavMenuCssClassFilter]` | `nav_menu_css_class` |
| `#[WpNavMenuLinkAttributesFilter]` | `nav_menu_link_attributes` |

### Feed（`Hook\Attribute\Feed\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[TheContentFeedFilter]` | `the_content_feed` |
| `#[TheExcerptRssFilter]` | `the_excerpt_rss` |
| `#[TheTitleRssFilter]` | `the_title_rss` |
| `#[RssChannelAction]` | `rss_channel` |
| `#[RssItemAction]` | `rss_item` |
| `#[Rss2HeadAction]` | `rss2_head` |
| `#[Rss2ItemAction]` | `rss2_item` |
| `#[AtomHeadAction]` | `atom_head` |
| `#[FeedContentTypeFilter]` | `feed_content_type` |
| `#[FeedLinksExtraFilter]` | `feed_links_extra` |
| `#[CommentFeedRssAction]` | `comment_feed_rss` |
| `#[AtomEntryAction]` | `atom_entry` |
| `#[AtomNsFilter]` | `atom_ns` |
| `#[RssEnclosureFilter]` | `rss_enclosure` |
| `#[Rss2NsFilter]` | `rss2_ns` |
| `#[RssTagFilter]` | `rss_tag` |

### OEmbed（`Hook\Attribute\OEmbed\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[OembedProvidersFilter]` | `oembed_providers` |
| `#[OembedWhitelistFilter]` | `oembed_whitelist` |
| `#[OembedFetchUrlFilter]` | `oembed_fetch_url` |
| `#[PreOembedResultFilter]` | `pre_oembed_result` |
| `#[OembedTtlFilter]` | `oembed_ttl` |
| `#[OembedResultFilter]` | `oembed_result` |
| `#[EmbedOembedHtmlFilter]` | `embed_oembed_html` |
| `#[OembedDataparseFilter]` | `oembed_dataparse` |
| `#[OembedDiscoveryLinksFilter]` | `oembed_discovery_links` |
| `#[OembedResponseDataFilter]` | `oembed_response_data` |
| `#[EmbedDefaultsFilter]` | `embed_defaults` |
| `#[EmbedHandlersFilter]` | `embed_handlers` |

### SiteHealth（`Hook\Attribute\SiteHealth\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[SiteHealthStatusFilter]` | `site_health_status` |
| `#[SiteHealthTestsFilter]` | `site_health_tests` |
| `#[SiteHealthNavigationTabsFilter]` | `site_health_navigation_tabs` |
| `#[SiteHealthDebugInfoFilter]` | `site_health_debug_info` |
| `#[PreSiteHealthCheckFilter]` | `pre_site_health_check` |
| `#[SiteHealthCheckCompleteAction]` | `site_health_check_complete` |
| `#[SiteHealthPhpVersionTestFilter]` | `site_health_php_version_test` |
| `#[SiteHealthSqlServerTestFilter]` | `site_health_sql_server_test` |
| `#[SiteHealthHttpsStatusTestFilter]` | `site_health_https_status_test` |
| `#[SiteHealthCronScheduleFilter]` | `site_health_cron_schedule` |
| `#[SiteHealthScheduledCheckAction]` | `site_health_scheduled_check` |

### DashboardWidget（`Hook\Attribute\DashboardWidget\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[WpDashboardSetupAction]` | - | `wp_dashboard_setup` |
| `#[WpNetworkDashboardSetupAction]` | - | `wp_network_dashboard_setup` |
| `#[UpdateUserOptionAction]` | `option: string` | `update_user_option` |
| `#[ActivityBoxEndAction]` | - | `activity_box_end` |
| `#[DashboardGlanceItemsFilter]` | - | `dashboard_glance_items` |

### Setting（`Hook\Attribute\Setting\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[SettingsPageAction]` | `page: string` | `settings_page_{page}` |
| `#[SettingsErrorsAction]` | - | `settings_errors` |

### Comment（`Hook\Attribute\Comment\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[PreCommentApprovedFilter]` | `pre_comment_approved` |
| `#[CommentPostAction]` | `comment_post` |
| `#[EditCommentAction]` | `edit_comment` |
| `#[DeleteCommentAction]` | `delete_comment` |
| `#[TransitionCommentStatusAction]` | `transition_comment_status` |
| `#[WpInsertCommentAction]` | `wp_insert_comment` |

### Shortcode（`Hook\Attribute\Shortcode\`）

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[ShortcodeAttsFilter]` | `shortcode: string` | `shortcode_atts_{shortcode}` |
| `#[DoShortcodeTagFilter]` | - | `do_shortcode_tag` |
| `#[PreDoShortcodeTagFilter]` | - | `pre_do_shortcode_tag` |
| `#[NoTexturizeShortcodesFilter]` | - | `no_texturize_shortcodes` |
| `#[StripShortcodesTagNamesFilter]` | - | `strip_shortcodes_tag_names` |

### Translation（`Hook\Attribute\Translation\`）

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[LoadTextDomainAction]` | `load_textdomain` |
| `#[LoadedTextDomainAction]` | `loaded_textdomain` |
| `#[GettextFilter]` | `gettext` |
| `#[GettextWithContextFilter]` | `gettext_with_context` |
| `#[NgetTextFilter]` | `ngettext` |
