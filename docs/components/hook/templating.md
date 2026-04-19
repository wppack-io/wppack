## Named Hook 属性

Templating コンポーネントは `the_content` / `the_title` フィルター用の Named Hook 属性も提供します:

```php
use WPPack\Component\Hook\Attribute\Templating\Filter\TheContentFilter;
use WPPack\Component\Hook\Attribute\Templating\Filter\TheTitleFilter;

final class ContentFormatter
{
    #[TheContentFilter(priority: 20)]
    public function formatContent(string $content): string
    {
        return '<div class="formatted">' . $content . '</div>';
    }

    #[TheTitleFilter]
    public function formatTitle(string $title): string
    {
        return trim($title);
    }
}
```
