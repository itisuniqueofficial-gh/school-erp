<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Book;
use App\Services\AuditLogger;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Library: catalogue, issue/return, and fines.
 */
final class LibraryController extends Controller
{
    private const FINE_PER_DAY = 2.0;

    public function index(Request $request): Response
    {
        $search = (string) $request->query('search', '');
        $page = max(1, (int) $request->query('page', 1));
        $result = Book::paginate($page, (int) config('app.per_page', 15), Book::filtered($search));

        return $this->view('admin.library.index', [
            'title'      => 'Library',
            'books'      => $result['data'],
            'meta'       => $result['meta'],
            'categories' => QueryBuilder::table('book_categories')->orderBy('name')->get(),
            'issues'     => Book::recentIssues(),
            'search'     => $search,
        ]);
    }

    public function storeBook(Request $request): Response
    {
        $data = $this->validate($request, [
            'title'        => 'required|string|max:255',
            'author'       => 'nullable|string|max:200',
            'isbn'         => 'nullable|string|max:20',
            'total_copies' => 'required|integer|min:1',
        ]);

        $copies = (int) $data['total_copies'];
        $id = Book::create([
            'book_category_id' => (int) ($request->input('book_category_id') ?: 0) ?: null,
            'title'            => $data['title'],
            'author'           => $data['author'] ?? null,
            'isbn'             => $data['isbn'] ?? null,
            'publisher'        => $request->input('publisher'),
            'total_copies'     => $copies,
            'available_copies' => $copies,
            'price'            => (float) $request->input('price', 0),
        ]);

        AuditLogger::log('library.book_added', 'book', $id, 'Added book ' . $data['title']);
        return $this->redirect('/admin/library', ['success' => 'Book added to catalogue.']);
    }

    public function issue(Request $request): Response
    {
        $data = $this->validate($request, [
            'book_id'    => 'required|integer',
            'student_id' => 'required|integer',
            'due_date'   => 'required|date',
        ]);

        $book = Book::find((int) $data['book_id']);
        if ($book === null || (int) $book['available_copies'] < 1) {
            return $this->back(['issue' => ['This book is not available.']]);
        }

        try {
            Connection::instance()->transaction(function () use ($data, $book): void {
                QueryBuilder::table('book_issues')->insert([
                    'book_id'    => (int) $data['book_id'],
                    'student_id' => (int) $data['student_id'],
                    'issue_date' => date('Y-m-d'),
                    'due_date'   => $data['due_date'],
                    'status'     => 'issued',
                    'issued_by'  => auth()->id(),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                QueryBuilder::table('books')->where('id', (int) $data['book_id'])->update([
                    'available_copies' => (int) $book['available_copies'] - 1,
                ]);
            });
        } catch (\Throwable $e) {
            logger()->error('Book issue failed: ' . $e->getMessage());
            return $this->back(['issue' => ['Could not issue the book.']]);
        }

        AuditLogger::log('library.issued', 'book', (int) $data['book_id'], 'Issued book');
        return $this->redirect('/admin/library', ['success' => 'Book issued.']);
    }

    public function returnBook(Request $request): Response
    {
        $issueId = (int) $request->input('issue_id', 0);
        $issue = QueryBuilder::table('book_issues')->where('id', $issueId)->first();
        if ($issue === null || $issue['status'] === 'returned') {
            return $this->back(['return' => ['Invalid issue record.']]);
        }

        // Calculate a fine for overdue returns.
        $fine = 0.0;
        $due = strtotime((string) $issue['due_date']);
        if ($due && time() > $due) {
            $days = (int) floor((time() - $due) / 86400);
            $fine = $days * self::FINE_PER_DAY;
        }

        try {
            Connection::instance()->transaction(function () use ($issue, $issueId, $fine): void {
                QueryBuilder::table('book_issues')->where('id', $issueId)->update([
                    'return_date' => date('Y-m-d'),
                    'status'      => 'returned',
                    'fine_amount' => $fine,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                $book = QueryBuilder::table('books')->where('id', (int) $issue['book_id'])->first();
                if ($book) {
                    QueryBuilder::table('books')->where('id', (int) $issue['book_id'])->update([
                        'available_copies' => min((int) $book['total_copies'], (int) $book['available_copies'] + 1),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            logger()->error('Book return failed: ' . $e->getMessage());
            return $this->back(['return' => ['Could not process the return.']]);
        }

        AuditLogger::log('library.returned', 'book', (int) $issue['book_id'], "Returned book, fine {$fine}");
        $msg = $fine > 0 ? "Book returned. Fine due: " . money($fine) : 'Book returned.';
        return $this->redirect('/admin/library', ['success' => $msg]);
    }
}
