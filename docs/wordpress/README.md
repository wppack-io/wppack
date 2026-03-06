# WordPress コア仕様リファレンス

WordPress 内部実装のリファレンスドキュメント。各コンポーネントが依存する WordPress コアサブシステムの仕様を記述しています。

## Infrastructure

- [hooks.md](./hooks.md) - フックシステム（action / filter）
- [object-cache-dropin.md](./object-cache-dropin.md) - Object Cache ドロップイン
- [options.md](./options.md) - Options API
- [transients.md](./transients.md) - Transients API
- [plugin-lifecycle.md](./plugin-lifecycle.md) - プラグインライフサイクル（activation / deactivation / uninstall）
- [cron.md](./cron.md) - WP-Cron API
- [database.md](./database.md) - Database API（wpdb）
- [filesystem.md](./filesystem.md) - Filesystem API
- [i18n.md](./i18n.md) - 国際化 API

## HTTP / Communication

- [http-api.md](./http-api.md) - HTTP API
- [mail.md](./mail.md) - Mail API（wp_mail）
- [ajax.md](./ajax.md) - Ajax API（admin-ajax.php）
- [rest-api.md](./rest-api.md) - REST API

## Content

- [post-types.md](./post-types.md) - 投稿タイプ / WP_Query
- [taxonomy.md](./taxonomy.md) - タクソノミー API
- [comments.md](./comments.md) - コメント API
- [media.md](./media.md) - メディア / 添付ファイル API
- [blocks.md](./blocks.md) - ブロックエディター API
- [shortcodes.md](./shortcodes.md) - ショートコード API
- [feeds.md](./feeds.md) - フィード API（RSS / Atom）
- [oembed.md](./oembed.md) - oEmbed API

## Security

- [nonce.md](./nonce.md) - Nonce API
- [sanitization.md](./sanitization.md) - サニタイズ / エスケープ
- [users-and-roles.md](./users-and-roles.md) - ユーザー / ロール / 権限

## Appearance / Admin

- [theme.md](./theme.md) - テーマ / Enqueue API
- [admin.md](./admin.md) - 管理画面 UI API
- [settings-api.md](./settings-api.md) - Settings API
- [widgets.md](./widgets.md) - ウィジェット API
- [nav-menus.md](./nav-menus.md) - ナビゲーションメニュー API
- [rewrite.md](./rewrite.md) - リライト API（URL ルーティング）
- [site-health.md](./site-health.md) - サイトヘルス API
