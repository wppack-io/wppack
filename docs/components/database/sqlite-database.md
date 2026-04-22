# SQLite Database Bridge

**パッケージ:** `wppack/sqlite-database`
**名前空間:** `WPPack\Component\Database\Bridge\Sqlite\`
**Category:** Data

WPPack Database コンポーネントの SQLite ドライバ実装。`db.php` drop-in と組み合わせると、WordPress の MySQL クエリを AST レベルで SQLite 方言に翻訳して実行します。ローカル開発・CI・小規模サイト・WP-CLI などで MySQL をインストールせずに WordPress を動かせます。

## インストール

```bash
composer require wppack/sqlite-database
```

PHP の `ext-pdo_sqlite` 拡張が必要です (PHP 標準同梱)。

## DSN 設定

```php
// wp-config.php

// ファイルベース
define('DATABASE_DSN', 'sqlite:///path/to/database.db');

// In-memory（テスト用）
define('DATABASE_DSN', 'sqlite:///:memory:');

// 相対パス（wp-content/ 配下に保存）
define('DATABASE_DSN', 'sqlite://./wp-content/database.db');
```

### DSN 書式

```
sqlite://<path>
```

パス部分は絶対パス (`/` 始まり) または相対パス。`:memory:` を指定すると in-memory database になります (プロセス終了で消える)。

## クエリ翻訳

`db.php` drop-in と組み合わせた場合、WordPress が生成する MySQL クエリは `SqliteQueryTranslator` によって SQLite 方言に自動変換されます。主な変換:

| MySQL | SQLite |
|-------|--------|
| `NOW()` | `datetime('now')` |
| `AUTO_INCREMENT` | `AUTOINCREMENT` |
| `BIGINT` / `INT(N)` | `INTEGER` |
| `VARCHAR(N)` | `TEXT` |
| `DATETIME` | `TEXT` |
| `LIMIT offset, count` | `LIMIT count OFFSET offset` |
| `INSERT IGNORE` | `INSERT OR IGNORE` |
| `ON DUPLICATE KEY UPDATE` | `ON CONFLICT DO UPDATE SET` |

翻訳は `phpmyadmin/sql-parser` の AST に対して実行され、文字列リテラルは変換対象外です。

### User-Defined Functions

15 個の MySQL 互換関数を SQLite UDF として登録します: `REGEXP`, `CONCAT`, `CONCAT_WS`, `CHAR_LENGTH`, `FIELD`, `MD5`, `LOG`, `UNHEX`, `FROM_BASE64`, `TO_BASE64`, `INET_ATON`, `INET_NTOA`, `GET_LOCK`, `RELEASE_LOCK`, `IS_FREE_LOCK`。

詳細は [src/Component/Database/Bridge/Sqlite/README.md](../../../src/Component/Database/Bridge/Sqlite/README.md) と [query-translation.md](./query-translation.md) 参照。

## ユースケース

- **ローカル開発環境**: MySQL セットアップ不要で `wp-env` 相当の即席環境
- **CI**: コンテナ不要・依存なしでテスト実行
- **小規模サイト**: ~1万 posts 程度までは実用的
- **WP-CLI スクリプト**: in-memory DSN で fixture ベースのテスト

MySQL / PostgreSQL との互換性には翻訳レイヤの制限あり (complex JOIN / window function など)。本番スケールでは Aurora / PostgreSQL を推奨。

## 関連ドキュメント

- [Database component](./README.md) — コア抽象化
- [Query translation deep-dive](./query-translation.md)
- [Plugin comparison](./plugin-comparison.md) — 既存 SQLite プラグイン (SQLite Object Cache 等) との比較

## License

MIT
