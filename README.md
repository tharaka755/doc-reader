# Document Reader with RAG

A Laravel application that allows users to ask questions about uploaded documents using RAG (Retrieval Augmented Generation) powered by OpenAI.

## 🚀 Features

- **Document-based Q&A**: Ask questions about your documents and get intelligent answers
- **RAG Implementation**: Uses chunking, embeddings, and semantic search for scalable document processing
- **Smart Caching**: Only regenerates embeddings when documents change
- **Real-time Chat Interface**: Clean web interface for document conversations
- **Debug Tools**: API endpoints for monitoring RAG system status

## 🏗️ Architecture

### RAG Pipeline:
1. **Document Chunking**: Splits documents into manageable pieces
2. **Embedding Generation**: Converts chunks to vector representations using OpenAI
3. **Vector Storage**: Stores embeddings locally with intelligent caching
4. **Semantic Search**: Finds relevant chunks using cosine similarity
5. **Context Assembly**: Combines relevant chunks for AI processing
6. **Response Generation**: Uses GPT-4o-mini for natural language answers

### Services:
- `ChunkingService`: Handles document splitting with overlap
- `EmbeddingService`: Manages OpenAI embeddings and similarity search
- `RAGService`: Orchestrates the complete RAG pipeline
- `AIChatService`: Coordinates chat functionality with RAG
- `DocumentService`: Handles document storage and retrieval

## 🛠️ Setup

### Prerequisites
- PHP 8.1+
- Composer
- Laravel Valet (recommended) or alternative web server
- OpenAI API key

### Installation

1. **Clone the repository**
   ```bash
   git clone <your-repo-url>
   cd doc-reader
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure OpenAI**
   ```bash
   # Add to .env
   OPENAI_API_KEY=your_openai_api_key_here
   OPENAI_ORGANIZATION=your_org_id_here  # optional
   ```

5. **Set up with Valet**
   ```bash
   valet link doc-reader
   ```

6. **Add your document**
   ```bash
   # Place your document at:
   storage/app/my-details.txt
   ```

## 🎯 Usage

### Web Interface
Visit `http://doc-reader.test/chat` and start asking questions about your document!

### API Endpoints

- `POST /chat` - Send a question and get an AI response
- `GET /chat/rag/status` - Check RAG system status
- `POST /chat/rag/init` - Initialize RAG system manually
- `GET /chat/summary` - Get document summary

### Example Questions
- "What is my name?"
- "What are my hobbies?"
- "Tell me about my preferences"

## 🔧 Development

### RAG System Debugging
```bash
# Check RAG status
curl http://doc-reader.test/chat/rag/status

# Initialize RAG manually
curl -X POST http://doc-reader.test/chat/rag/init
```

### Testing RAG Components
```bash
php artisan tinker

# Test chunking
$service = app(\App\Services\ChunkingService::class);
$chunks = $service->chunkDocument($content);

# Test embeddings
$service = app(\App\Services\EmbeddingService::class);
$similar = $service->findSimilarChunks("What is my name?");
```

## 📁 Project Structure

```
app/
├── Http/Controllers/ChatController.php
└── Services/
    ├── AIChatService.php      # Main chat coordination
    ├── RAGService.php         # RAG pipeline orchestration
    ├── ChunkingService.php    # Document splitting
    ├── EmbeddingService.php   # Vector operations
    └── DocumentService.php    # Document management

storage/app/
├── my-details.txt            # Your document
└── embeddings/              # Generated embeddings (auto-created)
```

## 🧠 How RAG Works

See [RAG_EXPLANATION.md](RAG_EXPLANATION.md) for a detailed explanation of how the system works.

## 🚀 Scaling Considerations

- **Small documents** (<10k tokens): Current approach is perfect
- **Large documents**: Consider vector databases (Pinecone, Weaviate)
- **Multiple users**: Add user authentication and document isolation
- **High volume**: Implement async processing and caching strategies

## 📝 License

Open source - feel free to use and modify!

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 💡 Future Enhancements

- [ ] Multiple document support
- [ ] PDF/DOCX file uploads
- [ ] User authentication
- [ ] Chat history
- [ ] Better chunking strategies
- [ ] Vector database integration