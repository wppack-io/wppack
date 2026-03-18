## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Rest/Subscriber/`

REST API 関連の WordPress フックを Named Hook として提供。

### アクション

| クラス | フック名 |
|--------|---------|
| `RestApiInitAction` | `rest_api_init` |

### フィルター

| クラス | フック名 |
|--------|---------|
| `RestAuthenticationErrorsFilter` | `rest_authentication_errors` |
| `RestPreDispatchFilter` | `rest_pre_dispatch` |
| `RestPreServeRequestFilter` | `rest_pre_serve_request` |
| `RestPreparePostFilter` | `rest_prepare_post` |
| `RestRequestAfterCallbacksFilter` | `rest_request_after_callbacks` |

```php
use WpPack\Component\Hook\Attribute\Rest\Action\RestApiInitAction;
use WpPack\Component\Hook\Attribute\Rest\Filter\RestPreDispatchFilter;

class MyRestSubscriber
{
    #[RestApiInitAction]
    public function onRestApiInit(): void
    {
        // REST API 初期化時の処理
    }

    #[RestPreDispatchFilter(priority: 5)]
    public function onPreDispatch(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed
    {
        // ディスパッチ前の処理
        return $result;
    }
}
```
