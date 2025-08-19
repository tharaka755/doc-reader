<?php

namespace App\Services;

class ChunkingService
{
    private int $chunkSize;
    private int $chunkOverlap;

    public function __construct(int $chunkSize = 500, int $chunkOverlap = 50)
    {
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
    }

    /**
     * Split document content into overlapping chunks
     */
    public function chunkDocument(string $content): array
    {
        $startTime = microtime(true);
        \Illuminate\Support\Facades\Log::info("CHUNKING_TIMING: Starting document chunking", [
            'content_length' => strlen($content),
            'chunk_size' => $this->chunkSize,
            'chunk_overlap' => $this->chunkOverlap
        ]);
        
        // Clean and normalize the content
        $normalizeStartTime = microtime(true);
        $content = $this->normalizeContent($content);
        $normalizeTime = microtime(true) - $normalizeStartTime;
        \Illuminate\Support\Facades\Log::info("CHUNKING_TIMING: Content normalization completed", [
            'duration_ms' => round($normalizeTime * 1000, 2),
            'normalized_length' => strlen($content)
        ]);
        
        // Split by sentences first to avoid breaking sentences
        $sentenceStartTime = microtime(true);
        $sentences = $this->splitIntoSentences($content);
        $sentenceTime = microtime(true) - $sentenceStartTime;
        \Illuminate\Support\Facades\Log::info("CHUNKING_TIMING: Sentence splitting completed", [
            'duration_ms' => round($sentenceTime * 1000, 2),
            'sentence_count' => count($sentences)
        ]);
        
        $processStartTime = microtime(true);
        $chunks = [];
        $currentChunk = '';
        $currentLength = 0;
        
        foreach ($sentences as $index => $sentence) {
            $sentenceLength = strlen($sentence);
            
            // If adding this sentence would exceed chunk size
            if ($currentLength + $sentenceLength > $this->chunkSize && !empty($currentChunk)) {
                // Save current chunk
                $chunks[] = [
                    'content' => trim($currentChunk),
                    'length' => $currentLength,
                    'id' => count($chunks)
                ];
                
                // Start new chunk with overlap
                $currentChunk = $this->getOverlapText($currentChunk) . ' ' . $sentence;
                $currentLength = strlen($currentChunk);
            } else {
                // Add sentence to current chunk
                $currentChunk .= ($currentChunk ? ' ' : '') . $sentence;
                $currentLength += $sentenceLength + ($currentChunk ? 1 : 0); // +1 for space
            }
        }
        
        // Add the last chunk if it has content
        if (!empty(trim($currentChunk))) {
            $chunks[] = [
                'content' => trim($currentChunk),
                'length' => strlen(trim($currentChunk)),
                'id' => count($chunks)
            ];
        }
        
        $processTime = microtime(true) - $processStartTime;
        $totalTime = microtime(true) - $startTime;
        
        \Illuminate\Support\Facades\Log::info("CHUNKING_TIMING: Chunk processing completed", [
            'duration_ms' => round($processTime * 1000, 2),
            'chunks_created' => count($chunks)
        ]);
        
        \Illuminate\Support\Facades\Log::info("CHUNKING_TIMING: Total chunking completed", [
            'total_duration_ms' => round($totalTime * 1000, 2),
            'final_chunk_count' => count($chunks),
            'average_chunk_size' => count($chunks) > 0 ? round(array_sum(array_column($chunks, 'length')) / count($chunks)) : 0
        ]);
        
        return $chunks;
    }

    /**
     * Normalize content for better chunking
     */
    private function normalizeContent(string $content): string
    {
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Ensure proper sentence endings
        $content = preg_replace('/([.!?])\s*/', '$1 ', $content);
        
        return trim($content);
    }

    /**
     * Split content into sentences
     */
    private function splitIntoSentences(string $content): array
    {
        // Split on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        // Handle edge cases where content doesn't end with punctuation
        if (empty($sentences)) {
            return [$content];
        }
        
        return $sentences;
    }

    /**
     * Get overlap text from the end of current chunk
     */
    private function getOverlapText(string $chunk): string
    {
        if (strlen($chunk) <= $this->chunkOverlap) {
            return $chunk;
        }
        
        // Get last N characters, but try to break at word boundary
        $overlap = substr($chunk, -$this->chunkOverlap);
        
        // Find the first space to avoid breaking words
        $spacePos = strpos($overlap, ' ');
        if ($spacePos !== false) {
            $overlap = substr($overlap, $spacePos + 1);
        }
        
        return $overlap;
    }

    /**
     * Get chunk statistics
     */
    public function getChunkingStats(array $chunks): array
    {
        $lengths = array_column($chunks, 'length');
        
        return [
            'total_chunks' => count($chunks),
            'avg_length' => count($lengths) > 0 ? round(array_sum($lengths) / count($lengths)) : 0,
            'min_length' => count($lengths) > 0 ? min($lengths) : 0,
            'max_length' => count($lengths) > 0 ? max($lengths) : 0,
            'total_length' => array_sum($lengths)
        ];
    }
}
