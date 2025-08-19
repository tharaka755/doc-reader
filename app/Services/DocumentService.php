<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DocumentService
{
    private string $documentPath = 'my-details.txt';
    private ?string $documentContent = null;

    /**
     * Get the document content
     */
    public function getDocumentContent(): string
    {
        if ($this->documentContent === null) {
            $this->documentContent = Storage::disk('local')->get($this->documentPath) ?? '';
        }

        return $this->documentContent;
    }

    /**
     * Get the document path
     */
    public function getDocumentPath(): string
    {
        return $this->documentPath;
    }

    /**
     * Get formatted context for AI
     */
    public function getFormattedContext(): string
    {
        $content = $this->getDocumentContent();
        
        if (empty($content)) {
            return 'No document content available.';
        }

        return "Based on the following document:\n\n" . $content . "\n\nPlease answer the user's question using only the information provided in this document.";
    }

    /**
     * Check if document exists
     */
    public function documentExists(): bool
    {
        return Storage::disk('local')->exists($this->documentPath);
    }

    /**
     * Get document summary
     */
    public function getDocumentSummary(): array
    {
        $content = $this->getDocumentContent();
        $lines = explode("\n", trim($content));
        
        $summary = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $summary[$key] = $value;
                }
            }
        }
        
        return $summary;
    }
}
