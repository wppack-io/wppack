## Named Hook アトリビュート

> Named Hook を使用するサブスクライバーの推奨配置先: `src/Role/Subscriber/`

### 権限フィルタリング

#### #[UserHasCapFilter]

ランタイムで権限を動的に付与・拒否します。

**WordPress Hook:** `user_has_cap`

```php
use WPPack\Component\Hook\Attribute\Role\UserHasCapFilter;

class CapabilityManager
{
    #[UserHasCapFilter(priority: 10)]
    public function filterCapabilities(
        array $allCaps,
        array $caps,
        array $args,
        \WP_User $user,
    ): array {
        // 時間帯による制限
        if ($this->isOutsideWorkingHours() && !in_array('administrator', $user->roles, true)) {
            unset($allCaps['publish_posts'], $allCaps['delete_posts']);
        }

        // 部署ベースの権限付与
        $department = get_user_meta($user->ID, 'department', true);
        if ($department) {
            foreach ($this->getDepartmentCapabilities($department) as $cap) {
                $allCaps[$cap] = true;
            }
        }

        return $allCaps;
    }
}
```

#### #[MapMetaCapFilter]

メタ権限をプリミティブ権限にマッピングします。

**WordPress Hook:** `map_meta_cap`

```php
use WPPack\Component\Hook\Attribute\Role\MapMetaCapFilter;

class MetaCapabilityMapper
{
    #[MapMetaCapFilter(priority: 10)]
    public function mapMetaCapabilities(
        array $caps,
        string $cap,
        int $userId,
        array $args,
    ): array {
        if ($cap === 'edit_product') {
            $postId = $args[0] ?? 0;
            $post = get_post($postId);

            if (!$post) {
                return ['do_not_allow'];
            }

            if ((int) $post->post_author === $userId) {
                return ['edit_products'];
            }

            return ['edit_others_products'];
        }

        return $caps;
    }
}
```

### ロール割り当てフック

#### #[SetUserRoleAction]

ユーザーのロールが変更された時にアクションを実行します。

**WordPress Hook:** `set_user_role`

```php
use WPPack\Component\Hook\Attribute\Role\SetUserRoleAction;

class RoleChangeHandler
{
    #[SetUserRoleAction(priority: 10)]
    public function handleRoleChange(int $userId, string $role, array $oldRoles): void
    {
        $this->logger->info('User role changed', [
            'user_id' => $userId,
            'new_role' => $role,
            'old_roles' => $oldRoles,
            'changed_by' => get_current_user_id(),
        ]);

        update_user_meta($userId, '_role_changed_at', current_time('mysql'));
        update_user_meta($userId, '_previous_roles', $oldRoles);
    }
}
```

### Hook Attribute 一覧

```php
// 権限フィルタリング
#[UserHasCapFilter(priority: 10)]           // ユーザー権限のフィルタ
#[MapMetaCapFilter(priority: 10)]           // メタ権限のマッピング

// ロール割り当て
#[SetUserRoleAction(priority: 10)]          // ロール変更時

// マルチサイト
#[GrantSuperAdminAction(priority: 10)]      // Super Admin 付与時
#[RevokeSuperAdminAction(priority: 10)]     // Super Admin 取り消し時
```
