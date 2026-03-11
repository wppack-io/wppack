# Debug Component Demos

Debug コンポーネントの各機能をブラウザで確認できるデモページ集です。

## 起動方法

```bash
php -S localhost:8080 -t src/Component/Debug/demo/
```

## デモ一覧

### exception.php — 例外デバッグページ

例外チェーン（PDOException → RuntimeException）を含むデバッグページ。

- コードスニペット（throw 行ハイライト）
- Stack Trace（アコーディオン展開でコード表示）
- Previous タブ（例外チェーン表示）
- Request / Environment / Performance タブ

```
http://localhost:8080/exception.php
```

### wp-die.php — wp_die() ハンドリング

`WpDieHandler` が `wp_die()` を横取りしてデバッグページを描画するデモ。`?scenario` パラメータでシナリオを切り替え。

| シナリオ | URL | 表示クラス | HTTP |
|---------|-----|----------|------|
| 権限不足 | `?scenario=permission` | `WP_Error (forbidden)` | 403 |
| DB 接続エラー | `?scenario=db` | `WP_Error (db_connect_fail)` | 500 |
| Nonce 失敗 | `?scenario=nonce` | `wp_die()` | 403 |
| 汎用エラー | `?scenario=default` | `wp_die()` | 500 |

```
http://localhost:8080/wp-die.php?scenario=permission
http://localhost:8080/wp-die.php?scenario=db
http://localhost:8080/wp-die.php?scenario=nonce
```

### toolbar.php — デバッグツールバー

全 DataCollector をフェイクデータで表示するツールバーデモ。L字レイアウト（左サイドバー + コンテンツエリア + ボトムバー）で Gutenberg スタイルのデザイン。

```
http://localhost:8080/toolbar.php
```
