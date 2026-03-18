## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Routing/Subscriber/`

低レベルフック操作用。Controller と併用可能。

**Actions（2）:**

| アトリビュート | WordPress フック |
|--------------|----------------|
| `ParseRequestAction` | `parse_request` |
| `TemplateRedirectAction` | `template_redirect` |

**Filters（7）:**

| アトリビュート | WordPress フック |
|--------------|----------------|
| `RewriteRulesArrayFilter` | `rewrite_rules_array` |
| `RootRewriteRulesFilter` | `root_rewrite_rules` |
| `PostRewriteRulesFilter` | `post_rewrite_rules` |
| `PageRewriteRulesFilter` | `page_rewrite_rules` |
| `QueryVarsFilter` | `query_vars` |
| `RequestFilter` | `request` |
| `TemplateIncludeFilter` | `template_include` |
