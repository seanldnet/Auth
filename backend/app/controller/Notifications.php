<?php
declare(strict_types=1);

namespace app\controller;

use app\auth\AuthContext;
use app\notification\NotificationService;
use RuntimeException;
use think\Request;
use think\response\Json;

final class Notifications
{
    public function index(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        return json(['success' => true, 'data' => (new NotificationService())->list(
            (int)$actor['id'], (int)$request->get('page', 1), (int)$request->get('page_size', 20),
        )]);
    }

    public function read(Request $request, string $notificationId): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        try {
            (new NotificationService())->markRead((int)$actor['id'], (int)$notificationId);
            return json(['success' => true]);
        } catch (RuntimeException $e) {
            return json(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    public function readAll(Request $request): Json
    {
        $actor = AuthContext::get($request);
        if ($actor === null) return json(['success' => false, 'message' => '请先登录'], 401);
        $count = (new NotificationService())->markAllRead((int)$actor['id']);
        return json(['success' => true, 'data' => ['marked_read' => $count]]);
    }
}
