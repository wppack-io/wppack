# WordPress Shortcode API 仕様

## 1. 概要

Shortcode API は、投稿コンテンツ内の `[shortcode]` タグをプログラムで生成した出力に置換する仕組みです。テーマやプラグインが独自のショートコードタグを定義し、投稿エディタで使用可能にします。

ショートコードの形式:

```
[tag]                    -- 自己完結型（self-closing）
[tag]コンテンツ[/tag]    -- 囲み型（enclosing）
[tag attr="value"]       -- 属性付き
[tag attr="value"]コンテンツ[/tag] -- 属性 + コンテンツ
```

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$shortcode_tags` | `array<string, callable>` | ショートコード名をキー、コールバックを値とする配列 |

## 2. データ構造

### `$shortcode_tags` の構造

```php
$shortcode_tags = [
    'gallery'   => 'gallery_shortcode',          // コア: ギャラリー
    'caption'   => 'img_caption_shortcode',       // コア: キャプション
    'audio'     => 'wp_audio_shortcode',          // コア: オーディオプレーヤー
    'video'     => 'wp_video_shortcode',          // コア: ビデオプレーヤー
    'playlist'  => 'wp_playlist_shortcode',       // コア: プレイリスト
    'embed'     => [$wp_embed, 'run_shortcode'],  // コア: oEmbed
    'my_tag'    => 'my_handler_function',          // プラグイン定義
];
```

### ショートコードコールバックのシグネチャ

```php
/**
 * @param array|string $atts    パースされた属性の配列、または属性なしの場合は空文字列
 * @param string|null  $content 囲み型の場合はコンテンツ、自己完結型の場合は null
 * @param string       $tag     ショートコードタグ名
 * @return string               置換後の HTML 出力
 */
function my_shortcode_handler(array|string $atts, ?string $content, string $tag): string {
    // ...
}
```

### 属性の処理

`shortcode_atts()` でデフォルト値とユーザー指定値をマージします:

```php
function my_shortcode_handler(array|string $atts): string {
    $atts = shortcode_atts([
        'color' => 'red',     // デフォルト値
        'size'  => 'medium',
    ], $atts, 'my_tag');

    // $atts['color'] と $atts['size'] を使用
    return '<div class="' . esc_attr($atts['color']) . '">' . esc_html($atts['size']) . '</div>';
}
```

## 3. API リファレンス

### 登録 / 削除

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_shortcode()` | `(string $tag, callable $callback): void` | ショートコードを登録 |
| `remove_shortcode()` | `(string $tag): void` | ショートコードを削除 |
| `remove_all_shortcodes()` | `(): void` | 全ショートコードを削除 |

```php
// 登録
add_shortcode('my_tag', 'my_shortcode_handler');

// 削除
remove_shortcode('my_tag');

// 全削除
remove_all_shortcodes();
```

### 実行

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `do_shortcode()` | `(string $content, bool $ignore_html = false): string` | コンテンツ内のショートコードを処理 |
| `do_shortcode_tag()` | `(array $m): string\|false` | 正規表現マッチからショートコードを実行（内部使用） |

`do_shortcode()` は `the_content` フィルターに優先度 11 で登録されています（`wpautop` の優先度 10 の直後）:

```php
// WordPress コアでの登録
add_filter('the_content', 'do_shortcode', 11);
add_filter('widget_text_content', 'do_shortcode', 11);
```

### 属性処理

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `shortcode_atts()` | `(array $pairs, array\|string $atts, string $shortcode = ''): array` | デフォルト属性とユーザー属性をマージ |
| `shortcode_parse_atts()` | `(string $text): array\|string` | 属性文字列をパースして配列に変換 |

`shortcode_atts()` の動作:
1. `$atts` 内の未知の属性を除外（`$pairs` に定義されていないキー）
2. `$pairs` のデフォルト値を `$atts` の値で上書き
3. `$shortcode` が指定された場合、`shortcode_atts_{$shortcode}` フィルターを適用

### クエリ / ユーティリティ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `shortcode_exists()` | `(string $tag): bool` | ショートコードが登録済みか確認 |
| `has_shortcode()` | `(string $content, string $tag): bool` | コンテンツに指定ショートコードが含まれるか確認 |
| `get_shortcode_regex()` | `(array $tagnames = null): string` | ショートコードマッチ用の正規表現を取得 |
| `strip_shortcodes()` | `(string $content): string` | コンテンツから全ショートコードを除去 |
| `strip_shortcode_tag()` | `(array $m): string` | 単一のショートコードタグを除去（内部使用） |

## 4. 実行フロー

### do_shortcode() の処理フロー

```
do_shortcode($content, $ignore_html = false)
│
├── $shortcode_tags が空 → $content をそのまま return
│
├── 登録済みタグ名から正規表現を構築
│   └── get_shortcode_regex(array_keys($shortcode_tags))
│
├── $ignore_html === false（デフォルト）
│   └── preg_replace_callback($pattern, 'do_shortcode_tag', $content)
│
├── $ignore_html === true
│   ├── HTML タグ内のショートコードを無視
│   ├── コンテンツを HTML タグとテキストに分割
│   ├── テキスト部分のみに preg_replace_callback を適用
│   └── 結合して返す
│
└── return $content（ショートコード置換済み）
```

### do_shortcode_tag() の処理フロー

```
do_shortcode_tag($m)  // $m は正規表現のマッチ配列
│
├── $m の構造
│   [1] => エスケープ開始（'[' or ''）
│   [2] => タグ名
│   [3] => 属性文字列
│   [4] => 自己完結マーカー（'/' or ''）
│   [5] => コンテンツ（囲み型の場合）
│   [6] => エスケープ終了（']' or ''）
│
├── エスケープチェック
│   └── $m[1] == '[' && $m[6] == ']'
│       → ショートコードをリテラルとして返す（[[tag]] → [tag]）
│
├── $tag = $m[2]
├── $attr = shortcode_parse_atts($m[3])
│
├── apply_filters('pre_do_shortcode_tag', false, $tag, $attr, $m)
│   └── false 以外が返されたら、その値を使用
│
├── $content = $m[5] ?? null
│
├── $output = call_user_func($shortcode_tags[$tag], $attr, $content, $tag)
│
├── apply_filters('do_shortcode_tag', $output, $tag, $attr, $m)
│
└── return $m[1] . $output . $m[6]
```

### 正規表現の構造

`get_shortcode_regex()` が生成する正規表現の構造:

```
\[                       # 開始ブラケット
(\[?)                    # グループ1: エスケープ開始（オプション）
(tag1|tag2|tag3)         # グループ2: 登録済みタグ名の OR パターン
(?![\w-])                # タグ名の境界（否定先読み）
(                        # グループ3: 属性
    [^\]\/]*             #   ']' と '/' 以外
    (?:
        \/(?!\])         #   自己完結でない '/'
        [^\]\/]*         #   続く属性文字列
    )*
)
(?:
    (\/)                 # グループ4: 自己完結の '/' (self-closing)
    \]                   # 終了ブラケット
|
    \]                   # 終了ブラケット
    (?:
        (                # グループ5: コンテンツ（囲み型）
            [^\[]*       #   '[' 以外の文字列
            (?:
                \[(?!\/\2\])  #   閉じタグでない '['
                [^\[]*
            )*
        )
        \[\/\2\]         # 閉じタグ [/tag]
    )?
)
(\]?)                    # グループ6: エスケープ終了（オプション）
```

### 属性パースのフロー

```
shortcode_parse_atts($text)
│
├── 正規表現で属性をマッチ
│   ├── パターン1: name="value"（ダブルクォート）
│   ├── パターン2: name='value'（シングルクォート）
│   ├── パターン3: name=value（クォートなし）
│   ├── パターン4: "value"（名前なしダブルクォート）
│   ├── パターン5: 'value'（名前なしシングルクォート）
│   └── パターン6: value（名前なしクォートなし）
│
├── マッチした属性を配列に格納
│   ├── 名前付き: $atts['name'] = 'value'
│   └── 名前なし: $atts[0] = 'value', $atts[1] = ...（数値インデックス）
│
├── マッチなし → 空文字列を返す
│
└── return $atts
```

## 5. ショートコードのネスト

### ネストの制限

WordPress のショートコード正規表現は**1 レベルのネスト**しかサポートしません。異なるタグのネストは可能ですが、同じタグのネストは不可です。

```php
// OK: 異なるタグのネスト
[outer]
    [inner]コンテンツ[/inner]
[/outer]
// outer のコールバック内で do_shortcode($content) を呼ぶ必要がある

// NG: 同じタグのネスト
[tag]
    [tag]内側[/tag]
[/tag]
// 正規表現が正しくマッチしない
```

囲み型ショートコードの内側でネストされたショートコードを処理するには、コールバック内で `do_shortcode($content)` を明示的に呼び出す必要があります:

```php
add_shortcode('outer', function ($atts, $content) {
    // 内側のショートコードを処理
    $content = do_shortcode($content);
    return '<div class="outer">' . $content . '</div>';
});
```

### エスケープ

ショートコードをリテラルとして表示するには、二重ブラケットでエスケープします:

```
[[my_tag]]        → [my_tag]（表示用）
[[my_tag]content[/my_tag]] → [my_tag]content[/my_tag]（表示用）
```

## 6. フック一覧

### Filter

| フック名 | 引数 | 説明 |
|---|---|---|
| `pre_do_shortcode_tag` | `(false\|string $output, string $tag, array\|string $attr, array $m)` | ショートコード実行前。`false` 以外を返すとコールバックをスキップ |
| `do_shortcode_tag` | `(string $output, string $tag, array\|string $attr, array $m)` | ショートコードの出力をフィルター |
| `shortcode_atts_{$shortcode}` | `(array $out, array $pairs, array $atts, string $shortcode)` | `shortcode_atts()` の結果をフィルター。`$shortcode` は第 3 引数で渡した名前 |
| `strip_shortcodes_tagnames` | `(array $tags_to_remove)` | `strip_shortcodes()` で除去するタグ名をフィルター |
| `no_texturize_shortcodes` | `(array $shortcodes)` | `wptexturize()` 処理を適用しないショートコードをフィルター |
