/**
 * DGT CHAT: Premium Real-Time Messaging Engine
 * Inspired by Telegram Desktop & WhatsApp Web with DGT branding.
 * Powered by Alpine.js with WebSocket support & delta-sync fallback.
 */

/**
 * Global helper for 1-click clipboard copying from chat code blocks
 */
window.copyChatMessageCode = function(button, encodedCode) {
    if (!button) return;
    try {
        let code = '';
        if (encodedCode) {
            code = decodeURIComponent(escape(atob(encodedCode)));
        } else {
            const card = button.closest('.dgt-code-card');
            const codeEl = card ? card.querySelector('code') : null;
            code = codeEl ? codeEl.textContent : '';
        }
        navigator.clipboard.writeText(code).then(() => {
            const originalHtml = button.innerHTML;
            button.innerHTML = `
                <svg class="w-3.5 h-3.5 text-emerald-400 inline" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span class="text-emerald-400 font-bold">Copied!</span>
            `;
            button.classList.add('bg-emerald-950/80', 'border-emerald-500/50');
            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.classList.remove('bg-emerald-950/80', 'border-emerald-500/50');
            }, 2000);
        }).catch(() => {
            // Fallback for non-https/permissions
            const ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            button.innerText = 'Copied!';
            setTimeout(() => { button.innerText = 'Copy'; }, 2000);
        });
    } catch(e) {
        console.error('Failed to copy chat code', e);
    }
};

window.dgtChat = function(initialConfig = {}) {
    return {
        // Core State
        currentUser: initialConfig.currentUser || {},
        conversations: initialConfig.conversations || [],
        activeConversation: null,
        messages: [],
        usersDirectory: initialConfig.usersDirectory || [],
        userSettings: initialConfig.settings || {
            theme: 'system',
            bubble_color: '#4f46e5',
            wallpaper: 'default',
            wallpaper_image: '',
            font_size: 'medium',
            notification_sound: true,
            status_message: 'Available'
        },
        customWallpaperUrlInput: (initialConfig.settings && initialConfig.settings.wallpaper_image) || '',

        // Navigation & View Filters
        activeTab: 'direct', // 'direct' (default), 'all', 'unread', 'groups', 'pinned', 'archived'
        searchQuery: '',
        searchInChat: '',
        showSearchInChat: false,
        showMembersDropdown: false,
        showInfoPanel: false, // Default closed; only show when user clicks the info (!) button
        mobileView: 'list', // 'list', 'chat', 'info'
        isMobile: window.innerWidth < 768,
        isSlidingBack: false,
        isSlidingIn: false,
        showScrollBottomBtn: false,
        swipeReplyMsgId: null,
        swipeReplyOffset: 0,
        _touchReplyMsg: null,

        // Composer & Message Staging
        composerText: '',
        stagedFiles: [],
        replyingTo: null,
        editingMessage: null,
        isSending: false,
        isUploading: false,
        uploadProgress: 0,
        uploadFileName: '',
        showEmojiPicker: false,
        showAttachMenu: false,
        isDraggingOverChat: false,

        // Media Drawer (Emoji, GIF, Stickers) State (WhatsApp Web Style)
        mediaDrawerTab: 'emoji', // 'emoji', 'gif', 'sticker'
        mediaSearchQuery: '',
        gifs: [],
        isLoadingGifs: false,
        stickers: [],
        isLoadingStickers: false,
        _gifDebounce: null,
        stickerActionModal: false,
        selectedStickerMessage: null,
        stickerCreatorModal: false,
        stickerCreator: {
            imageFile: null,
            previewUrl: '',
            caption: 'Thank... Kim',
            activeTool: 'text', // 'text', 'crop', 'emojis'
            cropShape: 'fit', // 'fit', 'circle', 'square'
            hasStickerOutline: true, // WhatsApp style white sticker contour border!
            zoom: 1,
            panX: 0,
            panY: 0,
            isDraggingImage: false,
            imageDragStartX: 0,
            imageDragStartY: 0,
            imageDragInitialPanX: 0,
            imageDragInitialPanY: 0,

            // Text coordinates & dragging
            textX: 50,
            textY: 82,
            isDraggingText: false,
            dragStartX: 0,
            dragStartY: 0,
            dragInitialTextX: 50,
            dragInitialTextY: 82,

            // Text zoom & scaling
            isZoomingText: false,
            zoomTextStartX: 0,
            zoomTextInitialSize: 28,

            // Text styling & multi-color
            fontFamily: 'Impact',
            fontSize: 28,
            textColor: '#ffffff',
            textBgStyle: 'none', // 'none', 'badge_dark', 'badge_light', 'badge_color'
            hasStroke: true,
            strokeColor: '#000000',

            isProcessing: false
        },

        // Profile Modal (WhatsApp Web Style)
        profileModal: false,
        profileTab: 'details', // 'details', 'media', 'docs', 'links', 'members'
        sharedMediaData: {
            media: [],
            docs: [],
            links: [],
            counts: { media: 0, docs: 0, links: 0, total: 0 }
        },
        isLoadingSharedMedia: false,
        isEditingGroupDetails: false,
        groupEditForm: { title: '', description: '' },
        isSavingGroupProfile: false,

        // Poll Creation & Voting State
        pollModal: false,
        pollQuestion: '',
        pollOptions: ['', ''],
        pollMultipleAnswers: false,
        pollIsAnonymous: false,
        isSubmittingPoll: false,

        // Message Forwarding State
        forwardModal: false,
        messageToForward: null,
        forwardSearch: '',
        isForwarding: false,

        // Nickname State
        isEditingNickname: false,
        nicknameInput: '',
        isSavingNickname: false,
        nicknameTargetConv: null,

        // Dynamic State
        typingUsers: [],
        _typingMap: {},
        typingConversations: {}, // Map of convId -> { name, expiresAt } for live sidebar typing indicators
        _messagesCache: {}, // High-speed in-memory message cache (0ms instant switching)
        typingTimer: null,
        lastTypingSent: 0,
        lastSyncId: 0,
        syncTimer: null,
        convListTimer: null,
        isLoadingMessages: false,
        isPusherLive: false,
        _pusherStateBound: false,
        _userBroadcastingSubscribed: false,
        _currentConvChannel: null,

        isConversationTyping(conv) {
            if (!conv) return false;
            const info = this.typingConversations[conv.id];
            return !!(info && info.expiresAt > Date.now());
        },

        getConversationTypingText(conv) {
            const info = this.typingConversations[conv.id];
            if (!info) return 'typing...';
            if (conv.type === 'group' && info.name) {
                return `${info.name} is typing...`;
            }
            return 'typing...';
        },

        _updateTypingUsers() {
            const now = Date.now();
            const active = Object.keys(this._typingMap).filter(name => this._typingMap[name] > now);
            if (active.join(',') !== this.typingUsers.join(',')) {
                const hadTyping = this.typingUsers.length > 0;
                this.typingUsers = active;
                if (!hadTyping && active.length > 0) {
                    this.$nextTick(() => this.scrollToBottom(false));
                }
            }

            // Cleanup expired sidebar typing indicators
            let typingChanged = false;
            for (const cId in this.typingConversations) {
                if (this.typingConversations[cId].expiresAt <= now) {
                    delete this.typingConversations[cId];
                    typingChanged = true;
                }
            }
            if (typingChanged) {
                this.typingConversations = { ...this.typingConversations };
            }
        },

        _deduplicateMessages() {
            // Pass 1: Identify all real database message IDs and their client_temp_ids
            const confirmedTempIds = new Set();
            const seenIds = new Set();
            for (const m of this.messages) {
                if (!m) continue;
                const isTemp = typeof m.id === 'string' && m.id.startsWith('temp_');
                if (!isTemp && m.id) {
                    seenIds.add(Number(m.id));
                    if (m.client_temp_id) confirmedTempIds.add(String(m.client_temp_id));
                }
            }

            // Pass 2: Keep messages, discarding temporary placeholders that were confirmed by DB
            const finalSeenIds = new Set();
            const finalSeenTemps = new Set();
            const result = [];

            for (const m of this.messages) {
                if (!m) continue;
                const isTemp = typeof m.id === 'string' && m.id.startsWith('temp_');

                if (isTemp) {
                    if (m.client_temp_id && confirmedTempIds.has(String(m.client_temp_id))) {
                        continue;
                    }
                    if (finalSeenTemps.has(String(m.id))) {
                        continue;
                    }
                    finalSeenTemps.add(String(m.id));
                    result.push(m);
                    continue;
                }

                const numId = Number(m.id);
                if (numId && finalSeenIds.has(numId)) {
                    continue;
                }
                if (numId) finalSeenIds.add(numId);
                if (m.client_temp_id) finalSeenTemps.add(String(m.client_temp_id));
                result.push(m);
            }

            this.messages = result;
        },

        // Modals
        newChatModal: false,
        newChatSearch: '',
        newGroupModal: false,
        groupTitle: '',
        groupDescription: '',
        selectedGroupUserIds: [],
        addMemberModal: false,
        selectedNewMemberIds: [],
        settingsModal: false,
        settingsTab: 'appearance',
        adminUsers: [],
        adminSearch: '',
        adminLoading: false,
        convertingWebp: false,

        // Lightbox
        lightbox: {
            open: false,
            items: [],
            currentIndex: 0,
            scale: 1,
            zoom: 1,
            panX: 0,
            panY: 0,
            isDragging: false,
            startX: 0,
            startY: 0,
            initialDistance: 0,
            initialScale: 1,
            dragMoved: false,
            url: '',
            name: '',
            type: 'image',
            download_url: ''
        },

        // Toast feedback
        toast: {
            show: false,
            message: '',
            type: 'success'
        },

        // WhatsApp-Style Context Dropdown Menu
        contextMenu: {
            open: false,
            x: 0,
            y: 0,
            msg: null
        },
        showReactionsInMenu: false,
        touchTimer: null,

        // WhatsApp-Style Multi-Select Messages Mode
        isSelectingMessages: false,
        selectedMessageIds: [],

        // In-App Confirmation Modal (replacing browser native confirm)
        confirmModal: {
            open: false,
            title: '',
            message: '',
            confirmText: 'Confirm',
            confirmType: 'danger',
            action: null
        },

        // Telegram-Style Conversation Context Menu
        conversationContextMenu: {
            open: false,
            x: 0,
            y: 0,
            conv: null
        },
        profileTab: 'media', // 'media', 'files', 'links', 'members'

        // Telegram-Style Voice Message Recording State
        voiceRecording: {
            active: false,
            mode: 'tap', // 'tap' or 'hold'
            startTime: 0,
            duration: 0,
            timer: null,
            mediaRecorder: null,
            stream: null,
            audioChunks: [],
            audioBlob: null,
            audioUrl: null,
            analyser: null,
            audioCtx: null,
            waveformData: [],
            previewAudio: null,
            isPlayingPreview: false,
        },

        // Telegram-Style Interactive Waveform Voice Message Player State
        voicePlayer: {
            currentMsgId: null,
            audio: null,
            isPlaying: false,
            currentTime: 0,
            duration: 0,
            playbackRate: 1.0, // 1.0, 1.5, 2.0
        },

        // WebRTC One-to-One Voice & Video Call State
        callState: {
            active: false,
            status: 'idle', // 'idle', 'calling', 'ringing', 'connected', 'ended'
            call: initialConfig.activeCall || null,
            isCaller: false,
            isMuted: false,
            isVideo: false,
            isCameraOn: false,
            remoteVideoActive: false,
            duration: 0,
            durationTimer: null,
            minimized: false, // floating glass capsule mode
            peerConnection: null,
            localStream: null,
            remoteStream: null,
            ringtoneInterval: null,
            ringtoneAudioEl: null,
            signalPollTimer: null,
            lastSignalId: 0,
            iceServers: (Array.isArray(initialConfig.iceServers) && initialConfig.iceServers.length > 0)
                ? initialConfig.iceServers
                : [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' },
                    { urls: 'stun:stun2.l.google.com:19302' },
                    { urls: 'stun:stun3.l.google.com:19302' },
                    { urls: 'stun:stun4.l.google.com:19302' },
                    { urls: 'stun:stun.cloudflare.com:3478' },
                    { urls: 'stun:openrelay.metered.ca:80' },
                    {
                        urls: [
                            'turn:openrelay.metered.ca:80',
                            'turn:openrelay.metered.ca:443',
                            'turn:openrelay.metered.ca:443?transport=tcp',
                            'turns:openrelay.metered.ca:443?transport=tcp'
                        ],
                        username: 'openrelayproject',
                        credential: 'openrelayproject'
                    }
                ],
            remoteOffer: null,
            pendingCandidates: [],
            _isHandlingOffer: false,
            _processedSignalIds: null,
            audioBlocked: false,
            swappedVideoView: false,
        },

        // Audio Context for crisp notifications and synthesized tones
        audioCtx: null,

        // Popular Emojis Categories (Array for Alpine x-for iteration)
        emojiCategoryList: [
            {
                name: 'Smileys & Emotion',
                emojis: ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '😉', '😌', '😍', '🥰', '😘', '😋', '😛', '😜', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '🤔', '🤫', '🤭', '🥱', '😴', '🤤']
            },
            {
                name: 'People & Gestures',
                emojis: ['👍', '👎', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '✋', '🤚', '🖐️', '🖖', '👋', '💪', '👊', '🤛', '🤜', '✊']
            },
            {
                name: 'Love & Symbols',
                emojis: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '✨', '⭐', '🌟', '💫', '💥', '🔥', '💯', '💢', '🎉', '🎊', '🏆', '🎯', '🚀']
            },
            {
                name: 'Work & Tech',
                emojis: ['💻', '🖥️', '📱', '⌨️', '🖱️', '📈', '📊', '📋', '📁', '📂', '📑', '📄', '📝', '✉️', '📦', '📌', '📎', '⚙️', '🛠️', '🔒', '🔑', '💡', '⏰', '⏳', '☕', '🚀', '✅', '❌', '⚠️', '⚡']
            }
        ],

        emojiCategories: {
            'Smileys & Emotion': ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '😉', '😌', '😍', '🥰', '😘', '😋', '😛', '😜', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '🤔', '🤫', '🤭', '🥱', '😴', '🤤'],
            'People & Gestures': ['👍', '👎', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '✋', '🤚', '🖐️', '🖖', '👋', '💪', '👊', '🤛', '🤜', '✊'],
            'Love & Symbols': ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '✨', '⭐', '🌟', '💫', '💥', '🔥', '💯', '💢', '🎉', '🎊', '🏆', '🎯', '🚀'],
            'Work & Tech': ['💻', '🖥️', '📱', '⌨️', '🖱️', '📈', '📊', '📋', '📁', '📂', '📑', '📄', '📝', '✉️', '📦', '📌', '📎', '⚙️', '🛠️', '🔒', '🔑', '💡', '⏰', '⏳', '☕', '🚀', '✅', '❌', '⚠️', '⚡']
        },

        // Fast Emoji Keywords Dictionary for instant search (e.g. 'Cry' -> 😭 😢 🥺)
        emojiKeywords: {
            // Smileys & Emotion
            '😀': ['grinning', 'smile', 'happy', 'grin'],
            '😃': ['smiley', 'happy', 'smile', 'excited'],
            '😄': ['smile', 'happy', 'laugh', 'cheerful'],
            '😁': ['beaming', 'smile', 'teeth', 'happy'],
            '😆': ['laughing', 'satisfied', 'haha', 'lol'],
            '😅': ['sweat smile', 'nervous', 'relief', 'whew'],
            '😂': ['joy', 'laugh', 'laughing', 'cry laughing', 'lol', 'funny', 'tears of joy', 'haha', 'cry'],
            '🤣': ['rofl', 'rolling on the floor laughing', 'laugh', 'lol', 'lmao', 'funny'],
            '😊': ['blush', 'smile', 'happy', 'warm'],
            '😇': ['innocent', 'halo', 'angel', 'pure'],
            '🙂': ['slight smile', 'smile', 'ok'],
            '😉': ['wink', 'flirt', 'playful'],
            '😌': ['relieved', 'peaceful', 'content'],
            '😍': ['heart eyes', 'love', 'crush', 'adore', 'heart'],
            '🥰': ['hearts', 'in love', 'love', 'warm', 'kiss'],
            '😘': ['kiss', 'blow kiss', 'love', 'flirt'],
            '😋': ['yum', 'delicious', 'tasty', 'silly', 'food'],
            '😛': ['tongue', 'playful', 'silly'],
            '😜': ['winking tongue', 'crazy', 'joke'],
            '😎': ['sunglasses', 'cool', 'chill', 'boss'],
            '🤩': ['star struck', 'excited', 'wow', 'super'],
            '🥳': ['party', 'celebrate', 'birthday', 'yay'],
            '😏': ['smirk', 'flirt', 'sly'],
            '😒': ['unamused', 'bored', 'annoyed'],
            '😞': ['disappointed', 'sad', 'down', 'cry'],
            '😔': ['pensive', 'sad', 'thoughtful', 'cry'],
            '😟': ['worried', 'nervous', 'concern'],
            '😕': ['confused', 'unsure'],
            '🙁': ['frown', 'sad', 'cry'],
            '😣': ['persevering', 'struggling'],
            '😖': ['confounded', 'distressed'],
            '😫': ['tired', 'frustrated'],
            '😩': ['weary', 'exhausted', 'ugh', 'cry'],
            '🥺': ['pleading', 'puppy eyes', 'beg', 'cry', 'crying', 'sad', 'cute', 'please'],
            '😢': ['cry', 'crying', 'tear', 'sad', 'unhappy', 'weep', 'sob'],
            '😭': ['cry', 'crying', 'sob', 'sobbing', 'tears', 'sad', 'weep', 'sadness', 'depressed'],
            '😤': ['triumph', 'huff', 'proud', 'fume'],
            '😠': ['angry', 'mad', 'annoyed'],
            '😡': ['pouting', 'rage', 'furious', 'red', 'angry'],
            '🤬': ['swearing', 'curse', 'angry', 'rage'],
            '🤯': ['exploding head', 'mind blown', 'shocked'],
            '😳': ['flushed', 'embarrassed', 'shy'],
            '🥵': ['hot', 'sweat', 'heat'],
            '🥶': ['cold', 'freezing', 'ice'],
            '😱': ['scream', 'fear', 'scared', 'shocked'],
            '😨': ['fearful', 'scared'],
            '😰': ['anxious', 'sweat', 'nervous'],
            '🤔': ['thinking', 'hmm', 'wonder'],
            '🤫': ['shh', 'quiet', 'secret'],
            '🤭': ['giggle', 'hand over mouth', 'oops'],
            '🥱': ['yawn', 'sleepy', 'bored'],
            '😴': ['sleeping', 'zzz', 'asleep'],
            '🤤': ['drool', 'drooling', 'hungry'],

            // Gestures & Body
            '👍': ['thumbs up', 'thumb', 'like', 'good', 'approve', 'yes', 'ok'],
            '👎': ['thumbs down', 'dislike', 'bad', 'no'],
            '👏': ['clap', 'applause', 'bravo', 'congrats'],
            '🙌': ['raising hands', 'celebrate', 'hooray', 'yay'],
            '👐': ['open hands', 'hug'],
            '🤲': ['palms up', 'prayer'],
            '🤝': ['handshake', 'deal', 'agree', 'partner'],
            '🙏': ['pray', 'please', 'thanks', 'thank you', 'namaste', 'hope'],
            '✌️': ['peace', 'victory', 'two'],
            '🤞': ['crossed fingers', 'luck', 'hope'],
            '🤟': ['love you', 'ily'],
            '🤘': ['rock on', 'metal'],
            '🤙': ['call me', 'hang loose', 'shaka'],
            '👈': ['point left', 'left'],
            '👉': ['point right', 'right'],
            '👆': ['point up', 'up'],
            '👇': ['point down', 'down'],
            '☝️': ['index up', 'one'],
            '✋': ['raised hand', 'stop', 'high five'],
            '🤚': ['back of hand', 'raised'],
            '🖐️': ['fingers splayed', 'hand'],
            '🖖': ['vulcan', 'spock'],
            '👋': ['wave', 'hello', 'bye', 'hi'],
            '💪': ['muscle', 'strong', 'flex', 'power'],
            '👊': ['fist bump', 'punch'],
            '🤛': ['left fist'],
            '🤜': ['right fist'],
            '✊': ['raised fist', 'power', 'solidarity'],

            // Love & Symbols
            '❤️': ['red heart', 'heart', 'love', 'like'],
            '🧡': ['orange heart', 'heart', 'love'],
            '💛': ['yellow heart', 'heart', 'love'],
            '💚': ['green heart', 'heart', 'love'],
            '💙': ['blue heart', 'heart', 'love'],
            '💜': ['purple heart', 'heart', 'love'],
            '🖤': ['black heart', 'heart'],
            '🤍': ['white heart', 'heart'],
            '🤎': ['brown heart', 'heart'],
            '💔': ['broken heart', 'sad', 'heartbreak', 'cry'],
            '❣️': ['heart exclamation', 'love'],
            '💕': ['two hearts', 'love'],
            '💞': ['revolving hearts', 'love'],
            '💓': ['beating heart', 'heartbeat'],
            '💗': ['growing heart', 'love'],
            '💖': ['sparkling heart', 'love', 'shine'],
            '💘': ['cupid', 'arrow heart', 'love'],
            '💝': ['gift heart', 'ribbon heart'],
            '✨': ['sparkles', 'magic', 'shine', 'stars', 'clean'],
            '⭐': ['star', 'favorite', 'rating'],
            '🌟': ['glowing star', 'star', 'shine'],
            '💫': ['dizzy', 'sparkle', 'star'],
            '💥': ['collision', 'boom', 'blast', 'bang'],
            '🔥': ['fire', 'flame', 'lit', 'hot', 'trend'],
            '💯': ['100', 'hundred', 'perfect', 'score'],
            '💢': ['anger symbol', 'mad'],
            '🎉': ['party popper', 'tada', 'celebrate', 'congrats'],
            '🎊': ['confetti', 'party'],
            '🏆': ['trophy', 'winner', 'first', 'champion'],
            '🎯': ['bullseye', 'target', 'goal'],
            '🚀': ['rocket', 'launch', 'fast', 'speed', 'to the moon'],

            // Work & Tech
            '💻': ['laptop', 'computer', 'tech', 'work', 'code'],
            '🖥️': ['desktop', 'computer', 'screen', 'monitor'],
            '📱': ['mobile phone', 'cell', 'iphone', 'android'],
            '⌨️': ['keyboard', 'type'],
            '🖱️': ['mouse', 'click'],
            '📈': ['chart increasing', 'stocks', 'growth', 'up'],
            '📊': ['bar chart', 'stats', 'analytics', 'data'],
            '📋': ['clipboard', 'list', 'tasks'],
            '📁': ['folder', 'files'],
            '📂': ['open folder', 'files'],
            '📑': ['bookmark tabs', 'document'],
            '📄': ['page', 'document', 'file'],
            '📝': ['memo', 'note', 'write', 'pencil'],
            '✉️': ['envelope', 'mail', 'email'],
            '📦': ['package', 'box', 'delivery'],
            '📌': ['pushpin', 'pin', 'pinned'],
            '📎': ['paperclip', 'attachment', 'attach'],
            '⚙️': ['gear', 'settings', 'config'],
            '🛠️': ['tools', 'hammer and wrench', 'repair', 'build'],
            '🔒': ['locked', 'security', 'private'],
            '🔑': ['key', 'password', 'access'],
            '💡': ['light bulb', 'idea', 'bright'],
            '⏰': ['alarm clock', 'time', 'reminder'],
            '⏳': ['hourglass', 'time', 'wait'],
            '☕': ['coffee', 'tea', 'cafe', 'break'],
            '✅': ['check mark', 'tick', 'done', 'success', 'approved'],
            '❌': ['cross mark', 'x', 'cancel', 'failed', 'wrong'],
            '⚠️': ['warning', 'alert', 'caution'],
            '⚡': ['lightning', 'zap', 'fast', 'electricity', 'power']
        },

        /**
         * Checks whether an emoji character matches a search keyword.
         */
        matchesEmojiSearch(emoji, query) {
            if (!query) return true;
            const q = String(query).toLowerCase().trim();
            if (!q) return true;
            if (emoji.includes(q)) return true;
            const kw = this.emojiKeywords[emoji];
            if (Array.isArray(kw)) {
                return kw.some(k => k.toLowerCase().includes(q) || q.includes(k.toLowerCase()));
            }
            return false;
        },

        getMatchingEmojis(category) {
            let list = [];
            if (typeof category === 'string') {
                const found = this.emojiCategoryList?.find(c => c.name === category);
                if (found) list = found.emojis;
                else if (this.emojiCategories && this.emojiCategories[category]) list = this.emojiCategories[category];
            } else if (category && Array.isArray(category.emojis)) {
                list = category.emojis;
            }
            if (!this.mediaSearchQuery) return list;
            return list.filter(e => this.matchesEmojiSearch(e, this.mediaSearchQuery));
        },

        hasAnyEmojiMatches() {
            if (!this.mediaSearchQuery) return true;
            if (Array.isArray(this.emojiCategoryList)) {
                for (const cat of this.emojiCategoryList) {
                    if (cat.emojis.some(e => this.matchesEmojiSearch(e, this.mediaSearchQuery))) {
                        return true;
                    }
                }
            }
            return false;
        },

        getFilteredStickers() {
            if (!this.mediaSearchQuery) return this.stickers;
            const q = this.mediaSearchQuery.toLowerCase().trim();
            return this.stickers.filter(s => {
                const name = (s.name || '').toLowerCase();
                const cat = (s.category || '').toLowerCase();
                return name.includes(q) || cat.includes(q);
            });
        },

        /**
         * Robust Avatar URL Resolver with Fallback to Initials Avatar Data URI
         */
        avatarUrl(url, name = 'User', color = '#4f46e5') {
            if (url && typeof url === 'string' && url.trim() !== '' && !url.includes('null') && !url.includes('undefined')) {
                let clean = url.trim();
                if (!clean.startsWith('http://') && !clean.startsWith('https://') && !clean.startsWith('data:') && !clean.startsWith('/')) {
                    clean = clean.startsWith('storage/') ? '/' + clean : '/storage/' + clean;
                }
                return clean;
            }
            if (window.dgtInitialsAvatar) {
                return window.dgtInitialsAvatar(name || 'User', color || '#4f46e5');
            }
            const cleanName = String(name || 'User')
                .replace(/\s*\([^)]*\)/g, '')
                .replace(/[^\p{L}\p{N}\s@]/gu, ' ')
                .trim()
                .replace(/\s+/g, ' ');
            const parts = cleanName.includes('@') ? [cleanName.split('@')[0]] : cleanName.split(' ').filter(Boolean);
            const initials = (parts.length > 1
                ? `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`
                : (parts[0] || 'U').slice(0, 2)
            ).toUpperCase() || 'U';
            return `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100"><rect width="100" height="100" fill="%234f46e5" rx="50"/><text x="50" y="58" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="40" font-weight="700" fill="%23ffffff" text-anchor="middle" dominant-baseline="middle">${initials}</text></svg>`;
        },

        handleAvatarError(e, name = 'User', color = '#4f46e5') {
            const fallback = this.avatarUrl('', name, color);
            if (e && e.target && e.target.src !== fallback) {
                e.target.onerror = null;
                e.target.src = fallback;
            }
        },

        _persistCacheToStorage(convId, msgs) {
            if (!convId || !Array.isArray(msgs)) return;
            const slice = msgs.slice(-50);
            this._messagesCache[convId] = msgs;
            const payload = JSON.stringify(slice);
            const key = `dgt_chat_cache_${this.currentUser?.id || 0}_${convId}`;
            try { sessionStorage.setItem('dgt_chat_cache_' + convId, payload); } catch (e) {}
            try { localStorage.setItem(key, payload); } catch (e) {}
        },

        _readCacheFromStorage(convId) {
            if (!convId) return null;
            if (this._messagesCache[convId]) return this._messagesCache[convId];
            const key = `dgt_chat_cache_${this.currentUser?.id || 0}_${convId}`;
            try {
                const raw = sessionStorage.getItem('dgt_chat_cache_' + convId) || localStorage.getItem(key);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        this._messagesCache[convId] = parsed;
                        return parsed;
                    }
                }
            } catch (e) {}
            return null;
        },

        /**
         * Initialize Chat Application
         */
        init() {
            this.applyAppearance();

            // Unlock Web Audio Context on first interaction for crystal clear notifications & ringtones
            const unlockHandler = () => {
                this.unlockAudio();
                ['click', 'touchstart', 'keydown'].forEach(evt => window.removeEventListener(evt, unlockHandler));
            };
            ['click', 'touchstart', 'keydown'].forEach(evt => window.addEventListener(evt, unlockHandler, { passive: true }));

            // Hydrate in-memory message cache from server-preloaded initialMessages (0ms initial open)
            if (initialConfig.initialMessages && typeof initialConfig.initialMessages === 'object') {
                for (const [cid, msgs] of Object.entries(initialConfig.initialMessages)) {
                    if (Array.isArray(msgs)) {
                        const normalized = msgs.map(m => {
                            m.user_id = Number(m.user_id);
                            m.is_me = Number(m.user_id) === Number(this.currentUser.id);
                            m.body = m.body || m.content || '';
                            m.content = m.content || m.body || '';
                            return m;
                        });
                        this._persistCacheToStorage(cid, normalized);
                    }
                }
            }

            // Resume active call if exists on load
            if (this.callState.call) {
                this.resumeActiveCall(this.callState.call);
            }

            // Pre-load stickers for instant WhatsApp/Telegram speed (GIFs are loaded on demand when GIF tab is opened)
            this.loadStickers();

            // Mobile visualViewport dynamic height handling (handles on-screen virtual keyboard smoothly without distorting layout)
            const updateMobileViewportHeight = () => {
                const wasMobile = this.isMobile;
                this.isMobile = window.innerWidth < 768;
                if (!wasMobile && this.isMobile && this.activeConversation) {
                    this.mobileView = 'chat';
                }
                const vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                document.documentElement.style.setProperty('--mobile-chat-vh', `${vh}px`);
                if (this.isMobile) {
                    if (window.scrollY > 0) window.scrollTo(0, 0);
                    if (this.activeConversation && !this.isSlidingBack) {
                        const isTextareaFocused = document.activeElement && document.activeElement.id === 'chat-composer-textarea';
                        if (isTextareaFocused) {
                            this.scrollToBottom(false);
                        }
                    }
                }
            };

            window.addEventListener('resize', updateMobileViewportHeight);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', updateMobileViewportHeight);
                window.visualViewport.addEventListener('scroll', updateMobileViewportHeight);
            }
            updateMobileViewportHeight();

            // Telegram-style mobile hardware Back button support (popstate)
            window.addEventListener('popstate', () => {
                if (this.lightbox && this.lightbox.open) {
                    this.closeLightbox();
                    return;
                }
                if (this.profileModal) {
                    this.profileModal = false;
                    return;
                }
                if (this.isMobile && this.activeConversation && this.mobileView === 'chat') {
                    this.backToConversations(true);
                }
            });

            // Pause polling when app/tab is in background, resume instantly on active
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    if (this.activeConversation) this.syncActiveConversation();
                    this.refreshConversationsList();
                }
            });

            // Global paste listener for pasting images / screenshots
            document.addEventListener('paste', (e) => this.handleGlobalPaste(e));

            // Start background delta sync polling (every 2.5 seconds)
            this.startSyncTimer();

            // Set up Pusher/Echo real-time broadcasting if available
            this.setupBroadcasting();

            // Background typing cleanup interval (smoothly expires typing indicator after silence without blinking)
            setInterval(() => this._updateTypingUsers(), 1000);

            // Broadcast initial total unread count on startup
            this.broadcastUnreadUpdate();

            // Warm the message cache for any remaining recent chats so opening any of them is instant
            setTimeout(() => this.prefetchRecentConversations(12), 250);

            // Auto-open requested conversation from query param (e.g. ?conversation_id=ID, ?conv_id=ID, ?conv=ID or ?c=ID).
            // On desktop/tablet (>768px), auto-restore last active conversation so center panel is never blank.
            const urlParams = new URLSearchParams(window.location.search);
            const targetConvId = parseInt(urlParams.get('conversation_id') || urlParams.get('conv_id') || urlParams.get('conv') || urlParams.get('c'));
            if (targetConvId) {
                const targetConv = this.conversations.find(c => Number(c.id) === targetConvId);
                if (targetConv) {
                    this.selectConversation(targetConv);
                } else {
                    // Fetch conversation if not in initial recent list
                    fetch(`/chat/conversations/${targetConvId}`, { credentials: 'same-origin' })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.conversation) {
                                this.conversations.unshift(data.conversation);
                                this.selectConversation(data.conversation);
                            }
                        })
                        .catch(() => {});
                }
            } else if (!this.isMobile && this.conversations && this.conversations.length > 0) {
                const lastConvId = localStorage.getItem('dgt_chat_last_active_conv');
                const found = lastConvId ? this.conversations.find(c => String(c.id) === String(lastConvId)) : null;
                this.selectConversation(found || this.conversations[0]);
            }

            // Prompt for notification permission on user's first interaction
            if (window.Notification && Notification.permission === 'default') {
                const requestPerm = () => {
                    this.requestNotificationPermission();
                };
                document.addEventListener('click', requestPerm, { once: true });
            }

            // Real-time visibility, focus & pageshow listeners: Instant delta sync and mark read
            const triggerImmediateSync = () => {
                if (document.visibilityState === 'visible') {
                    if (this.activeConversation) {
                        this.syncActiveConversation();
                        this.markConversationAsRead(this.activeConversation.id);
                    }
                    this.refreshConversationsList();
                }
            };
            document.addEventListener('visibilitychange', triggerImmediateSync);
            window.addEventListener('focus', triggerImmediateSync);
            window.addEventListener('pageshow', triggerImmediateSync);

            // Watch theme toggle to update default wallpaper between light and dark
            try {
                const themeObserver = new MutationObserver(() => {
                    if (this.userSettings.wallpaper === 'default' && !this.userSettings.wallpaper_image) {
                        this.applyAppearance();
                    }
                });
                themeObserver.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['data-theme', 'class']
                });
            } catch (e) {}
        },

        /**
         * Apply custom user theme, bubble colors and wallpaper live
         */
        applyAppearance() {
            const root = document.documentElement;
            const bubbleColor = this.userSettings.bubble_color || '#4f46e5';
            root.style.setProperty('--chat-bubble-outgoing', bubbleColor);

            const chatContainer = document.getElementById('chat-messages-container');
            if (chatContainer) {
                chatContainer.classList.remove(
                    'chat-wallpaper-default',
                    'chat-wallpaper-mesh',
                    'chat-wallpaper-indigo',
                    'chat-wallpaper-emerald',
                    'chat-wallpaper-doodle',
                    'chat-wallpaper-custom'
                );
                const wp = this.userSettings.wallpaper || 'default';
                chatContainer.classList.add('chat-wallpaper-' + wp);

                // Priority 1: User explicitly specified a custom wallpaper image URL or uploaded photo
                if (this.userSettings.wallpaper_image && this.userSettings.wallpaper_image.trim()) {
                    chatContainer.style.backgroundImage = `url('${this.userSettings.wallpaper_image.trim()}')`;
                    chatContainer.style.backgroundSize = 'cover';
                    chatContainer.style.backgroundPosition = 'center';
                    chatContainer.style.backgroundRepeat = 'no-repeat';
                } else if (wp === 'default') {
                    // Priority 2: Default Telegram wallpaper based on light / dark mode
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark' ||
                                   document.documentElement.getAttribute('data-theme') === 'neon' ||
                                   document.documentElement.classList.contains('dark') ||
                                   document.body.classList.contains('dark');
                    const defaultBg = isDark
                        ? 'https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-dark.webp'
                        : 'https://img.miniexcavator.org/ebay/Dashboard-Icon/chatbg-light.webp';
                    chatContainer.style.backgroundImage = `url('${defaultBg}')`;
                    chatContainer.style.backgroundSize = 'cover';
                    chatContainer.style.backgroundPosition = 'center';
                    chatContainer.style.backgroundRepeat = 'no-repeat';
                } else {
                    chatContainer.style.backgroundImage = '';
                    chatContainer.style.backgroundSize = '';
                    chatContainer.style.backgroundPosition = '';
                    chatContainer.style.backgroundRepeat = '';
                }
            }
        },

        /**
         * Apply custom wallpaper by URL
         */
        applyCustomWallpaperUrl(url) {
            const cleanUrl = (url || this.customWallpaperUrlInput || '').trim();
            if (!cleanUrl) {
                this.showToast('Please enter an image URL', 'error');
                return;
            }
            this.customWallpaperUrlInput = cleanUrl;
            this.userSettings.wallpaper = 'custom';
            this.userSettings.wallpaper_image = cleanUrl;
            this.applyAppearance();
            this.saveSettings();
            this.showToast('Background image updated!', 'success');
        },

        /**
         * Reset wallpaper to default (automatic light/dark)
         */
        resetToDefaultWallpaper() {
            this.customWallpaperUrlInput = '';
            this.userSettings.wallpaper = 'default';
            this.userSettings.wallpaper_image = '';
            this.applyAppearance();
            this.saveSettings();
            this.showToast('Reset to default background!', 'success');
        },

        /**
         * Handle custom wallpaper upload from disk
         */
        handleWallpaperUpload(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (evt) => {
                this.userSettings.wallpaper = 'custom';
                this.userSettings.wallpaper_image = evt.target.result;
                this.customWallpaperUrlInput = evt.target.result;
                this.applyAppearance();
                this.saveSettings();
                this.showToast('Custom wallpaper applied!', 'success');
            };
            reader.readAsDataURL(file);
        },

        /**
         * Filtered list of conversations
         */
        get filteredConversations() {
            let list = this.conversations;

            // Search filter
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase().trim();
                list = list.filter(c => {
                    const titleMatch = (c.title || '').toLowerCase().includes(q);
                    const realNameMatch = (c.real_name || '').toLowerCase().includes(q);
                    const nicknameMatch = (c.nickname || '').toLowerCase().includes(q);
                    const roleMatch = (c.team_role || '').toLowerCase().includes(q);
                    const lastMsgMatch = (c.last_message?.content || c.last_message?.body || '').toLowerCase().includes(q);
                    const memberMatch = (c.members || []).some(m => (m.user?.name || '').toLowerCase().includes(q));
                    return titleMatch || realNameMatch || nicknameMatch || roleMatch || lastMsgMatch || memberMatch;
                });
            }

            // Tab filter
            switch (this.activeTab) {
                case 'direct':
                    // "direct show only the people I chat with"
                    // Must be 1-on-1 private chat, unarchived, non-virtual, and has actual chat message history
                    list = list.filter(c => 
                        (c.type === 'direct' || c.type === 'private') && 
                        !c.is_archived && 
                        !c.is_virtual && 
                        !!(c.last_message && (c.last_message.id || c.last_message.content || c.last_message.body))
                    );
                    break;
                case 'all':
                    // "all must show all members but user our people we chat with on top"
                    // Show all active conversations (never archived)
                    list = list.filter(c => !c.is_archived);
                    break;
                case 'unread':
                    list = list.filter(c => (c.unread_count || 0) > 0 && !c.is_archived);
                    break;
                case 'groups':
                    list = list.filter(c => c.type === 'group' && !c.is_archived);
                    break;
                case 'pinned':
                    list = list.filter(c => c.is_pinned && !c.is_archived);
                    break;
                case 'archived':
                    list = list.filter(c => c.is_archived);
                    break;
                default:
                    list = list.filter(c => !c.is_archived);
                    break;
            }

            // In 'All' tab (or when searching): ensure every single colleague from usersDirectory
            // is listed so users can find and message anyone, while active chats stay on top.
            // NEVER include archived contacts or users already represented.
            if (this.activeTab === 'all' || (this.searchQuery && this.searchQuery.trim())) {
                const knownUserIds = new Set(
                    list
                        .filter(c => (c.type === 'direct' || c.type === 'private') && c.other_user_id)
                        .map(c => Number(c.other_user_id))
                );

                const missing = (this.usersDirectory || []).filter(u => 
                    Number(u.id) !== Number(this.currentUser?.id) && !knownUserIds.has(Number(u.id))
                );

                if (missing.length > 0) {
                    const extra = missing.map(u => ({
                        id: 'member-' + u.id,
                        is_virtual: true,
                        type: 'direct',
                        title: u.name,
                        display_name: u.name,
                        real_name: u.name,
                        team_role: u.team_role || 'Team Member',
                        avatar: u.avatar_url,
                        avatar_url: u.avatar_url,
                        other_user_id: u.id,
                        is_online: !!u.is_online,
                        last_seen: u.last_seen || 'Offline',
                        is_pinned: false,
                        is_archived: false,
                        unread_count: 0,
                        last_message: null,
                        last_message_at: null,
                    }));
                    list = [...list, ...extra];
                }
            }

            // Sort (matches Telegram & WhatsApp UX):
            // 1. Pinned conversations always on top
            // 2. Conversations with messages (people we chat with) on top, sorted newest message first
            // 3. Colleague contacts without messages below, stably sorted alphabetically by name
            // Note: Someone typing in row 2 DOES NOT move to top; order changes ONLY when a message is sent/received!
            return [...list].sort((a, b) => {
                if (a.is_pinned !== b.is_pinned) {
                    return a.is_pinned ? -1 : 1;
                }
                const hasMsgA = !!(a.last_message && (a.last_message.id || a.last_message.content || a.last_message.body));
                const hasMsgB = !!(b.last_message && (b.last_message.id || b.last_message.content || b.last_message.body));
                if (hasMsgA !== hasMsgB) {
                    return hasMsgA ? -1 : 1;
                }
                if (hasMsgA && hasMsgB) {
                    const dateA = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
                    const dateB = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
                    if (dateB !== dateA) {
                        return dateB - dateA;
                    }
                    return (a.title || '').localeCompare(b.title || '');
                }
                // Stable alphabetical sorting for colleagues without messages yet
                return (a.title || '').localeCompare(b.title || '');
            });
        },

        /**
         * Matching team members for global chat search
         */
        get matchingSearchUsers() {
            if (!this.searchQuery || !this.searchQuery.trim()) return [];
            const q = this.searchQuery.toLowerCase().trim();
            return this.usersDirectory.filter(u => 
                (u.name || '').toLowerCase().includes(q) || 
                (u.team_role || '').toLowerCase().includes(q)
            );
        },

        /**
         * Set shared media payload from API or manual assignment
         */
        set sharedMedia(val) {
            if (val && typeof val === 'object' && !Array.isArray(val)) {
                this.sharedMediaData = {
                    media: val.media || [],
                    docs: val.docs || [],
                    links: val.links || [],
                    counts: val.counts || {
                        media: (val.media || []).length,
                        docs: (val.docs || []).length,
                        links: (val.links || []).length,
                        total: (val.media || []).length + (val.docs || []).length + (val.links || []).length
                    }
                };
            }
        },

        /**
         * Determine if an attachment is an image (all formats: PNG, JPG, JPEG, WEBP, GIF, SVG, BMP, ICO, AVIF, HEIC, HEIF, TIFF)
         */
        isImageAttachment(att) {
            if (!att) return false;
            if (typeof att === 'string') {
                return /\.(png|jpe?g|gif|webp|svg|bmp|ico|tiff?|avif|heic|heif)(\?.*)?$/i.test(att);
            }
            if (att.type === 'image' || att.type === 'sticker' || att.type === 'gif') return true;
            const mime = (att.file_type || '').toLowerCase();
            if (mime.startsWith('image/')) return true;
            const name = (att.file_name || att.file_path || att.url || att.file_url || '').toLowerCase();
            return /\.(png|jpe?g|gif|webp|svg|bmp|ico|tiff?|avif|heic|heif)(\?.*)?$/i.test(name);
        },

        /**
         * Determine if an attachment is a voice/audio message
         */
        isVoiceAttachment(att, msg = null) {
            if (!att) return false;
            if (msg && (msg.type === 'voice' || msg.is_voice)) return true;
            if (att.waveform_data) return true;
            const name = (att.file_name || att.file_path || '').toLowerCase();
            if (name.startsWith('voice_') || name.startsWith('audio_') || name.startsWith('recording')) return true;
            const mime = (att.file_type || '').toLowerCase();
            if (mime.startsWith('audio/')) return true;
            if (/\.(ogg|oga|opus|mp3|wav|m4a|aac|flac)$/i.test(name)) return true;
            if (/\.webm$/i.test(name) && (mime.includes('audio') || name.includes('voice') || name.includes('audio') || (msg && msg.type === 'voice'))) return true;
            return false;
        },

        /**
         * Determine if an attachment is a video
         */
        isVideoAttachment(att, msg = null) {
            if (!att) return false;
            if (msg && (msg.type === 'voice' || msg.is_voice)) return false;
            if (this.isVoiceAttachment(att, msg)) return false;
            if (att.type === 'video') return true;
            const mime = (att.file_type || '').toLowerCase();
            if (mime.startsWith('video/')) return true;
            const name = (att.file_name || att.file_path || att.url || att.file_url || '').toLowerCase();
            return /\.(mp4|webm|mov|mkv|avi|m4v)(\?.*)?$/i.test(name);
        },

        /**
         * Determine if an attachment is a document or other file
         */
        isDocAttachment(att, msg = null) {
            if (!att) return false;
            if (msg && (msg.type === 'voice' || msg.is_voice || this.isVoiceOnly(msg))) return false;
            return !this.isImageAttachment(att) && !this.isVideoAttachment(att, msg) && !this.isVoiceAttachment(att, msg);
        },

        /**
         * Determine if a message is a standalone voice message (Messenger / WhatsApp style single pill)
         */
        isVoiceOnly(msg) {
            if (!msg || msg.is_unsent) return false;
            if (msg.type === 'voice') return true;
            const attachments = msg.attachments || [];
            const hasVoice = attachments.some(a => this.isVoiceAttachment(a, msg));
            if (!hasVoice) return false;
            const body = (msg.body || msg.content || '').trim();
            const hasText = body.length > 0 && body !== '🎤 Voice Message' && body !== 'Voice Message';
            const hasOtherAtt = attachments.some(a => !this.isVoiceAttachment(a, msg));
            return !hasText && !hasOtherAtt;
        },

        /**
         * All media (images and videos) shared in current conversation
         */
        get sharedMedia() {
            let list = [];
            if (this.sharedMediaData && Array.isArray(this.sharedMediaData.media) && this.sharedMediaData.media.length > 0) {
                list = [...this.sharedMediaData.media];
            } else {
                list = this.messages.flatMap(m => (m.attachments || []).map(a => ({
                    ...a,
                    sender_name: m.user?.name || 'User',
                    time: m.created_at,
                    message_id: m.id
                }))).filter(a => this.isImageAttachment(a) || this.isVideoAttachment(a));
            }
            // Attach backwards-compatible properties so .media, .docs, .counts.total never fail on this object
            list.media = this.sharedMediaData?.media || list;
            list.docs = this.sharedMediaData?.docs || this.sharedFiles;
            list.links = this.sharedMediaData?.links || this.sharedLinks;
            list.counts = this.sharedMediaData?.counts || {
                media: list.length,
                docs: (this.sharedMediaData?.docs || []).length,
                links: (this.sharedMediaData?.links || []).length,
                total: list.length + (this.sharedMediaData?.docs || []).length + (this.sharedMediaData?.links || []).length
            };
            return list;
        },

        /**
         * All documents and files shared in current conversation
         */
        get sharedFiles() {
            if (this.sharedMediaData && Array.isArray(this.sharedMediaData.docs) && this.sharedMediaData.docs.length > 0) {
                return this.sharedMediaData.docs;
            }
            return this.messages.flatMap(m => (m.attachments || []).map(a => ({
                ...a,
                sender_name: m.user?.name || 'User',
                time: m.created_at,
                message_id: m.id
            }))).filter(a => this.isDocAttachment(a));
        },

        /**
         * Safely escape HTML and format rich text:
         * - Multi-line code blocks ```lang\ncode\n``` with "Copy Code" button and syntax styling
         * - Inline code `code`
         * - WhatsApp-style text formatting: *bold*, _italic_, ~strikethrough~
         * - Highlighted & clickable URLs (https://, http://, www.)
         */
        formatMessageHtml(text, isMine = false) {
            if (!text) return '';
            const raw = String(text);
            const esc = (str) => String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const tokens = [];
            const makeToken = (html) => {
                const key = `___DGT_TOK_${tokens.length}_${Math.random().toString(36).substring(2, 7)}___`;
                tokens.push({ key, html });
                return key;
            };

            // 1. Multi-line code blocks ```lang\ncode\n``` or ```code```
            let processed = raw.replace(/```(?:([a-zA-Z0-9_\-\.\+#]+)\r?\n|\r?\n)?([\s\S]*?)```/g, (match, lang, code) => {
                const codeContent = code !== undefined ? code : '';
                const langName = (lang || 'code').trim();
                const langDisplay = (langName || 'CODE').toUpperCase();

                let b64 = '';
                try {
                    b64 = btoa(unescape(encodeURIComponent(codeContent)));
                } catch (e) {
                    b64 = '';
                }

                const copyBtn = `
                    <button type="button"
                            onclick="event.stopPropagation(); window.copyChatMessageCode(this, '${b64}')"
                            class="px-2 py-0.5 rounded-md text-[10px] font-sans font-semibold tracking-wide text-slate-300 hover:text-white bg-slate-800/90 hover:bg-slate-700/90 border border-slate-700/60 transition-colors flex items-center gap-1.5 cursor-pointer shadow-xs select-none">
                        <svg class="w-3 h-3 text-slate-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                        </svg>
                        <span>Copy</span>
                    </button>
                `;

                const codeBlockHtml = `
                    <div class="dgt-code-card my-2 rounded-xl overflow-hidden border border-slate-800 bg-[#0d1117] text-slate-100 shadow-md font-mono text-xs select-text">
                        <div class="flex items-center justify-between px-3 py-1.5 bg-[#161b22] border-b border-slate-800/80 text-[11px] text-slate-400 select-none">
                            <span class="font-bold tracking-wider text-indigo-400 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400/80"></span>
                                ${esc(langDisplay)}
                            </span>
                            ${copyBtn}
                        </div>
                        <pre class="p-3 overflow-x-auto whitespace-pre font-mono text-[12px] leading-relaxed text-emerald-400 dark:text-emerald-300 selection:bg-indigo-600 selection:text-white"><code>${esc(codeContent.replace(/^\n+|\n+$/g, ''))}</code></pre>
                    </div>
                `;
                return makeToken(codeBlockHtml);
            });

            // 2. Inline code `code`
            processed = processed.replace(/`([^`\r\n]+)`/g, (match, inlineCode) => {
                const inlineClass = isMine
                    ? 'px-1.5 py-0.5 rounded-md font-mono text-[12px] bg-black/35 text-amber-200 border border-white/20 select-text'
                    : 'px-1.5 py-0.5 rounded-md font-mono text-[12px] bg-slate-200/80 dark:bg-slate-900/80 text-pink-600 dark:text-pink-400 border border-slate-300/40 dark:border-slate-700/50 select-text';
                return makeToken(`<code class="${inlineClass}">${esc(inlineCode)}</code>`);
            });

            // 3. Highlighted & clickable URLs
            const urlRegex = /(https?:\/\/[^\s<>"]+|www\.[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+(?:\/[^\s<>"]*)?)/gi;
            const linkClass = isMine
                ? 'underline decoration-2 underline-offset-2 font-semibold text-cyan-200 hover:text-white break-all transition-colors cursor-pointer'
                : 'underline decoration-2 underline-offset-2 font-semibold text-indigo-600 dark:text-sky-400 hover:text-indigo-500 dark:hover:text-sky-300 break-all transition-colors cursor-pointer';

            processed = processed.replace(urlRegex, (matchedUrl) => {
                let trailing = '';
                const trailMatch = matchedUrl.match(/([.,!?;:)\]}]+)$/);
                if (trailMatch) {
                    trailing = trailMatch[1];
                    matchedUrl = matchedUrl.slice(0, -trailing.length);
                }
                const href = /^https?:\/\//i.test(matchedUrl) ? matchedUrl : ('https://' + matchedUrl);
                const linkHtml = `<a href="${esc(href)}" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()" class="${linkClass}">${esc(matchedUrl)}</a>${esc(trailing)}`;
                return makeToken(linkHtml);
            });

            // 4. Safely escape remaining raw text
            processed = esc(processed);

            // 5. WhatsApp / Markdown Rich Text Formatting (*bold*, _italic_, ~strike~)
            processed = processed
                .replace(/(^|[^\w*])\*([^\s*](?:.*?[^\s*])?)\*(?=[^\w*]|$)/g, '$1<strong>$2</strong>')
                .replace(/(^|[^\w_])_([^\s_](?:.*?[^\s_])?)_(?=[^\w_]|$)/g, '$1<em>$2</em>')
                .replace(/(^|[^\w~])~([^\s~](?:.*?[^\s~])?)~(?=[^\w~]|$)/g, '$1<del class="line-through opacity-80">$2</del>');

            // 6. Restore preserved tokens (code blocks, inline code, links)
            tokens.forEach(({ key, html }) => {
                processed = processed.split(key).join(html);
            });

            return processed;
        },

        /**
         * All URLs/links shared in current conversation
         */
        get sharedLinks() {
            if (this.sharedMediaData && Array.isArray(this.sharedMediaData.links) && this.sharedMediaData.links.length > 0) {
                return this.sharedMediaData.links.map(l => {
                    let domain = l.domain || '';
                    if (!domain && l.url) {
                        try {
                            domain = new URL(/^https?:\/\//i.test(l.url) ? l.url : ('https://' + l.url)).hostname.replace(/^www\./, '');
                        } catch(e) {
                            domain = 'link';
                        }
                    }
                    return {
                        ...l,
                        domain: domain || 'link',
                        time: l.created_at || l.time,
                        sender_name: l.sender_name || 'Member'
                    };
                });
            }
            const urlRegex = /(https?:\/\/[^\s<>"]+|www\.[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)+(?:\/[^\s<>"]*)?)/gi;
            const links = [];
            this.messages.forEach(m => {
                const text = m.body || m.content;
                if (!text) return;
                const matches = text.match(urlRegex);
                if (matches) {
                    matches.forEach(rawUrl => {
                        const clean = rawUrl.replace(/[.,!?;:)\]}]+$/, '');
                        const url = /^https?:\/\//i.test(clean) ? clean : ('https://' + clean);
                        let domain = '';
                        try {
                            domain = new URL(url).hostname.replace('www.', '');
                        } catch(e) {
                            domain = clean;
                        }
                        links.push({
                            url,
                            domain,
                            sender_name: m.user?.name || 'User',
                            time: m.created_at,
                            message_body: text
                        });
                    });
                }
            });
            return links;
        },

        /**
         * Total media, docs, and links count
         */
        get totalSharedMediaCount() {
            if (this.sharedMediaData?.counts?.total != null) {
                return this.sharedMediaData.counts.total;
            }
            return (this.sharedMedia.length + this.sharedFiles.length + this.sharedLinks.length);
        },

        /**
         * Total unread messages count across all active conversations
         */
        get totalUnreadCount() {
            return this.conversations
                .filter(c => !c.is_archived)
                .reduce((sum, c) => sum + (Number(c.unread_count) || 0), 0);
        },

        /**
         * Select and open a conversation
         */
        async selectConversation(conv) {
            if (!conv) return;

            // If virtual card (team member without DB conversation record yet), start private chat immediately
            if (conv.is_virtual && conv.other_user_id) {
                return this.startPrivateChat(conv.other_user_id);
            }

            this.isSlidingBack = false;
            this.isSlidingIn = true;
            this._lastConvOpenAt = Date.now();
            this._forceBottomUntil = Date.now() + 3000;
            const hadActive = !!this.activeConversation;
            this.activeConversation = conv;
            this.mobileView = 'chat';
            this.showScrollBottomBtn = false;
            if (this.isMobile && !hadActive) {
                try { history.pushState({ dgtChatMobileOpen: conv.id }, ''); } catch (e) {}
            }
            if (conv.id && !conv.is_virtual) {
                try { localStorage.setItem('dgt_chat_last_active_conv', String(conv.id)); } catch (e) {}
            }
            setTimeout(() => { this.isSlidingIn = false; }, 300);

            this.replyingTo = null;
            this.editingMessage = null;
            this.searchInChat = '';
            this.showSearchInChat = false;
            this.typingUsers = [];

            // Mark unread as 0 locally
            const convItem = this.conversations.find(c => c.id === conv.id);
            if (convItem && convItem.unread_count > 0) {
                convItem.unread_count = 0;
                this.broadcastUnreadUpdate();
            }

            // 1. Instant 0ms Cache Hydration: Retrieve cached messages from in-memory cache or browser storage
            let cached = this._readCacheFromStorage(conv.id);
            const hasNoMessagesYet = !conv.last_message;

            if (Array.isArray(cached) || hasNoMessagesYet) {
                // Instant render from cache (or known empty chat)! No blank screen, no spinner delay
                const list = Array.isArray(cached) ? cached : [];
                this.messages = list;
                this.isLoadingMessages = false;
                this.lastSyncId = list[list.length - 1]?.id || 0;
                this.$nextTick(() => {
                    const textarea = document.getElementById('chat-composer-textarea');
                    if (!this.isMobile && textarea) textarea.focus();
                    this.scrollToBottom(true);
                });
                // Silent background revalidate (also marks read on the backend)
                this.loadMessages(conv.id, true);
            } else {
                // Cold load: Show skeleton spinner only if cache is completely empty and chat has messages
                this.messages = [];
                this.lastSyncId = 0;
                await this.loadMessages(conv.id, false);
            }

            // Setup real-time Pusher channel listener for this conversation
            this.subscribeConversationBroadcasting(conv.id);

            // Focus message input (desktop only, to avoid unwanted mobile keyboard popup)
            this.$nextTick(() => {
                const textarea = document.getElementById('chat-composer-textarea');
                if (!this.isMobile && textarea) textarea.focus();
                this.scrollToBottom(true);
            });
        },

        /**
         * Real Application Slide Back to Conversations List
         */
        backToConversations(fromPopState = false) {
            if (!this.activeConversation) return;

            this.showScrollBottomBtn = false;
            this.closeContextMenu();
            if (this.isMobile) {
                this.isSlidingBack = true;
                setTimeout(() => {
                    this.mobileView = 'list';
                    this.activeConversation = null;
                    this.isSlidingBack = false;
                    this.messages = [];
                }, 260);
            } else {
                this.activeConversation = null;
                this.mobileView = 'list';
                this.messages = [];
            }
        },

        handleMessagesScroll(e) {
            const el = (e && e.target) ? e.target : document.getElementById('chat-messages-container');
            if (!el) return;
            const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
            this.showScrollBottomBtn = distanceFromBottom > 180;
        },

        /**
         * Load full message history for active conversation (with Stale-While-Revalidate support)
         */
        async loadMessages(conversationId, isBackground = false) {
            if (!isBackground) {
                this.isLoadingMessages = true;
            }
            try {
                const res = await fetch(`/chat/conversations/${conversationId}/messages`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success) {
                    const fetched = (data.messages || []).map(m => {
                        m.user_id = Number(m.user_id);
                        m.is_me = Number(m.user_id) === Number(this.currentUser.id);
                        m.body = m.body || m.content || '';
                        m.content = m.content || m.body || '';
                        return m;
                    });

                    // Store in memory cache & persist latest 50 messages to sessionStorage + localStorage
                    this._persistCacheToStorage(conversationId, fetched);

                    // Apply messages if user is still viewing this conversation
                    if (this.activeConversation && Number(this.activeConversation.id) === Number(conversationId)) {
                        const msgContainer = document.getElementById('chat-messages-container');
                        const wasNearBottom = !msgContainer || (msgContainer.scrollHeight - msgContainer.scrollTop - msgContainer.clientHeight < 350);
                        const wasRecentlyOpened = (Date.now() - (this._lastConvOpenAt || 0)) < 5000;
                        const prevLastId = this.messages[this.messages.length - 1]?.id || 0;
                        const fetchedTempIds = new Set(fetched.map(m => m.client_temp_id).filter(Boolean));
                        const fetchedIds = new Set(fetched.map(m => Number(m.id)).filter(Boolean));
                        const pendingOptimistic = (this.messages || []).filter(m =>
                            (String(m.id).startsWith('temp_') || m.status === 'sending') &&
                            !fetchedTempIds.has(m.client_temp_id || m.id) &&
                            !fetchedIds.has(Number(m.id))
                        );
                        this.messages = [...fetched, ...pendingOptimistic];
                        this._deduplicateMessages();
                        const newLastId = this.messages[this.messages.length - 1]?.id || 0;
                        if (newLastId && typeof newLastId === 'number') {
                            this.lastSyncId = newLastId;
                        }
                        if (!isBackground || newLastId !== prevLastId || wasRecentlyOpened || wasNearBottom) {
                            this.$nextTick(() => this.scrollToBottom(true));
                        }
                    }

                    if (data.conversation && this.activeConversation) {
                        if (data.conversation.is_online !== undefined) {
                            this.activeConversation.is_online = !!data.conversation.is_online;
                        }
                        if (data.conversation.last_seen !== undefined) {
                            this.activeConversation.last_seen = data.conversation.last_seen;
                        }
                        if (data.conversation.type) {
                            this.activeConversation.type = data.conversation.type;
                        }
                        const convItem = this.conversations.find(c => c.id === conversationId);
                        if (convItem) {
                            if (data.conversation.is_online !== undefined) convItem.is_online = !!data.conversation.is_online;
                            if (data.conversation.last_seen !== undefined) convItem.last_seen = data.conversation.last_seen;
                        }
                    }
                }
            } catch (err) {
                console.error('Failed to load messages:', err);
            } finally {
                if (!isBackground || (this.activeConversation && Number(this.activeConversation.id) === Number(conversationId))) {
                    this.isLoadingMessages = false;
                }
            }
        },

        /**
         * Silent background prefetch of a conversation's recent messages.
         * Never touches the UI and never marks anything as read (server skips read-marking for ?prefetch=1),
         * so opening the chat later is instant.
         */
        async prefetchMessages(conversationId) {
            if (!conversationId || String(conversationId).startsWith('member-') || String(conversationId).startsWith('temp')) return;
            if (this._messagesCache[conversationId] || (this._prefetching && this._prefetching[conversationId])) return;
            if (!this._prefetching) this._prefetching = {};
            this._prefetching[conversationId] = true;
            try {
                const res = await fetch(`/chat/conversations/${conversationId}/messages?prefetch=1`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success && !this._messagesCache[conversationId]) {
                    const fetched = (data.messages || []).map(m => {
                        m.user_id = Number(m.user_id);
                        m.is_me = Number(m.user_id) === Number(this.currentUser.id);
                        m.body = m.body || m.content || '';
                        m.content = m.content || m.body || '';
                        return m;
                    });
                    this._persistCacheToStorage(conversationId, fetched);
                }
            } catch (e) {
                // silent
            } finally {
                this._prefetching[conversationId] = false;
            }
        },

        /**
         * Warm the cache for the most recent conversations right after the page is ready
         */
        prefetchRecentConversations(limit = 12) {
            const ids = (this.conversations || [])
                .filter(c => c && c.id && !c.is_virtual && c.last_message && !this._messagesCache[c.id])
                .slice(0, limit)
                .map(c => c.id);
            ids.forEach((id, i) => setTimeout(() => this.prefetchMessages(id), 150 + i * 200));
        },

        /**
         * Delta sync: polls backend for new messages since lastSyncId
         */
        async syncActiveConversation() {
            if (document.hidden) return;
            if (!this.activeConversation) {
                // If no active conversation, poll conversations list periodically for unread counts
                this.refreshConversationsList();
                return;
            }

            try {
                const res = await fetch(`/chat/conversations/${this.activeConversation.id}/sync?after_id=${this.lastSyncId}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();

                if (data.success) {
                    // 1. Update partner online and last seen status in real time
                    if (this.activeConversation && data.partner_last_seen !== undefined) {
                        this.activeConversation.is_online = !!data.partner_is_online;
                        this.activeConversation.last_seen = data.partner_last_seen;
                        const convItem = this.conversations.find(c => c.id === this.activeConversation.id);
                        if (convItem) {
                            convItem.is_online = !!data.partner_is_online;
                            convItem.last_seen = data.partner_last_seen;
                        }
                    }

                    // 2. Update seen read receipts for outgoing messages in real time (changes from 1 tick to 2 blue ticks)
                    if (data.partner_last_read_id) {
                        const targetReadId = Number(data.partner_last_read_id);
                        let readUpdated = false;
                        this.messages.forEach(m => {
                            if (Number(m.user_id) === Number(this.currentUser.id) && Number(m.id) <= targetReadId) {
                                if (!m.is_seen || m.status !== 'read') {
                                    m.status = 'read';
                                    m.is_seen = true;
                                    readUpdated = true;
                                }
                            }
                        });
                        if (readUpdated) {
                            this.messages = [...this.messages];
                        }
                        if (this.activeConversation && this.activeConversation.last_message && Number(this.activeConversation.last_message.user_id) === Number(this.currentUser.id)) {
                            if (Number(this.activeConversation.last_message.id) <= targetReadId) {
                                this.activeConversation.last_message.status = 'read';
                                this.activeConversation.last_message.is_seen = true;
                            }
                        }
                    }

                    // 3. New incoming messages
                    const newMessages = data.new_messages || data.messages || [];
                    if (newMessages.length > 0) {
                        newMessages.forEach(msg => {
                            msg.user_id = Number(msg.user_id);
                            msg.is_me = Number(msg.user_id) === Number(this.currentUser.id);
                            msg.body = msg.body || msg.content || '';
                            msg.content = msg.content || msg.body || '';

                            // If sender was marked typing, clear immediately on message arrival
                            const senderName = msg.sender_name || msg.user?.name;
                            if (senderName && this._typingMap) {
                                delete this._typingMap[senderName];
                            }

                            // Match existing message:
                            // 1) exact ID
                            // 2) matching client_temp_id
                            // 3) if from current user:
                            //    - if voice message, match pending temporary voice note
                            //    - if text message, match temporary placeholder with same text
                            let existingIndex = this.messages.findIndex(m => m.id === msg.id || (m.client_temp_id && msg.client_temp_id && m.client_temp_id === msg.client_temp_id));
                            if (existingIndex === -1 && Number(msg.user_id) === Number(this.currentUser.id)) {
                                if (msg.type === 'voice' || msg.is_voice || (msg.attachments && msg.attachments.some(a => this.isVoiceAttachment(a, msg)))) {
                                    existingIndex = this.messages.findIndex(m => (m.type === 'voice' || m.is_voice || (m.attachments && m.attachments.some(a => this.isVoiceAttachment(a, m)))) && (String(m.id).startsWith('temp_') || m.status === 'sending'));
                                } else {
                                    existingIndex = this.messages.findIndex(m => (m.client_temp_id || String(m.id).startsWith('temp_')) && (m.body === (msg.body || msg.content)));
                                }
                            }

                            if (existingIndex !== -1) {
                                const oldMsg = this.messages[existingIndex];
                                msg.client_temp_id = oldMsg.client_temp_id || msg.client_temp_id;
                                if (this.voicePlayer && this.voicePlayer.currentMsgId === oldMsg.id) {
                                    this.voicePlayer.currentMsgId = msg.id;
                                }
                                if (msg.attachments && msg.attachments[0] && oldMsg && oldMsg.attachments && oldMsg.attachments[0]) {
                                    if (!msg.attachments[0].waveform_data || msg.attachments[0].waveform_data.length === 0) {
                                        msg.attachments[0].waveform_data = oldMsg.attachments[0].waveform_data;
                                    }
                                    if (!msg.attachments[0].duration_seconds) {
                                        msg.attachments[0].duration_seconds = oldMsg.attachments[0].duration_seconds;
                                    }
                                }
                                this.messages.splice(existingIndex, 1, msg);
                            } else {
                                this.messages.push(msg);
                                if (Number(msg.user_id) !== Number(this.currentUser.id)) {
                                    this.triggerDeviceNotification(msg);
                                }
                            }
                            if (msg.id > this.lastSyncId) {
                                this.lastSyncId = msg.id;
                            }
                        });

                        this._deduplicateMessages();
                        // Ensure Alpine reactivity triggers on message array change
                        this.messages = [...this.messages];
                        this._updateTypingUsers();

                        const lastMsg = newMessages[newMessages.length - 1];
                        this.updateConversationLastMessage(this.activeConversation.id, lastMsg);
                        this.markConversationAsRead(this.activeConversation.id);
                        this.$nextTick(() => this.scrollToBottom(false));
                    }

                    // 4. Update typing indicator without wiping Pusher state
                    if (Array.isArray(data.typing_users) && data.typing_users.length > 0) {
                        data.typing_users.forEach(name => {
                            this._typingMap[name] = Date.now() + 4500;
                        });
                        this._updateTypingUsers();
                    }
                }
            } catch (err) {
                // Network glitch or offline; quietly skip
            }
        },

        /**
         * Update conversation list when new message arrives or unread changes.
         * Bumps conversation to top ONLY when a genuinely new message is sent or received (matching Telegram / WhatsApp UX).
         * Typing indicators and read updates NEVER move the conversation.
         */
        updateConversationLastMessage(convId, message) {
            const index = this.conversations.findIndex(c => c.id === convId);
            if (index !== -1) {
                const conv = this.conversations[index];
                const isMe = (Number(message.user_id) === Number(this.currentUser.id));

                const prevMsgId = conv.last_message?.id ? Number(conv.last_message.id) : 0;
                const newMsgId = message.id ? Number(message.id) : 0;
                const isOptimistic = typeof message.id === 'string' && message.id.startsWith('temp_');
                // Genuinely newer if it's an optimistic sending message, or has a higher ID, or no previous message
                const isNewer = isOptimistic || (!prevMsgId && newMsgId > 0) || (newMsgId > 0 && newMsgId > prevMsgId);

                conv.last_message = {
                    id: message.id,
                    user_id: message.user_id,
                    content: message.content || message.body,
                    body: message.body || message.content,
                    sender_name: isMe ? 'You' : (message.user?.name || message.sender_name || 'User'),
                    time: message.formatted_time || (new Date(message.created_at || Date.now()).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })),
                    is_me: isMe,
                    is_seen: message.is_seen || message.status === 'read',
                    status: message.status || (isMe ? 'sent' : 'delivered'),
                    attachments: message.attachments || []
                };

                if (isNewer && message.created_at) {
                    conv.last_message_at = message.created_at;
                } else if (!conv.last_message_at) {
                    conv.last_message_at = message.created_at || new Date().toISOString();
                }

                // Telegram / WhatsApp UX:
                // Move conversation to top ONLY when a genuinely NEW message is sent or received!
                // Typing status or reading status stays in place without jumping.
                if (isNewer && index > 0 && !conv.is_pinned) {
                    this.conversations.splice(index, 1);
                    const firstUnpinnedIndex = this.conversations.findIndex(c => !c.is_pinned);
                    if (firstUnpinnedIndex !== -1) {
                        this.conversations.splice(firstUnpinnedIndex, 0, conv);
                    } else {
                        this.conversations.unshift(conv);
                    }
                    this.conversations = [...this.conversations];
                }

                // Update cache
                if (this._messagesCache[convId] && message && message.id) {
                    const cIdx = this._messagesCache[convId].findIndex(m => m.id === message.id || (m.client_temp_id && message.client_temp_id && m.client_temp_id === message.client_temp_id));
                    if (cIdx !== -1) {
                        this._messagesCache[convId][cIdx] = message;
                    } else if (!this._messagesCache[convId].some(m => m.id === message.id)) {
                        this._messagesCache[convId].push(message);
                    }
                    this._persistCacheToStorage(convId, this._messagesCache[convId]);
                }
            }
        },

        /**
         * Refresh conversation list in background
         */
        async refreshConversationsList() {
            if (document.hidden) return;
            try {
                const res = await fetch('/chat/conversations', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success && data.conversations) {
                    this.conversations = data.conversations;
                    this.broadcastUnreadUpdate();
                    try {
                        sessionStorage.setItem('dgt_chat_convs_cache', JSON.stringify(this.conversations));
                    } catch (e) {}
                }
            } catch (e) {}
        },

        /**
         * Adaptive background polling timer
         * When Pusher WebSocket is live, polls gently every 12s/18s without blocking.
         * When WebSocket drops, tightens to 3.5s failover until Pusher reconnects.
         */
        startSyncTimer() {
            if (this.syncTimer) clearInterval(this.syncTimer);
            const activeInterval = this.isPusherLive ? 12000 : 3500;
            this.syncTimer = setInterval(() => {
                this.syncActiveConversation();
                this.pollActiveCallCheck();
            }, activeInterval);

            if (this.callCheckTimer) clearInterval(this.callCheckTimer);
            this.callCheckTimer = setInterval(() => {
                this.pollActiveCallCheck();
            }, 2500);

            if (this.convListTimer) clearInterval(this.convListTimer);
            const convInterval = this.isPusherLive ? 18000 : 7000;
            this.convListTimer = setInterval(() => {
                this.refreshConversationsList();
            }, convInterval);
        },

        /**
         * Mark conversation read on backend
         */
        async markConversationAsRead(convId, messageId = null) {
            try {
                const pusherClient = typeof window.kiuqGetPusherClient === 'function' ? window.kiuqGetPusherClient() : (window.__kiuqPusher || null);
                const socketId = pusherClient?.connection?.socket_id;
                const headers = {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                };
                if (socketId) headers['X-Socket-ID'] = socketId;

                let targetId = messageId;
                if (!targetId && this.messages && this.messages.length > 0) {
                    for (let i = this.messages.length - 1; i >= 0; i--) {
                        const m = this.messages[i];
                        if (m && m.id && !String(m.id).startsWith('temp_')) {
                            targetId = Number(m.id);
                            break;
                        }
                    }
                }
                if (!targetId) targetId = this.lastSyncId || 0;

                await fetch(`/chat/conversations/${convId}/read`, {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin',
                    body: JSON.stringify({ message_id: targetId })
                });
                this.broadcastUnreadUpdate();
            } catch (e) {}
        },

        /**
         * Dispatch global DOM event so sidebar navigation unread pill updates instantly
         */
        broadcastUnreadUpdate() {
            window.dispatchEvent(new CustomEvent('chat:unread-updated', {
                detail: { unread_count: this.totalUnreadCount }
            }));
        },

        /**
         * Downscale large photos before upload (max 1920px, JPEG 85%).
         * Strictly preserves PNG transparency, GIF animations, SVG vectors, WebP, ICO, AVIF, HEIC.
         */
        async _compressImageForUpload(file) {
            try {
                if (!file) return file;
                const type = (file.type || '').toLowerCase();
                const name = (file.name || '').toLowerCase();
                // Never re-encode vectors, animations, icons, or transparent PNGs
                if (['image/gif', 'image/svg+xml', 'image/webp', 'image/png', 'image/x-icon', 'image/avif', 'image/heic'].includes(type) ||
                    /\.(svg|gif|png|webp|ico|avif|heic|heif)$/i.test(name)) {
                    return file;
                }
                if (!type.startsWith('image/')) return file;
                if (file.size < 400 * 1024) return file;
                if (typeof createImageBitmap !== 'function') return file;

                const bitmap = await createImageBitmap(file);
                const maxSide = 1920;
                const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
                const w = Math.max(1, Math.round(bitmap.width * scale));
                const h = Math.max(1, Math.round(bitmap.height * scale));

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(bitmap, 0, 0, w, h);
                if (bitmap.close) bitmap.close();

                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
                if (!blob || blob.size >= file.size) return file;

                const baseName = (file.name || 'image').replace(/\.[^.]+$/, '');
                return new File([blob], baseName + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
            } catch (e) {
                return file;
            }
        },

        /**
         * Send message with optimistic UI and multi-attachment upload
         */
        async sendMessage() {
            const hasAttachments = this.stagedFiles.length > 0;
            if (this.isSending && hasAttachments) return;

            const text = (this.composerText || '').trim();
            if (!text && !hasAttachments) {
                this.composerText = '';
                this.$nextTick(() => {
                    const ta = document.getElementById('chat-composer-textarea');
                    if (ta) {
                        ta.value = '';
                        ta.style.height = 'auto';
                    }
                });
                return;
            }

            const tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const convId = this.activeConversation.id;

            // Generate optimistic attachments with immediate client preview
            const optimisticAttachments = this.stagedFiles.map((item, idx) => ({
                id: 'temp_att_' + idx,
                file_name: item.name,
                file_size: item.size,
                file_type: item.type,
                file_path: item.previewUrl || '',
                file_url: item.previewUrl || '',
                view_url: item.previewUrl || '',
                download_url: item.previewUrl || '#'
            }));

            const uploadingLabel = this.stagedFiles.length === 1 ? this.stagedFiles[0].name : `${this.stagedFiles.length} files`;

            // Create optimistic message object
            const optimisticMsg = {
                id: tempId,
                client_temp_id: tempId,
                conversation_id: convId,
                user_id: Number(this.currentUser.id),
                is_me: true,
                user: {
                    id: Number(this.currentUser.id),
                    name: this.currentUser.name,
                    avatar_url: this.currentUser.avatar_url
                },
                body: text,
                content: text,
                status: 'sent',
                is_edited: false,
                created_at: new Date().toISOString(),
                created_at_human: 'Just now',
                attachments: optimisticAttachments,
                reactions: [],
                reply_to: this.replyingTo ? {
                    id: this.replyingTo.id,
                    body: this.replyingTo.body || this.replyingTo.content,
                    content: this.replyingTo.content || this.replyingTo.body,
                    user_name: this.replyingTo.user?.name || 'User'
                } : null,
                link_preview: null
            };

            // Add optimistic message to list and scroll
            this.messages.push(optimisticMsg);
            this.messages = [...this.messages];
            this.updateConversationLastMessage(convId, optimisticMsg);

            // Prepare payload
            const formData = new FormData();
            formData.append('body', text);
            formData.append('content', text);
            formData.append('client_temp_id', tempId);
            if (this.replyingTo) {
                formData.append('reply_to_id', this.replyingTo.id);
            }
            const stagedSnapshot = [...this.stagedFiles];

            // Reset composer state immediately (typing indicator is cleared server-side inside sendMessage)
            this.lastTypingSent = 0;
            if (this._typingStopTimeout) clearTimeout(this._typingStopTimeout);
            this.composerText = '';
            this.stagedFiles = [];
            this.replyingTo = null;
            this.showEmojiPicker = false;
            this.$nextTick(() => {
                const ta = document.getElementById('chat-composer-textarea');
                if (ta) {
                    ta.value = '';
                    ta.style.height = 'auto';
                }
                this.autoResizeTextarea();
                this.scrollToBottom(true);
            });

            this.isSending = true;

            const pusherClient = typeof window.kiuqGetPusherClient === 'function' ? window.kiuqGetPusherClient() : (window.__kiuqPusher || null);
            const socketId = pusherClient?.connection?.socket_id;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            if (hasAttachments) {
                this.isUploading = true;
                this.uploadProgress = 0;
                this.uploadFileName = uploadingLabel;

                // Use pre-started compression promise from staging (0ms if already finished while user was previewing)
                const preparedFiles = await Promise.all(stagedSnapshot.map(item => item.compressedPromise || this._compressImageForUpload(item.file)));
                preparedFiles.forEach(f => formData.append('attachments[]', f));

                await new Promise((resolve) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', `/chat/conversations/${convId}/messages`);
                    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
                    xhr.setRequestHeader('Accept', 'application/json');
                    if (socketId) xhr.setRequestHeader('X-Socket-ID', socketId);
                    xhr.withCredentials = true;

                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                        }
                    };

                    xhr.onload = () => {
                        this.isUploading = false;
                        this.uploadProgress = 0;
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                if (data.success && data.message) {
                                    data.message.client_temp_id = tempId;
                                    data.message.user_id = Number(data.message.user_id);
                                    data.message.is_me = Number(data.message.user_id) === Number(this.currentUser.id);
                                    data.message.body = data.message.body || data.message.content || text;
                                    data.message.content = data.message.content || data.message.body || text;
                                    if (!data.message.status) {
                                        data.message.status = data.message.is_seen ? 'read' : 'sent';
                                    }

                                    const existingByIdIdx = this.messages.findIndex(m => m.id === data.message.id && m.id !== tempId);
                                    const tempIdx = this.messages.findIndex(m => m.id === tempId || m.client_temp_id === tempId);

                                    if (existingByIdIdx !== -1) {
                                        if (tempIdx !== -1 && tempIdx !== existingByIdIdx) this.messages.splice(tempIdx, 1);
                                        this.messages.splice(existingByIdIdx, 1, data.message);
                                    } else if (tempIdx !== -1) {
                                        this.messages.splice(tempIdx, 1, data.message);
                                    } else {
                                        this.messages.push(data.message);
                                    }

                                    this._deduplicateMessages();
                                    this.messages = [...this.messages];

                                    if (data.message.id > this.lastSyncId) {
                                        this.lastSyncId = data.message.id;
                                    }
                                    this.updateConversationLastMessage(convId, data.message);
                                } else {
                                    this.markMessageFailed(tempId);
                                }
                            } catch (err) {
                                this.markMessageFailed(tempId);
                            }
                        } else {
                            this.markMessageFailed(tempId);
                        }
                        resolve();
                    };

                    xhr.onerror = () => {
                        this.isUploading = false;
                        this.uploadProgress = 0;
                        this.markMessageFailed(tempId);
                        resolve();
                    };

                    xhr.send(formData);
                });
                this.isSending = false;
                this.$nextTick(() => this.scrollToBottom(true));
            } else {
                try {
                    const reqHeaders = {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    };
                    if (socketId) reqHeaders['X-Socket-ID'] = socketId;

                    const res = await fetch(`/chat/conversations/${convId}/messages`, {
                        method: 'POST',
                        headers: reqHeaders,
                        credentials: 'same-origin',
                        body: formData
                    });

                    const data = await res.json();

                    if (data.success && data.message) {
                        data.message.client_temp_id = tempId;
                        data.message.user_id = Number(data.message.user_id);
                        data.message.is_me = Number(data.message.user_id) === Number(this.currentUser.id);
                        data.message.body = data.message.body || data.message.content || text;
                        data.message.content = data.message.content || data.message.body || text;
                        if (!data.message.status) {
                            data.message.status = data.message.is_seen ? 'read' : 'sent';
                        }

                        const existingByIdIdx = this.messages.findIndex(m => m.id === data.message.id && m.id !== tempId);
                        const tempIdx = this.messages.findIndex(m => m.id === tempId || m.client_temp_id === tempId);

                        if (existingByIdIdx !== -1) {
                            if (tempIdx !== -1 && tempIdx !== existingByIdIdx) this.messages.splice(tempIdx, 1);
                            this.messages.splice(existingByIdIdx, 1, data.message);
                        } else if (tempIdx !== -1) {
                            this.messages.splice(tempIdx, 1, data.message);
                        } else {
                            this.messages.push(data.message);
                        }

                        this._deduplicateMessages();
                        this.messages = [...this.messages];

                        if (data.message.id > this.lastSyncId) {
                            this.lastSyncId = data.message.id;
                        }
                        this.updateConversationLastMessage(convId, data.message);
                    } else {
                        this.markMessageFailed(tempId);
                    }
                } catch (err) {
                    console.error('Failed to send message:', err);
                    this.markMessageFailed(tempId);
                } finally {
                    this.isSending = false;
                    this.$nextTick(() => this.scrollToBottom(true));
                }
            }
        },

        markMessageFailed(tempId) {
            const msg = this.messages.find(m => m.id === tempId);
            if (msg) {
                msg.status = 'failed';
            }
            this.showToast('Message failed to send. Click to retry.', 'error');
        },

        /**
         * Retry sending a failed message
         */
        retryMessage(msg) {
            if (!msg || msg.status !== 'failed') return;
            msg.status = 'sending';
            this.composerText = msg.body;
            // Remove the failed one and re-send
            this.messages = this.messages.filter(m => m.id !== msg.id);
            this.sendMessage();
        },

        /**
         * Handle file selections from file input
         */
        handleFileSelect(e) {
            const files = Array.from(e.target.files || []);
            this.addFilesToStaging(files);
            e.target.value = '';
        },

        /**
         * Add files to staged attachment preview tray (Supports up to 100MB for photos, videos, and large documents)
         */
        addFilesToStaging(files) {
            files.forEach(file => {
                if (file.size > 100 * 1024 * 1024) {
                    this.showToast(`File "${file.name}" exceeds 100MB limit`, 'error');
                    return;
                }

                let previewUrl = null;
                const isMedia = this.isImageAttachment(file) || this.isVideoAttachment(file) || (file.type && (file.type.startsWith('image/') || file.type.startsWith('video/')));
                if (isMedia) {
                    try {
                        previewUrl = URL.createObjectURL(file);
                    } catch (e) {
                        previewUrl = null;
                    }
                }

                this.stagedFiles.push({
                    file: file,
                    compressedPromise: this._compressImageForUpload(file),
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    previewUrl: previewUrl
                });
            });
        },

        removeStagedFile(index) {
            if (this.stagedFiles[index] && this.stagedFiles[index].previewUrl) {
                URL.revokeObjectURL(this.stagedFiles[index].previewUrl);
            }
            this.stagedFiles.splice(index, 1);
        },

        /**
         * Global clipboard paste (e.g. screenshot paste)
         */
        handleGlobalPaste(e) {
            // Only capture if active conversation is open
            if (!this.activeConversation) return;

            const items = (e.clipboardData || e.originalEvent.clipboardData)?.items;
            if (!items) return;

            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    const blob = items[i].getAsFile();
                    if (blob) {
                        e.preventDefault();
                        const file = new File([blob], `pasted_image_${Date.now()}.png`, { type: blob.type });
                        this.addFilesToStaging([file]);
                        this.showToast('Screenshot attached from clipboard!', 'success');
                        break;
                    }
                }
            }
        },

        /**
         * Telegram-Style Drag & Drop handlers over chat area
         */
        onChatDragEnter(e) {
            e.preventDefault();
            if (this.activeConversation) {
                this.isDraggingOverChat = true;
            }
        },

        onChatDragLeave(e) {
            if (!e.currentTarget || !e.currentTarget.contains(e.relatedTarget)) {
                this.isDraggingOverChat = false;
            }
        },

        onChatDrop(e) {
            e.preventDefault();
            this.isDraggingOverChat = false;
            if (!this.activeConversation) return;
            const files = Array.from(e.dataTransfer?.files || []);
            if (files.length > 0) {
                this.addFilesToStaging(files);
                this.showToast(`${files.length} file(s) attached — press Enter to send`, 'success');
                this.$nextTick(() => {
                    const textarea = document.getElementById('chat-composer-textarea');
                    if (textarea) textarea.focus();
                });
            }
        },

        handleDrop(e) {
            this.onChatDrop(e);
        },

        /**
         * Toggle emoji reactions
         */
        async toggleReaction(message, emoji) {
            if (!message || !emoji) return;

            // Optimistic update
            if (!message.reactions) message.reactions = [];
            const existing = message.reactions.find(r => r.emoji === emoji);

            if (existing) {
                if (existing.user_reacted || existing.is_me) {
                    existing.count--;
                    existing.user_reacted = false;
                    existing.is_me = false;
                    if (existing.count <= 0) {
                        message.reactions = message.reactions.filter(r => r.emoji !== emoji);
                    }
                } else {
                    existing.count++;
                    existing.user_reacted = true;
                    existing.is_me = true;
                }
            } else {
                message.reactions.push({
                    emoji: emoji,
                    count: 1,
                    user_reacted: true,
                    is_me: true
                });
            }
            this.messages = [...this.messages];

            try {
                await fetch(`/chat/messages/${message.id}/reactions`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ emoji: emoji, reaction: emoji })
                });
            } catch (e) {
                console.error('Failed to toggle reaction', e);
            }
        },

        /**
         * Set reply preview
         */
        setReply(msg) {
            this.replyingTo = msg;
            this.editingMessage = null;
            this.$nextTick(() => {
                const textarea = document.getElementById('chat-composer-textarea');
                if (textarea) textarea.focus();
            });
        },

        cancelReply() {
            this.replyingTo = null;
        },

        /**
         * Start editing own message
         */
        startEdit(msg) {
            if (Number(msg.user_id) !== Number(this.currentUser.id)) return;
            this.editingMessage = msg;
            this.replyingTo = null;
            this.composerText = msg.body;
            this.$nextTick(() => {
                const textarea = document.getElementById('chat-composer-textarea');
                if (textarea) {
                    textarea.focus();
                    this.autoResizeTextarea();
                }
            });
        },

        cancelEdit() {
            this.editingMessage = null;
            this.composerText = '';
            this.$nextTick(() => this.autoResizeTextarea());
        },

        /**
         * Save edited message
         */
        async saveEdit() {
            if (!this.editingMessage) return;
            const newText = this.composerText.trim();
            if (!newText) return;

            const msgId = this.editingMessage.id;
            const targetMsg = this.messages.find(m => m.id === msgId);
            if (targetMsg) {
                targetMsg.body = newText;
                targetMsg.is_edited = true;
            }

            this.cancelEdit();

            try {
                await fetch(`/chat/messages/${msgId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ body: newText })
                });
            } catch (e) {
                this.showToast('Failed to save edit', 'error');
            }
        },

        /**
         * Check if current user is authorized to delete / unsend message
         */
        canDeleteMessage(msg) {
            return !!msg && !msg.is_unsent;
        },

        canDeleteForEveryone(msg) {
            if (!msg || msg.is_unsent) return false;
            const isMe = Number(msg.user_id) === Number(this.currentUser?.id);
            const isAdmin = !!(this.currentUser?.can_manage_permissions || ['Admin', 'Super Admin', 'super-admin'].includes(this.currentUser?.team_role) || this.currentUser?.is_manager);
            return isMe || isAdmin;
        },

        canUnsendMessage(msg) {
            return this.canDeleteForEveryone(msg);
        },

        /**
         * Delete message (WhatsApp / Telegram style with Delete for Everyone / Delete for Me)
         */
        async deleteMessage(msg, scope = 'everyone') {
            if (!msg) return;
            const msgId = Number(msg.id);
            const isMe = Number(msg.user_id) === Number(this.currentUser.id);
            const isAdmin = !!(this.currentUser.can_manage_permissions || ['Admin', 'Super Admin', 'super-admin'].includes(this.currentUser.team_role) || this.currentUser.is_manager);

            if (scope === 'me' && !isMe && !isAdmin) {
                // Remove locally only
                this.messages = this.messages.filter(m => Number(m.id) !== msgId);
                const convId = Number(this.activeConversation?.id || msg.conversation_id);
                if (convId) {
                    this._persistCacheToStorage(convId, this.messages);
                    const lastMsg = this.messages[this.messages.length - 1] || null;
                    this.updateConversationLastMessage(convId, lastMsg);
                }
                this.showToast('Message removed for you', 'success');
                return;
            }

            const title = (scope === 'everyone' || isMe || isAdmin) ? 'Delete for Everyone?' : 'Delete Message?';
            const confirmPrompt = (scope === 'everyone' || isMe || isAdmin)
                ? 'This message and any attached media will be permanently deleted for all members in this conversation.'
                : 'Are you sure you want to remove this message?';
            const actionText = (scope === 'everyone' || isMe || isAdmin) ? 'Delete for Everyone' : 'Delete';

            this.showConfirm(
                title,
                confirmPrompt,
                actionText,
                'danger',
                async () => {
                    const prevMessages = [...this.messages];
                    const convId = Number(this.activeConversation?.id || msg.conversation_id);

                    // Optimistic 0ms instant removal from chat screen and cache
                    this.messages = this.messages.filter(m => Number(m.id) !== msgId);
                    if (convId) {
                        this._persistCacheToStorage(convId, this.messages);
                        const lastMsg = this.messages[this.messages.length - 1] || null;
                        this.updateConversationLastMessage(convId, lastMsg);
                    }

                    try {
                        const res = await fetch(`/chat/messages/${msg.id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin'
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.showToast('Message deleted', 'success');
                        } else {
                            // If failed, roll back
                            this.messages = prevMessages;
                            if (convId) this._persistCacheToStorage(convId, this.messages);
                            this.showToast(data.error || 'Failed to delete message', 'error');
                        }
                    } catch (e) {
                        this.showToast('Failed to delete message', 'error');
                    }
                }
            );
        },

        /**
         * Copy message text to clipboard with automatic execCommand fallback
         */
        copyMessageText(text) {
            if (!text) return;
            const fallback = () => {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.top = '0';
                    ta.style.left = '0';
                    ta.style.opacity = '0';
                    ta.style.pointerEvents = 'none';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    const ok = document.execCommand('copy');
                    document.body.removeChild(ta);
                    if (ok) {
                        this.showToast('Message copied to clipboard!', 'success');
                    } else {
                        this.showToast('Failed to copy', 'error');
                    }
                } catch (e) {
                    this.showToast('Failed to copy', 'error');
                }
            };

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showToast('Message copied to clipboard!', 'success');
                }).catch(() => fallback());
            } else {
                fallback();
            }
        },

        /**
         * Toggle Conversation Pin
         */
        async togglePin(conv) {
            if (!conv) return;
            const previousState = !!conv.is_pinned;
            const newState = !previousState;
            conv.is_pinned = newState;

            const convIdx = this.conversations.findIndex(c => c.id === conv.id || (c.other_user_id && conv.other_user_id && Number(c.other_user_id) === Number(conv.other_user_id)));
            if (convIdx >= 0) {
                this.conversations[convIdx].is_pinned = newState;
            } else {
                this.conversations.push(conv);
            }

            try {
                const res = await fetch(`/chat/conversations/${conv.id}/pin`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data && data.success) {
                    conv.is_pinned = !!data.is_pinned;
                    if (data.conversation_id) {
                        conv.id = data.conversation_id;
                        conv.is_virtual = false;
                        if (convIdx >= 0) {
                            this.conversations[convIdx].id = data.conversation_id;
                            this.conversations[convIdx].is_virtual = false;
                            this.conversations[convIdx].is_pinned = !!data.is_pinned;
                        }
                    }
                    this.showToast(conv.is_pinned ? 'Conversation pinned' : 'Conversation unpinned', 'success');
                } else {
                    conv.is_pinned = previousState;
                    if (convIdx >= 0) this.conversations[convIdx].is_pinned = previousState;
                    this.showToast(data?.error || 'Failed to update pin', 'error');
                }
            } catch (e) {
                conv.is_pinned = previousState;
                if (convIdx >= 0) this.conversations[convIdx].is_pinned = previousState;
                this.showToast('Network error while pinning', 'error');
            }
        },

        /**
         * Toggle Conversation Archive
         */
        async toggleArchive(conv) {
            if (!conv) return;
            const previousState = !!conv.is_archived;
            const newState = !previousState;
            conv.is_archived = newState;

            // Immediately reflect in this.conversations
            const convIdx = this.conversations.findIndex(c => c.id === conv.id || (c.other_user_id && conv.other_user_id && Number(c.other_user_id) === Number(conv.other_user_id)));
            if (convIdx >= 0) {
                this.conversations[convIdx].is_archived = newState;
            } else {
                this.conversations.push(conv);
            }

            // Immediately reflect in activeConversation if currently opened
            if (this.activeConversation && (this.activeConversation.id === conv.id || (this.activeConversation.other_user_id && conv.other_user_id && Number(this.activeConversation.other_user_id) === Number(conv.other_user_id)))) {
                this.activeConversation.is_archived = newState;
            }

            try {
                const res = await fetch(`/chat/conversations/${conv.id}/archive`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data && data.success) {
                    conv.is_archived = !!data.is_archived;
                    if (data.conversation_id) {
                        conv.id = data.conversation_id;
                        conv.is_virtual = false;
                        if (convIdx >= 0) {
                            this.conversations[convIdx].id = data.conversation_id;
                            this.conversations[convIdx].is_virtual = false;
                            this.conversations[convIdx].is_archived = !!data.is_archived;
                        }
                    }
                    this.showToast(conv.is_archived ? 'Chat archived' : 'Chat unarchived', 'success');
                } else {
                    conv.is_archived = previousState;
                    if (convIdx >= 0) this.conversations[convIdx].is_archived = previousState;
                    if (this.activeConversation && this.activeConversation.id === conv.id) this.activeConversation.is_archived = previousState;
                    this.showToast(data?.error || 'Failed to update archive', 'error');
                }
            } catch (e) {
                conv.is_archived = previousState;
                if (convIdx >= 0) this.conversations[convIdx].is_archived = previousState;
                if (this.activeConversation && this.activeConversation.id === conv.id) this.activeConversation.is_archived = previousState;
                this.showToast('Network error while archiving', 'error');
            }
        },

        /**
         * Toggle Conversation Mute
         */
        async toggleMute(conv) {
            conv.is_muted = !conv.is_muted;
            try {
                await fetch(`/chat/conversations/${conv.id}/mute`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                });
                this.showToast(conv.is_muted ? 'Notifications muted' : 'Notifications unmuted', 'success');
            } catch (e) {}
        },

        /**
         * Open Nickname Edit Modal
         */
        openNicknameModal(conv) {
            const target = conv || this.activeConversation;
            if (!target) return;
            this.nicknameTargetConv = target;
            this.nicknameInput = target.nickname || '';
            this.isEditingNickname = true;
            this.$nextTick(() => {
                const el = document.getElementById('chat-nickname-input');
                if (el) {
                    el.focus();
                    el.select();
                }
            });
        },

        /**
         * Cancel Nickname Edit
         */
        cancelEditingNickname() {
            this.isEditingNickname = false;
            this.nicknameInput = '';
            this.nicknameTargetConv = null;
        },

        /**
         * Save Custom Nickname
         */
        async saveNickname(conv) {
            const target = conv || this.nicknameTargetConv || this.activeConversation;
            if (!target) return;
            this.isSavingNickname = true;
            const newNick = (this.nicknameInput || '').trim() || null;
            try {
                const res = await fetch(`/chat/conversations/${target.id}/nickname`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ nickname: newNick })
                });
                const data = await res.json();
                if (data.success) {
                    target.nickname = data.nickname;
                    target.title = data.title;
                    if (data.real_name) target.real_name = data.real_name;

                    const inList = this.conversations.find(c => Number(c.id) === Number(target.id));
                    if (inList) {
                        inList.nickname = data.nickname;
                        inList.title = data.title;
                        if (data.real_name) inList.real_name = data.real_name;
                    }
                    if (this.activeConversation && Number(this.activeConversation.id) === Number(target.id)) {
                        this.activeConversation.nickname = data.nickname;
                        this.activeConversation.title = data.title;
                        if (data.real_name) this.activeConversation.real_name = data.real_name;
                    }
                    this.conversations = [...this.conversations];
                    this.isEditingNickname = false;
                    this.nicknameTargetConv = null;
                    this.showToast(data.nickname ? `Nickname set to "${data.nickname}"` : 'Nickname removed', 'success');
                } else {
                    this.showToast(data.error || 'Failed to update nickname', 'error');
                }
            } catch (err) {
                console.error(err);
                this.showToast('Failed to update nickname', 'error');
            } finally {
                this.isSavingNickname = false;
            }
        },

        showConfirm(title, message, confirmText, confirmType, action) {
            this.confirmModal = {
                open: true,
                title: title || 'Confirmation',
                message: message || '',
                confirmText: confirmText || 'Confirm',
                confirmType: confirmType || 'danger',
                action: action
            };
        },

        executeConfirm() {
            if (typeof this.confirmModal.action === 'function') {
                this.confirmModal.action();
            }
            this.confirmModal.open = false;
            this.confirmModal.action = null;
        },

        cancelConfirm() {
            this.confirmModal.open = false;
            this.confirmModal.action = null;
        },

        starMessage(msg) {
            if (!msg) return;
            msg.is_starred = !msg.is_starred;
            this.showToast(msg.is_starred ? 'Message starred' : 'Message unstarred', 'success');
        },

        /**
         * Open Telegram-style context menu for a conversation row
         */
        openConversationMenu(e, conv) {
            if (!conv) return;
            if (e && e.preventDefault) e.preventDefault();
            if (e && e.stopPropagation) e.stopPropagation();

            let clientX = e.clientX || 0;
            let clientY = e.clientY || 0;

            if (e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else if (e.target && clientX === 0) {
                const rect = e.target.getBoundingClientRect();
                clientX = rect.right - 180;
                clientY = rect.bottom + 4;
            }

            const menuWidth = 210;
            const menuHeight = 240;
            const padding = 12;

            let x = clientX;
            let y = clientY;

            if (x + menuWidth > window.innerWidth - padding) {
                x = window.innerWidth - menuWidth - padding;
            }
            if (y + menuHeight > window.innerHeight - padding) {
                y = window.innerHeight - menuHeight - padding;
            }
            if (x < padding) x = padding;
            if (y < padding) y = padding;

            this.conversationContextMenu = {
                open: true,
                x,
                y,
                conv
            };
        },

        closeConversationMenu() {
            this.conversationContextMenu.open = false;
            this.conversationContextMenu.conv = null;
        },

        deleteConversation(conv) {
            if (!conv) return;
            this.showConfirm(
                'Delete Chat?',
                `Are you sure you want to delete "${conv.title}"? Messages in this chat will no longer appear in your list.`,
                'Delete Chat',
                'danger',
                async () => {
                    this.conversations = this.conversations.filter(c => c.id !== conv.id);
                    if (this.activeConversation && this.activeConversation.id === conv.id) {
                        this.activeConversation = null;
                        this.messages = [];
                    }
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.content;
                        await fetch(`/chat/conversations/${conv.id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token || ''
                            }
                        });
                    } catch (e) {}
                    this.showToast('Chat deleted', 'success');
                }
            );
        },

        toggleMarkUnread(conv) {
            if (!conv) return;
            if ((conv.unread_count || 0) > 0) {
                conv.unread_count = 0;
                this.showToast('Marked as read', 'success');
            } else {
                conv.unread_count = 1;
                this.showToast('Marked as unread', 'success');
            }
        },

        /**
         * Open WhatsApp-style context menu on chevron click / right-click / two-finger click / long-press
         */
        openContextMenu(e, msg) {
            if (!msg) return;
            if (e && e.preventDefault) e.preventDefault();
            if (e && e.stopPropagation) e.stopPropagation();

            let clientX = 0;
            let clientY = 0;

            const btn = e && e.target ? (e.target.closest('button[data-chat-chevron]') || e.target.closest('button')) : null;
            if (btn) {
                const rect = btn.getBoundingClientRect();
                clientX = rect.right - 240;
                clientY = rect.bottom + 6;
            } else if (e && e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX - 120;
                clientY = e.touches[0].clientY + 8;
            } else if (e && e.clientX !== undefined) {
                clientX = e.clientX - 100;
                clientY = e.clientY + 8;
            } else if (e && e.target) {
                const rect = e.target.getBoundingClientRect();
                clientX = rect.right - 240;
                clientY = rect.bottom + 6;
            }

            const menuWidth = Math.min(290, window.innerWidth - 16);
            const menuHeight = 420;
            const padding = 8;

            let x = clientX;
            let y = clientY;

            // Flip vertically if overflowing bottom of window
            if (btn) {
                const rect = btn.getBoundingClientRect();
                if (rect.bottom + menuHeight > window.innerHeight - padding) {
                    y = Math.max(padding, rect.top - menuHeight - 6);
                }
            } else if (y + menuHeight > window.innerHeight - padding) {
                y = Math.max(padding, window.innerHeight - menuHeight - padding);
            }

            // Ensure menu doesn't overflow horizontally
            if (x + menuWidth > window.innerWidth - padding) {
                x = window.innerWidth - menuWidth - padding;
            }
            if (x < padding) x = padding;
            if (y < padding) y = padding;

            this.showReactionsInMenu = true;
            this.contextMenu = {
                open: true,
                x: Math.round(x),
                y: Math.round(y),
                msg,
                showEmojiGrid: false
            };
        },

        closeContextMenu() {
            this.contextMenu.open = false;
            this.contextMenu.msg = null;
            this.contextMenu.showEmojiGrid = false;
        },

        hasReacted(message, emoji) {
            if (!message || !message.reactions) return false;
            const r = message.reactions.find(x => x.emoji === emoji);
            return r ? !!(r.user_reacted || r.is_me) : false;
        },

        hasMedia(msg) {
            if (!msg || msg.is_unsent) return false;
            if (msg.attachments && msg.attachments.length > 0) return true;
            if (msg.type === 'sticker' || msg.type === 'gif' || msg.type === 'image' || msg.type === 'voice') return true;
            return false;
        },

        hasViewableMedia(msg) {
            if (!msg || msg.is_unsent || this.isVoiceOnly(msg)) return false;
            if (msg.attachments && msg.attachments.length > 0) {
                return msg.attachments.some(a => this.isImageAttachment(a) || this.isVideoAttachment(a, msg));
            }
            if (msg.type === 'sticker' || msg.type === 'gif' || msg.type === 'image') return true;
            return false;
        },

        /**
         * Download media or message directly to user's computer (WhatsApp style "Save to Downloads" & "Save as...")
         */
        async downloadMessageMedia(msg, saveAs = false) {
            if (!msg) return;
            const att = (msg.attachments && msg.attachments.length > 0) ? msg.attachments[0] : null;
            let url = '';
            let filename = '';

            if (att) {
                url = att.download_url || (att.id ? `/chat/attachments/${att.id}/download` : '') || att.view_url || att.file_url || att.file_path;
                filename = att.file_name || (this.isImageAttachment(att) ? 'image.jpg' : (this.isVideoAttachment(att, msg) ? 'video.mp4' : (this.isVoiceAttachment(att, msg) ? 'voice-message.webm' : 'download')));
            } else if ((msg.type === 'sticker' || msg.type === 'gif') && (msg.sticker_url || msg.body || msg.content)) {
                url = msg.sticker_url || msg.body || msg.content;
                filename = msg.type === 'sticker' ? 'sticker.png' : 'animation.gif';
            } else if (msg.file_url) {
                url = msg.file_url;
                filename = msg.file_name || 'attachment';
            } else if (msg.body || msg.content) {
                const textContent = (msg.body || msg.content || '').trim();
                if (textContent) {
                    const blob = new Blob([textContent], { type: 'text/plain;charset=utf-8' });
                    url = URL.createObjectURL(blob);
                    filename = `message-${msg.id || Date.now()}.txt`;
                }
            }

            if (!url) {
                this.showToast('No downloadable content found', 'info');
                return;
            }

            if (saveAs && typeof window.showSaveFilePicker === 'function') {
                try {
                    const res = await fetch(url, { credentials: 'same-origin' });
                    const blob = await res.blob();
                    const handle = await window.showSaveFilePicker({ suggestedName: filename });
                    const writable = await handle.createWritable();
                    await writable.write(blob);
                    await writable.close();
                    this.showToast(`Saved ${filename}`, 'success');
                    return;
                } catch (err) {
                    if (err && err.name === 'AbortError') return;
                }
            }

            try {
                const a = document.createElement('a');
                a.href = url;
                a.setAttribute('download', filename);
                a.setAttribute('target', '_blank');
                document.body.appendChild(a);
                a.click();
                setTimeout(() => {
                    try { document.body.removeChild(a); } catch (e) {}
                }, 150);
                this.showToast(`Downloading ${filename}...`, 'success');
            } catch (e) {
                window.open(url, '_blank');
            }
        },

        copyMessage(msg) {
            if (!msg) return;
            let text = (msg.body || msg.content || '').trim();
            if (!text && msg.attachments && msg.attachments.length > 0) {
                text = msg.attachments[0].file_name || msg.attachments[0].view_url || msg.attachments[0].file_url || '';
            } else if (!text && msg.type === 'sticker') {
                text = msg.sticker_url || msg.body || 'Sticker';
            }
            if (text) {
                this.copyMessageText(text);
            } else {
                this.showToast('Nothing to copy', 'info');
            }
        },

        /**
         * WhatsApp & Telegram style Pin message in chat
         */
        togglePinMessage(msg) {
            if (!msg || !this.activeConversation) return;
            const isCurrentlyPinned = (this.activeConversation.pinned_message_id === msg.id) || !!msg.is_pinned;
            if (isCurrentlyPinned) {
                this.activeConversation.pinned_message_id = null;
                msg.is_pinned = false;
                this.showToast('Message unpinned', 'success');
            } else {
                this.messages.forEach(m => { m.is_pinned = false; });
                this.activeConversation.pinned_message_id = msg.id;
                msg.is_pinned = true;
                this.showToast('Message pinned to chat', 'success');
            }
        },

        getPinnedMessage() {
            if (!this.activeConversation || !this.activeConversation.pinned_message_id) return null;
            return this.messages.find(m => Number(m.id) === Number(this.activeConversation.pinned_message_id)) || null;
        },

        getPinnedMessageText() {
            const msg = this.getPinnedMessage();
            if (!msg) return '';
            if (msg.body || msg.content) return (msg.body || msg.content).trim();
            if (msg.type === 'voice') return '🎤 Voice message';
            if (msg.type === 'sticker') return '⭐ Sticker';
            if (msg.attachments && msg.attachments.length > 0) return '📎 ' + (msg.attachments[0].file_name || 'Attachment');
            return 'Pinned message';
        },

        scrollToMessage(msgId) {
            if (!msgId) return;
            const el = document.getElementById(`msg-${msgId}`) || document.querySelector(`[data-msg-id="${msgId}"]`);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('ring-2', 'ring-indigo-500', 'ring-offset-2');
                setTimeout(() => {
                    el.classList.remove('ring-2', 'ring-indigo-500', 'ring-offset-2');
                }, 2000);
            }
        },

        /**
         * WhatsApp-Style Multi-Select Messages Mode
         */
        startSelectMessages(msg) {
            this.isSelectingMessages = true;
            this.selectedMessageIds = msg && msg.id ? [msg.id] : [];
            this.showToast('Select messages mode enabled', 'info');
        },

        toggleSelectMessage(msg) {
            if (!msg || !msg.id) return;
            const idx = this.selectedMessageIds.indexOf(msg.id);
            if (idx >= 0) {
                this.selectedMessageIds.splice(idx, 1);
            } else {
                this.selectedMessageIds.push(msg.id);
            }
        },

        cancelSelectMessages() {
            this.isSelectingMessages = false;
            this.selectedMessageIds = [];
        },

        async deleteSelectedMessages() {
            if (!this.selectedMessageIds.length) return;
            const count = this.selectedMessageIds.length;
            this.showConfirm(
                'Delete Messages',
                `Are you sure you want to delete ${count} selected message(s)?`,
                'Delete',
                'danger',
                async () => {
                    const ids = [...this.selectedMessageIds];
                    this.cancelSelectMessages();
                    for (const id of ids) {
                        const m = this.messages.find(msg => Number(msg.id) === Number(id));
                        if (m) {
                            await this.deleteMessage(m, 'everyone');
                        }
                    }
                    this.showToast(`${count} message(s) deleted`, 'success');
                }
            );
        },

        forwardSelectedMessages() {
            if (!this.selectedMessageIds.length) return;
            const msg = this.messages.find(m => Number(m.id) === Number(this.selectedMessageIds[0]));
            if (msg) {
                this.openForwardModal(msg);
                this.cancelSelectMessages();
            }
        },

        reportMessage(msg) {
            if (!msg) return;
            this.showToast('Message reported to administrator. Thank you!', 'success');
        },

        handleMessageTouchStart(e, msg) {
            if (this.touchTimer) clearTimeout(this.touchTimer);
            this._touchReplyMsg = msg;
            this.swipeReplyMsgId = null;
            this.swipeReplyOffset = 0;
            if (e.touches && e.touches[0]) {
                this.touchStartX = e.touches[0].clientX;
                this.touchStartY = e.touches[0].clientY;
            }
            this.touchTimer = setTimeout(() => {
                if (this.swipeReplyMsgId) return;
                if (navigator.vibrate) {
                    try { navigator.vibrate(35); } catch (_) {}
                }
                this.openContextMenu(e, msg);
            }, 480);
        },

        handleMessageTouchMove(e) {
            if (!e.touches || !e.touches[0] || this.touchStartX === undefined) return;
            const rawDx = e.touches[0].clientX - this.touchStartX;
            const rawDy = e.touches[0].clientY - this.touchStartY;
            const dx = Math.abs(rawDx);
            const dy = Math.abs(rawDy);

            // If moved more than 8px, cancel long-press context menu timer
            if ((dx > 8 || dy > 8) && this.touchTimer) {
                clearTimeout(this.touchTimer);
                this.touchTimer = null;
            }

            // Telegram-style swipe-left on a message bubble to quick-reply
            if (this._touchReplyMsg && !this._touchReplyMsg.is_unsent && rawDx < -12 && dx > dy * 1.4) {
                this.swipeReplyMsgId = this._touchReplyMsg.id;
                this.swipeReplyOffset = Math.max(-64, rawDx * 0.65);
            } else if (this.swipeReplyMsgId && rawDx >= 0) {
                this.swipeReplyOffset = 0;
            }
        },

        handleMessageTouchEnd() {
            if (this.touchTimer) {
                clearTimeout(this.touchTimer);
                this.touchTimer = null;
            }
            if (this.swipeReplyMsgId && this.swipeReplyOffset <= -36 && this._touchReplyMsg) {
                if (navigator.vibrate) {
                    try { navigator.vibrate(20); } catch (_) {}
                }
                this.setReply(this._touchReplyMsg);
            }
            this.swipeReplyMsgId = null;
            this.swipeReplyOffset = 0;
            this._touchReplyMsg = null;
        },

        // Native iOS/Android edge swipe to go back from active chat to chat list
        handlePanelTouchStart(e) {
            if (!this.isMobile || !this.activeConversation) return;
            if (e.touches && e.touches.length === 1) {
                this.panelTouchStartX = e.touches[0].clientX;
                this.panelTouchStartY = e.touches[0].clientY;
                // Only consider as potential back-swipe if initiated within 35px from the left edge
                this.isPanelEdgeSwipe = this.panelTouchStartX <= 35;
            }
        },

        handlePanelTouchMove(e) {
            if (!this.isPanelEdgeSwipe || !e.touches || e.touches.length !== 1) return;
            const dx = e.touches[0].clientX - this.panelTouchStartX;
            const dy = Math.abs(e.touches[0].clientY - this.panelTouchStartY);
            // If dragging vertically faster than horizontally, cancel edge swipe
            if (dy > 20 && dy > dx) {
                this.isPanelEdgeSwipe = false;
            }
        },

        handlePanelTouchEnd(e) {
            if (!this.isPanelEdgeSwipe) return;
            this.isPanelEdgeSwipe = false;
            const touch = e.changedTouches && e.changedTouches[0];
            if (touch && (touch.clientX - this.panelTouchStartX > 65)) {
                this.backToConversations();
            }
        },

        handleTypingBroadcast(e) {
            if (!e || Number(e.user_id) === Number(this.currentUser.id)) return;
            const convId = Number(e.conversation_id);
            const name = e.user_name || 'Someone';

            if (e.is_typing) {
                if (convId) {
                    this.typingConversations[convId] = { name: name, expiresAt: Date.now() + 4500 };
                }
                if (this.activeConversation && Number(this.activeConversation.id) === convId) {
                    this._typingMap[name] = Date.now() + 4500;
                }
            } else {
                if (convId) {
                    delete this.typingConversations[convId];
                }
                delete this._typingMap[name];
            }
            this.typingConversations = { ...this.typingConversations };
            this._updateTypingUsers();
        },

        /**
         * Send typing indicator status to server (throttled & responsive)
         */
        onComposerInput() {
            this.autoResizeTextarea();
            if (!this.activeConversation) return;

            this.sendTypingStatus(true);

            if (this._typingStopTimeout) clearTimeout(this._typingStopTimeout);
            this._typingStopTimeout = setTimeout(() => {
                this.sendTypingStatus(false);
            }, 3500);
        },

        sendTypingStatus(isTyping) {
            if (!this.activeConversation) return;
            const now = Date.now();
            if (isTyping && now - this.lastTypingSent < 2800) return;
            if (isTyping) this.lastTypingSent = now;

            const pusherClient = typeof window.kiuqGetPusherClient === 'function' ? window.kiuqGetPusherClient() : (window.__kiuqPusher || null);
            const socketId = pusherClient?.connection?.socket_id;
            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            };
            if (socketId) headers['X-Socket-ID'] = socketId;

            fetch('/chat/typing', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify({
                    conversation_id: this.activeConversation.id,
                    is_typing: !!isTyping
                })
            }).catch(() => {});
        },

        /**
         * Handle keyboard shortcuts in message composer:
         * - Enter: Send message immediately (or save edit)
         * - Cmd/Ctrl + Enter: Send message immediately
         * - Shift + Enter: Insert newline row
         * - Escape: Cancel message editing if active
         */
        handleComposerKeydown(event) {
            if (event.key === 'Escape' || event.keyCode === 27) {
                if (this.editingMessage) {
                    event.preventDefault();
                    this.cancelEdit();
                }
                return;
            }

            if (event.key === 'Enter' || event.keyCode === 13) {
                // Allow Shift+Enter (without Cmd/Ctrl) to insert a regular newline row
                if (event.shiftKey && !event.metaKey && !event.ctrlKey) {
                    return;
                }

                // If IME is composing (e.g. typing Khmer, Japanese, Chinese, etc.), let IME finish
                if (event.isComposing || event.keyCode === 229) {
                    return;
                }

                // Immediately prevent default newline from being inserted
                event.preventDefault();
                event.stopPropagation();

                if (this.editingMessage) {
                    this.saveEdit();
                } else {
                    this.sendMessage();
                }
            }
        },

        autoResizeTextarea() {
            const ta = document.getElementById('chat-composer-textarea');
            if (ta) {
                ta.style.height = 'auto';
                ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
            }
        },

        /**
         * Insert emoji into composer
         */
        insertEmoji(emoji) {
            this.composerText += emoji;
            this.$nextTick(() => {
                const ta = document.getElementById('chat-composer-textarea');
                if (ta) {
                    ta.focus();
                    this.autoResizeTextarea();
                }
            });
        },

        /**
         * Load Available Stickers (Presets + Custom)
         */
        async loadStickers() {
            if (this.isLoadingStickers) return;
            this.isLoadingStickers = true;
            try {
                const res = await fetch('/chat/stickers', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success && Array.isArray(data.stickers)) {
                    this.stickers = data.stickers;
                }
            } catch (e) {
                console.warn('Failed to load stickers:', e);
            } finally {
                this.isLoadingStickers = false;
            }
        },

        /**
         * Send Sticker (WhatsApp / Telegram style transparent message)
         */
        async sendSticker(sticker) {
            if (!this.activeConversation) return;
            const convId = this.activeConversation.id;
            const tempId = 'temp_stk_' + Date.now();
            const stickerUrl = sticker.image_url || sticker.image_path;

            const optimisticMsg = {
                id: tempId,
                client_temp_id: tempId,
                conversation_id: convId,
                user_id: Number(this.currentUser.id),
                is_me: true,
                type: 'sticker',
                content: stickerUrl,
                body: stickerUrl,
                status: 'sent',
                created_at: new Date().toISOString(),
                created_at_human: 'Just now',
                user: {
                    id: Number(this.currentUser.id),
                    name: this.currentUser.name,
                    avatar_url: this.currentUser.avatar_url
                },
                attachments: []
            };

            this.messages.push(optimisticMsg);
            this.messages = [...this.messages];
            this.updateConversationLastMessage(convId, optimisticMsg);
            this.showEmojiPicker = false;
            this.$nextTick(() => this.scrollToBottom(true));

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('body', stickerUrl);
                fd.append('content', stickerUrl);
                fd.append('type', 'sticker');
                fd.append('client_temp_id', tempId);

                const res = await fetch(`/chat/conversations/${convId}/messages`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await res.json();
                if (data.success && data.message) {
                    const idx = this.messages.findIndex(m => m.id === tempId || m.client_temp_id === tempId);
                    if (idx !== -1) {
                        data.message.is_me = true;
                        data.message.type = 'sticker';
                        this.messages.splice(idx, 1, data.message);
                        this.messages = [...this.messages];
                    }
                }
            } catch (err) {
                console.error('Failed to send sticker:', err);
                this.markMessageFailed(tempId);
            }
        },

        /**
         * Open WhatsApp-style sticker action sheet / modal
         */
        openStickerActionModal(msg) {
            if (!msg || msg.type !== 'sticker') return;
            this.selectedStickerMessage = msg;
            this.stickerActionModal = true;
        },

        closeStickerActionModal() {
            this.stickerActionModal = false;
            this.selectedStickerMessage = null;
        },

        isStickerSaved(msg) {
            if (!msg) return false;
            const stickerUrl = msg.body || msg.content;
            if (!stickerUrl) return false;
            return this.stickers.some(s => {
                if (s.image_url === stickerUrl || s.image_path === stickerUrl) return true;
                if (s.image_path && stickerUrl.includes(s.image_path.replace(/^\//, ''))) return true;
                if (s.image_url && (stickerUrl.endsWith(s.image_url) || s.image_url.endsWith(stickerUrl))) return true;
                return false;
            });
        },

        /**
         * Save a sticker from a chat message (received from another user) into personal sticker drawer.
         */
        async saveStickerFromMessage(msg) {
            const stickerUrl = msg.body || msg.content;
            if (!stickerUrl) return;

            // Check if user already has this sticker in drawer
            if (this.isStickerSaved(msg)) {
                this.showToast('Sticker already in your drawer! ⭐', 'info');
                return;
            }

            // Derive nice name for sticker
            let stickerName = msg.sticker_name || (msg.user?.name ? ('Sticker by ' + msg.user.name) : 'Saved Sticker');
            if (stickerUrl.includes('thank_kim')) stickerName = 'Thank... Kim';
            else if (stickerUrl.includes('jo_hah')) stickerName = 'Jo hah';
            else if (stickerUrl.includes('jox')) stickerName = 'JoX Sunglasses';
            else if (stickerUrl.includes('fu_yak')) stickerName = 'FU YAK';
            else if (stickerUrl.includes('hart_mee')) stickerName = 'Hart mee???';
            else if (stickerUrl.includes('blocked_doggo') || stickerUrl.includes('blocked.svg')) stickerName = 'Blocked Doggo';
            else if (stickerUrl.includes('plom_cat')) stickerName = 'Plom? Cat';
            else if (stickerUrl.includes('nis_caption')) stickerName = 'Nis Caption!';
            else if (stickerUrl.includes('qi_bu_dui')) stickerName = 'Qi bu dui';
            else if (stickerUrl.includes('fire_exp')) stickerName = 'Fire Explosion';

            // Optimistically add sticker to local drawer (private to current user)
            const tempSticker = {
                id: 'saved_' + Date.now(),
                name: stickerName,
                image_url: stickerUrl,
                image_path: stickerUrl,
                category: 'saved',
                user_id: Number(this.currentUser.id),
                is_public: false
            };
            this.stickers.unshift(tempSticker);
            this.showToast('⭐ Sticker saved to your private collection!', 'success');

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('name', stickerName);
                fd.append('image_url', stickerUrl);
                fd.append('category', 'saved');

                const res = await fetch('/chat/stickers', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await res.json();
                if (data.success && data.sticker) {
                    const idx = this.stickers.findIndex(s => s.id === tempSticker.id);
                    if (idx !== -1) {
                        this.stickers.splice(idx, 1, data.sticker);
                    }
                }
            } catch (e) {
                console.warn('Failed to save sticker to server:', e);
            }
        },

        /**
         * Delete user custom / saved sticker
         */
        async deleteCustomSticker(stk, event) {
            if (event) event.stopPropagation();
            if (!stk || !stk.id) return;
            const idx = this.stickers.findIndex(s => s.id === stk.id);
            if (idx !== -1) {
                this.stickers.splice(idx, 1);
            }
            this.showToast('Sticker removed', 'info');
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                await fetch(`/chat/stickers/${stk.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
            } catch (e) {
                console.warn('Failed to delete sticker:', e);
            }
        },

        /**
         * Load Worldwide GIFs with fast search & trending
         */
        async loadGifs(query = '') {
            if (this._gifDebounce) clearTimeout(this._gifDebounce);
            this._gifDebounce = setTimeout(async () => {
                this.isLoadingGifs = true;
                try {
                    const url = `/chat/gifs/search?q=${encodeURIComponent(query)}`;
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (data.success && Array.isArray(data.gifs)) {
                        this.gifs = data.gifs;
                    }
                } catch (e) {
                    console.warn('Failed to load GIFs:', e);
                } finally {
                    this.isLoadingGifs = false;
                }
            }, query ? 250 : 0);
        },

        /**
         * Send Worldwide Animated GIF
         */
        async sendGif(gif) {
            if (!this.activeConversation) return;
            const convId = this.activeConversation.id;
            const tempId = 'temp_gif_' + Date.now();
            const gifUrl = gif.url || gif.preview_url;

            const optimisticMsg = {
                id: tempId,
                client_temp_id: tempId,
                conversation_id: convId,
                user_id: Number(this.currentUser.id),
                is_me: true,
                type: 'gif',
                content: gifUrl,
                body: gifUrl,
                status: 'sent',
                created_at: new Date().toISOString(),
                created_at_human: 'Just now',
                user: {
                    id: Number(this.currentUser.id),
                    name: this.currentUser.name,
                    avatar_url: this.currentUser.avatar_url
                },
                attachments: []
            };

            this.messages.push(optimisticMsg);
            this.messages = [...this.messages];
            this.updateConversationLastMessage(convId, optimisticMsg);
            this.showEmojiPicker = false;
            this.$nextTick(() => this.scrollToBottom(true));

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('body', gifUrl);
                fd.append('content', gifUrl);
                fd.append('type', 'gif');
                fd.append('client_temp_id', tempId);

                const res = await fetch(`/chat/conversations/${convId}/messages`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await res.json();
                if (data.success && data.message) {
                    const idx = this.messages.findIndex(m => m.id === tempId || m.client_temp_id === tempId);
                    if (idx !== -1) {
                        data.message.is_me = true;
                        data.message.type = 'gif';
                        this.messages.splice(idx, 1, data.message);
                        this.messages = [...this.messages];
                    }
                }
            } catch (err) {
                console.error('Failed to send GIF:', err);
                this.markMessageFailed(tempId);
            }
        },

        /**
         * Open Sticker Creator Modal (WhatsApp Sticker Studio UI/UX)
         */
        openStickerCreator(initialCaption = '') {
            this.stickerCreator = {
                imageFile: null,
                previewUrl: '',
                originalPreviewUrl: '',
                cutoutPreviewUrl: '',
                activePreviewUrl: '',
                caption: initialCaption || '',
                activeTool: 'text', // 'text', 'crop', 'emoji'
                cropShape: 'fit', // 'fit', 'circle', 'square'
                hasStickerOutline: true, // Iconic WhatsApp white contour border!
                zoom: 1,
                panX: 0,
                panY: 0,
                isDraggingImage: false,
                imageDragStartX: 0,
                imageDragStartY: 0,
                imageDragInitialPanX: 0,
                imageDragInitialPanY: 0,
                textX: 50,
                textY: 82,
                isDraggingText: false,
                dragStartX: 0,
                dragStartY: 0,
                dragInitialTextX: 50,
                dragInitialTextY: 82,
                isZoomingText: false,
                zoomTextStartX: 0,
                zoomTextInitialSize: 28,
                fontFamily: 'Impact',
                fontSize: 28,
                textColor: '#ffffff',
                textBgStyle: 'none', // 'none', 'badge_dark', 'badge_light', 'badge_color'
                hasStroke: true,
                strokeColor: '#000000',
                isProcessing: false
            };
            this.stickerCreatorModal = true;
            this.showEmojiPicker = false;
        },

        handleStickerImageSelect(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.stickerCreator.imageFile = file;
            if (this.stickerCreator.originalPreviewUrl && this.stickerCreator.originalPreviewUrl.startsWith('blob:')) {
                URL.revokeObjectURL(this.stickerCreator.originalPreviewUrl);
            }
            const blobUrl = URL.createObjectURL(file);
            this.stickerCreator.originalPreviewUrl = blobUrl;
            this.stickerCreator.previewUrl = blobUrl;
            this.stickerCreator.activePreviewUrl = blobUrl;
            this.stickerCreator.zoom = 1;
            this.stickerCreator.panX = 0;
            this.stickerCreator.panY = 0;
            this.stickerCreator.textX = 50;
            this.stickerCreator.textY = 82;

            // Generate smart cutout for stickers (WhatsApp background removal)
            this.generateStickerCutout(file);
        },

        generateStickerCutout(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    try {
                        const cvs = document.createElement('canvas');
                        const maxDim = 600;
                        let w = img.width;
                        let h = img.height;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) {
                                h = Math.round((h * maxDim) / w);
                                w = maxDim;
                            } else {
                                w = Math.round((w * maxDim) / h);
                                h = maxDim;
                            }
                        }
                        cvs.width = w;
                        cvs.height = h;
                        const ctx = cvs.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        const imgData = ctx.getImageData(0, 0, w, h);
                        const data = imgData.data;

                        // Check if already has transparent pixels (> 2% of image)
                        let transparentCount = 0;
                        const sampleStep = 8;
                        for (let i = 3; i < data.length; i += 4 * sampleStep) {
                            if (data[i] < 40) transparentCount++;
                        }
                        if (transparentCount > (data.length / (4 * sampleStep)) * 0.02) {
                            // Already transparent PNG
                            this.stickerCreator.cutoutPreviewUrl = e.target.result;
                            if (this.stickerCreator.hasStickerOutline) {
                                this.stickerCreator.activePreviewUrl = e.target.result;
                            }
                            return;
                        }

                        // Sample border colors to detect background
                        const corners = [
                            [0, 0], [w - 1, 0], [0, h - 1], [w - 1, h - 1],
                            [Math.floor(w / 2), 0], [Math.floor(w / 4), 0], [Math.floor((3 * w) / 4), 0]
                        ];
                        let rSum = 0, gSum = 0, bSum = 0, count = 0;
                        corners.forEach(([cx, cy]) => {
                            const idx = (cy * w + cx) * 4;
                            rSum += data[idx];
                            gSum += data[idx + 1];
                            bSum += data[idx + 2];
                            count++;
                        });
                        const bgR = rSum / count;
                        const bgG = gSum / count;
                        const bgB = bSum / count;
                        const tolerance = 42;

                        // Border-connected flood fill to make background transparent
                        const visited = new Uint8Array(w * h);
                        const queue = [];

                        const checkBorder = (x, y) => {
                            const idx = y * w + x;
                            if (visited[idx]) return;
                            visited[idx] = 1;
                            const p = idx * 4;
                            const dr = data[p] - bgR;
                            const dg = data[p + 1] - bgG;
                            const db = data[p + 2] - bgB;
                            if (Math.sqrt(dr * dr + dg * dg + db * db) <= tolerance) {
                                queue.push(x, y);
                                data[p + 3] = 0; // make transparent
                            }
                        };

                        for (let x = 0; x < w; x++) {
                            checkBorder(x, 0);
                            checkBorder(x, h - 1);
                        }
                        for (let y = 0; y < h; y++) {
                            checkBorder(0, y);
                            checkBorder(w - 1, y);
                        }

                        let head = 0;
                        while (head < queue.length) {
                            const qx = queue[head++];
                            const qy = queue[head++];

                            const neighbors = [
                                [qx + 1, qy],
                                [qx - 1, qy],
                                [qx, qy + 1],
                                [qx, qy - 1]
                            ];

                            for (let n = 0; n < 4; n++) {
                                const nx = neighbors[n][0];
                                const ny = neighbors[n][1];
                                if (nx >= 0 && nx < w && ny >= 0 && ny < h) {
                                    const ni = ny * w + nx;
                                    if (!visited[ni]) {
                                        visited[ni] = 1;
                                        const np = ni * 4;
                                        const dr = data[np] - bgR;
                                        const dg = data[np + 1] - bgG;
                                        const db = data[np + 2] - bgB;
                                        if (Math.sqrt(dr * dr + dg * dg + db * db) <= tolerance) {
                                            data[np + 3] = 0; // transparent
                                            queue.push(nx, ny);
                                        }
                                    }
                                }
                            }
                        }

                        ctx.putImageData(imgData, 0, 0);
                        const cutoutUrl = cvs.toDataURL('image/png');
                        this.stickerCreator.cutoutPreviewUrl = cutoutUrl;
                        if (this.stickerCreator.hasStickerOutline) {
                            this.stickerCreator.activePreviewUrl = cutoutUrl;
                        }
                    } catch (err) {
                        console.warn('Cutout generation error:', err);
                        this.stickerCreator.cutoutPreviewUrl = this.stickerCreator.originalPreviewUrl;
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        // --- Move Image Directly on Canvas (WhatsApp Drag Pan) ---
        startDragStickerImage(e) {
            if (this.stickerCreator.isDraggingText || this.stickerCreator.isZoomingText) return;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            this.stickerCreator.isDraggingImage = true;
            this.stickerCreator.imageDragStartX = clientX;
            this.stickerCreator.imageDragStartY = clientY;
            this.stickerCreator.imageDragInitialPanX = this.stickerCreator.panX || 0;
            this.stickerCreator.imageDragInitialPanY = this.stickerCreator.panY || 0;
        },

        handleDragStickerImage(e) {
            if (!this.stickerCreator.isDraggingImage) return;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            const deltaX = clientX - this.stickerCreator.imageDragStartX;
            const deltaY = clientY - this.stickerCreator.imageDragStartY;
            this.stickerCreator.panX = Math.round(this.stickerCreator.imageDragInitialPanX + deltaX);
            this.stickerCreator.panY = Math.round(this.stickerCreator.imageDragInitialPanY + deltaY);
        },

        stopDragStickerImage() {
            this.stickerCreator.isDraggingImage = false;
        },

        // --- Move Text Directly on Canvas (WhatsApp Text Drag) ---
        startDragStickerText(e) {
            if (e.stopPropagation) e.stopPropagation();
            this.stickerCreator.isDraggingText = true;
            this.stickerCreator.isDraggingImage = false;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            this.stickerCreator.dragStartX = clientX;
            this.stickerCreator.dragStartY = clientY;
            this.stickerCreator.dragInitialTextX = this.stickerCreator.textX;
            this.stickerCreator.dragInitialTextY = this.stickerCreator.textY;
        },

        handleDragStickerText(e) {
            if (!this.stickerCreator.isDraggingText) return;
            const box = document.getElementById('sticker-preview-canvas-box');
            if (!box) return;
            const rect = box.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            const deltaX = ((clientX - this.stickerCreator.dragStartX) / rect.width) * 100;
            const deltaY = ((clientY - this.stickerCreator.dragStartY) / rect.height) * 100;

            let newX = Math.round(this.stickerCreator.dragInitialTextX + deltaX);
            let newY = Math.round(this.stickerCreator.dragInitialTextY + deltaY);

            newX = Math.max(6, Math.min(94, newX));
            newY = Math.max(6, Math.min(94, newY));

            this.stickerCreator.textX = newX;
            this.stickerCreator.textY = newY;
        },

        stopDragStickerText() {
            this.stickerCreator.isDraggingText = false;
        },

        // --- Zoom / Scale Text Directly on Canvas (Corner Drag & Buttons) ---
        startZoomStickerText(e) {
            if (e.stopPropagation) e.stopPropagation();
            if (e.preventDefault) e.preventDefault();
            this.stickerCreator.isZoomingText = true;
            this.stickerCreator.isDraggingText = false;
            this.stickerCreator.isDraggingImage = false;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            this.stickerCreator.zoomTextStartX = clientX;
            this.stickerCreator.zoomTextInitialSize = this.stickerCreator.fontSize || 28;
        },

        handleZoomStickerText(e) {
            if (!this.stickerCreator.isZoomingText) return;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const delta = (clientX - this.stickerCreator.zoomTextStartX) * 0.45;
            let size = Math.round(this.stickerCreator.zoomTextInitialSize + delta);
            this.stickerCreator.fontSize = Math.max(14, Math.min(76, size));
        },

        stopZoomStickerText() {
            this.stickerCreator.isZoomingText = false;
        },

        zoomStickerText(delta) {
            let size = (this.stickerCreator.fontSize || 28) + delta;
            this.stickerCreator.fontSize = Math.max(14, Math.min(76, size));
        },

        zoomStickerImage(delta) {
            let z = (this.stickerCreator.zoom || 1) + delta;
            this.stickerCreator.zoom = Math.max(0.5, Math.min(3.0, Math.round(z * 100) / 100));
        },

        // --- Combined Canvas Listeners ---
        handleCanvasMouseMove(e) {
            if (this.stickerCreator.isZoomingText) {
                this.handleZoomStickerText(e);
            } else if (this.stickerCreator.isDraggingText) {
                this.handleDragStickerText(e);
            } else if (this.stickerCreator.isDraggingImage) {
                this.handleDragStickerImage(e);
            }
        },

        handleCanvasMouseUp() {
            this.stopZoomStickerText();
            this.stopDragStickerText();
            this.stopDragStickerImage();
        },

        handleCanvasTouchMove(e) {
            if (this.stickerCreator.isZoomingText) {
                this.handleZoomStickerText(e);
            } else if (this.stickerCreator.isDraggingText) {
                this.handleDragStickerText(e);
            } else if (this.stickerCreator.isDraggingImage) {
                this.handleDragStickerImage(e);
            }
        },

        handleCanvasTouchEnd() {
            this.stopZoomStickerText();
            this.stopDragStickerText();
            this.stopDragStickerImage();
        },

        handleCanvasWheel(e) {
            if (!this.stickerCreator.previewUrl) return;
            e.preventDefault();
            const delta = e.deltaY < 0 ? 0.08 : -0.08;
            this.zoomStickerImage(delta);
        },

        toggleStickerOutline() {
            this.stickerCreator.hasStickerOutline = !this.stickerCreator.hasStickerOutline;
        },

        appendStickerEmoji(emoji) {
            this.stickerCreator.caption = (this.stickerCreator.caption ? this.stickerCreator.caption + ' ' : '') + emoji;
        },

        setStickerTextPosition(pos) {
            if (pos === 'top') {
                this.stickerCreator.textX = 50;
                this.stickerCreator.textY = 16;
            } else if (pos === 'center') {
                this.stickerCreator.textX = 50;
                this.stickerCreator.textY = 50;
            } else if (pos === 'bottom') {
                this.stickerCreator.textX = 50;
                this.stickerCreator.textY = 82;
            }
        },

        resetStickerTransform() {
            this.stickerCreator.zoom = 1;
            this.stickerCreator.panX = 0;
            this.stickerCreator.panY = 0;
            this.stickerCreator.textX = 50;
            this.stickerCreator.textY = 82;
            this.stickerCreator.fontSize = 28;
            this.showToast('Reset position & zoom', 'info');
        },

        /**
         * Render custom sticker canvas with caption text & outline, save to stickers & send
         */
        async saveAndSendCustomSticker(shouldSend = true) {
            if (!this.stickerCreator.imageFile && !this.stickerCreator.previewUrl) {
                this.showToast('Please select an image first!', 'error');
                return;
            }

            this.stickerCreator.isProcessing = true;

            try {
                // High-DPI canvas 512x512 standard WhatsApp / Telegram sticker
                const canvas = document.createElement('canvas');
                canvas.width = 512;
                canvas.height = 512;
                const ctx = canvas.getContext('2d');

                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.src = this.stickerCreator.activePreviewUrl || this.stickerCreator.previewUrl;

                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = reject;
                });

                ctx.clearRect(0, 0, 512, 512);

                // Preview container in DOM:
                const box = document.getElementById('sticker-preview-canvas-box');
                const boxWidth = box ? box.offsetWidth : 320;
                const scaleFactor = 512 / boxWidth;
                const panX = (parseFloat(this.stickerCreator.panX) || 0) * scaleFactor;
                const panY = (parseFloat(this.stickerCreator.panY) || 0) * scaleFactor;
                const zoom = parseFloat(this.stickerCreator.zoom) || 1;

                let drawW, drawH, drawX, drawY;
                if (this.stickerCreator.cropShape === 'fit') {
                    const fitScale = Math.min(480 / img.width, 480 / img.height) * zoom;
                    drawW = img.width * fitScale;
                    drawH = img.height * fitScale;
                    drawX = (512 - drawW) / 2 + panX;
                    drawY = (512 - drawH) / 2 + panY;
                } else {
                    const fillScale = Math.max(512 / img.width, 512 / img.height) * zoom;
                    drawW = img.width * fillScale;
                    drawH = img.height * fillScale;
                    drawX = (512 - drawW) / 2 + panX;
                    drawY = (512 - drawH) / 2 + panY;
                }

                // If WhatsApp White Sticker Outline is enabled, draw multi-pass solid white contour outline!
                if (this.stickerCreator.hasStickerOutline) {
                    try {
                        const silCanvas = document.createElement('canvas');
                        silCanvas.width = 512;
                        silCanvas.height = 512;
                        const silCtx = silCanvas.getContext('2d');
                        silCtx.drawImage(img, drawX, drawY, drawW, drawH);
                        silCtx.globalCompositeOperation = 'source-in';
                        silCtx.fillStyle = '#ffffff';
                        silCtx.fillRect(0, 0, 512, 512);

                        ctx.save();
                        const outlineRadius = 6;
                        for (let r = 2; r <= outlineRadius; r += 2) {
                            for (let a = 0; a < Math.PI * 2; a += Math.PI / 8) {
                                ctx.drawImage(silCanvas, Math.cos(a) * r, Math.sin(a) * r);
                            }
                        }
                        ctx.shadowColor = '#ffffff';
                        ctx.shadowBlur = 4;
                        ctx.drawImage(silCanvas, 0, 0);
                        ctx.restore();
                    } catch (e) {
                        ctx.save();
                        ctx.shadowColor = '#ffffff';
                        ctx.shadowBlur = 12;
                        for (let i = 0; i < 4; i++) {
                            ctx.drawImage(img, drawX, drawY, drawW, drawH);
                        }
                        ctx.restore();
                    }
                }

                // Apply clipping shape if selected
                if (this.stickerCreator.cropShape === 'circle') {
                    ctx.save();
                    ctx.beginPath();
                    ctx.arc(256, 256, 246, 0, Math.PI * 2);
                    ctx.clip();
                } else if (this.stickerCreator.cropShape === 'square') {
                    ctx.save();
                    ctx.beginPath();
                    const r = 36;
                    ctx.moveTo(16 + r, 16);
                    ctx.lineTo(496 - r, 16);
                    ctx.quadraticCurveTo(496, 16, 496, 16 + r);
                    ctx.lineTo(496, 496 - r);
                    ctx.quadraticCurveTo(496, 496, 496 - r, 496);
                    ctx.lineTo(16 + r, 496);
                    ctx.quadraticCurveTo(16, 496, 16, 496 - r);
                    ctx.lineTo(16, 16 + r);
                    ctx.quadraticCurveTo(16, 16, 16 + r, 16);
                    ctx.closePath();
                    ctx.clip();
                }

                // Draw main image
                ctx.drawImage(img, drawX, drawY, drawW, drawH);

                if (this.stickerCreator.cropShape === 'circle' || this.stickerCreator.cropShape === 'square') {
                    ctx.restore();
                }

                // Render Draggable & Zoomed Text at user's exact coordinates
                const caption = (this.stickerCreator.caption || '').trim();
                if (caption) {
                    const fontSize = Math.round((this.stickerCreator.fontSize || 28) * scaleFactor);
                    const fontFamily = this.stickerCreator.fontFamily || 'Impact';
                    ctx.font = `900 ${fontSize}px ${fontFamily}, system-ui, -apple-system, sans-serif`;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    const textCanvasX = (this.stickerCreator.textX / 100) * 512;
                    const textCanvasY = (this.stickerCreator.textY / 100) * 512;

                    // Render background badge if selected
                    if (this.stickerCreator.textBgStyle && this.stickerCreator.textBgStyle !== 'none') {
                        const metrics = ctx.measureText(caption);
                        const textW = metrics.width;
                        const padX = 22;
                        const padY = 12;
                        const boxX = textCanvasX - textW / 2 - padX;
                        const boxY = textCanvasY - fontSize / 2 - padY / 2;
                        const boxW = textW + padX * 2;
                        const boxH = fontSize + padY;
                        const r = Math.min(20, boxH / 2);

                        ctx.save();
                        ctx.beginPath();
                        ctx.moveTo(boxX + r, boxY);
                        ctx.lineTo(boxX + boxW - r, boxY);
                        ctx.quadraticCurveTo(boxX + boxW, boxY, boxX + boxW, boxY + r);
                        ctx.lineTo(boxX + boxW, boxY + boxH - r);
                        ctx.quadraticCurveTo(boxX + boxW, boxY + boxH, boxX + boxW - r, boxY + boxH);
                        ctx.lineTo(boxX + r, boxY + boxH);
                        ctx.quadraticCurveTo(boxX, boxY + boxH, boxX, boxY + boxH - r);
                        ctx.lineTo(boxX, boxY + r);
                        ctx.quadraticCurveTo(boxX, boxY, boxX + r, boxY);
                        ctx.closePath();

                        if (this.stickerCreator.textBgStyle === 'badge_dark') {
                            ctx.fillStyle = 'rgba(0, 0, 0, 0.82)';
                        } else if (this.stickerCreator.textBgStyle === 'badge_light') {
                            ctx.fillStyle = 'rgba(255, 255, 255, 0.94)';
                        } else if (this.stickerCreator.textBgStyle === 'badge_color') {
                            ctx.fillStyle = this.stickerCreator.strokeColor || '#000000';
                        }
                        ctx.fill();
                        ctx.restore();
                    }

                    // Render Text Outline Stroke
                    if (this.stickerCreator.hasStroke) {
                        ctx.lineWidth = Math.max(4, Math.round(fontSize * 0.18));
                        ctx.strokeStyle = this.stickerCreator.strokeColor || '#000000';
                        ctx.lineJoin = 'round';
                        ctx.miterLimit = 2;
                        ctx.strokeText(caption, textCanvasX, textCanvasY);
                    }

                    // Render Text Fill Color
                    ctx.fillStyle = this.stickerCreator.textColor || '#ffffff';
                    ctx.fillText(caption, textCanvasX, textCanvasY);
                }

                const dataUri = canvas.toDataURL('image/png', 0.95);

                // Close modal immediately for instant snappy UX
                this.stickerCreatorModal = false;

                const stickerName = caption || 'Custom Sticker';
                const tempStk = {
                    id: 'temp_stk_' + Date.now(),
                    name: stickerName,
                    image_url: dataUri,
                    image_path: dataUri,
                    category: 'custom',
                    user_id: Number(this.currentUser.id),
                    is_public: false
                };
                this.stickers.unshift(tempStk);
                this.showToast('Custom sticker created! ⭐', 'success');

                if (shouldSend) {
                    await this.sendSticker(tempStk);
                }

                // Persist sticker to backend in background
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('name', stickerName);
                fd.append('image_data', dataUri);
                fd.append('category', 'custom');

                const res = await fetch('/chat/stickers', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await res.json();
                if (data.success && data.sticker) {
                    const idx = this.stickers.findIndex(s => s.id === tempStk.id);
                    if (idx !== -1) {
                        this.stickers.splice(idx, 1, data.sticker);
                    }
                }
            } catch (err) {
                console.error('Error creating custom sticker:', err);
                this.showToast('Failed to create sticker.', 'error');
            } finally {
                this.stickerCreator.isProcessing = false;
            }
        },

        /**
         * Open WhatsApp Web Style Profile Modal
         */
        openProfileModal(tab = 'details') {
            if (!this.activeConversation) return;
            this.profileTab = tab;
            this.profileModal = true;
            this.isEditingGroupDetails = false;
            this.groupEditForm = {
                title: this.activeConversation.title || '',
                description: this.activeConversation.description || ''
            };
            this.loadSharedMedia();
        },

        /**
         * Open Media, Docs, and Links view in Profile Modal (WhatsApp style)
         */
        openMediaDocsTab(tab = 'media') {
            this.profileTab = tab;
            this.profileModal = true;
            this.loadSharedMedia();
        },

        /**
         * Load Shared Media, Docs, and Links for active conversation
         */
        async loadSharedMedia() {
            if (!this.activeConversation) return;
            this.isLoadingSharedMedia = true;
            try {
                const res = await fetch(`/chat/conversations/${this.activeConversation.id}/media`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success) {
                    const media = data.media || [];
                    const docs  = data.docs || [];
                    const links = data.links || [];
                    this.sharedMediaData = {
                        media,
                        docs,
                        links,
                        counts: data.counts || {
                            media: media.length,
                            docs: docs.length,
                            links: links.length,
                            total: media.length + docs.length + links.length
                        }
                    };
                }
            } catch (err) {
                console.warn('Failed to load shared media:', err);
            } finally {
                this.isLoadingSharedMedia = false;
            }
        },

        /**
         * Save Group Profile Edits (title / description)
         */
        async saveGroupProfile() {
            if (!this.activeConversation || this.activeConversation.type !== 'group') return;
            this.isSavingGroupProfile = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const fd = new FormData();
                fd.append('title', this.groupEditForm.title);
                fd.append('description', this.groupEditForm.description);

                const res = await fetch(`/chat/conversations/${this.activeConversation.id}/profile`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await res.json();
                if (data.success && data.conversation) {
                    this.activeConversation.title = data.conversation.title;
                    this.activeConversation.description = data.conversation.description;
                    const c = this.conversations.find(item => item.id === this.activeConversation.id);
                    if (c) {
                        c.title = data.conversation.title;
                        c.description = data.conversation.description;
                    }
                    this.isEditingGroupDetails = false;
                    this.showToast('Group details updated!', 'success');
                } else {
                    this.showToast(data.error || 'Failed to update profile', 'error');
                }
            } catch (err) {
                console.error('Failed to save group profile:', err);
                this.showToast('Network error while saving profile.', 'error');
            } finally {
                this.isSavingGroupProfile = false;
            }
        },

        /**
         * Start a new direct 1-on-1 chat
         */
        async startPrivateChat(userId) {
            this.newChatModal = false;

            // Instant Switch: If direct chat with this colleague already exists in conversation list, open immediately
            const existing = this.conversations.find(c => 
                (c.type === 'direct' || c.type === 'private') && (c.other_user_id === userId || (c.members && c.members.some(m => m.user_id === userId)))
            );
            if (existing) {
                this.selectConversation(existing);
                return;
            }

            try {
                const res = await fetch('/chat/conversations/private', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ user_id: userId })
                });
                const data = await res.json();
                if (data.success && data.conversation) {
                    const found = this.conversations.find(c => c.id === data.conversation.id);
                    if (!found) {
                        this.conversations.unshift(data.conversation);
                    }
                    this.selectConversation(data.conversation);
                }
            } catch (err) {
                this.showToast('Could not start conversation', 'error');
            }
        },

        /**
         * Create a new Group Chat
         */
        async createGroup() {
            const title = this.groupTitle.trim();
            if (!title) {
                this.showToast('Please enter a group title', 'error');
                return;
            }
            if (this.selectedGroupUserIds.length === 0) {
                this.showToast('Select at least one member to add', 'error');
                return;
            }

            try {
                const res = await fetch('/chat/conversations/group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        title: title,
                        description: this.groupDescription.trim(),
                        user_ids: this.selectedGroupUserIds
                    })
                });

                const data = await res.json();
                if (data.success && data.conversation) {
                    this.conversations.unshift(data.conversation);
                    this.newGroupModal = false;
                    this.groupTitle = '';
                    this.groupDescription = '';
                    this.selectedGroupUserIds = [];
                    this.selectConversation(data.conversation);
                    this.showToast('Group created successfully!', 'success');
                }
            } catch (err) {
                this.showToast('Failed to create group', 'error');
            }
        },

        /**
         * Open Saved Messages conversation (Personal bookmarks & notes)
         */
        async openSavedMessages() {
            try {
                const res = await fetch('/chat/saved-messages', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success && data.conversation) {
                    const existing = this.conversations.find(c => c.id === data.conversation.id);
                    if (!existing) {
                        this.conversations.unshift(data.conversation);
                    }
                    this.selectConversation(existing || data.conversation);
                }
            } catch (err) {
                this.showToast('Could not open Saved Messages', 'error');
            }
        },

        /**
         * Open Poll Modal
         */
        openPollModal() {
            this.showAttachMenu = false;
            this.pollQuestion = '';
            this.pollOptions = ['', ''];
            this.pollMultipleAnswers = false;
            this.pollIsAnonymous = false;
            this.pollModal = true;
        },

        addPollOption() {
            if (this.pollOptions.length < 10) {
                this.pollOptions.push('');
            }
        },

        removePollOption(index) {
            if (this.pollOptions.length > 2) {
                this.pollOptions.splice(index, 1);
            }
        },

        /**
         * Submit New Poll
         */
        async submitPoll() {
            if (!this.activeConversation) return;
            const q = this.pollQuestion.trim();
            const cleanOptions = this.pollOptions.map(o => o.trim()).filter(o => o.length > 0);

            if (!q) {
                this.showToast('Please enter a poll question', 'error');
                return;
            }
            if (cleanOptions.length < 2) {
                this.showToast('Poll requires at least 2 options', 'error');
                return;
            }

            this.isSubmittingPoll = true;
            try {
                const res = await fetch(`/chat/conversations/${this.activeConversation.id}/poll`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        question: q,
                        options: cleanOptions,
                        multiple_answers: this.pollMultipleAnswers,
                        is_anonymous: this.pollIsAnonymous
                    })
                });

                const data = await res.json();
                if (data.success && data.message) {
                    this.messages.push(data.message);
                    this.lastSyncId = Math.max(this.lastSyncId, data.message.id);
                    this.updateConversationLastMessage(this.activeConversation.id, data.message);
                    this.pollModal = false;
                    this.showToast('Poll published!', 'success');
                    this.$nextTick(() => this.scrollToBottom(true));
                } else {
                    this.showToast(data.error || 'Failed to create poll', 'error');
                }
            } catch (err) {
                this.showToast('Failed to create poll', 'error');
            } finally {
                this.isSubmittingPoll = false;
            }
        },

        /**
         * Vote in a poll
         */
        async votePoll(message, optionId) {
            if (!message || !message.poll_data) return;

            // Optimistic update for instant 0ms tactile feedback
            const p = message.poll_data;
            if (p && p.options) {
                const targetOpt = p.options.find(o => o.id === optionId);
                if (targetOpt) {
                    const wasVoted = !!(targetOpt.user_voted || targetOpt.voted);
                    if (!p.multiple_answers) {
                        p.options.forEach(o => {
                            o.user_voted = false;
                            o.voted = false;
                        });
                    }
                    targetOpt.user_voted = !wasVoted;
                    targetOpt.voted = !wasVoted;
                    p.has_voted = p.options.some(o => o.user_voted || o.voted);
                    p.user_has_voted = p.has_voted;
                    this.messages = [...this.messages];
                }
            }

            try {
                const res = await fetch(`/chat/messages/${message.id}/vote`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ option_id: optionId })
                });

                const data = await res.json();
                if (data.success && data.message) {
                    const idx = this.messages.findIndex(m => m.id === message.id);
                    if (idx !== -1) {
                        this.messages.splice(idx, 1, data.message);
                        this.messages = [...this.messages];
                    }
                } else {
                    this.showToast(data.error || 'Vote could not be registered', 'error');
                }
            } catch (err) {
                this.showToast('Failed to register vote', 'error');
            }
        },

        /**
         * Unsend Message (Telegram & WhatsApp clean style - deletes completely for everyone)
         */
        async unsendMessage(msg) {
            if (!msg) return;
            return this.deleteMessage(msg, 'everyone');
        },

        /**
         * Open Forward Modal
         */
        openForwardModal(msg) {
            this.messageToForward = msg;
            this.forwardSearch = '';
            this.forwardModal = true;
        },

        /**
         * Forward message to target conversation
         */
        async forwardToConversation(targetConv) {
            if (!this.messageToForward || !targetConv) return;
            this.isForwarding = true;

            try {
                const res = await fetch(`/chat/messages/${this.messageToForward.id}/forward`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ target_conversation_id: targetConv.id })
                });

                const data = await res.json();
                if (data.success) {
                    this.forwardModal = false;
                    this.messageToForward = null;
                    this.showToast(`Message forwarded to ${targetConv.title}!`, 'success');

                    if (this.activeConversation && this.activeConversation.id === targetConv.id && data.message) {
                        this.messages.push(data.message);
                        this.$nextTick(() => this.scrollToBottom(true));
                    }
                } else {
                    this.showToast(data.error || 'Failed to forward message', 'error');
                }
            } catch (err) {
                this.showToast('Failed to forward message', 'error');
            } finally {
                this.isForwarding = false;
            }
        },

        /**
         * Save Chat Settings
         */
        async saveSettings() {
            try {
                const res = await fetch('/chat/settings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(this.userSettings)
                });
                const data = await res.json();
                if (data.success) {
                    this.applyAppearance();
                    this.settingsModal = false;
                    this.showToast('Settings saved successfully!', 'success');
                }
            } catch (err) {
                this.showToast('Failed to save settings', 'error');
            }
        },

        /**
         * Reset settings to default
         */
        resetSettings() {
            this.userSettings = {
                theme: 'system',
                bubble_color: '#4f46e5',
                wallpaper: 'default',
                font_size: 'medium',
                notification_sound: true,
                status_message: 'Available'
            };
            this.saveSettings();
        },

        /**
         * Fetch users for chat access permission management (Dara & Super Admin)
         */
        async loadAdminUsers() {
            if (!this.currentUser.can_manage_permissions) return;
            this.adminLoading = true;
            try {
                const res = await fetch('/chat/admin/users-permissions', { credentials: 'same-origin' });
                const data = await res.json();
                if (data.success && data.users) {
                    this.adminUsers = data.users;
                }
            } catch (err) {
                console.error('Failed to load admin users:', err);
            } finally {
                this.adminLoading = false;
            }
        },

        async toggleAdminUser(u) {
            if (u.is_manager) return;
            const original = u.can_use_chat;
            u.can_use_chat = !original;
            try {
                const res = await fetch('/chat/admin/toggle-user-chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ user_id: u.id, can_use_chat: u.can_use_chat })
                });
                const data = await res.json();
                if (data.success) {
                    this.showToast(data.message, 'success');
                } else {
                    u.can_use_chat = original;
                    this.showToast(data.error || 'Failed to update user', 'error');
                }
            } catch (err) {
                u.can_use_chat = original;
                this.showToast('Network error while updating user', 'error');
            }
        },

        async batchToggleAdmin(enable) {
            if (!confirm(`Are you sure you want to turn Chat ${enable ? 'ON' : 'OFF'} for all users?`)) return;
            try {
                const res = await fetch('/chat/admin/batch-toggle-chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ enable })
                });
                const data = await res.json();
                if (data.success) {
                    this.adminUsers.forEach(u => { if (!u.is_manager) u.can_use_chat = enable; });
                    this.showToast(data.message, 'success');
                }
            } catch (err) {
                this.showToast('Failed to update users', 'error');
            }
        },

        async runConvertWebp() {
            this.convertingWebp = true;
            try {
                const res = await fetch('/chat/admin/convert-webp-now', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success) {
                    this.showToast(data.output || 'Images converted to WebP successfully!', 'success');
                } else {
                    this.showToast('Conversion failed', 'error');
                }
            } catch (err) {
                this.showToast('Failed to run WebP conversion', 'error');
            } finally {
                this.convertingWebp = false;
            }
        },

        /**
         * Open Fullscreen Media Viewer with Next/Prev and Video Playback (Telegram-style)
         */
        /**
         * Open Fullscreen Media Viewer with Next/Prev, Zoom, and Video Playback (Telegram / WhatsApp / Apple Photos style)
         */
        openMediaViewer(attachmentOrId, activeMsgOrId = null) {
            // Guard against accidental DOM event
            if (attachmentOrId && (attachmentOrId.preventDefault || attachmentOrId.stopPropagation) && attachmentOrId.target) {
                const targetImg = attachmentOrId.target.closest('img') || attachmentOrId.target.querySelector('img');
                if (targetImg && targetImg.src) {
                    attachmentOrId = targetImg.src;
                }
            }

            // If a string URL was passed, open direct lightbox
            if (typeof attachmentOrId === 'string' && (attachmentOrId.startsWith('http') || attachmentOrId.startsWith('/') || attachmentOrId.startsWith('blob:') || attachmentOrId.startsWith('data:'))) {
                this.openLightbox(attachmentOrId, 'Media', 'image');
                return;
            }

            // If a message was passed instead of an attachment (e.g. from context menu "View")
            if (attachmentOrId && attachmentOrId.attachments && Array.isArray(attachmentOrId.attachments) && attachmentOrId.attachments.length > 0) {
                activeMsgOrId = attachmentOrId;
                const foundMediaAtt = attachmentOrId.attachments.find(a => this.isImageAttachment(a) || this.isVideoAttachment(a, attachmentOrId));
                attachmentOrId = foundMediaAtt || attachmentOrId.attachments[0];
            } else if (attachmentOrId && attachmentOrId.attachments && typeof attachmentOrId.attachments === 'object') {
                const attList = Object.values(attachmentOrId.attachments);
                if (attList.length > 0) {
                    activeMsgOrId = attachmentOrId;
                    const foundMediaAtt = attList.find(a => this.isImageAttachment(a) || this.isVideoAttachment(a, attachmentOrId));
                    attachmentOrId = foundMediaAtt || attList[0];
                }
            }

            // If sticker/gif message passed
            if (attachmentOrId && (attachmentOrId.type === 'sticker' || attachmentOrId.type === 'gif')) {
                const directUrl = attachmentOrId.body || attachmentOrId.content || attachmentOrId.sticker_url;
                if (directUrl) {
                    this.openLightbox(directUrl, attachmentOrId.type === 'sticker' ? 'Sticker' : 'GIF', 'image');
                    return;
                }
            }

            const seenIds = new Set();
            const mediaItems = [];

            // Helper to get reliable streamable URL for images/media
            const resolveMediaUrl = (att) => {
                if (!att) return '';
                if (typeof att === 'string') return att;
                if (typeof att.file_url === 'string' && (att.file_url.startsWith('blob:') || att.file_url.startsWith('data:'))) {
                    return att.file_url;
                }
                if (att.view_url) return att.view_url;
                if (att.id && !att.conversation_id) return `/chat/attachments/${att.id}/view`;
                if (att.file_url) return att.file_url;
                if (att.file_path) {
                    if (att.file_path.startsWith('http://') || att.file_path.startsWith('https://') || att.file_path.startsWith('/')) {
                        return att.file_path;
                    }
                    return `/storage/${att.file_path}`;
                }
                if (att.body && (att.body.startsWith('http://') || att.body.startsWith('https://') || att.body.startsWith('/'))) {
                    return att.body;
                }
                return '';
            };

            const resolveDownloadUrl = (att) => {
                if (!att) return '';
                if (typeof att === 'string') return att;
                if (att.download_url) return att.download_url;
                if (att.id && !att.conversation_id) return `/chat/attachments/${att.id}/download`;
                return resolveMediaUrl(att);
            };

            let directAtt = (typeof attachmentOrId === 'object' && attachmentOrId !== null) ? attachmentOrId : null;
            let targetId = directAtt ? directAtt.id : attachmentOrId;
            let targetUrl = directAtt ? resolveMediaUrl(directAtt) : (typeof attachmentOrId === 'string' ? attachmentOrId : '');

            // 1. First, search current in-memory conversation messages
            if (Array.isArray(this.messages)) {
                this.messages.forEach(msg => {
                    const attachments = Array.isArray(msg.attachments) ? msg.attachments : (msg.attachments && typeof msg.attachments === 'object' ? Object.values(msg.attachments) : []);
                    if (attachments && attachments.length > 0) {
                        attachments.forEach(att => {
                            if (!att) return;
                            const isImg = this.isImageAttachment(att);
                            const isVid = this.isVideoAttachment(att, msg);
                            if (isImg || isVid) {
                                const idKey = att.id || att.file_name || att.file_url || att.file_path;
                                if (idKey && seenIds.has(idKey)) return;
                                if (idKey) seenIds.add(idKey);

                                const viewUrl = resolveMediaUrl(att);
                                mediaItems.push({
                                    id: att.id,
                                    type: isVid ? 'video' : 'image',
                                    name: att.file_name || 'Media',
                                    url: viewUrl,
                                    view_url: viewUrl,
                                    download_url: resolveDownloadUrl(att),
                                    size: att.file_size || '',
                                    sender_name: msg.sender_name || msg.user?.name || 'User',
                                    time: msg.formatted_time || msg.created_at_human || ''
                                });
                            }
                        });
                    }
                });
            }

            // 2. Also include from sharedMediaData.media if loaded
            if (this.sharedMediaData && Array.isArray(this.sharedMediaData.media)) {
                this.sharedMediaData.media.forEach(att => {
                    if (!att) return;
                    const isImg = this.isImageAttachment(att);
                    const isVid = this.isVideoAttachment(att);
                    if (isImg || isVid) {
                        const idKey = att.id || att.file_name || att.file_url || att.file_path;
                        if (idKey && seenIds.has(idKey)) return;
                        if (idKey) seenIds.add(idKey);

                        const viewUrl = resolveMediaUrl(att);
                        mediaItems.push({
                            id: att.id,
                            type: isVid ? 'video' : 'image',
                            name: att.file_name || 'Media',
                            url: viewUrl,
                            view_url: viewUrl,
                            download_url: resolveDownloadUrl(att),
                            size: att.file_size || '',
                            sender_name: att.sender_name || 'User',
                            time: att.created_at || ''
                        });
                    }
                });
            }

            // 3. If direct attachment was passed and not found in mediaItems, prepend it
            if (directAtt) {
                const foundInMedia = mediaItems.some(item => (targetId && item.id == targetId) || (targetUrl && item.url === targetUrl));
                if (!foundInMedia) {
                    const isVid = this.isVideoAttachment(directAtt, activeMsgOrId);
                    const viewUrl = resolveMediaUrl(directAtt);
                    if (viewUrl) {
                        mediaItems.unshift({
                            id: directAtt.id || 0,
                            type: isVid ? 'video' : 'image',
                            name: directAtt.file_name || 'Media',
                            url: viewUrl,
                            view_url: viewUrl,
                            download_url: resolveDownloadUrl(directAtt),
                            size: directAtt.file_size || '',
                            sender_name: directAtt.sender_name || (activeMsgOrId?.user?.name) || 'User',
                            time: directAtt.created_at || (activeMsgOrId?.formatted_time) || ''
                        });
                    }
                }
            }

            // If still no media items, try to view the direct URL
            if (mediaItems.length === 0) {
                if (directAtt) {
                    const viewUrl = resolveMediaUrl(directAtt);
                    if (viewUrl) {
                        this.openLightbox(viewUrl, directAtt.file_name || 'Media', this.isVideoAttachment(directAtt, activeMsgOrId) ? 'video' : 'image');
                    }
                }
                return;
            }

            // Find target index
            let targetIdx = -1;
            if (targetId != null) {
                targetIdx = mediaItems.findIndex(item => item.id == targetId);
            }
            if (targetIdx === -1 && targetUrl) {
                targetIdx = mediaItems.findIndex(item => item.url === targetUrl);
            }
            if (targetIdx === -1 && directAtt && directAtt.file_name) {
                targetIdx = mediaItems.findIndex(item => item.name === directAtt.file_name);
            }
            if (targetIdx === -1) {
                targetIdx = 0;
            }

            const selected = mediaItems[targetIdx];
            this.resetZoom();

            // Set state on reactive proxy
            this.lightbox.items = mediaItems;
            this.lightbox.currentIndex = targetIdx;
            this.lightbox.url = selected.url;
            this.lightbox.view_url = selected.view_url || selected.url;
            this.lightbox.name = selected.name;
            this.lightbox.type = selected.type;
            this.lightbox.download_url = selected.download_url;
            this.lightbox.open = true;
        },

        openLightbox(url, name, type = 'image') {
            const resolvedUrl = url || '';
            const item = {
                id: 0,
                type: type,
                name: name || 'Media',
                url: resolvedUrl,
                view_url: resolvedUrl,
                download_url: resolvedUrl,
                sender_name: 'Media',
                time: ''
            };
            this.resetZoom();
            this.lightbox.items = [item];
            this.lightbox.currentIndex = 0;
            this.lightbox.url = resolvedUrl;
            this.lightbox.view_url = resolvedUrl;
            this.lightbox.name = name || 'Media';
            this.lightbox.type = type;
            this.lightbox.download_url = resolvedUrl;
            this.lightbox.open = true;
        },

        closeLightbox() {
            this.lightbox.open = false;
            this.resetZoom();
        },

        nextMedia() {
            if (!this.lightbox.items || !this.lightbox.items.length) return;
            this.lightbox.currentIndex = (this.lightbox.currentIndex + 1) % this.lightbox.items.length;
            this.resetZoom();
            const cur = this.lightbox.items[this.lightbox.currentIndex];
            this.lightbox.url = cur.url;
            this.lightbox.view_url = cur.view_url || cur.url;
            this.lightbox.name = cur.name;
            this.lightbox.type = cur.type;
            this.lightbox.download_url = cur.download_url;
        },

        prevMedia() {
            if (!this.lightbox.items || !this.lightbox.items.length) return;
            this.lightbox.currentIndex = (this.lightbox.currentIndex - 1 + this.lightbox.items.length) % this.lightbox.items.length;
            this.resetZoom();
            const cur = this.lightbox.items[this.lightbox.currentIndex];
            this.lightbox.url = cur.url;
            this.lightbox.view_url = cur.view_url || cur.url;
            this.lightbox.name = cur.name;
            this.lightbox.type = cur.type;
            this.lightbox.download_url = cur.download_url;
        },

        resetZoom() {
            this.lightbox.scale = 1;
            this.lightbox.zoom = 1;
            this.lightbox.panX = 0;
            this.lightbox.panY = 0;
            this.lightbox.isDragging = false;
            this.lightbox.dragMoved = false;
        },

        zoomIn() {
            const next = Math.min(8, +(this.lightbox.scale + 0.5).toFixed(2));
            this.lightbox.scale = next;
            this.lightbox.zoom = next;
        },

        zoomOut() {
            const next = Math.max(0.5, +(this.lightbox.scale - 0.5).toFixed(2));
            this.lightbox.scale = next;
            this.lightbox.zoom = next;
            if (next <= 1) {
                this.lightbox.panX = 0;
                this.lightbox.panY = 0;
            }
        },

        toggleClickZoom(e) {
            if (this.lightbox.dragMoved) {
                this.lightbox.dragMoved = false;
                return;
            }
            if (this.lightbox.scale === 1) {
                this.lightbox.scale = 2;
                this.lightbox.zoom = 2;
            } else {
                this.resetZoom();
            }
        },

        handleImageClick(e) {
            if (this.lightbox.dragMoved) {
                this.lightbox.dragMoved = false;
                return;
            }
            this.toggleClickZoom(e);
        },

        startDrag(e) {
            if (e.button !== 0) return; // Left click only
            this.lightbox.startX = e.clientX - this.lightbox.panX;
            this.lightbox.startY = e.clientY - this.lightbox.panY;
            this.lightbox.pointerDownX = e.clientX;
            this.lightbox.pointerDownY = e.clientY;
            this.lightbox.dragMoved = false;
            if (this.lightbox.scale > 1) {
                this.lightbox.isDragging = true;
                e.preventDefault();
            }
        },

        onDrag(e) {
            if (!this.lightbox.isDragging && this.lightbox.scale > 1) {
                const dx = Math.abs(e.clientX - (this.lightbox.pointerDownX || e.clientX));
                const dy = Math.abs(e.clientY - (this.lightbox.pointerDownY || e.clientY));
                if (dx > 4 || dy > 4) {
                    this.lightbox.isDragging = true;
                }
            }
            if (!this.lightbox.isDragging) return;
            const newPanX = e.clientX - this.lightbox.startX;
            const newPanY = e.clientY - this.lightbox.startY;
            if (Math.abs(newPanX - this.lightbox.panX) > 2 || Math.abs(newPanY - this.lightbox.panY) > 2) {
                this.lightbox.dragMoved = true;
            }
            this.lightbox.panX = newPanX;
            this.lightbox.panY = newPanY;
        },

        stopDrag(e) {
            this.lightbox.isDragging = false;
            setTimeout(() => {
                this.lightbox.dragMoved = false;
            }, 60);
        },

        handleWheel(e) {
            e.preventDefault();
            const isPinch = e.ctrlKey;
            const delta = isPinch ? -e.deltaY * 0.02 : (e.deltaY < 0 ? 0.3 : -0.3);
            let nextScale = +(this.lightbox.scale + delta).toFixed(2);
            nextScale = Math.min(Math.max(0.5, nextScale), 8);

            this.lightbox.scale = nextScale;
            this.lightbox.zoom = nextScale;
            if (nextScale <= 1) {
                this.lightbox.panX = 0;
                this.lightbox.panY = 0;
            }
        },

        handleTouchStart(e) {
            if (e.touches.length === 2) {
                this.lightbox.initialDistance = Math.hypot(
                    e.touches[0].pageX - e.touches[1].pageX,
                    e.touches[0].pageY - e.touches[1].pageY
                );
                this.lightbox.initialScale = this.lightbox.scale;
            } else if (e.touches.length === 1) {
                this.lightbox.isDragging = true;
                this.lightbox.dragMoved = false;
                this.lightbox.startX = e.touches[0].pageX - this.lightbox.panX;
                this.lightbox.startY = e.touches[0].pageY - this.lightbox.panY;
            }
        },

        handleTouchMove(e) {
            if (e.touches.length === 2 && this.lightbox.initialDistance > 0) {
                e.preventDefault();
                const currentDistance = Math.hypot(
                    e.touches[0].pageX - e.touches[1].pageX,
                    e.touches[0].pageY - e.touches[1].pageY
                );
                const ratio = currentDistance / this.lightbox.initialDistance;
                this.lightbox.scale = Math.min(Math.max(0.5, +(this.lightbox.initialScale * ratio).toFixed(2)), 8);
                this.lightbox.zoom = this.lightbox.scale;
            } else if (e.touches.length === 1 && this.lightbox.isDragging) {
                e.preventDefault();
                const newPanX = e.touches[0].pageX - this.lightbox.startX;
                const newPanY = e.touches[0].pageY - this.lightbox.startY;
                if (Math.abs(newPanX - this.lightbox.panX) > 3 || Math.abs(newPanY - this.lightbox.panY) > 3) {
                    this.lightbox.dragMoved = true;
                }
                this.lightbox.panX = newPanX;
                this.lightbox.panY = newPanY;
            }
        },

        handleTouchEnd(e) {
            if (e.touches.length < 2) {
                this.lightbox.initialDistance = 0;
            }
            if (e.touches.length === 0) {
                this.lightbox.isDragging = false;
            }
        },

        currentMediaItem() {
            if (!this.lightbox.items || !this.lightbox.items.length) return null;
            return this.lightbox.items[this.lightbox.currentIndex] || null;
        },

        /**
         * Re-pin scroll to bottom when GIFs, stickers, or image attachments finish loading
         */
        onMessageMediaLoaded() {
            const container = document.getElementById('chat-messages-container');
            if (!container) return;
            const isForceWindow = Date.now() < (this._forceBottomUntil || 0) || (Date.now() - (this._lastConvOpenAt || 0)) < 4000;
            const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 350;
            if (isForceWindow || isNearBottom) {
                container.scrollTop = container.scrollHeight + 2000;
                this.showScrollBottomBtn = false;
                requestAnimationFrame(() => {
                    container.scrollTop = container.scrollHeight + 2000;
                    this.showScrollBottomBtn = false;
                });
            }
        },

        /**
         * Scroll message container to bottom
         */
        scrollToBottom(force = false) {
            const container = document.getElementById('chat-messages-container');
            if (!container) return;
            if (force) {
                this._forceBottomUntil = Math.max(this._forceBottomUntil || 0, Date.now() + 2500);
                this.showScrollBottomBtn = false;
                if (!container._manualScrollBound) {
                    container._manualScrollBound = true;
                    container.addEventListener('wheel', (e) => {
                        if (e.deltaY < -5) this._forceBottomUntil = 0;
                    }, { passive: true });
                    container.addEventListener('touchmove', () => {
                        const dist = container.scrollHeight - container.scrollTop - container.clientHeight;
                        if (dist > 220) this._forceBottomUntil = 0;
                    }, { passive: true });
                }
            }
            const scroll = () => {
                const el = document.getElementById('chat-messages-container');
                if (!el) return;
                const isForceWindow = Date.now() < (this._forceBottomUntil || 0);
                const isNearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 300;
                if (isForceWindow || isNearBottom) {
                    el.scrollTop = el.scrollHeight + 2000;
                    this.showScrollBottomBtn = false;
                }
            };
            scroll();
            requestAnimationFrame(scroll);
            setTimeout(scroll, 50);
            setTimeout(scroll, 150);
            setTimeout(scroll, 320);
            setTimeout(scroll, 600);
            setTimeout(scroll, 1000);
        },

        /**
         * Notification Sound: Plays the exact board notification sound effect (fallback to Web Audio synth)
         */
        playChime() {
            try {
                if (typeof window.playNotificationSound === 'function') {
                    window.playNotificationSound();
                    return;
                }
                const audio = document.getElementById('notif-sound');
                if (audio) {
                    audio.currentTime = 0;
                    audio.volume = 1.0;
                    audio.play().catch(() => {});
                    return;
                }

                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;

                if (!this.audioCtx) {
                    this.audioCtx = new AudioContext();
                }

                if (this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }

                const now = this.audioCtx.currentTime;

                // High chime tone (note 1)
                const osc1 = this.audioCtx.createOscillator();
                const gain1 = this.audioCtx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(880, now); // A5
                gain1.gain.setValueAtTime(0.12, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.18);
                osc1.connect(gain1);
                gain1.connect(this.audioCtx.destination);
                osc1.start(now);
                osc1.stop(now + 0.18);

                // Second harmony tone (note 2)
                const osc2 = this.audioCtx.createOscillator();
                const gain2 = this.audioCtx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(1318.51, now + 0.08); // E6
                gain2.gain.setValueAtTime(0.15, now + 0.08);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.28);
                osc2.connect(gain2);
                gain2.connect(this.audioCtx.destination);
                osc2.start(now + 0.08);
                osc2.stop(now + 0.28);
            } catch (e) {
                // Audio not allowed yet or unsupported
            }
        },

        /**
         * Show quick toast feedback
         */
        showToast(message, type = 'success') {
            this.toast = {
                show: true,
                message: message,
                type: type
            };
            setTimeout(() => {
                this.toast.show = false;
            }, 3000);
        },

        /**
         * Format file size helper
         */
        formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        /**
         * Format timestamp helper
         */
        formatTime(dateString) {
            if (!dateString) return '';
            const d = new Date(dateString);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        /**
         * Format relative date or snippet timestamp
         */
        formatDateSnippet(dateString) {
            if (!dateString) return '';
            const d = new Date(dateString);
            const now = new Date();
            const diffHours = (now - d) / (1000 * 60 * 60);

            if (diffHours < 24 && d.getDate() === now.getDate()) {
                return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
            if (diffHours < 48) {
                return 'Yesterday';
            }
            return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
        },

        /**
         * Setup Pusher / Laravel Echo if present on page
         */
        /**
         * Setup Pusher / Laravel Echo if present on page
         */
        setupBroadcasting() {
            const pusher = typeof window.kiuqGetPusherClient === 'function' ? window.kiuqGetPusherClient() : (window.__kiuqPusher || null);
            if (!pusher) {
                setTimeout(() => this.setupBroadcasting(), 600);
                return;
            }

            try {
                // Monitor Pusher connection state and handle reconnection seamlessly
                if (pusher.connection && !this._pusherStateBound) {
                    this._pusherStateBound = true;
                    this.isPusherLive = (pusher.connection.state === 'connected');

                    pusher.connection.bind('state_change', (states) => {
                        const isNowLive = (states.current === 'connected');
                        if (this.isPusherLive !== isNowLive) {
                            this.isPusherLive = isNowLive;
                            this.startSyncTimer();
                        }
                        if (states.current === 'connected') {
                            this._userBroadcastingSubscribed = false;
                            this.setupBroadcasting();
                            if (this.activeConversation) {
                                this.subscribeConversationBroadcasting(this.activeConversation.id);
                                this.syncActiveConversation();
                            }
                        }
                    });
                }

                // Subscribe to authenticated user channel for direct notifications, calls, and incoming messages
                if (!this._userBroadcastingSubscribed) {
                    this._userBroadcastingSubscribed = true;
                    const userChannel = pusher.subscribe(`private-chat.user.${this.currentUser.id}`);
                    userChannel.bind('message.sent', (data) => this.handleIncomingBroadcast(data));
                    userChannel.bind('App\\Events\\Chat\\ChatMessageSent', (data) => this.handleIncomingBroadcast(data));
                    userChannel.bind('message.deleted', (data) => this.handleMessageDeletedBroadcast(data));
                    userChannel.bind('App\\Events\\Chat\\ChatMessageDeleted', (data) => this.handleMessageDeletedBroadcast(data));
                    userChannel.bind('message.read', (data) => this.handleReadReceiptBroadcast(data));
                    userChannel.bind('App\\Events\\Chat\\MessageReadEvent', (data) => this.handleReadReceiptBroadcast(data));
                    userChannel.bind('call.signal', (data) => this.handleIncomingCallSignal(data));
                    userChannel.bind('App\\Events\\Chat\\CallSignalingEvent', (data) => this.handleIncomingCallSignal(data));
                    userChannel.bind('user.typing', (data) => this.handleTypingBroadcast(data));
                    userChannel.bind('typing', (data) => this.handleTypingBroadcast(data));
                    userChannel.bind('UserTypingEvent', (data) => this.handleTypingBroadcast(data));
                    userChannel.bind('App\\Events\\Chat\\UserTypingEvent', (data) => this.handleTypingBroadcast(data));
                    if (typeof userChannel.bind_global === 'function') {
                        userChannel.bind_global((eventName, data) => {
                            if (!data) return;
                            const evStr = String(eventName);
                            if (evStr.includes('message.deleted') || evStr.includes('ChatMessageDeleted')) {
                                this.handleMessageDeletedBroadcast(data);
                            } else if (evStr.includes('ChatMessageSent') || evStr.includes('message.sent')) {
                                this.handleIncomingBroadcast(data);
                            } else if (evStr.includes('message.read') || evStr.includes('MessageReadEvent')) {
                                this.handleReadReceiptBroadcast(data);
                            } else if (evStr.includes('call.signal') || evStr.includes('CallSignalingEvent') || evStr.includes('call_signal')) {
                                this.handleIncomingCallSignal(data);
                            } else if (evStr.includes('typing') || evStr.includes('UserTypingEvent') || evStr.includes('user.typing')) {
                                this.handleTypingBroadcast(data);
                            }
                        });
                    }
                }

                // If an active conversation is already open, subscribe to it immediately
                if (this.activeConversation) {
                    this.subscribeConversationBroadcasting(this.activeConversation.id);
                }
            } catch (err) {
                console.warn('Pusher setup error:', err);
            }
        },

        subscribeConversationBroadcasting(convId) {
            const pusher = typeof window.kiuqGetPusherClient === 'function' ? window.kiuqGetPusherClient() : (window.__kiuqPusher || null);
            if (!pusher || !convId) return;

            if (this._currentConvChannel && this._currentConvChannel !== convId) {
                try { pusher.unsubscribe(`private-chat.conversation.${this._currentConvChannel}`); } catch (e) {}
            }
            this._currentConvChannel = convId;

            try {
                const convChannel = pusher.subscribe(`private-chat.conversation.${convId}`);
                convChannel.bind('message.sent', (data) => this.handleIncomingBroadcast(data));
                convChannel.bind('App\\Events\\Chat\\ChatMessageSent', (data) => this.handleIncomingBroadcast(data));
                convChannel.bind('message.deleted', (data) => this.handleMessageDeletedBroadcast(data));
                convChannel.bind('App\\Events\\Chat\\ChatMessageDeleted', (data) => this.handleMessageDeletedBroadcast(data));
                convChannel.bind('message.read', (data) => this.handleReadReceiptBroadcast(data));
                convChannel.bind('App\\Events\\Chat\\MessageReadEvent', (data) => this.handleReadReceiptBroadcast(data));
                convChannel.bind('user.typing', (data) => this.handleTypingBroadcast(data));
                convChannel.bind('typing', (data) => this.handleTypingBroadcast(data));
                convChannel.bind('UserTypingEvent', (data) => this.handleTypingBroadcast(data));
                convChannel.bind('App\\Events\\Chat\\UserTypingEvent', (data) => this.handleTypingBroadcast(data));
                convChannel.bind('call.signal', (data) => this.handleIncomingCallSignal(data));
                convChannel.bind('App\\Events\\Chat\\CallSignalingEvent', (data) => this.handleIncomingCallSignal(data));
                if (typeof convChannel.bind_global === 'function') {
                    convChannel.bind_global((eventName, data) => {
                        if (!data) return;
                        const evStr = String(eventName);
                        if (evStr.includes('message.deleted') || evStr.includes('ChatMessageDeleted')) {
                            this.handleMessageDeletedBroadcast(data);
                        } else if (evStr.includes('typing') || evStr.includes('UserTypingEvent') || evStr.includes('user.typing')) {
                            this.handleTypingBroadcast(data);
                        } else if (evStr.includes('message.read') || evStr.includes('MessageReadEvent')) {
                            this.handleReadReceiptBroadcast(data);
                        } else if (evStr.includes('ChatMessageSent') || evStr.includes('message.sent')) {
                            this.handleIncomingBroadcast(data);
                        } else if (evStr.includes('call.signal') || evStr.includes('CallSignalingEvent') || evStr.includes('call_signal')) {
                            this.handleIncomingCallSignal(data);
                        }
                    });
                }
            } catch (err) {
                console.warn('Pusher conv subscribe error:', err);
            }
        },

        /**
         * Handle Real-Time Message Deletion Broadcast
         */
        handleMessageDeletedBroadcast(data) {
            if (!data) return;
            const msgId = Number(data.id || data.message_id);
            const convId = Number(data.conversation_id);
            if (!msgId) return;

            // 1. If currently viewing this conversation, remove from messages array
            if (this.activeConversation && Number(this.activeConversation.id) === convId) {
                this.messages = this.messages.filter(m => Number(m.id) !== msgId);
                this._persistCacheToStorage(convId, this.messages);
                const lastMsg = this.messages[this.messages.length - 1] || null;
                this.updateConversationLastMessage(convId, lastMsg);
            }

            // 2. Also remove from background cache
            if (this._messagesCache[convId]) {
                this._messagesCache[convId] = this._messagesCache[convId].filter(m => Number(m.id) !== msgId);
                this._persistCacheToStorage(convId, this._messagesCache[convId]);
                const conv = this.conversations.find(c => Number(c.id) === convId);
                if (conv && conv.last_message && Number(conv.last_message.id) === msgId) {
                    const list = this._messagesCache[convId];
                    conv.last_message = list[list.length - 1] || null;
                    this.conversations = [...this.conversations];
                }
            }
        },

        handleReadReceiptBroadcast(data) {
            if (!data) return;
            const convId = Number(data.conversation_id);
            const readerId = Number(data.user_id);
            const readId = Number(data.last_read_message_id || 0);

            // If partner in active chat read our messages, update to 2 blue ticks + "Seen" instantly
            if (this.activeConversation && Number(this.activeConversation.id) === convId && readerId !== Number(this.currentUser.id)) {
                let updated = false;
                this.messages.forEach(m => {
                    if (Number(m.user_id) === Number(this.currentUser.id) && (Number(m.id) <= readId || readId === 0)) {
                        if (!m.is_seen || m.status !== 'read') {
                            m.status = 'read';
                            m.is_seen = true;
                            updated = true;
                        }
                    }
                });
                if (updated) {
                    this.messages = [...this.messages];
                }
                if (this.activeConversation.last_message && Number(this.activeConversation.last_message.user_id) === Number(this.currentUser.id)) {
                    if (Number(this.activeConversation.last_message.id) <= readId || readId === 0) {
                        this.activeConversation.last_message.status = 'read';
                        this.activeConversation.last_message.is_seen = true;
                    }
                }
            }

            // Also update conversation in sidebar list
            const conv = this.conversations.find(c => Number(c.id) === convId);
            if (conv && conv.last_message && Number(conv.last_message.user_id) === Number(this.currentUser.id)) {
                if (Number(conv.last_message.id) <= readId || readId === 0) {
                    conv.last_message.status = 'read';
                    conv.last_message.is_seen = true;
                    this.conversations = [...this.conversations];
                }
            }
        },

        /**
         * Helper: Check if this message is the latest seen outgoing message (Messenger style)
         */
        isLastSeenMessage(msg) {
            if (!msg || !msg.is_me || (!msg.is_seen && msg.status !== 'read')) return false;
            for (let i = this.messages.length - 1; i >= 0; i--) {
                const m = this.messages[i];
                if (Number(m.user_id) === Number(this.currentUser.id)) {
                    return (m.id === msg.id || (m.client_temp_id && m.client_temp_id === msg.client_temp_id));
                }
            }
            return false;
        },

        handleIncomingCallSignal(e) {
            if (!e) return;
            // Ignore signals targeted at a different user (prevents self-echo bugs)
            if (e.target_user_id && Number(e.target_user_id) !== Number(this.currentUser.id)) {
                return;
            }
            const signalType = e.type;

            // 1. Incoming Call ringing for this user
            if (signalType === 'incoming_call' || signalType === 'call_invitation') {
                const receiverId = Number(e.receiver_id || e.call?.receiver_id || e.receiver?.id);
                if (!this.callState.active && receiverId === Number(this.currentUser.id)) {
                    const isVideo = (e.payload?.call_type === 'video' || e.call_type === 'video' || e.call?.call_type === 'video');
                    const callObj = e.call || {
                        id: e.call_id,
                        conversation_id: e.conversation_id,
                        caller_id: Number(e.caller_id || e.caller?.id),
                        receiver_id: receiverId,
                        status: 'ringing',
                        call_type: isVideo ? 'video' : 'voice',
                        caller: e.caller,
                        receiver: e.receiver,
                    };
                    this.callState.active = true;
                    this.callState.call = callObj;
                    this.callState.isCaller = false;
                    this.callState.status = 'ringing';
                    this.callState.isVideo = isVideo;
                    this.callState.isCameraOn = false;
                    this.callState.remoteVideoActive = false; // Only activate once remote video frames are actually received
                    this.callState.lastSignalId = 0;
                    this.callState.pendingCandidates = [];
                    this.callState._processedSignalIds = new Set();
                    this.playCallAudioFeedback('ringing');
                    this.startSignalPolling(callObj.id);
                }
                return;
            }

            // 2. Call Accepted by Receiver
            if (signalType === 'call_accepted') {
                if (this.callState.active && this.callState.isCaller) {
                    this.callState.status = 'connected';
                    this.stopCallAudioFeedback();
                    this.startCallDurationTimer();
                }
                return;
            }

            // 3. Call Ended or Declined
            if (signalType === 'call_ended' || signalType === 'call_declined' || signalType === 'end') {
                if (e.call_id && this.callState.call && Number(e.call_id) !== Number(this.callState.call.id)) {
                    return;
                }
                this.cleanupCallState(signalType === 'call_declined' ? 'Call declined' : 'Call ended');
                if (this.activeConversation) this.syncActiveConversation();
                return;
            }

            // 4. Remote video camera state change
            if (signalType === 'video_on') {
                this.callState.remoteVideoActive = true;
                this.callState.isVideo = true;
                return;
            }
            if (signalType === 'video_off') {
                this.callState.remoteVideoActive = false;
                return;
            }

            // 5. WebRTC SDP Offer / Answer / ICE candidate (use real DB signal_id, NEVER Date.now()!)
            if (['offer', 'answer', 'ice-candidate', 'ice_candidate'].includes(signalType)) {
                this.handleIncomingSignal({
                    id: e.signal_id || null,
                    sender_id: e.sender_id || e.caller_id || null,
                    type: signalType === 'ice_candidate' ? 'ice-candidate' : signalType,
                    payload: e.payload
                });
            }
        },

        handleIncomingBroadcast(e) {
            if (!e) return;
            const msg = e.message || e;
            if (!msg || !msg.id) return;

            msg.user_id = Number(msg.user_id);
            msg.is_me = Number(msg.user_id) === Number(this.currentUser.id);
            msg.body = msg.body || msg.content || '';
            msg.content = msg.content || msg.body || '';
            if (!msg.status) {
                msg.status = (Number(msg.user_id) === Number(this.currentUser.id)) ? (msg.is_seen ? 'read' : 'sent') : 'delivered';
            }

            // In-place update of an existing message (e.g. link preview arrived): patch only, no sound/unread
            if (msg.is_update) {
                const patch = (list) => {
                    const i = list.findIndex(m => Number(m.id) === Number(msg.id));
                    if (i !== -1) {
                        msg.client_temp_id = list[i].client_temp_id || msg.client_temp_id;
                        list.splice(i, 1, { ...list[i], link_preview: msg.link_preview });
                        return true;
                    }
                    return false;
                };
                if (this.activeConversation && Number(msg.conversation_id) === Number(this.activeConversation.id)) {
                    if (patch(this.messages)) this.messages = [...this.messages];
                }
                if (this._messagesCache[msg.conversation_id]) patch(this._messagesCache[msg.conversation_id]);
                return;
            }

            // The same message arrives on both the user channel and the conversation channel: handle it once
            if (!this._seenBroadcastIds) this._seenBroadcastIds = new Set();
            const seenKey = Number(msg.id);
            if (this._seenBroadcastIds.has(seenKey)) return;
            this._seenBroadcastIds.add(seenKey);
            if (this._seenBroadcastIds.size > 500) {
                this._seenBroadcastIds.delete(this._seenBroadcastIds.values().next().value);
            }

            // Trigger Device & macOS Pop-up Notification
            if (Number(msg.user_id) !== Number(this.currentUser.id)) {
                this.triggerDeviceNotification(msg);
            }

            // If message belongs to current active conversation
            if (this.activeConversation && Number(msg.conversation_id) === Number(this.activeConversation.id)) {
                // Instantly clear typing indicator for the sender
                const senderName = msg.sender_name || msg.user?.name;
                if (senderName && this._typingMap) {
                    delete this._typingMap[senderName];
                    this._updateTypingUsers();
                }

                // Match existing message:
                // 1) exact ID
                // 2) matching client_temp_id
                // 3) if from current user:
                //    - if voice message, match any pending temporary voice note
                //    - if text message, match temporary placeholder with same text
                let existingIndex = this.messages.findIndex(m => m.id === msg.id || (m.client_temp_id && msg.client_temp_id && m.client_temp_id === msg.client_temp_id));
                if (existingIndex === -1 && Number(msg.user_id) === Number(this.currentUser.id)) {
                    if (msg.type === 'voice' || msg.is_voice || (msg.attachments && msg.attachments.some(a => this.isVoiceAttachment(a, msg)))) {
                        existingIndex = this.messages.findIndex(m => (m.type === 'voice' || m.is_voice || (m.attachments && m.attachments.some(a => this.isVoiceAttachment(a, m)))) && (String(m.id).startsWith('temp_') || m.status === 'sending'));
                    } else {
                        existingIndex = this.messages.findIndex(m => (m.client_temp_id || String(m.id).startsWith('temp_')) && (m.body === (msg.body || msg.content)));
                    }
                }

                if (existingIndex !== -1) {
                    const oldMsg = this.messages[existingIndex];
                    msg.client_temp_id = oldMsg.client_temp_id || msg.client_temp_id;
                    if (this.voicePlayer && this.voicePlayer.currentMsgId === oldMsg.id) {
                        this.voicePlayer.currentMsgId = msg.id;
                    }
                    if (msg.attachments && msg.attachments[0] && oldMsg && oldMsg.attachments && oldMsg.attachments[0]) {
                        if (!msg.attachments[0].waveform_data || msg.attachments[0].waveform_data.length === 0) {
                            msg.attachments[0].waveform_data = oldMsg.attachments[0].waveform_data;
                        }
                        if (!msg.attachments[0].duration_seconds) {
                            msg.attachments[0].duration_seconds = oldMsg.attachments[0].duration_seconds;
                        }
                    }
                    this.messages.splice(existingIndex, 1, msg);
                } else {
                    this.messages.push(msg);
                    this.lastSyncId = Math.max(this.lastSyncId, msg.id);
                    this.markConversationAsRead(msg.conversation_id, msg.id);
                    this.$nextTick(() => this.scrollToBottom(false));
                }

                this._deduplicateMessages();
                this.messages = [...this.messages];
                this.updateConversationLastMessage(this.activeConversation.id, msg);
            } else {
                // Update unread count for other conversations
                const conv = this.conversations.find(c => Number(c.id) === Number(msg.conversation_id));
                if (conv) {
                    conv.unread_count = (conv.unread_count || 0) + 1;
                    this.updateConversationLastMessage(conv.id, msg);
                    this.broadcastUnreadUpdate();
                } else {
                    this.refreshConversationsList();
                }
            }
        },

        /**
         * Device & macOS Pop-up Notification Engine
         * Dispatches native notifications with profile picture and message text
         */
        triggerDeviceNotification(msg) {
            if (!msg || Number(msg.user_id) === Number(this.currentUser.id)) return;

            const senderName = msg.sender_name || msg.user?.name || 'DGT Chat';
            const text = msg.body || msg.content || (msg.attachments && msg.attachments.length ? `Sent ${msg.attachments.length} file(s)` : 'New message');
            const rawAvatar = msg.sender_avatar || msg.user?.avatar_url || '';
            const avatarUrl = rawAvatar && String(rawAvatar).startsWith('/')
                ? window.location.origin + rawAvatar
                : (rawAvatar || (window.location.origin + '/favicon.ico'));

            // 1. macOS Native App via Flutter InAppWebView Channel
            try {
                const nativePayload = {
                    id: String(msg.id || Date.now()),
                    title: senderName,
                    subtitle: 'DGT Team Chat',
                    message: text,
                    link: window.location.origin + '/chat',
                    avatarUrl: avatarUrl
                };

                if (window.flutter_inappwebview && typeof window.flutter_inappwebview.callHandler === 'function') {
                    window.flutter_inappwebview.callHandler('DgtNativeNotifications', JSON.stringify(nativePayload));
                } else if (window.DgtNativeNotifications && typeof window.DgtNativeNotifications.postMessage === 'function') {
                    window.DgtNativeNotifications.postMessage(JSON.stringify(nativePayload));
                }
            } catch (err) {
                console.warn('macOS native notification bridge error:', err);
            }

            // 2. HTML5 Web Notification API (Browser / macOS Notification Center banner)
            if (window.Notification && Notification.permission === 'granted') {
                try {
                    const notif = new Notification(senderName, {
                        body: text,
                        icon: avatarUrl,
                        badge: '/favicon.ico',
                        tag: 'dgt-chat-conv-' + (msg.conversation_id || 'general'),
                        renotify: true,
                        silent: false
                    });
                    notif.onclick = () => {
                        window.focus();
                        if (msg.conversation_id) {
                            const c = this.conversations.find(conv => conv.id === msg.conversation_id);
                            if (c) this.selectConversation(c);
                        }
                    };
                } catch (err) {
                    console.warn('HTML5 Notification error:', err);
                }
            }

            // 3. Crisp synthesized chime tone
            if (this.userSettings.notification_sound) {
                this.playChime();
            }
        },

        /**
         * Request browser / system notification permission
         */
        async requestNotificationPermission() {
            if (window.Notification && Notification.permission === 'default') {
                try {
                    const perm = await Notification.requestPermission();
                    if (perm === 'granted') {
                        this.showToast('Device notifications enabled!', 'success');
                        if (window.beamsClient) {
                            window.beamsClient.start()
                                .then(() => window.beamsClient.addDeviceInterest('hello'))
                                .catch(err => console.warn('Beams registration note:', err));
                        }
                    }
                } catch (e) {
                    console.warn('Notification permission error:', e);
                }
            }
        },

        // =========================================================================
        // TELEGRAM-STYLE VOICE MESSAGES
        // =========================================================================

        /**
         * Start voice recording
         */
        async startVoiceRecording(mode = 'tap') {
            if (this.voiceRecording.active) return;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Microphone access is not supported by your browser.');
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    },
                    video: false
                });

                this.voiceRecording.stream = stream;
                this.voiceRecording.mode = mode;
                this.voiceRecording.audioChunks = [];
                this.voiceRecording.waveformData = [];
                this.voiceRecording.audioBlob = null;
                this.voiceRecording.audioUrl = null;

                // Detect supported mimeType
                let mimeType = 'audio/webm;codecs=opus';
                if (!MediaRecorder.isTypeSupported(mimeType)) {
                    mimeType = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' :
                              (MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' :
                              (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus') ? 'audio/ogg;codecs=opus' : ''));
                }

                const options = mimeType ? { mimeType } : {};
                const recorder = new MediaRecorder(stream, options);
                this.voiceRecording.mediaRecorder = recorder;

                recorder.ondataavailable = (e) => {
                    if (e.data && e.data.size > 0) {
                        this.voiceRecording.audioChunks.push(e.data);
                    }
                };

                // Setup Web Audio Analyser for live amplitude capture
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) {
                    const ctx = new AudioCtx();
                    const src = ctx.createMediaStreamSource(stream);
                    const analyser = ctx.createAnalyser();
                    analyser.fftSize = 64;
                    src.connect(analyser);
                    this.voiceRecording.analyser = analyser;
                    this.voiceRecording.audioCtx = ctx;

                    const dataArr = new Uint8Array(analyser.frequencyBinCount);
                    const sampleTimer = setInterval(() => {
                        if (!this.voiceRecording.active) {
                            clearInterval(sampleTimer);
                            return;
                        }
                        analyser.getByteFrequencyData(dataArr);
                        let sum = 0;
                        for (let i = 0; i < dataArr.length; i++) sum += dataArr[i];
                        const avg = sum / dataArr.length;
                        const barVal = Math.max(16, Math.min(96, Math.round((avg / 128) * 85 + 16)));
                        if (this.voiceRecording.waveformData.length < 40) {
                            this.voiceRecording.waveformData.push(barVal);
                        } else {
                            this.voiceRecording.waveformData.shift();
                            this.voiceRecording.waveformData.push(barVal);
                        }
                    }, 120);
                }

                recorder.start(100);
                this.voiceRecording.active = true;
                this.voiceRecording.startTime = Date.now();
                this.voiceRecording.duration = 0;

                this.voiceRecording.timer = setInterval(() => {
                    this.voiceRecording.duration = Math.floor((Date.now() - this.voiceRecording.startTime) / 1000);
                }, 250);

            } catch (err) {
                console.error('startVoiceRecording failed:', err);
                alert('Could not access microphone: ' + err.message);
                this.cancelVoiceRecording();
            }
        },

        /**
         * Stop recording audio
         */
        stopVoiceRecording(sendImmediately = false) {
            if (!this.voiceRecording.active) return;

            if (sendImmediately) {
                this.stopAndSendVoiceRecording();
                return;
            }

            const recorder = this.voiceRecording.mediaRecorder;
            clearInterval(this.voiceRecording.timer);

            if (recorder && recorder.state !== 'inactive') {
                recorder.onstop = () => {
                    const mimeType = recorder.mimeType || 'audio/webm';
                    this.voiceRecording.audioBlob = new Blob(this.voiceRecording.audioChunks, { type: mimeType });
                    this.voiceRecording.audioUrl = URL.createObjectURL(this.voiceRecording.audioBlob);

                    // Stop microphone tracks
                    if (this.voiceRecording.stream) {
                        this.voiceRecording.stream.getTracks().forEach(t => t.stop());
                        this.voiceRecording.stream = null;
                    }
                    if (this.voiceRecording.audioCtx) {
                        try { this.voiceRecording.audioCtx.close(); } catch (_) {}
                        this.voiceRecording.audioCtx = null;
                    }
                };
                try { recorder.stop(); } catch (e) {}
            }
            this.voiceRecording.active = false;
        },

        /**
         * Discard voice recording
         */
        cancelVoiceRecording() {
            if (this.voiceRecording.mediaRecorder && this.voiceRecording.mediaRecorder.state !== 'inactive') {
                try { this.voiceRecording.mediaRecorder.stop(); } catch (e) {}
            }
            if (this.voiceRecording.stream) {
                this.voiceRecording.stream.getTracks().forEach(t => t.stop());
                this.voiceRecording.stream = null;
            }
            if (this.voiceRecording.audioCtx) {
                try { this.voiceRecording.audioCtx.close(); } catch (_) {}
                this.voiceRecording.audioCtx = null;
            }
            if (this.voiceRecording.previewAudio) {
                try { this.voiceRecording.previewAudio.pause(); } catch (_) {}
                this.voiceRecording.previewAudio = null;
            }
            if (this.voiceRecording.audioUrl) {
                try { URL.revokeObjectURL(this.voiceRecording.audioUrl); } catch (_) {}
            }
            clearInterval(this.voiceRecording.timer);

            this.voiceRecording.active = false;
            this.voiceRecording.audioChunks = [];
            this.voiceRecording.audioBlob = null;
            this.voiceRecording.audioUrl = null;
            this.voiceRecording.waveformData = [];
            this.voiceRecording.duration = 0;
            this.voiceRecording.isPlayingPreview = false;
        },

        /**
         * Toggle preview playback for recorded audio before sending
         */
        toggleVoicePreview() {
            if (!this.voiceRecording.audioUrl) return;

            if (this.voiceRecording.previewAudio) {
                if (this.voiceRecording.isPlayingPreview) {
                    this.voiceRecording.previewAudio.pause();
                    this.voiceRecording.isPlayingPreview = false;
                } else {
                    this.voiceRecording.previewAudio.play();
                    this.voiceRecording.isPlayingPreview = true;
                }
                return;
            }

            const audio = new Audio(this.voiceRecording.audioUrl);
            this.voiceRecording.previewAudio = audio;
            audio.onended = () => {
                this.voiceRecording.isPlayingPreview = false;
            };
            audio.play().then(() => {
                this.voiceRecording.isPlayingPreview = true;
            }).catch(e => console.warn(e));
        },

        /**
         * Upload and dispatch voice note (called from review mode)
         */
        sendVoiceRecording() {
            this.stopAndSendVoiceRecording();
        },

        /**
         * Real-time zero-delay voice note dispatcher (0ms optimistic UI - instant appearance for sender)
         */
        async stopAndSendVoiceRecording() {
            if (!this.activeConversation) return;
            const convId = this.activeConversation.id;

            // 1. Capture duration & live waveform bars immediately
            const duration = Math.max(1, this.voiceRecording.duration || Math.round((Date.now() - (this.voiceRecording.startTime || Date.now())) / 1000) || 1);
            const waveform = (this.voiceRecording.waveformData && this.voiceRecording.waveformData.length > 0)
                ? [...this.voiceRecording.waveformData]
                : this.getWaveformBars(null, Date.now());

            const tempId = 'temp_voice_' + Date.now();

            // 2. Stop microphone tracks and timers immediately so device status turns off
            clearInterval(this.voiceRecording.timer);
            if (this.voiceRecording.stream) {
                this.voiceRecording.stream.getTracks().forEach(t => t.stop());
                this.voiceRecording.stream = null;
            }
            if (this.voiceRecording.audioCtx) {
                try { this.voiceRecording.audioCtx.close(); } catch (_) {}
                this.voiceRecording.audioCtx = null;
            }
            if (this.voiceRecording.previewAudio) {
                try { this.voiceRecording.previewAudio.pause(); } catch (_) {}
                this.voiceRecording.previewAudio = null;
            }

            // 3. Extract or assemble audio blob immediately
            let blob = this.voiceRecording.audioBlob;
            let previewUrl = this.voiceRecording.audioUrl;

            const recorder = this.voiceRecording.mediaRecorder;
            if (recorder && recorder.state !== 'inactive') {
                try { recorder.requestData(); } catch (_) {}
                try { recorder.stop(); } catch (_) {}
                const mimeType = recorder.mimeType || 'audio/webm';
                blob = new Blob(this.voiceRecording.audioChunks, { type: mimeType });
                previewUrl = URL.createObjectURL(blob);
            } else if (!previewUrl && blob) {
                previewUrl = URL.createObjectURL(blob);
            }

            // 4. Instantly reset recording dock in UI so composer is completely clean
            this.voiceRecording.active = false;
            this.voiceRecording.audioUrl = null;
            this.voiceRecording.audioBlob = null;
            this.voiceRecording.audioChunks = [];
            this.voiceRecording.waveformData = [];
            this.voiceRecording.duration = 0;
            this.voiceRecording.isPlayingPreview = false;

            // 5. INSTANT 0ms OPTIMISTIC MESSAGE: appears immediately on chat screen!
            const optimisticMsg = {
                id: tempId,
                client_temp_id: tempId,
                conversation_id: convId,
                user_id: Number(this.currentUser.id),
                user: this.currentUser,
                body: '🎤 Voice Message',
                content: '🎤 Voice Message',
                type: 'voice',
                created_at: new Date().toISOString(),
                attachments: [{
                    id: 'temp_att_' + tempId,
                    file_name: `voice_${Date.now()}.webm`,
                    file_type: 'audio/webm',
                    file_size: blob ? blob.size : 0,
                    duration_seconds: duration,
                    waveform_data: waveform,
                    file_url: previewUrl || '',
                    view_url: previewUrl || '',
                    download_url: previewUrl || ''
                }],
                reactions: [],
                is_mine: true,
                is_me: true,
                is_optimistic: true,
                status: 'sending'
            };

            this.messages.push(optimisticMsg);
            this.messages = [...this.messages];
            this.updateConversationLastMessage(convId, optimisticMsg);
            this.$nextTick(() => this.scrollToBottom(true));

            // 6. In background: upload to server and replace optimistic bubble seamlessly
            this.uploadVoiceMessage(convId, tempId, blob, duration, waveform, previewUrl);
        },

        /**
         * Background asynchronous upload of recorded voice note
         */
        async uploadVoiceMessage(convId, tempId, blob, duration, waveform, previewUrl) {
            // If blob was still flushing, wait up to 150ms
            if (!blob || blob.size === 0) {
                await new Promise((resolve) => setTimeout(resolve, 150));
            }

            const ext = blob && blob.type && blob.type.includes('mp4') ? 'm4a' : 'webm';
            const formData = new FormData();
            formData.append('conversation_id', convId);
            formData.append('client_temp_id', tempId);
            if (blob) {
                formData.append('voice_file', blob, `voice_${Date.now()}.${ext}`);
            }
            formData.append('duration', duration);
            formData.append('waveform_data', JSON.stringify(waveform));

            const pusherClient = typeof window.kiuqGetPusherClient === 'function' ? window.kiuqGetPusherClient() : (window.__kiuqPusher || null);
            const socketId = pusherClient?.connection?.socket_id;
            const headers = {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            };
            if (socketId) headers['X-Socket-ID'] = socketId;

            try {
                const res = await fetch('/chat/messages/voice', {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin',
                    body: formData
                });
                const data = await res.json();
                if (data.success && data.message) {
                    const serverMsg = data.message;
                    serverMsg.client_temp_id = tempId;
                    serverMsg.is_me = true;
                    serverMsg.user_id = Number(this.currentUser.id);

                    // Preserve waveform & duration if server returned raw attachment
                    if (serverMsg.attachments && serverMsg.attachments[0]) {
                        if (!serverMsg.attachments[0].waveform_data || serverMsg.attachments[0].waveform_data.length === 0) {
                            serverMsg.attachments[0].waveform_data = waveform;
                        }
                        if (!serverMsg.attachments[0].duration_seconds) {
                            serverMsg.attachments[0].duration_seconds = duration;
                        }
                    }

                    // Seamlessly replace optimistic temporary message in-place
                    const existingByIdIdx = this.messages.findIndex(m => Number(m.id) === Number(serverMsg.id));
                    const tempIdx = this.messages.findIndex(m => m.id === tempId || (m.client_temp_id && m.client_temp_id === tempId));

                    if (existingByIdIdx !== -1) {
                        if (tempIdx !== -1 && tempIdx !== existingByIdIdx) {
                            this.messages.splice(tempIdx, 1);
                        }
                        this.messages.splice(existingByIdIdx, 1, serverMsg);
                    } else if (tempIdx !== -1) {
                        this.messages.splice(tempIdx, 1, serverMsg);
                    } else {
                        this.messages.push(serverMsg);
                    }
                    if (this.voicePlayer && this.voicePlayer.currentMsgId === tempId) {
                        this.voicePlayer.currentMsgId = serverMsg.id;
                    }
                    this._deduplicateMessages();
                    this.messages = [...this.messages];
                    this._persistCacheToStorage(convId, this.messages);
                    this.lastSyncId = Math.max(this.lastSyncId, serverMsg.id);
                    this.updateConversationLastMessage(convId, serverMsg);
                    this.playChime();
                    this.$nextTick(() => this.scrollToBottom(true));
                } else {
                    console.error('Voice send error:', data);
                    const idx = this.messages.findIndex(m => m.id === tempId);
                    if (idx !== -1) {
                        this.messages[idx].status = 'error';
                        this.messages = [...this.messages];
                    }
                }
            } catch (err) {
                console.error('Failed to upload voice message:', err);
                const idx = this.messages.findIndex(m => m.id === tempId);
                if (idx !== -1) {
                    this.messages[idx].status = 'error';
                    this.messages = [...this.messages];
                }
            }
        },

        formatRecordingDuration(sec) {
            if (!sec || isNaN(sec) || sec < 0) return '0:00';
            const m = Math.floor(sec / 60);
            const s = sec % 60;
            return `${m}:${s < 10 ? '0' : ''}${s}`;
        },

        // =========================================================================
        // VOICE MESSAGE PLAYER & WAVEFORM VISUALIZATION
        // =========================================================================

        /**
         * Play / Pause Telegram-style voice message bubble
         */
        toggleVoiceMessage(msg, att) {
            if (!att) return;
            const audioUrl = att.file_url || att.view_url || att.download_url;
            if (!audioUrl) return;

            // Same message toggle
            if (this.voicePlayer.currentMsgId === msg.id && this.voicePlayer.audio) {
                if (this.voicePlayer.isPlaying) {
                    this.voicePlayer.audio.pause();
                    this.voicePlayer.isPlaying = false;
                } else {
                    this.voicePlayer.audio.play().then(() => {
                        this.voicePlayer.isPlaying = true;
                    }).catch(e => console.warn(e));
                }
                return;
            }

            // Different message: stop old
            if (this.voicePlayer.audio) {
                this.voicePlayer.audio.pause();
                this.voicePlayer.audio = null;
            }

            const audio = new Audio(audioUrl);
            audio.playbackRate = this.voicePlayer.playbackRate;
            this.voicePlayer.currentMsgId = msg.id;
            this.voicePlayer.audio = audio;
            this.voicePlayer.duration = att.duration_seconds || 0;
            this.voicePlayer.currentTime = 0;

            audio.addEventListener('loadedmetadata', () => {
                if (audio.duration && !isNaN(audio.duration) && audio.duration !== Infinity) {
                    this.voicePlayer.duration = audio.duration;
                }
            });

            audio.addEventListener('timeupdate', () => {
                this.voicePlayer.currentTime = audio.currentTime;
            });

            audio.addEventListener('ended', () => {
                this.voicePlayer.isPlaying = false;
                this.voicePlayer.currentTime = 0;
            });

            audio.play().then(() => {
                this.voicePlayer.isPlaying = true;
            }).catch(e => {
                console.warn('Voice play failed:', e);
                this.voicePlayer.isPlaying = false;
            });
        },

        /**
         * Seek voice message by clicking/scrubbing on waveform
         */
        seekVoiceMessage(msg, att, percent, event) {
            const totalDuration = this.voicePlayer.duration || att.duration_seconds || 1;
            const targetTime = Math.max(0, Math.min(totalDuration, percent * totalDuration));

            if (this.voicePlayer.currentMsgId !== msg.id || !this.voicePlayer.audio) {
                this.toggleVoiceMessage(msg, att);
                setTimeout(() => {
                    if (this.voicePlayer.audio) {
                        this.voicePlayer.audio.currentTime = targetTime;
                        this.voicePlayer.currentTime = targetTime;
                    }
                }, 120);
            } else {
                this.voicePlayer.audio.currentTime = targetTime;
                this.voicePlayer.currentTime = targetTime;
            }
        },

        cyclePlaybackSpeed() {
            const speeds = [1.0, 1.5, 2.0];
            const currIdx = speeds.indexOf(this.voicePlayer.playbackRate);
            const nextSpeed = speeds[(currIdx + 1) % speeds.length];
            this.voicePlayer.playbackRate = nextSpeed;
            if (this.voicePlayer.audio) {
                this.voicePlayer.audio.playbackRate = nextSpeed;
            }
        },

        formatAudioTime(sec) {
            if (!sec || isNaN(sec) || sec < 0) return '0:00';
            const s = Math.round(sec);
            const m = Math.floor(s / 60);
            const rem = s % 60;
            return `${m}:${rem < 10 ? '0' : ''}${rem}`;
        },

        getWaveformBars(att, msgId) {
            if (att && att.waveform_data && Array.isArray(att.waveform_data) && att.waveform_data.length > 0) {
                return att.waveform_data;
            }
            // Deterministic aesthetic waveform pattern
            const seed = Number(msgId) || 42;
            const bars = [];
            for (let i = 0; i < 36; i++) {
                const val = Math.floor(Math.sin((seed * 13 + i * 29) % 360) * 35 + 50);
                bars.push(Math.max(18, Math.min(95, val)));
            }
            return bars;
        },

        // =========================================================================
        // REAL-TIME ONE-TO-ONE WEBRTC VOICE CALLING
        // =========================================================================

        /**
         * Initiate outgoing video call
         */
        async startVideoCall(receiverId = null) {
            return this.startVoiceCall(receiverId, true);
        },

        /**
         * Initiate outgoing voice or video call
         */
        async startVoiceCall(receiverId = null, isVideo = false) {
            if (this._isInitiatingCall) {
                this.callState.minimized = false;
                return;
            }

            if (this.callState.active) {
                const pcState = this.callState.peerConnection?.connectionState;
                const isLiveConnected = this.callState.status === 'connected' && this.callState.peerConnection && !['closed', 'failed', 'disconnected'].includes(pcState);
                if (isLiveConnected) {
                    this.callState.minimized = false;
                    if (isVideo && !this.callState.isCameraOn) {
                        this.toggleCamera();
                    }
                    return;
                }
                // Stale or unconnected call state — clean up so the user's new call starts immediately
                this.cleanupCallState();
            }

            this._isInitiatingCall = true;

            // Pre-unlock remote audio element on user click gesture so autoplay never blocks
            this.unlockAudio();
            const audioEl = document.getElementById('chat-remote-audio-player');
            if (audioEl) {
                if (audioEl.hasAttribute('src')) {
                    audioEl.removeAttribute('src');
                    audioEl.src = '';
                }
                audioEl.muted = false;
                audioEl.volume = 1.0;
            }
            const ctx = this.getAudioContext();
            if (ctx && ctx.state === 'suspended') {
                ctx.resume().catch(() => {});
            }

            let targetReceiverId = receiverId ? Number(receiverId) : null;
            let targetConvId = this.activeConversation ? this.activeConversation.id : null;

            // Automatically detect receiver from active conversation if not passed explicitly
            if (!targetReceiverId && this.activeConversation) {
                if (this.activeConversation.other_user_id) {
                    targetReceiverId = Number(this.activeConversation.other_user_id);
                } else if (this.activeConversation.other_user?.id) {
                    targetReceiverId = Number(this.activeConversation.other_user.id);
                } else if (this.activeConversation.user?.id && Number(this.activeConversation.user.id) !== Number(this.currentUser.id)) {
                    targetReceiverId = Number(this.activeConversation.user.id);
                } else if (Array.isArray(this.activeConversation.members) && this.activeConversation.members.length > 0) {
                    const other = this.activeConversation.members.find(m => Number(m.id || m.user_id) !== Number(this.currentUser.id));
                    if (other) targetReceiverId = Number(other.id || other.user_id);
                } else if (Array.isArray(this.activeConversation.users) && this.activeConversation.users.length > 0) {
                    const other = this.activeConversation.users.find(u => Number(u.id) !== Number(this.currentUser.id));
                    if (other) targetReceiverId = Number(other.id);
                } else if (Array.isArray(this.activeConversation.participants) && this.activeConversation.participants.length > 0) {
                    const other = this.activeConversation.participants.find(p => Number(p.id || p.user_id) !== Number(this.currentUser.id));
                    if (other) targetReceiverId = Number(other.id || other.user_id);
                }
            }

            // Fallback check against conversation list if activeConversation lacks other_user_id
            if (!targetReceiverId && this.activeConversation) {
                const convItem = this.conversations.find(c => Number(c.id) === Number(this.activeConversation.id));
                if (convItem) {
                    if (convItem.other_user_id) {
                        targetReceiverId = Number(convItem.other_user_id);
                    } else if (convItem.other_user?.id) {
                        targetReceiverId = Number(convItem.other_user.id);
                    } else if (Array.isArray(convItem.members)) {
                        const other = convItem.members.find(m => Number(m.id || m.user_id) !== Number(this.currentUser.id));
                        if (other) targetReceiverId = Number(other.id || other.user_id);
                    }
                }
            }

            // Final fallback: match activeConversation name/title against usersDirectory
            if (!targetReceiverId && this.activeConversation && Array.isArray(this.usersDirectory)) {
                const convTitle = (this.activeConversation.title || this.activeConversation.name || '').trim().toLowerCase();
                if (convTitle && convTitle !== 'saved messages') {
                    const matchedUser = this.usersDirectory.find(u =>
                        Number(u.id) !== Number(this.currentUser.id) &&
                        (u.name || '').trim().toLowerCase() === convTitle
                    );
                    if (matchedUser) {
                        targetReceiverId = Number(matchedUser.id);
                    }
                }
            }

            if (!targetReceiverId) {
                this._isInitiatingCall = false;
                this.showToast('Please open a 1-on-1 chat or select a team member to start a call.', 'info');
                return;
            }

            if (Number(targetReceiverId) === Number(this.currentUser.id)) {
                this._isInitiatingCall = false;
                this.showToast('You cannot call yourself.', 'warning');
                return;
            }

            // Immediately display Outgoing Call overlay (0ms optimistic UI) so the user gets instant visual & audio feedback
            const contactUser = (Array.isArray(this.usersDirectory) && this.usersDirectory.find(u => Number(u.id) === Number(targetReceiverId)))
                || this.activeConversation?.other_user
                || this.activeConversation?.user
                || {
                    id: Number(targetReceiverId),
                    name: this.activeConversation?.title || this.activeConversation?.name || 'Team Member',
                    avatar_url: this.activeConversation?.avatar_url || null
                };

            this.callState.active = true;
            this.callState.minimized = false;
            this.callState.isCaller = true;
            this.callState.status = 'calling';
            this.callState.isMuted = false;
            this.callState.isVideo = !!isVideo;
            this.callState.isCameraOn = !!isVideo;
            this.callState.remoteVideoActive = false;
            this.callState.duration = 0;
            this.callState.lastSignalId = 0;
            this.callState.pendingCandidates = [];
            this.callState._isHandlingOffer = false;
            this.callState._processedSignalIds = new Set();
            this.callState.call = {
                id: 0,
                conversation_id: targetConvId || 0,
                caller_id: Number(this.currentUser.id),
                receiver_id: Number(targetReceiverId),
                is_caller: true,
                status: 'calling',
                call_type: isVideo ? 'video' : 'voice',
                receiver: contactUser,
                other_user: contactUser
            };

            this.playCallAudioFeedback('ringback');

            try {
                // If we have a target contact (e.g. from Members list) and conversation is missing or mismatched
                if (!targetConvId || this.activeConversation?.type === 'group' || (this.activeConversation?.other_user_id && Number(this.activeConversation.other_user_id) !== Number(targetReceiverId))) {
                    const existing = this.conversations.find(c =>
                        (c.type === 'direct' || c.type === 'private') && (Number(c.other_user_id) === Number(targetReceiverId) || (c.members && c.members.some(m => Number(m.user_id || m.id) === Number(targetReceiverId))))
                    );
                    if (existing) {
                        targetConvId = existing.id;
                    } else {
                        try {
                            const convRes = await fetch('/chat/conversations/private', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({ user_id: targetReceiverId })
                            });
                            const convData = await convRes.json();
                            if (convData.success && convData.conversation) {
                                targetConvId = convData.conversation.id;
                                this.conversations.unshift(convData.conversation);
                            }
                        } catch (e) {}
                    }
                }

                const res = await fetch('/chat/calls/initiate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        conversation_id: targetConvId,
                        receiver_id: targetReceiverId,
                        call_type: isVideo ? 'video' : 'voice'
                    })
                });
                const data = await res.json();
                if (!data.success || !data.call) {
                    this.cleanupCallState();
                    this.showToast(data.error || data.message || 'Unable to start call', 'error');
                    return;
                }

                // If user already tapped End Call while /chat/calls/initiate was in flight, cancel the created call immediately
                if (!this.callState.active) {
                    fetch(`/chat/calls/${data.call.id}/end`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ reason: 'cancelled' })
                    }).catch(() => {});
                    return;
                }

                this.callState.call = data.call;
                if (Array.isArray(data.call.ice_servers) && data.call.ice_servers.length > 0) {
                    this.callState.iceServers = data.call.ice_servers;
                }

                await this.setupPeerConnection(data.call.id, true, !!isVideo);
                this.startSignalPolling(data.call.id);

            } catch (err) {
                console.error('startVoiceCall error:', err);
                this.showToast('Failed to initiate call: ' + (err.message || 'Network error'), 'error');
                this.cleanupCallState();
            } finally {
                this._isInitiatingCall = false;
            }
        },

        /**
         * Attach local video stream to both PIP and main video viewport elements
         */
        attachLocalVideoPreview(stream) {
            this.$nextTick(() => {
                const localVideoEl = document.getElementById('chat-local-video-player');
                if (localVideoEl && stream) {
                    localVideoEl.srcObject = stream;
                    localVideoEl.muted = true;
                    localVideoEl.defaultMuted = true;
                    localVideoEl.playsInline = true;
                    localVideoEl.setAttribute('playsinline', '');
                    localVideoEl.setAttribute('webkit-playsinline', '');
                    localVideoEl.play().catch(e => console.warn('PIP local video play warning:', e));
                }

                // If remote video is not active, also show local camera on the main full-screen viewport
                const mainLocalEl = document.getElementById('chat-main-local-video-player');
                if (mainLocalEl && stream) {
                    mainLocalEl.srcObject = stream;
                    mainLocalEl.muted = true;
                    mainLocalEl.defaultMuted = true;
                    mainLocalEl.playsInline = true;
                    mainLocalEl.setAttribute('playsinline', '');
                    mainLocalEl.setAttribute('webkit-playsinline', '');
                    mainLocalEl.play().catch(e => console.warn('Main local video play warning:', e));
                }
            });
        },

        /**
         * Handle incoming tracks from remote peer with dedicated element binding
         */
        handleIncomingRemoteTrack(event) {
            if (!event || !event.track) return;
            const track = event.track;

            const streamToUse = (event.streams && event.streams[0]) ? event.streams[0] : (this.callState.remoteStream || new MediaStream());
            this.callState.remoteStream = streamToUse;
            if (!streamToUse.getTracks().some(t => t.id === track.id)) {
                streamToUse.addTrack(track);
            }

            if (track.kind === 'audio') {
                const audioEl = document.getElementById('chat-remote-audio-player');
                if (audioEl) {
                    if (audioEl.hasAttribute('src')) {
                        audioEl.removeAttribute('src');
                        audioEl.src = '';
                    }
                    audioEl.srcObject = streamToUse;
                    audioEl.muted = false;
                    audioEl.volume = 1.0;
                    audioEl.play().then(() => {
                        this.callState.audioBlocked = false;
                    }).catch(e => {
                        console.warn('Remote audio autoplay blocked:', e);
                        this.callState.audioBlocked = true;
                        this.routeRemoteAudio(streamToUse);
                    });
                }

                // If remote video element is present, also attach the synchronized stream with audio unmuted
                const remoteVideoEl = document.getElementById('chat-remote-video-player');
                if (remoteVideoEl) {
                    if (remoteVideoEl.hasAttribute('src')) {
                        remoteVideoEl.removeAttribute('src');
                    }
                    remoteVideoEl.srcObject = streamToUse;
                    remoteVideoEl.muted = false;
                    remoteVideoEl.volume = 1.0;
                    remoteVideoEl.play().catch(() => {});
                }

                track.onunmute = () => {
                    if (audioEl) audioEl.play().catch(() => {});
                    if (remoteVideoEl) remoteVideoEl.play().catch(() => {});
                };
            }

            if (track.kind === 'video') {
                this.callState.isVideo = true;

                const attachVideoToPlayer = () => {
                    this.$nextTick(() => {
                        const remoteVideoEl = document.getElementById('chat-remote-video-player');
                        if (remoteVideoEl) {
                            if (remoteVideoEl.hasAttribute('src')) {
                                remoteVideoEl.removeAttribute('src');
                            }
                            remoteVideoEl.srcObject = streamToUse;
                            remoteVideoEl.muted = false; // Audio heard through video element
                            remoteVideoEl.volume = 1.0;
                            remoteVideoEl.playsInline = true;
                            remoteVideoEl.setAttribute('playsinline', '');
                            remoteVideoEl.setAttribute('webkit-playsinline', '');
                            remoteVideoEl.play().catch(e => console.warn('Remote video play warning:', e));
                        }
                    });
                };

                track.onunmute = () => {
                    this.callState.remoteVideoActive = true;
                    this.callState.isVideo = true;
                    attachVideoToPlayer();
                };

                track.onmute = () => {
                    const hasActiveVideo = streamToUse.getVideoTracks().some(t => t.readyState === 'live' && !t.muted);
                    if (!hasActiveVideo) {
                        this.callState.remoteVideoActive = false;
                    }
                };

                track.onended = () => {
                    const live = streamToUse.getVideoTracks().filter(t => t.readyState === 'live');
                    if (live.length === 0) {
                        this.callState.remoteVideoActive = false;
                    }
                };

                if (track.readyState === 'live' && !track.muted) {
                    this.callState.remoteVideoActive = true;
                    attachVideoToPlayer();
                }
            }
        },

        /**
         * Attach remote video/audio stream to player elements and Web Audio API
         */
        attachRemoteMediaElements(stream) {
            if (!stream) return;
            this.callState.remoteStream = stream;

            const audioTracks = stream.getAudioTracks();
            if (audioTracks.length > 0) {
                const audioEl = document.getElementById('chat-remote-audio-player');
                if (audioEl) {
                    if (audioEl.hasAttribute('src')) {
                        audioEl.removeAttribute('src');
                        audioEl.src = '';
                    }
                    audioEl.srcObject = stream;
                    audioEl.muted = false;
                    audioEl.volume = 1.0;
                    audioEl.play().then(() => {
                        this.callState.audioBlocked = false;
                    }).catch(e => {
                        console.warn('Remote audio autoplay warning:', e);
                        this.callState.audioBlocked = true;
                        this.routeRemoteAudio(stream);
                    });
                }
            }

            const videoTracks = stream.getVideoTracks();
            const hasLiveVideo = videoTracks.some(t => t.readyState === 'live' && !t.muted);
            if (hasLiveVideo) {
                this.callState.remoteVideoActive = true;
                this.callState.isVideo = true;
            }

            this.$nextTick(() => {
                const remoteVideoEl = document.getElementById('chat-remote-video-player');
                if (remoteVideoEl) {
                    if (remoteVideoEl.hasAttribute('src')) {
                        remoteVideoEl.removeAttribute('src');
                    }
                    remoteVideoEl.srcObject = stream;
                    remoteVideoEl.muted = false; // Never mute remote video player!
                    remoteVideoEl.volume = 1.0;
                    remoteVideoEl.playsInline = true;
                    remoteVideoEl.setAttribute('playsinline', '');
                    remoteVideoEl.setAttribute('webkit-playsinline', '');
                    remoteVideoEl.play().catch(e => console.warn('Remote video play warning:', e));
                }
            });
        },

        /**
         * Route remote stream audio directly to AudioContext destination for loud, crisp audio
         */
        routeRemoteAudio(stream) {
            if (!stream || stream.getAudioTracks().length === 0) return;
            try {
                const ctx = this.getAudioContext();
                if (!ctx) return;
                if (ctx.state === 'suspended') ctx.resume();
                if (this._remoteAudioSourceNode) {
                    try { this._remoteAudioSourceNode.disconnect(); } catch (e) {}
                }
                this._remoteAudioSourceNode = ctx.createMediaStreamSource(stream);
                this._remoteAudioSourceNode.connect(ctx.destination);
            } catch (e) {
                console.warn('Direct WebAudio routing warning:', e);
            }
        },

        /**
         * User-triggered unblock if browser restricted background audio playback
         */
        unblockRemoteAudio() {
            this.callState.audioBlocked = false;
            this.unlockAudio();
            const ctx = this.getAudioContext();
            if (ctx && ctx.state === 'suspended') {
                ctx.resume().catch(() => {});
            }

            const audioEl = document.getElementById('chat-remote-audio-player');
            if (audioEl) {
                if (audioEl.hasAttribute('src')) {
                    audioEl.removeAttribute('src');
                    audioEl.src = '';
                }
                if (this.callState.remoteStream) {
                    audioEl.srcObject = this.callState.remoteStream;
                }
                audioEl.muted = false;
                audioEl.volume = 1.0;
                audioEl.play().catch(() => {});
            }

            const remoteVideoEl = document.getElementById('chat-remote-video-player');
            if (remoteVideoEl) {
                if (remoteVideoEl.hasAttribute('src')) {
                    remoteVideoEl.removeAttribute('src');
                }
                if (this.callState.remoteStream) {
                    remoteVideoEl.srcObject = this.callState.remoteStream;
                }
                remoteVideoEl.muted = false;
                remoteVideoEl.volume = 1.0;
                remoteVideoEl.play().catch(() => {});
            }

            if (this.callState.remoteStream) {
                this.routeRemoteAudio(this.callState.remoteStream);
            }
        },

        /**
         * Initialize RTCPeerConnection and audio/video streams with resilient device & network handling
         */
        async setupPeerConnection(callId, isCaller, withVideo = false) {
            try {
                if (this.callState.peerConnection) {
                    try { this.callState.peerConnection.close(); } catch (e) {}
                }

                const fallbackIceServers = [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' },
                    { urls: 'stun:stun2.l.google.com:19302' },
                    { urls: 'stun:stun3.l.google.com:19302' },
                    { urls: 'stun:stun4.l.google.com:19302' },
                    { urls: 'stun:stun.cloudflare.com:3478' },
                    { urls: 'stun:openrelay.metered.ca:80' },
                    {
                        urls: [
                            'turn:openrelay.metered.ca:80',
                            'turn:openrelay.metered.ca:443',
                            'turn:openrelay.metered.ca:443?transport=tcp',
                            'turns:openrelay.metered.ca:443?transport=tcp'
                        ],
                        username: 'openrelayproject',
                        credential: 'openrelayproject'
                    }
                ];

                const serversToUse = (Array.isArray(this.callState.iceServers) && this.callState.iceServers.length > 0)
                    ? this.callState.iceServers
                    : fallbackIceServers;

                const pc = new RTCPeerConnection({
                    iceServers: serversToUse,
                    iceCandidatePoolSize: 10
                });
                this.callState.peerConnection = pc;
                if (!this.callState.pendingCandidates) {
                    this.callState.pendingCandidates = [];
                }

                // 1. Acquire Local Audio & Video with resilient fallback
                let stream = null;
                const audioConstraints = {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                };
                const videoConstraints = {
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    facingMode: 'user'
                };

                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    if (withVideo) {
                        try {
                            stream = await navigator.mediaDevices.getUserMedia({
                                audio: audioConstraints,
                                video: videoConstraints
                            });
                            this.callState.isCameraOn = true;
                            this.callState.isVideo = true;
                        } catch (videoErr) {
                            try {
                                stream = await navigator.mediaDevices.getUserMedia({
                                    audio: true,
                                    video: true
                                });
                                this.callState.isCameraOn = true;
                                this.callState.isVideo = true;
                            } catch (e2) {
                                console.warn('Camera+Mic access failed, falling back to voice:', videoErr);
                                this.callState.isCameraOn = false;
                            }
                        }
                    }

                    if (!stream) {
                        try {
                            stream = await navigator.mediaDevices.getUserMedia({
                                audio: audioConstraints,
                                video: false
                            });
                        } catch (audioErr1) {
                            try {
                                stream = await navigator.mediaDevices.getUserMedia({
                                    audio: true,
                                    video: false
                                });
                            } catch (audioErr2) {
                                console.warn('Microphone unavailable, joining in receive-only mode:', audioErr2);
                                this.showToast('Microphone unavailable — joined in listen mode.', 'warning');
                            }
                        }
                    }
                }

                if (stream) {
                    this.callState.localStream = stream;
                    stream.getTracks().forEach(track => {
                        pc.addTrack(track, stream);
                    });
                    if (stream.getVideoTracks().length > 0) {
                        this.attachLocalVideoPreview(stream);
                    }
                }

                // Ensure audio transceiver exists (in case local mic is unavailable or in listen-only mode)
                const hasAudioSender = pc.getSenders().some(s => s.track && s.track.kind === 'audio');
                if (!hasAudioSender) {
                    try {
                        pc.addTransceiver('audio', { direction: 'recvonly' });
                    } catch (e) {}
                }

                // If user has no local video yet, add receive-only video transceiver so incoming video is received
                const hasVideoSender = pc.getSenders().some(s => s.track && s.track.kind === 'video');
                if (!hasVideoSender) {
                    try {
                        pc.addTransceiver('video', { direction: 'recvonly' });
                    } catch (e) {}
                }

                // 2. Track Event Handling
                pc.ontrack = (event) => {
                    this.handleIncomingRemoteTrack(event);
                };

                // 3. ICE Candidate Handling
                pc.onicecandidate = (event) => {
                    if (event.candidate) {
                        const candData = event.candidate.toJSON ? event.candidate.toJSON() : {
                            candidate: event.candidate.candidate,
                            sdpMid: event.candidate.sdpMid,
                            sdpMLineIndex: event.candidate.sdpMLineIndex
                        };
                        this.sendSignalToServer(callId, 'ice-candidate', candData);
                    }
                };

                // 4. ICE Connection State Monitoring
                pc.oniceconnectionstatechange = () => {
                    if (pc.iceConnectionState === 'connected' || pc.iceConnectionState === 'completed') {
                        this.callState.status = 'connected';
                        this.stopCallAudioFeedback();
                        this.playCallConnectedChime();
                        if (!this.callState.durationTimer) {
                            this.startCallDurationTimer();
                        }
                        if (this.callState.remoteStream) {
                            this.attachRemoteMediaElements(this.callState.remoteStream);
                        }
                    } else if (pc.iceConnectionState === 'failed') {
                        try { pc.restartIce(); } catch (e) {}
                    }
                };

                pc.onconnectionstatechange = () => {
                    if (pc.connectionState === 'connected') {
                        this.callState.status = 'connected';
                        this.stopCallAudioFeedback();
                        if (!this.callState.durationTimer) {
                            this.startCallDurationTimer();
                        }
                    }
                };

                // 5. If caller, generate & send SDP Offer
                if (isCaller) {
                    const offer = await pc.createOffer({
                        offerToReceiveAudio: true,
                        offerToReceiveVideo: true
                    });
                    await pc.setLocalDescription(offer);
                    await this.sendSignalToServer(callId, 'offer', {
                        type: offer.type,
                        sdp: offer.sdp
                    });
                }

            } catch (err) {
                console.error('setupPeerConnection error:', err);
                this.showToast('Call audio/video setup warning: ' + (err.message || 'Check permissions'), 'warning');
            }
        },

        async sendSignalToServer(callId, type, payload) {
            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                };
                if (this.pusherClient && this.pusherClient.connection && this.pusherClient.connection.socket_id) {
                    headers['X-Socket-ID'] = this.pusherClient.connection.socket_id;
                }
                const res = await fetch(`/chat/calls/${callId}/signal`, {
                    method: 'POST',
                    headers,
                    credentials: 'same-origin',
                    body: JSON.stringify({ type, payload })
                });
                const data = await res.json();
                if (data && data.signal_id) {
                    if (!this.callState._processedSignalIds) {
                        this.callState._processedSignalIds = new Set();
                    }
                    this.callState._processedSignalIds.add(Number(data.signal_id));
                }
            } catch (e) {
                console.warn('sendSignalToServer failed:', e);
            }
        },

        startSignalPolling(callId) {
            if (this.callState.signalPollTimer) clearInterval(this.callState.signalPollTimer);
            const runPoll = async () => {
                if (!this.callState.active || !this.callState.call || Number(this.callState.call.id) !== Number(callId)) {
                    if (this.callState.signalPollTimer) clearInterval(this.callState.signalPollTimer);
                    return;
                }
                try {
                    const safeLastId = (this.callState.lastSignalId && this.callState.lastSignalId < 1000000000)
                        ? this.callState.lastSignalId
                        : 0;
                    const res = await fetch(`/chat/calls/${callId}/signals?last_signal_id=${safeLastId}&after_id=${safeLastId}`, {
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (data && data.success) {
                        if (data.call_status) {
                            if (data.call_status === 'connected' && this.callState.status !== 'connected') {
                                this.callState.status = 'connected';
                                this.stopCallAudioFeedback();
                                if (!this.callState.durationTimer) {
                                    this.startCallDurationTimer();
                                }
                            } else if (['ended', 'declined', 'missed', 'cancelled'].includes(data.call_status)) {
                                this.cleanupCallState('Call ' + data.call_status);
                                return;
                            }
                        }
                        if (Array.isArray(data.signals)) {
                            for (const sig of data.signals) {
                                await this.handleIncomingSignal(sig);
                            }
                        }
                    }
                } catch (e) {
                    console.warn('Signal polling warning:', e);
                }
            };

            runPoll();
            this.callState.signalPollTimer = setInterval(runPoll, 450);
        },

        async flushPendingIceCandidates() {
            if (this.callState.peerConnection && this.callState.peerConnection.remoteDescription && Array.isArray(this.callState.pendingCandidates)) {
                const queue = [...this.callState.pendingCandidates];
                this.callState.pendingCandidates = [];
                for (const cand of queue) {
                    try {
                        if (!cand || (!cand.candidate && cand.candidate !== '')) continue;
                        await this.callState.peerConnection.addIceCandidate(new RTCIceCandidate(cand));
                    } catch (e) {
                        console.warn('addIceCandidate warning:', e);
                    }
                }
            }
        },

        /**
         * Safely apply remote SDP Offer and send SDP Answer with mutex guard
         */
        async applyRemoteOfferAndAnswer(callId, offerPayload) {
            const pc = this.callState.peerConnection;
            if (!pc || !offerPayload || this.callState._isHandlingOffer) return;

            this.callState._isHandlingOffer = true;
            try {
                if (offerPayload.sdp && offerPayload.sdp.includes('m=video') && !offerPayload.sdp.includes('m=video 0')) {
                    this.callState.isVideo = true;
                }

                if (pc.signalingState !== 'stable') {
                    try {
                        await Promise.all([
                            pc.setLocalDescription({ type: 'rollback' }),
                            pc.setRemoteDescription(new RTCSessionDescription(offerPayload))
                        ]);
                    } catch (e) {
                        await pc.setRemoteDescription(new RTCSessionDescription(offerPayload));
                    }
                } else {
                    await pc.setRemoteDescription(new RTCSessionDescription(offerPayload));
                }

                const answer = await pc.createAnswer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: true
                });
                await pc.setLocalDescription(answer);
                await this.flushPendingIceCandidates();
                await this.sendSignalToServer(callId, 'answer', {
                    type: answer.type,
                    sdp: answer.sdp
                });
            } catch (err) {
                console.warn('applyRemoteOfferAndAnswer warning:', err);
            } finally {
                this.callState._isHandlingOffer = false;
            }
        },

        async handleIncomingSignal(sig) {
            if (!sig) return;

            // Ignore signals sent by ourselves if sender_id is present
            if (sig.sender_id && Number(sig.sender_id) === Number(this.currentUser.id)) {
                if (sig.id && Number(sig.id) < 1000000000) {
                    this.callState.lastSignalId = Math.max(this.callState.lastSignalId, Number(sig.id));
                }
                return;
            }

            // Deduplicate signals that arrive via both Pusher and HTTP polling
            if (sig.id && Number(sig.id) < 1000000000) {
                const numericId = Number(sig.id);
                this.callState.lastSignalId = Math.max(this.callState.lastSignalId, numericId);
                if (!this.callState._processedSignalIds) {
                    this.callState._processedSignalIds = new Set();
                }
                if (this.callState._processedSignalIds.has(numericId)) {
                    return;
                }
                this.callState._processedSignalIds.add(numericId);
            }

            const sigType = sig.type;

            if (sigType === 'offer') {
                this.callState.remoteOffer = sig.payload;
                // Answer offer if receiver has already accepted & created peerConnection, or during mid-call video upgrade
                if (this.callState.peerConnection && this.callState.call) {
                    await this.applyRemoteOfferAndAnswer(this.callState.call.id, sig.payload);
                }
            } else if (sigType === 'answer') {
                if (this.callState.peerConnection && (this.callState.peerConnection.signalingState === 'have-local-offer' || this.callState.peerConnection.signalingState === 'have-remote-offer')) {
                    try {
                        await this.callState.peerConnection.setRemoteDescription(new RTCSessionDescription(sig.payload));
                        await this.flushPendingIceCandidates();
                        this.callState.status = 'connected';
                        this.stopCallAudioFeedback();
                        if (!this.callState.durationTimer) {
                            this.startCallDurationTimer();
                        }
                    } catch (err) {
                        console.warn('Remote answer warning:', err);
                    }
                }
            } else if (sigType === 'ice-candidate' || sigType === 'ice_candidate') {
                const cand = sig.payload;
                if (!cand || (!cand.candidate && cand.candidate !== '')) return;
                const candidateInit = {
                    candidate: cand.candidate,
                    sdpMid: cand.sdpMid !== undefined && cand.sdpMid !== null ? String(cand.sdpMid) : null,
                    sdpMLineIndex: cand.sdpMLineIndex !== undefined && cand.sdpMLineIndex !== null ? Number(cand.sdpMLineIndex) : null,
                    usernameFragment: cand.usernameFragment || undefined
                };
                if (this.callState.peerConnection && this.callState.peerConnection.remoteDescription && this.callState.peerConnection.remoteDescription.type) {
                    try {
                        await this.callState.peerConnection.addIceCandidate(new RTCIceCandidate(candidateInit));
                    } catch (e) {
                        console.warn('ICE candidate warning:', e);
                    }
                } else {
                    if (!this.callState.pendingCandidates) this.callState.pendingCandidates = [];
                    this.callState.pendingCandidates.push(candidateInit);
                }
            } else if (sigType === 'video_on') {
                this.callState.remoteVideoActive = true;
                this.callState.isVideo = true;
            } else if (sigType === 'video_off') {
                this.callState.remoteVideoActive = false;
            } else if (sigType === 'end') {
                this.cleanupCallState('Call ended by other party');
            }
        },

        async acceptIncomingCall(withVideo = null) {
            if (!this.callState.call) return;
            this.stopCallAudioFeedback();
            const callId = this.callState.call.id;
            const wantVideo = withVideo !== null ? !!withVideo : !!this.callState.isVideo;

            // Pause background signal polling during setup to prevent concurrent races
            if (this.callState.signalPollTimer) {
                clearInterval(this.callState.signalPollTimer);
                this.callState.signalPollTimer = null;
            }

            // Pre-unlock remote audio element on user click gesture so autoplay never blocks
            this.unlockAudio();
            const audioEl = document.getElementById('chat-remote-audio-player');
            if (audioEl) {
                if (audioEl.hasAttribute('src')) {
                    audioEl.removeAttribute('src');
                    audioEl.src = '';
                }
                audioEl.muted = false;
                audioEl.volume = 1.0;
            }
            const ctx = this.getAudioContext();
            if (ctx && ctx.state === 'suspended') {
                ctx.resume().catch(() => {});
            }

            try {
                const res = await fetch(`/chat/calls/${callId}/accept`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (!data.success) {
                    this.showToast(data.message || 'Unable to accept call', 'error');
                    this.cleanupCallState();
                    return;
                }

                // Transition UI immediately to connected so the call window stays open
                this.callState.status = 'connected';
                this.callState.isVideo = wantVideo || this.callState.isVideo;
                this.startCallDurationTimer();

                // Fetch all pending signals (offer + any candidates)
                try {
                    const sigRes = await fetch(`/chat/calls/${callId}/signals?after_id=0`, { credentials: 'same-origin' });
                    const sigData = await sigRes.json();
                    if (sigData.success && Array.isArray(sigData.signals)) {
                        if (!this.callState._processedSignalIds) {
                            this.callState._processedSignalIds = new Set();
                        }
                        if (!this.callState.pendingCandidates) {
                            this.callState.pendingCandidates = [];
                        }
                        for (const s of sigData.signals) {
                            if (s.id) {
                                this.callState.lastSignalId = Math.max(this.callState.lastSignalId, Number(s.id));
                                this.callState._processedSignalIds.add(Number(s.id));
                            }
                            if (s.type === 'offer') {
                                this.callState.remoteOffer = s.payload;
                            } else if ((s.type === 'ice-candidate' || s.type === 'ice_candidate') && s.payload) {
                                const cand = s.payload;
                                if (cand && (cand.candidate || cand.candidate === '')) {
                                    this.callState.pendingCandidates.push({
                                        candidate: cand.candidate,
                                        sdpMid: cand.sdpMid !== undefined && cand.sdpMid !== null ? String(cand.sdpMid) : null,
                                        sdpMLineIndex: cand.sdpMLineIndex !== undefined && cand.sdpMLineIndex !== null ? Number(cand.sdpMLineIndex) : null,
                                        usernameFragment: cand.usernameFragment || undefined
                                    });
                                }
                            } else if (s.type === 'video_on') {
                                this.callState.remoteVideoActive = true;
                                this.callState.isVideo = true;
                            }
                        }
                    }
                } catch (e) {
                    console.warn('Initial signal fetch warning:', e);
                }

                await this.setupPeerConnection(callId, false, wantVideo);

                if (this.callState.remoteOffer && this.callState.peerConnection) {
                    await this.applyRemoteOfferAndAnswer(callId, this.callState.remoteOffer);
                }

                // Resume polling for subsequent ICE candidates or signals
                this.startSignalPolling(callId);
            } catch (e) {
                console.error('acceptIncomingCall error:', e);
                if (this.callState.status === 'connected') {
                    this.startSignalPolling(callId);
                } else {
                    this.cleanupCallState();
                }
            }
        },

        async declineIncomingCall() {
            if (!this.callState.call) return;
            const callId = this.callState.call.id;
            this.stopCallAudioFeedback();
            try {
                await fetch(`/chat/calls/${callId}/decline`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                });
            } catch (e) {}
            this.cleanupCallState();
        },

        async endCurrentCall(reason = 'normal') {
            if (!this.callState.call || !this.callState.call.id) {
                this.cleanupCallState();
                return;
            }
            const callId = this.callState.call.id;
            this.sendSignalToServer(callId, 'end', { reason });
            try {
                await fetch(`/chat/calls/${callId}/end`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ reason })
                });
            } catch (e) {}
            this.cleanupCallState();
            if (this.activeConversation) {
                this.syncActiveConversation();
            }
        },

        cleanupCallState(reason = null) {
            if (this.callState.localStream) {
                this.callState.localStream.getTracks().forEach(t => {
                    try { t.stop(); } catch (e) {}
                });
                this.callState.localStream = null;
            }
            if (this.callState.peerConnection) {
                try { this.callState.peerConnection.close(); } catch (e) {}
                this.callState.peerConnection = null;
            }
            if (this.callState.durationTimer) {
                clearInterval(this.callState.durationTimer);
                this.callState.durationTimer = null;
            }
            if (this.callState.signalPollTimer) {
                clearInterval(this.callState.signalPollTimer);
                this.callState.signalPollTimer = null;
            }
            this.stopCallAudioFeedback();
            const audioEl = document.getElementById('chat-remote-audio-player');
            if (audioEl) {
                audioEl.srcObject = null;
            }
            const remoteVideoEl = document.getElementById('chat-remote-video-player');
            if (remoteVideoEl) {
                remoteVideoEl.srcObject = null;
            }
            const localVideoEl = document.getElementById('chat-local-video-player');
            if (localVideoEl) {
                localVideoEl.srcObject = null;
            }
            const mainLocalVideoEl = document.getElementById('chat-main-local-video-player');
            if (mainLocalVideoEl) {
                mainLocalVideoEl.srcObject = null;
            }
            if (this._remoteAudioSourceNode) {
                try { this._remoteAudioSourceNode.disconnect(); } catch (e) {}
                this._remoteAudioSourceNode = null;
            }

            this.callState.active = false;
            this.callState.status = 'idle';
            this.callState.call = null;
            this.callState.isCaller = false;
            this.callState.isMuted = false;
            this.callState.isVideo = false;
            this.callState.isCameraOn = false;
            this.callState.remoteVideoActive = false;
            this.callState.duration = 0;
            this.callState.minimized = false;
            this.callState.remoteStream = null;
            this.callState.remoteOffer = null;
            this.callState.pendingCandidates = [];
            this.callState._isHandlingOffer = false;
            this.callState._processedSignalIds = null;
            this.callState.audioBlocked = false;
            this.callState.swappedVideoView = false;
            this._isInitiatingCall = false;
            if (reason) {
                this.playCallEndedChime();
            }
        },

        toggleMuteMic() {
            this.callState.isMuted = !this.callState.isMuted;
            if (this.callState.localStream) {
                this.callState.localStream.getAudioTracks().forEach(t => {
                    t.enabled = !this.callState.isMuted;
                });
            }
            if (this.callState.call) {
                this.sendSignalToServer(this.callState.call.id, this.callState.isMuted ? 'mute' : 'unmute', {});
            }
        },

        /**
         * Toggle local camera on/off during an active voice or video call
         */
        async toggleCamera() {
            if (!this.callState.active || !this.callState.call) return;
            const callId = this.callState.call.id;
            const pc = this.callState.peerConnection;

            // If we have an active live video track, toggle its enabled state
            const activeVideoTrack = this.callState.localStream ? this.callState.localStream.getVideoTracks().find(t => t.readyState === 'live') : null;
            if (activeVideoTrack) {
                this.callState.isCameraOn = !this.callState.isCameraOn;
                activeVideoTrack.enabled = this.callState.isCameraOn;
                if (this.callState.isCameraOn) {
                    this.callState.isVideo = true;
                    this.attachLocalVideoPreview(this.callState.localStream);
                }
                await this.sendSignalToServer(callId, this.callState.isCameraOn ? 'video_on' : 'video_off', {});
                return;
            }

            // Otherwise, acquire camera stream and add/replace track on the PeerConnection
            try {
                const videoStream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' },
                    audio: false
                });
                const videoTrack = videoStream.getVideoTracks()[0];
                if (!videoTrack) return;

                if (!this.callState.localStream) {
                    this.callState.localStream = new MediaStream();
                }
                this.callState.localStream.addTrack(videoTrack);
                this.callState.isCameraOn = true;
                this.callState.isVideo = true;
                this.attachLocalVideoPreview(this.callState.localStream);

                if (pc) {
                    const senders = pc.getSenders();
                    const videoSender = senders.find(s => (s.track && s.track.kind === 'video') || (!s.track && s.dtlsTransport));
                    const transceiver = pc.getTransceivers().find(t => t.sender === videoSender || t.receiver?.track?.kind === 'video');

                    if (videoSender) {
                        await videoSender.replaceTrack(videoTrack);
                        if (transceiver && transceiver.direction !== 'sendrecv') {
                            transceiver.direction = 'sendrecv';
                        }
                    } else {
                        pc.addTrack(videoTrack, this.callState.localStream);
                    }

                    await this.sendSignalToServer(callId, 'video_on', {});

                    // Renegotiate SDP if stable
                    if (pc.signalingState === 'stable') {
                        const offer = await pc.createOffer({
                            offerToReceiveAudio: true,
                            offerToReceiveVideo: true
                        });
                        await pc.setLocalDescription(offer);
                        await this.sendSignalToServer(callId, 'offer', {
                            type: offer.type,
                            sdp: offer.sdp
                        });
                    }
                }
            } catch (err) {
                console.warn('toggleCamera error:', err);
                this.showToast('Unable to access camera: ' + (err.message || 'Permission denied'), 'error');
            }
        },

        toggleMinimizeCall() {
            this.callState.minimized = !this.callState.minimized;
        },

        startCallDurationTimer() {
            if (this.callState.durationTimer) clearInterval(this.callState.durationTimer);
            this.callState.duration = 0;
            this.callState.durationTimer = setInterval(() => {
                this.callState.duration++;
            }, 1000);
        },

        formatCallDuration(sec) {
            if (!sec || isNaN(sec) || sec < 0) return '00:00';
            const m = Math.floor(sec / 60);
            const s = sec % 60;
            if (m >= 60) {
                const h = Math.floor(m / 60);
                const remM = m % 60;
                return `${h}:${remM < 10 ? '0' : ''}${remM}:${s < 10 ? '0' : ''}${s}`;
            }
            return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`;
        },

        /**
         * Get Human-Friendly Contact Name for Call UI (Prevents generic "Contact" fallback)
         */
        getCallContactName() {
            const call = this.callState.call;
            if (call) {
                if (this.callState.isCaller) {
                    if (call.receiver && call.receiver.name) return call.receiver.name;
                    if (call.other_user && call.other_user.name) return call.other_user.name;
                } else {
                    if (call.caller && call.caller.name) return call.caller.name;
                    if (call.other_user && call.other_user.name) return call.other_user.name;
                }
            }
            if (this.activeConversation) {
                if (this.activeConversation.user && this.activeConversation.user.name) return this.activeConversation.user.name;
                if (this.activeConversation.name) return this.activeConversation.name;
            }
            return 'Team Member';
        },

        /**
         * Get Contact Avatar URL for Call UI
         */
        getCallContactAvatar() {
            const call = this.callState.call;
            let avatar = null;
            const name = this.getCallContactName();
            if (call) {
                if (this.callState.isCaller) {
                    avatar = call.receiver?.avatar_url || call.other_user?.avatar_url;
                } else {
                    avatar = call.caller?.avatar_url || call.other_user?.avatar_url;
                }
            }
            if (!avatar && this.activeConversation) {
                avatar = this.activeConversation.user?.avatar_url || this.activeConversation.avatar_url;
            }
            return this.avatarUrl(avatar, name);
        },

        getAudioContext() {
            if (!this.audioCtx) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx) this.audioCtx = new AudioCtx();
            }
            if (this.audioCtx && this.audioCtx.state === 'suspended') {
                this.audioCtx.resume().catch(() => {});
            }
            return this.audioCtx;
        },

        playCallAudioFeedback(type) {
            this.stopCallAudioFeedback();
            const ctx = this.getAudioContext();
            if (!ctx) return;

            if (type === 'ringback') {
                // Outgoing Ringback Tone: US/Europe dual frequency 440Hz + 480Hz
                const playTone = () => {
                    if (!this.callState.active || this.callState.status !== 'calling') return;
                    try {
                        const now = ctx.currentTime;
                        const osc1 = ctx.createOscillator();
                        const osc2 = ctx.createOscillator();
                        const gain = ctx.createGain();

                        osc1.type = 'sine';
                        osc2.type = 'sine';
                        osc1.frequency.setValueAtTime(440, now);
                        osc2.frequency.setValueAtTime(480, now);

                        gain.gain.setValueAtTime(0.28, now);
                        gain.gain.setValueAtTime(0.28, now + 1.8);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 2.0);

                        osc1.connect(gain);
                        osc2.connect(gain);
                        gain.connect(ctx.destination);

                        osc1.start(now);
                        osc2.start(now);
                        osc1.stop(now + 2.0);
                        osc2.stop(now + 2.0);
                    } catch (e) {
                        console.warn('Ringback audio error:', e);
                    }
                };
                playTone();
                this.callState.ringtoneInterval = setInterval(playTone, 3800);
            } else if (type === 'ringing') {
                // Incoming Call Ringtone: Loud, melodic marimba chime phrase (WhatsApp style)
                const playChime = () => {
                    if (!this.callState.active || this.callState.status !== 'ringing') return;
                    try {
                        const now = ctx.currentTime;
                        // Melody: C5, E5, G5, C6, B5, G5, E5, C6
                        const notes = [
                            { f: 523.25, t: 0.00, d: 0.22 },
                            { f: 659.25, t: 0.16, d: 0.22 },
                            { f: 783.99, t: 0.32, d: 0.22 },
                            { f: 1046.50, t: 0.48, d: 0.35 },
                            { f: 987.77, t: 0.70, d: 0.22 },
                            { f: 783.99, t: 0.86, d: 0.22 },
                            { f: 659.25, t: 1.02, d: 0.25 },
                            { f: 1046.50, t: 1.22, d: 0.45 }
                        ];

                        notes.forEach(n => {
                            const osc = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.type = 'triangle';
                            osc.frequency.setValueAtTime(n.f, now + n.t);

                            gain.gain.setValueAtTime(0.35, now + n.t);
                            gain.gain.exponentialRampToValueAtTime(0.001, now + n.t + n.d);

                            osc.connect(gain);
                            gain.connect(ctx.destination);

                            osc.start(now + n.t);
                            osc.stop(now + n.t + n.d + 0.05);
                        });
                    } catch (e) {
                        console.warn('Incoming ringtone error:', e);
                    }
                };
                playChime();
                this.callState.ringtoneInterval = setInterval(playChime, 2400);
            }
        },

        stopCallAudioFeedback() {
            if (this.callState.ringtoneInterval) {
                clearInterval(this.callState.ringtoneInterval);
                this.callState.ringtoneInterval = null;
            }
        },

        playCallConnectedChime() {
            const ctx = this.getAudioContext();
            if (!ctx) return;
            try {
                const now = ctx.currentTime;
                [ { f: 587.33, t: 0.0 }, { f: 880.00, t: 0.12 } ].forEach(n => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(n.f, now + n.t);
                    gain.gain.setValueAtTime(0.25, now + n.t);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + n.t + 0.2);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now + n.t);
                    osc.stop(now + n.t + 0.25);
                });
            } catch (e) {}
        },

        playCallEndedChime() {
            const ctx = this.getAudioContext();
            if (!ctx) return;
            try {
                const now = ctx.currentTime;
                [ { f: 659.25, t: 0.0 }, { f: 440.00, t: 0.14 } ].forEach(n => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(n.f, now + n.t);
                    gain.gain.setValueAtTime(0.2, now + n.t);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + n.t + 0.25);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now + n.t);
                    osc.stop(now + n.t + 0.3);
                });
            } catch (e) {}
        },

        async pollActiveCallCheck() {
            if (document.hidden) return;
            // Never interfere with or terminate an active call via periodic background sync!
            if (this.callState.active || this._isInitiatingCall) return;
            try {
                const res = await fetch('/chat/calls/active', {
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success && data.active && data.call) {
                    const call = data.call;
                    const receiverId = Number(call.receiver_id || call.receiver?.id || 0);
                    const callerId = Number(call.caller_id || call.caller?.id || 0);
                    const isCaller = call.is_caller !== undefined ? !!call.is_caller : (callerId === Number(this.currentUser.id));

                    if (!this.callState.active && (call.status === 'ringing' || call.status === 'calling') && (!isCaller || receiverId === Number(this.currentUser.id))) {
                        this.callState.active = true;
                        this.callState.minimized = false;
                        this.callState.call = call;
                        this.callState.isCaller = false;
                        this.callState.status = 'ringing';
                        this.callState.isVideo = (call.call_type === 'video');
                        this.callState.isCameraOn = false;
                        this.callState.remoteVideoActive = false;
                        this.callState.lastSignalId = 0;
                        this.callState.pendingCandidates = [];
                        this.callState._processedSignalIds = new Set();
                        if (Array.isArray(call.ice_servers) && call.ice_servers.length > 0) {
                            this.callState.iceServers = call.ice_servers;
                        }
                        this.playCallAudioFeedback('ringing');
                        this.startSignalPolling(call.id);
                    }
                }
            } catch (e) {}
        },

        resumeActiveCall(call) {
            if (!call || !call.id) {
                this.callState.active = false;
                this.callState.call = null;
                this.callState.status = 'idle';
                return;
            }

            const callerId = Number(call.caller_id || call.caller?.id || 0);
            const isCaller = call.is_caller !== undefined ? !!call.is_caller : (callerId === Number(this.currentUser.id));

            // Only resume an incoming unanswered call on page reload so the recipient can accept it.
            // Any previous outgoing or connected call lost its RTCPeerConnection on page reload and must be cleaned up.
            if (!isCaller && (call.status === 'ringing' || call.status === 'calling')) {
                this.callState.active = true;
                this.callState.minimized = false;
                this.callState.call = call;
                this.callState.isCaller = false;
                this.callState.status = 'ringing';
                this.callState.isVideo = (call.call_type === 'video');
                this.callState.isCameraOn = false;
                this.callState.remoteVideoActive = false;
                this.callState.duration = 0;
                this.callState.lastSignalId = 0;
                this.callState.pendingCandidates = [];
                this.callState._processedSignalIds = new Set();
                if (Array.isArray(call.ice_servers) && call.ice_servers.length > 0) {
                    this.callState.iceServers = call.ice_servers;
                }
                this.playCallAudioFeedback('ringing');
                this.startSignalPolling(call.id);
            } else {
                // Clean up orphaned call from before page reload so callState.active is never stuck
                const staleId = call.id;
                this.callState.active = false;
                this.callState.call = null;
                this.callState.status = 'idle';
                this.callState.isCaller = false;
                fetch(`/chat/calls/${staleId}/end`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ reason: 'page_reload' })
                }).catch(() => {});
            }
        }
    };
};

/**
 * Telegram-Style Swipe Item Component for Chat Cards
 * Allows user to hold and move / swipe to reveal Pin (Green), Delete (Red), and Archive (Grey) actions
 */
window.telegramSwipeItem = function(conv) {
    return {
        offset: 0,
        startX: 0,
        startY: 0,
        initialOffset: 0,
        isDragging: false,
        hasMoved: false,
        justSwiped: false,
        isOpen: false,
        longPressTimer: null,

        initSwipe() {
            window.addEventListener('dgt-close-all-swipes', (e) => {
                if (e.detail?.exceptId !== conv.id && this.offset !== 0) {
                    this.offset = 0;
                    this.isOpen = false;
                }
            });
        },

        onPointerDown(e, openMenuFn) {
            // Ignore mouse right-click so contextmenu event fires smoothly
            if (e.pointerType === 'mouse' && e.button !== 0) return;

            this.startX = e.clientX;
            this.startY = e.clientY;
            this.initialOffset = this.offset;
            this.isDragging = false;
            this.hasMoved = false;

            // Long press support for touch screens (Telegram mobile experience)
            if (e.pointerType === 'touch' && typeof openMenuFn === 'function') {
                clearTimeout(this.longPressTimer);
                this.longPressTimer = setTimeout(() => {
                    if (!this.hasMoved && !this.isDragging) {
                        openMenuFn(e, conv);
                    }
                }, 550);
            }

            try {
                e.currentTarget?.setPointerCapture?.(e.pointerId);
            } catch (err) {}
        },

        onPointerMove(e) {
            if (!this.startX) return;
            const dx = e.clientX - this.startX;
            const dy = e.clientY - this.startY;

            if (Math.abs(dx) > 6 || Math.abs(dy) > 6) {
                clearTimeout(this.longPressTimer);
            }

            if (!this.isDragging) {
                // If vertical movement dominates, cancel swipe so natural list scroll works uninterrupted
                if (Math.abs(dy) > Math.abs(dx) && Math.abs(dy) > 6) {
                    this.startX = 0;
                    return;
                }
                if (Math.abs(dx) > 6) {
                    this.isDragging = true;
                    this.hasMoved = true;
                    window.dispatchEvent(new CustomEvent('dgt-close-all-swipes', { detail: { exceptId: conv.id } }));
                }
            }

            if (this.isDragging) {
                if (e.cancelable) e.preventDefault();
                const target = this.initialOffset + dx;
                // Only allow swiping to the left: clamp between 0 (closed) and -210px (fully open)
                this.offset = Math.min(0, Math.max(-210, target));
            }
        },

        onPointerUp(e) {
            clearTimeout(this.longPressTimer);
            try {
                e.currentTarget?.releasePointerCapture?.(e.pointerId);
            } catch (err) {}

            if (!this.startX && !this.isDragging) return;
            this.startX = 0;

            if (this.isDragging) {
                this.isDragging = false;
                this.justSwiped = true;
                setTimeout(() => { this.justSwiped = false; }, 250);

                // Snap threshold: if swiped more than 60px to the left, open full 210px; else snap back to 0
                if (this.offset < -60) {
                    this.offset = -210;
                    this.isOpen = true;
                } else {
                    this.offset = 0;
                    this.isOpen = false;
                }
            }
        },

        onPointerCancel(e) {
            clearTimeout(this.longPressTimer);
            try {
                e.currentTarget?.releasePointerCapture?.(e.pointerId);
            } catch (err) {}

            this.startX = 0;
            this.isDragging = false;
            if (this.offset < -60) {
                this.offset = -210;
                this.isOpen = true;
            } else {
                this.offset = 0;
                this.isOpen = false;
            }
        },

        onCardClick(e, conv, selectFn) {
            clearTimeout(this.longPressTimer);
            if (this.justSwiped || this.hasMoved) {
                e.stopPropagation();
                return;
            }
            if (this.isOpen || this.offset !== 0) {
                // If card is currently swiped open, clicking it snaps it closed
                e.stopPropagation();
                this.offset = 0;
                this.isOpen = false;
                return;
            }
            if (typeof selectFn === 'function') {
                selectFn(conv);
            }
        },

        closeSwipe() {
            this.offset = 0;
            this.isOpen = false;
        }
    };
};

// Register Alpine Components for DGT Chat
if (typeof window.Alpine !== 'undefined') {
    if (typeof window.dgtChat === 'function') window.Alpine.data('dgtChat', window.dgtChat);
    if (typeof window.telegramSwipeItem === 'function') window.Alpine.data('telegramSwipeItem', window.telegramSwipeItem);
} else {
    document.addEventListener('alpine:init', () => {
        if (typeof window.Alpine !== 'undefined') {
            if (typeof window.dgtChat === 'function') window.Alpine.data('dgtChat', window.dgtChat);
            if (typeof window.telegramSwipeItem === 'function') window.Alpine.data('telegramSwipeItem', window.telegramSwipeItem);
        }
    });
}


