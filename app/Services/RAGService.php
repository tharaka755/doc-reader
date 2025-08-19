<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RAGService
{
    private DocumentService $documentService;
    private ChunkingService $chunkingService;
    private EmbeddingService $embeddingService;

    public function __construct(
        DocumentService $documentService,
        ChunkingService $chunkingService,
        EmbeddingService $embeddingService
    ) {
        $this->documentService = $documentService;
        $this->chunkingService = $chunkingService;
        $this->embeddingService = $embeddingService;
    }

    /**
     * Initialize RAG system by processing document and generating embeddings
     */
    public function initialize(): array
    {
        try {
            if (!$this->documentService->documentExists()) {
                throw new \Exception('Document not found');
            }

            $documentContent = $this->documentService->getDocumentContent();
            
            // Check if embeddings already exist and are current
            if ($this->embeddingService->embeddingsExist($documentContent)) {
                Log::info('Using existing embeddings');
                return [
                    'status' => 'success',
                    'message' => 'Using existing embeddings',
                    'embeddings_existed' => true
                ];
            }

            Log::info('Generating new embeddings for document');

            // Step 1: Chunk the document
            $chunks = $this->chunkingService->chunkDocument($documentContent);
            $stats = $this->chunkingService->getChunkingStats($chunks);
            
            Log::info('Document chunked', $stats);

            // Step 2: Generate embeddings
            $embeddings = $this->embeddingService->generateEmbeddings($chunks);

            // Step 3: Store embeddings
            $stored = $this->embeddingService->storeEmbeddings($embeddings);

            if (!$stored) {
                throw new \Exception('Failed to store embeddings');
            }

            return [
                'status' => 'success',
                'message' => 'RAG system initialized successfully',
                'stats' => $stats,
                'embeddings_count' => count($embeddings),
                'embeddings_existed' => false
            ];

        } catch (\Exception $e) {
            Log::error('RAG initialization failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Query the RAG system
     */
    public function query(string $question, int $topK = 3, float $similarityThreshold = 0.1): array
    {
        try {
            // Find most relevant chunks
            $relevantChunks = $this->embeddingService->findSimilarChunks($question, $topK);

            if (empty($relevantChunks)) {
                return [
                    'status' => 'error',
                    'message' => 'No relevant content found'
                ];
            }

            // Filter by similarity threshold
            $relevantChunks = array_filter($relevantChunks, function($chunk) use ($similarityThreshold) {
                return $chunk['similarity'] >= $similarityThreshold;
            });

            if (empty($relevantChunks)) {
                return [
                    'status' => 'error',
                    'message' => 'No sufficiently relevant content found'
                ];
            }

            // Build context from relevant chunks
            $context = $this->buildContext($relevantChunks);

            return [
                'status' => 'success',
                'context' => $context,
                'relevant_chunks' => $relevantChunks,
                'chunk_count' => count($relevantChunks)
            ];

        } catch (\Exception $e) {
            Log::error('RAG query failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to process query: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build context string from relevant chunks
     */
    private function buildContext(array $chunks): string
    {
        $contextParts = [];
        
        foreach ($chunks as $chunk) {
            $similarity = round($chunk['similarity'] * 100, 1);
            $contextParts[] = "Relevant content (similarity: {$similarity}%):\n" . $chunk['content'];
        }

        return implode("\n\n", $contextParts);
    }

    /**
     * Get RAG system status
     */
    public function getStatus(): array
    {
        try {
            $embeddings = $this->embeddingService->loadEmbeddings();
            
            if (!$embeddings) {
                return [
                    'initialized' => false,
                    'message' => 'RAG system not initialized'
                ];
            }

            return [
                'initialized' => true,
                'chunk_count' => count($embeddings),
                'message' => 'RAG system ready',
                'last_updated' => $embeddings[0]['created_at'] ?? 'Unknown'
            ];

        } catch (\Exception $e) {
            return [
                'initialized' => false,
                'message' => 'Error checking status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Clear embeddings and reset system
     */
    public function reset(): bool
    {
        try {
            $embeddingService = $this->embeddingService;
            $reflection = new \ReflectionClass($embeddingService);
            $property = $reflection->getProperty('embeddingsFile');
            $property->setAccessible(true);
            $embeddingsFile = $property->getValue($embeddingService);

            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($embeddingsFile)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($embeddingsFile);
            }

            Log::info('RAG system reset successfully');
            return true;

        } catch (\Exception $e) {
            Log::error('RAG reset failed: ' . $e->getMessage());
            return false;
        }
    }
}
