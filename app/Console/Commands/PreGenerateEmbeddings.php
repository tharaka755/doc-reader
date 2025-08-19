<?php

namespace App\Console\Commands;

use App\Services\RAGService;
use App\Services\DocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PreGenerateEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'embeddings:generate 
                            {--force : Force regeneration even if embeddings exist}
                            {--progress : Show detailed progress}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-generate embeddings for the document to enable fast RAG queries';

    private RAGService $ragService;
    private DocumentService $documentService;

    /**
     * Create a new command instance.
     */
    public function __construct(RAGService $ragService, DocumentService $documentService)
    {
        parent::__construct();
        $this->ragService = $ragService;
        $this->documentService = $documentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting embedding pre-generation...');
        $this->newLine();

        // Check if document exists
        if (!$this->documentService->documentExists()) {
            $this->error('❌ Document not found. Please ensure the document is uploaded.');
            return Command::FAILURE;
        }

        // Show document info
        $this->showDocumentInfo();

        // Check if embeddings already exist
        $documentContent = $this->documentService->getDocumentContent();
        $embeddingsExist = $this->ragService->getStatus()['embeddings_ready'] ?? false;

        if ($embeddingsExist && !$this->option('force')) {
            $this->warn('⚠️  Embeddings already exist.');
            $this->info('💡 Use --force flag to regenerate embeddings');
            
            if (!$this->confirm('Do you want to continue anyway?')) {
                $this->info('✋ Aborted by user.');
                return Command::SUCCESS;
            }
        }

        // Start the generation process
        $this->info('⏳ Generating embeddings...');
        
        if ($this->option('progress')) {
            $this->info('📊 This process will:');
            $this->info('   1. Extract/load document content');
            $this->info('   2. Split content into semantic chunks');
            $this->info('   3. Generate embeddings for each chunk via OpenAI API');
            $this->info('   4. Store embeddings for fast retrieval');
            $this->newLine();
        }

        $startTime = microtime(true);
        
        // Initialize progress bar if not showing detailed progress
        $progressBar = null;
        if (!$this->option('progress')) {
            $this->info('📈 Progress will be shown below...');
            $this->newLine();
        }

        // Generate embeddings
        $result = $this->ragService->initialize();

        $totalTime = microtime(true) - $startTime;

        $this->newLine();
        
        // Show results
        if ($result['status'] === 'success') {
            $this->info('✅ Embeddings generated successfully!');
            $this->newLine();
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Time', round($totalTime, 2) . ' seconds'],
                    ['Chunks Created', $result['stats']['chunk_count'] ?? 'N/A'],
                    ['Embeddings Generated', $result['embeddings_count'] ?? 'N/A'],
                    ['Average Chunk Size', ($result['stats']['average_chunk_size'] ?? 'N/A') . ' characters'],
                    ['Cache Used', $result['embeddings_existed'] ? 'Yes' : 'No'],
                ]
            );

            $this->newLine();
            $this->info('🎉 Your document is now ready for fast AI queries!');
            $this->info('💬 Users can now ask questions without waiting for embedding generation.');
            
        } else {
            $this->error('❌ Failed to generate embeddings: ' . $result['message']);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Show document information
     */
    private function showDocumentInfo()
    {
        try {
            $content = $this->documentService->getDocumentContent();
            $contentLength = strlen($content);
            
            // Try to get document type and size info
            $documentPath = 'private/java_tutorial.pdf'; // This should ideally come from config
            $isPDF = $this->documentService->isPDF();
            
            $this->info('📄 Document Information:');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Type', $isPDF ? 'PDF' : 'Text'],
                    ['Content Length', number_format($contentLength) . ' characters'],
                    ['Estimated Chunks', '~' . ceil($contentLength / 500)],
                    ['Estimated Time', '~' . ceil($contentLength / 500 * 0.8) . ' seconds'],
                ]
            );
            $this->newLine();
            
        } catch (\Exception $e) {
            $this->warn('⚠️  Could not retrieve document details: ' . $e->getMessage());
        }
    }
}
