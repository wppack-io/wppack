# Setting コンポーネント

**パッケージ:** `wppack/setting`
**名前空間:** `WpPack\Component\Setting\`
**レイヤー:** Application

WordPress Settings API をモダンな PHP で扱うためのコンポーネントです。アトリビュートベースの設定ページ定義と Named Hook アトリビュートを提供します。

## インストール

```bash
composer require wppack/setting
```

## 主要クラス

| クラス | 説明 |
|--------|------|
| `AsSettingsPage` | 設定ページを定義するクラスレベルアトリビュート |
| `AbstractSettingsPage` | 設定ページの基底クラス |
| `SettingsConfigurator` | セクション・フィールドを定義するビルダー |
| `SectionDefinition` | セクション定義 |
| `FieldDefinition` | フィールド定義 |
| `SettingsRegistry` | 設定ページの自動登録レジストリ |

## ドキュメント

詳細は [docs/components/setting/](../../../docs/components/setting/) を参照してください。
