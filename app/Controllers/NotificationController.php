<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * In-app notification centre available to every authenticated user.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $svc = new NotificationService();
        $userId = (int) auth()->id();
        return $this->view('notifications.index', [
            'title'         => 'Notifications',
            'notifications' => $svc->recent($userId, 50),
            'unread'        => $svc->unreadCount($userId),
        ]);
    }

    public function readAll(Request $request): Response
    {
        (new NotificationService())->markAllRead((int) auth()->id());
        return $this->redirect('/notifications', ['success' => 'All notifications marked as read.']);
    }

    public function read(Request $request, string $id): Response
    {
        $svc = new NotificationService();
        $userId = (int) auth()->id();
        $svc->markRead((int) $id, $userId);
        // Follow the notification's link if it has one.
        $n = null;
        foreach ($svc->recent($userId, 50) as $row) {
            if ((int) $row['id'] === (int) $id) {
                $n = $row;
                break;
            }
        }
        $link = $n['link'] ?? null;
        return $this->redirect($link ?: '/notifications');
    }
}
