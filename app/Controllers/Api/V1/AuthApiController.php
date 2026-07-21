<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Services\ApiTokenService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;
use Core\Security\Hash;

/**
 * API authentication: token issuance and identity.
 */
final class AuthApiController extends Controller
{
    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $user = QueryBuilder::table('users')
            ->where('email', $data['email'])->orWhere('username', $data['email'])
            ->whereNull('deleted_at')->first();

        if ($user === null || (int) ($user['is_active'] ?? 0) !== 1 || !Hash::verify($data['password'], (string) $user['password'])) {
            return $this->error('Invalid credentials.', 401);
        }

        $token = (new ApiTokenService())->create((int) $user['id'], 'api-login');
        unset($user['password']);

        return $this->success([
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('auth.api.ttl', 3600),
            'user'       => $user,
        ], 'Login successful.');
    }

    public function refresh(Request $request): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return $this->error('Token required.', 401);
        }
        $service = new ApiTokenService();
        $userId = $service->resolve($token);
        if ($userId === null) {
            return $this->error('Invalid or expired token.', 401);
        }
        $service->revoke($token);
        $new = $service->create($userId, 'api-refresh');
        return $this->success(['token' => $new, 'token_type' => 'Bearer'], 'Token refreshed.');
    }

    public function me(Request $request): Response
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->error('Unauthenticated.', 401);
        }
        return $this->success($user, 'OK');
    }

    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();
        if ($token !== null) {
            (new ApiTokenService())->revoke($token);
        }
        return $this->success(null, 'Logged out.');
    }
}
