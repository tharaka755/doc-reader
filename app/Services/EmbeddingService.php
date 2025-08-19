<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    private Client $openai;
    private string $embeddingsFile = 'embeddings/document_embeddings.json';

    public function __construct()
    {
        $this->openai = (new Factory())
            ->withApiKey(config('openai.api_key'))
            ->make();
    }

    /**
     * Generate embeddings for document chunks
     */
    public function generateEmbeddings(array $chunks): array
    {
        $embeddings = [];
        
        foreach ($chunks as $chunk) {
            try {
                Log::info("Generating embedding for chunk {$chunk['id']}");
                
                $response = $this->openai->embeddings()->create([
                    'model' => 'text-embedding-3-small', // More cost-effective than ada-002
                    'input' => $chunk['content'],
                ]);

                $embeddings[] = [
                    'id' => $chunk['id'],
                    'content' => $chunk['content'],
                    'length' => $chunk['length'],
                    'embedding' => $response->embeddings[0]->embedding,
                    'created_at' => now()->toISOString()
                ];

                // Small delay to avoid rate limits
                usleep(100000); // 0.1 second

            } catch (\Exception $e) {
                Log::error("Error generating embedding for chunk {$chunk['id']}: " . $e->getMessage());
                throw $e;
            }
        }

        return $embeddings;
    }

    /**
     * Store embeddings to file
     */
    public function storeEmbeddings(array $embeddings): bool
    {
        try {
            // Ensure directory exists
            $directory = dirname($this->embeddingsFile);
            if (!Storage::disk('local')->exists($directory)) {
                Storage::disk('local')->makeDirectory($directory);
            }

            $data = [
                'embeddings' => $embeddings,
                'metadata' => [
                    'total_chunks' => count($embeddings),
                    'model' => 'text-embedding-3-small',
                    'created_at' => now()->toISOString(),
                    'document_hash' => md5(json_encode(array_column($embeddings, 'content')))
                ]
            ];

            Storage::disk('local')->put($this->embeddingsFile, json_encode($data, JSON_PRETTY_PRINT));
            return true;

        } catch (\Exception $e) {
            Log::error('Error storing embeddings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load embeddings from file
     */
    public function loadEmbeddings(): ?array
    {
        try {
            if (!Storage::disk('local')->exists($this->embeddingsFile)) {
                return null;
            }

            $content = Storage::disk('local')->get($this->embeddingsFile);
            $data = json_decode($content, true);

            return $data['embeddings'] ?? null;

        } catch (\Exception $e) {
            Log::error('Error loading embeddings: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if embeddings exist and are current
     */
    public function embeddingsExist(string $documentContent): bool
    {
        try {
            if (!Storage::disk('local')->exists($this->embeddingsFile)) {
                return false;
            }

            $content = Storage::disk('local')->get($this->embeddingsFile);
            $data = json_decode($content, true);

            if (!isset($data['metadata']['document_hash'])) {
                return false;
            }

            $currentHash = md5($documentContent);
            return $data['metadata']['document_hash'] === $currentHash;

        } catch (\Exception $e) {
            Log::error('Error checking embeddings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate embedding for a query
     */
    public function generateQueryEmbedding(string $query): ?array
    {
        try {
            $response = $this->openai->embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $query,
            ]);

            return $response->embeddings[0]->embedding;

        } catch (\Exception $e) {
            Log::error('Error generating query embedding: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosineSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            throw new \InvalidArgumentException('Vectors must have the same length');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Find most similar chunks to a query
     */
    public function findSimilarChunks(string $query, int $topK = 3): array
    {
        $embeddings = $this->loadEmbeddings();
        if (!$embeddings) {
            return [];
        }

        $queryEmbedding = $this->generateQueryEmbedding($query);
        if (!$queryEmbedding) {
            return [];
        }

        $similarities = [];
        foreach ($embeddings as $chunk) {
            $similarity = $this->cosineSimilarity($queryEmbedding, $chunk['embedding']);
            $similarities[] = [
                'id' => $chunk['id'],
                'content' => $chunk['content'],
                'similarity' => $similarity,
                'length' => $chunk['length']
            ];
        }

        // Sort by similarity (highest first)
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // Return top K results
        return array_slice($similarities, 0, $topK);
    }
}
