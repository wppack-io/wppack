# WordPress フックシステム仕様

## 1. 概要

WordPress のフックシステムは、コアの実行フロー上にプラグインやテーマが介入するための仕組みです。**Action**（アクション）と **Filter**（フィルター）の 2 種類がありますが、内部的には同一の `WP_Hook` クラスで管理されています。

フックシステムは以下の 3 つのグローバル変数で状態を管理します:

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_filter` | `WP_Hook[]` | フック名をキーとした `WP_Hook` インスタンスの配列。Action と Filter の両方を格納 |
| `$wp_actions` | `int[]` | フック名をキーとした Action の発火回数カウンター |
| `$wp_filters` | `int[]` | フック名をキーとした Filter の適用回数カウンター |
| `$wp_current_filter` | `string[]` | 現在実行中のフック名のスタック。ネスト実行を追跡 |

`$wp_actions` と `$wp_filters` は独立したカウンターです。`do_action()` は `$wp_actions` をインクリメントし、`apply_filters()` は `$wp_filters` をインクリメントします。

## 2. データ構造

### WP_Hook クラス

WordPress 4.7 で導入された `final` クラスで、`Iterator` と `ArrayAccess` を実装しています。

```php
final class WP_Hook implements Iterator, ArrayAccess {
    public  $callbacks        = [];   // コールバック配列
    protected $priorities     = [];   // ソート済み優先度配列
    private $iterations       = [];   // アクティブなイテレーションの優先度キースタック
    private $current_priority = [];   // 各イテレーションの現在の優先度
    private $nesting_level    = 0;    // 再帰呼び出しの深さ
    private $doing_action     = false; // Action 実行中フラグ
}
```

### `$callbacks` の構造

```
$callbacks = [
    $priority => [
        $unique_id => [
            'function'      => callable,  // コールバック関数
            'accepted_args' => int,       // 受け取る引数の数
        ],
        ...
    ],
    ...
];
```

- 第 1 レベルのキーは優先度（int）
- 第 2 レベルのキーは `_wp_filter_build_unique_id()` が生成するユニーク ID
- 各エントリはコールバックと受け取り引数数のペア

### `$priorities` 配列

`$callbacks` のキーをソート済みで保持する配列です。コールバックの追加・削除時に `array_keys($this->callbacks)` で同期されます。イテレーション時にこの配列を走査することで、優先度順の実行を実現しています。

### イテレーション管理プロパティ

| プロパティ | 説明 |
|---|---|
| `$iterations` | ネストレベルごとの優先度キー配列。PHP の配列ポインタで現在位置を追跡 |
| `$current_priority` | ネストレベルごとの現在処理中の優先度 |
| `$nesting_level` | 再帰呼び出しの深さ。`apply_filters()` 開始時にインクリメント、終了時にデクリメント |
| `$doing_action` | `do_action()` から呼ばれた場合に `true`。Filter と Action の挙動を分岐 |

## 3. API リファレンス

### 登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_filter()` | `(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true` | フィルターコールバックを登録 |
| `add_action()` | `(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): true` | アクションコールバックを登録 |

`add_action()` は `add_filter()` のエイリアスです:

```php
function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
    return add_filter($hook_name, $callback, $priority, $accepted_args);
}
```

`add_filter()` は `$wp_filter[$hook_name]` に `WP_Hook` インスタンスが存在しなければ新規作成し、`WP_Hook::add_filter()` に委譲します。常に `true` を返します（コールバックの妥当性は検証しません）。

### 実行 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `apply_filters()` | `(string $hook_name, mixed $value, mixed ...$args): mixed` | フィルターを適用し、変換された値を返す |
| `do_action()` | `(string $hook_name, mixed ...$arg): void` | アクションを実行（戻り値なし） |
| `apply_filters_ref_array()` | `(string $hook_name, array $args): mixed` | 引数を配列で渡す `apply_filters()` |
| `do_action_ref_array()` | `(string $hook_name, array $args): void` | 引数を配列で渡す `do_action()` |

### 削除 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `remove_filter()` | `(string $hook_name, callable $callback, int $priority = 10): bool` | フィルターコールバックを削除 |
| `remove_action()` | `(string $hook_name, callable $callback, int $priority = 10): bool` | アクションコールバックを削除 |
| `remove_all_filters()` | `(string $hook_name, int\|false $priority = false): true` | 全コールバックを削除 |
| `remove_all_actions()` | `(string $hook_name, int\|false $priority = false): true` | 全アクションコールバックを削除 |

`remove_action()` は `remove_filter()` のエイリアスです。削除には登録時と同じ `$callback` と `$priority` が必要です。`$priority` のデフォルトは `10` です。

`remove_all_actions()` は `remove_all_filters()` のエイリアスです。`$priority` を指定すると、その優先度のコールバックのみ削除します。

### クエリ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `has_filter()` | `(string $hook_name, callable\|false $callback = false, int\|false $priority = false): bool\|int` | フィルター登録の確認 |
| `has_action()` | `(string $hook_name, callable\|false $callback = false, int\|false $priority = false): bool\|int` | アクション登録の確認 |
| `did_action()` | `(string $hook_name): int` | アクションの発火回数を返す |
| `did_filter()` | `(string $hook_name): int` | フィルターの適用回数を返す |
| `current_filter()` | `(): string\|false` | 現在実行中のフック名を返す |
| `current_action()` | `(): string\|false` | 現在実行中のアクション名を返す |
| `doing_filter()` | `(?string $hook_name = null): bool` | 指定フィルターが実行中か判定 |
| `doing_action()` | `(?string $hook_name = null): bool` | 指定アクションが実行中か判定 |

`has_action()` は `has_filter()` のエイリアスです。`has_filter()` の戻り値:

- `$callback` 省略時: 登録があれば `true`、なければ `false`
- `$callback` 指定時: 登録されていればその優先度（`int`）、なければ `false`
- `$callback` + `$priority` 指定時: その優先度に登録されていれば `true`、なければ `false`

> [!NOTE]
> `$callback` 指定時は優先度 `0` が返る場合があります。`has_filter() === false` で比較してください。

`current_action()` は `current_filter()` のエイリアスです。`doing_action()` は `doing_filter()` のエイリアスです。いずれも `$wp_current_filter` スタックを参照します。

### 非推奨フック API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `apply_filters_deprecated()` | `(string $hook_name, array $args, string $version, string $replacement = '', string $message = ''): mixed` | 非推奨通知付きフィルター適用 |
| `do_action_deprecated()` | `(string $hook_name, array $args, string $version, string $replacement = '', string $message = ''): void` | 非推奨通知付きアクション実行 |

これらはコールバックが登録されている場合のみ `_deprecated_hook()` を呼び出し、その後 `_ref_array` バリアントに委譲します。

## 4. 実行フロー詳細

### `apply_filters()` のフロー

```
apply_filters('my_filter', $value, $arg2, $arg3)
│
├── $wp_filters['my_filter']++          // 適用回数カウンター
│
├── 'all' フックが存在する場合
│   ├── $wp_current_filter[] = 'my_filter'
│   └── _wp_call_all_hook($all_args)
│
├── $wp_filter['my_filter'] が未登録なら $value を即座に return
│
├── $wp_current_filter[] = 'my_filter'  // 実行スタックに追加
│
├── $args = [$value, $arg2, $arg3]      // 引数配列を構築
│
├── WP_Hook::apply_filters($value, $args)
│   ├── $nesting_level++
│   ├── $iterations[$level] = $priorities  // 優先度配列をコピー
│   │
│   ├── do {  // 優先度ごとのループ
│   │   ├── foreach ($callbacks[$priority] as $the_) {
│   │   │   ├── $args[0] = $value         // ※ doing_action 時はスキップ
│   │   │   │
│   │   │   ├── accepted_args == 0:
│   │   │   │   $value = $the_['function']()
│   │   │   ├── accepted_args >= count($args):
│   │   │   │   $value = $the_['function'](...$args)
│   │   │   └── else:
│   │   │       $value = $the_['function'](...array_slice($args, 0, $accepted_args))
│   │   │   }
│   │   }
│   └── } while (next($iterations[$level]))
│   │
│   ├── unset iterations/current_priority
│   ├── $nesting_level--
│   └── return $value
│
├── array_pop($wp_current_filter)       // 実行スタックから除去
└── return $filtered
```

Filter では各コールバック実行前に `$args[0] = $value` が更新されます。これにより、前のコールバックが返した値が次のコールバックの第 1 引数として渡され、値がチェーン（連鎖）されます。

### 複数引数の Filter における値の流れ

`apply_filters()` に追加引数を渡した場合、**チェーンされるのは第 1 引数（フィルター値）のみ**です。第 2 引数以降はコンテキスト情報として全コールバックに同じ値が渡されます。

```php
$result = apply_filters('the_content', $content, $post, $is_preview);
// $args = [$content, $post, $is_preview]
```

| コールバック実行 | `$args[0]`（フィルター値） | `$args[1]` | `$args[2]` |
|---|---|---|---|
| 初期状態 | `$content`（元の値） | `$post` | `$is_preview` |
| callback_A 実行前 | `$content` | `$post` | `$is_preview` |
| callback_A 実行後 | callback_A の戻り値 | `$post` | `$is_preview` |
| callback_B 実行前 | callback_A の戻り値で `$args[0]` を更新 | `$post`（不変） | `$is_preview`（不変） |
| callback_B 実行後 | callback_B の戻り値 | `$post` | `$is_preview` |

```php
// accepted_args=1（デフォルト）: フィルター値のみ受け取る
add_filter('the_content', function ($content) {
    return $content . '<p>appended</p>';
});

// accepted_args=3: 全引数を受け取る（追加引数はコンテキストとして利用）
add_filter('the_content', function ($content, $post, $is_preview) {
    if ($is_preview) {
        return $content . '<p>Preview of: ' . $post->post_title . '</p>';
    }
    return $content;
}, 10, 3);
```

この設計により、フィルターチェーンの途中で追加引数が書き換わることはなく、全コールバックが同じコンテキスト情報を参照できます。

### `do_action()` のフロー

```
do_action('my_action', $arg1, $arg2)
│
├── $wp_actions['my_action']++          // 発火回数カウンター
│
├── 'all' フック処理（apply_filters と同様）
│
├── $wp_filter['my_action'] が未登録なら即座に return
│
├── $wp_current_filter[] = 'my_action'
│
├── 引数が空なら $arg = [''] を設定
│
├── WP_Hook::do_action($arg)
│   ├── $doing_action = true
│   ├── apply_filters('', $arg)          // 空文字を初期値として apply_filters を呼ぶ
│   └── nesting_level == 0 なら $doing_action = false
│
└── array_pop($wp_current_filter)
```

`do_action()` は内部的に `apply_filters()` を呼び出しますが、`$doing_action = true` が設定されているため、`$args[0] = $value` の更新がスキップされます。コールバックの戻り値は無視されます。

### `accepted_args` の動作

| `accepted_args` の値 | 動作 |
|---|---|
| `0` | 引数なしで `call_user_func()` |
| `>= count($args)` | 全引数をそのまま `call_user_func_array()` |
| `< count($args)` | `array_slice($args, 0, $accepted_args)` で切り詰め |

デフォルト値は `1`（第 1 引数のみ受け取り）です。

## 5. Action と Filter の違い

### 内部実装の同一性

| Action API | Filter API | 関係 |
|---|---|---|
| `add_action()` | `add_filter()` | `add_action` = `add_filter` のエイリアス |
| `do_action()` | `apply_filters()` | `do_action` 内部で `WP_Hook::apply_filters()` を呼ぶ |
| `remove_action()` | `remove_filter()` | `remove_action` = `remove_filter` のエイリアス |
| `has_action()` | `has_filter()` | `has_action` = `has_filter` のエイリアス |

### 挙動の違い

| 観点 | Action | Filter |
|---|---|---|
| 目的 | 副作用の実行（処理の追加） | 値の変換（データの加工） |
| 戻り値 | 無視される | チェーンされる（次のコールバックの第 1 引数になる） |
| `$doing_action` フラグ | `true` | `false` |
| `$args[0]` の更新 | スキップ（各コールバックは元の引数を受け取る） | 毎回更新（前のコールバックの戻り値が入る） |
| カウンター | `$wp_actions` | `$wp_filters` |
| 初期値 | 空文字 `''` | `apply_filters()` の第 2 引数 |

### `$doing_action` による分岐

`WP_Hook::apply_filters()` 内の以下のコードが挙動の違いを生みます:

```php
if (!$this->doing_action) {
    $args[0] = $value;  // Filter: 前のコールバックの戻り値で更新
}
```

Action 実行時は `$doing_action = true` なので `$args[0]` は更新されず、各コールバックは元の引数をそのまま受け取ります。

## 6. 優先度

### 基本ルール

- デフォルト値は `10`
- **小さい値ほど先に実行**される
- 同一優先度内では**登録順**で実行される
- 負の値も使用可能

### ソート管理

`WP_Hook::add_filter()` は新しい優先度が追加された場合に `ksort($this->callbacks, SORT_NUMERIC)` でソートし、`$priorities = array_keys($this->callbacks)` で優先度配列を再構築します。既存の優先度にコールバックが追加されるだけの場合はソートは発生しません。

### 実行中の優先度追加

イテレーション中に新しい優先度が追加された場合、`resort_active_iterations()` がアクティブな全イテレーションの優先度配列を更新します。新しい優先度が現在の実行位置より後にあれば実行されますが、既に通過した優先度に追加された場合は現在の実行では呼ばれません。

## 7. コールバック識別子

`_wp_filter_build_unique_id()` はコールバックの型に応じてユニーク ID を生成します。このIDは `$callbacks` 配列のキーとして使われ、同じコールバックの重複登録防止と削除時の照合に使用されます。

| コールバックの型 | 生成される ID | 例 |
|---|---|---|
| 文字列（関数名） | そのまま返す | `'my_function'` |
| Closure | `spl_object_hash($closure) . ''` | `'000000004e7c3f940000000043a652e6'` |
| Static メソッド (`['Class', 'method']`) | `'Class::method'` | `'MyClass::handle'` |
| インスタンスメソッド (`[$obj, 'method']`) | `spl_object_hash($obj) . 'method'` | `'000000004e7c3f94...myMethod'` |

> [!WARNING]
> `spl_object_hash()` ベースの ID は、オブジェクトが破棄されると同じ ID が別のオブジェクトに再利用される可能性があります。`remove_filter()` はオブジェクトのライフタイム内に呼ぶ必要があります。

## 8. 再帰・ネスト実行

### `$nesting_level` スタックの仕組み

フック内から別のフック（または同じフック）が発火するケースを安全に処理するため、`WP_Hook` はネストレベルをスタックとして管理します。

```php
// apply_filters() 開始時
$nesting_level = $this->nesting_level++;
$this->iterations[$nesting_level] = $this->priorities;

// apply_filters() 終了時
unset($this->iterations[$nesting_level]);
unset($this->current_priority[$nesting_level]);
--$this->nesting_level;
```

各ネストレベルは独自の `$iterations` と `$current_priority` を持ちます。これにより、外側のイテレーションの状態を壊さずに内側のフック実行が可能です。

### 再帰実行の例

```php
add_action('outer', function () {
    // ここで inner が発火しても outer のイテレーションは安全
    do_action('inner');
});

add_action('outer', function () {
    // inner の実行後も、outer の残りのコールバックは正しく実行される
});

do_action('outer');
```

### イテレーション中のフック追加・削除

イテレーション中にコールバックが追加・削除されると、`resort_active_iterations()` がアクティブな全イテレーションの優先度配列を再構築します。

**追加の場合:**
- 新しい優先度がまだ処理されていなければ、現在の実行で呼ばれる
- 既に通過した優先度に追加された場合は、現在の実行では呼ばれない
- 現在処理中の優先度に追加された場合は、ポインタ位置の調整が行われる

**削除の場合:**
- 削除された優先度にコールバックがなくなった場合、その優先度自体が `$callbacks` から除去される
- `resort_active_iterations()` により、イテレーションのポインタが正しい位置に調整される

## 9. 特殊フック

### `all` フック

`all` という名前で登録されたコールバックは、**すべてのフック発火前に**呼び出されるメタオブザーバーとして機能します。

```php
add_action('all', function () {
    $args = func_get_args();
    $hook_name = $args[0]; // 発火されたフック名
    // デバッグやプロファイリングに利用
});
```

`apply_filters()` と `do_action()` の冒頭で `$wp_filter['all']` の存在をチェックし、存在すれば `WP_Hook::do_all_hook()` を呼び出します。`do_all_hook()` は通常の `apply_filters()` と異なり、戻り値を無視し、引数を参照渡しで受け取ります。

`all` フックの処理フロー:

1. `$wp_current_filter[]` にフック名をプッシュ
2. `_wp_call_all_hook()` → `$wp_filter['all']->do_all_hook($args)` を実行
3. 通常のフック処理を実行
4. `array_pop($wp_current_filter)`

### `$wp_current_filter` スタック

現在実行中のフック名のスタックです。フック開始時に `push`、終了時に `pop` されます。

```php
// init フック実行中に save_post が発火した場合
$wp_current_filter = ['init', 'save_post'];

current_filter();               // => 'save_post'（最後の要素）
doing_filter('init');           // => true（スタック内に存在）
doing_filter('save_post');      // => true
doing_filter('the_content');    // => false
doing_filter();                 // => true（何かが実行中）
```

