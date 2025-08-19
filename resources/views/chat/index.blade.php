<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Document Chat</title>
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <i class="fas fa-robot text-2xl text-blue-600 mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-900">AI Document Chat</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full {{ $isAIAvailable ? 'bg-green-500' : 'bg-red-500' }} mr-2"></div>
                            <span class="text-sm text-gray-600">
                                {{ $isAIAvailable ? 'AI Online' : 'AI Offline' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Document Summary Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-file-alt text-blue-600 mr-2"></i>
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
                        <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                Ask me anything about the information in this document!
                            </p>
                            @if(config('app.debug'))
                                <div class="mt-2 p-2 bg-gray-100 rounded text-xs">
                                    <strong>Debug Info:</strong><br>
                                    Document Path: {{ $documentService->getDocumentPath() }}<br>
                                    Document Exists: {{ $documentService->documentExists() ? 'Yes' : 'No' }}<br>
                                    @if($aiChatService->getOpenAIFileId())
                                        OpenAI File ID: {{ $aiChatService->getOpenAIFileId() }}
                                    @else
                                        OpenAI File ID: Not uploaded yet
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- File Management Section -->
                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                            <h4 class="text-sm font-semibold text-yellow-800 mb-2">
                                <i class="fas fa-folder-open mr-2"></i>
                                OpenAI File Management
                            </h4>
                            <div class="space-y-2">
                                <button 
                                    id="refreshFilesBtn"
                                    class="w-full px-3 py-2 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 transition-colors"
                                >
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    Refresh File List
                                </button>
                                <button 
                                    id="clearAllFilesBtn"
                                    class="w-full px-3 py-2 bg-red-500 text-white text-xs rounded hover:bg-red-600 transition-colors"
                                >
                                    <i class="fas fa-trash mr-1"></i>
                                    Clear All Files
                                </button>
                            </div>
                            <div id="filesList" class="mt-3 text-xs">
                                <div class="text-gray-600">Click "Refresh File List" to see uploaded files</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Interface -->
                <div class="lg:col-span-3">
                    <div class="bg-white rounded-lg shadow-sm border">
                        <!-- Chat Header -->
                        <div class="border-b border-gray-200 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-comments text-green-600 mr-2"></i>
                                Chat with AI
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">Ask questions about the document content</p>
                        </div>

                        <!-- Chat Messages -->
                        <div class="chat-container overflow-y-auto p-6" id="chatMessages">
                            <!-- Welcome Message -->
                            <div class="flex justify-start mb-4">
                                <div class="message-bubble bg-blue-100 text-blue-900 rounded-lg px-4 py-3">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-robot text-blue-600 mr-2"></i>
                                        <span class="font-medium">AI Assistant</span>
                                    </div>
                                    <p>Hello! I'm your AI assistant. I've read the document and I'm ready to answer your questions about it. What would you like to know?</p>
                                </div>
                            </div>
                        </div>

                        <!-- Typing Indicator -->
                        <div class="typing-indicator px-6 pb-4" id="typingIndicator">
                            <div class="flex items-center">
                                <div class="message-bubble bg-gray-100 text-gray-700 rounded-lg px-4 py-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-robot text-gray-600 mr-2"></i>
                                        <span class="font-medium mr-2">AI Assistant</span>
                                        <div class="flex space-x-1">
                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Input -->
                        <div class="border-t border-gray-200 px-6 py-4">
                            <form id="chatForm" class="flex space-x-3">
                                <div class="flex-1">
                                    <input 
                                        type="text" 
                                        id="messageInput"
                                        placeholder="Type your question here..." 
                                        value="whats in this doc"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        maxlength="1000"
                                    >
                                </div>
                                <button 
                                    type="submit" 
                                    id="sendButton"
                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Send
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

            // CSRF token for AJAX requests
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            function addMessage(content, isUser = false) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${isUser ? 'justify-end' : 'justify-start'} mb-4`;
                
                const bubbleClass = isUser 
                    ? 'bg-blue-600 text-white' 
                    : 'bg-gray-100 text-gray-900';
                
                const icon = isUser ? 'fas fa-user' : 'fas fa-robot';
                const name = isUser ? 'You' : 'AI Assistant';
                
                messageDiv.innerHTML = `
                    <div class="message-bubble ${bubbleClass} rounded-lg px-4 py-3">
                        <div class="flex items-center mb-2">
                            <i class="${icon} ${isUser ? 'text-blue-200' : 'text-gray-600'} mr-2"></i>
                            <span class="font-medium">${name}</span>
                        </div>
                        <p>${content}</p>
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
                    const response = await fetch('/chat', {
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

            // Focus input on load
            messageInput.focus();

            // File Management Functions
            const refreshFilesBtn = document.getElementById('refreshFilesBtn');
            const clearAllFilesBtn = document.getElementById('clearAllFilesBtn');
            const filesList = document.getElementById('filesList');

            // Refresh files list
            refreshFilesBtn.addEventListener('click', async function() {
                try {
                    refreshFilesBtn.disabled = true;
                    refreshFilesBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Loading...';
                    
                    const response = await fetch('/chat/files');
                    const data = await response.json();
                    
                    if (data.files && data.files.length > 0) {
                        let filesHtml = '<div class="space-y-2">';
                        data.files.forEach(file => {
                            const fileSize = (file.bytes / 1024).toFixed(2);
                            const createdAt = file.created_at ? new Date(file.created_at * 1000).toLocaleString() : 'Unknown';
                            
                            filesHtml += `
                                <div class="p-2 bg-white rounded border">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="font-medium text-gray-800">${file.filename}</div>
                                            <div class="text-gray-600">ID: ${file.id}</div>
                                            <div class="text-gray-600">Size: ${fileSize} KB</div>
                                            <div class="text-gray-600">Purpose: ${file.purpose}</div>
                                            <div class="text-gray-600">Status: ${file.status}</div>
                                            <div class="text-gray-600">Created: ${createdAt}</div>
                                        </div>
                                        <div class="flex space-x-2">
                                            ${file.status === 'processed' && file.purpose === 'assistants' ? 
                                                `<a 
                                                    href="/chat/files/${file.id}/download"
                                                    class="px-2 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 transition-colors"
                                                    title="Download file"
                                                >
                                                    <i class="fas fa-download"></i>
                                                </a>` : 
                                                `<span 
                                                    class="px-2 py-1 bg-gray-400 text-white text-xs rounded cursor-not-allowed"
                                                    title="File not ready for download (Status: ${file.status}, Purpose: ${file.purpose})"
                                                >
                                                    <i class="fas fa-download"></i>
                                                </span>`
                                            }
                                            <button 
                                                onclick="deleteFile('${file.id}')"
                                                class="px-2 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 transition-colors"
                                                title="Delete file"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        filesHtml += '</div>';
                        filesList.innerHTML = filesHtml;
                    } else {
                        filesList.innerHTML = '<div class="text-gray-600">No files uploaded</div>';
                    }
                } catch (error) {
                    console.error('Error fetching files:', error);
                    filesList.innerHTML = '<div class="text-red-600">Error loading files</div>';
                } finally {
                    refreshFilesBtn.disabled = false;
                    refreshFilesBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Refresh File List';
                }
            });

            // Delete individual file
            window.deleteFile = async function(fileId) {
                if (!confirm('Are you sure you want to delete this file?')) return;
                
                try {
                    const response = await fetch(`/chat/files/${fileId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': token
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Refresh the files list
                        refreshFilesBtn.click();
                    } else {
                        alert('Failed to delete file: ' + data.message);
                    }
                } catch (error) {
                    console.error('Error deleting file:', error);
                    alert('Error deleting file');
                }
            };

            // Clear all files
            clearAllFilesBtn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to delete ALL uploaded files? This action cannot be undone.')) return;
                
                try {
                    clearAllFilesBtn.disabled = true;
                    clearAllFilesBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';
                    
                    const response = await fetch('/chat/files', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': token
                        }
                    });
                    
                    const data = await response.json();
                    
                    alert(data.message);
                    
                    // Refresh the files list
                    refreshFilesBtn.click();
                    
                } catch (error) {
                    console.error('Error clearing files:', error);
                    alert('Error clearing files');
                } finally {
                    clearAllFilesBtn.disabled = false;
                    clearAllFilesBtn.innerHTML = '<i class="fas fa-trash mr-1"></i>Clear All Files';
                }
            });
        });
    </script>
</body>
</html>
