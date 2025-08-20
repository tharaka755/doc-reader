<?php

namespace App\Http\Controllers;

use App\Services\AssistantChatService;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class AssistantChatController extends Controller
{
    private AssistantChatService $assistantChatService;

    public function __construct(AssistantChatService $assistantChatService)
    {
        $this->assistantChatService = $assistantChatService;
    }

    /**
     * Show the assistant chat interface
     */
    public function index(): View
    {
        $documentSummary = $this->assistantChatService->getDocumentSummary();
        $isAIAvailable = $this->assistantChatService->isAvailable();
        $documentService = app(DocumentService::class);
        $status = $this->assistantChatService->getStatus();

        return view('assistant-chat.index', compact(
            'documentSummary', 
            'isAIAvailable', 
            'documentService', 
            'status'
        ));
    }

    /**
     * Handle chat message and return AI response using OpenAI Assistant API
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $message = $request->input('message');
        
        Log::info('Assistant API chat request received', [
            'message_length' => strlen($message)
        ]);

        $response = $this->assistantChatService->getResponse($message);

        return response()->json([
            'response' => $response,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get document summary for display
     */
    public function getDocumentSummary(): JsonResponse
    {
        $summary = $this->assistantChatService->getDocumentSummary();
        
        return response()->json([
            'summary' => $summary,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Clear conversation thread (start fresh conversation)
     */
    public function clearConversation(): JsonResponse
    {
        $success = $this->assistantChatService->clearConversation();
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Conversation cleared successfully' : 'Failed to clear conversation',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get Assistant API system status
     */
    public function getStatus(): JsonResponse
    {
        $status = $this->assistantChatService->getStatus();
        
        return response()->json([
            'status' => $status,
            'timestamp' => now()->toISOString()
        ]);
    }
}
