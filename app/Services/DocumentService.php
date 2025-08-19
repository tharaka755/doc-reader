<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DocumentService
{
    private string $documentPath = 'private/java_tutorial.pdf'; // Updated to use PDF
    private ?string $documentContent = null;
    private PDFService $pdfService;

    public function __construct(PDFService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Get the document content
     */
    public function getDocumentContent(): string
    {
        if ($this->documentContent === null) {
            if ($this->isPDF()) {
                // Extract text from PDF
                $this->documentContent = $this->pdfService->extractTextFromPDF($this->documentPath);
            } else {
                // Read text file directly
                $this->documentContent = Storage::disk('local')->get($this->documentPath) ?? '';
            }
        }

        return $this->documentContent;
    }

    /**
     * Check if current document is a PDF
     */
    public function isPDF(): bool
    {
        return $this->pdfService->isPDF($this->documentPath);
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
        // Check using Laravel Storage first
        if (Storage::disk('local')->exists($this->documentPath)) {
            return true;
        }
        
        // Fallback: check direct file path
        $fullPath = storage_path('app/' . $this->documentPath);
        return file_exists($fullPath);
    }

    /**
     * Get document summary
     */
    public function getDocumentSummary(): array
    {
        if ($this->isPDF()) {
            return $this->getPDFSummary();
        }

        // For text files, use the original logic
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

    /**
     * Get PDF-specific summary
     */
    private function getPDFSummary(): array
    {
        $pdfInfo = $this->pdfService->getPDFInfo($this->documentPath);
        $content = $this->getDocumentContent();
        
        return [
            'Document Type' => 'PDF',
            'Title' => $pdfInfo['title'] ?? 'Unknown',
            'Author' => $pdfInfo['author'] ?? 'Unknown',
            'Pages' => $pdfInfo['pages'] ?? 'Unknown',
            'File Size' => $this->formatFileSize($pdfInfo['file_size'] ?? 0),
            'Content Length' => number_format(strlen($content)) . ' characters',
            'Word Count' => number_format(str_word_count($content)) . ' words',
            'Preview' => substr($content, 0, 200) . '...'
        ];
    }

    /**
     * Format file size in human-readable format
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Get document preview
     */
    public function getDocumentPreview(int $length = 500): string
    {
        if ($this->isPDF()) {
            return $this->pdfService->getTextPreview($this->documentPath, $length);
        }

        $content = $this->getDocumentContent();
        return substr($content, 0, $length) . (strlen($content) > $length ? '...' : '');
    }
}
