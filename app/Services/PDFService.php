<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PDFService
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extract text content from PDF file
     */
    public function extractTextFromPDF(string $filePath): string
    {
        $startTime = microtime(true);
        
        try {
            Log::info("PDF_TIMING: Starting PDF text extraction", ['file' => $filePath, 'start_time' => $startTime]);
            
            // Get the full file path - handle both direct paths and storage paths
            $pathStartTime = microtime(true);
            if (Storage::disk('local')->exists($filePath)) {
                $fullPath = Storage::disk('local')->path($filePath);
            } else {
                // Try direct path construction
                $fullPath = storage_path('app/' . $filePath);
            }
            $pathTime = microtime(true) - $pathStartTime;
            Log::info("PDF_TIMING: Path resolution completed", ['duration_ms' => round($pathTime * 1000, 2)]);
            
            if (!file_exists($fullPath)) {
                throw new \Exception("PDF file not found: {$filePath} (tried: {$fullPath})");
            }

            Log::info("PDF_TIMING: Starting PDF parsing", ['file_size_mb' => round(filesize($fullPath) / 1048576, 2)]);
            
            // Parse the PDF
            $parseStartTime = microtime(true);
            $pdf = $this->parser->parseFile($fullPath);
            $parseTime = microtime(true) - $parseStartTime;
            Log::info("PDF_TIMING: PDF parsing completed", ['duration_ms' => round($parseTime * 1000, 2)]);
            
            // Extract text from all pages
            $extractStartTime = microtime(true);
            $text = $pdf->getText();
            $extractTime = microtime(true) - $extractStartTime;
            Log::info("PDF_TIMING: Text extraction completed", [
                'duration_ms' => round($extractTime * 1000, 2),
                'raw_chars' => strlen($text)
            ]);
            
            // Clean up the extracted text
            $cleanStartTime = microtime(true);
            $cleanedText = $this->cleanExtractedText($text);
            $cleanTime = microtime(true) - $cleanStartTime;
            Log::info("PDF_TIMING: Text cleaning completed", [
                'duration_ms' => round($cleanTime * 1000, 2),
                'final_chars' => strlen($cleanedText)
            ]);
            
            $totalTime = microtime(true) - $startTime;
            Log::info("PDF_TIMING: Total PDF extraction completed", [
                'total_duration_ms' => round($totalTime * 1000, 2),
                'final_length' => strlen($cleanedText)
            ]);
            
            return $cleanedText;

        } catch (\Exception $e) {
            Log::error("Error extracting text from PDF {$filePath}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clean up extracted PDF text
     */
    private function cleanExtractedText(string $text): string
    {
        // Remove excessive whitespace and normalize line breaks
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove page numbers and common PDF artifacts
        $text = preg_replace('/\d+\s*$/', '', $text); // Remove trailing page numbers
        
        // Remove multiple spaces
        $text = preg_replace('/[ ]{2,}/', ' ', $text);
        
        // Normalize line breaks for better sentence detection
        $text = preg_replace('/([.!?])\s*/', '$1 ', $text);
        
        // Remove any remaining control characters
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        return trim($text);
    }

    /**
     * Get PDF metadata
     */
    public function getPDFInfo(string $filePath): array
    {
        try {
            // Get the full file path - handle both direct paths and storage paths
            if (Storage::disk('local')->exists($filePath)) {
                $fullPath = Storage::disk('local')->path($filePath);
            } else {
                // Try direct path construction
                $fullPath = storage_path('app/' . $filePath);
            }
            
            if (!file_exists($fullPath)) {
                return [];
            }

            $pdf = $this->parser->parseFile($fullPath);
            $details = $pdf->getDetails();
            
            return [
                'title' => $details['Title'] ?? 'Unknown',
                'author' => $details['Author'] ?? 'Unknown',
                'subject' => $details['Subject'] ?? '',
                'creator' => $details['Creator'] ?? 'Unknown',
                'pages' => count($pdf->getPages()),
                'file_size' => filesize($fullPath),
                'creation_date' => $details['CreationDate'] ?? null,
                'modification_date' => $details['ModDate'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error("Error getting PDF info for {$filePath}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if file is a PDF
     */
    public function isPDF(string $filePath): bool
    {
        return strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Get text preview from PDF (first N characters)
     */
    public function getTextPreview(string $filePath, int $length = 500): string
    {
        try {
            $fullText = $this->extractTextFromPDF($filePath);
            return substr($fullText, 0, $length) . (strlen($fullText) > $length ? '...' : '');
        } catch (\Exception $e) {
            return "Error reading PDF: " . $e->getMessage();
        }
    }
}
