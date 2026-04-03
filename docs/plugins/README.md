# Plugin パッケージ

WordPress プラグインとしてコンポーネントを統合するパッケージ群。各プラグインは対応するコンポーネントパッケージに依存し、WordPress の管理画面・WP-CLI・フック統合を提供します。

## パッケージ一覧

| パッケージ | 説明 | 主な依存 |
|-----------|------|---------|
| [wppack/eventbridge-scheduler-plugin](./eventbridge-scheduler-plugin.md) | EventBridge ベースのスケジューラー | wppack/scheduler, wppack/messenger |
| [wppack/s3-storage-plugin](./s3-storage-plugin.md) | S3 ベースのメディアストレージ | wppack/messenger, wppack/hook, wppack/media |
| [wppack/amazon-mailer-plugin](./amazon-mailer-plugin.md) | Amazon SES メール配信 | wppack/amazon-mailer, wppack/hook |
| [wppack/debug-plugin](./debug-plugin.md) | デバッグツールバー | wppack/debug, wppack/hook |
| [wppack/redis-cache-plugin](./redis-cache-plugin.md) | Redis Object Cache | wppack/cache, wppack/redis-cache |
| [wppack/saml-login-plugin](./saml-login-plugin.md) | SAML 2.0 SSO ログイン | wppack/saml-security, wppack/security |
| [wppack/scim-plugin](./scim-plugin.md) | SCIM 2.0 プロビジョニング | wppack/scim, wppack/security |
| [wppack/oauth-login-plugin](./oauth-login-plugin.md) | OAuth 2.0 / OpenID Connect SSO ログイン | wppack/oauth-security, wppack/security |
| [wppack/monitoring-plugin](./monitoring-plugin.md) | インフラモニタリングダッシュボード | wppack/monitoring, wppack/cloudwatch-monitoring |

## 共通パターン

### 名前空間

すべてのプラグインパッケージは `WpPack\Plugin\{Name}\` 名前空間を使用します:

```
WpPack\Plugin\EventBridgeSchedulerPlugin\
WpPack\Plugin\S3StoragePlugin\
WpPack\Plugin\AmazonMailerPlugin\
```

### プラグインの構造

各プラグインは共通の構造を持ちます:

```
src/Plugin/{Name}/
├── composer.json          # パッケージ定義
├── README.md              # パッケージ README（英語）
├── Plugin.php             # プラグインエントリポイント
├── Admin/                 # 管理画面 UI
│   └── SettingsPage.php
├── Command/               # WP-CLI コマンド
├── Handler/               # メッセージハンドラ
├── Message/               # メッセージ定義
└── WordPress/             # WordPress フック統合
```

### AWS 依存

プラグインパッケージは AWS サービスを利用します。`async-aws/*` パッケージはプラグインまたは対応するコンポーネント（`wppack/amazon-mailer` 等）が依存します:

- `async-aws/scheduler` - EventBridge Scheduler
- `async-aws/sqs` - Amazon SQS
- `async-aws/s3` - Amazon S3
- `async-aws/ses` - Amazon SES（`wppack/amazon-mailer` の依存）

### 環境変数（共通）

```bash
# AWS 認証情報（全プラグイン共通）
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=ap-northeast-1
```
