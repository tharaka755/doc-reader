# Document Reader with Dual AI Chat

A Laravel application that allows users to ask questions about uploaded documents using two different AI implementations: **RAG (Retrieval Augmented Generation)** and **OpenAI Assistant API with File Search**.

## 🚀 Features

- **Two Chat Implementations**: Choose between RAG-based chat and OpenAI Assistant API
- **Document-based Q&A**: Ask questions about your documents and get intelligent answers
- **RAG Implementation**: Uses chunking, embeddings, and semantic search for scalable document processing
- **Assistant API Integration**: Leverages OpenAI's file search tools for advanced document analysis
- **Smart Caching**: Only regenerates embeddings when documents change
- **Real-time Chat Interface**: Clean web interfaces for both chat implementations
- **Debug Tools**: API endpoints for monitoring both systems

## 🏗️ Architecture

### RAG Pipeline:
1. **Document Chunking**: Splits documents into manageable pieces
2. **Embedding Generation**: Converts chunks to vector representations using OpenAI
3. **Vector Storage**: Stores embeddings locally with intelligent caching
4. **Semantic Search**: Finds relevant chunks using cosine similarity
5. **Context Assembly**: Combines relevant chunks for AI processing
6. **Response Generation**: Uses GPT-4o-mini for natural language answers

### Chat Implementations:

#### 1. RAG-based Chat (`/chat`)
- Uses custom chunking, embeddings, and vector search
- Built for scalability and cost control
- Ideal for understanding system internals

#### 2. Assistant API Chat (`/assistant-chat`)
- Leverages OpenAI's Assistant API with file search tools
- Handles file processing automatically
- More advanced features like citations and conversation persistence

### Services:
- `ChunkingService`: Handles document splitting with overlap (RAG)
- `EmbeddingService`: Manages OpenAI embeddings and similarity search (RAG)
- `RAGService`: Orchestrates the complete RAG pipeline (RAG)
- `AIChatService`: Coordinates chat functionality with RAG (RAG)
- `AssistantChatService`: Manages OpenAI Assistant API integration (Assistant)
- `DocumentService`: Handles document storage and retrieval (Both)

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

### Web Interfaces

#### RAG-based Chat
Visit `http://doc-reader.test/chat` for the custom RAG implementation

#### Assistant API Chat  
Visit `http://doc-reader.test/assistant-chat` for the OpenAI Assistant API implementation

### API Endpoints

#### RAG Chat Endpoints
- `POST /chat` - Send a question and get an AI response
- `GET /chat/rag/status` - Check RAG system status
- `POST /chat/rag/init` - Initialize RAG system manually
- `GET /chat/summary` - Get document summary

#### Assistant Chat Endpoints
- `POST /assistant-chat` - Send a question using Assistant API
- `GET /assistant-chat/status` - Check Assistant system status
- `POST /assistant-chat/clear` - Clear conversation thread
- `GET /assistant-chat/summary` - Get document summary

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
├── Http/Controllers/
│   ├── ChatController.php         # RAG-based chat controller
│   └── AssistantChatController.php # Assistant API chat controller
└── Services/
    ├── AIChatService.php           # RAG chat coordination
    ├── AssistantChatService.php    # Assistant API integration
    ├── RAGService.php              # RAG pipeline orchestration
    ├── ChunkingService.php         # Document splitting
    ├── EmbeddingService.php        # Vector operations
    └── DocumentService.php         # Document management

resources/views/
├── chat/index.blade.php            # RAG chat interface
└── assistant-chat/index.blade.php  # Assistant API chat interface

storage/app/
├── my-details.txt                  # Your document
└── embeddings/                     # Generated embeddings (auto-created)
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

## 🆚 RAG vs Assistant API Comparison

| Feature | RAG Implementation | Assistant API |
|---------|-------------------|---------------|
| **Cost Control** | ✅ Full control over embeddings and chunks | ❌ OpenAI handles everything |
| **Customization** | ✅ Custom chunking, similarity thresholds | ❌ Limited customization |
| **Setup Complexity** | ❌ Requires understanding of RAG concepts | ✅ Simple setup |
| **Citations** | ❌ Manual implementation needed | ✅ Built-in citation support |
| **Conversation Memory** | ❌ Stateless (each query independent) | ✅ Persistent conversation threads |
| **Learning Opportunity** | ✅ Great for understanding AI internals | ❌ Black box approach |
| **File Processing** | ❌ Manual file handling | ✅ Automatic file processing |
| **Scalability** | ✅ Full control over scaling decisions | ❌ Dependent on OpenAI limits |

## 💡 Future Enhancements

### RAG Implementation
- [ ] Multiple document support
- [ ] Better chunking strategies
- [ ] Vector database integration
- [ ] Conversation history storage

### Assistant API Implementation  
- [ ] Multiple file support
- [ ] File management interface
- [ ] Advanced assistant configurations
- [ ] Custom instructions per document

### Both Implementations
- [ ] PDF/DOCX file uploads
- [ ] User authentication
- [ ] Chat history persistence
- [ ] Response quality metrics