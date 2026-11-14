/**
 * KIUQ SYSTEM — Global Real-Time WebRTC Voice & Video Call Engine
 * Works across all authenticated pages (Desktop Web, Mobile Safari/Chrome, and Native Flutter Bridge).
 */
(function () {
    if (window.DgtWebRtcCallManager) {
        return;
    }

    const DEFAULT_ICE_SERVERS = [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
        { urls: 'stun:stun.cloudflare.com:3478' },
    ];

    class WebRtcCallEngine {
        constructor() {
            this.ui = null;
            this.pc = null;
            this.localStream = null;
            this.remoteStream = null;
            this.pendingCandidates = [];
            this.processedSignalKeys = new Set();
            this.iceServers = DEFAULT_ICE_SERVERS;
            this.iceTransportPolicy = 'all';
            this.ringTimeoutSeconds = 45;

            this.signalPollTimer = null;
            this.activePollTimer = null;
            this.durationTimer = null;
            this.ringTimeoutTimer = null;
            this.reconnectTimer = null;
            this.ringtoneInterval = null;
            this.activeOscillators = new Set();
            this.audioCtx = null;

            this.lastSignalId = 0;
            this.iceRestartAttempted = false;
            this.isInitiating = false;
            this.pusherBound = false;
            this.availableAudioOutputs = [];
            this.currentAudioOutputIndex = 0;

            this.state = this.defaultState();
        }

        defaultState() {
            return {
                active: false,
                minimized: false,
                isCaller: false,
                status: 'idle', // 'idle' | 'calling' | 'ringing' | 'connecting' | 'connected' | 'reconnecting' | 'permission_error' | 'ended'
                callId: 0,
                conversationId: 0,
                otherUserId: 0,
                contactName: 'Team Member',
                contactAvatar: '',
                isVideo: false,
                isMuted: false,
                isCameraOn: false,
                facingMode: 'user',
                canSwitchCamera: false,
                speakerOn: true,
                ringtoneMuted: false,
                remoteVideoActive: false,
                remoteMuted: false,
                audioBlocked: false,
                networkQuality: 'good', // 'good' | 'fair' | 'poor'
                permissionErrorMessage: '',
                duration: 0,
                call: null,
            };
        }

        get authUserId() {
            const meta = document.querySelector('meta[name="kiuq-user-id"]')?.content;
            return meta ? Number(meta) : 0;
        }

        get csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }

        get socketId() {
            return window.__kiuqPusher?.connection?.socket_id || '';
        }

        isNativeMobileEngineAvailable() {
            return Boolean(
                window.__dgtNativeWebRtcAvailable === true &&
                Number(window.__dgtNativeWebRtcBridgeVersion || 0) >= 3 &&
                window.flutter_inappwebview &&
                typeof window.flutter_inappwebview.callHandler === 'function'
            );
        }

        registerUi(alpineComponent) {
            this.ui = alpineComponent;
            this.ensurePersistentAudioElement();
            this.syncUi();
            this.bindPusherChannel();
            this.refreshDeviceCapabilities();

            if (this.state.active) {
                this.attachLocalVideoElements();
                this.attachRemoteVideoElement();
                this.attachRemoteAudioElement();
            } else {
                this.checkExistingActiveCall(true);
            }

            if (!this.activePollTimer) {
                this.activePollTimer = setInterval(() => {
                    if (!document.hidden && !this.state.active && !this.isInitiating) {
                        this.checkExistingActiveCall(false);
                    }
                }, 3500);
            }
        }

        ensurePersistentAudioElement() {
            let audioEl = document.getElementById('dgt-persistent-remote-audio');
            if (!audioEl && document.documentElement) {
                audioEl = document.createElement('audio');
                audioEl.id = 'dgt-persistent-remote-audio';
                audioEl.autoplay = true;
                audioEl.playsInline = true;
                audioEl.setAttribute('playsinline', '');
                audioEl.setAttribute('webkit-playsinline', '');
                audioEl.style.cssText = 'position:fixed;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;';
                document.documentElement.appendChild(audioEl);
            }
            return audioEl || document.getElementById('dgt-global-remote-audio');
        }

        attachRemoteAudioElement() {
            const audioEl = this.ensurePersistentAudioElement();
            if (audioEl && this.remoteStream && this.remoteStream.getAudioTracks().length > 0) {
                if (audioEl.srcObject !== this.remoteStream) {
                    audioEl.srcObject = this.remoteStream;
                }
                audioEl.muted = false;
                audioEl.volume = this.state.speakerOn ? 1.0 : 0.45;
                audioEl.play().then(() => {
                    this.updateState({ audioBlocked: false });
                }).catch(() => {
                    this.updateState({ audioBlocked: true });
                });
            }
        }

        ensureUiReady() {
            const root = document.getElementById('dgt-global-webrtc-root');
            if (!root) return;
            const uiDetached = !this.ui || (this.ui.$el && !document.body.contains(this.ui.$el));
            if (uiDetached && window.Alpine && typeof window.Alpine.initTree === 'function') {
                try {
                    window.Alpine.initTree(root);
                } catch (_) {}
            }
        }

        syncUi() {
            if (this.ui) {
                try {
                    Object.assign(this.ui, { ...this.state });
                } catch (_) {}
            }
            try {
                window.dispatchEvent(new CustomEvent('dgt-webrtc-state', {
                    detail: { ...this.state },
                }));
            } catch (_) {}
        }

        updateState(patch) {
            Object.assign(this.state, patch);
            this.syncUi();
        }

        notifyToast(message, type = 'info') {
            const tone = type === 'warning' ? 'warning' : (type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'));
            try {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { msg: message, type: tone },
                }));
            } catch (_) {}
            if (typeof window.showToast === 'function') {
                try { window.showToast(message, tone); } catch (_) {}
            }
        }

        headers(json = true) {
            const h = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            };
            if (json) {
                h['Content-Type'] = 'application/json';
            }
            if (this.socketId) {
                h['X-Socket-ID'] = this.socketId;
            }
            return h;
        }

        async apiPost(url, body = {}) {
            const res = await fetch(url, {
                method: 'POST',
                headers: this.headers(true),
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.success === false) {
                const err = new Error(data.error || data.message || `Request failed (${res.status})`);
                err.status = res.status;
                err.busy = Boolean(data.busy || res.status === 409);
                throw err;
            }
            return data;
        }

        async apiGet(url) {
            const res = await fetch(url, {
                method: 'GET',
                headers: this.headers(false),
                credentials: 'same-origin',
            });
            return res.json().catch(() => ({}));
        }

        bindPusherChannel() {
            if (this.pusherBound || !this.authUserId) return;
            const pusher = typeof window.kiuqGetPusherClient === 'function'
                ? window.kiuqGetPusherClient()
                : (window.__kiuqPusher || null);

            if (!pusher) {
                setTimeout(() => this.bindPusherChannel(), 1500);
                return;
            }

            try {
                const channelName = `private-chat.user.${this.authUserId}`;
                const userChannel = pusher.channel(channelName) || pusher.subscribe(channelName);
                this.pusherBound = true;

                userChannel.bind_global((eventName, payload) => {
                    if (!payload || typeof payload !== 'object') return;
                    const ev = String(eventName || '');
                    if (
                        ev.includes('call.') ||
                        ev.includes('CallSignalingEvent') ||
                        ev.includes('CallLifecycleEvent') ||
                        ev.includes('call_signal')
                    ) {
                        this.onSignalingEvent(ev, payload);
                    }
                });
            } catch (e) {
                this.pusherBound = false;
                console.warn('WebRTC Pusher subscribe warning:', e);
            }
        }

        async refreshDeviceCapabilities() {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
                return;
            }
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoInputs = devices.filter(d => d.kind === 'videoinput');
                const audioOutputs = devices.filter(d => d.kind === 'audiooutput');
                this.availableAudioOutputs = audioOutputs;
                const isMobileUa = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
                this.updateState({
                    canSwitchCamera: videoInputs.length > 1 || isMobileUa,
                });
            } catch (_) {}
        }

        async fetchIceConfiguration() {
            try {
                const data = await this.apiGet('/chat/calls/ice-config');
                if (data && Array.isArray(data.iceServers) && data.iceServers.length > 0) {
                    this.iceServers = data.iceServers;
                }
                if (data && data.iceTransportPolicy) {
                    this.iceTransportPolicy = data.iceTransportPolicy;
                }
                if (data && data.ringTimeoutSeconds) {
                    this.ringTimeoutSeconds = Number(data.ringTimeoutSeconds) || 45;
                }
            } catch (_) {}
        }

        getAvatarFallback(name) {
            if (typeof window.dgtInitialsAvatar === 'function') {
                return window.dgtInitialsAvatar(name || 'User');
            }
            return '/favicon.svg';
        }

        resolveContactAvatar(avatarUrl, name) {
            if (avatarUrl && String(avatarUrl).trim() !== '') {
                return avatarUrl;
            }
            return this.getAvatarFallback(name);
        }

        /* =====================================================================
         * OUTGOING CALL INITIATION
         * ===================================================================== */
        async startCall(options = {}) {
            const receiverId = Number(options.receiverId || options.userId || 0);
            const conversationId = Number(options.conversationId || 0);
            const callType = options.callType === 'video' ? 'video' : 'voice';
            const contactName = options.name || options.contactName || 'Team Member';
            const contactAvatar = this.resolveContactAvatar(options.avatar || options.contactAvatar, contactName);

            if (!receiverId) {
                this.notifyToast('Please select a contact to call.', 'info');
                return;
            }
            if (receiverId === this.authUserId) {
                this.notifyToast('You cannot call yourself.', 'warning');
                return;
            }

            // Delegate to Native Flutter WebRTC Engine when running inside KIUQ Mobile App (v3+)
            if (this.isNativeMobileEngineAvailable()) {
                try {
                    const nativeRes = await window.flutter_inappwebview.callHandler('DgtWebRtcCall', {
                        action: 'startCall',
                        receiverId,
                        conversationId,
                        callType,
                        contactName,
                        contactAvatar,
                        authUserId: this.authUserId,
                        csrfToken: this.csrfToken,
                        socketId: this.socketId,
                    });
                    if (nativeRes && typeof nativeRes === 'object' && nativeRes.ok === true) {
                        return;
                    }
                } catch (nativeErr) {
                    console.warn('Native WebRTC startCall fallback to browser:', nativeErr);
                }
            }

            this.ensureUiReady();

            if (this.isInitiating) {
                this.updateState({ minimized: false });
                return;
            }

            if (this.state.active) {
                if (['calling', 'ringing', 'connecting', 'connected', 'reconnecting'].includes(this.state.status)) {
                    this.updateState({ minimized: false });
                    if (callType === 'video' && !this.state.isCameraOn && this.state.status === 'connected') {
                        await this.toggleCamera();
                    }
                    return;
                }
                this.cleanupMediaAndState();
            }

            this.isInitiating = true;
            this.unlockAudioPlayback();
            this.processedSignalKeys.clear();
            this.pendingCandidates = [];
            this.lastSignalId = 0;
            this.iceRestartAttempted = false;

            const isVideo = callType === 'video';
            this.updateState({
                active: true,
                minimized: false,
                isCaller: true,
                status: 'calling',
                callId: 0,
                conversationId,
                otherUserId: receiverId,
                contactName,
                contactAvatar,
                isVideo,
                isMuted: false,
                isCameraOn: isVideo,
                remoteVideoActive: false,
                remoteMuted: false,
                permissionErrorMessage: '',
                duration: 0,
            });

            // 1. Acquire local microphone (and camera if video) on user gesture before creating peer connection
            const mediaOk = await this.acquireLocalMedia(isVideo);
            if (!mediaOk) {
                this.isInitiating = false;
                return;
            }

            this.startAudioFeedback('ringback');

            try {
                const data = await this.apiPost('/chat/calls/initiate', {
                    receiver_id: receiverId,
                    conversation_id: conversationId || undefined,
                    call_type: callType,
                });

                const call = data.call;
                if (!this.state.active) {
                    // Caller cancelled while HTTP request was in flight
                    if (call?.id) {
                        this.apiPost(`/chat/calls/${call.id}/cancel`, { reason: 'cancelled' }).catch(() => {});
                    }
                    return;
                }

                if (Array.isArray(call.ice_servers) && call.ice_servers.length > 0) {
                    this.iceServers = call.ice_servers;
                } else {
                    await this.fetchIceConfiguration();
                }

                const peerName = call.other_user?.name || call.receiver?.name || contactName;
                const peerAvatar = this.resolveContactAvatar(
                    call.other_user?.avatar_url || call.receiver?.avatar_url || contactAvatar,
                    peerName
                );

                this.updateState({
                    callId: call.id,
                    conversationId: call.conversation_id || conversationId,
                    contactName: peerName,
                    contactAvatar: peerAvatar,
                    call,
                });

                await this.createPeerConnection(call.id);
                await this.createAndSendOffer(call.id, false);
                this.startSignalPolling(call.id);
                this.armRingTimeout(call.id);
            } catch (err) {
                this.stopAudioFeedback();
                this.cleanupMediaAndState();
                this.notifyToast(err.message || 'Unable to place call.', err.busy ? 'warning' : 'error');
            } finally {
                this.isInitiating = false;
            }
        }

        /* =====================================================================
         * INCOMING CALL & SIGNALING DISPATCHER
         * ===================================================================== */
        onSignalingEvent(eventName, data) {
            if (this.isNativeMobileEngineAvailable()) {
                try {
                    window.flutter_inappwebview.callHandler('DgtWebRtcCall', {
                        action: 'pusherSignal',
                        eventName,
                        data,
                        authUserId: this.authUserId,
                        csrfToken: this.csrfToken,
                        socketId: this.socketId,
                    }).then((res) => {
                        if (!res || typeof res !== 'object' || res.ok !== true) {
                            this._processBrowserSignalingEvent(eventName, data);
                        }
                    }).catch(() => {
                        this._processBrowserSignalingEvent(eventName, data);
                    });
                    return;
                } catch (_) {}
            }
            this._processBrowserSignalingEvent(eventName, data);
        }

        _processBrowserSignalingEvent(eventName, data) {
            this.ensureUiReady();

            const callId = Number(data.call_id || data.call?.id || 0);
            const senderId = Number(data.sender_id || 0);
            const signalId = Number(data.signal_id || 0);
            const rawType = String(data.payload?.canonical_event || data.event || data.type || eventName || '');

            // Never process our own echoed WebRTC signals (except answered_elsewhere for multi-tab sync)
            if (senderId && senderId === this.authUserId && !rawType.includes('answered_elsewhere')) {
                return;
            }

            // Deduplicate dual-broadcast (.call.signal + .call.<lifecycle>)
            const dedupeKey = signalId
                ? `sig:${callId}:${signalId}`
                : `evt:${callId}:${rawType}:${data.status || ''}:${data.timestamp || ''}`;
            if (this.processedSignalKeys.has(dedupeKey)) {
                return;
            }
            this.processedSignalKeys.add(dedupeKey);
            if (signalId > this.lastSignalId) {
                this.lastSignalId = signalId;
            }

            if (rawType.includes('invited') || rawType === 'incoming_call') {
                this.handleIncomingInvite(data);
                return;
            }

            if (rawType.includes('answered_elsewhere')) {
                if (this.state.active && this.state.callId === callId && !this.state.isCaller && this.state.status === 'ringing') {
                    this.stopAudioFeedback();
                    this.cleanupMediaAndState();
                }
                return;
            }

            if (!this.state.active || (this.state.callId && callId && this.state.callId !== callId)) {
                return;
            }

            if (rawType.includes('ringing')) {
                if (this.state.isCaller && this.state.status === 'calling') {
                    this.updateState({ status: 'ringing' });
                }
            } else if (rawType.includes('accepted')) {
                this.handleRemoteAccepted(data);
            } else if (rawType.includes('rejected') || rawType === 'call_declined' || rawType.includes('busy')) {
                this.stopAudioFeedback();
                this.notifyToast(
                    rawType.includes('busy') ? `${this.state.contactName} is busy on another call.` : `${this.state.contactName} declined the call.`,
                    'info'
                );
                this.dispatchCallEndedDomEvent(callId, 'declined');
                this.cleanupMediaAndState();
            } else if (rawType.includes('cancelled') || rawType.includes('timeout') || rawType.includes('ended') || rawType === 'end') {
                this.stopAudioFeedback();
                const reasonLabel = rawType.includes('timeout')
                    ? 'Missed call'
                    : (rawType.includes('cancelled') ? 'Call cancelled' : 'Call ended');
                if (this.state.status !== 'idle') {
                    this.notifyToast(reasonLabel, 'info');
                }
                this.dispatchCallEndedDomEvent(callId, rawType);
                this.cleanupMediaAndState();
            } else if (rawType.includes('offer') || rawType === 'renegotiate') {
                if (data.payload?.sdp_in_db || !data.payload?.sdp) {
                    this.pollSignalsOnce(callId, false);
                } else {
                    this.handleRemoteOffer(callId, data.payload || {});
                }
            } else if (rawType.includes('answer')) {
                if (data.payload?.sdp_in_db || !data.payload?.sdp) {
                    this.pollSignalsOnce(callId, false);
                } else {
                    this.handleRemoteAnswer(data.payload || {});
                }
            } else if (rawType.includes('ice_candidate') || rawType === 'ice-candidate') {
                this.handleRemoteIceCandidate(data.payload || {});
            } else if (rawType.includes('video_on')) {
                this.updateState({ remoteVideoActive: true, isVideo: true });
            } else if (rawType.includes('video_off')) {
                this.updateState({ remoteVideoActive: false });
            } else if (rawType.includes('unmute')) {
                this.updateState({ remoteMuted: false });
            } else if (rawType.includes('mute')) {
                this.updateState({ remoteMuted: true });
            }
        }

        handleIncomingInvite(data) {
            const callId = Number(data.call_id || data.call?.id || 0);
            const callerId = Number(data.caller_id || data.caller?.id || 0);
            const receiverId = Number(data.receiver_id || data.receiver?.id || 0);

            if (!callId || receiverId !== this.authUserId || callerId === this.authUserId) {
                return;
            }

            // Busy auto-reject if already in another active call
            if (this.state.active && this.state.callId && this.state.callId !== callId) {
                this.apiPost(`/chat/calls/${callId}/reject`, { reason: 'busy' }).catch(() => {});
                return;
            }

            const callType = (data.call_type || data.payload?.call_type || data.call?.call_type) === 'video' ? 'video' : 'voice';
            const callerName = data.caller?.name || data.call?.caller?.name || 'Team Member';
            const callerAvatar = this.resolveContactAvatar(
                data.caller?.avatar_url || data.call?.caller?.avatar_url,
                callerName
            );

            if (Array.isArray(data.payload?.ice_servers) && data.payload.ice_servers.length > 0) {
                this.iceServers = data.payload.ice_servers;
            }

            this.pendingCandidates = [];
            this.lastSignalId = 0;
            this.iceRestartAttempted = false;

            this.updateState({
                active: true,
                minimized: false,
                isCaller: false,
                status: 'ringing',
                callId,
                conversationId: Number(data.conversation_id || data.call?.conversation_id || 0),
                otherUserId: callerId,
                contactName: callerName,
                contactAvatar: callerAvatar,
                isVideo: callType === 'video',
                isMuted: false,
                isCameraOn: false,
                ringtoneMuted: false,
                remoteVideoActive: false,
                remoteMuted: false,
                permissionErrorMessage: '',
                duration: 0,
                call: data.call || null,
            });

            // Acknowledge ringing to caller
            this.apiPost(`/chat/calls/${callId}/ringing`).catch(() => {});
            this.startAudioFeedback('ringing');
            this.startSignalPolling(callId);
            this.armRingTimeout(callId);
        }

        async acceptIncomingCall(withVideo = null) {
            const callId = this.state.callId;
            if (!callId) return;

            this.unlockAudioPlayback();
            this.stopAudioFeedback();

            const wantVideo = withVideo !== null ? Boolean(withVideo) : this.state.isVideo;
            this.updateState({
                status: 'connecting',
                isCameraOn: wantVideo,
                isVideo: wantVideo || this.state.isVideo,
                permissionErrorMessage: '',
            });

            const mediaOk = await this.acquireLocalMedia(wantVideo);
            if (!mediaOk) {
                return;
            }

            try {
                const acceptRes = await this.apiPost(`/chat/calls/${callId}/accept`);
                if (Array.isArray(acceptRes.call?.ice_servers) && acceptRes.call.ice_servers.length > 0) {
                    this.iceServers = acceptRes.call.ice_servers;
                } else {
                    await this.fetchIceConfiguration();
                }

                await this.createPeerConnection(callId);

                // Pull all buffered signals (especially Caller's SDP Offer & early ICE candidates)
                await this.pollSignalsOnce(callId, true);
            } catch (err) {
                this.notifyToast(err.message || 'Could not accept call.', 'error');
                this.cleanupMediaAndState();
            }
        }

        async rejectIncomingCall() {
            const callId = this.state.callId;
            this.stopAudioFeedback();
            this.cleanupMediaAndState();
            if (callId) {
                try {
                    await this.apiPost(`/chat/calls/${callId}/reject`, { reason: 'declined' });
                    this.dispatchCallEndedDomEvent(callId, 'declined');
                } catch (_) {}
            }
        }

        async endOrCancelCall(reason = 'normal') {
            const callId = this.state.callId;
            const wasConnected = this.state.status === 'connected' || this.state.status === 'reconnecting';
            const isCaller = this.state.isCaller;

            this.stopAudioFeedback();
            this.cleanupMediaAndState();

            if (!callId) return;

            try {
                if (!wasConnected && isCaller) {
                    await this.apiPost(`/chat/calls/${callId}/cancel`, { reason: 'cancelled' });
                    this.dispatchCallEndedDomEvent(callId, 'cancelled');
                } else {
                    await this.apiPost(`/chat/calls/${callId}/end`, { reason });
                    this.dispatchCallEndedDomEvent(callId, 'ended');
                }
            } catch (_) {}
        }

        muteIncomingRingtone() {
            this.updateState({ ringtoneMuted: true });
            this.stopAudioFeedback();
        }

        async retryCallAfterError() {
            if (this.state.isCaller) {
                const opts = {
                    receiverId: this.state.otherUserId,
                    conversationId: this.state.conversationId,
                    callType: this.state.isVideo ? 'video' : 'voice',
                    name: this.state.contactName,
                    avatar: this.state.contactAvatar,
                };
                this.cleanupMediaAndState();
                await this.startCall(opts);
            } else if (this.state.callId) {
                await this.acceptIncomingCall(Boolean(this.state.isVideo));
            }
        }

        async retryAudioOnlyAfterPermissionError() {
            if (this.state.isCaller) {
                const opts = {
                    receiverId: this.state.otherUserId,
                    conversationId: this.state.conversationId,
                    callType: 'voice',
                    name: this.state.contactName,
                    avatar: this.state.contactAvatar,
                };
                this.cleanupMediaAndState();
                await this.startCall(opts);
            } else if (this.state.callId) {
                await this.acceptIncomingCall(false);
            }
        }

        /* =====================================================================
         * WEBRTC PEER CONNECTION & MEDIA STREAMS
         * ===================================================================== */
        async acquireLocalMedia(wantVideo) {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                this.stopAudioFeedback();
                const httpsMsg = 'Microphone/camera access requires HTTPS or a supported browser.';
                this.updateState({
                    status: 'permission_error',
                    permissionErrorMessage: httpsMsg,
                });
                this.notifyToast(httpsMsg, 'error');
                return false;
            }

            const withTimeout = (promise, ms = 12000) => new Promise((resolve, reject) => {
                const t = setTimeout(() => {
                    const timeoutErr = new Error('Microphone/camera prompt timed out. Please allow access and tap Retry.');
                    timeoutErr.name = 'TimeoutError';
                    reject(timeoutErr);
                }, ms);
                promise.then(
                    (val) => { clearTimeout(t); resolve(val); },
                    (err) => { clearTimeout(t); reject(err); }
                );
            });

            const audioConstraints = {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            };

            const getAudioOnlyFallback = async () => {
                try {
                    return await withTimeout(navigator.mediaDevices.getUserMedia({
                        audio: audioConstraints,
                        video: false,
                    }));
                } catch (_) {
                    return await withTimeout(navigator.mediaDevices.getUserMedia({
                        audio: true,
                        video: false,
                    }));
                }
            };

            try {
                if (wantVideo) {
                    try {
                        this.localStream = await withTimeout(navigator.mediaDevices.getUserMedia({
                            audio: audioConstraints,
                            video: {
                                facingMode: this.state.facingMode || 'user',
                                width: { ideal: 1280, max: 1920 },
                                height: { ideal: 720, max: 1080 },
                                frameRate: { ideal: 30, max: 30 },
                            },
                        }));
                    } catch (hdErr) {
                        // Fallback 1: Standard resolution
                        try {
                            this.localStream = await withTimeout(navigator.mediaDevices.getUserMedia({
                                audio: audioConstraints,
                                video: { facingMode: this.state.facingMode || 'user' },
                            }));
                        } catch (camErr) {
                            // Fallback 2: Camera unavailable or camera permission denied -> try microphone-only so call still connects!
                            this.localStream = await getAudioOnlyFallback();
                            this.updateState({ isCameraOn: false });
                            this.notifyToast('Camera unavailable; continuing with voice audio.', 'info');
                        }
                    }
                } else {
                    this.localStream = await getAudioOnlyFallback();
                }

                this.attachLocalVideoElements();
                this.refreshDeviceCapabilities();
                return true;
            } catch (err) {
                const errName = String(err?.name || '');
                // If the device physically lacks a microphone (e.g., desktop workstation without mic or simulator),
                // allow the call to proceed in muted/listen-only mode instead of blocking call setup completely.
                if (errName === 'NotFoundError' || errName === 'DevicesNotFoundError') {
                    this.localStream = null;
                    this.updateState({ isMuted: true, isCameraOn: false });
                    this.notifyToast('No microphone detected — connecting call in listen-only mode.', 'warning');
                    return true;
                }

                this.stopAudioFeedback();
                const isDenied = errName === 'NotAllowedError' || errName === 'PermissionDeniedError' || errName === 'SecurityError';
                const msg = isDenied
                    ? (wantVideo
                        ? 'Camera or microphone permission was denied. Please allow access in your browser/device settings.'
                        : 'Microphone permission was denied. Please allow microphone access in your browser or iOS/Android settings to place calls.')
                    : (err?.message || 'Unable to access microphone/camera.');

                this.updateState({
                    status: 'permission_error',
                    permissionErrorMessage: msg,
                });
                this.notifyToast(msg, 'error');
                return false;
            }
        }

        async createPeerConnection(callId) {
            if (this.pc) {
                try { this.pc.close(); } catch (_) {}
                this.pc = null;
            }

            const pc = new RTCPeerConnection({
                iceServers: this.iceServers?.length ? this.iceServers : DEFAULT_ICE_SERVERS,
                iceTransportPolicy: this.iceTransportPolicy || 'all',
                iceCandidatePoolSize: 4,
            });
            this.pc = pc;
            this.remoteStream = new MediaStream();

            let hasAudioSender = false;
            let hasVideoSender = false;
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    if (track.kind === 'audio') hasAudioSender = true;
                    if (track.kind === 'video') hasVideoSender = true;
                    pc.addTrack(track, this.localStream);
                });
            }

            if (typeof pc.addTransceiver === 'function') {
                if (!hasAudioSender) {
                    try { pc.addTransceiver('audio', { direction: 'recvonly' }); } catch (_) {}
                }
                if (!hasVideoSender && this.state.isVideo) {
                    try { pc.addTransceiver('video', { direction: 'recvonly' }); } catch (_) {}
                }
            }

            pc.onicecandidate = (event) => {
                if (event.candidate && this.state.active && this.state.callId === callId) {
                    this.apiPost(`/chat/calls/${callId}/signal`, {
                        type: 'ice_candidate',
                        payload: { candidate: event.candidate.toJSON ? event.candidate.toJSON() : event.candidate },
                    }).catch(() => {});
                }
            };

            pc.ontrack = (event) => {
                this.handleRemoteTrack(event);
            };

            pc.oniceconnectionstatechange = () => {
                if (this.pc !== pc) return;
                const iceState = pc.iceConnectionState;
                if (iceState === 'checking') {
                    if (this.state.status === 'ringing') {
                        this.updateState({ status: 'connecting' });
                    }
                } else if (iceState === 'connected' || iceState === 'completed') {
                    this.onCallMediaConnected();
                    this.updateState({ networkQuality: 'good' });
                } else if (iceState === 'disconnected') {
                    if (this.state.status === 'connected') {
                        this.updateState({ status: 'reconnecting', networkQuality: 'fair' });
                        clearTimeout(this.reconnectTimer);
                        this.reconnectTimer = setTimeout(() => {
                            if (this.pc === pc && ['disconnected', 'failed'].includes(pc.iceConnectionState)) {
                                this.attemptIceRestart(callId);
                            }
                        }, 3500);
                    }
                } else if (iceState === 'failed') {
                    this.updateState({ networkQuality: 'poor' });
                    this.attemptIceRestart(callId);
                }
            };

            pc.onconnectionstatechange = () => {
                if (this.pc !== pc) return;
                if (pc.connectionState === 'connected') {
                    this.onCallMediaConnected();
                }
            };

            return pc;
        }

        async createAndSendOffer(callId, iceRestart = false) {
            if (!this.pc) return;
            const offer = await this.pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true,
                iceRestart: Boolean(iceRestart),
            });
            await this.pc.setLocalDescription(offer);
            await this.apiPost(`/chat/calls/${callId}/signal`, {
                type: 'offer',
                payload: {
                    sdp: offer.sdp,
                    type: offer.type,
                    video_enabled: this.state.isCameraOn,
                },
            });
        }

        async handleRemoteAccepted() {
            if (!this.state.active) return;
            clearTimeout(this.ringTimeoutTimer);
            this.stopAudioFeedback();
            if (this.state.status !== 'connected') {
                this.updateState({ status: 'connecting' });
            }
            // Re-send offer if remote peer joined after initial offer or missed it
            if (this.state.isCaller && this.pc && this.pc.signalingState === 'stable' && !this.pc.currentRemoteDescription) {
                try {
                    await this.createAndSendOffer(this.state.callId, false);
                } catch (_) {}
            }
        }

        async handleRemoteOffer(callId, payload) {
            const sdp = payload?.sdp || payload?.offer?.sdp;
            const type = payload?.type || payload?.offer?.type || 'offer';
            if (!sdp) return;

            if (payload.video_enabled) {
                this.updateState({ isVideo: true });
            }

            // If we haven't accepted yet (still ringing), keep offer in DB queue — acceptIncomingCall will pull & answer it!
            if (!this.state.isCaller && this.state.status === 'ringing') {
                return;
            }

            if (!this.pc) {
                await this.createPeerConnection(callId);
            }

            try {
                await this.pc.setRemoteDescription(new RTCSessionDescription({ type, sdp }));
                await this.flushPendingCandidates();

                const answer = await this.pc.createAnswer();
                await this.pc.setLocalDescription(answer);
                await this.apiPost(`/chat/calls/${callId}/signal`, {
                    type: 'answer',
                    payload: {
                        sdp: answer.sdp,
                        type: answer.type,
                        video_enabled: this.state.isCameraOn,
                    },
                });
            } catch (err) {
                console.warn('WebRTC handleRemoteOffer error:', err);
            }
        }

        async handleRemoteAnswer(payload) {
            if (!this.pc) return;
            const sdp = payload?.sdp || payload?.answer?.sdp;
            const type = payload?.type || payload?.answer?.type || 'answer';
            if (!sdp) return;

            if (payload.video_enabled) {
                this.updateState({ remoteVideoActive: true, isVideo: true });
            }

            try {
                if (this.pc.signalingState === 'have-local-offer') {
                    await this.pc.setRemoteDescription(new RTCSessionDescription({ type, sdp }));
                    await this.flushPendingCandidates();
                }
            } catch (err) {
                console.warn('WebRTC handleRemoteAnswer error:', err);
            }
        }

        async handleRemoteIceCandidate(payload) {
            const rawCand = payload?.candidate ?? payload;
            if (!rawCand || (typeof rawCand === 'object' && !rawCand.candidate)) {
                return;
            }

            if (!this.pc || !this.pc.remoteDescription || !this.pc.remoteDescription.type) {
                this.pendingCandidates.push(rawCand);
                return;
            }

            try {
                await this.pc.addIceCandidate(new RTCIceCandidate(rawCand));
            } catch (_) {}
        }

        async flushPendingCandidates() {
            if (!this.pc || !this.pc.remoteDescription) return;
            while (this.pendingCandidates.length > 0) {
                const cand = this.pendingCandidates.shift();
                try {
                    await this.pc.addIceCandidate(new RTCIceCandidate(cand));
                } catch (_) {}
            }
        }

        handleRemoteTrack(event) {
            const track = event?.track;
            if (!track) return;

            if (!this.remoteStream) {
                this.remoteStream = new MediaStream();
            }
            if (!this.remoteStream.getTracks().some(t => t.id === track.id)) {
                this.remoteStream.addTrack(track);
            }

            if (track.kind === 'audio') {
                this.attachRemoteAudioElement();
            } else if (track.kind === 'video') {
                this.updateState({ remoteVideoActive: true, isVideo: true });
                track.onmute = () => this.updateState({ remoteVideoActive: false });
                track.onunmute = () => this.updateState({ remoteVideoActive: true, isVideo: true });
                this.attachRemoteVideoElement();
            }
        }

        attachLocalVideoElements() {
            requestAnimationFrame(() => {
                ['dgt-global-local-pip-video', 'dgt-global-local-main-video'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el && this.localStream) {
                        el.srcObject = this.localStream;
                        el.muted = true;
                        el.playsInline = true;
                        el.play().catch(() => {});
                    }
                });
            });
        }

        attachRemoteVideoElement() {
            requestAnimationFrame(() => {
                const videoEl = document.getElementById('dgt-global-remote-video');
                if (videoEl && this.remoteStream) {
                    videoEl.srcObject = this.remoteStream;
                    videoEl.muted = false;
                    videoEl.playsInline = true;
                    videoEl.play().catch(() => {
                        videoEl.muted = true;
                        videoEl.play().catch(() => {});
                    });
                }
            });
        }

        onCallMediaConnected() {
            clearTimeout(this.ringTimeoutTimer);
            clearTimeout(this.reconnectTimer);
            this.stopAudioFeedback();

            if (this.state.status !== 'connected') {
                this.updateState({ status: 'connected' });
                if (!this.durationTimer) {
                    this.durationTimer = setInterval(() => {
                        this.updateState({ duration: this.state.duration + 1 });
                    }, 1000);
                }
            }
        }

        async attemptIceRestart(callId) {
            if (!this.pc || !this.state.active || this.state.callId !== callId) return;
            if (!this.iceRestartAttempted && this.state.isCaller) {
                this.iceRestartAttempted = true;
                this.updateState({ status: 'reconnecting' });
                try {
                    await this.fetchIceConfiguration();
                    await this.createAndSendOffer(callId, true);
                    return;
                } catch (_) {}
            }
            this.notifyToast('Call connection lost due to network failure.', 'error');
            await this.endOrCancelCall('failed');
        }

        /* =====================================================================
         * IN-CALL MEDIA CONTROLS (MUTE, CAMERA, FLIP CAMERA, SPEAKER, MINIMIZE)
         * ===================================================================== */
        toggleMute() {
            const nextMuted = !this.state.isMuted;
            if (this.localStream) {
                this.localStream.getAudioTracks().forEach(t => {
                    t.enabled = !nextMuted;
                });
            }
            this.updateState({ isMuted: nextMuted });
            if (this.state.callId) {
                this.apiPost(`/chat/calls/${this.state.callId}/signal`, {
                    type: nextMuted ? 'mute' : 'unmute',
                    payload: { muted: nextMuted },
                }).catch(() => {});
            }
        }

        async toggleCamera() {
            if (!this.state.active) return;

            const existingVideoTracks = this.localStream ? this.localStream.getVideoTracks() : [];
            if (existingVideoTracks.length > 0) {
                const nextCam = !this.state.isCameraOn;
                existingVideoTracks.forEach(t => {
                    t.enabled = nextCam;
                });
                this.updateState({ isCameraOn: nextCam, isVideo: nextCam || this.state.remoteVideoActive });
                this.attachLocalVideoElements();
                if (this.state.callId) {
                    this.apiPost(`/chat/calls/${this.state.callId}/signal`, {
                        type: nextCam ? 'video_on' : 'video_off',
                        payload: { video: nextCam },
                    }).catch(() => {});
                }
                return;
            }

            // Upgrade an active voice-only call to video!
            try {
                const camStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: this.state.facingMode || 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                    audio: false,
                });
                const newVideoTrack = camStream.getVideoTracks()[0];
                if (!newVideoTrack) return;

                if (!this.localStream) {
                    this.localStream = new MediaStream();
                }
                this.localStream.addTrack(newVideoTrack);

                if (this.pc) {
                    const videoSender = this.pc.getSenders().find(s => s.track && s.track.kind === 'video');
                    if (videoSender) {
                        await videoSender.replaceTrack(newVideoTrack);
                    } else {
                        this.pc.addTrack(newVideoTrack, this.localStream);
                        if (this.state.callId && this.state.status === 'connected') {
                            await this.createAndSendOffer(this.state.callId, false);
                        }
                    }
                }

                this.updateState({ isCameraOn: true, isVideo: true });
                this.attachLocalVideoElements();
                if (this.state.callId) {
                    this.apiPost(`/chat/calls/${this.state.callId}/signal`, {
                        type: 'video_on',
                        payload: { video: true },
                    }).catch(() => {});
                }
            } catch (err) {
                this.notifyToast('Unable to enable camera: ' + (err?.message || 'Permission denied'), 'error');
            }
        }

        async switchCamera() {
            if (!this.state.isCameraOn || !navigator.mediaDevices?.getUserMedia) return;
            const nextFacing = this.state.facingMode === 'user' ? 'environment' : 'user';

            try {
                const newStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: nextFacing },
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                    audio: false,
                });
                const newTrack = newStream.getVideoTracks()[0];
                if (!newTrack) return;

                if (this.localStream) {
                    this.localStream.getVideoTracks().forEach(old => {
                        old.stop();
                        this.localStream.removeTrack(old);
                    });
                    this.localStream.addTrack(newTrack);
                }

                if (this.pc) {
                    const sender = this.pc.getSenders().find(s => s.track && s.track.kind === 'video');
                    if (sender) {
                        await sender.replaceTrack(newTrack);
                    }
                }

                this.updateState({ facingMode: nextFacing });
                this.attachLocalVideoElements();
            } catch (err) {
                this.notifyToast('Could not switch camera on this device.', 'info');
            }
        }

        async toggleSpeaker() {
            const audioEl = this.ensurePersistentAudioElement();
            const nextSpeaker = !this.state.speakerOn;
            this.updateState({ speakerOn: nextSpeaker });

            if (audioEl && typeof audioEl.setSinkId === 'function' && this.availableAudioOutputs.length > 1) {
                this.currentAudioOutputIndex = (this.currentAudioOutputIndex + 1) % this.availableAudioOutputs.length;
                const sink = this.availableAudioOutputs[this.currentAudioOutputIndex];
                try {
                    await audioEl.setSinkId(sink.deviceId);
                    this.notifyToast(`Audio output: ${sink.label || 'Speaker'}`, 'info');
                    return;
                } catch (_) {}
            }

            if (audioEl) {
                audioEl.volume = nextSpeaker ? 1.0 : 0.45;
            }
        }

        toggleMinimize() {
            this.updateState({ minimized: !this.state.minimized });
            if (!this.state.minimized) {
                this.attachLocalVideoElements();
                this.attachRemoteVideoElement();
            }
        }

        unblockAudio() {
            const audioEl = this.ensurePersistentAudioElement();
            if (audioEl) {
                audioEl.muted = false;
                audioEl.volume = 1.0;
                audioEl.play().then(() => {
                    this.updateState({ audioBlocked: false });
                }).catch(() => {});
            }
        }

        unlockAudioPlayback() {
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass && !this.audioCtx) {
                    this.audioCtx = new AudioContextClass();
                }
                if (this.audioCtx && this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume().catch(() => {});
                }
                const audioEl = this.ensurePersistentAudioElement();
                if (audioEl) {
                    audioEl.muted = false;
                    audioEl.volume = 1.0;
                }
            } catch (_) {}
        }

        /* =====================================================================
         * HYBRID POLLING FALLBACK & TIMEOUTS
         * ===================================================================== */
        startSignalPolling(callId) {
            clearInterval(this.signalPollTimer);
            this.signalPollTimer = setInterval(() => {
                if (!this.state.active || this.state.callId !== callId) {
                    clearInterval(this.signalPollTimer);
                    return;
                }
                this.pollSignalsOnce(callId, false);
            }, 950);
        }

        async pollSignalsOnce(callId, resetCursor = false) {
            try {
                const afterId = resetCursor ? 0 : this.lastSignalId;
                const data = await this.apiGet(`/chat/calls/${callId}/signals?after_id=${afterId}`);
                if (!data || !data.success) return;

                if (['ended', 'declined', 'missed', 'cancelled', 'busy'].includes(data.call_status)) {
                    this.stopAudioFeedback();
                    this.dispatchCallEndedDomEvent(callId, data.call_status);
                    this.cleanupMediaAndState();
                    return;
                }

                if (data.call_status === 'ringing' && this.state.isCaller && this.state.status === 'calling') {
                    this.updateState({ status: 'ringing' });
                }

                if (Array.isArray(data.signals)) {
                    for (const sig of data.signals) {
                        if (sig.id > this.lastSignalId) {
                            this.lastSignalId = sig.id;
                        }
                        const sigKey = `sig:${callId}:${sig.id}`;
                        if (this.processedSignalKeys.has(sigKey) && !resetCursor) continue;
                        this.processedSignalKeys.add(sigKey);

                        if (sig.type === 'offer' || sig.type === 'renegotiate') {
                            await this.handleRemoteOffer(callId, sig.payload || {});
                        } else if (sig.type === 'answer') {
                            await this.handleRemoteAnswer(sig.payload || {});
                        } else if (sig.type === 'ice_candidate' || sig.type === 'ice-candidate') {
                            await this.handleRemoteIceCandidate(sig.payload || {});
                        } else if (sig.type === 'video_on') {
                            this.updateState({ remoteVideoActive: true, isVideo: true });
                        } else if (sig.type === 'video_off') {
                            this.updateState({ remoteVideoActive: false });
                        }
                    }
                }
            } catch (_) {}
        }

        armRingTimeout(callId) {
            clearTimeout(this.ringTimeoutTimer);
            const ms = Math.max(30, this.ringTimeoutSeconds) * 1000;
            this.ringTimeoutTimer = setTimeout(() => {
                if (this.state.active && this.state.callId === callId && ['calling', 'ringing'].includes(this.state.status)) {
                    this.stopAudioFeedback();
                    this.notifyToast('No answer.', 'info');
                    this.apiPost(`/chat/calls/${callId}/timeout`).catch(() => {});
                    this.dispatchCallEndedDomEvent(callId, 'missed');
                    this.cleanupMediaAndState();
                }
            }, ms);
        }

        async checkExistingActiveCall(isInitialLoad = false) {
            try {
                const data = await this.apiGet('/chat/calls/active');
                if (!data?.success || !data?.active || !data?.call) return;
                const call = data.call;
                const callerId = Number(call.caller_id || call.caller?.id || 0);
                const receiverId = Number(call.receiver_id || call.receiver?.id || 0);
                const isCaller = callerId === this.authUserId;

                if (!isCaller && receiverId === this.authUserId && ['calling', 'ringing'].includes(call.status)) {
                    const invitePayload = {
                        event: 'call.invited',
                        type: 'incoming_call',
                        call_id: call.id,
                        caller_id: callerId,
                        receiver_id: receiverId,
                        conversation_id: call.conversation_id,
                        call_type: call.call_type,
                        caller: call.caller,
                        payload: { ice_servers: call.ice_servers, call_type: call.call_type, canonical_event: 'call.invited' },
                        call,
                    };
                    if (this.isNativeMobileEngineAvailable()) {
                        this.onSignalingEvent('call.invited', invitePayload);
                    } else {
                        this.handleIncomingInvite(invitePayload);
                    }
                } else if (isInitialLoad && call.id && isCaller && ['calling', 'ringing'].includes(call.status)) {
                    // Clean up orphaned outgoing call left over from full browser reload
                    this.apiPost(`/chat/calls/${call.id}/cancel`, { reason: 'page_reload' }).catch(() => {});
                }
            } catch (_) {}
        }

        /* =====================================================================
         * SYNTHESIZED RINGBACK & INCOMING RINGTONE (INSTANT SILENCE ON STOP)
         * ===================================================================== */
        startAudioFeedback(mode) {
            this.stopAudioFeedback();
            if (mode === 'ringing' && (this.state.ringtoneMuted || localStorage.getItem('dgt_notifications_muted') === 'true')) {
                return;
            }

            const playBurst = () => {
                if (!this.state.active) {
                    this.stopAudioFeedback();
                    return;
                }
                if (mode === 'ringing' && (this.state.ringtoneMuted || this.state.status !== 'ringing')) {
                    this.stopAudioFeedback();
                    return;
                }
                if (mode === 'ringback' && !['calling', 'ringing'].includes(this.state.status)) {
                    this.stopAudioFeedback();
                    return;
                }

                try {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContextClass) return;
                    if (!this.audioCtx) this.audioCtx = new AudioContextClass();
                    if (this.audioCtx.state === 'suspended') {
                        this.audioCtx.resume().catch(() => {});
                    }
                    const now = this.audioCtx.currentTime;
                    const notes = mode === 'ringback'
                        ? [{ f: 440, t: 0, d: 0.7 }, { f: 480, t: 0, d: 0.7 }]
                        : [{ f: 587.33, t: 0, d: 0.22 }, { f: 783.99, t: 0.24, d: 0.22 }, { f: 880, t: 0.48, d: 0.34 }];

                    notes.forEach(n => {
                        const osc = this.audioCtx.createOscillator();
                        const gain = this.audioCtx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(n.f, now + n.t);
                        gain.gain.setValueAtTime(0.001, now + n.t);
                        gain.gain.exponentialRampToValueAtTime(mode === 'ringback' ? 0.045 : 0.09, now + n.t + 0.03);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + n.t + n.d);
                        osc.connect(gain);
                        gain.connect(this.audioCtx.destination);
                        this.activeOscillators.add(osc);
                        osc.onended = () => this.activeOscillators.delete(osc);
                        osc.start(now + n.t);
                        osc.stop(now + n.t + n.d + 0.02);
                    });
                } catch (_) {}
            };

            playBurst();
            this.ringtoneInterval = setInterval(playBurst, mode === 'ringback' ? 3600 : 2500);
        }

        stopAudioFeedback() {
            if (this.ringtoneInterval) {
                clearInterval(this.ringtoneInterval);
                this.ringtoneInterval = null;
            }
            this.activeOscillators.forEach(osc => {
                try { osc.stop(); osc.disconnect(); } catch (_) {}
            });
            this.activeOscillators.clear();
        }

        dispatchCallEndedDomEvent(callId, status) {
            window.dispatchEvent(new CustomEvent('dgt:call-ended', {
                detail: { callId, status, conversationId: this.state.conversationId },
            }));
        }

        cleanupMediaAndState() {
            clearInterval(this.signalPollTimer);
            this.signalPollTimer = null;
            clearInterval(this.durationTimer);
            this.durationTimer = null;
            clearTimeout(this.ringTimeoutTimer);
            this.ringTimeoutTimer = null;
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;

            this.stopAudioFeedback();

            if (this.localStream) {
                this.localStream.getTracks().forEach(t => {
                    try { t.stop(); } catch (_) {}
                });
                this.localStream = null;
            }

            if (this.remoteStream) {
                this.remoteStream.getTracks().forEach(t => {
                    try { t.stop(); } catch (_) {}
                });
                this.remoteStream = null;
            }

            if (this.pc) {
                try {
                    this.pc.ontrack = null;
                    this.pc.onicecandidate = null;
                    this.pc.oniceconnectionstatechange = null;
                    this.pc.onconnectionstatechange = null;
                    this.pc.close();
                } catch (_) {}
                this.pc = null;
            }

            ['dgt-persistent-remote-audio', 'dgt-global-remote-audio', 'dgt-global-remote-video', 'dgt-global-local-pip-video', 'dgt-global-local-main-video'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    try { el.pause(); } catch (_) {}
                    el.srcObject = null;
                }
            });

            this.pendingCandidates = [];
            this.state = this.defaultState();
            this.refreshDeviceCapabilities();
            this.syncUi();
        }
    }

    const engine = new WebRtcCallEngine();
    window.DgtWebRtcCallManager = engine;

    // Global entrypoints callable from Chat cards, Conversation headers, Contact cards, and Members directory
    window.dgtStartCall = function (receiverIdOrOptions, callType = 'voice', meta = {}) {
        if (typeof receiverIdOrOptions === 'object' && receiverIdOrOptions !== null) {
            return engine.startCall(receiverIdOrOptions);
        }
        return engine.startCall({
            receiverId: Number(receiverIdOrOptions),
            callType: callType === 'video' ? 'video' : 'voice',
            conversationId: meta.conversationId || 0,
            name: meta.name || meta.contactName || 'Team Member',
            avatar: meta.avatar || meta.contactAvatar || '',
        });
    };

    window.dgtStartVoiceCall = function (receiverId, meta = {}) {
        return window.dgtStartCall(receiverId, 'voice', meta);
    };

    window.dgtStartVideoCall = function (receiverId, meta = {}) {
        return window.dgtStartCall(receiverId, 'video', meta);
    };

    // Alpine.js component factory for resources/views/chat/partials/global-webrtc-call-overlay.blade.php
    window.dgtGlobalWebRtcOverlay = function () {
        return {
            ...engine.defaultState(),
            pipX: 0,
            pipY: 0,
            _draggingPip: false,
            _pipStartX: 0,
            _pipStartY: 0,

            init() {
                engine.registerUi(this);
            },
            applyState(nextState) {
                if (!nextState || typeof nextState !== 'object') return;
                Object.assign(this, nextState);
            },
            formatDuration(sec) {
                const total = Math.max(0, Number(sec) || 0);
                const hrs = Math.floor(total / 3600);
                const mins = Math.floor((total % 3600) / 60);
                const secs = total % 60;
                const pad = n => String(n).padStart(2, '0');
                return hrs > 0 ? `${pad(hrs)}:${pad(mins)}:${pad(secs)}` : `${pad(mins)}:${pad(secs)}`;
            },
            acceptVoice() {
                engine.acceptIncomingCall(false);
            },
            acceptVideo() {
                engine.acceptIncomingCall(true);
            },
            rejectCall() {
                engine.rejectIncomingCall();
            },
            endCall() {
                engine.endOrCancelCall('normal');
            },
            muteRingtone() {
                engine.muteIncomingRingtone();
            },
            retryCall() {
                engine.retryCallAfterError();
            },
            retryAudioOnly() {
                engine.retryAudioOnlyAfterPermissionError();
            },
            toggleMute() {
                engine.toggleMute();
            },
            toggleCamera() {
                engine.toggleCamera();
            },
            switchCamera() {
                engine.switchCamera();
            },
            toggleSpeaker() {
                engine.toggleSpeaker();
            },
            toggleMinimize() {
                engine.toggleMinimize();
            },
            unblockAudio() {
                engine.unblockAudio();
            },
            startPipDrag(e) {
                this._draggingPip = true;
                this._pipStartX = e.clientX - this.pipX;
                this._pipStartY = e.clientY - this.pipY;
            },
            movePipDrag(e) {
                if (!this._draggingPip) return;
                this.pipX = Math.max(-220, Math.min(20, e.clientX - this._pipStartX));
                this.pipY = Math.max(-360, Math.min(20, e.clientY - this._pipStartY));
            },
            endPipDrag() {
                this._draggingPip = false;
            },
        };
    };

    const registerAlpineOverlayComponent = () => {
        if (window.Alpine && typeof window.Alpine.data === 'function') {
            window.Alpine.data('dgtGlobalWebRtcOverlay', window.dgtGlobalWebRtcOverlay);
        }
    };

    registerAlpineOverlayComponent();
    document.addEventListener('alpine:init', registerAlpineOverlayComponent);
    document.addEventListener('turbo:load', () => {
        engine.bindPusherChannel();
        engine.ensureUiReady();
    });
})();
