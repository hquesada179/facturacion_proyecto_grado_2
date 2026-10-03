<?php

namespace App\Http\Controllers;

use App\Models\AssistantMessage;
use App\Services\Assistant\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssistantController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('use-assistant');

        return response()->view('assistant.index');
    }

    public function message(Request $request, AiAssistantService $assistant): JsonResponse
    {
        Gate::authorize('use-assistant');

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1000'],
            'screen' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9_.:-]+$/'],
            'resource_type' => ['nullable', Rule::in(['invoice', 'credit_note', 'customer', 'product'])],
            'resource_id' => ['nullable', 'integer', 'min:1'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($assistant->handle($request->user(), $validated));
    }

    public function confirm(Request $request, AiAssistantService $assistant): JsonResponse
    {
        Gate::authorize('use-assistant');

        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32', 'max:120'],
        ]);

        return response()->json($assistant->confirm($request->user(), $validated['token']));
    }

    public function feedback(Request $request, AssistantMessage $assistantMessage): JsonResponse
    {
        Gate::authorize('use-assistant');

        $validated = $request->validate([
            'feedback' => ['required', Rule::in(['useful', 'not_useful'])],
        ]);

        $assistantMessage->loadMissing('conversation');

        abort_unless(
            $assistantMessage->role === 'assistant'
                && $assistantMessage->conversation
                && $assistantMessage->conversation->company_id === $request->user()->company_id
                && $assistantMessage->conversation->user_id === $request->user()->id,
            403
        );

        $assistantMessage->forceFill([
            'feedback' => $validated['feedback'],
            'feedback_at' => now(),
        ])->save();

        return response()->json(['status' => 'ok']);
    }
}
