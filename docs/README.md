# WpPack ドキュメント

WordPress をモダンに扱うためのコンポーネントライブラリ。Symfony にインスパイアされた設計で、WordPress のエコシステムに最適化されたパッケージ群を提供します。

## プロジェクト概要

WpPack は以下の特徴を持つ PHP コンポーネントライブラリです:

- **コンポーネントライブラリ**: フレームワークではなく、必要なパッケージだけを選んで使える
- **Symfony インスパイア**: Symfony のパターンと規約を WordPress に適用
- **WordPress ファースト**: WordPress の仕組みを尊重しつつ、モダンな開発体験を提供
- **サーバレス対応**: AWS Lambda (Bref) + SQS + EventBridge による非同期処理

## ドキュメント構成

### [architecture/](./architecture/) - アーキテクチャ

プロジェクト全体の設計思想と構造。

- [overview.md](./architecture/overview.md) - レイヤー構造と設計原則
- [monorepo.md](./architecture/monorepo.md) - モノレポ構造と開発フロー
- [serverless.md](./architecture/serverless.md) - サーバレスアーキテクチャ

### [components/](./components/) - コンポーネント

各コンポーネントパッケージの詳細ドキュメント。

### [plugins/](./plugins/) - プラグイン

WordPress プラグインパッケージの詳細ドキュメント。

## Getting Started

### 必要要件

- PHP 8.1 以上
- Composer 2.x
- WordPress 6.x
- Action Scheduler 3.x

### インストール

必要なコンポーネントを個別にインストールします:

```bash
# メッセージング基盤
composer require wppack/messenger

# スケジューラー
composer require wppack/scheduler

# プラグイン
composer require wppack/scheduler-plugin
```

### 開発環境セットアップ

```bash
git clone https://github.com/wppack-io/wppack.git
cd wppack
composer install
```

### CI コマンド

```bash
composer phpstan    # 静的解析
composer cs-check   # コードスタイルチェック
composer test       # テスト実行
```
