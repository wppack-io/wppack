# WordPress サニタイゼーション / エスケーピング仕様

## 1. 概要

WordPress のサニタイゼーション（入力浄化）とエスケーピング（出力エスケープ）は、セキュリティの根幹を成すレイヤーです。主に `formatting.php` と `kses.php` に実装され、以下の原則に基づいています:

- **入力のサニタイズ**: データを保存・処理する前に浄化する（Sanitize early）
- **出力のエスケープ**: データを表示する直前にエスケープする（Escape late）
- **KSES**: HTML フィルタリング。許可タグ・属性のホワイトリストに基づき不正な HTML を除去

### 主要ファイル

| ファイル | 説明 |
|---|---|
| `wp-includes/formatting.php` | テキスト処理・サニタイゼーション・エスケーピング関数群 |
| `wp-includes/kses.php` | KSES（Kses Strips Evil Scripts）HTML フィルタリング |

### KSES のグローバル変数

| 変数 | 型 | 説明 |
|---|---|---|
| `$allowedposttags` | `array` | 投稿コンテンツで許可される HTML タグ・属性 |
| `$allowedtags` | `array` | コメント等で許可される HTML タグ・属性（制限的） |
| `$allowedentitynames` | `string[]` | 許可される HTML エンティティ名 |
| `$allowedxmlentitynames` | `string[]` | 許可される XML エンティティ名 |
| `$wp_kses_allowed_html` | `array` | コンテキスト別の許可タグキャッシュ |

## 2. データ構造

### KSES 許可タグの構造

```php
$allowedposttags = [
    'a' => [
        'href'   => true,    // 属性を許可
        'title'  => true,
        'rel'    => true,
        'target' => true,
        'class'  => true,
    ],
    'img' => [
        'src'    => true,
        'alt'    => true,
        'width'  => true,
        'height' => true,
        'class'  => true,
    ],
    'p'      => ['class' => true, 'style' => true],
    'br'     => [],
    'strong'  => [],
    'em'     => [],
    'div'    => ['class' => true, 'style' => true, 'id' => true],
    'span'   => ['class' => true, 'style' => true],
    // ... 多数のタグ
];
```

各タグのキーは属性名、値は以下のいずれか:
- `true` — 属性を許可（値は任意）
- `array` — 値のバリデーションルール（`values`, `value_prefix` 等）

### コンテキスト別の許可タグ

`wp_kses_allowed_html()` は以下のコンテキストに対応:

| コンテキスト | 説明 |
|---|---|
| `'post'` | 投稿コンテンツ（`$allowedposttags`） |
| `'user_description'` | ユーザープロフィール |
| `'pre_user_description'` | ユーザープロフィール（保存前） |
| `'strip'` | すべてのタグを除去 |
| `'entities'` | エンティティのみ許可 |
| `'data'` | データ属性付き |
| (配列) | カスタム許可タグ配列 |

## 3. API リファレンス

### サニタイゼーション関数（入力浄化）

#### テキスト・文字列

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `sanitize_text_field()` | `(string $str): string` | テキストフィールドのサニタイズ。タグ除去・特殊文字エンコード |
| `sanitize_textarea_field()` | `(string $str): string` | テキストエリアのサニタイズ。改行を保持 |
| `sanitize_title()` | `(string $title, string $fallback_title = '', string $context = 'save'): string` | タイトルのサニタイズ（スラッグ生成向け） |
| `sanitize_title_with_dashes()` | `(string $title, string $raw_title = '', string $context = 'display'): string` | タイトルを URL スラッグに変換 |
| `sanitize_file_name()` | `(string $filename): string` | ファイル名のサニタイズ |
| `sanitize_user()` | `(string $username, bool $strict = false): string` | ユーザー名のサニタイズ |
| `sanitize_key()` | `(string $key): string` | キー文字列のサニタイズ（小文字英数字・ダッシュ・アンダースコア） |
| `sanitize_hex_color()` | `(string $color): string\|void` | 16 進数カラーコードの検証・サニタイズ |
| `sanitize_hex_color_no_hash()` | `(string $color): string\|null` | `#` なしの 16 進数カラーコード |
| `sanitize_mime_type()` | `(string $mime_type): string` | MIME タイプのサニタイズ |

#### HTML

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_kses()` | `(string $content, array\|string $allowed_html, string[] $allowed_protocols = []): string` | 許可タグ/属性以外の HTML を除去 |
| `wp_kses_post()` | `(string $data): string` | 投稿コンテンツ向け KSES（`$allowedposttags` 使用） |
| `wp_kses_data()` | `(string $data): string` | 一般データ向け KSES |
| `wp_kses_one_attr()` | `(string $attr, string $element): string` | 単一 HTML 属性のフィルタリング |
| `wp_kses_no_null()` | `(string $content, array $options = null): string` | NULL バイトの除去 |
| `wp_filter_kses()` | `(string $data): string` | `$allowedtags` でフィルタリング |
| `wp_filter_post_kses()` | `(string $data): string` | `$allowedposttags` でフィルタリング |
| `wp_filter_nohtml_kses()` | `(string $data): string` | すべての HTML を除去 |
| `wp_strip_all_tags()` | `(string $text, bool $remove_breaks = false): string` | すべての HTML タグを除去（`strip_tags` 強化版） |

#### URL

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `sanitize_url()` | `(string $url, string[] $protocols = null): string` | URL のサニタイズ |
| `esc_url_raw()` | `(string $url, string[] $protocols = null): string` | DB 保存用 URL サニタイズ（`sanitize_url` のエイリアス） |
| `wp_kses_bad_protocol()` | `(string $content, string[] $allowed_protocols): string` | 不正なプロトコルの除去 |

#### 数値

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `absint()` | `(mixed $maybeint): int` | 非負整数に変換（`abs(intval($maybeint))`） |
| `intval()` | PHP 組み込み | 整数に変換 |

#### メールアドレス

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `sanitize_email()` | `(string $email): string` | メールアドレスのサニタイズ |
| `is_email()` | `(string $email, bool $deprecated = false): string\|false` | メールアドレスの検証 |

### エスケーピング関数（出力エスケープ）

#### HTML エスケープ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `esc_html()` | `(string $text): string` | HTML コンテキストでのエスケープ。`<`, `>`, `&`, `"`, `'` を変換 |
| `esc_attr()` | `(string $text): string` | HTML 属性値のエスケープ。`esc_html()` と同じ文字を変換 |
| `esc_textarea()` | `(string $text): string` | `<textarea>` 内のエスケープ |
| `esc_xml()` | `(string $text): string` | XML コンテキストでのエスケープ |
| `wp_kses_post()` | `(string $data): string` | 投稿コンテンツのフィルタリング |

#### URL エスケープ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `esc_url()` | `(string $url, string[] $protocols = null, string $_context = 'display'): string` | URL のエスケープ（表示用）。`&` → `&amp;` 等 |
| `esc_url_raw()` | `(string $url, string[] $protocols = null): string` | URL のエスケープ（DB 保存用）。表示用の変換なし |

#### JavaScript エスケープ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `esc_js()` | `(string $text): string` | JavaScript 文字列内のエスケープ |
| `wp_json_encode()` | `(mixed $value, int $options = 0, int $depth = 512): string\|false` | JSON エンコード（`json_encode` のラッパー） |

#### SQL エスケープ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `$wpdb->prepare()` | `(string $query, mixed ...$args): string\|void` | プリペアドステートメント形式の SQL エスケープ |
| `$wpdb->esc_like()` | `(string $text): string` | `LIKE` 句のワイルドカード（`%`, `_`, `\`）のエスケープ |
| `esc_sql()` | `(string\|array $data): string\|array` | SQL エスケープ（`$wpdb->_real_escape()` の短縮） |

#### 翻訳 + エスケープ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `esc_html__()` | `(string $text, string $domain = 'default'): string` | 翻訳 + HTML エスケープ |
| `esc_html_e()` | `(string $text, string $domain = 'default'): void` | 翻訳 + HTML エスケープ + echo |
| `esc_html_x()` | `(string $text, string $context, string $domain = 'default'): string` | コンテキスト付き翻訳 + HTML エスケープ |
| `esc_attr__()` | `(string $text, string $domain = 'default'): string` | 翻訳 + 属性エスケープ |
| `esc_attr_e()` | `(string $text, string $domain = 'default'): void` | 翻訳 + 属性エスケープ + echo |
| `esc_attr_x()` | `(string $text, string $context, string $domain = 'default'): string` | コンテキスト付き翻訳 + 属性エスケープ |

## 4. 実行フロー

### `wp_kses()` の処理フロー

```
wp_kses($content, $allowed_html, $allowed_protocols)
│
├── $allowed_html が文字列の場合
│   └── wp_kses_allowed_html($allowed_html) でコンテキスト変換
│       └── 'post' → $allowedposttags, 'strip' → [] 等
│
├── $allowed_protocols が空の場合
│   └── wp_allowed_protocols() のデフォルト値を使用
│       └── ['http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', ...]
│
├── wp_kses_no_null($content)
│   └── NULL バイト除去
│
├── wp_kses_normalize_entities($content)
│   └── HTML エンティティを正規化
│       ├── 不正な数値参照を修正
│       └── 許可されていない名前付きエンティティを除去
│
├── wp_kses_hook($content, $allowed_html, $allowed_protocols)
│   └── 【フィルター】 pre_kses ($content, $allowed_html, $allowed_protocols)
│
├── wp_kses_split($content, $allowed_html, $allowed_protocols)
│   │
│   ├── 正規表現で HTML タグ / コメント / CDATA を分割
│   │
│   ├── 各タグに対して wp_kses_split2()
│   │   │
│   │   ├── HTML コメントの場合
│   │   │   └── 除去（空文字を返す）
│   │   │
│   │   ├── 閉じタグの場合 (</tag>)
│   │   │   ├── タグが $allowed_html に含まれるか
│   │   │   ├── YES → '</tag>' を返す
│   │   │   └── NO → 除去
│   │   │
│   │   └── 開きタグの場合 (<tag attr="val">)
│   │       │
│   │       ├── wp_kses_attr($tag, $attr_string, $allowed_html, $allowed_protocols)
│   │       │   │
│   │       │   ├── タグが $allowed_html に含まれるか
│   │       │   │   └── NO → 除去
│   │       │   │
│   │       │   ├── wp_kses_hair($attr_string)
│   │       │   │   └── 属性文字列をパースして name/value ペアに分解
│   │       │   │
│   │       │   ├── 各属性に対して
│   │       │   │   ├── 属性名が許可リストに含まれるか
│   │       │   │   ├── wp_kses_check_attr_val() で値を検証
│   │       │   │   ├── wp_kses_bad_protocol() でプロトコルを検証
│   │       │   │   └── 【フィルター】 safe_style_css ($styles) (style 属性の場合)
│   │       │   │
│   │       │   └── 検証済み属性でタグを再構築
│   │       │
│   │       └── return 構築されたタグ文字列
│   │
│   └── 非タグテキストはそのまま通過
│
└── return フィルタリング済みの $content
```

### `esc_html()` の処理フロー

```
esc_html($text)
│
├── 【フィルター】 pre_esc_html ($text)
│   └── null 以外を返すとショートサーキット
│
├── _wp_specialchars($text, ENT_QUOTES, false, true)
│   │
│   ├── htmlspecialchars($text, $quote_style, 'UTF-8', $double_encode)
│   │   └── <  → &lt;
│   │   └── >  → &gt;
│   │   └── &  → &amp;
│   │   └── "  → &quot;
│   │   └── '  → &#039;
│   │
│   └── return 変換済み文字列
│
├── 【フィルター】 esc_html ($safe_text, $text)
│
└── return $safe_text
```

### `sanitize_text_field()` の処理フロー

```
sanitize_text_field($str)
│
├── _sanitize_text_fields($str, false)
│   │
│   ├── wp_check_invalid_utf8($str)
│   │   └── 不正な UTF-8 シーケンスの検出・除去
│   │
│   ├── wp_kses_no_null($str)
│   │   └── NULL バイトの除去
│   │
│   ├── preg_replace: タグを単一スペースに置換
│   │   └── /<[^>]*>(\\s|&nbsp;)*/ → ' '
│   │
│   ├── wp_strip_all_tags($str)
│   │   └── すべての HTML タグを除去
│   │
│   ├── 改行の処理
│   │   └── $filtered = false の場合は改行を除去
│   │   └── (sanitize_textarea_field は改行を保持)
│   │
│   ├── 連続スペースの除去
│   │   └── preg_replace('/[\r\n\t ]+/', ' ')
│   │
│   └── trim($str)
│
├── 【フィルター】 sanitize_text_field ($filtered, $str)
│
└── return $filtered
```

### `esc_url()` の処理フロー

```
esc_url($url, $protocols, $_context)
│
├── 【フィルター】 pre_esc_url ($url)
│   └── null 以外を返すとショートサーキット
│
├── 空文字チェック → 空なら即座に return
│
├── $url = trim($url)
│
├── NULL バイト除去
│
├── プロトコルのチェック
│   ├── ':' が含まれるがプロトコルが省略されている場合
│   │   └── プロトコルを補完（例: '//example.com' → 'http://example.com'）
│   ├── wp_kses_bad_protocol($url, $protocols)
│   │   └── 許可プロトコル以外を除去
│   └── javascript:, vbscript: 等の危険なプロトコルをブロック
│
├── URL のパース・再構築
│   ├── 不正な文字のパーセントエンコード
│   └── 制御文字の除去
│
├── $_context === 'display' の場合
│   ├── $url = str_replace('&amp;', '&', $url)  // 二重エスケープ防止
│   └── $url = str_replace('&', '&amp;', $url)  // & をエスケープ
│
├── 【フィルター】 clean_url ($url, $original_url, $_context)
│
└── return $url
```

## 5. フック一覧

### フィルター

#### KSES 関連

| フック名 | パラメータ | 説明 |
|---|---|---|
| `pre_kses` | `(string $content, array $allowed_html, string[] $allowed_protocols)` | `wp_kses()` 処理前のフィルタリング |
| `wp_kses_allowed_html` | `(array $tags, string $context)` | コンテキスト別の許可タグを変更 |
| `safe_style_css` | `(string[] $styles)` | 許可する CSS プロパティ名のリスト |
| `safecss_filter_attr_allow_css` | `(bool $allow_css, string $css_test_string)` | CSS 属性の許可判定 |

#### エスケープ関連

| フック名 | パラメータ | 説明 |
|---|---|---|
| `pre_esc_html` | `(string $text)` | `esc_html()` 処理前 |
| `esc_html` | `(string $safe_text, string $text)` | `esc_html()` 処理後 |
| `pre_esc_attr` | `(string $text)` | `esc_attr()` 処理前 |
| `esc_attr` | `(string $safe_text, string $text)` | `esc_attr()` 処理後 |
| `clean_url` | `(string $url, string $original_url, string $context)` | `esc_url()` 処理後 |
| `js_escape` | `(string $safe_text, string $text)` | `esc_js()` 処理後 |

#### サニタイゼーション関連

| フック名 | パラメータ | 説明 |
|---|---|---|
| `sanitize_text_field` | `(string $filtered, string $str)` | `sanitize_text_field()` 処理後 |
| `sanitize_textarea_field` | `(string $filtered, string $str)` | `sanitize_textarea_field()` 処理後 |
| `sanitize_title` | `(string $title, string $raw_title, string $context)` | `sanitize_title()` 処理後 |
| `sanitize_file_name` | `(string $filename, string $raw_filename)` | `sanitize_file_name()` 処理後 |
| `sanitize_file_name_chars` | `(string[] $special_chars)` | ファイル名から除去する特殊文字リスト |
| `sanitize_user` | `(string $username, string $raw_username, bool $strict)` | `sanitize_user()` 処理後 |
| `sanitize_key` | `(string $key, string $raw_key)` | `sanitize_key()` 処理後 |
| `sanitize_email` | `(string $email, string $raw_email)` | `sanitize_email()` 処理後 |
| `sanitize_html_class` | `(string $sanitized, string $raw_class, string $fallback)` | `sanitize_html_class()` 処理後 |
| `sanitize_option_{$option}` | `(mixed $value, string $option, mixed $original_value)` | オプション値のサニタイズ |

## 6. KSES のセキュリティモデル

### 許可リスト方式

KSES はブロックリスト（危険なものを列挙）ではなく、**許可リスト**（安全なものを列挙）方式を採用しています。

1. **タグレベル**: 許可リストにないタグは除去
2. **属性レベル**: 許可リストにない属性は除去
3. **プロトコルレベル**: 許可リストにないプロトコルは除去
4. **CSS レベル**: `style` 属性内の CSS プロパティも許可リストでフィルタリング

### `style` 属性の CSS フィルタリング

`safecss_filter_attr()` は `style` 属性の値を個別の CSS プロパティに分解し、`safe_style_css` フィルターの許可リストに含まれるプロパティのみを残します。

デフォルトで許可される CSS プロパティ:

```
background, background-color, background-image, background-position,
background-repeat, background-size, border, border-bottom, border-color,
border-left, border-radius, border-right, border-style, border-top,
border-width, clear, color, cursor, direction, display, float, font,
font-family, font-size, font-style, font-variant, font-weight, height,
letter-spacing, line-height, list-style, margin, max-height, max-width,
min-height, min-width, opacity, overflow, padding, text-align,
text-decoration, text-indent, text-transform, vertical-align,
white-space, width, word-spacing, writing-mode, ...
```

### ユーザー権限と KSES

`unfiltered_html` ケイパビリティを持たないユーザー（デフォルトでは管理者・スーパー管理者以外）は、投稿保存時に `wp_filter_post_kses()` が適用されます。管理者はこのフィルタリングをバイパスできます。

マルチサイトでは `DISALLOW_UNFILTERED_HTML` 定数で管理者も含めて KSES を強制できます:

```php
define('DISALLOW_UNFILTERED_HTML', true);
```

### `wp_kses` vs `esc_html` の使い分け

| 関数 | 用途 | 動作 |
|---|---|---|
| `esc_html()` | テキストを HTML コンテキストに安全に埋め込む | 全ての HTML 特殊文字をエンティティに変換 |
| `wp_kses_post()` | リッチテキスト（HTML を含む投稿）のフィルタリング | 許可リスト外のタグ・属性のみ除去、安全な HTML は保持 |
| `wp_strip_all_tags()` | プレーンテキストの抽出 | すべてのタグを除去 |
