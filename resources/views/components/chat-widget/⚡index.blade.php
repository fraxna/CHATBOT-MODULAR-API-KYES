<?php

use Livewire\Component;

new class extends Component {
    // Single File Component - Logic murni di Alpine.js client-side
};

?>

<div x-data="chatClientEngine()" x-init="initEngine()" class="fixed bottom-6 right-6 z-50 font-sans">

    {{-- 1. Tombol Chatbot Floating --}}
    <button x-show="isReady" x-cloak x-transition @click="isOpen = !isOpen"
        class="relative bg-blue-600 hover:bg-blue-500 text-white p-3.5 rounded-full shadow-lg shadow-blue-900/30 border border-blue-400/30 transition-all duration-200 active:scale-95 flex items-center justify-center group"
        title="Buka Chatbot AI">
        <span class="absolute top-0 right-0 flex h-3 w-3 -mt-0.5 -mr-0.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 border-2 border-gray-900"></span>
        </span>

        <svg class="w-6 h-6 transition-transform duration-200 group-hover:scale-110" fill="none"
            stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z">
            </path>
        </svg>
    </button>

    {{-- 2. Tampilan UI Chat Floating Box --}}
    <div x-show="isOpen" x-cloak x-transition
        class="fixed bottom-24 right-6 w-96 max-w-[calc(100vw-3rem)] bg-gray-900 border border-gray-700/80 rounded-xl shadow-2xl overflow-hidden flex flex-col h-[520px] backdrop-blur-sm">

        {{-- Header Chat --}}
        <div class="px-4 py-3 bg-gray-800/90 border-b border-gray-700/80 flex justify-between items-center">

            <div class="flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>

                <span class="text-sm font-semibold text-white tracking-wide">
                    AI Support SMKN 6

                    <span
                        class="text-xs px-2 py-0.5 rounded bg-blue-950 text-blue-400 border border-blue-800/60 font-mono">
                        ONLINE
                    </span>
                </span>
            </div>

            <button @click="isOpen = false" class="text-gray-400 hover:text-white p-1 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>

        </div>

        {{-- Messages Container --}}
        <div x-ref="chatContainer"
            class="flex-1 p-4 overflow-y-auto space-y-3.5 text-xs font-sans scrollbar-thin scrollbar-thumb-gray-700">

            <template x-for="(msg, i) in messages" :key="i">

                <div :class="msg.role === 'user' ? 'text-right' : 'text-left'">

                    <div :class="msg.role === 'user' ?
                        'bg-blue-600 text-white rounded-br-none' :
                        'bg-gray-800 text-gray-200 border border-gray-700/70 rounded-bl-none'"
                        class="inline-block px-3.5 py-2.5 rounded-xl max-w-[85%] text-left leading-relaxed shadow-sm break-words">

                        {{-- Tampilan saat AI sedang diproses (Content masih kosong & sedang streaming) --}}
                        <template
                            x-if="msg.role === 'assistant' && !msg.content && isStreaming && i === messages.length - 1">
                            <div class="flex items-center space-x-2 text-gray-400 font-medium py-0.5">
                                <div class="flex space-x-1">
                                    <span class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-bounce"
                                        style="animation-delay: 0ms"></span>
                                    <span class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-bounce"
                                        style="animation-delay: 150ms"></span>
                                    <span class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-bounce"
                                        style="animation-delay: 300ms"></span>
                                </div>
                                <span class="text-[11px] text-gray-400 italic">Sabar guys ini lagi loading...</span>
                            </div>
                        </template>

                        {{-- Tampilan Teks Normal / Markdown --}}
                        <span x-show="msg.content" x-html="parseMarkdown(msg.content)"></span>

                        {{-- Kursor kedip saat teks sedang ber-stream --}}
                        <span x-show="isStreaming && msg.content && i === messages.length - 1"
                            class="inline-block w-1.5 h-3 bg-blue-400 animate-pulse ml-0.5 align-middle"></span>
                    </div>

                </div>

            </template>

        </div>

        {{-- Input Form Area --}}
        <form @submit.prevent="handleSend()" class="p-3 border-t border-gray-800 bg-gray-900/90 flex gap-2">

            <input type="text" x-model="userPrompt" :disabled="isStreaming"
                placeholder="Tanyakan info jurusan, fasilitas, PKL..."
                class="flex-1 bg-gray-800 border border-gray-700 text-white placeholder-gray-500 px-3 py-2 rounded-lg text-xs focus:ring-1 focus:ring-blue-500 focus:border-blue-500 focus:outline-none transition disabled:opacity-50">

            <button type="submit" :disabled="isStreaming || !userPrompt.trim()"
                class="bg-blue-600 hover:bg-blue-500 text-white px-3.5 py-2 rounded-lg font-semibold text-xs transition-colors shadow flex items-center space-x-1 disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-text="isStreaming ? '...' : 'KIRIM'"></span>

                <svg x-show="!isStreaming" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3">
                    </path>
                </svg>
            </button>

        </form>

    </div>

    {{-- Fuse.js CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/fuse.js@6.6.2"></script>

    <script>
        function chatClientEngine() {
            return {
                isReady: false,
                isOpen: false,
                isStreaming: false,
                userPrompt: '',
                messages: [],
                knowledgeBase: [],
                vocabulary: [],
                fuseVocab: null,
                MIN_RELEVANCE_SCORE: 20,

                stopwords: new Set([
                    'apa', 'apakah', 'itu', 'ini', 'info', 'informasi', 'tentang', 'mengenai',
                    'jelaskan', 'jelaskanlah', 'jelasin', 'tolong', 'mohon', 'yang', 'dan',
                    'atau', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'dalam', 'adalah',
                    'merupakan', 'sebutkan', 'carikan', 'cari', 'bagaimana', 'kenapa', 'mengapa'
                ]),

                async initEngine() {
                    try {
                        let cleanKnowledge = [];
                        try {
                            const res = await fetch('/api/knowledge/version');
                            if (!res.ok) throw new Error(`HTTP ${res.status}`);
                            const data = await res.json();
                            const serverVersion = data.version;
                            const localVersion = localStorage.getItem('knowledge_version');
                            const localData = localStorage.getItem('knowledge_data');

                            if (localVersion !== serverVersion || !localData) {
                                const knowledgeRes = await fetch('/api/knowledge/download');
                                if (!knowledgeRes.ok) throw new Error(`HTTP ${knowledgeRes.status}`);
                                const rawJson = await knowledgeRes.json();
                                cleanKnowledge = rawJson.items || rawJson;

                                localStorage.setItem('knowledge_version', serverVersion);
                                localStorage.setItem('knowledge_data', JSON.stringify(cleanKnowledge));
                            } else {
                                cleanKnowledge = JSON.parse(localData);
                            }
                        } catch (e) {
                            const fallbackRes = await fetch('/storage/knowledge.json');
                            if (!fallbackRes.ok) throw new Error(`HTTP ${fallbackRes.status}`);
                            const rawFallback = await fallbackRes.json();
                            cleanKnowledge = rawFallback.items || rawFallback;
                        }

                        if (!Array.isArray(cleanKnowledge)) {
                            throw new Error('Format knowledge tidak valid. Expected array.');
                        }

                        this.knowledgeBase = cleanKnowledge.map(item => ({
                            id: String(item.id || ''),
                            title: String(item.title || ''),
                            content: String(item.content || ''),
                            keywords: Array.isArray(item.keywords) ?
                                item.keywords.map(k => String(k).toLowerCase().trim()) : []
                        }));

                        this.buildVocabulary();
                        this.isReady = true;

                    } catch (err) {
                        console.error('❌ Gagal sinkronisasi knowledge base.', err);
                        this.isReady = false;
                    }
                },

                // Parser Markdown Ringan & Aman (Escape XSS + Bold, Italic, Code, List, Newline)
                parseMarkdown(text) {
                    if (!text) return '';

                    let html = text
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;");

                    html = html.replace(/```([\s\S]*?)```/g,
                        '<pre class="bg-gray-950 p-2 rounded border border-gray-700 my-1 overflow-x-auto font-mono text-xs"><code>$1</code></pre>'
                    );

                    html = html.replace(/`([^`]+)`/g,
                    '<code class="bg-gray-950 px-1 rounded text-blue-300 font-mono">$1</code>');

                html = html.replace(/(\*\*\*|___)(.*?)\1/g, '<strong><em>$2</em></strong>');
                html = html.replace(/(\*\*|__)(.*?)\1/g, '<strong class="font-bold text-white">$2</strong>');
                html = html.replace(/(\*|_)(.*?)\1/g, '<em class="italic">$2</em>');
                html = html.replace(/^\s*[\*\-]\s+(.*)$/gm, '<li class="ml-4 list-disc">$1</li>');
                html = html.replace(/\n/g, '<br>');

                return html;
            },

            buildVocabulary() {
                const vocabSet = new Set();
                this.knowledgeBase.forEach(item => {
                    this.normalizeText(item.title).split(/\s+/).forEach(w => {
                        if (w.length > 2 && !this.stopwords.has(w)) vocabSet.add(w);
                    });
                    item.keywords.forEach(kw => {
                        this.normalizeText(kw).split(/\s+/).forEach(w => {
                            if (w.length > 2 && !this.stopwords.has(w)) vocabSet.add(w);
                        });
                    });
                });

                this.vocabulary = Array.from(vocabSet).map(word => ({
                    word
                }));
                this.fuseVocab = new Fuse(this.vocabulary, {
                    keys: ['word'],
                    includeScore: true,
                    threshold: 0.4,
                    distance: 100,
                    minMatchCharLength: 3
                });
            },

            normalizeText(text) {
                return String(text || '')
                    .toLowerCase()
                    .normalize('NFKC')
                    .replace(/[^a-z0-9\s]/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            },

            tokenizeQueryWithTypoCorrection(rawQuery) {
                const normalized = this.normalizeText(rawQuery);
                const rawTokens = normalized.split(/\s+/).filter(Boolean).filter(w => !this.stopwords.has(w));
                const correctedTokens = [];

                rawTokens.forEach(token => {
                    const isExact = this.vocabulary.some(v => v.word === token);
                    if (isExact || token.length <= 2) {
                        correctedTokens.push({
                            original: token,
                            corrected: token,
                            isTypo: false
                        });
                    } else if (this.fuseVocab) {
                        const res = this.fuseVocab.search(token);
                        if (res.length > 0 && res[0].score <= 0.38) {
                            correctedTokens.push({
                                original: token,
                                corrected: res[0].item.word,
                                isTypo: true
                            });
                        } else {
                            correctedTokens.push({
                                original: token,
                                corrected: token,
                                isTypo: false
                            });
                        }
                    }
                });

                return correctedTokens;
            },

            calculateScore(queryTokens, item) {
                let score = 0;
                let reasons = [];
                const itemTitle = this.normalizeText(item.title);
                const itemContent = this.normalizeText(item.content);
                const itemKeywords = item.keywords.map(k => this.normalizeText(k));

                queryTokens.forEach(t => {
                    const word = t.corrected;
                    const baseWeight = t.isTypo ? 0.75 : 1.0;

                    for (const kw of itemKeywords) {
                        if (kw === word) {
                            score += Math.round(100 * baseWeight);
                            break;
                        } else if (kw.includes(word)) {
                            score += Math.round(60 * baseWeight);
                            break;
                        }
                    }

                    if (itemTitle.includes(word)) score += Math.round(40 * baseWeight);
                    if (itemContent.includes(word)) score += Math.round(15 * baseWeight);
                });

                return {
                    score,
                    reasons
                };
            },

            getTop5Knowledge(rawQuery) {
                if (!this.knowledgeBase.length || !rawQuery || !rawQuery.trim()) return [];

                const queryTokens = this.tokenizeQueryWithTypoCorrection(rawQuery);
                const scoredItems = [];

                for (const item of this.knowledgeBase) {
                    const {
                        score,
                        reasons
                    } = this.calculateScore(queryTokens, item);
                    scoredItems.push({
                        item,
                        score,
                        reasons
                    });
                }

                const relevant = scoredItems.filter(entry => entry.score >= this.MIN_RELEVANCE_SCORE);
                relevant.sort((a, b) => b.score - a.score);

                return relevant.slice(0, 5).map(entry => ({
                    id: entry.item.id,
                    title: entry.item.title,
                    content: entry.item.content
                }));
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    if (this.$refs.chatContainer) {
                        this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
                    }
                });
            },

            async handleSend() {
                if (!this.userPrompt.trim() || this.isStreaming) return;

                const promptText = this.userPrompt;
                this.userPrompt = '';

                this.messages.push({
                    role: 'user',
                    content: promptText
                });
                const top5Knowledge = this.getTop5Knowledge(promptText);

                this.messages.push({
                    role: 'assistant',
                    content: ''
                });
                const assistantIndex = this.messages.length - 1;
                this.isStreaming = true;
                this.scrollToBottom();

                try {
                    const response = await fetch('/api/chat/stream', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute(
                                'content') || ''
                        },
                        body: JSON.stringify({
                            knowledge: top5Knowledge,
                            query: promptText
                        })
                    });

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                        const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
                        let sseBuffer = '';

                        while (true) {
                            const {
                                value,
                                done
                            } = await reader.read();
                            if (done) break;

                            if (value) {
                                sseBuffer += value;
                                const lines = sseBuffer.split('\n');
                                sseBuffer = lines.pop();

                                for (let line of lines) {
                                    line = line.trim();
                                    if (line.startsWith('data: ')) {
                                        const content = line.replace('data: ', '');
                                        if (content === '[DONE]') break;
                                        try {
                                            const json = JSON.parse(content);
                                            const textChunk = json.candidates?.[0]?.content?.parts?.[0]?.text ||
                                                json.choices?.[0]?.delta?.content ||
                                                json.text ||
                                                '';

                                            this.messages[assistantIndex].content += textChunk;
                                        } catch (e) {
                                            // Abaikan jika chunk JSON terpotong di tengah stream
                                        }
                                    } else if (line && !line.startsWith(':') && !line.startsWith('data:')) {
                                        this.messages[assistantIndex].content += line;
                                    }
                                }
                                this.scrollToBottom();
                            }
                        }

                    } catch (err) {
                        console.error('❌ Stream Error:', err);
                        this.messages[assistantIndex].content += '\n\n[Gagal terhubung ke layanan AI Support]';
                    } finally {
                        this.isStreaming = false;
                        this.scrollToBottom();
                    }
                }
            };
        }
    </script>

</div>
