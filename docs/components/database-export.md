# DatabaseExport コンポーネント

**パッケージ:** `wppack/database-export`
**名前空間:** `WPPack\Component\DatabaseExport\`
**レイヤー:** Feature

WordPress データベースを複数のフォーマット（wpress 互換 SQL、JSON、CSV）でエクスポートするコンポーネントです。MySQL、MariaDB、SQLite、PostgreSQL の各ソース DB に対応しています。

## インストール

```bash
composer require wppack/database-export
```

## 基本的な使い方

```php
use WPPack\Component\Database\DatabaseManager;
use WPPack\Component\Database\SchemaReader\MySQLSchemaReader;
use WPPack\Component\DatabaseExport\DatabaseExporter;
use WPPack\Component\DatabaseExport\ExportConfiguration;
use WPPack\Component\DatabaseExport\RowTransformer\WpOptionsTransformer;
use WPPack\Component\DatabaseExport\RowTransformer\WpUserMetaTransformer;
use WPPack\Component\DatabaseExport\TableFilter\PrefixTableFilter;
use WPPack\Component\DatabaseExport\Writer\WpressSqlWriter;

$db = new DatabaseManager();
$config = new ExportConfiguration(
    dbPrefix: $db->prefix(),
    tablePrefix: 'WPPACK_PREFIX_',
);

$exporter = new DatabaseExporter(
    db: $db,
    schemaReader: new MySQLSchemaReader(),
    writer: new WpressSqlWriter(),
    tableFilter: new PrefixTableFilter($config),
    rowTransformers: [
        new WpOptionsTransformer($config),
        new WpUserMetaTransformer($config),
    ],
);

$sql = $exporter->exportToString($config);
```

## 出力フォーマット

### WpressSqlWriter

All-in-One WP Migration 互換の MySQL SQL ダンプを出力します。

- `SERVMASK_PREFIX_` / `WPPACK_PREFIX_` プレースホルダによるテーブルプレフィックス置換
- 1行1INSERT、バイナリデータは `0x` hex エンコード
- 1000行ごとのトランザクション制御

### JsonWriter

ストリーミング JSON 出力。テーブルごとにカラム名 + 行データ。

### CsvWriter

RFC 4180 準拠の CSV 出力。テーブル区切りマーカー付き。

## マルチサイト対応

`ExportConfiguration::$blogIds` で対象サイトを制御できます。

| blogIds | 動作 |
|---------|------|
| `[]`（空） | ネットワーク全体 |
| `[1]` | メインサイトのみ |
| `[2]` | サブサイト 2 のみ |

## セキュリティデフォルト

- トランジェント（`_transient_*`）はデフォルトで除外
- セッショントークン（`session_tokens`）はデフォルトで除外
- `active_plugins` は空配列 `a:0:{}` にリセット
- `template`/`stylesheet` は空文字にリセット

## 依存関係

- `wppack/database`（必須）
- `wppack/wpress`（suggest — .wpress アーカイブ統合）
