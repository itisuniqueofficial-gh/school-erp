<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Models\Book;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * REST API for the library catalogue.
 */
final class LibraryApiController extends Controller
{
    public function books(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $result = Book::paginate($page, $perPage, Book::filtered((string) $request->query('search', '')));
        return Response::json(['success' => true, 'data' => $result['data'], 'meta' => $result['meta']]);
    }
}
