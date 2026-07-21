<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Handles the public landing page and health-check endpoint.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        // Redirect authenticated users to their portal home.
        if (auth()->check()) {
            $user = auth()->user();
            $role = $user['roles'][0] ?? 'student';
            $home = config("auth.home_routes.{$role}", '/admin/dashboard');
            return $this->redirect($home);
        }

        return $this->view('home.index', [
            'title' => 'Welcome',
        ]);
    }

    /**
     * Lightweight health check for load balancers / uptime monitors.
     */
    public function health(Request $request): Response
    {
        return $this->json([
            'status'    => 'ok',
            'app'       => config('app.name'),
            'version'   => config('app.version'),
            'timestamp' => now(),
        ]);
    }
}
