<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DirectFileSearchController;
use App\Http\Controllers\AssistantChatController;

Route::get('/', function () {
    return view('welcome');
});

// RAG Chat routes (existing implementation with embeddings and vector search)
Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::post('/chat', [ChatController::class, 'chat'])->name('chat.chat');
Route::get('/chat/summary', [ChatController::class, 'getDocumentSummary'])->name('chat.summary');

// Direct File Search routes (using OpenAI Responses API with file search tool)
Route::get('/direct-file-search', [DirectFileSearchController::class, 'index'])->name('direct-file-search.index');
Route::post('/direct-file-search', [DirectFileSearchController::class, 'chat'])->name('direct-file-search.chat');
Route::get('/direct-file-search/summary', [DirectFileSearchController::class, 'getDocumentSummary'])->name('direct-file-search.summary');
Route::post('/direct-file-search/clear', [DirectFileSearchController::class, 'clearCache'])->name('direct-file-search.clear');
Route::get('/direct-file-search/status', [DirectFileSearchController::class, 'getStatus'])->name('direct-file-search.status');

// Test route for debugging DirectFileSearchService
Route::get('/direct-file-search/test', function() {
    try {
        $service = app(\App\Services\DirectFileSearchService::class);
        $status = $service->getStatus();
        $isAvailable = $service->isAvailable();
        $documentExists = $service->getDocumentSummary();
        
        return response()->json([
            'success' => true,
            'service_class' => get_class($service),
            'status' => $status,
            'is_available' => $isAvailable,
            'document_summary' => $documentExists
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

// Assistant Chat routes (using OpenAI Assistant API with file search)
Route::get('/assistant-chat', [AssistantChatController::class, 'index'])->name('assistant-chat.index');
Route::post('/assistant-chat', [AssistantChatController::class, 'chat'])->name('assistant-chat.chat');
Route::get('/assistant-chat/summary', [AssistantChatController::class, 'getDocumentSummary'])->name('assistant-chat.summary');
Route::post('/assistant-chat/clear', [AssistantChatController::class, 'clearConversation'])->name('assistant-chat.clear');
Route::get('/assistant-chat/status', [AssistantChatController::class, 'getStatus'])->name('assistant-chat.status');

// File management routes
Route::get('/chat/files', [ChatController::class, 'getUploadedFiles'])->name('chat.files');
Route::delete('/chat/files/{file_id}', [ChatController::class, 'deleteFile'])->name('chat.deleteFile');
Route::delete('/chat/files', [ChatController::class, 'clearAllFiles'])->name('chat.clearAllFiles');
Route::get('/chat/files/{file_id}/download', [ChatController::class, 'downloadFile'])->name('chat.downloadFile');

// RAG debugging routes
Route::get('/chat/rag/status', [ChatController::class, 'getRAGStatus'])->name('chat.rag.status');
Route::post('/chat/rag/init', [ChatController::class, 'initializeRAG'])->name('chat.rag.init');
