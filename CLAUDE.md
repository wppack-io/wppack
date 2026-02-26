# CLAUDE.md

このファイルはClaude Codeがこのリポジトリで作業する際のガイダンスを提供します。

## プロジェクト概要

WpPackは、WordPressをモダンPHPで拡張するコンポーネントライブラリのモノレポです。

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
| DependencyInjection | wppack/dependency-injection | サービスコンテナ |
| Config | wppack/config | 設定管理 |
| EventDispatcher | wppack/event-dispatcher | イベントシステム |
| Filesystem | wppack/filesystem | ファイル操作 |
| Kernel | wppack/kernel | アプリケーションブートストラップ |
| Option | wppack/option | WordPress options ラッパー |
| Transient | wppack/transient | WordPress transients ラッパー |
| Role | wppack/role | ユーザーロール管理 |
| Templating | wppack/templating | テンプレートエンジン |
| Logger | wppack/logger | PSR-3 ロギング |

### Abstraction Layer
| Component | パッケージ名 | 説明 |
|-----------|-------------|------|
| Cache | wppack/cache | キャッシュインターフェース |
| Database | wppack/database | データベース抽象化 |
| Query | wppack/query | クエリビルダー |
| Security | wppack/security | セキュリティユーティリティ |
| Sanitizer | wppack/sanitizer | データサニタイズ |
| HttpClient | wppack/http-client | HTTPクライアント |
| HttpFoundation | wppack/http-foundation | Request/Response |
| Mailer | wppack/mailer | メール抽象化（Transport基盤含む） |
| AmazonMailer | wppack/amazon-mailer | SES トランスポート実装 |
| Messenger | wppack/messenger | メッセージングバス（SQS/Lambda） |
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
| Wpress | wppack/wpress | .wpress バックアップファイル操作 |

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

wppack/amazon-mailer
    ↓ requires
wppack/mailer
    + async-aws/ses

wppack/amazon-mailer-plugin
    ↓ requires
wppack/amazon-mailer, wppack/hook
```

## 開発ガイドライン

### 言語
- ドキュメント: 日本語
- コード: 英語（変数名、クラス名、コメント）

### PHP要件
- PHP 8.1以上
- PSR-4オートロード

### コーディング規約

**モダンPHPのベストプラクティスに従う。WordPress Coding Standardsは使用しない。**

- PER Coding Style (PSR-12後継) に準拠
- 厳格な型宣言 (`declare(strict_types=1)`)
- finalクラスを優先
- readonly プロパティを活用
- Symfony のパターンに従う
- コンストラクタプロパティプロモーションを活用
- match式を活用
- Named argumentsを適切に使用

### 名前空間

```
WpPack\Component\{Name}\  - コンポーネント
WpPack\Plugin\{Name}\     - プラグイン
```

### 静的解析・CI

```bash
composer phpstan    # 静的解析
composer cs-check   # コードスタイルチェック
composer test       # テスト実行
```

### モノレポ開発フロー
- ルート `composer.json` で全パッケージを管理
- `replace` セクションで自パッケージを宣言
- splitsh-lite で各パッケージリポジトリに分割公開
- GitHub Actions で CI/CD 実行

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
