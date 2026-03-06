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
| Hook | wppack/hook | アトリビュートベースのアクション/フィルター |
| DependencyInjection | wppack/dependency-injection | サービスコンテナ + 設定管理 |
| EventDispatcher | wppack/event-dispatcher | イベントシステム |
| Filesystem | wppack/filesystem | ファイル操作 |
| Kernel | wppack/kernel | アプリケーションブートストラップ |
| Option | wppack/option | WordPress options ラッパー |
| Transient | wppack/transient | WordPress transients ラッパー |
| Role | wppack/role | ユーザーロール管理 |
| Templating | wppack/templating | テンプレートエンジン |
| Logger | wppack/logger | PSR-3 ロギング |
| MonologLogger | wppack/monolog-logger | Monolog ブリッジ |

### Abstraction Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Cache | wppack/cache | キャッシュインターフェース |
| RedisCache | wppack/redis-cache | Redis / Valkey キャッシュ |
| ElastiCacheAuth | wppack/elasticache-auth | ElastiCache IAM 認証 |
| DynamoDbCache | wppack/dynamodb-cache | DynamoDB キャッシュ |
| MemcachedCache | wppack/memcached-cache | Memcached キャッシュ |
| ApcuCache | wppack/apcu-cache | APCu キャッシュ |
| Database | wppack/database | データベース抽象化 |
| Query | wppack/query | クエリビルダー |
| Security | wppack/security | セキュリティユーティリティ |
| Sanitizer | wppack/sanitizer | 入力サニタイズ |
| Escaper | wppack/escaper | 出力エスケープ |
| HttpClient | wppack/http-client | HTTPクライアント |
| HttpFoundation | wppack/http-foundation | Request/Response |
| Mailer | wppack/mailer | メール抽象化（Transport基盤含む） |
| AmazonMailer | wppack/amazon-mailer | SES トランスポート実装 |
| AzureMailer | wppack/azure-mailer | Azure Communication Services トランスポート実装 |
| SendGridMailer | wppack/sendgrid-mailer | SendGrid トランスポート実装 |
| Messenger | wppack/messenger | メッセージングバス（SQS/Lambda） |
| OptionsResolver | wppack/options-resolver | オプション解決（Symfony OptionsResolver 拡張） |
| Debug | wppack/debug | デバッグ・プロファイリング |

### Feature Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Admin | wppack/admin | 管理画面ユーティリティ |
| Rest | wppack/rest | REST API フレームワーク |
| Routing | wppack/routing | URLルーティング |
| PostType | wppack/post-type | カスタム投稿タイプ |
| Scheduler | wppack/scheduler | スケジューラー（EventBridge同期） |
| Command | wppack/command | WP-CLI 統合 |
| Shortcode | wppack/shortcode | ショートコードフレームワーク |
| Nonce | wppack/nonce | CSRF トークン管理 |
| Ajax | wppack/ajax | Ajax ハンドラー |

### Application Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Plugin | wppack/plugin | プラグイン開発フレームワーク |
| Theme | wppack/theme | テーマ開発フレームワーク |
| Widget | wppack/widget | ウィジェットシステム |
| Setting | wppack/setting | 設定管理 |
| User | wppack/user | ユーザー管理 |
| Block | wppack/block | Gutenberg ブロック |
| Media | wppack/media | メディアライブラリ |
| Comment | wppack/comment | コメントシステム |
| Taxonomy | wppack/taxonomy | タクソノミー管理 |
| NavigationMenu | wppack/navigation-menu | メニュー管理 |
| Feed | wppack/feed | RSS/Atom フィード |
| OEmbed | wppack/oembed | oEmbed |
| SiteHealth | wppack/site-health | サイトヘルスチェック |
| DashboardWidget | wppack/dashboard-widget | ダッシュボードウィジェット |
| Translation | wppack/translation | 国際化 |

### Plugin パッケージ
| Plugin | パッケージ名 | 説明 |
|--------|-------------|------|
| SchedulerPlugin | wppack/scheduler-plugin | EventBridge スケジューラープラグイン |
| S3StoragePlugin | wppack/s3-storage-plugin | S3 ストレージプラグイン |
| AmazonMailerPlugin | wppack/amazon-mailer-plugin | Amazon SES メーラープラグイン |

## 主要な依存関係

```
wppack/scheduler-plugin
    ↓ requires
wppack/scheduler
    ↓ requires
wppack/messenger

wppack/s3-storage-plugin
    ↓ requires
wppack/media, wppack/filesystem, wppack/hook
    + async-aws/s3

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
wppack/amazon-mailer, wppack/hook

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

### Named Hook 規約

各 component が WordPress フック用の named hook アトリビュートを定義する際の規約:
- 詳細: [docs/components/hook/named-hook-conventions.md](docs/components/hook/named-hook-conventions.md)
- Hook component はライフサイクルフックのみ所有（`init`, `admin_init` 等）
- ドメイン固有フックは各 component が所有（PostType → `SavePostAction` 等）
- 名前空間: `WpPack\Component\{Name}\Attribute\Action\` / `Attribute\Filter\`
- 自動検出: `ReflectionAttribute::IS_INSTANCEOF` により追加設定不要

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

テストは wp-phpunit + MySQL による WordPress 統合テスト環境に対応。`tests/wp-config.php` の有無で動作が切り替わる:

- **`tests/wp-config.php` あり**: WordPress をロードし全テスト実行（スキップなし）
- **`tests/wp-config.php` なし**: PHPMailer のみロード、WordPress 依存テストはスキップ

#### ローカルでの統合テスト実行

```bash
docker compose up -d --wait                        # MySQL 起動
cp tests/wp-config.php.dist tests/wp-config.php    # 設定ファイル配置
vendor/bin/phpunit                                 # 全テスト実行
docker compose down                                # MySQL 停止
```

#### テストでの WordPress 関数モック

WordPress 関数に依存するテストでは `pre_http_request` フィルターで HTTP 呼び出しをモックする。`HttpClient` を匿名クラスで拡張するパターンは使用しない（clone ベースの immutability と相性が悪い）。

```php
// setUp() でフィルター登録
if (function_exists('add_filter')) {
    add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
}

// tearDown() でフィルター解除
if (function_exists('remove_filter')) {
    remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
}
```

WordPress が利用不可な環境では `function_exists` ガードでスキップ:

```php
if (!function_exists('wp_remote_request')) {
    self::markTestSkipped('WordPress functions are not available.');
}
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
│       ├── SchedulerPlugin/  → wppack/scheduler-plugin
│       ├── S3StoragePlugin/  → wppack/s3-storage-plugin
│       └── AmazonMailerPlugin/  → wppack/amazon-mailer-plugin
├── tests/
│   ├── Component/
│   └── Plugin/
├── docs/
└── ...
```

## ステータス

- 全パッケージ: 設計中

## このファイルの更新について

このCLAUDE.mdは、プロジェクトの変更に合わせて必要に応じて更新してください:

- 新しいパッケージやモジュールが追加された場合
- アーキテクチャや設計方針が変更された場合
- コーディング規約が更新された場合
- 重要な開発ルールやコマンドが追加された場合
- プロジェクトステータスが変わった場合
