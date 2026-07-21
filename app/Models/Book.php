<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Library book model.
 */
final class Book extends Model
{
    protected string $table = 'books';
    protected array $fillable = [
        'book_category_id', 'title', 'author', 'publisher', 'isbn', 'edition',
        'rack_no', 'total_copies', 'available_copies', 'price',
    ];

    public static function filtered(string $search = ''): QueryBuilder
    {
        $q = QueryBuilder::table('books')
            ->select(['books.*', 'book_categories.name AS category_name'])
            ->leftJoin('book_categories', 'books.book_category_id', '=', 'book_categories.id');

        if ($search !== '') {
            $t = '%' . $search . '%';
            $q->whereRaw('(`books`.`title` LIKE ? OR `books`.`author` LIKE ? OR `books`.`isbn` LIKE ?)', [$t, $t, $t]);
        }
        return $q->orderBy('books.title');
    }

    /** @return array<int,array<string,mixed>> Recent issues with book + borrower. */
    public static function recentIssues(int $limit = 15): array
    {
        return QueryBuilder::table('book_issues')
            ->select([
                'book_issues.*', 'books.title AS book_title',
                'students.first_name', 'students.last_name',
            ])
            ->leftJoin('books', 'book_issues.book_id', '=', 'books.id')
            ->leftJoin('students', 'book_issues.student_id', '=', 'students.id')
            ->orderBy('book_issues.id', 'DESC')
            ->limit($limit)
            ->get();
    }
}
