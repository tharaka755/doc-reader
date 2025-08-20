<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI\Factory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DirectFileSearchService
{
    private DocumentService $documentService;
    private Client $openai;
    private ?string $vectorStoreId = null;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
        
        // Create OpenAI client
        $this->openai = (new Factory())
            ->withApiKey(config('openai.api_key'))
            ->make();
    }

    /**
     * Get AI response using direct file search (no assistants)
     */
    public function getResponse(string $question): string
    {
        $startTime = microtime(true);
        
        try {
            if (!$this->documentService->documentExists()) {
                return 'Sorry, the document is not available at the moment.';
            }

            // Get or create vector store
            $vectorStoreId = $this->getOrCreateVectorStore();
            if (!$vectorStoreId) {
                return 'Sorry, could not initialize the document search. Please try again.';
            }

            $question = $question."; Make sure to include the citation in your response.";

            // Use responses API with file search tool
            $response = $this->openai->responses()->create([
                'model' => 'gpt-4o-mini',
                'input' => $question,
                'tools' => [
                    [
                        'type' => 'file_search',
                        'vector_store_ids' => [$vectorStoreId]
                    ]
                ]
            ]);

            $totalTime = microtime(true) - $startTime;

            logger(json_encode($response, JSON_PRETTY_PRINT));

            // Handle Responses API response structure
            if ($response && isset($response->output)) {
                // The output is a JSON string, decode it first
                if (is_string($response->output)) {
                    $decodedOutput = json_decode($response->output, true);
                    if ($decodedOutput && is_array($decodedOutput)) {
                        // Look for message objects in the decoded array
                        foreach ($decodedOutput as $outputItem) {
                            if (isset($outputItem['type']) && $outputItem['type'] === 'message') {
                                if (isset($outputItem['content']) && is_array($outputItem['content'])) {
                                    // Extract text from content array
                                    $textResponse = '';
                                    foreach ($outputItem['content'] as $contentItem) {
                                        if (isset($contentItem['type']) && $contentItem['type'] === 'output_text') {
                                            $textResponse .= $contentItem['text'] ?? '';
                                        }
                                    }
                                    return !empty($textResponse) ? $textResponse : 'I processed your request but could not extract text from the response.';
                                }
                            }
                        }
                    }
                } else if (is_array($response->output)) {
                    // Handle case where output is already an array
                    foreach ($response->output as $outputItem) {
                        if (isset($outputItem->type) && $outputItem->type === 'message') {
                            if (isset($outputItem->content) && is_array($outputItem->content)) {
                                $textResponse = '';
                                foreach ($outputItem->content as $contentItem) {
                                    if (isset($contentItem->type) && $contentItem->type === 'output_text') {
                                        $textResponse .= $contentItem->text ?? '';
                                    }

                                }
                                return !empty($textResponse) ? $textResponse : 'I processed your request but could not extract text from the response.';
                            }
                        }
                    }
                }

            }

            return 'Sorry, I could not generate a response. Please try again.';

        } catch (\Exception $e) {
            $totalTime = microtime(true) - $startTime;
            Log::error('Direct File Search Error: ' . $e->getMessage(), [
                'duration_ms' => round($totalTime * 1000, 2)
            ]);
            return 'Sorry, there was an error processing your request. Please try again later.';
        }
    }

    /**
     * Get or create vector store with document
     */
    private function getOrCreateVectorStore(): ?string
    {
        try {
            // Check cache first
            $cachedVectorStoreId = Cache::get('direct_file_search_vector_store_id');
            if ($cachedVectorStoreId) {
                $this->vectorStoreId = $cachedVectorStoreId;
                return $this->vectorStoreId;
            }

            // Upload document to OpenAI
            $fileId = $this->uploadDocumentToOpenAI();
            if (!$fileId) {
                return null;
            }

            // Create vector store with file
            $vectorStore = $this->openai->vectorStores()->create([
                'name' => 'Direct File Search Store - ' . date('Y-m-d H:i:s'),
                'file_ids' => [$fileId]
            ]);

            // Wait for vector store to be ready
            if (!$this->waitForVectorStoreReady($vectorStore->id)) {
                Log::error('Vector store failed to initialize', ['vector_store_id' => $vectorStore->id]);
                return null;
            }

            $this->vectorStoreId = $vectorStore->id;
            // Cache for 1 hour
            Cache::put('direct_file_search_vector_store_id', $this->vectorStoreId, 3600);
            Log::info('Created vector store for direct file search', ['vector_store_id' => $this->vectorStoreId]);
            
            return $this->vectorStoreId;

        } catch (\Exception $e) {
            Log::error('Error creating vector store for direct file search: ' . $e->getMessage());
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
                Log::info('Uploaded document to OpenAI for direct file search', ['file_id' => $file->id]);
                return $file->id;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error uploading document for direct file search: ' . $e->getMessage());
            return null;
        }
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
     * Get service status
     */
    public function getStatus(): array
    {
        return [
            'available' => $this->isAvailable(),
            'document_exists' => $this->documentService->documentExists(),
            'vector_store_id' => $this->vectorStoreId
        ];
    }

    /**
     * Clear cache
     */
    public function clearCache(): bool
    {
        try {
            Cache::forget('direct_file_search_vector_store_id');
            $this->vectorStoreId = null;
            return true;
        } catch (\Exception $e) {
            Log::error('Error clearing direct file search cache: ' . $e->getMessage());
            return false;
        }
    }
}
