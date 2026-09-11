<?php

declare(strict_types=1);

namespace tests\Unit\Database;

use core\database\SqlScript;
use tests\TestCase;

final class SqlScriptTest extends TestCase
{
    public function test_splits_on_semicolons_outside_quotes(): void
    {
        $sql = "CREATE TABLE a (id int);\nINSERT INTO a VALUES (1);";
        $this->assertSame(['CREATE TABLE a (id int)', 'INSERT INTO a VALUES (1)'], SqlScript::split($sql));
    }

    public function test_semicolons_inside_strings_and_identifiers_are_kept(): void
    {
        $sql = "INSERT INTO t (`a;b`, c) VALUES ('x;y', \"p;q\");SELECT 1";
        $this->assertSame(["INSERT INTO t (`a;b`, c) VALUES ('x;y', \"p;q\")", 'SELECT 1'], SqlScript::split($sql));
    }

    public function test_escaped_quotes_do_not_end_the_string(): void
    {
        $sql = "INSERT INTO t VALUES ('it\\'s; fine');SELECT 2;";
        $this->assertSame(["INSERT INTO t VALUES ('it\\'s; fine')", 'SELECT 2'], SqlScript::split($sql));
    }

    public function test_line_comments_are_skipped_even_with_quotes_or_semicolons(): void
    {
        $sql = "-- 头部注释; it's fine\nSELECT 'a;b';\n-- 结尾注释";
        $this->assertSame(["SELECT 'a;b'"], SqlScript::split($sql));
    }

    public function test_double_dash_inside_a_string_is_not_a_comment(): void
    {
        $this->assertSame(["SELECT '-- not comment'"], SqlScript::split("SELECT '-- not comment';"));
    }
}
