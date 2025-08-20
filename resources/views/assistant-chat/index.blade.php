<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant Chat - File Search</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 200px);
        }
        .message-bubble {
            max-width: 80%;
            word-wrap: break-word;
        }
        .typing-indicator {
            display: none;
        }
        .typing-indicator.show {
            display: block;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="gradient-bg shadow-lg border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div class="flex items-center">
                        <i class="fas fa-robot text-3xl text-white mr-4"></i>
                        <div>
                            <h1 class="text-3xl font-bold text-white">Assistant Chat</h1>
                            <p class="text-blue-100">Powered by OpenAI Assistant API</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full {{ $isAIAvailable ? 'bg-green-400' : 'bg-red-400' }} mr-2"></div>
                            <span class="text-white font-medium">
                                {{ $isAIAvailable ? 'Assistant Online' : 'Assistant Offline' }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation between different approaches -->
                <div class="flex justify-center space-x-6 py-3 border-t border-white border-opacity-20">
                    <a href="{{ route('chat.index') }}" class="text-white hover:text-blue-200 underline">RAG Chat</a>
                    <a href="{{ route('direct-file-search.index') }}" class="text-white hover:text-blue-200 underline">Direct File Search</a>
                    <a href="{{ route('assistant-chat.index') }}" class="text-white hover:text-blue-200 underline font-semibold">Assistant Chat</a>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Document Summary Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg border p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-file-search text-purple-600 mr-2"></i>
                            Document Summary
                        </h3>
                        <div class="space-y-3">
                            @foreach($documentSummary as $key => $value)
                                <div class="border-b border-gray-100 pb-2">
                                    <dt class="text-sm font-medium text-gray-600">{{ ucfirst($key) }}</dt>
                                    <dd class="text-sm text-gray-900 mt-1">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Responses API Features -->
                        <div class="mt-6 p-4 bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg border border-purple-200">
                            <h4 class="text-sm font-semibold text-purple-800 mb-2">
                                <i class="fas fa-magic mr-2"></i>
                                Responses API Features
                            </h4>
                            <ul class="text-xs text-purple-700 space-y-1">
                                <li><i class="fas fa-check-circle mr-1"></i> Built-in file search</li>
                                <li><i class="fas fa-check-circle mr-1"></i> Optimized for tools</li>
                                <li><i class="fas fa-check-circle mr-1"></i> Single API call</li>
                                <li><i class="fas fa-check-circle mr-1"></i> Latest OpenAI technology</li>
                            </ul>
                        </div>

                        <!-- Status Information -->
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-semibold text-gray-800 mb-2">
                                <i class="fas fa-info-circle mr-2"></i>
                                System Status
                            </h4>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between">
                                    <span>Available:</span>
                                    <span class="text-{{ $status['available'] ? 'green' : 'red' }}-600 font-medium">
                                        {{ $status['available'] ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Document:</span>
                                    <span class="text-{{ $status['document_exists'] ? 'green' : 'red' }}-600 font-medium">
                                        {{ $status['document_exists'] ? 'Ready' : 'Missing' }}
                                    </span>
                                </div>
                                @if($status['vector_store_id'])
                                    <div class="text-green-600">
                                        <i class="fas fa-database mr-1"></i>
                                        Vector Store Ready
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Clear Cache -->
                        <div class="mt-4">
                            <button 
                                id="clearConversationBtn"
                                class="w-full px-3 py-2 bg-orange-500 text-white text-sm rounded-lg hover:bg-orange-600 transition-colors"
                            >
                                <i class="fas fa-refresh mr-2"></i>
                                Clear Cache
                            </button>
                        </div>

                        @if(config('app.debug'))
                            <div class="mt-4 p-2 bg-gray-100 rounded text-xs">
                                <strong>Debug Info:</strong><br>
                                Document Path: {{ $documentService->getDocumentPath() }}<br>
                                Document Exists: {{ $documentService->documentExists() ? 'Yes' : 'No' }}<br>
                                Vector Store ID: {{ $status['vector_store_id'] ?? 'Not created' }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Chat Interface -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-xl shadow-lg border overflow-hidden">
                        <!-- Chat Header -->
                        <div class="gradient-bg px-6 py-4">
                            <h3 class="text-xl font-semibold text-white">
                                <i class="fas fa-magic text-yellow-300 mr-3"></i>
                                Responses API Chat
                            </h3>
                            <p class="text-blue-100 mt-1">OpenAI's latest Responses API with built-in file search</p>
                        </div>

                        <!-- Chat Messages -->
                        <div class="chat-container overflow-y-auto p-6 bg-gray-50" id="chatMessages">
                            <!-- Welcome Message -->
                            <div class="flex justify-start mb-6">
                                <div class="message-bubble bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-xl px-6 py-4 shadow-lg">
                                    <div class="flex items-center mb-3">
                                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-search text-sm"></i>
                                        </div>
                                        <span class="font-semibold">AI Assistant</span>
                                    </div>
                                    <p class="leading-relaxed">Hello! I'm powered by OpenAI's latest Responses API with built-in file search. I can analyze your document and provide detailed answers. What would you like to know?</p>
                                    <div class="mt-3 p-2 bg-white bg-opacity-10 rounded text-sm">
                                        <i class="fas fa-lightbulb mr-2"></i>
                                        Using OpenAI's newest technology!
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Typing Indicator -->
                        <div class="typing-indicator px-6 pb-4 bg-gray-50" id="typingIndicator">
                            <div class="flex items-center">
                                <div class="message-bubble bg-white text-gray-700 rounded-xl px-6 py-4 shadow-md border">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-robot text-white text-sm"></i>
                                        </div>
                                        <span class="font-medium mr-3">Assistant is searching...</span>
                                        <div class="flex space-x-1">
                                            <div class="w-2 h-2 bg-purple-400 rounded-full animate-bounce"></div>
                                            <div class="w-2 h-2 bg-purple-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                            <div class="w-2 h-2 bg-purple-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Input -->
                        <div class="border-t border-gray-200 px-6 py-4 bg-white">
                            <form id="chatForm" class="flex space-x-4">
                                <div class="flex-1">
                                    <input 
                                        type="text" 
                                        id="messageInput"
                                        placeholder="Ask a detailed question about the document..." 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent shadow-sm"
                                        maxlength="1000"
                                    >
                                </div>
                                <button 
                                    type="submit" 
                                    id="sendButton"
                                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl hover:from-purple-700 hover:to-blue-700 focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fas fa-search mr-2"></i>
                                    Search
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatForm = document.getElementById('chatForm');
            const messageInput = document.getElementById('messageInput');
            const chatMessages = document.getElementById('chatMessages');
            const sendButton = document.getElementById('sendButton');
            const typingIndicator = document.getElementById('typingIndicator');
            const clearConversationBtn = document.getElementById('clearConversationBtn');

            // CSRF token for AJAX requests
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            function addMessage(content, isUser = false) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-6`;
                
                const bubbleClass = isUser 
                    ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg' 
                    : 'bg-white text-gray-900 shadow-md border';
                
                const avatarClass = isUser 
                    ? 'bg-white bg-opacity-20' 
                    : 'bg-gradient-to-r from-purple-500 to-blue-500';
                
                const icon = isUser ? 'fas fa-user' : 'fas fa-robot';
                const iconColor = isUser ? '' : 'text-white';
                const name = isUser ? 'You' : 'AI Assistant';
                
                messageDiv.innerHTML = `
                    <div class="message-bubble ${bubbleClass} rounded-xl px-6 py-4">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 ${avatarClass} rounded-full flex items-center justify-center mr-3">
                                <i class="${icon} ${iconColor} text-sm"></i>
                            </div>
                            <span class="font-semibold">${name}</span>
                        </div>
                        <p class="leading-relaxed whitespace-pre-line">${content}</p>
                    </div>
                `;
                
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            function showTypingIndicator() {
                typingIndicator.classList.add('show');
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            function hideTypingIndicator() {
                typingIndicator.classList.remove('show');
            }

            function setInputState(disabled) {
                messageInput.disabled = disabled;
                sendButton.disabled = disabled;
                if (disabled) {
                    sendButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Searching...';
                } else {
                    sendButton.innerHTML = '<i class="fas fa-search mr-2"></i>Search';
                }
            }

            chatForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (!message) return;

                // Add user message
                addMessage(message, true);
                
                // Clear input and disable
                messageInput.value = '';
                setInputState(true);
                
                // Show typing indicator
                showTypingIndicator();

                try {
                    const response = await fetch('/assistant-chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token
                        },
                        body: JSON.stringify({ message: message })
                    });

                    const data = await response.json();
                    
                    // Hide typing indicator
                    hideTypingIndicator();
                    
                    // Add AI response
                    addMessage(data.response);
                    
                } catch (error) {
                    console.error('Error:', error);
                    hideTypingIndicator();
                    addMessage('Sorry, there was an error processing your request. Please try again.');
                } finally {
                    setInputState(false);
                    messageInput.focus();
                }
            });

            // Enter key to send
            messageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    chatForm.dispatchEvent(new Event('submit'));
                }
            });

                                        // Clear cache
            clearConversationBtn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to clear the cache? This will reset the vector store.')) return;
                
                try {
                    clearConversationBtn.disabled = true;
                    clearConversationBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Clearing...';
                    
                    const response = await fetch('/assistant-chat/clear', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Clear the chat messages except welcome message
                        const welcomeMessage = chatMessages.querySelector('.flex:first-child');
                        chatMessages.innerHTML = '';
                        if (welcomeMessage) {
                            chatMessages.appendChild(welcomeMessage);
                        }
                        
                        // Show success message
                        addMessage('Cache cleared! The system will recreate the vector store on the next query.');
                    } else {
                        alert('Failed to clear conversation: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error clearing conversation:', error);
                    alert('Error clearing conversation');
                } finally {
                    clearConversationBtn.disabled = false;
                    clearConversationBtn.innerHTML = '<i class="fas fa-refresh mr-2"></i>Clear Cache';
                }
            });

            // Focus input on load
            messageInput.focus();
        });
    </script>
</body>
</html>
