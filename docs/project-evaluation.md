# WPPack プロジェクト総合評価

> 評価日: 2026-04-20
> 対象: WPPack モノレポ全体（`1.x` ブランチ）

## プロジェクト概要

| 項目 | 値 |
|---|---|
| コミット数 | 1,282 |
| 開発者 | TSURU（単独） |
| 開発期間 | 2026-02-25 〜 2026-04-20（約2ヶ月） |
| コンポーネント数 | 58 |
| Bridge パッケージ | 25（8領域） |
| プラグイン | 11 |
| 本番コード | 1,437 ファイル / 114,185 LOC |
| テストコード | 738 ファイル / 149,419 LOC（比率 1.31:1） |
| ドキュメント | 395 ファイル / 76,644 行 |
| インターフェース | 122 ファイル |
| PHP 要件 | ^8.2 |
| WordPress 要件 | 6.7 以上 |
| リリースタグ | なし（設計段階） |

---

## 1. 総合評価: A-

WordPress エコシステムにおいて**前例のない規模と設計品質**を持つモダン PHP コンポーネントライブラリ。約2ヶ月で 1,282 コミット、114k LOC の本番コードと 149k LOC のテストコードは、単独開発として驚異的な生産性を示す。

アーキテクチャ設計、コード品質、テスト体制、ドキュメントの充実度はいずれも高水準。特にクラウドファースト / マルチクラウド対応という独自のポジショニングは、WordPress エコシステムで他に類を見ない。直近 1 ヶ月で Database コンポーネントのマルチエンジン対応（SQLite / PostgreSQL / Aurora DSQL）、PostgreSQL schema / search_path サポート、Security bridge 拡充（Passkey）、Monitoring プラグイン追加と、深さ・広さとも拡大している。

**減点要因**: 単独開発者リスク（Bus Factor = 1）、実運用実績なし、コンポーネント成熟度のばらつき、ガバナンス文書（CONTRIBUTING / SECURITY / CHANGELOG）未整備。

---

## 2. 強み

### 2.1 アーキテクチャ設計

- **4層レイヤーモデル**（Infrastructure → Abstraction → Feature → Application）が明確に分離。Symfony や Spring の成熟した設計を WordPress に適応
- **Bridge パターン**: 8領域25パッケージで、コアインターフェースとプロバイダ実装を完全分離
  - Cache: Redis / DynamoDB / Memcached / APCu / ElastiCacheAuth
  - Storage: S3 / Azure Blob / GCS
  - Mailer: SES / Azure / SendGrid
  - Database: SQLite / PostgreSQL / Aurora DSQL / MySQL DataApi / PostgreSQL DataApi
  - Security: SAML / OAuth / Passkey
  - Monitoring: CloudWatch / Cloudflare
  - Messenger: SQS、Scheduler: EventBridge、Logger: Monolog、Templating: Twig
- **コンポーネント独立性**: 58 コンポーネント全てが個別 `composer.json` を持ち、splitsh-lite による個別パッケージ公開を設計。`composer require wppack/option` のように必要なものだけ導入可能
- **`wordpress/core-implementation` 明示宣言**: WordPress 関数を直接呼ぶ全コンポーネントが composer 依存として WP を宣言。downstream の「WP が無い環境で入ってしまう」事故を防止
- **122 のインターフェースファイル**: interface-first design が徹底

### 2.2 コード品質

- 全ファイル `declare(strict_types=1)`
- PHP 8.2+ 機能の積極活用: readonly, enum, コンストラクタプロモーション, match, `#[\SensitiveParameter]`
- **PSR 準拠**: PSR-3, PSR-4, PSR-6/16, PSR-11, PSR-14, PSR-18
- **PHPStan Level 6** + php-cs-fixer (PER Coding Style) を CI で強制
- WordPress プロジェクトで PHPStan Level 6 を全コードベースに適用しているものは極めて稀

### 2.3 テスト体制

- テスト/本番比率 **1.31:1**（149k LOC テスト vs 114k LOC 本番）
- **CI マトリックス: 16 ジョブ**（PHP 8.2 / 8.3 / 8.4 / 8.5 × wpdb / mysql / sqlite / postgresql）全て green
- バックエンドサービス: MySQL 8.0, PostgreSQL 17, Valkey 8（スタンドアロン + クラスタ + Sentinel）, DynamoDB Local, Memcached
- Codecov で **85+ コンポーネント個別カバレッジ追跡**
- PHPUnit 属性スタイル (`#[Test]`, `#[DataProvider]`) を一貫使用
- 統合テスト (`MySQLWpdbIntegrationTest`, `SqliteWpdbIntegrationTest`, `PostgreSQLWpdbIntegrationTest`, `PostgreSQLSearchPathTest`, `MySQLGoneAwayTest`, `PostgreSQLGoneAwayTest` など) で実 DB 動作を担保

### 2.4 ドキュメント

- **395 マークダウンファイル / 76,644 行**
- アーキテクチャ文書（4層モデル、クラウド戦略、モノレポ構造、テスト方針）
- 72+ のコンポーネント README
- 開発ガイド 3本（プラグイン / テーマ / セキュリティ）
- 仕様書 6トピック（Object Cache Pro, S3, SES, wpress, フレームワーク比較等）
- Database コンポーネントは独自の詳細リファレンス、query translation、plugin-comparison の 3 本立て
- `CLAUDE.md` にコーディング規約・設計方針を集約

### 2.5 クラウドファースト / マルチクラウド対応

- WordPress エコシステムで**唯一のマルチクラウド設計**（AWS / GCP / Azure）
- AsyncAWS 採用で軽量なクラウド統合
- **Aurora DSQL 対応**: IAM トークン認証、SigV4 presigned URL、OCC リトライ（指数バックオフ + decorrelated jitter）、120 秒前トークン自動更新
- **RDS Data API 対応**: MySQL / PostgreSQL 両方、エラー分類（Throttling / Timeout / Credentials）、1 MB レスポンス上限の考慮
- **PostgreSQL schema / search_path サポート**: DSN `?search_path=tenant_42,public` で multi-tenant 対応、NUL/newline 注入防御、`current_schema()` ベースの introspection
- サーバーレスフォールバック設計: Messenger（SQS → 同期）、Scheduler（EventBridge → WP-Cron）
- ステートレス接続設計: Database gone-away 再接続（MySQL errno 2006/2013、PostgreSQL fatal disconnect）

### 2.6 開発プロセス

- Conventional Commits 厳格運用（1,282 コミット全て準拠）
- GitHub Actions CI/CD（`.github/workflows/ci.yml` 16 ジョブ + Code Quality + CodeQL）
- モノレポ + splitsh-lite パッケージ分割設計
- Dependabot 有効（composer / npm）

---

## 3. 改善点 / リスク

### 3.1 単独開発者リスク（Bus Factor = 1）

1,282 コミット全てが単一開発者。58 コンポーネント + 25 Bridge + 11 プラグインを 1 人で維持する持続可能性に懸念。CONTRIBUTING.md / CODE_OF_CONDUCT.md / SECURITY.md が未作成。

### 3.2 コンポーネント成熟度のばらつき

コンポーネントの `src/` 内ファイル数に大きな差がある:

| 成熟度 | コンポーネント | src ファイル数 |
|---|---|---|
| 大規模 | Hook (346), Debug (92), Scim (55), Database (52), Security (46), Messenger (36) | 36+ |
| 中規模 | Mailer (30), Query (27), HttpFoundation (25), DI (24), Wpress (24) | 10-35 |
| 小規模 | Admin, Ajax, Nonce, Option, Transient 等 | 1-9 |
| **未実装** | **Block, Comment, Plugin, Theme** | **0** |

未実装の 4 コンポーネントがコンポーネント一覧に掲載されており、「実装済み」と誤解される可能性がある。

Bridge パッケージの成熟度（src ファイル数）:

| Bridge | src |
|---|---|
| Security → OAuth | 43 |
| Security → SAML | 17 |
| Security → Passkey | 10 |
| Cache → Redis | 8 |
| Scheduler → EventBridge | 7 |
| Monitoring → CloudWatch | 7 |
| Database → (Sqlite / PostgreSQL / AuroraDSQL / MySQLDataApi / PostgreSQLDataApi) | 3 each |
| Storage → Azure | 4 |
| Mailer → (Amazon / Azure / SendGrid) | 3-4 each |
| Logger → Monolog | 3 |
| Cache → (DynamoDb / Memcached / APCu) | 2 each |
| Messenger → SQS | 2 |
| Storage → (S3 / GCS) | 2 each |
| Cache → ElastiCacheAuth | 1 |

プラグインの成熟度:

| プラグイン | src | tests |
|---|---|---|
| S3StoragePlugin | 20 | 16 |
| AmazonMailerPlugin | 11 | 9 |
| PasskeyLoginPlugin | 10 | 0 |
| OAuthLoginPlugin | 7 | 6 |
| SamlLoginPlugin | 6 | 6 |
| MonitoringPlugin | 6 | 0 |
| RoleProvisioningPlugin | 6 | 0 |
| RedisCachePlugin | 5 | 5 |
| ScimPlugin | 5 | 5 |
| DebugPlugin | 2 | 2 |
| EventBridgeSchedulerPlugin | 0 | 0 |

### 3.3 実運用検証の不足

全体が「設計中」ステータス。実運用環境でのパフォーマンス、メモリ使用量、WordPress アップデートとの互換性、他プラグインとの共存性が未検証。

### 3.4 ADR（Architecture Decision Records）の不在

重要な設計判断の背景が文書化されていない:
- なぜ Laravel ではなく Symfony ベースか
- なぜ PSR-7 ではなく独自 HttpFoundation か
- Hook コンポーネントに 346 ファイルを集約した理由
- EventDispatcher を推奨しつつ Hook を残す方針

### 3.5 リリース管理の未確立

リリースタグなし、CHANGELOG なし。splitsh-lite による分割公開は設計済みだが、実際のリリースフローが未確立。

### 3.6 WordPress.org 配布の課題

Composer 前提のため WordPress.org 公式プラグインディレクトリへの配布が困難。PHP-Scoper 等の対策が必要だが具体的解決策は未確立。

---

## 4. 競合比較ポジショニング

| 軸 | Acorn | Themosis | WPPack |
|---|---|---|---|
| アプローチ | Laravel を WP に移植 | WP を Laravel 化 | WP API をモダン PHP で再設計 |
| 導入粒度 | フレームワーク一括 | フレームワーク一括 | コンポーネント単位 |
| クラウド統合 | なし | なし | マルチクラウド (AWS/GCP/Azure) |
| Database multi-engine | なし | なし | MySQL / SQLite / PostgreSQL / Aurora DSQL |
| コンパイル済みコンテナ | 不可 | 不可 | 可能（Symfony ContainerBuilder） |
| 静的解析 | なし | なし | PHPStan Level 6 |
| テスト比率 | 不明 | 不明 | 1.31:1 |
| コミュニティ | ~960 stars | ~1.3k stars | 未公開 |

**差別化要因**: コンポーネント粒度、マルチクラウド、multi-engine database、型安全性、WordPress との共存

**劣位要因**: コミュニティ不在、実績不在、学習資源不足、エコシステム連携なし

---

## 5. 推奨事項

### 短期（1-3ヶ月）

1. **MVP パッケージ先行リリース**: 成熟度の高い 5-10 パッケージ（Database, Mailer, Cache, Storage, Debug, Security 等）を先行公開
2. **サンプルアプリケーション**: 実際の WordPress プラグイン/テーマのサンプルを公開
3. **CONTRIBUTING.md / SECURITY.md / CODE_OF_CONDUCT.md**: コントリビュータ受け入れ体制の整備
4. **未実装コンポーネントの明示**: Block, Comment, Plugin, Theme に「未実装」ステータスを付与
5. **プラグインのテスト整備**: PasskeyLoginPlugin / MonitoringPlugin / RoleProvisioningPlugin のテスト未着手

### 中期（3-6ヶ月）

6. **ADR 導入**: 主要な設計判断を `/docs/adr/` に文書化
7. **ベンチマーク公開**: DI コンテナ、Cache Bridge、Mailer Transport、Database multi-engine の性能測定
8. **リリース自動化**: splitsh-lite + release-please による自動リリースフロー
9. **WordPress.org 配布戦略**: PHP-Scoper 等によるパッケージング方針の確立

### 長期（6-12ヶ月）

10. **コミュニティ形成**: GitHub Discussions / Discord、技術ブログ / カンファレンス発表
11. **エンタープライズ検証**: 高トラフィック WordPress サイトでの dogfooding
12. **プラグインポートフォリオ完成**: EventBridgeSchedulerPlugin 等の実装完了

---

## 6. 総括

WPPack は WordPress API を「置換」ではなく「再設計」する、WordPress 20年の歴史で初の「API 再設計型」アプローチ。技術的品質は既に高水準にあり、直近 1 ヶ月で Database multi-engine 対応、PostgreSQL schema サポート、Security bridge 拡充と、深さ・広さとも拡大している。成否は**コミュニティ形成と実ユーザー獲得**にかかっている。完璧を目指すより、成熟したコンポーネントの先行リリースでフィードバックループを回すことが最優先。
