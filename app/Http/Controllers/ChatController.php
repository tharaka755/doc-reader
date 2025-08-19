<?php

namespace App\Http\Controllers;

use App\Services\AIChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private AIChatService $aiChatService;

    public function __construct(AIChatService $aiChatService)
    {
        $this->aiChatService = $aiChatService;
    }

    /**
     * Show the chat interface
     */
    public function index(): View
    {
        $documentSummary = $this->aiChatService->getDocumentSummary();
        $isAIAvailable = $this->aiChatService->isAvailable();
        $documentService = app(DocumentService::class);
        $aiChatService = $this->aiChatService;

        return view('chat.index', compact('documentSummary', 'isAIAvailable', 'documentService', 'aiChatService'));
    }

    /**
     * Handle chat message and return AI response
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $message = $request->input('message');
        $response = $this->aiChatService->getResponse($message);

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
        $summary = $this->aiChatService->getDocumentSummary();
        
        return response()->json([
            'summary' => $summary,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get list of uploaded files from OpenAI
     */
    public function getUploadedFiles(): JsonResponse
    {
        $files = $this->aiChatService->getUploadedFiles();
        
        return response()->json([
            'files' => $files,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Delete a specific file from OpenAI
     */
    public function deleteFile(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'required|string'
        ]);

        $fileId = $request->input('file_id');
        $success = $this->aiChatService->deleteFile($fileId);
        
        return response()->json([
            'success' => $success,
            'file_id' => $fileId,
            'message' => $success ? 'File deleted successfully' : 'Failed to delete file',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Clear all uploaded files from OpenAI
     */
    public function clearAllFiles(): JsonResponse
    {
        $result = $this->aiChatService->clearAllFiles();
        
        return response()->json([
            'result' => $result,
            'message' => "Deleted {$result['deleted']} out of {$result['total']} files",
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Download a file from OpenAI storage
     */
    public function downloadFile(string $fileId): Response
    {
        try {
            $fileData = $this->aiChatService->downloadFile($fileId);
            
            if (!$fileData) {
                abort(404, 'File not found or could not be downloaded');
            }
            
            return response($fileData['content'])
                ->header('Content-Type', $fileData['mime_type'])
                ->header('Content-Disposition', 'attachment; filename="' . $fileData['filename'] . '"');
        } catch (\Exception $e) {
            abort(500, 'Error downloading file: ' . $e->getMessage());
        }
    }

    /**
     * Get RAG system status
     */
    public function getRAGStatus(): JsonResponse
    {
        $status = $this->aiChatService->getRAGStatus();
        
        return response()->json([
            'status' => $status,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Initialize RAG system
     */
    public function initializeRAG(): JsonResponse
    {
        $result = $this->aiChatService->initializeRAG();
        
        return response()->json([
            'result' => $result,
            'timestamp' => now()->toISOString()
        ]);
    }
}
