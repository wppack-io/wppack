# メンテナ向け詳細メモ

CLAUDE.md のセッション運用ルールと分離した、コンポーネント固有の深掘り
リファレンス。新規タスクで触る前に該当セクションを読むと落とし穴を回避
しやすい。

## Monorepo Development Workflow

- すべてのパッケージはルート `composer.json` 経由で管理
- Self-packages は `replace` セクションで宣言
- 個別パッケージリポジトリは splitsh-lite で publish
- ディレクトリ構成の詳細: [architecture/monorepo.md](./monorepo.md)
- CI/CD: GitHub Actions
  - **push / PR (auto, 19 jobs)**: WP 6.9 × PHP {8.2-8.5} × DB
    {wpdb,sqlite,mysql,postgresql} + smoke combo (PHP 8.2 × wpdb) for
    WP {6.7, 6.8, 7.0}.
  - **workflow_dispatch (manual, 56 jobs)**: full PHP × DB × WP matrix
    minus PHP 8.5 × WP 6.7/6.8 (compat exclusions)。Actions UI または
    `gh workflow run ci.yml` から実行。

## Backward Compatibility

全パッケージは `1.x` ブランチでプレリリース(設計フェーズ)のため、後方
互換性は考慮不要。API 変更、パラメータ並び替え、クラス renames、削除は
自由に行ってよい。

## Database Component Maintenance Notes

`wppack/database` はプロジェクト内で最も機能密度が高い抽象化(マルチ
エンジンドライバ層、AST ベースクエリ変換、`$wpdb` 置換 WPPackWpdb)。
変更時のルール:

- **AST-first translation.** エンジン固有のリライトは
  `Bridge/{Sqlite,PostgreSQL,AuroraDSQL}/src/Translator/*QueryTranslator.php`
  に配置。AST 変換を優先し、phpmyadmin/sql-parser の AST に形が出ない
  ケースのみ `QueryRewriter`(トークンウォーク)へ降りる。サイレント
  パススルーは禁止 ─ 未対応機能は `UnsupportedFeatureException` を投げ
  て呼出側に見せる。
- **Integration CI matrix.** MySQL / SQLite / PostgreSQL に対して wpdb
  変種 + 3 driver 変種 × 4 PHP バージョンで実行。`wpdb` 変種のみ
  merge blocker で他は `continue-on-error: true`(Query コンポーネント
  の既知失敗あり)。translator 挙動を変えたら commit 前にローカル 3 変種
  で確認:
  ```bash
  DATABASE_DSN='sqlite:///tmp/wppack_test.db' vendor/bin/phpunit --filter SqliteWpdbIntegrationTest
  DATABASE_DSN='mysql://root:password@127.0.0.1:3307/wppack_test' vendor/bin/phpunit --filter MySQLWpdbIntegrationTest
  DATABASE_DSN='pgsql://wppack:wppack@127.0.0.1:5433/wppack_test' vendor/bin/phpunit --filter PostgreSQLWpdbIntegrationTest
  ```
- **PSR-3 logger + PSR-14 events は生 bind 値を絶対に漏らさない。**
  ペイロードは `paramsSummary()`(`#0 => 'string(7)'` 型/長さ記述子)を
  通す。`WPPACK_DB_LOG_VALUES=1` はローカル開発専用。生 `$params` を
  logger context に埋めない。
- **Reader/Writer routing.** `WPPackWpdb::selectDriver()` が単一ディス
  パッチ点。SELECT vs 書き込み、トランザクション vs 平文の振り分けを
  変える場合はレプリケーション遅延影響を伴うので、二本接続に別データ
  を seed する spy-driver テストを必ず追加。
- **PreparedBank.** マーカーは `/*WPP:<16-hex>*/`、per-instance
  `random_bytes(8)` salt + `sha1(sql + params)` で計算。salt は
  絶対に除去しない(forge 耐性)。マーカーフォーマットを変える場合は
  `PreparedBank.php` の `MARKER_PATTERN` と `tests/` 配下のハードコード
  regex すべてを更新。
- **Drivers' gone-away handling.** `MySQLDriver::throwQueryError()` と
  `PostgreSQLDriver::throwQueryError()` は特定エラーコードで
  `$this->connection = null` を落とし、`ensureConnected()` 次回呼出で
  再接続させる。古いハンドルを「親切に」復元しない ─ production 呼出側
  はこの null 化に依存。
- **PostgreSQL search_path.** `PostgreSQLDriver`(および継承の
  `AuroraDSQLDriver`)は `searchPath` ctor arg / `?search_path=` DSN
  オプションを受け、connect 後に `SET search_path TO ...` を発行。
  Translator の introspection (`SHOW TABLES` / `SHOW COLUMNS`) は
  `current_schema()` を参照 ─ ハードコード `'public'` ではない。
- **Translator 例外.** `TranslationException`, `ParserFailureException`,
  `UnsupportedFeatureException`。Driver 側は `DriverException`,
  `DriverThrottledException`, `DriverTimeoutException`,
  `CredentialsExpiredException`, `ConnectionException`。すべて
  `ExceptionInterface` 実装。
- **Mocking caveats.** `AuroraDSQLDriver` / DataApi driver は
  async-aws 系パッケージ(optional)を要求。ライブ接続不要なテストは
  `ReflectionClass::newInstanceWithoutConstructor()` + プロパティ注入
  で構築(例: `tests/Bridge/AuroraDSQL/tests/OccRetryTest.php`)。

### DSN fallbacks must fail loud

DSN-driven factory (Database driver / Mailer transport / Cache adapter)
では **silent default を置かない**。理由は脅威モデル:

1. オペレータが `DATABASE_DSN=mysql://user:pass@prod-db.internal/app` を
   設定。typo や env 展開失敗で `mysql://user:pass@/app` になる。
2. 旧挙動: `$dsn->getHost() ?? '127.0.0.1'` で黙って localhost 接続。
3. ローカルに攻撃者制御の MySQL (コンテナ内、sibling tenant、
   credential-harvesting oracle) がいれば、user/pass/queries を
   そこへ送出 — exfiltration の古典パターン。

ルール:

- **host / endpoint / ARN**: `?? 'default'` 禁止。`null`/`''` なら
  `ConnectionException` (DB) / `InvalidArgumentException` (cache,
  mail) で throw。exception message に DSN のスキーム部分だけ含め、
  user/password 部は含めない。
- **username / password**: `?string` のまま driver に渡す。
  `mysqli(null, …)` / libpq `user=` 省略 / SMTP AUTH skip は `''`
  との意味が異なる — 空認証送出を招くので `?? ''` しない。
  `SesSmtpTransport` のように認証必須な transport は `?? throw` で
  明示拒否。
- **API key / secret**: `?? throw new InvalidArgumentException(...)`
  ideom (`SendGridTransportFactory` 参照)。

対応済み factory: `MySQLDriverFactory`, `PostgreSQLDriverFactory`,
`MySQLDataApiDriverFactory`, `PostgreSQLDataApiDriverFactory`,
`AuroraDSQLDriverFactory`, `MemcachedAdapterFactory`,
`NativeTransportFactory`, `SesTransportFactory`, `AzureTransportFactory`。

## Cache Component Notes

アダプタ選択は `CACHE_DSN` 環境変数 / PHP 定数が駆動(旧名
`WPPACK_CACHE_DSN` から rename)。`object-cache.php` drop-in もこれを
参照し、`CloudWatch` 自動検出・`RedisCacheConfiguration` も同様。

## Plugin Settings Pages (WordPress Components)

プラグイン設定ページは WordPress Components (`@wordpress/components`)
と `@wordpress/scripts` ビルドパイプラインを使う。モダンな WordPress
admin UI パターンに従うこと:

- [How to use DataForm to create plugin settings pages](https://developer.wordpress.org/news/2026/01/how-to-use-dataform-to-create-plugin-settings-pages/)
- [How to use WordPress React components for plugin pages](https://developer.wordpress.org/news/2024/03/how-to-use-wordpress-react-components-for-plugin-pages/)

重要パターン:
- `wp-components`, `wp-element`, `wp-api-fetch` を依存として enqueue
- 設定 CRUD は独自 REST API endpoint (`/wppack/v1/...`) を使用
- センシティブフィールド(証明書・鍵)は API レスポンスでマスク
- 定数由来フィールドは readonly 表示
- **`npm install` は常に `--ignore-scripts` を付ける**(例:
  `npm install --ignore-scripts`)。依存からの任意スクリプト実行を防止。

## Plugin Settings Menu Position

サブメニュー `position` はカテゴリごとに 100 刻みで付与。各プラグインは
カテゴリ内で一意の値(base + 1, 2, 3...)を持ち確定順序を確保。
`AdminPageRegistry` が WordPress core item の後ろで WPPack 項目を
position ソート。

| position | Category | Plugins |
|----------|----------|---------|
| 101–103 | Infrastructure (Cache, Storage, Mail) | RedisCachePlugin (101), S3StoragePlugin (102), AmazonMailerPlugin (103) |
| 201–203 | Authentication (SSO, OAuth, Passkey) | SamlLoginPlugin (201), OAuthLoginPlugin (202), PasskeyLoginPlugin (203) |
| 300–301 | Provisioning | ScimPlugin (300), RoleProvisioningPlugin (301) |

MonitoringPlugin は Settings 配下ではなく top-level メニュー(WordPress
admin サイドバー `position: 90`)。

新規プラグイン追加時は既存カテゴリの次の連番値を使用。新カテゴリが必要な
場合は 100 の倍数を新たに base として採用。
