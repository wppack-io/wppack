# WordPress メディア / アタッチメント API 仕様

## 1. 概要

WordPress のメディア API は、画像・動画・音声・ドキュメントなどのメディアファイルのアップロード、管理、表示を担うサブシステムです。メディアファイルは「アタッチメント」として `attachment` 投稿タイプで管理され、メタデータ（サイズ、EXIF等）は `wp_postmeta` に保存されます。

主要コンポーネント:

| コンポーネント | 説明 |
|---|---|
| アタッチメント投稿 | `attachment` 投稿タイプ。メディアファイルのメタ情報を管理 |
| `WP_Image_Editor` | 画像の編集（リサイズ、クロップ、回転等）を担う抽象クラス |
| `WP_Image_Editor_GD` | GD ライブラリを使用した画像エディタ実装 |
| `WP_Image_Editor_Imagick` | ImageMagick を使用した画像エディタ実装 |
| メディアアップローダー | ファイルのアップロードを処理する関数群 |
| 画像サイズ管理 | 登録されたサイズに基づく自動リサイズ |

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$_wp_additional_image_sizes` | `array` | `add_image_size()` で追加されたカスタム画像サイズ |
| `$_wp_default_headers` | `array` | `register_default_headers()` で登録されたデフォルトヘッダー画像 |

## 2. データ構造

### アタッチメント投稿タイプ

メディアファイルは `wp_posts` テーブルに `post_type = 'attachment'` として保存されます:

| カラム | 説明 |
|---|---|
| `ID` | アタッチメントID |
| `post_author` | アップロードしたユーザーID |
| `post_title` | メディアのタイトル |
| `post_content` | メディアの説明 |
| `post_excerpt` | キャプション |
| `post_mime_type` | MIME タイプ（例: `image/jpeg`, `video/mp4`） |
| `post_status` | `inherit`（親投稿のステータスを継承） |
| `post_parent` | 添付先の投稿ID（0 = 未添付） |
| `guid` | ファイルの URL |

### アタッチメントメタデータ

`wp_postmeta` に保存される主要なメタデータ:

| メタキー | 説明 |
|---|---|
| `_wp_attached_file` | `uploads/` からの相対パス（例: `2024/01/photo.jpg`） |
| `_wp_attachment_metadata` | シリアライズされたメタデータ配列 |
| `_wp_attachment_image_alt` | 画像の alt テキスト |
| `_wp_attachment_context` | アタッチメントのコンテキスト |
| `_wp_attachment_backup_sizes` | 画像編集前のバックアップサイズ |

### `_wp_attachment_metadata` の構造（画像の場合）

```php
[
    'width'      => 2400,
    'height'     => 1600,
    'file'       => '2024/01/photo.jpg',
    'filesize'   => 524288,
    'sizes'      => [
        'thumbnail' => [
            'file'      => 'photo-150x150.jpg',
            'width'     => 150,
            'height'    => 150,
            'mime-type' => 'image/jpeg',
            'filesize'  => 8192,
        ],
        'medium' => [
            'file'      => 'photo-300x200.jpg',
            'width'     => 300,
            'height'    => 200,
            'mime-type' => 'image/jpeg',
            'filesize'  => 16384,
        ],
        // ... その他のサイズ
    ],
    'image_meta' => [
        'aperture'          => '2.8',
        'credit'            => '',
        'camera'            => 'Canon EOS R5',
        'caption'           => '',
        'created_timestamp'  => 1704067200,
        'copyright'         => '',
        'focal_length'      => '50',
        'iso'               => '100',
        'shutter_speed'     => '0.005',
        'title'             => '',
        'orientation'       => '1',
        'keywords'          => [],
    ],
]
```

### WP_Image_Editor クラス

画像編集の抽象基底クラスです:

```php
abstract class WP_Image_Editor {
    protected $file;              // 画像ファイルのパス
    protected $size;              // ['width' => int, 'height' => int]
    protected $mime_type;         // MIME タイプ
    protected $output_mime_type;  // 出力 MIME タイプ
    protected $default_mime_type = 'image/jpeg';
    protected $quality = false;   // 画像品質（1-100）
    protected $default_quality = 82;
}
```

## 3. API リファレンス

### アップロード API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_handle_upload()` | `(array &$file, array\|false $overrides = false, string $time = null): array` | `$_FILES` からのファイルアップロード処理 |
| `wp_handle_sideload()` | `(array &$file, array\|false $overrides = false, string $time = null): array` | フォーム外からのファイルアップロード |
| `media_handle_upload()` | `(string $file_id, int $post_id, array $post_data = [], array $overrides = []): int\|WP_Error` | メディアファイルをアタッチメントとして保存 |
| `media_handle_sideload()` | `(array $file_array, int $post_id = 0, string $desc = null, array $post_data = []): int\|WP_Error` | サイドロードファイルをアタッチメントとして保存 |
| `wp_upload_dir()` | `(string $time = null, bool $create_dir = true, bool $refresh_cache = false): array` | アップロードディレクトリ情報を取得 |
| `wp_unique_filename()` | `(string $dir, string $filename, callable $unique_filename_callback = null): string` | ユニークなファイル名を生成 |
| `wp_check_filetype()` | `(string $filename, string[] $mimes = null): array` | ファイル拡張子から MIME タイプを判定 |
| `wp_check_filetype_and_ext()` | `(string $file, string $filename, string[] $mimes = null): array` | ファイル内容と拡張子から MIME タイプを検証 |

### アタッチメント CRUD API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_insert_attachment()` | `(string\|array $args, string\|false $file = false, int $parent_post_id = 0, bool $wp_error = false, bool $fire_after_hooks = true): int\|WP_Error` | アタッチメントを作成/更新 |
| `wp_delete_attachment()` | `(int $post_id, bool $force_delete = false): WP_Post\|false\|null` | アタッチメントを削除 |
| `wp_get_attachment_metadata()` | `(int $attachment_id = 0, bool $unfiltered = false): array\|false` | メタデータを取得 |
| `wp_update_attachment_metadata()` | `(int $attachment_id, array $data): int\|false` | メタデータを更新 |
| `wp_generate_attachment_metadata()` | `(int $attachment_id, string $file): array` | メタデータを生成（サブサイズ作成含む） |
| `get_attached_file()` | `(int $attachment_id, bool $unfiltered = false): string\|false` | アタッチメントのファイルパスを取得 |
| `update_attached_file()` | `(int $attachment_id, string $file): bool` | アタッチメントのファイルパスを更新 |

### 画像表示 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_get_attachment_image()` | `(int $attachment_id, string\|int[] $size = 'thumbnail', bool $icon = false, string\|array $attr = ''): string` | アタッチメントの `<img>` タグを取得 |
| `wp_get_attachment_image_src()` | `(int $attachment_id, string\|int[] $size = 'thumbnail', bool $icon = false): array\|false` | 画像の URL・幅・高さを取得 |
| `wp_get_attachment_image_url()` | `(int $attachment_id, string\|int[] $size = 'thumbnail', bool $icon = false): string\|false` | 画像 URL を取得 |
| `wp_get_attachment_image_srcset()` | `(int $attachment_id, string\|int[] $size = 'medium', array $image_meta = null): string\|false` | srcset 属性値を取得 |
| `wp_get_attachment_url()` | `(int $attachment_id): string\|false` | アタッチメントの URL を取得 |
| `wp_get_attachment_thumb_url()` | `(int $attachment_id = 0): string\|false` | サムネイル URL を取得 |
| `wp_get_attachment_caption()` | `(int $post_id = 0): string\|false` | キャプションを取得 |
| `get_post_thumbnail_id()` | `(int\|WP_Post $post = null): int\|false` | アイキャッチ画像IDを取得 |
| `get_the_post_thumbnail()` | `(int\|WP_Post $post = null, string\|int[] $size = 'post-thumbnail', string\|array $attr = ''): string` | アイキャッチ画像の `<img>` タグ |
| `the_post_thumbnail()` | `(string\|int[] $size = 'post-thumbnail', string\|array $attr = ''): void` | アイキャッチ画像を表示 |
| `has_post_thumbnail()` | `(int\|WP_Post $post = null): bool` | アイキャッチ画像があるか |

### 画像サイズ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_image_size()` | `(string $name, int $width = 0, int $height = 0, bool\|array $crop = false): void` | カスタム画像サイズを登録 |
| `remove_image_size()` | `(string $name): bool` | カスタム画像サイズを削除 |
| `has_image_size()` | `(string $name): bool` | 画像サイズが登録されているか |
| `set_post_thumbnail_size()` | `(int $width = 0, int $height = 0, bool\|array $crop = false): void` | アイキャッチ画像のサイズを設定 |
| `get_intermediate_image_sizes()` | `(): string[]` | 登録済みの中間画像サイズ名を取得 |
| `wp_get_registered_image_subsizes()` | `(): array` | 登録済みの全画像サブサイズ情報を取得 |
| `image_constrain_size_for_editor()` | `(int $width, int $height, string\|int[] $size = 'medium', string $context = null): int[]` | エディタ用にサイズを制約 |

### 画像編集 API（WP_Image_Editor）

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_Image_Editor::get_instance()` | `(string $path, array $args = []): WP_Image_Editor\|WP_Error` | 適切なエディタインスタンスを取得（static） |
| `resize()` | `(int\|null $max_w, int\|null $max_h, bool\|array $crop = false): true\|WP_Error` | 画像をリサイズ |
| `multi_resize()` | `(array $sizes): array` | 複数サイズに一括リサイズ |
| `crop()` | `(int $src_x, int $src_y, int $src_w, int $src_h, int $dst_w = null, int $dst_h = null, bool $src_abs = false): true\|WP_Error` | 画像をクロップ |
| `rotate()` | `(float $angle): true\|WP_Error` | 画像を回転 |
| `flip()` | `(bool $horz, bool $vert): true\|WP_Error` | 画像を反転 |
| `save()` | `(string $destfilename = null, string $mime_type = null): array\|WP_Error` | 画像を保存 |
| `get_size()` | `(): array` | 現在のサイズ `['width' => int, 'height' => int]` |
| `set_quality()` | `(int $quality): true\|WP_Error` | 品質を設定（1-100） |
| `generate_filename()` | `(string $suffix = null, string $dest_path = null, string $extension = null): string` | 出力ファイル名を生成 |

### ユーティリティ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_get_mime_types()` | `(): string[]` | 拡張子 => MIME タイプのマッピングを取得 |
| `get_allowed_mime_types()` | `(int\|WP_User $user = null): string[]` | ユーザーが許可された MIME タイプ |
| `wp_max_upload_size()` | `(): int` | 最大アップロードサイズ（バイト） |
| `wp_read_image_metadata()` | `(string $file): array\|false` | 画像の EXIF/IPTC メタデータを読み取り |
| `wp_get_image_mime()` | `(string $file): string\|false` | ファイルの MIME タイプを検出 |
| `wp_prepare_attachment_for_js()` | `(int\|WP_Post $attachment): array\|void` | メディアライブラリ用の JavaScript データを準備 |

## 4. 実行フロー

### ファイルアップロードフロー

```
media_handle_upload('async-upload', $post_id)
│
├── wp_handle_upload($_FILES['async-upload'], $overrides)
│   │
│   ├── ファイル検証
│   │   ├── wp_check_filetype_and_ext() で MIME タイプ検証
│   │   ├── apply_filters('upload_mimes', $mimes) で許可リスト確認
│   │   └── apply_filters('wp_handle_upload_prefilter', $file)
│   │
│   ├── wp_upload_dir($time) でアップロード先決定
│   │   └── {basedir}/{year}/{month}/
│   │
│   ├── wp_unique_filename() でユニーク名生成
│   │
│   ├── move_uploaded_file() でファイル移動
│   │
│   └── return ['file' => $path, 'url' => $url, 'type' => $mime]
│
├── wp_insert_attachment($attachment, $file, $post_id)
│   └── wp_insert_post() でアタッチメント投稿を作成
│
├── wp_update_attachment_metadata(
│       $attach_id,
│       wp_generate_attachment_metadata($attach_id, $file)
│   )
│   │
│   └── wp_generate_attachment_metadata()
│       ├── 画像の場合:
│       │   ├── wp_read_image_metadata() で EXIF 読み取り
│       │   ├── _wp_make_subsizes() で全サブサイズ生成
│       │   │   ├── 登録済みサイズごとに WP_Image_Editor::resize()
│       │   │   └── WP_Image_Editor::save() で保存
│       │   └── apply_filters('wp_generate_attachment_metadata', $metadata, $attach_id, 'create')
│       │
│       ├── 動画の場合:
│       │   └── メタデータ読み取り（ID3タグ等）
│       │
│       └── 音声の場合:
│           └── メタデータ読み取り（ID3タグ等）
│
└── return $attach_id
```

### 画像サイズ解決フロー

```
wp_get_attachment_image_src($attachment_id, 'medium')
│
├── wp_get_attachment_metadata($attachment_id)
│
├── image_downsize($attachment_id, 'medium')
│   ├── apply_filters('image_downsize', false, $id, $size)
│   │   └── false が返った場合は WordPress のデフォルト処理
│   │
│   ├── $size が文字列の場合:
│   │   ├── metadata['sizes'][$size] が存在するか確認
│   │   ├── 存在する場合: そのサイズの情報を使用
│   │   └── 存在しない場合: フルサイズにフォールバック
│   │
│   └── $size が配列 [width, height] の場合:
│       └── 最も近いサイズを選択
│
└── return [$url, $width, $height, $is_intermediate]
```

## 5. 組み込み画像サイズ

| サイズ名 | デフォルト幅 | デフォルト高さ | クロップ | オプションキー |
|---|---|---|---|---|
| `thumbnail` | 150 | 150 | `true` | `thumbnail_size_w` / `thumbnail_size_h` |
| `medium` | 300 | 300 | `false` | `medium_size_w` / `medium_size_h` |
| `medium_large` | 768 | 0 | `false` | `medium_large_size_w` / `medium_large_size_h` |
| `large` | 1024 | 1024 | `false` | `large_size_w` / `large_size_h` |
| `1536x1536` | 1536 | 1536 | `false` | ― |
| `2048x2048` | 2048 | 2048 | `false` | ― |
| `post-thumbnail` | テーマ依存 | テーマ依存 | テーマ依存 | ― |

`add_theme_support('post-thumbnails')` で `post-thumbnail` サイズが有効化されます。

## 6. アップロードディレクトリ構造

`wp_upload_dir()` が返す配列:

```php
[
    'path'    => '/var/www/html/wp-content/uploads/2024/01',  // サーバーパス
    'url'     => 'https://example.com/wp-content/uploads/2024/01',
    'subdir'  => '/2024/01',                                   // 年/月サブディレクトリ
    'basedir' => '/var/www/html/wp-content/uploads',           // ベースディレクトリ
    'baseurl' => 'https://example.com/wp-content/uploads',     // ベース URL
    'error'   => false,                                        // エラーメッセージ
]
```

ディレクトリ構造は `uploads_use_yearmonth_folders` オプションで制御されます（デフォルト: 有効）。

## 7. フック一覧

### Action フック

| フック名 | パラメータ | 説明 |
|---|---|---|
| `add_attachment` | `int $attachment_id` | アタッチメント作成後 |
| `edit_attachment` | `int $attachment_id` | アタッチメント更新後 |
| `delete_attachment` | `int $attachment_id, WP_Post $post` | アタッチメント削除前 |
| `deleted_post` | `int $post_id, WP_Post $post` | アタッチメント削除後（投稿削除フック） |
| `wp_create_file_in_uploads` | `string $file, int $attachment_id` | アップロードファイル作成後 |

### Filter フック

| フック名 | パラメータ | 戻り値 | 説明 |
|---|---|---|---|
| `upload_mimes` | `array $mimes, int\|null $user_id` | `array` | 許可する MIME タイプ |
| `upload_size_limit` | `int $size` | `int` | アップロードサイズ上限（バイト） |
| `wp_handle_upload_prefilter` | `array $file` | `array` | アップロード前のファイルデータ |
| `wp_handle_upload` | `array $upload, string $context` | `array` | アップロード成功後の結果 |
| `upload_dir` | `array $uploads` | `array` | アップロードディレクトリ情報 |
| `wp_generate_attachment_metadata` | `array $metadata, int $attachment_id, string $context` | `array` | 生成されたメタデータ |
| `wp_get_attachment_metadata` | `array $data, int $attachment_id, bool $unfiltered` | `array` | 取得したメタデータ |
| `wp_update_attachment_metadata` | `array $data, int $attachment_id` | `array` | 更新するメタデータ |
| `wp_get_attachment_url` | `string $url, int $attachment_id` | `string` | アタッチメント URL |
| `wp_get_attachment_image_attributes` | `array $attr, WP_Post $attachment, string\|int[] $size` | `array` | `<img>` タグの属性 |
| `wp_get_attachment_image_src` | `array\|false $image, int $attachment_id, string\|int[] $size, bool $icon` | `array\|false` | 画像 src 情報 |
| `wp_calculate_image_srcset` | `array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id` | `array` | srcset のソース配列 |
| `wp_calculate_image_sizes` | `string $sizes, array $size, string\|null $image_src, array\|null $image_meta, int $attachment_id` | `string` | sizes 属性値 |
| `image_downsize` | `false\|array $downsize, int $id, string\|int[] $size` | `false\|array` | 画像ダウンサイズ処理のオーバーライド |
| `intermediate_image_sizes` | `string[] $sizes` | `string[]` | 中間画像サイズの名前リスト |
| `intermediate_image_sizes_advanced` | `array $new_sizes, array $image_meta, int $attachment_id` | `array` | 中間画像サイズの詳細情報 |
| `big_image_size_threshold` | `int $threshold, array $imagesize, string $file, int $attachment_id` | `int` | 大画像のリサイズ閾値（デフォルト: 2560px） |
| `wp_image_editors` | `string[] $editors` | `string[]` | 画像エディタクラスの優先順位 |
| `image_editor_default_mime_type` | `string $mime_type, string $file` | `string` | デフォルト MIME タイプ |
| `wp_editor_set_quality` | `int $quality, string $mime_type` | `int` | 画像品質 |
| `wp_read_image_metadata` | `array $meta, string $file, int $image_type, array $iptc, array $exif` | `array` | 画像メタデータ |
| `wp_prepare_attachment_for_js` | `array $response, WP_Post $attachment, array\|false $meta` | `array` | JS 用データ |
