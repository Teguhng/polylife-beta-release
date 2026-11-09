<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Ai\AiChatController;
use App\Http\Controllers\Controller;
use App\Models\AiChatSession;
use App\Services\Ai\AiConversationBranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiWorkspaceController extends Controller
{
    public function __construct(private readonly AiConversationBranchService $branchService) {}

    public function sessions(Request $request): JsonResponse
    {
        $sessions = AiChatSession::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->latest('id')
            ->limit(30)
            ->get(['id', 'title', 'updated_at']);

        return response()->json(['status' => 'success', 'sessions' => $sessions]);
    }

    public function messages(Request $request, int $session): JsonResponse
    {
        $conversation = AiChatSession::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($session);
        $messages = $this->branchService->recentActiveLineage($conversation, limit: 100)
            ->filter(fn ($message) => $message->status === 'completed'
                && in_array($message->role, ['user', 'assistant'], true))
            ->values()
            ->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at,
            ]);

        return response()->json([
            'status' => 'success',
            'session' => ['id' => $conversation->id, 'title' => $conversation->title],
            'messages' => $messages,
        ]);
    }
}
