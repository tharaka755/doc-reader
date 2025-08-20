<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct File Search - Document Reader</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="app" class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">Direct File Search</h1>
            <p class="text-gray-600">Test OpenAI Responses API with file search tool</p>
            <div class="flex justify-center space-x-4 mt-4">
                <a href="{{ route('chat.index') }}" class="text-blue-600 hover:text-blue-800 underline">RAG Chat</a>
                <span class="text-gray-400">|</span>
                <a href="{{ route('direct-file-search.index') }}" class="text-blue-600 hover:text-blue-800 underline font-semibold">Direct File Search</a>
                <span class="text-gray-400">|</span>
                <a href="{{ route('assistant-chat.index') }}" class="text-blue-600 hover:text-blue-800 underline">Assistant Chat</a>
            </div>
        </div>

        <!-- Status Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">System Status</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold {{ $isAIAvailable ? 'text-green-600' : 'text-red-600' }}">
                        {{ $isAIAvailable ? '✓' : '✗' }}
                    </div>
                    <div class="text-sm text-gray-600">AI Service</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold {{ $documentService->documentExists() ? 'text-green-600' : 'text-red-600' }}">
                        {{ $documentService->documentExists() ? '✓' : '✗' }}
                    </div>
                    <div class="text-sm text-gray-600">Document</div>
                </div>
                <div class="text-center p-4 bg-gray-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ isset($status) && isset($status['vector_store_id']) && $status['vector_store_id'] ? '✓' : '⏳' }}
                    </div>
                    <div class="text-sm text-gray-600">Vector Store</div>
                </div>
            </div>
            
            @if(isset($status) && is_array($status))
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Service Details</h4>
                <div class="text-xs text-gray-600 space-y-1">
                    <div>Available: {{ $status['available'] ? 'Yes' : 'No' }}</div>
                    <div>Document Exists: {{ $status['document_exists'] ? 'Yes' : 'No' }}</div>
                    <div>Vector Store ID: {{ $status['vector_store_id'] ?? 'Not initialized' }}</div>
                </div>
            </div>
            @endif
        </div>

        <!-- Document Summary -->
        @if($documentService->documentExists())
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Document Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <strong>File:</strong> {{ $documentSummary['filename'] ?? $documentSummary['Title'] ?? 'Unknown' }}
                </div>
                <div>
                    <strong>Size:</strong> {{ $documentSummary['size'] ?? $documentSummary['File Size'] ?? 'Unknown' }}
                </div>
                <div>
                    <strong>Uploaded:</strong> {{ $documentSummary['uploaded_at'] ?? 'Unknown' }}
                </div>
                <div>
                    <strong>Status:</strong> 
                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                        Available
                    </span>
                </div>
            </div>
        </div>
        @endif

        <!-- Chat Interface -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Direct File Search Chat</h2>
            
            <!-- Chat Messages -->
            <div id="chat-messages" class="space-y-4 mb-6 max-h-96 overflow-y-auto border rounded-lg p-4 bg-gray-50">
                <div class="text-center text-gray-500">
                    Start a conversation by typing a message below...
                </div>
            </div>

            <!-- Input Form -->
            <form id="chat-form" class="flex space-x-2">
                <input 
                    type="text" 
                    id="message-input" 
                    placeholder="Ask a question about the document..." 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    maxlength="1000"
                    required
                >
                <button 
                    type="submit" 
                    id="send-button"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Send
                </button>
            </form>

            <!-- Controls -->
            <div class="flex justify-between items-center mt-4">
                <button 
                    onclick="clearChat()" 
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 underline"
                >
                    Clear Chat
                </button>
                <button 
                    onclick="clearCache()" 
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 underline"
                >
                    Clear Cache
                </button>
            </div>
        </div>
    </div>

    <script>
        // Set up CSRF token for Axios
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const chatMessages = document.getElementById('chat-messages');
        const chatForm = document.getElementById('chat-form');
        const messageInput = document.getElementById('message-input');
        const sendButton = document.getElementById('send-button');

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;

            // Add user message
            addMessage('user', message);
            messageInput.value = '';

            // Disable input while processing
            messageInput.disabled = true;
            sendButton.disabled = true;
            sendButton.textContent = 'Processing...';

            try {
                const response = await axios.post('{{ route("direct-file-search.chat") }}', {
                    message: message
                });

                // Add AI response
                addMessage('assistant', response.data.response);
            } catch (error) {
                console.error('Error:', error);
                addMessage('assistant', 'Sorry, there was an error processing your request. Please try again.');
            } finally {
                // Re-enable input
                messageInput.disabled = false;
                sendButton.disabled = false;
                sendButton.textContent = 'Send';
                messageInput.focus();
            }
        });

        function addMessage(role, content) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `p-3 rounded-lg ${role === 'user' ? 'bg-blue-100 ml-8' : 'bg-white mr-8'}`;
            
            if (role === 'assistant') {
                // Format the response content (preserve newlines and structure)
                const formattedContent = content.replace(/\n/g, '<br>');
                messageDiv.innerHTML = `<strong>AI Response:</strong><br>${formattedContent}`;
            } else {
                messageDiv.innerHTML = `<strong>You:</strong> ${content}`;
            }
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function clearChat() {
            chatMessages.innerHTML = '<div class="text-center text-gray-500">Start a conversation by typing a message below...</div>';
        }

        async function clearCache() {
            try {
                const response = await axios.post('{{ route("direct-file-search.clear") }}');
                if (response.data.success) {
                    alert('Cache cleared successfully!');
                    location.reload(); // Reload to refresh status
                } else {
                    alert('Failed to clear cache');
                }
            } catch (error) {
                console.error('Error clearing cache:', error);
                alert('Error clearing cache');
            }
        }
    </script>
</body>
</html>
