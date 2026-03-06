# Database コンポーネント

WordPress の `$wpdb` を型安全にラップし、例外ベースのエラーハンドリングと `dbDelta()` によるカスタムテーブルのスキーマ管理を提供するコンポーネントです。

## インストール

```bash
composer require wppack/database
```

## 基本的な使い方

```php
use WpPack\Component\Database\DatabaseManager;

$db = new DatabaseManager();

// Doctrine DBAL 風のフェッチ API
$rows = $db->fetchAllAssociative(
    "SELECT * FROM {$db->prefix()}analytics WHERE status = %s",
    'active',
);

$row = $db->fetchAssociative(
    "SELECT * FROM {$db->prefix()}analytics WHERE id = %d",
    $id,
);

$count = $db->fetchOne(
    "SELECT COUNT(*) FROM {$db->prefix()}analytics WHERE status = %s",
    'active',
);

// テーブル操作（自動プレフィックス付与）
$db->insert('analytics', ['name' => 'test', 'status' => 'active']);
$db->update('analytics', ['status' => 'inactive'], ['id' => 1]);
$db->delete('analytics', ['id' => 1]);

// トランザクション
$db->beginTransaction();
try {
    $db->insert('analytics', ['name' => 'tx_test']);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

## ドキュメント

詳細は [docs/components/database/](../../docs/components/database/) を参照してください。
