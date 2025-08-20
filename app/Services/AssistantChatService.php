<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI\Factory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AssistantChatService
{
    private DocumentService $documentService;
    private Client $openai;
    private ?string $assistantId = null;
    private ?string $threadId = null;
    private ?string $vectorStoreId = null;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
        
        // Create Guzzle client for OpenAI API with required headers
        $httpClient = new \GuzzleHttp\Client([
            'headers' => [
                'OpenAI-Beta' => 'assistants=v2'
            ]
        ]);
        
        $this->openai = (new Factory())
            ->withApiKey(config('openai.api_key'))
            ->withHttpClient($httpClient)
            ->make();
    }

    /**
     * Get AI response using OpenAI Assistant API with file search
     */
    public function getResponse(string $question): string
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->documentService->documentExists()) {
                return 'Sorry, the document is not available at the moment.';
            }

            // Initialize assistant if not already done
            $assistantId = $this->getOrCreateAssistant();
            if (!$assistantId) {
                return 'Sorry, could not initialize the assistant. Please try again.';
            }

            // Get or create thread
            $threadId = $this->getOrCreateThread();
            if (!$threadId) {
                return 'Sorry, could not create conversation thread. Please try again.';
            }

            Log::info('ASSISTANT_TIMING: Starting assistant query', [
                'question_length' => strlen($question),
                'assistant_id' => $assistantId,
                'thread_id' => $threadId
            ]);

            // Add message to thread
            $message = $this->openai->threads()->messages()->create($threadId, [
                'role' => 'user',
                'content' => $question
            ]);

            // Create and run the assistant
            $run = $this->openai->threads()->runs()->create($threadId, [
                'assistant_id' => $assistantId
            ]);

            // Wait for completion
            $completedRun = $this->waitForRunCompletion($threadId, $run->id);
            
            if ($completedRun->status !== 'completed') {
                Log::error('Assistant run failed', [
                    'status' => $completedRun->status,
                    'run_id' => $run->id
                ]);
                return 'Sorry, I encountered an issue processing your request. Please try again.';
            }

            // Get the assistant's response
            $messages = $this->openai->threads()->messages()->list($threadId);

            logger([
                'assistant approach response' => json_encode($messages, JSON_PRETTY_PRINT),
            ]);
            
            if ($messages && isset($messages->data) && count($messages->data) > 0) {
                $latestMessage = $messages->data[0]; // Most recent message
                
                if ($latestMessage->role === 'assistant' && isset($latestMessage->content)) {
                    $content = $latestMessage->content;
                    
                    // Extract text content
                    if (is_array($content) && count($content) > 0) {
                        $textContent = '';
                        foreach ($content as $contentPart) {
                            if (isset($contentPart->type) && $contentPart->type === 'text') {
                                $textContent .= $contentPart->text->value ?? '';
                            }
                        }
                        
                        $totalTime = microtime(true) - $startTime;
                        Log::info('ASSISTANT_TIMING: Response generated successfully', [
                            'total_duration_ms' => round($totalTime * 1000, 2)
                        ]);
                        
                        return !empty($textContent) ? $textContent : 'I received your question but could not generate a proper response.';
                    }
                }
            }

            return 'Sorry, I could not generate a response. Please try again.';

        } catch (\Exception $e) {
            $totalTime = microtime(true) - $startTime;
            Log::error('Assistant Chat Error: ' . $e->getMessage(), [
                'duration_ms' => round($totalTime * 1000, 2)
            ]);
            return 'Sorry, there was an error processing your request. Please try again later.';
        }
    }

    /**
     * Get or create an assistant with file search capability
     */
    private function getOrCreateAssistant(): ?string
    {
        try {
            // Check cache first
            $cachedAssistantId = Cache::get('assistant_chat_assistant_id');
            if ($cachedAssistantId) {
                $this->assistantId = $cachedAssistantId;
                return $this->assistantId;
            }

            // Get or create vector store
            $vectorStoreId = $this->getOrCreateVectorStore();
            if (!$vectorStoreId) {
                return null;
            }

            // Create assistant with file search tool
            $assistant = $this->openai->assistants()->create([
                'name' => 'Document Search Assistant',
                'instructions' => 'You are a helpful assistant that answers questions based on the uploaded document using file search. Always cite specific information from the document when answering questions. If the answer is not clearly found in the document, state that clearly. Provide detailed and accurate responses based on the document content.',
                'model' => 'gpt-4o-mini',
                'tools' => [
                    ['type' => 'file_search']
                ],
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [$vectorStoreId]
                    ]
                ]
            ]);

            if ($assistant && isset($assistant->id)) {
                $this->assistantId = $assistant->id;
                // Cache for 1 hour
                Cache::put('assistant_chat_assistant_id', $this->assistantId, 3600);
                Log::info('Created new assistant', ['assistant_id' => $this->assistantId]);
                return $this->assistantId;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error creating assistant: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get or create vector store with document
     */
    private function getOrCreateVectorStore(): ?string
    {
        try {
            // Check cache first
            $cachedVectorStoreId = Cache::get('assistant_chat_vector_store_id');
            if ($cachedVectorStoreId) {
                $this->vectorStoreId = $cachedVectorStoreId;
                return $this->vectorStoreId;
            }

            // Upload document to OpenAI
            $fileId = $this->uploadDocumentToOpenAI();
            if (!$fileId) {
                return null;
            }

            // Create vector store
            $vectorStore = $this->openai->vectorStores()->create([
                'name' => 'Assistant Document Store - ' . date('Y-m-d H:i:s')
            ]);

            // Add file to vector store
            $this->openai->vectorStores()->files()->create($vectorStore->id, [
                'file_id' => $fileId
            ]);

            // Wait for vector store to be ready
            if (!$this->waitForVectorStoreReady($vectorStore->id)) {
                Log::error('Vector store failed to initialize', ['vector_store_id' => $vectorStore->id]);
                return null;
            }

            $this->vectorStoreId = $vectorStore->id;
            // Cache for 1 hour
            Cache::put('assistant_chat_vector_store_id', $this->vectorStoreId, 3600);
            Log::info('Created vector store', ['vector_store_id' => $this->vectorStoreId]);
            
            return $this->vectorStoreId;

        } catch (\Exception $e) {
            Log::error('Error creating vector store: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload document to OpenAI for file search
     */
    private function uploadDocumentToOpenAI(): ?string
    {
        try {
            $documentPath = $this->documentService->getDocumentPath();
            if (!$documentPath || !$this->documentService->documentExists()) {
                Log::error('Document file not found', ['path' => $documentPath]);
                return null;
            }

            // Get the full storage path
            $fullPath = storage_path('app/' . $documentPath);
            if (!file_exists($fullPath)) {
                Log::error('Document file not found at full path', ['path' => $fullPath]);
                return null;
            }

            $file = $this->openai->files()->upload([
                'file' => fopen($fullPath, 'r'),
                'purpose' => 'assistants'
            ]);

            if ($file && isset($file->id)) {
                Log::info('Uploaded document to OpenAI', ['file_id' => $file->id]);
                return $file->id;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error uploading document: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get or create conversation thread
     */
    private function getOrCreateThread(): ?string
    {
        try {
            if ($this->threadId) {
                return $this->threadId;
            }

            $thread = $this->openai->threads()->create([]);
            
            if ($thread && isset($thread->id)) {
                $this->threadId = $thread->id;
                return $this->threadId;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error creating thread: ' . $e->getMessage());
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
    private function waitForVectorStoreReady(string $vectorStoreId, int $maxWaitTime = 60): bool
    {
        $startTime = time();
        
        while (time() - $startTime < $maxWaitTime) {
            $vectorStore = $this->openai->vectorStores()->retrieve($vectorStoreId);
            
            if ($vectorStore->status === 'completed') {
                return true;
            }
            
            if (in_array($vectorStore->status, ['failed', 'cancelled'])) {
                Log::error('Vector store failed', ['status' => $vectorStore->status]);
                return false;
            }
            
            sleep(2); // Wait 2 seconds before checking again
        }
        
        Log::warning('Vector store timed out', ['vector_store_id' => $vectorStoreId]);
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
     * Check if AI service is available
     */
    public function isAvailable(): bool
    {
        return !empty(config('openai.api_key'));
    }

    /**
     * Clear conversation thread (start fresh conversation)
     */
    public function clearConversation(): bool
    {
        try {
            $this->threadId = null;
            return true;
        } catch (\Exception $e) {
            Log::error('Error clearing conversation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get conversation status
     */
    public function getStatus(): array
    {
        return [
            'available' => $this->isAvailable(),
            'document_exists' => $this->documentService->documentExists(),
            'assistant_id' => $this->assistantId,
            'thread_id' => $this->threadId,
            'vector_store_id' => $this->vectorStoreId
        ];
    }
}
