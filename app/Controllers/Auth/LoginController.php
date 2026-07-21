<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Services\AuditLogger;
use App\Services\LoginThrottle;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Handles user login and logout across all portals (multi-role).
 */
final class LoginController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('auth.login', ['title' => 'Sign In']);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'login'    => 'required|string|max:190',
            'password' => 'required|string|max:255',
        ], [
            'login' => 'Email or username is required.',
        ]);

        $remember = $request->boolean('remember');
        $throttle = new LoginThrottle();
        $ip = $request->ip();

        // Brute-force protection: block after too many recent failures.
        if ($throttle->tooManyAttempts($data['login'], $ip)) {
            $minutes = (int) ceil($throttle->availableIn($data['login'], $ip) / 60);
            AuditLogger::log('login.locked', 'user', null, 'Login locked for ' . $data['login']);
            return $this->back(
                ['login' => ["Too many failed attempts. Please try again in {$minutes} minute(s)."]],
                ['login' => $data['login']]
            );
        }

        if (!auth()->attempt($data['login'], $data['password'], $remember)) {
            $throttle->record($data['login'], $ip, false, $request->userAgent());
            AuditLogger::log('login.failed', 'user', null, 'Failed login for ' . $data['login']);
            return $this->back(
                ['login' => ['These credentials do not match our records.']],
                ['login' => $data['login']]
            );
        }

        // Successful login: clear failed attempts and record success.
        $throttle->record($data['login'], $ip, true, $request->userAgent());
        $throttle->clear($data['login'], $ip);

        // Rotate the CSRF token after privilege change.
        unset($_SESSION['_csrf_token']);
        session()->regenerate();

        $user = auth()->user();
        AuditLogger::log('login.success', 'user', $user['id'] ?? null, 'User logged in');

        $role = $user['roles'][0] ?? 'student';
        $home = config("auth.home_routes.{$role}", '/admin/dashboard');

        return $this->redirect($home, ['success' => 'Welcome back, ' . e($user['name'] ?? '') . '!']);
    }

    public function logout(Request $request): Response
    {
        $userId = auth()->id();
        auth()->logout();
        AuditLogger::log('logout', 'user', $userId, 'User logged out');

        return $this->redirect('/login', ['success' => 'You have been signed out.']);
    }
}
