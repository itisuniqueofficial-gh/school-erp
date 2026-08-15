<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $books */
/** @var array<string,int> $meta */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<int,array<string,mixed>> $issues */
/** @var string $search */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-4">
        <form method="GET" class="card p-4">
            <div class="flex gap-3">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search title / author / ISBN" class="form-input">
                <button class="btn btn-secondary" aria-label="Search books"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
            </div>
        </form>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Available</th></tr></thead>
                <tbody>
                <?php if (empty($books)): ?><tr><td colspan="4" class="py-8 text-center text-slate-400">No books.</td></tr>
                <?php else: foreach ($books as $b): ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) $b['title']) ?></td>
                        <td><?= e((string) ($b['author'] ?? '—')) ?></td>
                        <td><?= e((string) ($b['category_name'] ?? '—')) ?></td>
                        <td><span class="badge <?= (int) $b['available_copies'] > 0 ? 'badge-green' : 'badge-red' ?>"><?= (int) $b['available_copies'] ?>/<?= (int) $b['total_copies'] ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php $this->include('partials.pagination', ['meta' => $meta]); ?>

        <div class="card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-3 flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">Recent issues</h2>
            </div>
            <table class="data-table">
                <thead><tr><th>Book</th><th>Borrower</th><th>Due</th><th>Status</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                <?php if (empty($issues)): ?><tr><td colspan="5" class="py-6 text-center text-slate-400">No issues.</td></tr>
                <?php else: foreach ($issues as $i): ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) ($i['book_title'] ?? '—')) ?></td>
                        <td><?= e((string) (($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? ''))) ?></td>
                        <td class="text-slate-500"><?= e((string) $i['due_date']) ?></td>
                        <td><span class="badge <?= $i['status'] === 'returned' ? 'badge-green' : 'badge-amber' ?>"><?= e(ucfirst((string) $i['status'])) ?></span></td>
                        <td class="text-right">
                            <?php if ($i['status'] !== 'returned' && auth()->can('library.manage')): ?>
                            <form method="POST" action="<?= e(url('/admin/library/return')) ?>" class="inline">
                                <?= csrf_field() ?><input type="hidden" name="issue_id" value="<?= (int) $i['id'] ?>">
                                <button class="text-brand-600 hover:underline text-sm">Return</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (auth()->can('library.manage')): ?>
    <div class="space-y-4">
        <div class="card p-5">
            <h2 class="font-semibold text-slate-800">Add book</h2>
            <form method="POST" action="<?= e(url('/admin/library/books')) ?>" class="mt-4 space-y-3" data-loading>
                <?= csrf_field() ?>
                <div><label class="form-label required" for="title">Title</label><input id="title" name="title" class="form-input" required></div>
                <div><label class="form-label" for="author">Author</label><input id="author" name="author" class="form-input"></div>
                <div><label class="form-label" for="book_category_id">Category</label><select id="book_category_id" name="book_category_id" class="form-select"><option value="">—</option><?php foreach($categories as $cat):?><option value="<?=(int)$cat['id']?>"><?=e((string)$cat['name'])?></option><?php endforeach;?></select></div>
                <div><label class="form-label" for="isbn">ISBN</label><input id="isbn" name="isbn" class="form-input"></div>
                <div><label class="form-label required" for="total_copies">Copies</label><input id="total_copies" name="total_copies" type="number" min="1" value="1" class="form-input" required></div>
                <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add book</button>
            </form>
        </div>
        <div class="card p-5">
            <h2 class="font-semibold text-slate-800">Issue book</h2>
            <form method="POST" action="<?= e(url('/admin/library/issue')) ?>" class="mt-4 space-y-3" data-loading>
                <?= csrf_field() ?>
                <div><label class="form-label required" for="book_id">Book ID</label><input id="book_id" name="book_id" type="number" class="form-input" required></div>
                <div><label class="form-label required" for="student_id">Student ID</label><input id="student_id" name="student_id" type="number" class="form-input" required></div>
                <div><label class="form-label required" for="due_date">Due date</label><input id="due_date" name="due_date" type="date" class="form-input" required></div>
                <button class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-hand-holding"></i> Issue</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
