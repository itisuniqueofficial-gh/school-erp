<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Staff;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\AuditLogger;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Self-service account area: lets the signed-in user manage their own profile
 * picture. Writes to users.avatar and mirrors to their linked staff.photo so
 * the image is consistent across admin surfaces and the navbar.
 */
final class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = User::find((int) auth()->id());
        if ($user === null) {
            abort(404, 'Account not found.');
        }
        return $this->view('account.profile', [
            'title' => 'My Profile',
            'user'  => $user,
        ]);
    }

    public function uploadPhoto(Request $request): Response
    {
        $userId = (int) auth()->id();
        $user = User::find($userId);
        if ($user === null) {
            abort(404, 'Account not found.');
        }
        $file = $request->file('photo');
        if ($file === null || !$file->isValid()) {
            return $this->redirect('/account', ['error' => 'Please choose an image to upload.']);
        }
        $svc = new AvatarService();
        $res = $svc->store($file);
        if (!$res['ok']) {
            return $this->redirect('/account', ['error' => $res['message']]);
        }
        $svc->delete($user['avatar'] ?? null);
        User::updateById($userId, ['avatar' => $res['filename']]);
        $this->mirrorToStaff($userId, $res['filename']);
        AuditLogger::log('account.photo_updated', 'user', $userId, 'Updated own profile picture');
        return $this->redirect('/account', ['success' => 'Profile picture updated.']);
    }

    public function removePhoto(Request $request): Response
    {
        $userId = (int) auth()->id();
        $user = User::find($userId);
        if ($user === null) {
            abort(404, 'Account not found.');
        }
        (new AvatarService())->delete($user['avatar'] ?? null);
        User::updateById($userId, ['avatar' => null]);
        $this->mirrorToStaff($userId, null);
        AuditLogger::log('account.photo_removed', 'user', $userId, 'Removed own profile picture');
        return $this->redirect('/account', ['success' => 'Profile picture removed.']);
    }

    private function mirrorToStaff(int $userId, ?string $filename): void
    {
        $staff = QueryBuilder::table('staff')->where('user_id', $userId)->whereNull('deleted_at')->first();
        if ($staff !== null) {
            Staff::updateById((int) $staff['id'], ['photo' => $filename]);
        }
    }
}
