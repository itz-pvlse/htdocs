<div id="aiOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden opacity-0 transition-opacity duration-500"></div>

<div id="aiChat" class="hidden fixed z-[10001] flex-col bg-[#0a0a0c]/95 backdrop-blur-3xl border border-white/10 rounded-[2.5rem] shadow-[0_50px_100px_rgba(0,0,0,0.9)] overflow-hidden transition-all duration-500 translate-y-4">
    <div id="aiHeader" class="flex items-center gap-4 p-6 bg-white/[0.02] border-b border-white/5 relative">
        <div class="relative">
            <img src="../allo_profile.png" alt="ALVA" id="aiHeaderAvatar" width="48" height="48" class="w-12 h-12 rounded-2xl border border-white/10 object-cover" />
            <div class="absolute -top-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border-2 border-[#0a0a0c] animate-pulse"></div>
        </div>
        <div class="flex flex-col">
            <span class="text-[8px] font-black text-blue-500 uppercase tracking-[0.4em] mb-1">Neural Assistant</span>
            <span class="text-lg font-black text-white tracking-tight leading-none uppercase">ALVA <span class="text-white/20 font-light italic text-[10px]">v2.0</span></span>
        </div>
        <div class="flex items-center gap-2 ml-auto">
            <button id="aiFullscreen" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white/40 hover:text-white transition-colors">⛶</button>
            <button id="aiClose" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white/40 hover:bg-red-500/20 hover:text-red-400 transition-all">✕</button>
        </div>
    </div>

    <div id="aiMessages" class="flex-1 p-6 overflow-y-auto space-y-6 custom-scrollbar flex flex-col"></div>

    <div id="aiInputContainer" class="p-6 bg-gradient-to-t from-black/40 to-transparent flex flex-col gap-4">
        <div class="relative flex items-center">
            <input id="aiInput" type="text" placeholder="Ask about NTSA, logistics, or specs..." 
                   class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-sm font-medium text-white placeholder-white/20 focus:outline-none focus:border-blue-500/50 transition-all pr-24" />
            <button id="aiSend" class="absolute right-2 top-2 bottom-2 bg-blue-600 hover:bg-blue-500 text-white px-6 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all active:scale-95 shadow-lg shadow-blue-600/20">Send</button>
        </div>
        <p class="text-[8px] text-center text-white/20 uppercase tracking-[0.2em]">AutoLog Intelligence System • Kenya</p>
    </div>
</div>

<style>
/* --- 1. CENTERING LOGIC --- */
#aiChat { 
    /* Center positioning */
    top: 50%;
    left: 50%;
    transform: translate(-50%, -45%); /* Starts slightly lower for entry animation */
    
    width: 450px; 
    max-width: 95vw;
    height: 700px; 
    max-height: 85vh;
    display: none; 
}

/* State when active (triggered by JS) */
#aiChat.active {
    display: flex;
    transform: translate(-50%, -50%);
    opacity: 1;
}

#aiChat.fullscreen { 
    top: 0 !important; 
    left: 0 !important; 
    transform: none !important;
    width: 100vw !important; 
    height: 100vh !important; 
    max-height: 100vh !important;
    max-width: 100vw !important;
    border-radius: 0; 
}

#aiOverlay.active { 
    display: block !important; 
    opacity: 1; 
}

/* --- 2. MESSAGE STYLING --- */
.message-user {
    align-self: flex-end;
    background: #fff; color: #000;
    padding: 14px 20px; border-radius: 20px 20px 4px 20px;
    font-size: 13px; font-weight: 700;
    max-width: 85%;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
}

.message-ai {
    align-self: flex-start;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.9); padding: 14px 20px; border-radius: 20px 20px 20px 4px;
    font-size: 13px; line-height: 1.7;
    max-width: 85%;
    display: flex; gap: 10px; align-items: flex-start;
}

/* --- 3. UI EXTRAS --- */
.message-loading {
    align-self: flex-start;
    background: rgba(255,255,255,0.03);
    padding: 14px 20px; border-radius: 20px;
    display: flex; gap: 5px; align-items: center;
}
.dot { width: 6px; height: 6px; background: #3b82f6; border-radius: 50%; animation: pulse 1.5s infinite ease-in-out; }
.dot:nth-child(2) { animation-delay: 0.2s; }
.dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes pulse { 0%, 100% { transform: scale(0.8); opacity: 0.3; } 50% { transform: scale(1.2); opacity: 1; } }

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

/* Mobile adjustments */
@media (max-width: 480px) {
    #aiChat {
        width: 95vw;
        height: 80vh;
    }
}
</style>

<script>
const aiChat = document.getElementById("aiChat");
const aiClose = document.getElementById("aiClose");
const aiOverlay = document.getElementById("aiOverlay");
const aiMessages = document.getElementById("aiMessages");
const aiInput = document.getElementById("aiInput");
const aiSend = document.getElementById("aiSend");
const aiFullscreen = document.getElementById("aiFullscreen");

let conversationHistory = [{ role: "system", content: "You are ALVA(AutoLog Vehicle Agent), a Kenyan vehicle AI. Answer professionally and contextually for Kenya." }];

function openChat() {
    if(!aiChat.classList.contains("active")) {
        aiChat.style.display = "flex";
        aiOverlay.style.display = "block";
        // Tiny timeout to trigger the CSS transform transition
        setTimeout(() => {
            aiChat.classList.add("active");
            aiOverlay.classList.add("active");
        }, 10);

        if(!document.querySelector(".message-ai.welcome")){
            showAiMessage("Hi! I'm ALVA, your AutoLog Vehicle Agent. Ask me anything about cars, NTSA, or maintenance in Kenya!", true);
        }
    } else {
        closeChat();
    }
}

function closeChat() {
    aiChat.classList.remove("active");
    aiOverlay.classList.remove("active");
    setTimeout(() => { 
        if(!aiChat.classList.contains('active')) {
            aiChat.style.display = "none";
            aiOverlay.style.display = "none";
        }
    }, 500);
}

async function typeReply(element, text) {
    const words = text.split(" ");
    for (let i = 0; i < words.length; i++) {
        element.innerHTML += words[i] + " ";
        aiMessages.scrollTop = aiMessages.scrollHeight;
        await new Promise(resolve => setTimeout(resolve, 40));
    }
}

async function showAiMessage(text, isInstant = false) {
    const aiDiv = document.createElement("div");
    aiDiv.className = "message-ai" + (isInstant ? " welcome" : "");
    const avatar = document.createElement("img");
    avatar.src = isInstant ? "../allo_profile.png" : "../allo_avatar.png";
    avatar.style.width="30px"; avatar.style.height="30px"; avatar.style.borderRadius="50%";
    aiDiv.appendChild(avatar);
    
    const textSpan = document.createElement("span");
    aiDiv.appendChild(textSpan);
    aiMessages.appendChild(aiDiv);
    
    if(isInstant) {
        textSpan.textContent = text;
        conversationHistory.push({role:"assistant", content: text});
    } else {
        await typeReply(textSpan, text);
    }
    aiMessages.scrollTop = aiMessages.scrollHeight;
}

async function sendMessage(){
    const message = aiInput.value.trim();
    if(!message) return;
    
    const userDiv = document.createElement("div");
    userDiv.className = "message-user";
    userDiv.textContent = message;
    aiMessages.appendChild(userDiv);
    aiInput.value = "";
    aiMessages.scrollTop = aiMessages.scrollHeight;
    
    conversationHistory.push({role:"user", content: message});

    const loadingDiv = document.createElement("div");
    loadingDiv.className = "message-loading";
    loadingDiv.innerHTML = '<div class="dot"></div><div class="dot"></div><div class="dot"></div>';
    aiMessages.appendChild(loadingDiv);
    aiMessages.scrollTop = aiMessages.scrollHeight;

    try {
        const res = await fetch("/includes/ai_bot_api.php", {
            method: "POST",
            headers: {"Content-Type":"application/json"},
            body: JSON.stringify({conversation: conversationHistory})
        });
        const data = await res.json();
        loadingDiv.remove();
        
        const reply = data.reply || "No response from AI.";
        await showAiMessage(reply);
        conversationHistory.push({role:"assistant", content: reply});
    } catch(err){
        loadingDiv.remove();
        const errDiv = document.createElement("div");
        errDiv.className = "message-ai";
        errDiv.textContent = "System connection error.";
        aiMessages.appendChild(errDiv);
    }
}

aiClose.onclick = closeChat;
aiOverlay.onclick = closeChat;
aiFullscreen.onclick = () => aiChat.classList.toggle("fullscreen");
aiSend.onclick = sendMessage;
aiInput.onkeydown = (e) => { if(e.key === 'Enter') sendMessage(); };

window.openChat = openChat;
</script>
