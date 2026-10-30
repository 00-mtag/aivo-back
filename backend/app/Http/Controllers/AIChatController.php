<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AIChatController extends Controller
{
    public function index(Request $request)
    {
        $conversations = DB::table('conversations')
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($conversation) => $this->conversationWithMessages($conversation));

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'sometimes|nullable|string|max:255',
            'content' => 'sometimes|nullable|string',
        ]);
        $userId = $request->user()->user_id;

        $conversationId = DB::table('conversations')->insertGetId([
            'user_id' => $userId,
            'title' => $validated['title'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'conversation_id');

        if (! empty($validated['content'])) {
            DB::table('messages')->insert([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'sender' => 'user',
                'content' => $validated['content'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $conversation = DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        return response()->json([
            'conversation' => $this->conversationWithMessages($conversation),
        ], 201);
    }

    public function show(Request $request, int $conversationId)
    {
        $conversation = DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        return response()->json([
            'conversation' => $this->conversationWithMessages($conversation),
        ]);
    }

    public function storeMessage(Request $request, int $conversationId)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);
        $userId = $request->user()->user_id;
        $conversation = DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }

        DB::table('messages')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'sender' => 'user',
            'content' => $validated['content'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->update(['updated_at' => now()]);

        $conversation = DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        return response()->json([
            'conversation' => $this->conversationWithMessages($conversation),
        ], 201);
    }

    private function conversationWithMessages(object $conversation): object
    {
        $conversation->messages = DB::table('messages')
            ->where('conversation_id', $conversation->conversation_id)
            ->where('user_id', $conversation->user_id)
            ->orderBy('message_id')
            ->get();

        return $conversation;
    }
}
