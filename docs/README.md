# WPPack ドキュメント

WordPress をモダンに扱うためのコンポーネントライブラリ。Symfony にインスパイアされた設計で、WordPress のエコシステムに最適化されたパッケージ群を提供します。

## プロジェクト概要

WPPack は、WordPress のグローバル関数・手続き型 API を、型安全な OOP インターフェースでラップするコンポーネントライブラリです。Symfony のパターンを WordPress に持ち込み、`declare(strict_types=1)` の世界で WordPress 開発を行えるようにします。

主な特徴:

- **PHP Attributes による宣言的 API** — イベントリスナー (`#[AsEventListener]`)、ルート、オプション注入 (`#[Option]`) などを Attribute で定義
- **DI コンテナ** — Symfony スタイルのオートワイヤリングとサービス自動検出
- **型安全なラッパー** — WordPress API を `declare(strict_types=1)` で扱える
- **コンポーネント単位の導入** — 必要なパッケージだけ `composer require`
- **クラウドファースト設計** — Lambda / Cloud Functions / Fargate / Aurora Serverless を第一等市民として扱う。ステートレス、gone-away 再接続、OCC リトライ、graceful fallback
- **マルチクラウド対応** — コアは抽象インターフェース、AWS / GCP / Azure は Bridge パッケージで分離

コード例や API リファレンスは各コンポーネントの README を参照してください。トップレベルに重複させず、使いどころのあるページに集約しています。

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
- WordPress 6.3 以上

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

## 設計原則

### Hook vs EventDispatcher

新規実装では **EventDispatcher を優先** してください (`#[AsEventListener]` など)。EventDispatcher は内部で WordPress の `$wp_filter` をバックエンドに利用しており、WordPress フックも型安全に扱えます。`wppack/hook` コンポーネントは既存コードとの互換性のために残されています。

### セキュリティ統合

OAuth 2.0 / OIDC、SAML 2.0、WebAuthn / Passkey は個別実装ではなく、共通の `wppack/security` フレームワークの上に bridge として実装されています。`AbstractAuthenticator` / `TokenStorage` / `AccessDecisionManager` / `UserProvider` を一度実装すれば、プロバイダの追加・切替は bridge 単位で行えます。
