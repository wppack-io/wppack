# Attribute 一覧

WpPack の全コンポーネントで使用される PHP Attribute の包括的なカタログです。

---

## 目次

1. [サービス登録 Attributes](#1-サービス登録-attributes)
2. [設定・DI Attributes](#2-設定di-attributes)
3. [Hook Attributes（汎用）](#3-hook-attributes汎用)
4. [Named Hook Attributes（コンポーネント別）](#4-named-hook-attributesコンポーネント別)
5. [条件 Attributes](#5-条件-attributes)
6. [データ定義 Attributes](#6-データ定義-attributes)
7. [バリデーション Attributes](#7-バリデーション-attributes)
8. [セキュリティ Attributes](#8-セキュリティ-attributes)
9. [ルーティング Attributes](#9-ルーティング-attributes)
10. [パフォーマンス Attributes](#10-パフォーマンス-attributes)
11. [CLI Attributes](#11-cli-attributes)
12. [ショートコード Attributes](#12-ショートコード-attributes)
13. [プラグイン Attributes](#13-プラグイン-attributes)
14. [テーマ Attributes](#14-テーマ-attributes)

---

## 1. サービス登録 Attributes

コンポーネントやサービスをDIコンテナに登録するための Attribute。

| Attribute | パラメータ | 提供元 | 説明 |
|-----------|-----------|--------|------|
| `#[AsService]` | `public?: bool`, `lazy?: bool`, `tags?: array` | DependencyInjection | サービスをDIコンテナに登録 |
| `#[AsAlias]` | `class: string` | DependencyInjection | インターフェースを実装クラスにバインド |
| `#[AsHookSubscriber]` | _(なし)_ | Hook | WordPress フック購読クラスとして登録 |
| `#[AsEventListener]` | `priority?: int` | EventDispatcher | PSR-14 イベントリスナーとして登録 |
| `#[AsEventSubscriber]` | _(なし)_ | EventDispatcher | PSR-14 イベントサブスクライバーとして登録 |
| `#[AsMessageHandler]` | _(なし)_ | Messenger | メッセージハンドラーとして登録。クラスレベル: 単一メッセージ + `__invoke()`、メソッドレベル: 複数メッセージ処理 |
| `#[AsSchedule]` | _(なし)_ | Scheduler | スケジュールプロバイダーとして登録 |
| `#[AsHealthCheck]` | `id: string`, `label: string`, `category?: string`, `priority?: int` | SiteHealth | サイトヘルスチェックとして登録。WordPress Site Health API にマップ |
| `#[AsDashboardWidget]` | `id: string`, `title: string`, `icon?: string`, `columns?: string`, `priority?: string`, `hasControls?: bool`, `stateful?: bool`, `height?: string` | DashboardWidget | ダッシュボードウィジェットとして登録。`wp_add_dashboard_widget()` にマップ |

---

## 2. 設定・DI Attributes

設定値の注入やコンフィグレーションクラスの定義に使用。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[AsConfig]` | `prefix: string` | Config | 設定クラスのマーカー | - |
| `#[Env]` | `name: string` | Config | 環境変数から値を取得 | `$_ENV` / `getenv()` |
| `#[Option]` | `key: string` | Config | WordPress オプションから値を取得 | `get_option()` |
| `#[Constant]` | `name: string` | Config | PHP 定数から値を取得 | `defined()` / `constant()` |
| `#[Autowire]` | `env?: string`, `param?: string` | DependencyInjection | コンストラクタパラメータの自動注入 | - |

---

## 3. Hook Attributes（汎用）

任意の WordPress フックに対応する汎用 Attribute。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[Action]` | `hook: string`, `priority?: int = 10` | Hook | 任意のアクションフックを購読 | `add_action()` |
| `#[Filter]` | `hook: string`, `priority?: int = 10` | Hook | 任意のフィルターフックを購読 | `add_filter()` |
| `#[EventListener]` | `event: string`, `priority?: int = 10` | EventDispatcher | PSR-14 イベントをリッスン | - |

---

## 4. Named Hook Attributes（コンポーネント別）

WordPress のフックに直接対応する名前付き Attribute。全て `priority?: int = 10` パラメータを持つ（Hook 基底クラスから継承）。

### Hook コンポーネント

#### アクション

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[InitAction]` | - | `init` |
| `#[AdminInitAction]` | - | `admin_init` |
| `#[AdminMenuAction]` | - | `admin_menu` |
| `#[SavePostAction]` | `postType?: string` | `save_post` / `save_post_{post_type}` |
| `#[DeletePostAction]` | - | `delete_post` |
| `#[TransitionPostStatusAction]` | - | `transition_post_status` |
| `#[PreGetPostsAction]` | - | `pre_get_posts` |
| `#[WpEnqueueScriptsAction]` | `condition?: string` | `wp_enqueue_scripts` |
| `#[AdminEnqueueScriptsAction]` | - | `admin_enqueue_scripts` |
| `#[RestApiInitAction]` | - | `rest_api_init` |
| `#[WidgetsInitAction]` | - | `widgets_init` |
| `#[WpHeadAction]` | - | `wp_head` |
| `#[WpFooterAction]` | - | `wp_footer` |
| `#[PluginsLoadedAction]` | - | `plugins_loaded` |
| `#[AfterSetupThemeAction]` | - | `after_setup_theme` |

#### AJAX アクション

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[WpAjaxAction]` | `action: string` | `wp_ajax_{action}` |
| `#[WpAjaxNoprivAction]` | `action: string` | `wp_ajax_nopriv_{action}` |

#### フィルター

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[TheContentFilter]` | - | `the_content` |
| `#[TheTitleFilter]` | - | `the_title` |
| `#[BodyClassFilter]` | - | `body_class` |
| `#[UploadMimesFilter]` | - | `upload_mimes` |
| `#[WpMailFilter]` | - | `wp_mail` |
| `#[PostsWhereFilter]` | - | `posts_where` |

### Admin コンポーネント

#### アクション

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[AdminMenuAction]` | `admin_menu` |
| `#[NetworkAdminMenuAction]` | `network_admin_menu` |
| `#[UserAdminMenuAction]` | `user_admin_menu` |
| `#[AdminInitAction]` | `admin_init` |
| `#[CurrentScreenAction]` | `current_screen` |
| `#[AdminEnqueueScriptsAction]` | `admin_enqueue_scripts` |
| `#[AdminPrintStylesAction]` | `admin_print_styles` |
| `#[AdminPrintScriptsAction]` | `admin_print_scripts` |
| `#[AdminNoticesAction]` | `admin_notices` |
| `#[NetworkAdminNoticesAction]` | `network_admin_notices` |
| `#[UserAdminNoticesAction]` | `user_admin_notices` |
| `#[AllAdminNoticesAction]` | `all_admin_notices` |
| `#[WpDashboardSetupAction]` | `wp_dashboard_setup` |
| `#[WpNetworkDashboardSetupAction]` | `wp_network_dashboard_setup` |
| `#[AdminHeadAction]` | `admin_head` |
| `#[AdminFooterAction]` | `admin_footer` |
| `#[AdminPrintFooterScriptsAction]` | `admin_print_footer_scripts` |
| `#[ManagePostsCustomColumnAction]` | `manage_posts_custom_column` |
| `#[AdminBarMenuAction]` | `admin_bar_menu` |
| `#[WpBeforeAdminBarRenderAction]` | `wp_before_admin_bar_render` |

#### フィルター

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[ManagePostsColumnsFilter]` | `manage_posts_columns` |
| `#[ManagePagesColumnsFilter]` | `manage_pages_columns` |
| `#[ManageUsersColumnsFilter]` | `manage_users_columns` |

### Option コンポーネント

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

### Transient コンポーネント

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

### Taxonomy コンポーネント

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

### Block コンポーネント

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

### Widget コンポーネント

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

### Query コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[PreGetPostsAction]` | `pre_get_posts` |
| `#[ParseQueryAction]` | `parse_query` |
| `#[ParseRequestAction]` | `parse_request` |
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
| `#[QueryVarsFilter]` | `query_vars` |

### Security コンポーネント

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
| `#[UserHasCapFilter]` | `user_has_cap` |
| `#[MapMetaCapFilter]` | `map_meta_cap` |
| `#[CheckAdminRefererAction]` | `check_admin_referer` |
| `#[CheckAjaxRefererAction]` | `check_ajax_referer` |

### Nonce コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[CheckAdminRefererAction]` | `check_admin_referer` |
| `#[CheckAjaxRefererAction]` | `check_ajax_referer` |
| `#[NonceUserLoggedOutFilter]` | `nonce_user_logged_out` |
| `#[NonceLifeFilter]` | `nonce_life` |

### REST コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[RestApiInitAction]` | `rest_api_init` |
| `#[RestAuthenticationErrorsFilter]` | `rest_authentication_errors` |
| `#[DetermineCurrentUserFilter]` | `determine_current_user` |
| `#[RestPreparePostFilter]` | `rest_prepare_post` |
| `#[RestPreServeRequestFilter]` | `rest_pre_serve_request` |
| `#[RestPreDispatchFilter]` | `rest_pre_dispatch` |
| `#[RestRequestAfterCallbacksFilter]` | `rest_request_after_callbacks` |

### Routing コンポーネント

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

### Scheduler コンポーネント

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

### Database コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[QueryFilter]` | `query` |
| `#[DbprepareFilter]` | `dbprepare` |
| `#[WpUpgradeAction]` | `wp_upgrade` |
| `#[DbDeltaQueriesFilter]` | `dbdelta_queries` |
| `#[DbDeltaCreateQueriesFilter]` | `dbdelta_create_queries` |
| `#[DbDeltaInsertQueriesFilter]` | `dbdelta_insert_queries` |

### Mailer コンポーネント

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

### HttpClient コンポーネント

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

### Filesystem コンポーネント

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

### Sanitizer コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[SanitizePostMetaFilter]` | `sanitize_post_meta` |
| `#[SanitizeUserMetaFilter]` | `sanitize_user_meta` |
| `#[SanitizeTermMetaFilter]` | `sanitize_term_meta` |
| `#[SanitizeCommentMetaFilter]` | `sanitize_comment_meta` |
| `#[SanitizeTextFieldFilter]` | `sanitize_text_field` |
| `#[SanitizeTitleFilter]` | `sanitize_title` |
| `#[SanitizeFileNameFilter]` | `sanitize_file_name` |
| `#[SanitizeEmailFilter]` | `sanitize_email` |
| `#[SanitizeKeyFilter]` | `sanitize_key` |
| `#[EscHtmlFilter]` | `esc_html` |
| `#[EscAttrFilter]` | `esc_attr` |
| `#[EscUrlFilter]` | `esc_url` |
| `#[EscJsFilter]` | `esc_js` |
| `#[PreCommentApprovedFilter]` | `pre_comment_approved` |
| `#[PreInsertTermFilter]` | `pre_insert_term` |
| `#[PreUserLoginFilter]` | `pre_user_login` |

### Media コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[WpHandleUploadFilter]` | `wp_handle_upload` |
| `#[WpHandleUploadPrefilterFilter]` | `wp_handle_upload_prefilter` |
| `#[UploadMimesFilter]` | `upload_mimes` |
| `#[WpGenerateAttachmentMetadataFilter]` | `wp_generate_attachment_metadata` |
| `#[IntermediateSizesAdvancedFilter]` | `intermediate_image_sizes_advanced` |
| `#[WpImageEditorsFilter]` | `wp_image_editors` |
| `#[AjaxQueryAttachmentsArgsFilter]` | `ajax_query_attachments_args` |
| `#[MediaUploadTabsFilter]` | `media_upload_tabs` |
| `#[WpGetAttachmentImageAttributesFilter]` | `wp_get_attachment_image_attributes` |

### User コンポーネント

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

### Role コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[UserHasCapFilter]` | `user_has_cap` |
| `#[MapMetaCapFilter]` | `map_meta_cap` |
| `#[SetUserRoleAction]` | `set_user_role` |
| `#[GrantSuperAdminAction]` | `grant_super_admin` |
| `#[RevokeSuperAdminAction]` | `revoke_super_admin` |

### Plugin コンポーネント

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[PluginsLoadedAction]` | - | `plugins_loaded` |
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

### Theme コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[AfterSetupThemeAction]` | `after_setup_theme` |
| `#[WpEnqueueScriptsAction]` | `wp_enqueue_scripts` |
| `#[WpPrintStylesAction]` | `wp_print_styles` |
| `#[WpPrintScriptsAction]` | `wp_print_scripts` |
| `#[WpHeadAction]` | `wp_head` |
| `#[WpFooterAction]` | `wp_footer` |
| `#[WpBodyOpenAction]` | `wp_body_open` |
| `#[TemplateRedirectAction]` | `template_redirect` |
| `#[CustomizeRegisterAction]` | `customize_register` |
| `#[CustomizePreviewInitAction]` | `customize_preview_init` |
| `#[BodyClassFilter]` | `body_class` |
| `#[PostClassFilter]` | `post_class` |
| `#[ScriptLoaderTagFilter]` | `script_loader_tag` |
| `#[StyleLoaderTagFilter]` | `style_loader_tag` |

### NavigationMenu コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[WpNavMenuArgsFilter]` | `wp_nav_menu_args` |
| `#[WpNavMenuItemsFilter]` | `wp_nav_menu_items` |
| `#[WpNavMenuObjectsFilter]` | `wp_nav_menu_objects` |
| `#[PreWpNavMenuFilter]` | `pre_wp_nav_menu` |
| `#[WpNavMenuItemCustomFieldsAction]` | `wp_nav_menu_item_custom_fields` |
| `#[WpUpdateNavMenuItemAction]` | `wp_update_nav_menu_item` |
| `#[NavMenuCssClassFilter]` | `nav_menu_css_class` |

### Feed コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[TheContentFeedFilter]` | `the_content_feed` |
| `#[TheExcerptRssFilter]` | `the_excerpt_rss` |
| `#[TheTitleRssFilter]` | `the_title_rss` |
| `#[RssChannelAction]` | `rss_channel` |
| `#[RssItemAction]` | `rss_item` |
| `#[Rss2HeadAction]` | `rss2_head` |
| `#[AtomHeadAction]` | `atom_head` |
| `#[FeedContentTypeFilter]` | `feed_content_type` |
| `#[FeedLinksExtraFilter]` | `feed_links_extra` |
| `#[CommentFeedRssAction]` | `comment_feed_rss` |

### OEmbed コンポーネント

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

### SiteHealth コンポーネント

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

### DashboardWidget コンポーネント

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[WpDashboardSetupAction]` | - | `wp_dashboard_setup` |
| `#[WpNetworkDashboardSetupAction]` | - | `wp_network_dashboard_setup` |
| `#[UpdateUserOptionAction]` | `option: string` | `update_user_option` |

### Setting コンポーネント

| Attribute | 追加パラメータ | WordPress フック |
|-----------|--------------|-----------------|
| `#[SettingsPageAction]` | `page: string` | `settings_page_{page}` |
| `#[SettingsErrorsAction]` | - | `settings_errors` |

### Comment コンポーネント

| Attribute | WordPress フック |
|-----------|-----------------|
| `#[PreCommentApprovedFilter]` | `pre_comment_approved` |
| `#[CommentPostAction]` | `comment_post` |
| `#[EditCommentAction]` | `edit_comment` |
| `#[DeleteCommentAction]` | `delete_comment` |
| `#[TransitionCommentStatusAction]` | `transition_comment_status` |
| `#[WpInsertCommentAction]` | `wp_insert_comment` |

---

## 5. 条件 Attributes

フック登録の条件を制御する Attribute。

| Attribute | パラメータ | 提供元 | 説明 |
|-----------|-----------|--------|------|
| `#[IsAdmin]` | _(なし)_ | Hook | 管理画面でのみフックを登録 |
| `#[IsFrontend]` | _(なし)_ | Hook | フロントエンドでのみフックを登録 |

---

## 6. データ定義 Attributes

WordPress のデータ構造を定義するための Attribute。

### 投稿タイプ（PostType コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[PostType]` | `name: string`, `labels?: array`, `public?: bool`, `hasArchive?: bool`, `showInRest?: bool`, `supports?: array`, `menuIcon?: string`, `menuPosition?: int`, `hierarchical?: bool` | `register_post_type()` |
| `#[Meta]` | `type: string`, `label: string`, `description?: string`, `required?: bool`, `default?: mixed`, `maxLength?: int`, `placeholder?: string`, `min?: int`, `max?: int`, `step?: float`, `options?: array`, `rows?: int`, `mediaType?: string`, `multiple?: bool`, `fields?: array` | `register_meta()` |

### タクソノミー（Taxonomy コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Taxonomy]` | `name: string`, `postTypes: array`, `hierarchical?: bool`, `public?: bool`, `showInRest?: bool`, `labels?: array`, `rewrite?: array` | `register_taxonomy()` |
| `#[TaxonomyField]` | `name: string`, `type: string`, `default?: mixed`, `enum?: array` | Term Meta API |

### ブロック（Block コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Block]` | `name: string`, `namespace: string`, `title: string`, `description?: string`, `category: string`, `icon?: string`, `keywords?: array`, `dynamic?: bool`, `supports?: array` | `register_block_type()` |
| `#[BlockAttribute]` | `name: string`, `type: string`, `required?: bool`, `default?: mixed`, `min?: int`, `max?: int`, `enum?: array` | Block API |
| `#[BlockStyle]` | `name: string`, `label: string` | `register_block_style()` |
| `#[BlockVariation]` | `name: string`, `title: string`, `config: array` | Block Variations API |
| `#[BlockPattern]` | `name: string`, `title: string`, `description?: string`, `categories: array`, `keywords?: array` | `register_block_pattern()` |
| `#[BlockCollection]` | `name: string`, `title: string`, `icon: string` | Block Collections API |
| `#[BlockAsset]` | `type: string`, `handle: string`, `lazy?: bool` | Block Assets API |

### ウィジェット（Widget コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Widget]` | `id: string`, `name: string`, `description: string` | `register_widget()` |
| `#[WidgetField]` | `type: string`, `label: string`, `default?: mixed`, `placeholder?: string`, `maxlength?: int`, `rows?: int`, `cols?: int`, `min?: int`, `max?: int`, `step?: int`, `options?: array`, `showIf?: array`, `cssClass?: string`, `helpText?: string` | Widget API |

### コメント（Comment コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Comment]` | `type?: string`, `requiresApproval?: bool`, `allowReplies?: bool`, `maxDepth?: int` | Comments API |
| `#[CommentMeta]` | `key: string`, `type?: string`, `required?: bool`, `min?: int`, `max?: int` | `register_meta()` |
| `#[AntiSpam]` | `enabled?: bool`, `checkAkismet?: bool`, `customFilters?: bool`, `quarantineThreshold?: float` | Comments API |
| `#[Moderation]` | `autoApprove?: bool`, `requiresManualReview?: bool`, `notifyModerators?: bool` | Comments API |

### メディア（Media コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Attachment]` | `mimeType?: string`, `category?: string` | Attachment API |
| `#[ImageSizes]` | `sizes: array` | `add_image_size()` |
| `#[AttachmentMeta]` | `key: string`, `type?: string`, `min?: int`, `max?: int` | `register_meta()` |

### ユーザー（User コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[UserModel]` | _(なし)_ | Users API |
| `#[UserField]` | `key: string`, `type?: string`, `required?: bool` | User Meta API |
| `#[UserRole]` | `role: string` | `add_role()` |
| `#[UserCapability]` | `capability: string` | Capabilities API |

### データベース（Database コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Table]` | `name: string` | `dbDelta()` |

### フィード（Feed コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Feed]` | `type: string`, `format: string`, `path: string` | `add_feed()` |

### OEmbed（OEmbed コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[oEmbedProvider]` | `pattern: string`, `endpoint: string`, `format?: string` | `wp_oembed_add_provider()` |
| `#[EmbedTransformer]` | `priority: int` | oEmbed API |

### ロール（Role コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Role]` | `name: string`, `label: string`, `capabilities?: array` | `add_role()` |
| `#[Capability]` | `name: string`, `default?: bool` | Capabilities API |

### ナビゲーションメニュー（NavigationMenu コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Menu]` | `id: string`, `name: string`, `description?: string` | `register_nav_menus()` |
| `#[MenuLocation]` | `location: string` | `register_nav_menus()` |

### 翻訳（Translation コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[TextDomain]` | `domain: string`, `path: string` | `load_plugin_textdomain()` |
| `#[Translation]` | `text: string` | `__()` / `_e()` |
| `#[Pluralizable]` | `singular: string`, `plural: string`, `zero?: string` | `_n()` |
| `#[Context]` | `context: string` | `_x()` |
| `#[GroupAttr]` | `domain: string`, `namespace: string`, `fallbackLocale?: string` | i18n API |

### オプション（Option コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Property]` | _(なし)_ | Options API |
| `#[OptionName]` | `name: string` | Options API |
| `#[Encrypted]` | _(なし)_ | Options API |

### 設定（Setting コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Setting]` | `id: string`, `label: string`, `menuTitle: string`, `capability?: string`, `position?: int` | `add_options_page()` |
| `#[SettingField]` | `type: string`, `label: string`, `description?: string`, `default?: mixed`, `placeholder?: string`, `maxlength?: int`, `readonly?: bool`, `rows?: int`, `cols?: int`, `min?: int`, `max?: int`, `step?: int`, `options?: array`, `showIf?: array` | `add_settings_field()` |
| `#[SettingSection]` | `id: string`, `title?: string` | `add_settings_section()` |
| `#[SettingTab]` | `id: string`, `label: string`, `icon?: string` | Settings API |

### Transient（Transient コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[TransientConfig]` | `name: string`, `ttl: int`, `prefix?: string` | Transients API |

### DashboardWidget コンポーネント

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Permission]` | `capability: string` | `current_user_can()` |
| `#[RefreshInterval]` | `seconds: int` | - |
| `#[WidgetControl]` | `type: string`, `label: string`, `options?: array`, `min?: int`, `max?: int`, `default?: mixed`, `step?: int`, `showIf?: array` | Dashboard API |
| `#[DashboardWidgetControl]` | `type: string`, `label: string`, `options?: array`, `min?: int`, `max?: int`, `default?: mixed` | Dashboard API |
| `#[AjaxEndpoint]` | `action: string` | AJAX API |
| `#[WidgetGroup]` | `id: string`, `name: string` | Dashboard API |

### Admin コンポーネント

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[AdminPage]` | `slug: string` | `add_menu_page()` |
| `#[MenuItem]` | `title: string`, `menuTitle?: string`, `capability?: string`, `icon?: string`, `position?: int` | `add_menu_page()` |
| `#[AdminAsset]` | `type: 'script'\|'style'`, `handle: string`, `path: string`, `dependencies?: string[]`, `condition?: string` | `wp_enqueue_script()` / `wp_enqueue_style()` |
| `#[ScreenOption]` | `key: string`, `label: string`, `default: mixed` | Screen Options API |
| `#[ListTableColumn]` | `label: string`, `sortable?: bool`, `primary?: bool` | `WP_List_Table` |
| `#[BulkAction]` | `id: string`, `label: string` | `WP_List_Table` |
| `#[SubMenuItem]` | `parent: string`, `title: string`, `menuTitle?: string`, `capability?: string` | `add_submenu_page()` |
| `#[Ajax]` | `action: string`, `public?: bool` | AJAX API |

### Debug コンポーネント

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[DebugCollector]` | `name: string` | - |
| `#[Profile]` | `name: string` | - |

### SiteHealth コンポーネント

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Category]` | `name: string` | Site Health API |
| `#[Group]` | `id: string`, `name: string` | Site Health API |

---

## 7. バリデーション Attributes

WordPress のバリデーション・サニタイズ機能をラップする Attribute。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[MetaValidate]` | `rules: string` | PostType | 投稿メタのバリデーション | `register_meta()` の `sanitize_callback` |
| `#[OptionValidate]` | `notEmpty?: bool`, `min?: int`, `max?: int`, `pattern?: string`, `in?: array` | Option | オプション値のバリデーション | `register_setting()` の `sanitize_callback` |
| `#[SettingValidate]` | `rules: string` | Setting | 設定フィールドのバリデーション | Settings API バリデーション |
| `#[Validate]` | `rules: array` | Validator | 汎用バリデーション | - |
| `#[Sanitize]` | `rules: array` | Sanitizer | データサニタイズ | WordPress sanitize 関数群 |
| `#[CustomSanitizer]` | `name: string` | Sanitizer | カスタムサニタイザー定義 | - |

---

## 8. セキュリティ Attributes

アクセス制御・CSRF 保護に使用する Attribute。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[RequiresCapability]` | `capability: string` | Security / Role | ケイパビリティによるアクセス制御 | `current_user_can()` |
| `#[RequiresNonce]` | `action: string`, `method?: string`, `name?: string = '_wpnonce'` | Security / Nonce | HTTP メソッド制約付き nonce 検証 | `wp_verify_nonce()` |
| `#[NonceProtected]` | `action: string`, `name?: string = '_wpnonce'` | Nonce | nonce 自動検証 | `wp_verify_nonce()` |

---

## 9. ルーティング Attributes

URL ルーティングと REST API エンドポイントの定義に使用する Attribute。

### REST API（REST コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[RestRoute]` | `path: string`, `namespace?: string`, `methods?: array` | `register_rest_route()` |
| `#[RestParam]` | `name: string`, `type: string`, `required?: bool`, `description?: string`, `default?: mixed`, `enum?: string`, `min?: int`, `max?: int`, `minLength?: int`, `maxLength?: int`, `items?: array` | REST API Schema |
| `#[RestPermission]` | `capability: string` | `permission_callback` |
| `#[ApiDocumentation]` | `summary?: string`, `description?: string`, `tags?: array` | REST API Discovery |
| `#[ApiResponse]` | `status: int`, `resourceClass: string`, `description?: string` | REST API Schema |

### リライトルール（Routing コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[RewriteRule]` | `regex: string`, `query: string`, `position: string` | `add_rewrite_rule()` |
| `#[RewriteTag]` | `tag: string`, `regex: string` | `add_rewrite_tag()` |
| `#[QueryVar]` | `varName: string` | `query_vars` フィルター |
| `#[TemplateRedirect]` | _(なし)_ | `template_redirect` |

### AJAX（Ajax コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[AjaxHandler]` | `action: string`, `requiresAuth?: bool`, `capability?: string`, `nonceAction?: string`, `priority?: int = 10` | `wp_ajax_{action}` / `wp_ajax_nopriv_{action}` |
| `#[RequestParam]` | `name: string`, `required?: bool`, `default?: mixed`, `type?: string`, `maxSize?: int`, `allowedTypes?: array` | - |
| `#[FileUpload]` | `maxSize: int`, `allowedTypes: array` | `wp_handle_upload()` |

---

## 10. パフォーマンス Attributes

キャッシュやパフォーマンス最適化に使用する Attribute。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[Cache]` | `duration: int`, `key?: string` | Cache | メソッド戻り値のキャッシュ | `wp_cache_set()` / `wp_cache_get()` |
| `#[RefreshInterval]` | `seconds: int` | DashboardWidget | ウィジェットの自動更新間隔 | - |

---

## 11. CLI Attributes

WP-CLI コマンドの定義に使用する Attribute。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[Command]` | `name: string`, `description?: string` | Command | WP-CLI コマンドの定義 | `WP_CLI::add_command()` |
| `#[Argument]` | `name: string`, `description?: string`, `required?: bool` | Command | コマンド引数の定義 | WP-CLI Args |
| `#[Option]` | `name: string`, `description?: string`, `default?: mixed` | Command | コマンドオプションの定義 | WP-CLI Args |

---

## 12. ショートコード Attributes

WordPress ショートコードの定義に使用する Attribute。

| Attribute | パラメータ | 提供元 | WordPress API |
|-----------|-----------|--------|--------------|
| `#[Shortcode]` | `name: string`, `description?: string` | Shortcode | `add_shortcode()` |
| `#[ShortcodeAttr]` | `type?: string`, `required?: bool`, `description?: string`, `default?: mixed`, `min?: int`, `max?: int`, `enum?: array` | Shortcode | Shortcode API |

---

## 13. プラグイン Attributes

WordPress プラグインの定義に使用する Attribute。

| Attribute | パラメータ | 提供元 | WordPress API |
|-----------|-----------|--------|--------------|
| `#[Plugin]` | `textDomain: string`, `basePath: string`, `hasAdmin?: bool`, `hasPublic?: bool`, `hasApi?: bool` | Plugin | Plugin API |
| `#[RequiresWP]` | `version: string` | Plugin | Plugin Headers |
| `#[RequiresPHP]` | `version: string` | Plugin | Plugin Headers |

---

## 14. テーマ Attributes

WordPress テーマの定義に使用する Attribute。

| Attribute | パラメータ | 提供元 | WordPress API |
|-----------|-----------|--------|--------------|
| `#[Theme]` | `textDomain: string`, `version?: string` | Theme | Theme API |
| `#[ThemeSupport]` | `feature: string`, `options?: array` | Theme | `add_theme_support()` |
| `#[Menu]` | `location: string`, `description: string` | Theme | `register_nav_menus()` |
| `#[Sidebar]` | `id: string`, `name: string`, `description?: string` | Theme | `register_sidebar()` |
| `#[ParentTheme]` | `slug: string`, `minVersion?: string` | Theme | Theme API |
