<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\Test;
use WpPack\Component\Database\WpPackWpdb;

/**
 * Shared integration tests for WpPackWpdb across all database engines.
 *
 * Each concrete test class provides a WpPackWpdb instance backed by a real
 * driver + query translator. MySQL DDL/DML goes through the full translation
 * pipeline, verifying end-to-end correctness for each engine.
 */
trait WpdbIntegrationTestTrait
{
    abstract protected function getTestWpdb(): WpPackWpdb;

    /**
     * Create WordPress-like tables using MySQL DDL syntax.
     *
     * The query translator converts this to the target engine's dialect.
     */
    protected function createWordPressTables(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}options (
            option_id bigint(20) unsigned NOT NULL auto_increment,
            option_name varchar(191) NOT NULL default '',
            option_value longtext NOT NULL,
            autoload varchar(20) NOT NULL default 'yes',
            PRIMARY KEY (option_id),
            UNIQUE KEY option_name (option_name)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}posts (
            ID bigint(20) unsigned NOT NULL auto_increment,
            post_author bigint(20) unsigned NOT NULL default '0',
            post_date datetime NOT NULL default '0000-00-00 00:00:00',
            post_content longtext NOT NULL,
            post_title text NOT NULL,
            post_status varchar(20) NOT NULL default 'publish',
            post_name varchar(200) NOT NULL default '',
            post_type varchar(20) NOT NULL default 'post',
            PRIMARY KEY (ID),
            KEY post_name (post_name(191)),
            KEY type_status_date (post_type, post_status, post_date, ID),
            KEY post_author (post_author)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}postmeta (
            meta_id bigint(20) unsigned NOT NULL auto_increment,
            post_id bigint(20) unsigned NOT NULL default '0',
            meta_key varchar(255) default NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY post_id (post_id),
            KEY meta_key (meta_key(191))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}users (
            ID bigint(20) unsigned NOT NULL auto_increment,
            user_login varchar(60) NOT NULL default '',
            user_email varchar(100) NOT NULL default '',
            user_registered datetime NOT NULL default '0000-00-00 00:00:00',
            user_status int(11) NOT NULL default '0',
            display_name varchar(250) NOT NULL default '',
            PRIMARY KEY (ID),
            KEY user_login_key (user_login),
            KEY user_email (user_email)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}usermeta (
            umeta_id bigint(20) unsigned NOT NULL auto_increment,
            user_id bigint(20) unsigned NOT NULL default '0',
            meta_key varchar(255) default NULL,
            meta_value longtext,
            PRIMARY KEY (umeta_id),
            KEY user_id (user_id),
            KEY meta_key (meta_key(191))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Table with composite unique key for REPLACE INTO testing
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$p}term_relationships (
            object_id bigint(20) unsigned NOT NULL default 0,
            term_taxonomy_id bigint(20) unsigned NOT NULL default 0,
            term_order int(11) NOT NULL default 0,
            PRIMARY KEY (object_id, term_taxonomy_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    // ── Options CRUD ──

    #[Test]
    public function insertAndGetOption(): void
    {
        $wpdb = $this->getTestWpdb();
        $result = $wpdb->insert('options', [
            'option_name' => 'site_title',
            'option_value' => 'My WordPress Site',
            'autoload' => 'yes',
        ]);

        self::assertSame(1, $result);
        self::assertGreaterThan(0, $wpdb->insert_id);

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s",
                'site_title',
            ),
        );

        self::assertSame('My WordPress Site', $value);
    }

    #[Test]
    public function updateOption(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('options', [
            'option_name' => 'blogdescription',
            'option_value' => 'Just another WordPress site',
            'autoload' => 'yes',
        ]);

        $updated = $wpdb->update(
            'options',
            ['option_value' => 'A much better site'],
            ['option_name' => 'blogdescription'],
        );

        self::assertSame(1, $updated);

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s",
                'blogdescription',
            ),
        );

        self::assertSame('A much better site', $value);
    }

    #[Test]
    public function deleteOption(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('options', [
            'option_name' => 'temp_option',
            'option_value' => 'delete_me',
        ]);

        $deleted = $wpdb->delete('options', ['option_name' => 'temp_option']);
        self::assertSame(1, $deleted);

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s",
                'temp_option',
            ),
        );

        self::assertNull($value);
    }

    #[Test]
    public function replaceIntoInsertsNewRow(): void
    {
        $wpdb = $this->getTestWpdb();

        $result = $wpdb->replace('options', [
            'option_name' => 'replace_new',
            'option_value' => 'new_value',
            'autoload' => 'yes',
        ]);

        self::assertNotFalse($result);

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s", 'replace_new'),
        );
        self::assertSame('new_value', $value);
    }

    #[Test]
    public function replaceIntoReplacesExistingByUniqueKey(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        // Insert original
        $wpdb->insert('options', [
            'option_name' => 'replace_existing',
            'option_value' => 'original',
            'autoload' => 'yes',
        ]);

        // REPLACE with same unique key (option_name) → value should be updated
        $wpdb->replace('options', [
            'option_name' => 'replace_existing',
            'option_value' => 'replaced',
            'autoload' => 'no',
        ]);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT option_value, autoload FROM {$p}options WHERE option_name = %s", 'replace_existing'),
        );
        self::assertSame('replaced', $row->option_value);
        self::assertSame('no', $row->autoload);
    }

    #[Test]
    public function replaceIntoPreservesNonConflictingRows(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('options', ['option_name' => 'keep_this', 'option_value' => 'preserved']);
        $wpdb->insert('options', ['option_name' => 'replace_this', 'option_value' => 'old']);

        $wpdb->replace('options', [
            'option_name' => 'replace_this',
            'option_value' => 'new',
        ]);

        // Non-conflicting row untouched
        $kept = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$p}options WHERE option_name = %s", 'keep_this'),
        );
        self::assertSame('preserved', $kept);

        // Conflicting row replaced
        $replaced = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$p}options WHERE option_name = %s", 'replace_this'),
        );
        self::assertSame('new', $replaced);
    }

    #[Test]
    public function replaceIntoViaRawQuery(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->query("INSERT INTO {$p}options (option_name, option_value) VALUES ('raw_replace', 'original')");

        $wpdb->query("REPLACE INTO {$p}options (option_name, option_value) VALUES ('raw_replace', 'replaced')");

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$p}options WHERE option_name = %s", 'raw_replace'),
        );
        self::assertSame('replaced', $value);
    }

    #[Test]
    public function replaceIntoWithCompositePrimaryKey(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        // Insert initial row
        $wpdb->replace('term_relationships', [
            'object_id' => 1,
            'term_taxonomy_id' => 10,
            'term_order' => 0,
        ]);

        // Replace with same composite key (object_id=1, term_taxonomy_id=10) → update term_order
        $wpdb->replace('term_relationships', [
            'object_id' => 1,
            'term_taxonomy_id' => 10,
            'term_order' => 5,
        ]);

        // Should have 1 row, not 2
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$p}term_relationships WHERE object_id = 1 AND term_taxonomy_id = 10");
        self::assertSame('1', (string) $count);

        // term_order should be updated
        $order = $wpdb->get_var("SELECT term_order FROM {$p}term_relationships WHERE object_id = 1 AND term_taxonomy_id = 10");
        self::assertSame('5', (string) (int) $order);

        // Different composite key should not conflict
        $wpdb->replace('term_relationships', [
            'object_id' => 1,
            'term_taxonomy_id' => 20,
            'term_order' => 3,
        ]);

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$p}term_relationships WHERE object_id = 1");
        self::assertSame('2', (string) $total);
    }

    #[Test]
    public function replaceIntoOnTableWithoutUniqueConstraint(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        // postmeta has PK (meta_id) but no UNIQUE constraint
        // REPLACE should work as a normal INSERT
        $result = $wpdb->replace('postmeta', [
            'post_id' => 99,
            'meta_key' => 'no_unique_test',
            'meta_value' => 'value1',
        ]);

        self::assertNotFalse($result);

        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$p}postmeta WHERE meta_key = %s", 'no_unique_test'),
        );
        self::assertSame('1', (string) $count);
    }

    #[Test]
    public function getNonExistentOptionReturnsNull(): void
    {
        $wpdb = $this->getTestWpdb();

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s",
                'nonexistent_option_xyz',
            ),
        );

        self::assertNull($value);
    }

    #[Test]
    public function optionWithSerializedArray(): void
    {
        $wpdb = $this->getTestWpdb();
        $data = serialize(['key1' => 'value1', 'key2' => [1, 2, 3]]);

        $wpdb->insert('options', [
            'option_name' => 'serialized_option',
            'option_value' => $data,
        ]);

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s",
                'serialized_option',
            ),
        );

        $restored = unserialize($value);
        self::assertSame('value1', $restored['key1']);
        self::assertSame([1, 2, 3], $restored['key2']);
    }

    #[Test]
    public function optionWithUnicodeAndEmoji(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('options', [
            'option_name' => 'unicode_option',
            'option_value' => 'Hello 世界 🎉🚀 café résumé',
        ]);

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s",
                'unicode_option',
            ),
        );

        self::assertSame('Hello 世界 🎉🚀 café résumé', $value);
    }

    // ── Posts CRUD ──

    #[Test]
    public function insertAndGetPost(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', [
            'post_author' => 1,
            'post_date' => '2024-01-15 10:30:00',
            'post_content' => 'This is the post content.',
            'post_title' => 'Hello World',
            'post_status' => 'publish',
            'post_name' => 'hello-world',
            'post_type' => 'post',
        ]);
        $postId = $wpdb->insert_id;

        self::assertGreaterThan(0, $postId);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
        );

        self::assertSame('Hello World', $row->post_title);
        self::assertSame('publish', $row->post_status);
        self::assertSame('This is the post content.', $row->post_content);
    }

    #[Test]
    public function updatePost(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', [
            'post_title' => 'Draft Post',
            'post_content' => 'Draft content',
            'post_status' => 'draft',
            'post_type' => 'post',
        ]);
        $postId = $wpdb->insert_id;

        $updated = $wpdb->update(
            'posts',
            ['post_title' => 'Published Post', 'post_status' => 'publish'],
            ['ID' => $postId],
        );

        self::assertSame(1, $updated);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT post_title, post_status FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
        );

        self::assertSame('Published Post', $row->post_title);
        self::assertSame('publish', $row->post_status);
    }

    #[Test]
    public function deletePost(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', [
            'post_title' => 'To Delete',
            'post_content' => '',
            'post_status' => 'trash',
            'post_type' => 'post',
        ]);
        $postId = $wpdb->insert_id;

        $deleted = $wpdb->delete('posts', ['ID' => $postId]);
        self::assertSame(1, $deleted);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
        );
        self::assertNull($row);
    }

    #[Test]
    public function listPostsByStatus(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Pub 1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Pub 2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Draft 1', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);

        $published = $wpdb->get_results(
            $wpdb->prepare("SELECT post_title FROM {$p}posts WHERE post_status = %s ORDER BY post_title", 'publish'),
        );

        self::assertCount(2, $published);
        self::assertSame('Pub 1', $published[0]->post_title);
        self::assertSame('Pub 2', $published[1]->post_title);
    }

    #[Test]
    public function paginateWithLimitOffset(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        for ($i = 1; $i <= 5; ++$i) {
            $wpdb->insert('posts', [
                'post_title' => "Post {$i}",
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'post',
            ]);
        }

        $page2 = $wpdb->get_results("SELECT post_title FROM {$p}posts ORDER BY ID LIMIT 2 OFFSET 2");

        self::assertCount(2, $page2);
        self::assertSame('Post 3', $page2[0]->post_title);
        self::assertSame('Post 4', $page2[1]->post_title);
    }

    #[Test]
    public function countPostsByStatus(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'P1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'P2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'page']);
        $wpdb->insert('posts', ['post_title' => 'P3', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'D1', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);

        $counts = $wpdb->get_results(
            "SELECT post_status, COUNT(*) AS cnt FROM {$p}posts GROUP BY post_status ORDER BY post_status",
        );

        self::assertCount(2, $counts);
        self::assertSame('draft', $counts[0]->post_status);
        self::assertSame('1', (string) $counts[0]->cnt);
        self::assertSame('publish', $counts[1]->post_status);
        self::assertSame('3', (string) $counts[1]->cnt);
    }

    #[Test]
    public function searchPostsByTitle(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'WordPress Tutorial', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'PHP Guide', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Advanced WordPress', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $like = '%' . $wpdb->esc_like('WordPress') . '%';
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT post_title FROM {$p}posts WHERE post_title LIKE %s ORDER BY post_title", $like),
        );

        self::assertCount(2, $results);
        self::assertSame('Advanced WordPress', $results[0]->post_title);
        self::assertSame('WordPress Tutorial', $results[1]->post_title);
    }

    #[Test]
    public function sqlCalcFoundRowsPagination(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        for ($i = 1; $i <= 10; ++$i) {
            $wpdb->insert('posts', [
                'post_title' => "Found Row {$i}",
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'post',
            ]);
        }

        $wpdb->query("SELECT SQL_CALC_FOUND_ROWS * FROM {$p}posts ORDER BY ID LIMIT 3");
        self::assertSame(3, $wpdb->num_rows);

        $total = $wpdb->get_var('SELECT FOUND_ROWS()');
        self::assertSame('10', (string) $total);
    }

    // ── Post Meta ──

    #[Test]
    public function insertAndGetPostMeta(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Meta Test', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $wpdb->insert('postmeta', [
            'post_id' => $postId,
            'meta_key' => '_thumbnail_id',
            'meta_value' => '42',
        ]);

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$p}postmeta WHERE post_id = %d AND meta_key = %s",
                $postId,
                '_thumbnail_id',
            ),
        );

        self::assertSame('42', $value);
    }

    #[Test]
    public function updatePostMeta(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Meta Update', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $wpdb->insert('postmeta', ['post_id' => $postId, 'meta_key' => 'color', 'meta_value' => 'red']);

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$p}postmeta SET meta_value = %s WHERE post_id = %d AND meta_key = %s",
                'blue',
                $postId,
                'color',
            ),
        );

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$p}postmeta WHERE post_id = %d AND meta_key = %s",
                $postId,
                'color',
            ),
        );

        self::assertSame('blue', $value);
    }

    #[Test]
    public function deletePostMeta(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Meta Del', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $wpdb->insert('postmeta', ['post_id' => $postId, 'meta_key' => 'temp', 'meta_value' => 'val']);
        $wpdb->delete('postmeta', ['post_id' => $postId, 'meta_key' => 'temp']);

        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$p}postmeta WHERE post_id = %d AND meta_key = %s", $postId, 'temp'),
        );

        self::assertSame('0', (string) $count);
    }

    #[Test]
    public function multipleMetaForSamePost(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Multi Meta', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $wpdb->insert('postmeta', ['post_id' => $postId, 'meta_key' => 'color', 'meta_value' => 'red']);
        $wpdb->insert('postmeta', ['post_id' => $postId, 'meta_key' => 'size', 'meta_value' => 'large']);
        $wpdb->insert('postmeta', ['post_id' => $postId, 'meta_key' => 'weight', 'meta_value' => '100']);

        $metas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$p}postmeta WHERE post_id = %d ORDER BY meta_key",
                $postId,
            ),
        );

        self::assertCount(3, $metas);
        self::assertSame('color', $metas[0]->meta_key);
        self::assertSame('red', $metas[0]->meta_value);
        self::assertSame('size', $metas[1]->meta_key);
        self::assertSame('weight', $metas[2]->meta_key);
    }

    #[Test]
    public function joinPostsAndPostMeta(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Featured', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id1 = $wpdb->insert_id;
        $wpdb->insert('posts', ['post_title' => 'Normal', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id2 = $wpdb->insert_id;

        $wpdb->insert('postmeta', ['post_id' => $id1, 'meta_key' => '_featured', 'meta_value' => '1']);
        $wpdb->insert('postmeta', ['post_id' => $id2, 'meta_key' => '_featured', 'meta_value' => '0']);

        $results = $wpdb->get_results(
            "SELECT p.post_title FROM {$p}posts p
             INNER JOIN {$p}postmeta pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_featured' AND pm.meta_value = '1'",
        );

        self::assertCount(1, $results);
        self::assertSame('Featured', $results[0]->post_title);
    }

    #[Test]
    public function metaWithNullValue(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Null Meta', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $wpdb->insert('postmeta', ['post_id' => $postId, 'meta_key' => 'nullable', 'meta_value' => null]);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT meta_value FROM {$p}postmeta WHERE post_id = %d AND meta_key = %s",
                $postId,
                'nullable',
            ),
        );

        self::assertNull($row->meta_value);
    }

    // ── Users ──

    #[Test]
    public function insertAndGetUser(): void
    {
        $wpdb = $this->getTestWpdb();

        $wpdb->insert('users', [
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
            'user_registered' => '2024-06-01 12:00:00',
            'display_name' => 'Test User',
        ]);
        $userId = $wpdb->insert_id;

        $user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}users WHERE ID = %d", $userId),
        );

        self::assertSame('testuser', $user->user_login);
        self::assertSame('test@example.com', $user->user_email);
        self::assertSame('Test User', $user->display_name);
    }

    #[Test]
    public function updateUser(): void
    {
        $wpdb = $this->getTestWpdb();

        $wpdb->insert('users', [
            'user_login' => 'updatable',
            'user_email' => 'old@example.com',
            'display_name' => 'Old Name',
        ]);
        $userId = $wpdb->insert_id;

        $wpdb->update(
            'users',
            ['user_email' => 'new@example.com', 'display_name' => 'New Name'],
            ['ID' => $userId],
        );

        $user = $wpdb->get_row(
            $wpdb->prepare("SELECT user_email, display_name FROM {$wpdb->prefix}users WHERE ID = %d", $userId),
        );

        self::assertSame('new@example.com', $user->user_email);
        self::assertSame('New Name', $user->display_name);
    }

    #[Test]
    public function deleteUserAndMeta(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('users', ['user_login' => 'deleteme', 'user_email' => 'del@example.com', 'display_name' => 'Del']);
        $userId = $wpdb->insert_id;

        $wpdb->insert('usermeta', ['user_id' => $userId, 'meta_key' => 'role', 'meta_value' => 'subscriber']);
        $wpdb->insert('usermeta', ['user_id' => $userId, 'meta_key' => 'pref', 'meta_value' => 'dark']);

        $wpdb->delete('usermeta', ['user_id' => $userId]);
        $wpdb->delete('users', ['ID' => $userId]);

        $metaCount = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$p}usermeta WHERE user_id = %d", $userId),
        );
        self::assertSame('0', (string) $metaCount);

        $user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$p}users WHERE ID = %d", $userId),
        );
        self::assertNull($user);
    }

    #[Test]
    public function joinUsersAndMeta(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('users', ['user_login' => 'admin1', 'user_email' => 'a@e.com', 'display_name' => 'Admin']);
        $adminId = $wpdb->insert_id;
        $wpdb->insert('users', ['user_login' => 'sub1', 'user_email' => 's@e.com', 'display_name' => 'Sub']);
        $subId = $wpdb->insert_id;

        $wpdb->insert('usermeta', ['user_id' => $adminId, 'meta_key' => 'wp_capabilities', 'meta_value' => 'a:1:{s:13:"administrator";b:1;}']);
        $wpdb->insert('usermeta', ['user_id' => $subId, 'meta_key' => 'wp_capabilities', 'meta_value' => 'a:1:{s:10:"subscriber";b:1;}']);

        $admins = $wpdb->get_results(
            "SELECT u.display_name FROM {$p}users u
             INNER JOIN {$p}usermeta um ON u.ID = um.user_id
             WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%administrator%'",
        );

        self::assertCount(1, $admins);
        self::assertSame('Admin', $admins[0]->display_name);
    }

    // ── wpdb Query Methods ──

    #[Test]
    public function getVarReturnsScalar(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', ['post_title' => 'Scalar', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts");

        self::assertSame('1', (string) $count);
    }

    #[Test]
    public function getRowReturnsObject(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', ['post_title' => 'Object Row', 'post_content' => 'body', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT post_title, post_content FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
            OBJECT,
        );

        self::assertIsObject($row);
        self::assertSame('Object Row', $row->post_title);
        self::assertSame('body', $row->post_content);
    }

    #[Test]
    public function getRowReturnsAssociativeArray(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', ['post_title' => 'Assoc Row', 'post_content' => 'data', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT post_title, post_content FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
            ARRAY_A,
        );

        self::assertIsArray($row);
        self::assertSame('Assoc Row', $row['post_title']);
        self::assertSame('data', $row['post_content']);
    }

    #[Test]
    public function getRowReturnsNumericArray(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', ['post_title' => 'Num Row', 'post_content' => 'nc', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT post_title, post_content FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
            ARRAY_N,
        );

        self::assertIsArray($row);
        self::assertSame('Num Row', $row[0]);
        self::assertSame('nc', $row[1]);
    }

    #[Test]
    public function getColReturnsColumn(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Col A', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Col B', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Col C', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $titles = $wpdb->get_col("SELECT post_title FROM {$p}posts ORDER BY post_title");

        self::assertSame(['Col A', 'Col B', 'Col C'], $titles);
    }

    #[Test]
    public function getResultsReturnsObjects(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Res 1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Res 2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $results = $wpdb->get_results("SELECT post_title FROM {$p}posts ORDER BY post_title", OBJECT);

        self::assertCount(2, $results);
        self::assertIsObject($results[0]);
        self::assertSame('Res 1', $results[0]->post_title);
    }

    #[Test]
    public function getResultsReturnsAssociativeArrays(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Arr 1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Arr 2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $results = $wpdb->get_results("SELECT post_title FROM {$p}posts ORDER BY post_title", ARRAY_A);

        self::assertCount(2, $results);
        self::assertIsArray($results[0]);
        self::assertSame('Arr 1', $results[0]['post_title']);
    }

    // ── Prepared Statements ──

    #[Test]
    public function prepareWithStringPlaceholder(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('options', ['option_name' => 'str_test', 'option_value' => 'found']);

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s", 'str_test'),
        );

        self::assertSame('found', $value);
    }

    #[Test]
    public function prepareWithIntPlaceholder(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', ['post_title' => 'Int Test', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $title = $wpdb->get_var(
            $wpdb->prepare("SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
        );

        self::assertSame('Int Test', $title);
    }

    #[Test]
    public function prepareWithMixedPlaceholders(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', ['post_title' => 'Mixed', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 5]);
        $postId = $wpdb->insert_id;

        $title = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = %d AND post_status = %s AND post_author = %d",
                $postId,
                'publish',
                5,
            ),
        );

        self::assertSame('Mixed', $title);
    }

    #[Test]
    public function prepareWithIdentifierPlaceholder(): void
    {
        $wpdb = $this->getTestWpdb();
        $wpdb->insert('posts', ['post_title' => 'Ident', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT %i FROM {$wpdb->prefix}posts WHERE post_status = %s LIMIT 1",
                'post_title',
                'publish',
            ),
        );

        self::assertSame('Ident', $value);
    }

    #[Test]
    public function prepareWithLikePattern(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => '100% Complete', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Normal Title', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $like = '%' . $wpdb->esc_like('100%') . '%';
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT post_title FROM {$p}posts WHERE post_title LIKE %s", $like),
        );

        self::assertCount(1, $results);
        self::assertSame('100% Complete', $results[0]->post_title);
    }

    // ── Complex Queries ──

    #[Test]
    public function orderByMultipleColumns(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'B', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 2]);
        $wpdb->insert('posts', ['post_title' => 'A', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1]);
        $wpdb->insert('posts', ['post_title' => 'C', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post', 'post_author' => 1]);

        $results = $wpdb->get_results(
            "SELECT post_title FROM {$p}posts ORDER BY post_status DESC, post_title ASC",
        );

        self::assertCount(3, $results);
        self::assertSame('A', $results[0]->post_title);
        self::assertSame('B', $results[1]->post_title);
        self::assertSame('C', $results[2]->post_title);
    }

    #[Test]
    public function groupByWithAggregate(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'P1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1]);
        $wpdb->insert('posts', ['post_title' => 'P2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1]);
        $wpdb->insert('posts', ['post_title' => 'P3', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 2]);

        $counts = $wpdb->get_results(
            "SELECT post_author, COUNT(*) AS post_count FROM {$p}posts GROUP BY post_author ORDER BY post_author",
        );

        self::assertCount(2, $counts);
        self::assertSame('1', (string) $counts[0]->post_author);
        self::assertSame('2', (string) $counts[0]->post_count);
        self::assertSame('2', (string) $counts[1]->post_author);
        self::assertSame('1', (string) $counts[1]->post_count);
    }

    #[Test]
    public function subqueryInWhere(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Has Meta', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id1 = $wpdb->insert_id;
        $wpdb->insert('posts', ['post_title' => 'No Meta', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $wpdb->insert('postmeta', ['post_id' => $id1, 'meta_key' => 'special', 'meta_value' => 'yes']);

        $results = $wpdb->get_results(
            "SELECT post_title FROM {$p}posts WHERE ID IN (
                SELECT post_id FROM {$p}postmeta WHERE meta_key = 'special'
            )",
        );

        self::assertCount(1, $results);
        self::assertSame('Has Meta', $results[0]->post_title);
    }

    #[Test]
    public function betweenDates(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Jan', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_date' => '2024-01-15 10:00:00']);
        $wpdb->insert('posts', ['post_title' => 'Mar', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_date' => '2024-03-15 10:00:00']);
        $wpdb->insert('posts', ['post_title' => 'Jun', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_date' => '2024-06-15 10:00:00']);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_title FROM {$p}posts WHERE post_date BETWEEN %s AND %s ORDER BY post_date",
                '2024-02-01 00:00:00',
                '2024-05-01 00:00:00',
            ),
        );

        self::assertCount(1, $results);
        self::assertSame('Mar', $results[0]->post_title);
    }

    #[Test]
    public function nullAndNotNull(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('postmeta', ['post_id' => 1, 'meta_key' => 'key1', 'meta_value' => 'val']);
        $wpdb->insert('postmeta', ['post_id' => 2, 'meta_key' => null, 'meta_value' => null]);

        $withKey = $wpdb->get_results("SELECT post_id FROM {$p}postmeta WHERE meta_key IS NOT NULL");
        self::assertCount(1, $withKey);

        $withoutKey = $wpdb->get_results("SELECT post_id FROM {$p}postmeta WHERE meta_key IS NULL");
        self::assertCount(1, $withoutKey);
    }

    #[Test]
    public function inClauseMultipleValues(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'P1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id1 = $wpdb->insert_id;
        $wpdb->insert('posts', ['post_title' => 'P2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id2 = $wpdb->insert_id;
        $wpdb->insert('posts', ['post_title' => 'P3', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_title FROM {$p}posts WHERE ID IN (%d, %d) ORDER BY post_title",
                $id1,
                $id2,
            ),
        );

        self::assertCount(2, $results);
        self::assertSame('P1', $results[0]->post_title);
        self::assertSame('P2', $results[1]->post_title);
    }

    #[Test]
    public function distinctValues(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'A', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'B', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'C', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);

        $statuses = $wpdb->get_col("SELECT DISTINCT post_status FROM {$p}posts ORDER BY post_status");

        self::assertSame(['draft', 'publish'], $statuses);
    }

    #[Test]
    public function aliasedColumns(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Alias Test', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $row = $wpdb->get_row("SELECT post_title AS title, post_status AS status FROM {$p}posts LIMIT 1");

        self::assertSame('Alias Test', $row->title);
        self::assertSame('publish', $row->status);
    }

    // ── Data Integrity ──

    #[Test]
    public function transactionCommit(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;
        $driver = $wpdb->getWriter();

        $driver->beginTransaction();
        $wpdb->insert('posts', ['post_title' => 'TX Commit', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $driver->commit();

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$p}posts WHERE post_title = 'TX Commit'");
        self::assertSame('1', (string) $count);
    }

    #[Test]
    public function transactionRollback(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;
        $driver = $wpdb->getWriter();

        $wpdb->insert('posts', ['post_title' => 'Baseline', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $driver->beginTransaction();
        $wpdb->insert('posts', ['post_title' => 'TX Rollback', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $driver->rollBack();

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$p}posts WHERE post_title = 'TX Rollback'");
        self::assertSame('0', (string) $count);

        $baseCount = $wpdb->get_var("SELECT COUNT(*) FROM {$p}posts WHERE post_title = 'Baseline'");
        self::assertSame('1', (string) $baseCount);
    }

    #[Test]
    public function specialCharactersInValues(): void
    {
        $wpdb = $this->getTestWpdb();

        $special = "It's a \"test\" with \\ backslash & <html> 'quotes'";
        $wpdb->insert('options', ['option_name' => 'special_chars', 'option_value' => $special]);

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name = %s", 'special_chars'),
        );

        self::assertSame($special, $value);
    }

    #[Test]
    public function emptyStringVsNull(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('postmeta', ['post_id' => 1, 'meta_key' => 'empty', 'meta_value' => '']);
        $wpdb->insert('postmeta', ['post_id' => 2, 'meta_key' => 'null_val', 'meta_value' => null]);

        // WordPress get_var() returns null for empty strings (by design).
        // Use get_row() to distinguish empty string from NULL.
        $emptyRow = $wpdb->get_row(
            $wpdb->prepare("SELECT meta_value FROM {$p}postmeta WHERE meta_key = %s", 'empty'),
        );
        self::assertSame('', $emptyRow->meta_value);

        $nullRow = $wpdb->get_row(
            $wpdb->prepare("SELECT meta_value FROM {$p}postmeta WHERE meta_key = %s", 'null_val'),
        );
        self::assertNull($nullRow->meta_value);
    }

    #[Test]
    public function largeTextContent(): void
    {
        $wpdb = $this->getTestWpdb();

        $largeContent = str_repeat('WordPress content block. ', 10000);

        $wpdb->insert('posts', [
            'post_title' => 'Large Post',
            'post_content' => $largeContent,
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);
        $postId = $wpdb->insert_id;

        $content = $wpdb->get_var(
            $wpdb->prepare("SELECT post_content FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
        );

        self::assertSame($largeContent, $content);
    }

    // ── WordPress Patterns ──

    #[Test]
    public function autoIncrementLastInsertId(): void
    {
        $wpdb = $this->getTestWpdb();

        $wpdb->insert('posts', ['post_title' => 'First', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id1 = $wpdb->insert_id;

        $wpdb->insert('posts', ['post_title' => 'Second', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id2 = $wpdb->insert_id;

        self::assertGreaterThan(0, $id1);
        self::assertGreaterThan($id1, $id2);
    }

    #[Test]
    public function affectedRowsOnUpdate(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'U1', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'U2', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'U3', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $result = $wpdb->query(
            $wpdb->prepare("UPDATE {$p}posts SET post_status = %s WHERE post_status = %s", 'pending', 'draft'),
        );

        self::assertSame(2, $result);
    }

    #[Test]
    public function affectedRowsOnDelete(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'D1', 'post_content' => '', 'post_status' => 'trash', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'D2', 'post_content' => '', 'post_status' => 'trash', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'K1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $result = $wpdb->query("DELETE FROM {$p}posts WHERE post_status = 'trash'");

        self::assertSame(2, $result);
    }

    #[Test]
    public function multiTableJoinWithGroupBy(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('users', ['user_login' => 'author1', 'user_email' => 'a1@e.com', 'display_name' => 'Author One']);
        $author1 = $wpdb->insert_id;
        $wpdb->insert('users', ['user_login' => 'author2', 'user_email' => 'a2@e.com', 'display_name' => 'Author Two']);
        $author2 = $wpdb->insert_id;

        $wpdb->insert('posts', ['post_title' => 'A1P1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $author1]);
        $wpdb->insert('posts', ['post_title' => 'A1P2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $author1]);
        $wpdb->insert('posts', ['post_title' => 'A1P3', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $author1]);
        $wpdb->insert('posts', ['post_title' => 'A2P1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $author2]);

        $results = $wpdb->get_results(
            "SELECT u.display_name, COUNT(p.ID) AS post_count
             FROM {$p}users u
             INNER JOIN {$p}posts p ON u.ID = p.post_author
             GROUP BY u.ID, u.display_name
             ORDER BY post_count DESC",
        );

        self::assertCount(2, $results);
        self::assertSame('Author One', $results[0]->display_name);
        self::assertSame('3', (string) $results[0]->post_count);
        self::assertSame('Author Two', $results[1]->display_name);
        self::assertSame('1', (string) $results[1]->post_count);
    }

    #[Test]
    public function leftJoinShowsMissingRelations(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'With Meta', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id1 = $wpdb->insert_id;
        $wpdb->insert('posts', ['post_title' => 'Without Meta', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $wpdb->insert('postmeta', ['post_id' => $id1, 'meta_key' => 'color', 'meta_value' => 'red']);

        $results = $wpdb->get_results(
            "SELECT p.post_title, pm.meta_value
             FROM {$p}posts p
             LEFT JOIN {$p}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'color'
             ORDER BY p.ID",
        );

        self::assertCount(2, $results);
        self::assertSame('With Meta', $results[0]->post_title);
        self::assertSame('red', $results[0]->meta_value);
        self::assertSame('Without Meta', $results[1]->post_title);
        self::assertNull($results[1]->meta_value);
    }

    // ── Advanced Patterns ──

    #[Test]
    public function caseExpressionInSelect(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Published', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'Drafted', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);

        $results = $wpdb->get_results(
            "SELECT post_title,
                    CASE post_status
                        WHEN 'publish' THEN 'live'
                        WHEN 'draft' THEN 'hidden'
                        ELSE 'other'
                    END AS visibility
             FROM {$p}posts ORDER BY post_title",
        );

        self::assertCount(2, $results);
        self::assertSame('Drafted', $results[0]->post_title);
        self::assertSame('hidden', $results[0]->visibility);
        self::assertSame('Published', $results[1]->post_title);
        self::assertSame('live', $results[1]->visibility);
    }

    #[Test]
    public function existsSubquery(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'Commented', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id1 = $wpdb->insert_id;
        $wpdb->insert('posts', ['post_title' => 'Uncommented', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $wpdb->insert('postmeta', ['post_id' => $id1, 'meta_key' => '_has_comments', 'meta_value' => '1']);

        $results = $wpdb->get_results(
            "SELECT post_title FROM {$p}posts p
             WHERE EXISTS (
                SELECT 1 FROM {$p}postmeta pm WHERE pm.post_id = p.ID AND pm.meta_key = '_has_comments'
             )",
        );

        self::assertCount(1, $results);
        self::assertSame('Commented', $results[0]->post_title);
    }

    #[Test]
    public function havingClause(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('users', ['user_login' => 'prolific', 'user_email' => 'p@e.com', 'display_name' => 'Prolific']);
        $u1 = $wpdb->insert_id;
        $wpdb->insert('users', ['user_login' => 'lazy', 'user_email' => 'l@e.com', 'display_name' => 'Lazy']);
        $u2 = $wpdb->insert_id;

        for ($i = 0; $i < 5; ++$i) {
            $wpdb->insert('posts', ['post_title' => "PP{$i}", 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $u1]);
        }
        $wpdb->insert('posts', ['post_title' => 'LP1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $u2]);

        $results = $wpdb->get_results(
            "SELECT u.display_name, COUNT(*) AS cnt
             FROM {$p}users u
             INNER JOIN {$p}posts p ON u.ID = p.post_author
             GROUP BY u.ID, u.display_name
             HAVING COUNT(*) >= 3",
        );

        self::assertCount(1, $results);
        self::assertSame('Prolific', $results[0]->display_name);
    }

    #[Test]
    public function concatInSelect(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('users', ['user_login' => 'jdoe', 'user_email' => 'j@e.com', 'display_name' => 'John Doe']);

        $results = $wpdb->get_results(
            "SELECT CONCAT(user_login, '@', display_name) AS full_ref FROM {$p}users",
        );

        self::assertCount(1, $results);
        self::assertSame('jdoe@John Doe', $results[0]->full_ref);
    }

    #[Test]
    public function ifnullCoalesce(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'IFNULL Test', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $id = $wpdb->insert_id;

        $result = $wpdb->get_var(
            "SELECT IFNULL(pm.meta_value, 'default')
             FROM {$p}posts p
             LEFT JOIN {$p}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'missing_key'
             WHERE p.ID = {$id}",
        );

        self::assertSame('default', $result);
    }

    #[Test]
    public function updateMultipleColumns(): void
    {
        $wpdb = $this->getTestWpdb();

        $wpdb->insert('posts', [
            'post_title' => 'Original',
            'post_content' => 'Original content',
            'post_status' => 'draft',
            'post_name' => 'original',
            'post_type' => 'post',
        ]);
        $postId = $wpdb->insert_id;

        $wpdb->update('posts', [
            'post_title' => 'Updated',
            'post_content' => 'Updated content',
            'post_status' => 'publish',
            'post_name' => 'updated',
        ], ['ID' => $postId]);

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}posts WHERE ID = %d", $postId),
        );

        self::assertSame('Updated', $row->post_title);
        self::assertSame('Updated content', $row->post_content);
        self::assertSame('publish', $row->post_status);
        self::assertSame('updated', $row->post_name);
    }

    #[Test]
    public function errorOnInvalidTableReturnsFalse(): void
    {
        $wpdb = $this->getTestWpdb();

        $result = $wpdb->query('SELECT * FROM nonexistent_table_xyz');

        self::assertFalse($result);
        self::assertNotSame('', $wpdb->last_error);
    }

    // ── WordPress Core DML Patterns ──

    #[Test]
    public function insertIgnore(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->query("INSERT INTO {$p}options (option_name, option_value) VALUES ('ign_test', 'first')");

        // INSERT IGNORE with duplicate key should not error
        $result = $wpdb->query("INSERT IGNORE INTO {$p}options (option_name, option_value) VALUES ('ign_test', 'second')");

        // Should not fail (true or 0, not false)
        self::assertNotFalse($result);

        // Original value should be unchanged
        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$p}options WHERE option_name = %s", 'ign_test'),
        );
        self::assertSame('first', $value);
    }

    #[Test]
    public function onDuplicateKeyUpdate(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->query("INSERT INTO {$p}options (option_name, option_value, autoload) VALUES ('odku_test', 'original', 'yes')");

        // WordPress core pattern: INSERT ... ON DUPLICATE KEY UPDATE
        $wpdb->query(
            "INSERT INTO {$p}options (option_name, option_value, autoload) VALUES ('odku_test', 'updated', 'no')
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)",
        );

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT option_value, autoload FROM {$p}options WHERE option_name = %s", 'odku_test'),
        );
        self::assertSame('updated', $row->option_value);
        self::assertSame('no', $row->autoload);
    }

    #[Test]
    public function deleteWithLimit(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        for ($i = 1; $i <= 5; ++$i) {
            $wpdb->insert('posts', ['post_title' => "DL{$i}", 'post_content' => '', 'post_status' => 'trash', 'post_type' => 'post']);
        }

        // DELETE ... LIMIT N (WordPress transient cleanup pattern)
        $wpdb->query("DELETE FROM {$p}posts WHERE post_status = 'trash' LIMIT 3");

        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$p}posts WHERE post_status = 'trash'");
        self::assertSame('2', (string) $remaining);
    }

    #[Test]
    public function updateWithLimit(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        for ($i = 1; $i <= 5; ++$i) {
            $wpdb->insert('posts', ['post_title' => "UL{$i}", 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);
        }

        $wpdb->query("UPDATE {$p}posts SET post_status = 'publish' WHERE post_status = 'draft' LIMIT 2");

        $published = $wpdb->get_var("SELECT COUNT(*) FROM {$p}posts WHERE post_status = 'publish'");
        self::assertSame('2', (string) $published);

        $draft = $wpdb->get_var("SELECT COUNT(*) FROM {$p}posts WHERE post_status = 'draft'");
        self::assertSame('3', (string) $draft);
    }

    #[Test]
    public function truncateTable(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'T1', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'T2', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $wpdb->query("TRUNCATE TABLE {$p}posts");

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$p}posts");
        self::assertSame('0', (string) $count);
    }

    #[Test]
    public function insertSelectFromDual(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        // Action Scheduler pattern: INSERT ... SELECT ... FROM DUAL WHERE NOT EXISTS
        $wpdb->query(
            "INSERT INTO {$p}options (option_name, option_value, autoload)
             SELECT 'dual_test', 'value1', 'yes' FROM DUAL
             WHERE (SELECT NULL FROM DUAL) IS NULL",
        );

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$p}options WHERE option_name = %s", 'dual_test'),
        );
        self::assertSame('value1', $value);
    }

    // ── Date/Time Functions ──

    #[Test]
    public function nowFunction(): void
    {
        $wpdb = $this->getTestWpdb();

        $result = $wpdb->get_var('SELECT NOW()');

        // Should return a valid datetime string
        self::assertNotNull($result);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}[\sT]\d{2}:\d{2}:\d{2}/', $result);
    }

    #[Test]
    public function curdateFunction(): void
    {
        $wpdb = $this->getTestWpdb();

        $result = $wpdb->get_var('SELECT CURDATE()');

        self::assertNotNull($result);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $result);
    }

    #[Test]
    public function dateAddInterval(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', [
            'post_title' => 'DateAdd',
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => '2024-01-15 10:00:00',
        ]);

        $result = $wpdb->get_var(
            "SELECT DATE_ADD(post_date, INTERVAL 1 DAY) FROM {$p}posts WHERE post_title = 'DateAdd'",
        );

        self::assertNotNull($result);
        self::assertStringContainsString('2024-01-16', $result);
    }

    #[Test]
    public function dateSubInterval(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', [
            'post_title' => 'DateSub',
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => '2024-01-15 10:30:00',
        ]);

        $result = $wpdb->get_var(
            "SELECT DATE_SUB(post_date, INTERVAL 30 MINUTE) FROM {$p}posts WHERE post_title = 'DateSub'",
        );

        self::assertNotNull($result);
        self::assertStringContainsString('2024-01-15', $result);
        self::assertStringContainsString('10:00', $result);
    }

    #[Test]
    public function monthYearDayExtraction(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', [
            'post_title' => 'Extract',
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => '2024-03-15 14:30:00',
        ]);

        $month = $wpdb->get_var("SELECT MONTH(post_date) FROM {$p}posts WHERE post_title = 'Extract'");
        $year = $wpdb->get_var("SELECT YEAR(post_date) FROM {$p}posts WHERE post_title = 'Extract'");
        $day = $wpdb->get_var("SELECT DAY(post_date) FROM {$p}posts WHERE post_title = 'Extract'");

        self::assertSame('3', (string) (int) $month);
        self::assertSame('2024', (string) (int) $year);
        self::assertSame('15', (string) (int) $day);
    }

    #[Test]
    public function unixTimestampAndFromUnixtime(): void
    {
        $wpdb = $this->getTestWpdb();

        $ts = $wpdb->get_var('SELECT UNIX_TIMESTAMP()');
        self::assertNotNull($ts);
        self::assertGreaterThan(1700000000, (int) $ts);

        $dt = $wpdb->get_var("SELECT FROM_UNIXTIME({$ts})");
        self::assertNotNull($dt);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $dt);
    }

    #[Test]
    public function datediffFunction(): void
    {
        $wpdb = $this->getTestWpdb();

        $result = $wpdb->get_var("SELECT DATEDIFF('2024-01-15', '2024-01-10')");

        self::assertSame('5', (string) (int) $result);
    }

    #[Test]
    public function dateFormatFunction(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', [
            'post_title' => 'FmtTest',
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_date' => '2024-03-15 14:05:09',
        ]);

        $result = $wpdb->get_var(
            "SELECT DATE_FORMAT(post_date, '%Y-%m-%d %H:%i') FROM {$p}posts WHERE post_title = 'FmtTest'",
        );

        self::assertSame('2024-03-15 14:05', $result);
    }

    // ── String / Comparison Functions ──

    #[Test]
    public function leftAndRightFunctions(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('options', ['option_name' => 'lr_test', 'option_value' => 'WordPress']);

        $left = $wpdb->get_var("SELECT LEFT(option_value, 4) FROM {$p}options WHERE option_name = 'lr_test'");
        self::assertSame('Word', $left);

        $right = $wpdb->get_var("SELECT RIGHT(option_value, 5) FROM {$p}options WHERE option_name = 'lr_test'");
        self::assertSame('Press', $right);
    }

    #[Test]
    public function substringFunction(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('options', ['option_name' => 'sub_test', 'option_value' => 'WordPress']);

        $result = $wpdb->get_var("SELECT SUBSTRING(option_value, 5, 5) FROM {$p}options WHERE option_name = 'sub_test'");
        self::assertSame('Press', $result);
    }

    #[Test]
    public function locateFunction(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('options', ['option_name' => 'loc_test', 'option_value' => 'Hello World']);

        $pos = $wpdb->get_var("SELECT LOCATE('World', option_value) FROM {$p}options WHERE option_name = 'loc_test'");
        self::assertSame('7', (string) (int) $pos);
    }

    #[Test]
    public function concatWsFunction(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('users', ['user_login' => 'cws', 'user_email' => 'cws@e.com', 'display_name' => 'CWS']);

        $result = $wpdb->get_var("SELECT CONCAT_WS(', ', user_login, user_email) FROM {$p}users WHERE user_login = 'cws'");
        self::assertSame('cws, cws@e.com', $result);
    }

    #[Test]
    public function ifFunction(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('posts', ['post_title' => 'IfTest', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $wpdb->insert('posts', ['post_title' => 'IfDraft', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post']);

        $results = $wpdb->get_results(
            "SELECT post_title, IF(post_status = 'publish', 'yes', 'no') AS is_pub FROM {$p}posts ORDER BY post_title",
        );

        self::assertCount(2, $results);
        self::assertSame('IfDraft', $results[0]->post_title);
        self::assertSame('no', $results[0]->is_pub);
        self::assertSame('IfTest', $results[1]->post_title);
        self::assertSame('yes', $results[1]->is_pub);
    }

    #[Test]
    public function greatestLeastFunctions(): void
    {
        $wpdb = $this->getTestWpdb();

        $g = $wpdb->get_var('SELECT GREATEST(1, 5, 3)');
        self::assertSame('5', (string) (int) $g);

        $l = $wpdb->get_var('SELECT LEAST(1, 5, 3)');
        self::assertSame('1', (string) (int) $l);
    }

    #[Test]
    public function castAsSigned(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('postmeta', ['post_id' => 1, 'meta_key' => 'num', 'meta_value' => '42']);

        $result = $wpdb->get_var("SELECT CAST(meta_value AS SIGNED) FROM {$p}postmeta WHERE meta_key = 'num'");
        self::assertSame('42', (string) (int) $result);
    }

    // ── DDL Operations ──

    #[Test]
    public function alterTableAddColumn(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->query("ALTER TABLE {$p}posts ADD COLUMN post_views bigint(20) NOT NULL DEFAULT 0");

        // Verify column exists by inserting/selecting
        $wpdb->insert('posts', ['post_title' => 'Alter', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $views = $wpdb->get_var(
            $wpdb->prepare("SELECT post_views FROM {$p}posts WHERE ID = %d", $postId),
        );
        self::assertSame('0', (string) (int) $views);
    }

    #[Test]
    public function alterTableDropColumn(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        // First add a column, then drop it
        $wpdb->query("ALTER TABLE {$p}posts ADD COLUMN temp_col varchar(50) DEFAULT 'tmp'");
        $wpdb->query("ALTER TABLE {$p}posts DROP COLUMN temp_col");

        // Verify column is gone — SELECT should not include it
        $wpdb->insert('posts', ['post_title' => 'AfterDrop', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$p}posts WHERE ID = %d", $postId),
            ARRAY_A,
        );
        self::assertArrayNotHasKey('temp_col', $row);
    }

    #[Test]
    public function alterTableAddAndDropIndex(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        // ADD INDEX
        $wpdb->query("ALTER TABLE {$p}posts ADD INDEX idx_status_type (post_status, post_type)");

        // Verify by inserting and querying (index should not change behavior, just not error)
        $wpdb->insert('posts', ['post_title' => 'Idx', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);

        $result = $wpdb->get_results(
            "SELECT post_title FROM {$p}posts WHERE post_status = 'publish' AND post_type = 'post'",
        );
        self::assertCount(1, $result);

        // DROP INDEX
        $wpdb->query("ALTER TABLE {$p}posts DROP INDEX idx_status_type");

        // Query should still work after index drop
        $result2 = $wpdb->get_results("SELECT post_title FROM {$p}posts");
        self::assertCount(1, $result2);
    }

    #[Test]
    public function alterTableRenameColumn(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->query("ALTER TABLE {$p}posts ADD COLUMN old_col varchar(50) DEFAULT 'val'");
        $wpdb->query("ALTER TABLE {$p}posts CHANGE old_col new_col varchar(50) DEFAULT 'val'");

        $wpdb->insert('posts', ['post_title' => 'Renamed', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId = $wpdb->insert_id;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$p}posts WHERE ID = %d", $postId),
            ARRAY_A,
        );
        self::assertArrayHasKey('new_col', $row);
        self::assertArrayNotHasKey('old_col', $row);
    }

    // ── Metadata / SHOW Commands ──

    #[Test]
    public function showTables(): void
    {
        $wpdb = $this->getTestWpdb();

        $wpdb->query('SHOW TABLES');

        // Extract table names from result (column name varies by engine)
        $tables = [];
        foreach ($wpdb->last_result as $row) {
            $arr = (array) $row;
            $tables[] = (string) array_shift($arr);
        }

        self::assertContains($wpdb->prefix . 'options', $tables);
        self::assertContains($wpdb->prefix . 'posts', $tables);
    }

    #[Test]
    public function showColumnsFrom(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$p}options");

        self::assertNotEmpty($columns);

        // SQLite PRAGMA table_info uses 'name', PostgreSQL information_schema uses 'column_name'
        $colNames = [];
        foreach ($columns as $row) {
            $arr = (array) $row;
            $colNames[] = $arr['name'] ?? $arr['column_name'] ?? $arr['Field'] ?? (string) array_shift($arr);
        }
        self::assertContains('option_name', $colNames);
        self::assertContains('option_value', $colNames);
    }

    #[Test]
    public function getLockAndReleaseLock(): void
    {
        $wpdb = $this->getTestWpdb();

        $acquired = $wpdb->get_var("SELECT GET_LOCK('test_lock_1', 10)");
        self::assertSame('1', (string) (int) $acquired);

        $released = $wpdb->get_var("SELECT RELEASE_LOCK('test_lock_1')");
        self::assertSame('1', (string) (int) $released);
    }

    #[Test]
    public function getLockIsReentrant(): void
    {
        $wpdb = $this->getTestWpdb();

        $first = $wpdb->get_var("SELECT GET_LOCK('test_lock_2', 10)");
        self::assertSame('1', (string) (int) $first);

        // Re-entrant: same session can acquire same lock again (MySQL 8.0 behaviour)
        $second = $wpdb->get_var("SELECT GET_LOCK('test_lock_2', 0)");
        self::assertSame('1', (string) (int) $second);

        // Release
        $wpdb->get_var("SELECT RELEASE_LOCK('test_lock_2')");
    }

    #[Test]
    public function releaseLockFailsWhenNotHeld(): void
    {
        $wpdb = $this->getTestWpdb();

        $result = $wpdb->get_var("SELECT RELEASE_LOCK('never_acquired_lock')");
        self::assertSame('0', (string) (int) $result);
    }

    #[Test]
    public function regexpOperator(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('options', ['option_name' => 'widget_text_1', 'option_value' => 'a']);
        $wpdb->insert('options', ['option_name' => 'widget_text_2', 'option_value' => 'b']);
        $wpdb->insert('options', ['option_name' => 'sidebar_widgets', 'option_value' => 'c']);

        $results = $wpdb->get_results(
            "SELECT option_name FROM {$p}options WHERE option_name REGEXP '^widget_text_[0-9]+$' ORDER BY option_name",
        );

        self::assertCount(2, $results);
        self::assertSame('widget_text_1', $results[0]->option_name);
        self::assertSame('widget_text_2', $results[1]->option_name);
    }

    // ── Security Tests ──

    #[Test]
    public function sqlInjectionInPreparedStatement(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $malicious = "'; DROP TABLE {$p}options; --";

        // Insert malicious value via prepared statement
        $wpdb->insert('options', [
            'option_name' => 'injection_test',
            'option_value' => $malicious,
        ]);

        // Value should be stored literally, not executed
        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT option_value FROM {$p}options WHERE option_name = %s", 'injection_test'),
        );
        self::assertSame($malicious, $value);

        // Options table should still exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$p}options");
        self::assertNotNull($count);
    }

    #[Test]
    public function sqlInjectionInLikePattern(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('options', ['option_name' => 'safe_option', 'option_value' => 'safe']);

        // Attempt injection via LIKE pattern
        $malicious = "%' OR '1'='1";
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT option_name FROM {$p}options WHERE option_value LIKE %s", $malicious),
        );

        // Should return 0 results (not all rows)
        self::assertCount(0, $results);
    }

    #[Test]
    public function uniqueConstraintViolationReturnsFalse(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('options', ['option_name' => 'unique_test', 'option_value' => 'first']);

        // Direct INSERT with duplicate unique key should fail
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$p}options (option_name, option_value) VALUES (%s, %s)",
                'unique_test',
                'second',
            ),
        );

        self::assertFalse($result);
        self::assertNotSame('', $wpdb->last_error);
    }

    // ── Lock Edge Cases ──

    #[Test]
    public function getLockWithNullNameReturnsZero(): void
    {
        $wpdb = $this->getTestWpdb();

        $result = $wpdb->get_var('SELECT GET_LOCK(NULL, 10)');
        self::assertSame('0', (string) (int) $result);
    }

    #[Test]
    public function multipleDistinctLocks(): void
    {
        $wpdb = $this->getTestWpdb();

        $lock1 = $wpdb->get_var("SELECT GET_LOCK('lock_a', 10)");
        $lock2 = $wpdb->get_var("SELECT GET_LOCK('lock_b', 10)");

        self::assertSame('1', (string) (int) $lock1);
        self::assertSame('1', (string) (int) $lock2);

        $wpdb->get_var("SELECT RELEASE_LOCK('lock_a')");
        $wpdb->get_var("SELECT RELEASE_LOCK('lock_b')");
    }

    // ── Date Function Edge Cases ──

    #[Test]
    public function datediffNegativeAndZero(): void
    {
        $wpdb = $this->getTestWpdb();

        // Negative: earlier - later
        $neg = $wpdb->get_var("SELECT DATEDIFF('2024-01-10', '2024-01-15')");
        self::assertSame('-5', (string) (int) $neg);

        // Zero: same date
        $zero = $wpdb->get_var("SELECT DATEDIFF('2024-01-15', '2024-01-15')");
        self::assertSame('0', (string) (int) $zero);
    }

    // ── NULL WHERE Handling ──

    #[Test]
    public function updateWithNullInWhere(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('postmeta', ['post_id' => 1, 'meta_key' => null, 'meta_value' => 'original']);

        // Update rows WHERE meta_key IS NULL
        $affected = $wpdb->update(
            'postmeta',
            ['meta_value' => 'updated'],
            ['post_id' => 1, 'meta_key' => null],
        );

        self::assertSame(1, $affected);

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT meta_value FROM {$p}postmeta WHERE post_id = %d AND meta_key IS NULL", 1),
        );
        self::assertSame('updated', $value);
    }

    #[Test]
    public function deleteWithNullInWhere(): void
    {
        $wpdb = $this->getTestWpdb();
        $p = $wpdb->prefix;

        $wpdb->insert('postmeta', ['post_id' => 2, 'meta_key' => null, 'meta_value' => 'to_delete']);
        $wpdb->insert('postmeta', ['post_id' => 2, 'meta_key' => 'keep', 'meta_value' => 'kept']);

        // Delete rows WHERE meta_key IS NULL
        $deleted = $wpdb->delete('postmeta', ['post_id' => 2, 'meta_key' => null]);

        self::assertSame(1, $deleted);

        // Non-null row should still exist
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$p}postmeta WHERE post_id = %d", 2),
        );
        self::assertSame('1', (string) $count);
    }
}
