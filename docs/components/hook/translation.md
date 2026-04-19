## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Translation/Subscriber/`

Translation 関連の WordPress フックに対応する Named Hook アトリビュートを提供します。Hook コンポーネントはオプション依存です。

### Action

| アトリビュート | WordPress フック |
|---|---|
| `LoadTextdomainAction` | `load_textdomain` |
| `UnloadTextdomainAction` | `unload_textdomain` |

### Filter

| アトリビュート | WordPress フック |
|---|---|
| `LocaleFilter` | `locale` |
| `DetermineLocaleFilter` | `determine_locale` |
| `GettextFilter` | `gettext` |

### 使用例

```php
use WPPack\Component\Hook\Attribute\Translation\Filter\GettextFilter;

class TranslationCustomizer
{
    #[GettextFilter(priority: 20)]
    public function customizeTranslation(string $translation, string $text, string $domain): string
    {
        if ($domain === 'my-plugin' && $text === 'Submit') {
            return 'Send';
        }

        return $translation;
    }
}
```
