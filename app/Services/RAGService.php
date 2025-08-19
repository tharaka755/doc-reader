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
        $startTime = microtime(true);
        
        try {
            Log::info('RAG_TIMING: Starting RAG initialization', ['start_time' => $startTime]);
            
            $docCheckStartTime = microtime(true);
            if (!$this->documentService->documentExists()) {
                throw new \Exception('Document not found');
            }
            $docCheckTime = microtime(true) - $docCheckStartTime;
            Log::info('RAG_TIMING: Document existence check completed', ['duration_ms' => round($docCheckTime * 1000, 2)]);

            $contentStartTime = microtime(true);
            $documentContent = $this->documentService->getDocumentContent();
            $contentTime = microtime(true) - $contentStartTime;
            Log::info('RAG_TIMING: Document content retrieval completed', [
                'duration_ms' => round($contentTime * 1000, 2),
                'content_length' => strlen($documentContent)
            ]);
            
            // Check if embeddings already exist and are current
            $cacheCheckStartTime = microtime(true);
            if ($this->embeddingService->embeddingsExist($documentContent)) {
                $cacheCheckTime = microtime(true) - $cacheCheckStartTime;
                $totalTime = microtime(true) - $startTime;
                
                Log::info('RAG_TIMING: Using existing embeddings (cache hit)', [
                    'cache_check_duration_ms' => round($cacheCheckTime * 1000, 2),
                    'total_duration_ms' => round($totalTime * 1000, 2)
                ]);
                
                return [
                    'status' => 'success',
                    'message' => 'Using existing embeddings',
                    'embeddings_existed' => true,
                    'timing' => [
                        'total_ms' => round($totalTime * 1000, 2),
                        'cache_hit' => true
                    ]
                ];
            }
            $cacheCheckTime = microtime(true) - $cacheCheckStartTime;
            Log::info('RAG_TIMING: Cache check completed (cache miss)', ['duration_ms' => round($cacheCheckTime * 1000, 2)]);

            Log::info('RAG_TIMING: Starting new embedding generation pipeline');

            // Step 1: Chunk the document
            $chunkStartTime = microtime(true);
            $chunks = $this->chunkingService->chunkDocument($documentContent);
            $chunkTime = microtime(true) - $chunkStartTime;
            $stats = $this->chunkingService->getChunkingStats($chunks);
            
            Log::info('RAG_TIMING: Document chunking phase completed', [
                'duration_ms' => round($chunkTime * 1000, 2),
                'chunks_created' => count($chunks)
            ]);

            // Step 2: Generate embeddings
            $embeddingStartTime = microtime(true);
            $embeddings = $this->embeddingService->generateEmbeddings($chunks);
            $embeddingTime = microtime(true) - $embeddingStartTime;
            
            Log::info('RAG_TIMING: Embedding generation phase completed', [
                'duration_seconds' => round($embeddingTime, 2),
                'embeddings_created' => count($embeddings)
            ]);

            // Step 3: Store embeddings
            $storeStartTime = microtime(true);
            $stored = $this->embeddingService->storeEmbeddings($embeddings);
            $storeTime = microtime(true) - $storeStartTime;
            
            Log::info('RAG_TIMING: Embedding storage phase completed', ['duration_ms' => round($storeTime * 1000, 2)]);

            if (!$stored) {
                throw new \Exception('Failed to store embeddings');
            }

            $totalTime = microtime(true) - $startTime;
            Log::info('RAG_TIMING: Complete RAG initialization finished', [
                'total_duration_seconds' => round($totalTime, 2),
                'phases' => [
                    'document_retrieval_ms' => round($contentTime * 1000, 2),
                    'chunking_ms' => round($chunkTime * 1000, 2),
                    'embedding_generation_seconds' => round($embeddingTime, 2),
                    'storage_ms' => round($storeTime * 1000, 2)
                ]
            ]);

            return [
                'status' => 'success',
                'message' => 'RAG system initialized successfully',
                'stats' => $stats,
                'embeddings_count' => count($embeddings),
                'embeddings_existed' => false,
                'timing' => [
                    'total_seconds' => round($totalTime, 2),
                    'embedding_generation_seconds' => round($embeddingTime, 2),
                    'percentage_embedding' => round(($embeddingTime / $totalTime) * 100, 1)
                ]
            ];

        } catch (\Exception $e) {
            $totalTime = microtime(true) - $startTime;
            Log::error('RAG initialization failed: ' . $e->getMessage(), ['duration_seconds' => round($totalTime, 2)]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timing' => ['total_seconds' => round($totalTime, 2)]
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
                    'embeddings_ready' => false,
                    'message' => 'RAG system not initialized'
                ];
            }

            return [
                'initialized' => true,
                'embeddings_ready' => true,
                'chunk_count' => count($embeddings),
                'message' => 'RAG system ready',
                'last_updated' => $embeddings[0]['created_at'] ?? 'Unknown'
            ];

        } catch (\Exception $e) {
            return [
                'initialized' => false,
                'embeddings_ready' => false,
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
