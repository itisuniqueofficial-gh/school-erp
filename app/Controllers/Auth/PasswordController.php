<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Services\AuditLogger;
use App\Services\MailService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;
use Core\Security\Hash;

/**
 * Handles forgot-password, reset-password and change-password flows.
 */
final class PasswordController extends Controller
{
    // ---- Forgot password --------------------------------------------------

    public function showForgot(Request $request): Response
    {
        return $this->view('auth.forgot', ['title' => 'Forgot Password']);
    }

    public function sendReset(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email|max:190']);

        $user = QueryBuilder::table('users')->where('email', $data['email'])->whereNull('deleted_at')->first();

        // Always behave the same to avoid user enumeration.
        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            $ttl = (int) config('auth.reset_token_ttl', 60);

            // Invalidate any previous tokens for this email.
            QueryBuilder::table('password_resets')->where('email', $data['email'])->delete();
            QueryBuilder::table('password_resets')->insert([
                'email'      => $data['email'],
                'token'      => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + $ttl * 60),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $link = url('/reset-password/' . $token) . '?email=' . urlencode($data['email']);
            MailService::send(
                $data['email'],
                'Reset your password',
                "We received a request to reset your password.\n\nReset link: {$link}\n\nThis link expires in {$ttl} minutes. If you did not request this, ignore this email."
            );
            AuditLogger::log('password.reset_requested', 'user', $user['id'], 'Password reset requested');
        }

        return $this->redirect('/forgot-password', [
            'success' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    // ---- Reset password ---------------------------------------------------

    public function showReset(Request $request, string $token): Response
    {
        return $this->view('auth.reset', [
            'title' => 'Reset Password',
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, [
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|password|confirmed',
        ], [
            'password' => 'Password must be at least 8 characters and match the confirmation.',
        ]);

        $record = QueryBuilder::table('password_resets')
            ->where('email', $data['email'])
            ->first();

        if ($record === null
            || !hash_equals((string) $record['token'], hash('sha256', $data['token']))
            || strtotime((string) $record['expires_at']) < time()
        ) {
            return $this->back(['email' => ['This password reset token is invalid or has expired.']]);
        }

        QueryBuilder::table('users')->where('email', $data['email'])->update([
            'password'   => Hash::make($data['password']),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        QueryBuilder::table('password_resets')->where('email', $data['email'])->delete();
        AuditLogger::log('password.reset', 'user', null, 'Password reset completed for ' . $data['email']);

        return $this->redirect('/login', ['success' => 'Your password has been reset. Please sign in.']);
    }

    // ---- Change password (authenticated) ----------------------------------

    public function showChange(Request $request): Response
    {
        return $this->view('auth.change', ['title' => 'Change Password']);
    }

    public function change(Request $request): Response
    {
        $data = $this->validate($request, [
            'current_password' => 'required|string',
            'password'         => 'required|string|password|confirmed',
        ]);

        $userId = auth()->id();
        $user = QueryBuilder::table('users')->where('id', $userId)->first();

        if ($user === null || !Hash::verify($data['current_password'], (string) $user['password'])) {
            return $this->back(['current_password' => ['Your current password is incorrect.']]);
        }

        QueryBuilder::table('users')->where('id', $userId)->update([
            'password'   => Hash::make($data['password']),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        AuditLogger::log('password.changed', 'user', $userId, 'Password changed');

        return $this->redirect('/change-password', ['success' => 'Your password has been updated.']);
    }
}
