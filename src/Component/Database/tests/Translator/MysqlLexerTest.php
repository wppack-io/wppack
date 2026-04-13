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

namespace WpPack\Component\Database\Tests\Translator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Translator\MysqlLexer;
use WpPack\Component\Database\Translator\MysqlTokenType;

final class MysqlLexerTest extends TestCase
{
    private MysqlLexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new MysqlLexer();
    }

    #[Test]
    public function tokenizeSimpleSelect(): void
    {
        $tokens = $this->lexer->tokenize('SELECT * FROM `posts`');

        $types = array_map(fn ($t) => $t->type, $tokens);
        $values = array_map(fn ($t) => $t->value, $tokens);

        self::assertSame(MysqlTokenType::Keyword, $types[0]);    // SELECT
        self::assertSame(MysqlTokenType::Whitespace, $types[1]);
        self::assertSame(MysqlTokenType::Operator, $types[2]);    // *
        self::assertSame(MysqlTokenType::Whitespace, $types[3]);
        self::assertSame(MysqlTokenType::Keyword, $types[4]);    // FROM
        self::assertSame(MysqlTokenType::Whitespace, $types[5]);
        self::assertSame(MysqlTokenType::QuotedIdentifier, $types[6]); // `posts`
        self::assertSame('`posts`', $values[6]);
    }

    #[Test]
    public function tokenizeStringLiterals(): void
    {
        $tokens = $this->lexer->tokenize("'hello world'");

        self::assertCount(1, $tokens);
        self::assertSame(MysqlTokenType::StringLiteral, $tokens[0]->type);
        self::assertSame("'hello world'", $tokens[0]->value);
    }

    #[Test]
    public function tokenizeEscapedStringLiteral(): void
    {
        $tokens = $this->lexer->tokenize("'it\\'s a test'");

        self::assertCount(1, $tokens);
        self::assertSame("'it\\'s a test'", $tokens[0]->value);
    }

    #[Test]
    public function tokenizeBacktickIdentifierWithEscapedBacktick(): void
    {
        $tokens = $this->lexer->tokenize('`col``name`');

        self::assertCount(1, $tokens);
        self::assertSame(MysqlTokenType::QuotedIdentifier, $tokens[0]->type);
        self::assertSame('`col``name`', $tokens[0]->value);
    }

    #[Test]
    public function tokenizeNumbers(): void
    {
        $tokens = $this->lexer->tokenize('42 3.14');

        $numbers = array_filter($tokens, fn ($t) => $t->type === MysqlTokenType::NumberLiteral);

        self::assertCount(2, $numbers);
    }

    #[Test]
    public function tokenizePlaceholders(): void
    {
        $tokens = $this->lexer->tokenize('SELECT * FROM t WHERE id = ? AND name = %s');

        $placeholders = array_values(array_filter($tokens, fn ($t) => $t->type === MysqlTokenType::Placeholder));

        self::assertCount(2, $placeholders);
        self::assertSame('?', $placeholders[0]->value);
        self::assertSame('%s', $placeholders[1]->value);
    }

    #[Test]
    public function tokenizeSingleLineComment(): void
    {
        $tokens = $this->lexer->tokenize("SELECT 1 -- comment\nSELECT 2");

        $comments = array_filter($tokens, fn ($t) => $t->type === MysqlTokenType::Comment);

        self::assertCount(1, $comments);
    }

    #[Test]
    public function tokenizeBlockComment(): void
    {
        $tokens = $this->lexer->tokenize('SELECT /* comment */ 1');

        $comments = array_filter($tokens, fn ($t) => $t->type === MysqlTokenType::Comment);

        self::assertCount(1, $comments);
    }

    #[Test]
    public function tokenizePunctuation(): void
    {
        $tokens = $this->lexer->tokenize('(a, b)');

        $puncts = array_values(array_filter($tokens, fn ($t) => $t->type === MysqlTokenType::Punctuation));

        self::assertCount(3, $puncts);
        self::assertSame('(', $puncts[0]->value);
        self::assertSame(',', $puncts[1]->value);
        self::assertSame(')', $puncts[2]->value);
    }

    #[Test]
    public function tokenizeOperators(): void
    {
        $tokens = $this->lexer->tokenize('a = 1 AND b != 2 AND c >= 3');

        $ops = array_values(array_filter($tokens, fn ($t) => $t->type === MysqlTokenType::Operator));

        self::assertSame('=', $ops[0]->value);
        self::assertSame('!=', $ops[1]->value);
        self::assertSame('>=', $ops[2]->value);
    }

    #[Test]
    public function keywordsVsIdentifiers(): void
    {
        $tokens = $this->lexer->tokenize('SELECT custom_column FROM my_table');

        self::assertSame(MysqlTokenType::Keyword, $tokens[0]->type);     // SELECT
        self::assertSame(MysqlTokenType::Identifier, $tokens[2]->type);  // custom_column
        self::assertSame(MysqlTokenType::Keyword, $tokens[4]->type);     // FROM
        self::assertSame(MysqlTokenType::Identifier, $tokens[6]->type);  // my_table
    }

    #[Test]
    public function stringLiteralNotMisidentifiedAsKeyword(): void
    {
        $tokens = $this->lexer->tokenize("INSERT INTO t VALUES ('SELECT * FROM users')");

        $stringTokens = array_filter($tokens, fn ($t) => $t->type === MysqlTokenType::StringLiteral);

        self::assertCount(1, $stringTokens);
        $string = array_values($stringTokens)[0];
        self::assertSame("'SELECT * FROM users'", $string->value);
    }
}
