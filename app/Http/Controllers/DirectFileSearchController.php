<?php

namespace App\Http\Controllers;

use App\Services\DirectFileSearchService;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class DirectFileSearchController extends Controller
{
    private DirectFileSearchService $directFileSearchService;

    public function __construct(DirectFileSearchService $directFileSearchService)
    {
        $this->directFileSearchService = $directFileSearchService;
    }

    /**
     * Show the direct file search interface
     */
    public function index(): View
    {
        try {
            $documentSummary = $this->directFileSearchService->getDocumentSummary();
            $isAIAvailable = $this->directFileSearchService->isAvailable();
            $documentService = app(DocumentService::class);
            $status = $this->directFileSearchService->getStatus();
            
            // Debug logging
            Log::info('DirectFileSearchController index method', [
                'documentSummary' => $documentSummary,
                'isAIAvailable' => $isAIAvailable,
                'status' => $status,
                'service_class' => get_class($this->directFileSearchService)
            ]);

            return view('direct-file-search.index', compact(
                'documentSummary', 
                'isAIAvailable', 
                'documentService', 
                'status'
            ));
        } catch (\Exception $e) {
            Log::error('Error in DirectFileSearchController index method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return with safe defaults
            return view('direct-file-search.index', [
                'documentSummary' => [],
                'isAIAvailable' => false,
                'documentService' => app(DocumentService::class),
                'status' => [
                    'available' => false,
                    'document_exists' => false,
                    'vector_store_id' => null
                ]
            ]);
        }
    }

    /**
     * Handle chat message and return AI response using Direct File Search
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $message = $request->input('message');
        
        Log::info('Direct File Search chat request received', [
            'message_length' => strlen($message)
        ]);

        $response = $this->directFileSearchService->getResponse($message);

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
        $summary = $this->directFileSearchService->getDocumentSummary();
        
        return response()->json([
            'summary' => $summary,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Clear cache
     */
    public function clearCache(): JsonResponse
    {
        $success = $this->directFileSearchService->clearCache();
        
        return response()->json([
            'success' => $success,
            'message' => $success ? 'Cache cleared successfully' : 'Failed to clear cache',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Get Direct File Search system status
     */
    public function getStatus(): JsonResponse
    {
        $status = $this->directFileSearchService->getStatus();
        
        return response()->json([
            'status' => $status,
            'timestamp' => now()->toISOString()
        ]);
    }
}
