# WPPack ドキュメント

WPPack の設計思想・コンポーネント仕様・運用ガイドをまとめたドキュメントです。プロジェクト概要と採用理由はリポジトリルートの [README.md](../README.md) を参照してください。

## ドキュメント構成

### [architecture/](./architecture/) - アーキテクチャ

プロジェクト全体の設計思想と構造。4 層モデル、マルチクラウド戦略、サーバーレス対応、モノレポ構造。詳細は [architecture/README.md](./architecture/README.md)。

### [components/](./components/) - コンポーネント

各コンポーネントパッケージの詳細ドキュメント。Infrastructure / Abstraction / Feature / Application の各層に分類。詳細は [components/README.md](./components/README.md)。

### [plugins/](./plugins/) - プラグイン

WordPress プラグインパッケージの詳細ドキュメント。詳細は [plugins/README.md](./plugins/README.md)。

### [guides/](./guides/) - ガイド

実践的な開発ガイド。詳細は [guides/README.md](./guides/README.md)。

### [wordpress/](./wordpress/) - WordPress コア仕様

WordPress 内部実装のリファレンス。詳細は [wordpress/README.md](./wordpress/README.md)。

## Getting Started

### 必要要件

- PHP 8.2 以上
- Composer 2.x
- WordPress 6.7 以上

### インストール

必要なコンポーネントを個別にインストールします:

```bash
# 単独ユーティリティ (既存プラグインに組み込む)
composer require wppack/option

# スタックとしてまとめて
composer require wppack/kernel wppack/dependency-injection wppack/event-dispatcher

# 配布可能プラグイン
composer require wppack/saml-login-plugin
```

各コンポーネントの利用例は、それぞれの `components/{name}/` ドキュメントまたは `src/Component/{Name}/README.md` にあります。

### 開発環境セットアップ

```bash
git clone https://github.com/wppack-io/wppack.git
cd wppack
composer install
docker compose up -d --wait
```

### CI コマンド

```bash
vendor/bin/phpstan analyse                      # 静的解析 (level: 6)
vendor/bin/php-cs-fixer fix --dry-run --diff    # コードスタイルチェック (PER Coding Style)
vendor/bin/phpunit                              # テスト実行
```

コーディング規約・コミットメッセージ形式・コンポーネント追加チェックリストは [coding-standards.md](../coding-standards.md) に、Issue / PR のプロセスは [CONTRIBUTING.md](../CONTRIBUTING.md) に集約しています。
