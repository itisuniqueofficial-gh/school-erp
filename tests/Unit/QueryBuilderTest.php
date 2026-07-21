<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SQL generation (no database connection required).
 */
final class QueryBuilderTest extends TestCase
{
    public function testSimpleSelect(): void
    {
        $sql = QueryBuilder::table('students')->toSql();
        $this->assertSame('SELECT * FROM `students`', $sql);
    }

    public function testWhereAndOrder(): void
    {
        $sql = QueryBuilder::table('students')
            ->select(['id', 'first_name'])
            ->where('status', 'active')
            ->orderBy('id', 'DESC')
            ->toSql();
        $this->assertStringContainsString('WHERE `status` = ?', $sql);
        $this->assertStringContainsString('ORDER BY `id` DESC', $sql);
        $this->assertStringContainsString('`first_name`', $sql);
    }

    public function testJoinAndLimit(): void
    {
        $sql = QueryBuilder::table('students')
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->limit(10)->offset(20)
            ->toSql();
        $this->assertStringContainsString('LEFT JOIN `classes` ON `students`.`class_id` = `classes`.`id`', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 20', $sql);
    }

    public function testWhereInGeneratesPlaceholders(): void
    {
        $sql = QueryBuilder::table('students')->whereIn('id', [1, 2, 3])->toSql();
        $this->assertStringContainsString('IN (?, ?, ?)', $sql);
    }
}
