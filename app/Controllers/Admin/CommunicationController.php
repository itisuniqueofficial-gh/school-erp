<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use App\Services\MailService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Communication: notices, internal messages, and notifications.
 * Email/SMS delivery is delegated to the pluggable MailService.
 */
final class CommunicationController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.communication.index', [
            'title'    => 'Communication',
            'notices'  => QueryBuilder::table('notices')->orderBy('id', 'DESC')->limit(30)->get(),
            'messages' => QueryBuilder::table('messages')
                ->select(['messages.*', 'users.name AS sender_name'])
                ->leftJoin('users', 'messages.sender_id', '=', 'users.id')
                ->orderBy('messages.id', 'DESC')->limit(20)->get(),
        ]);
    }

    public function storeNotice(Request $request): Response
    {
        $data = $this->validate($request, [
            'title'    => 'required|string|max:200',
            'body'     => 'required|string',
            'audience' => 'required|in:all,students,parents,teachers,staff',
        ]);

        $id = QueryBuilder::table('notices')->insert([
            'title'        => $data['title'],
            'body'         => $data['body'],
            'audience'     => $data['audience'],
            'is_published' => 1,
            'published_at' => date('Y-m-d H:i:s'),
            'created_by'   => auth()->id(),
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        AuditLogger::log('communication.notice', 'notice', $id, 'Published notice ' . $data['title']);
        return $this->redirect('/admin/communication', ['success' => 'Notice published.']);
    }

    public function sendMessage(Request $request): Response
    {
        $data = $this->validate($request, [
            'subject' => 'required|string|max:200',
            'body'    => 'required|string',
        ]);

        try {
            Connection::instance()->transaction(function () use ($data, $request): void {
                $messageId = QueryBuilder::table('messages')->insert([
                    'sender_id'  => auth()->id(),
                    'subject'    => $data['subject'],
                    'body'       => $data['body'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                // Deliver to all active users as an in-app notification + email.
                $recipients = QueryBuilder::table('users')->select(['id', 'email'])
                    ->where('is_active', 1)->whereNull('deleted_at')->get();

                foreach ($recipients as $user) {
                    QueryBuilder::table('message_recipients')->insert([
                        'message_id'   => $messageId,
                        'recipient_id' => (int) $user['id'],
                    ]);
                    QueryBuilder::table('notifications')->insert([
                        'user_id'    => (int) $user['id'],
                        'type'       => 'message',
                        'title'      => $data['subject'],
                        'body'       => mb_substr($data['body'], 0, 480),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    if (!empty($user['email'])) {
                        MailService::send((string) $user['email'], $data['subject'], $data['body']);
                    }
                }
            });
        } catch (\Throwable $e) {
            logger()->error('Message send failed: ' . $e->getMessage());
            return $this->back(['message' => ['Could not send the message.']]);
        }

        AuditLogger::log('communication.message', 'message', null, 'Broadcast message');
        return $this->redirect('/admin/communication', ['success' => 'Message sent to all users.']);
    }
}
