<?php

declare(strict_types=1);

namespace WpPack\Component\User\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\User\UserRepository;

#[CoversClass(UserRepository::class)]
final class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new UserRepository();
    }

    #[Test]
    public function findAllReturnsArray(): void
    {
        $users = $this->repository->findAll();

        self::assertIsArray($users);
    }

    #[Test]
    public function findReturnsUserForValidId(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_find_' . uniqid(),
            'user_pass' => 'password123',
            'user_email' => 'find_' . uniqid() . '@example.com',
        ]);
        self::assertIsInt($userId);

        $user = $this->repository->find($userId);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);

        wp_delete_user($userId);
    }

    #[Test]
    public function findReturnsNullForInvalidId(): void
    {
        self::assertNull($this->repository->find(999999));
    }

    #[Test]
    public function findByEmailReturnsUser(): void
    {
        $email = 'byemail_' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'test_email_' . uniqid(),
            'user_pass' => 'password123',
            'user_email' => $email,
        ]);
        self::assertIsInt($userId);

        $user = $this->repository->findByEmail($email);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);

        wp_delete_user($userId);
    }

    #[Test]
    public function findByEmailReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->findByEmail('nonexistent_' . uniqid() . '@example.com'));
    }

    #[Test]
    public function findByLoginReturnsUser(): void
    {
        $login = 'test_login_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass' => 'password123',
            'user_email' => 'login_' . uniqid() . '@example.com',
        ]);
        self::assertIsInt($userId);

        $user = $this->repository->findByLogin($login);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);

        wp_delete_user($userId);
    }

    #[Test]
    public function findBySlugReturnsUser(): void
    {
        $login = 'test_slug_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_pass' => 'password123',
            'user_email' => 'slug_' . uniqid() . '@example.com',
        ]);
        self::assertIsInt($userId);

        $user = $this->repository->findBySlug($login);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);

        wp_delete_user($userId);
    }

    #[Test]
    public function findByLoginReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->findByLogin('nonexistent_login_' . uniqid()));
    }

    #[Test]
    public function findBySlugReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->findBySlug('nonexistent-slug-' . uniqid()));
    }

    #[Test]
    public function insertCreatesUser(): void
    {
        $login = 'test_insert_' . uniqid();
        $result = $this->repository->insert([
            'user_login' => $login,
            'user_pass' => 'password123',
            'user_email' => 'insert_' . uniqid() . '@example.com',
        ]);

        self::assertIsInt($result);
        self::assertGreaterThan(0, $result);

        $user = get_userdata($result);
        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($login, $user->user_login);

        wp_delete_user($result);
    }

    #[Test]
    public function updateModifiesUser(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_update_' . uniqid(),
            'user_pass' => 'password123',
            'user_email' => 'update_' . uniqid() . '@example.com',
            'display_name' => 'Before',
        ]);
        self::assertIsInt($userId);

        $result = $this->repository->update([
            'ID' => $userId,
            'display_name' => 'After',
        ]);

        self::assertIsInt($result);
        self::assertSame($userId, $result);

        $user = get_userdata($userId);
        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame('After', $user->display_name);

        wp_delete_user($userId);
    }

    #[Test]
    public function deleteRemovesUser(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_delete_' . uniqid(),
            'user_pass' => 'password123',
            'user_email' => 'delete_' . uniqid() . '@example.com',
        ]);
        self::assertIsInt($userId);

        $result = $this->repository->delete($userId);

        self::assertTrue($result);
        self::assertFalse(get_userdata($userId));
    }

    #[Test]
    public function metaCrud(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_meta_' . uniqid(),
            'user_pass' => 'password123',
            'user_email' => 'meta_' . uniqid() . '@example.com',
        ]);
        self::assertIsInt($userId);

        // addMeta
        $metaId = $this->repository->addMeta($userId, 'test_key', 'test_value');
        self::assertIsInt($metaId);
        self::assertGreaterThan(0, $metaId);

        // getMeta (single)
        $value = $this->repository->getMeta($userId, 'test_key', true);
        self::assertSame('test_value', $value);

        // getMeta (all for key)
        $values = $this->repository->getMeta($userId, 'test_key');
        self::assertIsArray($values);
        self::assertContains('test_value', $values);

        // updateMeta
        $this->repository->updateMeta($userId, 'test_key', 'updated_value');
        $value = $this->repository->getMeta($userId, 'test_key', true);
        self::assertSame('updated_value', $value);

        // deleteMeta
        $deleted = $this->repository->deleteMeta($userId, 'test_key');
        self::assertTrue($deleted);

        $value = $this->repository->getMeta($userId, 'test_key', true);
        self::assertSame('', $value);

        wp_delete_user($userId);
    }
}
