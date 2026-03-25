# CLAUDE.md

このファイルはClaude Codeがこのリポジトリで作業する際のガイダンスを提供します。

## プロジェクト概要

WpPackは、WordPressをモダンPHPで拡張するコンポーネントライブラリのモノレポです。

## アーキテクチャ方針

### マルチクラウド対応（AWS / GCP / Azure）

コアインターフェース（Abstraction Layer）はクラウド非依存。プロバイダ固有の実装は Bridge パッケージとして分離する。AWS ファーストで開発し、GCP・Azure に順次拡大。

- 例: Mailer（コア） → AmazonMailer / AzureMailer / SendGridMailer
- 例: Cache（コア） → RedisCache / DynamoDbCache / MemcachedCache / ApcuCache
- Bridge 命名: `wppack/{provider}-{component}`

### サーバーレス環境対応

Lambda・Cloud Functions 等のサーバーレス環境をファーストクラスでサポート。ローカル / サーバーフル環境でも動作するグレースフルフォールバックを提供する。

- 例: Messenger（SQS/Lambda → 同期フォールバック）、Scheduler（EventBridge → WP-Cron フォールバック）
- 詳細: [docs/architecture/infrastructure.md](docs/architecture/infrastructure.md)

## パッケージカテゴリ

### Component（ライブラリ）
WordPress コンポーネント。`composer require` でインストール。
- 名前空間: `WpPack\Component\{Name}\`
- パッケージ名: `wppack/{name}`
- ディレクトリ: `src/Component/{Name}/`

### Plugin（WordPress プラグイン）
WordPress プラグインとして配布。Component を利用。
- 名前空間: `WpPack\Plugin\{Name}\`
- パッケージ名: `wppack/{name}`
- ディレクトリ: `src/Plugin/{Name}/`

## コンポーネント一覧

### Infrastructure Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Hook | wppack/hook | アトリビュートベースの WordPress フック（アクション/フィルター）管理 |
| DependencyInjection | wppack/dependency-injection | PSR-11 準拠のサービスコンテナ、オートワイヤリング、設定管理 |
| EventDispatcher | wppack/event-dispatcher | PSR-14 準拠のイベントシステム |
| Filesystem | wppack/filesystem | WP_Filesystem DI ラッパー、ファイル操作抽象化 |
| Kernel | wppack/kernel | アプリケーションブートストラップ |
| Option | wppack/option | wp_options の型安全ラッパー |
| Transient | wppack/transient | Transient API の型安全ラッパー |
| Role | wppack/role | ロール・権限管理 |
| Templating | wppack/templating | テンプレートエンジン抽象化 |
| TwigTemplating | wppack/twig-templating | Twig ブリッジ |
| Stopwatch | wppack/stopwatch | コード実行時間の計測 |
| Logger | wppack/logger | PSR-3 準拠ロガー |
| MonologLogger | wppack/monolog-logger | Monolog ブリッジ |
| Mime | wppack/mime | MIME 型判定・拡張子マッピング |
| Site | wppack/site | マルチサイト管理（ブログ切替・コンテキスト・サイト照会） |

### Abstraction Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Cache | wppack/cache | PSR-6/PSR-16 キャッシュ抽象化 |
| RedisCache | wppack/redis-cache | Redis / Valkey キャッシュ |
| ElastiCacheAuth | wppack/elasticache-auth | ElastiCache IAM 認証 |
| DynamoDbCache | wppack/dynamodb-cache | DynamoDB キャッシュ |
| MemcachedCache | wppack/memcached-cache | Memcached キャッシュ |
| ApcuCache | wppack/apcu-cache | APCu キャッシュ |
| Database | wppack/database | $wpdb の型安全ラッパー、マイグレーション |
| Query | wppack/query | WP_Query ビルダー |
| Security | wppack/security | 認証・認可フレームワーク |
| SamlSecurity | wppack/saml-security | SAML 2.0 SP 認証ブリッジ |
| OAuthSecurity | wppack/oauth-security | OAuth 2.0 / OpenID Connect 認証ブリッジ |
| Sanitizer | wppack/sanitizer | 入力サニタイズ |
| Escaper | wppack/escaper | 出力エスケープ |
| HttpClient | wppack/http-client | HTTP クライアント抽象化 |
| HttpFoundation | wppack/http-foundation | Request/Response 抽象化 |
| Mailer | wppack/mailer | メール送信抽象化、TransportInterface |
| AmazonMailer | wppack/amazon-mailer | SES トランスポート実装 |
| AzureMailer | wppack/azure-mailer | Azure Communication Services トランスポート実装 |
| SendGridMailer | wppack/sendgrid-mailer | SendGrid トランスポート実装 |
| Messenger | wppack/messenger | トランスポート非依存のメッセージバス |
| SqsMessenger | wppack/sqs-messenger | Amazon SQS トランスポート |
| Serializer | wppack/serializer | オブジェクト直列化（Normalizer チェーン） |
| OptionsResolver | wppack/options-resolver | オプション解決（Symfony OptionsResolver 拡張） |
| Debug | wppack/debug | デバッグ・プロファイリング |
| Storage | wppack/storage | オブジェクトストレージ抽象化 |
| S3Storage | wppack/s3-storage | Amazon S3 ストレージアダプタ |
| AzureStorage | wppack/azure-storage | Azure Blob Storage アダプタ |
| GcsStorage | wppack/gcs-storage | Google Cloud Storage アダプタ |

### Feature Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Admin | wppack/admin | 管理画面ページ・メニュー登録 |
| Rest | wppack/rest | REST API エンドポイント定義 |
| Routing | wppack/routing | URL ルーティング |
| PostType | wppack/post-type | カスタム投稿タイプ・メタ登録 |
| Scheduler | wppack/scheduler | Trigger ベースのタスクスケジューラー |
| EventBridgeScheduler | wppack/eventbridge-scheduler | EventBridge スケジューラー |
| Console | wppack/console | WP-CLI コマンドフレームワーク |
| Shortcode | wppack/shortcode | ショートコード登録 |
| Nonce | wppack/nonce | CSRF トークン管理 |
| Asset | wppack/asset | アセット管理（スクリプト・スタイル） |
| Ajax | wppack/ajax | Admin Ajax ハンドラー |
| Wpress | wppack/wpress | .wpress アーカイブ形式操作 |

### Application Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Plugin | wppack/plugin | プラグインライフサイクル管理 |
| Theme | wppack/theme | テーマ開発フレームワーク |
| Widget | wppack/widget | ウィジェット定義 |
| Setting | wppack/setting | Settings API ラッパー |
| User | wppack/user | ユーザー管理 |
| Block | wppack/block | ブロックエディタ統合 |
| Media | wppack/media | メディア管理 |
| Comment | wppack/comment | コメント管理 |
| Taxonomy | wppack/taxonomy | タクソノミー定義 |
| NavigationMenu | wppack/navigation-menu | メニュー管理 |
| Feed | wppack/feed | RSS/Atom フィード |
| OEmbed | wppack/oembed | oEmbed プロバイダー |
| SiteHealth | wppack/site-health | サイトヘルスチェック |
| DashboardWidget | wppack/dashboard-widget | ダッシュボードウィジェット |
| Translation | wppack/translation | 翻訳・国際化 |

### Plugin パッケージ
| Plugin | パッケージ名 | 説明 |
|--------|-------------|------|
| EventBridgeSchedulerPlugin | wppack/eventbridge-scheduler-plugin | EventBridge スケジューラープラグイン |
| S3StoragePlugin | wppack/s3-storage-plugin | S3 ストレージプラグイン |
| AmazonMailerPlugin | wppack/amazon-mailer-plugin | Amazon SES メーラープラグイン |
| DebugPlugin | wppack/debug-plugin | デバッグツールバープラグイン |
| RedisCachePlugin | wppack/redis-cache-plugin | Redis キャッシュプラグイン |

## 主要な依存関係

```
wppack/eventbridge-scheduler-plugin
    ↓ requires
wppack/scheduler
    ↓ requires
wppack/messenger

wppack/messenger
    ↓ requires
wppack/serializer, wppack/site

wppack/sqs-messenger
    ↓ requires
wppack/messenger, wppack/site
    + async-aws/sqs

wppack/media
    ↓ requires
wppack/post-type, wppack/storage, wppack/site

wppack/s3-storage-plugin
    ↓ requires
wppack/storage, wppack/s3-storage, wppack/rest, wppack/site
    + wppack/media, wppack/messenger
    + async-aws/s3

wppack/s3-storage
    ↓ requires
wppack/storage
    + async-aws/s3

wppack/azure-storage
    ↓ requires
wppack/storage
    + azure-oss/storage

wppack/gcs-storage
    ↓ requires
wppack/storage
    + google/cloud-storage

wppack/elasticache-auth
    + async-aws/core

wppack/redis-cache
    ↓ requires
wppack/cache
    + ext-redis / ext-relay / predis/predis

wppack/dynamodb-cache
    ↓ requires
wppack/cache
    + async-aws/dynamo-db

wppack/memcached-cache
    ↓ requires
wppack/cache
    + ext-memcached

wppack/apcu-cache
    ↓ requires
wppack/cache
    + ext-apcu

wppack/amazon-mailer
    ↓ requires
wppack/mailer
    + async-aws/ses

wppack/azure-mailer
    ↓ requires
wppack/mailer

wppack/sendgrid-mailer
    ↓ requires
wppack/mailer

wppack/amazon-mailer-plugin
    ↓ requires
wppack/amazon-mailer, wppack/mailer, wppack/hook
    + wppack/dependency-injection, wppack/kernel, wppack/messenger

wppack/redis-cache-plugin
    ↓ requires
wppack/cache, wppack/redis-cache, wppack/elasticache-auth, wppack/hook
    + wppack/dependency-injection, wppack/kernel

wppack/security
    ↓ requires
wppack/role, wppack/http-foundation, wppack/event-dispatcher, wppack/site

wppack/admin, wppack/setting, wppack/dashboard-widget
    ↓ requires
wppack/role, wppack/http-foundation
    + wppack/security (suggest)
    + wppack/templating (suggest)

wppack/ajax, wppack/routing, wppack/rest
    ↓ requires
wppack/role, wppack/http-foundation

wppack/saml-security
    ↓ requires
wppack/security, wppack/site
    + onelogin/php-saml

wppack/oauth-security
    ↓ requires
wppack/security, wppack/site
    + firebase/php-jwt

wppack/eventbridge-scheduler
    ↓ requires
wppack/scheduler, wppack/messenger, wppack/site
    + async-aws/scheduler

wppack/twig-templating
    ↓ requires
wppack/templating
    + twig/twig

wppack/monolog-logger
    ↓ requires
wppack/logger
    + monolog/monolog
```

## 開発ガイドライン

### 言語
- ドキュメント: 日本語
- コード: 英語（変数名、クラス名、コメント）

### PHP要件
- PHP 8.2以上
- PSR-4オートロード

### コーディング規約

**モダンPHPのベストプラクティスに従う。WordPress Coding Standardsは使用しない。**

- PER Coding Style (PSR-12後継) に準拠
- 厳格な型宣言 (`declare(strict_types=1)`)
- readonly プロパティを活用
- Symfony のパターンに従う
- コンストラクタプロパティプロモーションを活用
- match式を活用
- Named argumentsを適切に使用

### コミットメッセージ

[Conventional Commits](https://www.conventionalcommits.org/) ベースの形式を使用する。

```
<type>(<scope>): <summary>

<body>
```

#### サマリー行（1行目）

- **72文字以内**（`git log --oneline` で見切れない長さ）
- **型プレフィックス**: 変更の種類を明示
- **スコープ（任意）**: 変更対象のコンポーネント名やパッケージ名
- **命令形**: "Add", "Fix", "Refactor" etc.（"Added", "Fixes" ではない）

| type | 用途 |
|------|------|
| `feat` | 新機能追加 |
| `fix` | バグ修正 |
| `refactor` | リファクタリング（動作変更なし） |
| `docs` | ドキュメントのみの変更 |
| `test` | テストの追加・修正のみ |
| `chore` | ビルド、CI、依存関係等の雑務 |

#### 本文（2行目以降）

- サマリー行の後に**空行1行**を挟む
- **箇条書き**（`-` 使用）で変更内容を構造化
- 「なぜ」この変更が必要だったかを含める
- 技術的な詳細は必要に応じて

#### 例

```
feat(Admin,DashboardWidget,Setting): add render() shortcut

- Add render() method to AbstractAdminPage, AbstractDashboardWidget,
  AbstractSettingsPage that delegates to TemplateRendererInterface
- Registry classes accept optional TemplateRendererInterface in constructor
  and inject via setter during register()
- Setting uses $templateRenderer to avoid collision with $renderer
  (SettingsRenderer)
```

#### コミットの粒度

**1コミット = 1つの論理的な変更単位（atomic commit）** を原則とする。

- **同じコミットに含める**: 機能実装とそのテスト、機能実装と直接関連するドキュメント更新
- **別コミットに分ける**: 独立したバグ修正同士、新機能と無関係なリファクタリング
- **判断基準**: 「このコミットだけを `git revert` したとき、意味のある単位で元に戻せるか？」

### `function_exists()` の使用方針

WordPress がロードされていない環境を想定する必要はない。WordPress コア関数に対する `function_exists()` ガードは不要。

- **不要:** `get_post`, `wp_insert_user`, `get_term_meta` 等の WordPress コア関数（WordPress がロードされていれば常に存在する）
- **必要:** マルチサイト専用関数（`get_sites`, `switch_to_blog` 等 — シングルサイトでは存在しない）
- **必要:** PHP 拡張の関数（`apcu_enabled`, `bzcompress`, `finfo_open` 等）
- **必要:** `wp-admin` 専用関数で `require_once` が必要な場合（`dbDelta`, `wp_delete_user` 等）

### Hook vs EventDispatcher の使い分け

新規実装では **EventDispatcher を優先**する。EventDispatcher は WordPress の `$wp_filter` をバックエンドに使っており、WordPress フック（アクション・フィルター）も `WordPressEvent` / Extended Event クラスで型安全に扱える。

| ケース | 推奨 |
|--------|------|
| DI コンテナ起動前のフック（`plugins_loaded` 等） | WordPress 関数を直接使用（`add_action()` / `add_filter()`） |
| WordPress フック全般（`init` 以降） | **EventDispatcher**（`WordPressEvent` / `#[AsEventListener]`） |
| アプリケーション固有のドメインイベント | **EventDispatcher**（カスタムイベント + `#[AsEventListener]`） |
| コンポーネント間の疎結合な通知 | **EventDispatcher** |

Hook コンポーネントは既存コードとの互換性のために残すが、新規実装では EventDispatcher を使う。

### Named Hook 規約

全 Named Hook アトリビュートは Hook コンポーネントに集約されている:
- 詳細: [docs/components/hook/named-hook-conventions.md](docs/components/hook/named-hook-conventions.md)
- Hook component がライフサイクルフック（`init`, `admin_init` 等）およびドメイン固有フックをすべて所有
- 名前空間: `WpPack\Component\Hook\Attribute\{ComponentName}\Action\` / `Filter\`
- ディレクトリ: `src/Component/Hook/src/Attribute/{ComponentName}/Action/` / `Filter/`
- 自動検出: `ReflectionAttribute::IS_INSTANCEOF` により追加設定不要

### WordPress バージョン互換性

WordPress のフックや関数がバージョン間でリネーム・廃止される場合は、`version_compare(get_bloginfo('version'), ...)` でバージョンを判定し、新旧両方に対応する。新しいバージョンのフックを優先し、古いバージョンにはフォールバックを提供する。

```php
// 例: WP 6.8 で setted_transient → set_transient にリネーム
$useNewHooks = version_compare(get_bloginfo('version'), '6.8', '>=');
$setHook = $useNewHooks ? 'set_transient' : 'setted_transient';
add_action($setHook, [$this, 'onTransientSet'], 10, 3);
```

- 二重発火を避けるため、新旧両方を同時に登録しない（どちらか一方を条件分岐で選択）
- 廃止されたフック／関数を使い続けない（deprecation warning の原因になる）
- 対応バージョン範囲はコメントで明記する（例: `// WP 6.8+: ... / WP < 6.8: ...`）

### 名前空間

```
WpPack\Component\{Name}\  - コンポーネント
WpPack\Plugin\{Name}\     - プラグイン
```

### 静的解析・CI

```bash
vendor/bin/phpstan analyse                      # 静的解析
vendor/bin/php-cs-fixer fix --dry-run --diff    # コードスタイルチェック
vendor/bin/phpunit                              # テスト実行
```

### テスト

#### テスト構成

テストは wp-phpunit + MySQL による WordPress 統合テスト環境で実行する。`tests/bootstrap.php` は常に WordPress をフルロードするため、テスト実行前に Docker で MySQL を起動する必要がある。

#### ローカルでのテスト実行

```bash
docker compose up -d --wait    # MySQL 起動（必須）
vendor/bin/phpunit             # 全テスト実行
docker compose down            # MySQL 停止
```

#### テストでの WordPress 関数モック

WordPress 関数に依存するテストでは `pre_http_request` フィルターで HTTP 呼び出しをモックする。`HttpClient` を匿名クラスで拡張するパターンは使用しない（clone ベースの immutability と相性が悪い）。

```php
// setUp() でフィルター登録
add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);

// tearDown() でフィルター解除
remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
```

#### テストファイル配置

各コンポーネントのテストは `src/Component/{Name}/tests/` に配置。

### モノレポ開発フロー
- ルート `composer.json` で全パッケージを管理
- `replace` セクションで自パッケージを宣言
- splitsh-lite で各パッケージリポジトリに分割公開
- GitHub Actions で CI/CD 実行

### コンポーネント追加時のチェックリスト

新しいコンポーネント / Bridge パッケージを追加する際は、以下のファイルをすべて更新すること:

1. **ルート `composer.json`** — `autoload.psr-4`、`autoload-dev.psr-4`、`replace` に追加
2. **`codecov.yml`** — `individual_components` に `component_id` / `name` / `paths` を追加
3. **`CLAUDE.md`** — コンポーネント一覧テーブル、主要な依存関係に追加
4. **`docs/`** — 該当コンポーネントのドキュメント作成・更新

### ドキュメント・コンポーネント更新時の一貫性チェック

- **ドキュメント更新時**: `docs/components/README.md` のコンポーネント一覧テーブルで、リンク先パスが実在するか確認する。新規ドキュメント追加時はテーブルにリンクを追加し、既存リンクのパス形式（ファイル: `./name.md`、ディレクトリ: `./name/`）と整合させる
- **コンポーネント更新時**: `CLAUDE.md` のコンポーネント一覧テーブル、`docs/components/README.md` のテーブル、`src/Component/{Name}/README.md`（パッケージ README）、および `src/` 配下の実装（名前空間・ディレクトリ名・`composer.json`）で、コンポーネント名・パッケージ名・説明の表記が一致しているか確認し、一貫性を保つ

### ディレクトリ構造

```
wppack/
├── src/
│   ├── Component/          # WordPress コンポーネント
│   │   ├── Hook/          → wppack/hook
│   │   ├── Mailer/        → wppack/mailer
│   │   ├── Messenger/     → wppack/messenger
│   │   └── ...
│   └── Plugin/             # WordPress プラグイン
│       ├── EventBridgeSchedulerPlugin/  → wppack/eventbridge-scheduler-plugin
│       ├── S3StoragePlugin/  → wppack/s3-storage-plugin
│       └── AmazonMailerPlugin/  → wppack/amazon-mailer-plugin
├── tests/
│   ├── Component/
│   └── Plugin/
├── docs/
└── ...
```

### 後方互換性

全パッケージがリリース前（設計中）のため、後方互換性は考慮しない。API の変更、パラメータ順序の変更、クラスのリネーム・削除は自由に行ってよい。

## ステータス

- 全パッケージ: 設計中

## このファイルの更新について

このCLAUDE.mdは、プロジェクトの変更に合わせて必要に応じて更新してください:

- 新しいパッケージやモジュールが追加された場合
- アーキテクチャや設計方針が変更された場合
- コーディング規約が更新された場合
- 重要な開発ルールやコマンドが追加された場合
- プロジェクトステータスが変わった場合
