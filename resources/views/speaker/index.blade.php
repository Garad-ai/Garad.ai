<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garad AI Speaker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', Arial, sans-serif; }
        html, body { width: 100%; height: 100%; overflow: hidden; background: #07111f; color: #fff; }
        
        #speaker-app { position: relative; width: 100vw; height: 100vh; overflow: hidden; }

        .stage-background {
            position: absolute; inset: 0;
            background-image: url('https://images.unsplash.com/photo-1519750157634-b6d493a0d5e6?q=80&w=2070&auto=format&fit=crop');
            background-size: cover; background-position: center; z-index: 0;
        }
        .stage-background::before { content: ""; position: absolute; inset: 0; background: linear-gradient(rgba(2, 9, 20, .45), rgba(2, 9, 20, .72)); }
        .stage-background::after { content: ""; position: absolute; inset: 0; background: radial-gradient(circle at center, rgba(16, 163, 127, .20), transparent 65%); }

        .topbar {
            position: absolute; top: 0; left: 0; width: 100%; height: 90px;
            padding: 0 40px; display: flex; justify-content: space-between; align-items: center; z-index: 100;
        }
        .logo-area { display: flex; align-items: center; gap: 10px; }
        .logo { height: 56px; }
        .logo-text { font-size: 24px; font-weight: bold; color: #fff; letter-spacing: 2px; }
        .logo-text span { color: #10a37f; }
        
        .top-actions { display: flex; gap: 14px; }
        .top-actions button {
            height: 48px; padding: 0 22px; border: none; border-radius: 12px;
            background: rgba(10, 18, 34, .65); border: 1px solid rgba(255, 255, 255, .08);
            color: #fff; font-size: 15px; cursor: pointer; transition: .25s; display: flex; align-items: center; gap: 8px;
        }
        .top-actions button:hover { background: #10a37f; }

        .sidebar {
            position: fixed; top: 120px; left: 35px; width: 310px; padding: 28px;
            border-radius: 22px; background: rgba(8, 15, 30, .75); backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, .08); z-index: 500; transition: transform .35s ease;
        }
        .sidebar.closed { transform: translateX(-360px); }
        .sidebar h2 { font-size: 24px; margin-bottom: 10px; }
        .sidebar p { opacity: 0.7; margin-bottom: 20px; line-height: 1.5; }
        
        .sidebar-toggle {
            position: fixed; top: 140px; left: 315px; width: 44px; height: 44px;
            display: flex; align-items: center; justify-content: center; background: #0d1830;
            border: 1px solid rgba(255, 255, 255, .12); border-radius: 10px; cursor: pointer;
            z-index: 9999; transition: left .35s ease; color: #fff;
        }
        .sidebar-toggle:hover { background: #10a37f; }

        .status { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; }
        .online { width: 12px; height: 12px; border-radius: 50%; background: #1eff76; box-shadow: 0 0 12px #1eff76; }

        .btn-group-sidebar { display: flex; flex-direction: column; gap: 10px; }
        .listen-btn, .speak-btn, .stop-btn {
            width: 100%; height: 52px; border: none; border-radius: 14px; font-size: 16px;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .listen-btn { background: #10a37f; color: #fff; }
        .speak-btn { background: #111d32; color: #fff; border: 1px solid #2a4060; }
        .stop-btn { background: #5c1212; color: #fff; }
        .listen-btn:hover, .speak-btn:hover, .stop-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 163, 127, .35); }

        .stage {
            position: absolute; inset: 0; display: flex; justify-content: center; align-items: center; overflow: hidden; z-index: 20;
        }
        .stage::before {
            content: ""; position: absolute; bottom: -180px; left: 50%; transform: translateX(-50%);
            width: 900px; height: 900px; border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 163, 127, .28), rgba(16, 163, 127, .10), transparent 72%);
            filter: blur(15px);
        }

        .avatar-container { position: relative; width: 380px; height: 380px; display: flex; justify-content: center; align-items: center; cursor: pointer; }

        .ring { position: absolute; border-radius: 50%; border: 2px solid rgba(16, 163, 127, 0.3); box-shadow: 0 0 15px rgba(16, 163, 127, 0.1); transition: transform 0.1s ease-out, box-shadow 0.2s ease, border-color 0.2s ease; pointer-events: none; }
        .ring-1 { width: 100%; height: 100%; border-color: rgba(16, 163, 127, 0.5); }
        .ring-2 { width: 80%; height: 80%; border-color: rgba(16, 163, 127, 0.4); }
        .ring-3 { width: 60%; height: 60%; border-color: rgba(16, 163, 127, 0.3); }
        .ring-4 { width: 40%; height: 40%; border-color: rgba(16, 163, 127, 0.2); }

        .face-core { position: absolute; width: 60%; height: 60%; background: radial-gradient(circle at center, #112e27, #081512); border-radius: 50%; display: flex; justify-content: center; align-items: center; box-shadow: inset 0 0 40px rgba(16, 163, 127, 0.1), 0 0 40px rgba(16, 163, 127, 0.1); overflow: hidden; z-index: 10; }
        .face-core::before { content: ""; position: absolute; width: 100%; height: 100%; background: radial-gradient(circle at center, rgba(16, 163, 127, 0.2) 0%, transparent 70%); pointer-events: none; }

        .eyes { position: absolute; top: 30%; width: 50%; display: flex; justify-content: space-between; }
        .eye { width: 16px; height: 16px; background: radial-gradient(circle, #ffffff, #a0ffdf); border-radius: 50%; box-shadow: 0 0 15px rgba(255, 255, 255, 0.8); transition: all 0.1s ease; }
        .eye.speaking { transform: scaleY(0.3); }

        .mouth-wrapper { position: absolute; bottom: 28%; width: 30%; height: 16px; display: flex; justify-content: center; align-items: center; overflow: hidden; }
        .mouth { width: 100%; height: 6px; background: #a0ffdf; border-radius: 10px; box-shadow: 0 0 15px rgba(160, 255, 223, 0.5); transition: height 0.05s linear, border-radius 0.1s ease; }
        .mouth.speaking { height: 16px; width: 80%; border-radius: 0 0 20px 20px; background: #2ab58d; }

        .subtitle-box { position: absolute; left: 50%; bottom: 35px; transform: translateX(-50%); width: 720px; min-height: 95px; display: flex; align-items: center; gap: 20px; padding: 22px 28px; border-radius: 22px; background: rgba(8, 15, 30, .88); backdrop-filter: blur(18px); border: 1px solid rgba(255, 255, 255, .08); z-index: 100; }
        .voice{ display: flex; gap: 5px; align-items: flex-end; height: 45px; }
        .voice span{ width: 5px; background: #10a37f; border-radius: 20px; animation: voice 1s infinite ease-in-out; }
        .voice span:nth-child(1){ height: 18px; }
        .voice span:nth-child(2){ height: 35px; animation-delay: .1s; }
        .voice span:nth-child(3){ height: 24px; animation-delay: .2s; }
        .voice span:nth-child(4){ height: 42px; animation-delay: .3s; }
        .voice span:nth-child(5){ height: 26px; animation-delay: .4s; }
        .voice span:nth-child(6){ height: 38px; animation-delay: .5s; }
        .voice span:nth-child(7){ height: 20px; animation-delay: .6s; }
        .voice span:nth-child(8){ height: 32px; animation-delay: .7s; }
        @keyframes voice{ 0%{ transform: scaleY(.35); } 50%{ transform: scaleY(1); } 100%{ transform: scaleY(.35); } }
        #subtitle{ flex: 1; font-size: 24px; font-weight: 500; color: #fff; line-height: 1.4; min-height: 35px; }

        .powered{ position: absolute; right: 30px; bottom: 30px; display: flex; align-items: center; gap: 15px; padding: 16px 22px; border-radius: 18px; background: rgba(8, 15, 30, .85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, .08); z-index: 100; box-shadow: 0 10px 35px rgba(0, 0, 0, .35); }
        .powered span{ font-size: 16px; color: #ffffff; }

        @media(max-width: 1100px){ .sidebar{ display: none; } .sidebar-toggle{ display: none; } .topbar{ padding: 20px; } .subtitle-box{ width: 92%; } }
        @media(max-width: 768px){ .logo{ height: 42px; } .top-actions{ display: none; } .subtitle-box{ bottom: 20px; padding: 18px; } #subtitle{ font-size: 18px; } .powered{ display: none; } .avatar-container { width: 250px; height: 250px; } .eye { width: 12px; height: 12px; } }
    </style>
</head>
<body>
<div id="speaker-app">
    <div class="stage-background"></div>
    <header class="topbar">
        <div class="logo-area">
            <img src="{{ asset('speaker/images/logo.png') }}" class="logo" alt="Garad AI"> 
            <div class="logo-text">GARAD <span>AI</span></div>
        </div>
        <div class="top-actions">
            <button id="fullscreenBtn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg> Fullscreen
            </button>
        </div>
    </header>

    <button id="sidebarToggle" class="sidebar-toggle" type="button">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
    </button>

    <aside class="sidebar" id="sidebar">
        <h2>Garad Speaker</h2>
        <p>AI Powered Live Presenter for Conferences, Events and Demonstrations.</p>
        <div class="status"><span class="online"></span><span>Online (GPT-5.6)</span></div>
        <div class="btn-group-sidebar">
            <button class="listen-btn" id="listenBtn"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg> Listen</button>
            <button class="speak-btn" id="speakBtn"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg> Speak Test</button>
            <button class="stop-btn" id="stopBtn"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="4" y="4" width="16" height="16" rx="2"></rect></svg> Stop</button>
        </div>
    </aside>

    <main class="stage">
        <div class="avatar-container" id="avatarClicker">
            <div class="ring ring-1"></div><div class="ring ring-2"></div><div class="ring ring-3"></div><div class="ring ring-4"></div>
            <div class="face-core">
                <div class="eyes"><div class="eye" id="leftEye"></div><div class="eye" id="rightEye"></div></div>
                <div class="mouth-wrapper"><div class="mouth" id="mouth"></div></div>
            </div>
        </div>
    </main>

    <div class="subtitle-box">
        <div class="voice"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
        <div id="subtitle">Garad AI Speaker Ready</div>
    </div>

    <div class="powered"><span>Powered by Arkaan AI Center</span></div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const sidebar = document.getElementById("sidebar"); 
        const toggle = document.getElementById("sidebarToggle");
        const subtitle = document.getElementById("subtitle"); 
        const voiceBars = document.querySelectorAll(".voice span");
        const speakBtn = document.getElementById("speakBtn"); 
        const stopBtn = document.getElementById("stopBtn");
        const listenBtn = document.getElementById("listenBtn"); 
        const fullscreenBtn = document.getElementById("fullscreenBtn");
        const leftEye = document.getElementById("leftEye"); 
        const rightEye = document.getElementById("rightEye");
        const mouth = document.getElementById("mouth"); 
        const rings = document.querySelectorAll('.ring');
        const avatarClicker = document.getElementById("avatarClicker");

        // BroadcastChannel to receive commands from OpenAI Admin Panel
        const speakerChannel = new BroadcastChannel('garad_speaker_channel');

        toggle.addEventListener("click", () => {
            sidebar.classList.toggle("closed");
            toggle.style.left = sidebar.classList.contains("closed") ? "15px" : "315px";
        });

        function startBars() { voiceBars.forEach(bar => bar.style.animationPlayState = "running"); }
        function stopBars() { voiceBars.forEach(bar => bar.style.animationPlayState = "paused"); }
        stopBars();

        let isSpeaking = false; 
        let animFrame;
        let subtitleInterval = null;

        function animateAvatar(intensity) {
            const scale1 = 1 + (intensity * 0.05); const scale2 = 1 + (intensity * 0.10);
            const scale3 = 1 + (intensity * 0.15); const scale4 = 1 + (intensity * 0.20);
            rings[0].style.transform = `scale(${scale1})`; rings[1].style.transform = `scale(${scale2})`;
            rings[2].style.transform = `scale(${scale3})`; rings[3].style.transform = `scale(${scale4})`;
            const glowIntensity = 0.2 + (intensity * 0.8);
            rings.forEach(ring => { ring.style.boxShadow = `0 0 ${15 + (intensity * 25)}px rgba(16, 163, 127, ${glowIntensity * 0.2})`; ring.style.borderColor = `rgba(16, 163, 127, ${0.2 + (intensity * 0.3)})`; });
            if (intensity > 0.05) {
                const mouthHeight = 6 + (intensity * 15); mouth.style.height = `${mouthHeight}px`; mouth.classList.add('speaking');
                if(Math.random() > 0.8) { leftEye.classList.add('speaking'); rightEye.classList.add('speaking'); setTimeout(() => { leftEye.classList.remove('speaking'); rightEye.classList.remove('speaking'); }, 100); }
            } else { mouth.style.height = `6px`; mouth.classList.remove('speaking'); leftEye.classList.remove('speaking'); rightEye.classList.remove('speaking'); }
        }

        // Subtitle Chunking Function (Shows text piece by piece progressively)
        function animateSubtitles(text) {
            if (!subtitle) return;
            if (subtitleInterval) clearInterval(subtitleInterval);

            const words = text.split(" ");
            let index = 0;
            const chunkSize = 5; // Wax yar yar (5 eray mar walba)
            subtitle.textContent = "";

            subtitleInterval = setInterval(() => {
                if (index < words.length) {
                    const chunk = words.slice(index, index + chunkSize).join(" ");
                    subtitle.textContent = chunk;
                    index += chunkSize;
                } else {
                    clearInterval(subtitleInterval);
                }
            }, 600); // Waqtiga ay erayadu is beddelayaan
        }

        // SPEECH FUNCTION WITH DYNAMIC SUBTITLE CHUNKING
        function speakText(text) {
            if (!window.speechSynthesis) return;
            window.speechSynthesis.cancel(); 
            if (subtitleInterval) clearInterval(subtitleInterval);

            isSpeaking = true; 
            startBars(); 
            animateSubtitles(text);

            const utterance = new SpeechSynthesisUtterance(text); 
            utterance.lang = "en-US"; 
            utterance.rate = 0.95;
            utterance.pitch = 0.95;

            // Load selected voice index from Admin configuration in localStorage
            const voices = window.speechSynthesis.getVoices();
            const savedVoiceIdx = localStorage.getItem('garad_selected_voice');
            if (savedVoiceIdx !== null && voices[savedVoiceIdx]) {
                utterance.voice = voices[savedVoiceIdx];
            }

            let progress = 0;
            function simulateWave() {
                if (!isSpeaking) { animateAvatar(0); return; }
                progress += 0.3; const wave = Math.abs(Math.sin(progress)); const noise = Math.random() * 0.2;
                const intensity = Math.min(wave + noise, 1.0); animateAvatar(intensity);
                animFrame = requestAnimationFrame(simulateWave);
            }

            utterance.onstart = () => simulateWave();
            utterance.onend = () => { 
                isSpeaking = false; 
                cancelAnimationFrame(animFrame); 
                animateAvatar(0); 
                stopBars(); 
                if (subtitleInterval) clearInterval(subtitleInterval);
                subtitle.textContent = "Garad AI Speaker Ready"; 
            };
            utterance.onerror = () => { 
                isSpeaking = false; 
                cancelAnimationFrame(animFrame); 
                animateAvatar(0); 
                stopBars(); 
                if (subtitleInterval) clearInterval(subtitleInterval);
                subtitle.textContent = "Error"; 
            };

            window.speechSynthesis.speak(utterance);
        }

        function stopSpeaking() { 
            window.speechSynthesis.cancel(); 
            isSpeaking = false; 
            cancelAnimationFrame(animFrame); 
            if (subtitleInterval) clearInterval(subtitleInterval);
            animateAvatar(0); 
            stopBars(); 
            subtitle.textContent = "Speaker Stopped"; 
        }

        // LISTEN FOR MESSAGES FROM ADMIN PANEL VIA BroadcastChannel
        speakerChannel.onmessage = (event) => {
            if (event.data) {
                if (event.data.type === 'speak' && event.data.text) {
                    speakText(event.data.text);
                } else if (event.data.type === 'stop') {
                    stopSpeaking();
                }
            }
        };

        // REAL OPENAI API CALL USING GPT-5.6 (if mic is clicked)
        async function askOpenAI(prompt) {
            const apiKey = localStorage.getItem('garad_openai_key');
            if (!apiKey) {
                subtitle.textContent = "Please configure OpenAI API Key in Admin Panel first.";
                return "API Key is missing. Please set it in the Admin dashboard.";
            }
            subtitle.textContent = "Thinking with GPT-5.6...";
            const url = 'https://api.openai.com/v1/chat/completions';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${apiKey}`
                    },
                    body: JSON.stringify({
                        model: 'gpt-5.6',
                        messages: [
                            { role: "system", content: "Provide direct text to be read out loud. Keep it concise." },
                            { role: "user", content: prompt }
                        ]
                    })
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.error ? data.error.message : "OpenAI API Error");
                }
                return data.choices[0].message.content.trim();
            } catch (e) {
                return "Error connecting to OpenAI: " + e.message;
            }
        }

        let recognition = null;
        if ("webkitSpeechRecognition" in window || "SpeechRecognition" in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition(); 
            recognition.lang = "en-US"; 
            recognition.interimResults = false;
            recognition.onstart = () => { subtitle.textContent = "Listening..."; animateAvatar(0.05); };
            recognition.onresult = async (event) => { 
                const userInput = event.results[0][0].transcript; 
                subtitle.textContent = `You said: "${userInput}"`; 
                const aiResponse = await askOpenAI(userInput); 
                speakText(aiResponse); 
            };
            recognition.onerror = () => { subtitle.textContent = "Microphone error/denied"; animateAvatar(0); };
        }

        avatarClicker.addEventListener('click', () => { 
            if (isSpeaking) stopSpeaking(); 
            if (recognition) recognition.start(); 
            else subtitle.textContent = "Recognition not supported"; 
        });

        speakBtn.addEventListener('click', () => { 
            speakText("Hello everyone. I am Garad AI Speaker, successfully connected with the OpenAI administration panel using model GPT-5.6."); 
        });

        stopBtn.addEventListener('click', stopSpeaking);
        listenBtn.addEventListener('click', () => { 
            if (recognition) recognition.start(); 
            else subtitle.textContent = "Mic not supported"; 
        });

        fullscreenBtn.addEventListener('click', () => { 
            if (!document.fullscreenElement) document.documentElement.requestFullscreen(); 
            else document.exitFullscreen(); 
        });

        setTimeout(() => subtitle.textContent = "Garad AI Speaker Ready", 1500);
    });
</script>
</body>
</html>