<?php

declare(strict_types=1);

namespace App\Controllers\Portal;

use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Parent portal — view children, their attendance and fees.
 */
final class ParentPortalController extends Controller
{
    /** @return array<int,array<string,mixed>> */
    private function resolveChildren(): array
    {
        try {
            $guardian = QueryBuilder::table('guardians')->where('user_id', auth()->id())->first();
            if ($guardian === null) {
                return [];
            }
            return QueryBuilder::table('student_guardians')
                ->select(['students.*'])
                ->join('students', 'student_guardians.student_id', '=', 'students.id')
                ->where('student_guardians.guardian_id', (int) $guardian['id'])
                ->whereNull('students.deleted_at')
                ->get();
        } catch (\Throwable) {
            return [];
        }
    }

    public function dashboard(Request $request): Response
    {
        return $this->view('portal.parent.dashboard', [
            'title'    => 'Parent Dashboard',
            'children' => $this->resolveChildren(),
        ]);
    }

    public function children(Request $request): Response
    {
        return $this->view('portal.parent.dashboard', [
            'title'    => 'My Children',
            'children' => $this->resolveChildren(),
        ]);
    }

    public function fees(Request $request): Response
    {
        $children = $this->resolveChildren();
        $invoices = [];
        try {
            $ids = array_map(static fn ($c) => (int) $c['id'], $children);
            if ($ids !== []) {
                $invoices = QueryBuilder::table('fee_invoices')->whereIn('student_id', $ids)->orderBy('id', 'DESC')->get();
            }
        } catch (\Throwable) {
        }
        return $this->view('portal.parent.fees', [
            'title' => 'Children Fees', 'invoices' => $invoices,
        ]);
    }
}
