<?php

namespace App\Http\Controllers;

use App\Notifications\SystemNotice;
use Illuminate\Http\Request;


class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
            'data' => $user->notifications()->latest()->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        $request->user()->notify(
            new SystemNotice($validated['message'], $validated['url'] ?? null)
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully.',
        ], 201);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['success' => true, 'message' => 'Notification marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    public function destroy(Request $request, string $id)
    {
        $deleted = $request->user()->notifications()->whereKey($id)->delete();

        if (!$deleted) {
            return response()->json(['success' => false, 'error' => 'Notification not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Notification deleted.']);
    }
}
