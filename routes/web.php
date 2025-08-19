<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/', function () {
    return view('welcome');
});

// Chat routes
Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::post('/chat', [ChatController::class, 'chat'])->name('chat.chat');
Route::get('/chat/summary', [ChatController::class, 'getDocumentSummary'])->name('chat.summary');

// File management routes
Route::get('/chat/files', [ChatController::class, 'getUploadedFiles'])->name('chat.files');
Route::delete('/chat/files/{file_id}', [ChatController::class, 'deleteFile'])->name('chat.deleteFile');
Route::delete('/chat/files', [ChatController::class, 'clearAllFiles'])->name('chat.clearAllFiles');
Route::get('/chat/files/{file_id}/download', [ChatController::class, 'downloadFile'])->name('chat.downloadFile');

// RAG debugging routes
Route::get('/chat/rag/status', [ChatController::class, 'getRAGStatus'])->name('chat.rag.status');
Route::post('/chat/rag/init', [ChatController::class, 'initializeRAG'])->name('chat.rag.init');
