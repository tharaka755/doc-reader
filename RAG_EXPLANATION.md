# How RAG Works - Simple Step-by-Step

## 🏗️ **Setup Phase** (happens once when document is first processed)

1. **Document Chunking**: Take your document → Split into smaller pieces (chunks)
   - Your `my-details.txt` becomes 1 chunk (it's small)
   - Large documents become many chunks

2. **Create Embeddings**: Each chunk → OpenAI converts to numbers (vectors)
   - "my name: Tharaka" → [0.1, -0.3, 0.8, ...] (1536 numbers)
   - These numbers represent the "meaning" of the text

3. **Store Embeddings**: Save these number arrays to disk for later use

## 💬 **When You Ask a Question** (happens every time)

4. **Question → Embedding**: Convert your question to the same type of numbers
   - "What is my name?" → [0.2, -0.1, 0.7, ...] 

5. **Find Similar Chunks**: Compare question numbers with stored chunk numbers
   - Math finds which chunks are most "similar" to your question
   - Returns top 3 most relevant chunks with similarity scores

6. **Build Context**: Take the relevant chunks and combine them
   - Instead of sending the ENTIRE document to AI
   - Only send the relevant parts that might have the answer

7. **Ask AI**: Send question + relevant context to OpenAI
   - AI only sees the important parts, not everything
   - Gets faster, more focused answers

## 🎯 **Why This is Better**

- **Small documents**: Still works great (like yours)
- **Large documents**: Only sends relevant sections to AI (cheaper + faster)
- **Smart search**: Finds answers even if question words don't exactly match document words

That's it! Your system now works like a smart librarian that quickly finds the right book sections before reading them to you.

---

## 🔧 **Technical Implementation**

### Services Created:
- `ChunkingService`: Splits documents intelligently
- `EmbeddingService`: Handles OpenAI embeddings and similarity search
- `RAGService`: Orchestrates the entire pipeline
- `AIChatService`: Updated to use RAG instead of direct context

### Key Features:
- **Caching**: Only regenerates embeddings when document changes
- **Similarity Scoring**: Shows relevance percentages for transparency
- **Debug Endpoints**: `/chat/rag/status` and `/chat/rag/init` for monitoring
- **Scalable Architecture**: Ready for production use with large documents
