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
        // Clean and normalize the content
        $content = $this->normalizeContent($content);
        
        // Split by sentences first to avoid breaking sentences
        $sentences = $this->splitIntoSentences($content);
        
        $chunks = [];
        $currentChunk = '';
        $currentLength = 0;
        
        foreach ($sentences as $sentence) {
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
