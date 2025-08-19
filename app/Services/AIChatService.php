<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI\Factory;
use Illuminate\Support\Facades\Log;

class AIChatService
{
    private DocumentService $documentService;
    private RAGService $ragService;
    private Client $openai;
    private ?string $openaiFileId = null;
    private ?string $assistantId = null;

    public function __construct(DocumentService $documentService, RAGService $ragService)
    {
        $this->documentService = $documentService;
        $this->ragService = $ragService;
        
        // Create Guzzle client for OpenAI API
        $httpClient = new \GuzzleHttp\Client();
        
        $this->openai = (new Factory())
            ->withApiKey(config('openai.api_key'))
            ->withHttpClient($httpClient)
            ->make();
    }

    /**
     * Upload document to OpenAI and get file ID
     */
    private function uploadDocumentToOpenAI(): ?string
    {
        try {
            if ($this->openaiFileId) {
                return $this->openaiFileId;
            }

            if (!$this->documentService->documentExists()) {
                return null;
            }

            $fileContent = $this->documentService->getDocumentContent();
            
            // Create a temporary file for upload with .txt extension
            $tempFile = tempnam(sys_get_temp_dir(), 'doc_') . '.txt';
            file_put_contents($tempFile, $fileContent);
            
            // Upload file to OpenAI
            $response = $this->openai->files()->upload([
                'purpose' => 'assistants',
                'file' => fopen($tempFile, 'r'),
            ]);

            // Clean up temp file
            unlink($tempFile);

            if ($response && isset($response->id)) {
                $this->openaiFileId = $response->id;
                return $this->openaiFileId;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error uploading document to OpenAI: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get AI response using RAG (Retrieval Augmented Generation)
     */
    public function getResponse(string $question): string
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->documentService->documentExists()) {
                return 'Sorry, the document is not available at the moment.';
            }

            // Check if RAG system is ready - FAIL FAST if not ready
            $ragStatus = $this->ragService->getStatus();
            if (!$ragStatus['embeddings_ready']) {
                Log::warning('RAG system not ready for real-time queries');
                return 'Document is still being processed. Please run: php artisan embeddings:generate to prepare the document for questions.';
            }

            Log::info('CHAT_TIMING: RAG system ready, starting query', ['question_length' => strlen($question)]);

            // Query the RAG system for relevant content
            $ragStartTime = microtime(true);
            $ragResult = $this->ragService->query($question, 3, 0.1);
            $ragTime = microtime(true) - $ragStartTime;
            
            Log::info('CHAT_TIMING: RAG query completed', [
                'duration_ms' => round($ragTime * 1000, 2),
                'chunks_found' => count($ragResult['chunks'] ?? []),
                'status' => $ragResult['status']
            ]);
            
            if ($ragResult['status'] !== 'success') {
                return 'Sorry, I could not find relevant information in the document to answer your question.';
            }

            // Build prompt with retrieved context
            $systemMessage = "You are a helpful assistant. Answer the user's question based only on the provided relevant content from the document. " .
                           "If the answer is not in the provided content, say so clearly.\n\n" .
                           "Relevant content:\n" . $ragResult['context'];

            // Call OpenAI Chat Completions API
            Log::info('CHAT_TIMING: Sending request to OpenAI API');
            $apiStartTime = microtime(true);
            
            $response = $this->openai->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemMessage],
                    ['role' => 'user', 'content' => $question]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]);

            $apiTime = microtime(true) - $apiStartTime;
            $totalTime = microtime(true) - $startTime;

            Log::info('CHAT_TIMING: Complete chat response generated', [
                'api_duration_ms' => round($apiTime * 1000, 2),
                'total_duration_ms' => round($totalTime * 1000, 2),
                'rag_percentage' => round(($ragTime / $totalTime) * 100, 1)
            ]);

            if ($response && isset($response->choices) && !empty($response->choices)) {
                $choice = $response->choices[0];
                if (isset($choice->message) && isset($choice->message->content)) {
                    return $choice->message->content;
                }
            }

            return 'Sorry, I could not generate a response at the moment. Please try again.';

        } catch (\Exception $e) {
            $totalTime = microtime(true) - $startTime;
            Log::error('AI Chat Error: ' . $e->getMessage(), ['duration_ms' => round($totalTime * 1000, 2)]);
            return 'Sorry, there was an error processing your request. Please try again later.';
        }
    }

    /**
     * Get or create an assistant with file search capability
     */
    private function getOrCreateAssistant(string $fileId): ?string
    {
        try {
            if ($this->assistantId) {
                return $this->assistantId;
            }

            // Create a vector store for the file
            $vectorStore = $this->openai->vectorStores()->create([
                'name' => 'Document Vector Store - ' . date('Y-m-d H:i:s')
            ]);

            // Add file to vector store
            $this->openai->vectorStores()->files()->create($vectorStore->id, [
                'file_id' => $fileId
            ]);

            // Wait for vector store to be ready
            $this->waitForVectorStoreReady($vectorStore->id);

            // Create assistant with file search tool
            $assistant = $this->openai->assistants()->create([
                'name' => 'Document Assistant',
                'instructions' => 'You are a helpful assistant that answers questions based on the uploaded document. Always cite specific information from the document when answering questions. If the answer is not in the document, clearly state that.',
                'model' => 'gpt-4o-mini',
                'tools' => [
                    ['type' => 'file_search']
                ],
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [$vectorStore->id]
                    ]
                ]
            ]);

            if ($assistant && isset($assistant->id)) {
                $this->assistantId = $assistant->id;
                return $this->assistantId;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error creating assistant: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Wait for run completion
     */
    private function waitForRunCompletion(string $threadId, string $runId, int $maxWaitTime = 30): object
    {
        $startTime = time();
        
        while (time() - $startTime < $maxWaitTime) {
            $run = $this->openai->threads()->runs()->retrieve($threadId, $runId);
            
            if (in_array($run->status, ['completed', 'failed', 'cancelled', 'expired'])) {
                return $run;
            }
            
            sleep(1); // Wait 1 second before checking again
        }
        
        // Return the last status even if not completed
        return $this->openai->threads()->runs()->retrieve($threadId, $runId);
    }

    /**
     * Wait for vector store to be ready
     */
    private function waitForVectorStoreReady(string $vectorStoreId, int $maxWaitTime = 30): bool
    {
        $startTime = time();
        
        while (time() - $startTime < $maxWaitTime) {
            $vectorStore = $this->openai->vectorStores()->retrieve($vectorStoreId);
            
            if ($vectorStore->status === 'completed') {
                return true;
            }
            
            if (in_array($vectorStore->status, ['failed', 'cancelled'])) {
                return false;
            }
            
            sleep(1); // Wait 1 second before checking again
        }
        
        return false;
    }

    /**
     * Get document summary for display
     */
    public function getDocumentSummary(): array
    {
        return $this->documentService->getDocumentSummary();
    }

    /**
     * Get RAG system status
     */
    public function getRAGStatus(): array
    {
        return $this->ragService->getStatus();
    }

    /**
     * Initialize RAG system manually
     */
    public function initializeRAG(): array
    {
        return $this->ragService->initialize();
    }

    /**
     * Check if AI service is available
     */
    public function isAvailable(): bool
    {
        return !empty(config('openai.api_key'));
    }

    /**
     * Get OpenAI file ID (for debugging)
     */
    public function getOpenAIFileId(): ?string
    {
        return $this->openaiFileId;
    }

    /**
     * Get list of all uploaded files from OpenAI
     */
    public function getUploadedFiles(): array
    {
        try {
            $response = $this->openai->files()->list();
            $files = [];
            
            if ($response && isset($response->data)) {
                foreach ($response->data as $file) {
                    $files[] = [
                        'id' => $file->id,
                        'filename' => $file->filename ?? 'Unknown',
                        'purpose' => $file->purpose ?? 'Unknown',
                        'bytes' => $file->bytes ?? 0,
                        'created_at' => $file->created_at ?? null,
                        'status' => $file->status ?? 'Unknown'
                    ];
                }
            }
            
            return $files;
        } catch (\Exception $e) {
            Log::error('Error fetching uploaded files: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a specific file from OpenAI
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $response = $this->openai->files()->delete($fileId);
            return $response && isset($response->deleted) && $response->deleted === true;
        } catch (\Exception $e) {
            Log::error('Error deleting file ' . $fileId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all uploaded files from OpenAI
     */
    public function clearAllFiles(): array
    {
        try {
            $files = $this->getUploadedFiles();
            $deletedCount = 0;
            $errors = [];
            
            foreach ($files as $file) {
                if ($this->deleteFile($file['id'])) {
                    $deletedCount++;
                    // Reset our cached file ID if it was deleted
                    if ($this->openaiFileId === $file['id']) {
                        $this->openaiFileId = null;
                        $this->assistantId = null;
                    }
                } else {
                    $errors[] = $file['id'];
                }
            }
            
            // Also clean up assistants
            $this->cleanupAssistants();
            
            return [
                'deleted' => $deletedCount,
                'total' => count($files),
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Log::error('Error clearing all files: ' . $e->getMessage());
            return ['deleted' => 0, 'total' => 0, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Clean up old assistants
     */
    private function cleanupAssistants(): void
    {
        try {
            $assistants = $this->openai->assistants()->list();
            
            if ($assistants && isset($assistants->data)) {
                foreach ($assistants->data as $assistant) {
                    if (strpos($assistant->name, 'Document Assistant') !== false) {
                        $this->openai->assistants()->delete($assistant->id);
                    }
                }
            }
            
            $this->assistantId = null;
        } catch (\Exception $e) {
            Log::error('Error cleaning up assistants: ' . $e->getMessage());
        }
    }

    /**
     * Download a file from OpenAI storage
     */
    public function downloadFile(string $fileId): ?array
    {
        try {
            // Get the list of uploaded files to check if this file exists and get its details
            $uploadedFiles = $this->getUploadedFiles();
            $targetFile = null;
            
            // Find the file in our uploaded files list
            foreach ($uploadedFiles as $file) {
                if ($file['id'] === $fileId) {
                    $targetFile = $file;
                    break;
                }
            }
            
            if (!$targetFile) {
                return null;
            }
                
            // Check if this file is downloadable based on its properties
            if ($targetFile['status'] === 'processed' && $targetFile['purpose'] === 'assistants') {
                
                // Get the local document content (since OpenAI doesn't provide direct content download)
                $localContent = $this->documentService->getDocumentContent();
                
                return [
                    'content' => $localContent,
                    'filename' => $targetFile['filename'] ?? 'my-details.txt',
                    'mime_type' => 'text/plain'
                ];
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
