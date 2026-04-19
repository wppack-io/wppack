# モノレポ構造

## 概要

WPPack はモノレポで管理され、splitsh-lite を使って各パッケージを個別リポジトリに分割公開します。開発はモノレポで行い、ユーザーは Composer で個別パッケージをインストールします。

## ディレクトリ構成

```
wppack/
├── src/
│   ├── Component/
│   │   ├── Messenger/
│   │   │   ├── composer.json
│   │   │   └── src/
│   │   ├── Scheduler/
│   │   │   ├── composer.json
│   │   │   └── src/
│   │   ├── Hook/
│   │   │   ├── composer.json
│   │   │   └── src/
│   │   └── ...
│   └── Plugin/
│       ├── EventBridgeSchedulerPlugin/
│       │   ├── composer.json
│       │   └── src/
│       ├── S3StoragePlugin/
│       │   ├── composer.json
│       │   └── src/
│       └── ...
├── tests/
├── docs/
├── composer.json          # ルート composer.json
├── .github/
│   └── workflows/
│       └── split.yml      # 分割公開ワークフロー
└── ...
```

## splitsh-lite による分割公開

### 仕組み

splitsh-lite はモノレポの特定ディレクトリを、独立した Git リポジトリとして分割公開するツールです。Git の `filter-branch` よりも高速に動作します。

```
モノレポ (wppack/wppack)
  ├── src/Component/Messenger/  →  wppack/messenger
  ├── src/Component/Scheduler/  →  wppack/scheduler
  ├── src/Plugin/EventBridgeSchedulerPlugin/  →  wppack/eventbridge-scheduler-plugin
  └── ...
```

### GitHub Actions split.yml

`split.yml` は、`main` ブランチやタグのプッシュ時に各パッケージを分割公開します。

```yaml
# 概要（簡略化）
on:
  push:
    branches: [main]
    tags: ['v*']

jobs:
  split:
    strategy:
      matrix:
        package:
          - { local: 'src/Component/Messenger', remote: 'wppack/messenger' }
          - { local: 'src/Component/Scheduler', remote: 'wppack/scheduler' }
          - { local: 'src/Plugin/EventBridgeSchedulerPlugin', remote: 'wppack/eventbridge-scheduler-plugin' }
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      # splitsh-lite で分割・プッシュ
      # タグ push 時はタグも同期
```

## Composer 設定

### ルート composer.json

ルートの `composer.json` は開発用です。`replace` セクションにより、モノレポ内での開発時にパッケージの重複インストールを防ぎます。

```json
{
    "name": "wppack/wppack",
    "type": "project",
    "require": {
        "php": ">=8.2"
    },
    "autoload": {
        "psr-4": {
            "WPPack\\Component\\Messenger\\": "src/Component/Messenger/src/",
            "WPPack\\Component\\Scheduler\\": "src/Component/Scheduler/src/",
            "WPPack\\Plugin\\EventBridgeSchedulerPlugin\\": "src/Plugin/EventBridgeSchedulerPlugin/src/"
        }
    },
    "replace": {
        "wppack/messenger": "self.version",
        "wppack/scheduler": "self.version",
        "wppack/eventbridge-scheduler-plugin": "self.version"
    }
}
```

### replace セクションの役割

`replace` は Composer に「このパッケージはルートが提供する」と伝えます。これにより:

- モノレポ内で `composer require wppack/messenger` が不要になる
- パッケージ間の依存（例: `scheduler` → `messenger`）がルートの autoload で解決される
- 開発時はモノレポ内のソースが直接使われる

### 各パッケージの composer.json

各パッケージの `composer.json` は、分割公開後にユーザーが使う設定です。

```json
{
    "name": "wppack/messenger",
    "type": "library",
    "require": {
        "php": ">=8.2"
    },
    "autoload": {
        "psr-4": {
            "WPPack\\Component\\Messenger\\": "src/"
        }
    }
}
```

## パッケージ間の依存ルール

1. **レイヤー制約**: 上位レイヤーから下位レイヤーへの依存のみ許可
2. **循環禁止**: 相互依存・循環依存は禁止
3. **明示的依存**: `composer.json` の `require` に依存先を明記
4. **最小依存**: 必要最小限のパッケージにのみ依存する

```
wppack/eventbridge-scheduler-plugin
    ↓ requires
wppack/scheduler
    ↓ requires
wppack/messenger
```

## 開発フロー

### ブランチ戦略

- `main`: 安定版。splitsh-lite による分割公開のソース
- `feature/*`: 機能開発ブランチ
- `fix/*`: バグ修正ブランチ

### 開発の流れ

```
1. feature/* ブランチを作成
2. モノレポ内で開発・テスト
3. PR を作成、CI でテスト・静的解析
4. main にマージ
5. split.yml が自動で各パッケージリポジトリに分割公開
```

### テスト

```bash
# 全パッケージのテストを実行
vendor/bin/phpunit

# 静的解析
vendor/bin/phpstan analyse

# コードスタイルチェック
vendor/bin/php-cs-fixer fix --dry-run --diff
```

### リリース

1. `main` ブランチでバージョンタグを作成（例: `v1.0.0`）
2. `split.yml` がタグを各パッケージリポジトリに同期
3. Packagist が自動的に新バージョンを検出
