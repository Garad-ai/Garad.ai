<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garad AI Admin Dashboard - OpenAI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        body { background: #040d1a; color: #fff; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        
        .admin-container {
            width: 100%; max-width: 680px; background: rgba(8, 15, 30, 0.85);
            backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px; padding: 35px; box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
        }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding-bottom: 15px; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo { height: 45px; }
        .title { font-size: 22px; font-weight: bold; letter-spacing: 1px; color: #fff; }
        .title span { color: #10a37f; }

        .status-badge { display: flex; align-items: center; gap: 8px; font-size: 13px; background: rgba(16, 163, 127, 0.1); padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(16, 163, 127, 0.3); color: #10a37f; }
        .status-dot { width: 8px; height: 8px; background: #10a37f; border-radius: 50%; box-shadow: 0 0 10px #10a37f; }
        .status-badge.disconnected { background: rgba(255, 22, 22, 0.1); border-color: rgba(255, 22, 22, 0.3); color: #ff1e1e; }
        .status-badge.disconnected .status-dot { background: #ff1e1e; box-shadow: 0 0 10px #ff1e1e; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: #a0aec0; }
        
        input[type="password"], select, textarea {
            width: 100%; background: rgba(13, 24, 48, 0.8); border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px; padding: 14px; color: #fff; font-size: 15px; outline: none; transition: 0.3s;
        }
        input[type="password"]:focus, select:focus, textarea:focus { border-color: #10a37f; box-shadow: 0 0 12px rgba(16, 163, 127, 0.3); }

        .api-row { display: flex; gap: 10px; }
        .api-row input { flex: 1; }
        .btn-verify { background: #112240; border: 1px solid #10a37f; color: #10a37f; padding: 0 18px; border-radius: 12px; cursor: pointer; font-weight: 600; transition: 0.2s; white-space: nowrap; }
        .btn-verify:hover { background: #10a37f; color: #fff; }

        .voice-row { display: flex; gap: 10px; }
        .voice-row select { flex: 1; }
        .btn-test { background: #112240; border: 1px solid #10a37f; color: #10a37f; padding: 0 20px; border-radius: 12px; cursor: pointer; font-weight: 600; transition: 0.2s; }
        .btn-test:hover { background: #10a37f; color: #fff; }

        .quick-prompts { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; }
        .quick-chip { background: rgba(16, 163, 127, 0.12); border: 1px solid rgba(16, 163, 127, 0.3); color: #4fd1c5; padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; transition: 0.2s; }
        .quick-chip:hover { background: #10a37f; color: #fff; }

        .btn-group { display: flex; gap: 12px; margin-top: 25px; }
        .btn-primary { flex: 2; background: #10a37f; color: #fff; border: none; padding: 15px; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-primary:hover { background: #0d8c6d; box-shadow: 0 8px 20px rgba(16, 163, 127, 0.4); }
        .btn-danger { flex: 1; background: #5c1212; color: #fff; border: none; padding: 15px; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-danger:hover { background: #7a1818; }

        .log-box { margin-top: 25px; background: rgba(4, 9, 18, 0.9); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 12px; padding: 15px; height: 140px; overflow-y: auto; font-family: monospace; font-size: 13px; color: #cbd5e0; }
        .log-item { margin-bottom: 6px; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 4px; }
        .log-user { color: #63b3ed; }
        .log-ai { color: #68d391; }
        .log-error { color: #fc8181; }
        
        .clear-log { margin-top: 10px; background: transparent; border: 1px solid rgba(255,255,255,0.1); color: #a0aec0; padding: 6px 12px; border-radius: 8px; font-size: 12px; cursor: pointer; }
        .clear-log:hover { background: rgba(255,255,255,0.05); color: #fff; }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="header">
        <div class="logo-area">
            <img src="{{ asset('speaker/images/logo.png') }}" class="logo" alt="Garad Logo">
            <div class="title">GARAD <span>AI ADMIN</span></div>
        </div>
        <div class="status-badge" id="statusBadge"><span class="status-dot"></span><span id="statusText">System Ready</span></div>
    </div>

    <div class="form-group">
        <label>OpenAI API Key</label>
        <div class="api-row">
            <input type="password" id="apiKeyInput" placeholder="sk-proj-...">
            <button class="btn-verify" id="verifyApiKeyBtn">Test API Key</button>
        </div>
    </div>

    <div class="form-group">
        <label>Select OpenAI Model</label>
        <select id="modelSelect">
            <option value="gpt-5.6">GPT-5.6 (Latest & Recommended)</option>
            <option value="gpt-5.5">GPT-5.5</option>
            <option value="gpt-5.4">GPT-5.4</option>
            <option value="gpt-4o">GPT-4o</option>
        </select>
    </div>

    <div class="form-group">
        <label>Select OpenAI Voice (TTS)</label>
        <div class="voice-row">
            <select id="voiceSelect">
                <option value="alloy">Alloy (Neutral & Balanced)</option>
                <option value="echo">Echo (Warm & Male)</option>
                <option value="fable">Fable (Expressive & British)</option>
                <option value="onyx">Onyx (Deep & Authoritative)</option>
                <option value="nova">Nova (Energetic & Female)</option>
                <option value="shimmer">Shimmer (Clear & Soft Female)</option>
            </select>
            <button class="btn-test" id="testVoiceBtn">Test Voice</button>
        </div>
    </div>

    <div class="form-group">
        <label>Quick Prompt Templates</label>
        <div class="quick-prompts">
            <span class="quick-chip" onclick="setPrompt('Welcome everyone to the official launch of Garad AI.')">Welcome Speech</span>
            <span class="quick-chip" onclick="setPrompt('Explain how OpenAI empowers businesses and automation.')">AI Capabilities</span>
            <span class="quick-chip" onclick="setPrompt('Thank you for attending today grand opening. Enjoy the rest of the evening.')">Closing Note</span>
        </div>
        <textarea id="promptInput" rows="3" placeholder="Type prompt or message for OpenAI and speaker to read..."></textarea>
    </div>

    <div class="btn-group">
        <button class="btn-primary" id="sendBtn">Send to AI & Speaker</button>
        <button class="btn-danger" id="stopBtn">Stop Speaker</button>
    </div>

    <label style="margin-top: 20px;">Activity Log</label>
    <div class="log-box" id="logBox">
        <div class="log-item">[System]: Garad AI Admin Panel initialized successfully.</div>
    </div>
    <button class="clear-log" id="clearLogBtn">Clear Log</button>
</div>

<script>
    const speakerChannel = new BroadcastChannel('garad_speaker_channel');
    const apiKeyInput = document.getElementById('apiKeyInput');
    const modelSelect = document.getElementById('modelSelect');
    const voiceSelect = document.getElementById('voiceSelect');
    const promptInput = document.getElementById('promptInput');
    const sendBtn = document.getElementById('sendBtn');
    const stopBtn = document.getElementById('stopBtn');
    const testVoiceBtn = document.getElementById('testVoiceBtn');
    const verifyApiKeyBtn = document.getElementById('verifyApiKeyBtn');
    const logBox = document.getElementById('logBox');
    const clearLogBtn = document.getElementById('clearLogBtn');
    const statusBadge = document.getElementById('statusBadge');
    const statusText = document.getElementById('statusText');

    // Load saved settings
    if(localStorage.getItem('garad_openai_key')) {
        apiKeyInput.value = localStorage.getItem('garad_openai_key');
    }
    if(localStorage.getItem('garad_openai_model')) {
        modelSelect.value = localStorage.getItem('garad_openai_model');
    }
    if(localStorage.getItem('garad_openai_voice')) {
        voiceSelect.value = localStorage.getItem('garad_openai_voice');
    }

    apiKeyInput.addEventListener('input', () => {
        localStorage.setItem('garad_openai_key', apiKeyInput.value.trim());
    });

    modelSelect.addEventListener('change', () => {
        localStorage.setItem('garad_openai_model', modelSelect.value);
    });

    voiceSelect.addEventListener('change', () => {
        localStorage.setItem('garad_openai_voice', voiceSelect.value);
    });

    function setPrompt(text) {
        promptInput.value = text;
    }

    function addLog(sender, text, type = 'user') {
        const div = document.createElement('div');
        div.className = 'log-item';
        let colorClass = 'log-user';
        if(type === 'ai') colorClass = 'log-ai';
        if(type === 'error') colorClass = 'log-error';
        div.innerHTML = `<span class="${colorClass}">[${sender}]:</span> ${text}`;
        logBox.appendChild(div);
        logBox.scrollTop = logBox.scrollHeight;
    }

    function updateStatus(isConnected, message) {
        statusText.textContent = message;
        if(isConnected) {
            statusBadge.className = 'status-badge';
        } else {
            statusBadge.className = 'status-badge disconnected';
        }
    }

    // Verify OpenAI API Key Connection
    verifyApiKeyBtn.addEventListener('click', async () => {
        const apiKey = apiKeyInput.value.trim();
        if(!apiKey) {
            updateStatus(false, 'API Key Missing');
            addLog('System', 'Please enter an OpenAI API Key to verify.', 'error');
            return;
        }

        addLog('System', 'Testing OpenAI API connection...', 'user');
        const url = 'https://api.openai.com/v1/models';
        
        try {
            const res = await fetch(url, {
                method: 'GET',
                headers: { 'Authorization': `Bearer ${apiKey}` }
            });
            const data = await res.json();
            
            if(res.ok) {
                updateStatus(true, 'Connected');
                addLog('System', 'OpenAI API Key Verified Successfully. Connected!', 'ai');
            } else {
                updateStatus(false, 'Connection Failed');
                addLog('Error', data.error ? data.error.message : 'Invalid OpenAI API Key', 'error');
            }
        } catch(e) {
            updateStatus(false, 'Network Error');
            addLog('Error', e.message, 'error');
        }
    });

    // Test OpenAI Native Audio Speech (TTS) API
    async function playOpenAITTS(textToSpeak) {
        const apiKey = apiKeyInput.value.trim();
        const selectedVoice = voiceSelect.value;
        
        if(!apiKey) {
            addLog('System', 'API Key required for voice test.', 'error');
            return;
        }

        addLog('System', `Generating voice speech using OpenAI (${selectedVoice})...`, 'user');

        try {
            const res = await fetch('https://api.openai.com/v1/audio/speech', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiKey}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    model: 'tts-1',
                    input: textToSpeak,
                    voice: selectedVoice
                })
            });

            if(!res.ok) {
                const errData = await res.json();
                throw new Error(errData.error ? errData.error.message : 'TTS Audio Generation Failed');
            }

            const audioBlob = await res.blob();
            const audioUrl = URL.createObjectURL(audioBlob);
            const audio = new Audio(audioUrl);
            
            // Store reference globally so stop button can halt it if needed
            window.currentAudio = audio;
            audio.play();
            addLog('System', 'OpenAI TTS Voice test played successfully.', 'ai');

        } catch(e) {
            addLog('Error', e.message, 'error');
        }
    }

    testVoiceBtn.addEventListener('click', () => {
        playOpenAITTS("Garad AI speaker voice test successful.");
    });

    // OpenAI Chat Completion API Call (Strict clean text for speaker output)
    async function askOpenAI(promptText) {
        const apiKey = apiKeyInput.value.trim();
        const currentModel = modelSelect.value;
        
        if(!apiKey) {
            addLog("System", "Please enter a valid OpenAI API Key.", "error");
            return null;
        }
        addLog("System", `Sending prompt to OpenAI (${currentModel})...`, "user");
        
        const url = 'https://api.openai.com/v1/chat/completions';
        
        try {
            const res = await fetch(url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Authorization": `Bearer ${apiKey}`
                },
                body: JSON.stringify({
                    model: currentModel,
                    messages: [
                        { role: "system", content: "You are a professional presenter. Provide only the direct text to be read out loud clearly. Do not add markdown formatting, bullet points, or conversational filler." },
                        { role: "user", content: promptText }
                    ]
                })
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.error ? data.error.message : "OpenAI API Request Failed");
            }

            const aiReply = data.choices[0].message.content.trim();
            addLog("Garad AI", aiReply, "ai");
            updateStatus(true, 'Connected');
            return aiReply;

        } catch(e) {
            addLog("Error", e.message, "error");
            updateStatus(false, 'API Error');
            return null;
        }
    }

    sendBtn.addEventListener('click', async () => {
        const text = promptInput.value.trim();
        if(!text) return;
        
        addLog("Admin", text, "user");
        const aiResponse = await askOpenAI(text);
        if(aiResponse) {
            // Broadcast clean response text and chosen voice to frontend speaker
            speakerChannel.postMessage({ 
                type: 'speak', 
                text: aiResponse, 
                apiKey: apiKeyInput.value.trim(), 
                voice: voiceSelect.value 
            });
            // Also trigger audio locally if tested from admin window
            playOpenAITTS(aiResponse);
        }
    });

    stopBtn.addEventListener('click', () => {
        if(window.currentAudio) {
            window.currentAudio.pause();
            window.currentAudio.currentTime = 0;
        }
        speakerChannel.postMessage({ type: 'stop' });
        addLog("System", "Speaker stopped by admin.", "error");
    });

    clearLogBtn.addEventListener('click', () => {
        logBox.innerHTML = '<div class="log-item">[System]: Log cleared.</div>';
    });
</script>
</body>
</html>