<?php
// ai_bot.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if(empty($input['conversation'])) {
        echo json_encode(['reply' => 'No conversation provided']);
        exit;
    }

    $GROQ_API_KEY = "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";

    $data = [
        "model" => "llama-3.3-70b-versatile",
        "messages" => $input['conversation'],
        "temperature" => 0.7,
        "max_tokens" => 200
    ];

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $GROQ_API_KEY",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    $reply = $result['choices'][0]['message']['content'] ?? 'No reply from AI';

    echo json_encode(['reply' => $reply]);
    exit;
}
?>

<!-- Floating AI Bot -->
<div id="aiBot" class="fixed z-[10000] select-none touch-none" style="bottom: 30px; right: 30px;">
    <div id="aiToggle" class="flex flex-col items-center cursor-pointer group">
        <div class="relative">
            <div class="absolute inset-0 bg-blue-600 rounded-full blur-2xl opacity-20 group-hover:opacity-50 transition-opacity duration-700"></div>
            
            <div class="absolute -inset-1.5 border border-dashed border-blue-500/20 rounded-full animate-[spin_10s_linear_infinite] opacity-0 group-hover:opacity-100 transition-opacity"></div>

            <div class="relative w-16 h-16 rounded-full p-1 bg-white/10 border border-white/20 backdrop-blur-md shadow-2xl overflow-hidden transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3">
                <img src="allo_avatar.png" alt="ALVA" id="aiAvatar" class="w-full h-full object-cover rounded-full bg-black shadow-inner" />
            </div>
            
            <div class="absolute bottom-1 right-1 w-3.5 h-3.5 bg-green-500 rounded-full border-2 border-black shadow-[0_0_10px_rgba(34,197,94,0.6)] z-20"></div>
        </div>

        <div id="aiLabel" class="mt-3 px-5 py-2 bg-blue-600 shadow-2xl rounded-full transform -translate-y-2 group-hover:translate-y-0 opacity-0 group-hover:opacity-100 transition-all duration-500 border border-blue-400/30">
            <span class="text-[10px] font-black text-white uppercase tracking-[0.2em] whitespace-nowrap">Initialize <span class="text-blue-100">ALVA</span></span>
        </div>
    </div>
</div>

<div id="aiChat" class="hidden fixed z-[10001] flex-col bg-[#0a0a0c]/95 backdrop-blur-3xl border border-white/10 rounded-[2.5rem] shadow-[0_30px_100px_rgba(0,0,0,0.8)] overflow-hidden transition-all duration-500" style="bottom: 120px; right: 30px; width: 400px; height: 620px;">
    
    <div id="aiHeader" class="flex items-center gap-4 p-6 bg-white/[0.02] border-b border-white/5 relative">
        <div class="relative">
            <img src="allo_profile.png" alt="ALVA" id="aiHeaderAvatar" class="w-12 h-12 rounded-2xl border border-white/10 object-cover" />
            <div class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-blue-500 rounded-full border-2 border-[#0a0a0c] animate-pulse"></div>
        </div>
        
        <div class="flex flex-col">
            <span class="text-[8px] font-black text-blue-500 uppercase tracking-[0.4em] mb-1">Neural Assistant</span>
            <span class="text-lg font-black text-white tracking-tight leading-none uppercase">ALVA <span class="text-white/20 font-light italic lowercase text-xs">v2.0</span></span>
        </div>

        <button id="aiClose" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 text-white/40 hover:bg-red-500/20 hover:text-red-400 transition-all ml-auto">✕</button>
    </div>

    <div id="aiMessages" class="flex-1 p-6 overflow-y-auto space-y-6 custom-scrollbar bg-transparent"></div>

    <div id="aiInputContainer" class="p-6 bg-gradient-to-t from-black/60 to-transparent flex flex-col gap-4">
        <div class="relative flex items-center group/input">
            <input id="aiInput" type="text" placeholder="Ask about NTSA, logistics, or specs..." 
                   class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-sm font-medium text-white placeholder-white/20 focus:outline-none focus:border-blue-500/50 focus:bg-white/[0.05] transition-all pr-24" />
            
            <button id="aiSend" class="absolute right-2 top-2 bottom-2 bg-blue-600 hover:bg-blue-500 text-white px-6 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all active:scale-95 shadow-lg shadow-blue-600/20">
                Send
            </button>
        </div>
        <p class="text-[8px] text-center text-white/20 uppercase tracking-[0.3em] font-bold">AutoLog Intelligence System • Kenya</p>
    </div>
</div>

<style>
/* Custom Scrollbar for the Elite Feel */
.custom-scrollbar::-webkit-scrollbar { width: 3px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

/* Message Animations & Shapes */
.message-user, .message-ai, .message-loading { 
    padding: 14px 18px; 
    font-size: 13.5px; 
    line-height: 1.6; 
    max-width: 85%; 
    animation: popIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
}

.message-user { 
    align-self: flex-end; 
    background: #ffffff; 
    color: #000000; 
    border-radius: 20px 20px 4px 20px; 
    font-weight: 700; 
    box-shadow: 0 10px 25px rgba(255,255,255,0.05); 
}

.message-ai { 
    align-self: flex-start; 
    background: rgba(255,255,255,0.04); 
    border: 1px solid rgba(255,255,255,0.08); 
    color: rgba(255,255,255,0.9); 
    border-radius: 20px 20px 20px 4px; 
    display: flex;
    gap: 10px;
}

.message-loading { 
    align-self: flex-start; 
    background: rgba(255,255,255,0.02); 
    color: rgba(255,255,255,0.4); 
    border-radius: 15px;
    font-style: normal;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.1em;
}

@keyframes popIn {
    0% { opacity: 0; transform: translateY(10px) scale(0.95); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

@media (max-width: 480px) {
    #aiChat { 
        width: 92vw !important; 
        height: 75vh !important; 
        right: 4vw !important; 
        bottom: 110px !important;
        border-radius: 2rem;
    }
}
</style>

<script>
const aiToggle = document.getElementById("aiToggle");
const aiChat = document.getElementById("aiChat");
const aiClose = document.getElementById("aiClose");
const aiMessages = document.getElementById("aiMessages");
const aiInput = document.getElementById("aiInput");
const aiSend = document.getElementById("aiSend");

let conversationHistory = [
    {
        role: "system",
        content: `You are ALVA(AutoLog Vehicle Agent), a professional Kenyan vehicle expert AI for AutoLog. 
You know all features of AutoLog and can guide users on how to use them. The features include:
- Searching for verified garages and booking services.
- Tracking vehicle maintenance and service history.
- Viewing car ownership and accident history.
- Logging repairs, mileage, and service alerts.
- Accessing vehicle documents like logbooks or registration certificates.
- Helping garage owners manage their profile, services, certifications, and customer requests.
- Assisting car dealerships with listing vehicles, viewing maintenance history, and tracking sales.
- Assisting car buyers in finding cars for sale through AutoLog’s dealerships, verifying vehicle history, accident reports, and ownership before purchase.

Always guide users to use AutoLog’s platform for garages, dealerships, and services. 
Do not provide generic advice, invented prices, or fake garage options. 
Be concise, friendly, clear, and use Kenyan context (KES, cities, NTSA, local brands). 
For car buyers specifically, always suggest checking AutoLog dealerships and verified vehicle histories before purchase.`
    }
];

// Initially hidden
aiChat.style.display = "none";

aiToggle.addEventListener("click", () => {
    if (aiChat.style.display !== "flex") {
        aiChat.style.display = "flex";

        // Welcome message once
        if (!document.querySelector(".message-ai.welcome")) {
            const welcomeDiv = document.createElement("div");
            welcomeDiv.className = "message-ai welcome";
            
            const avatar = document.createElement("img");
            avatar.src = "allo_profile.png";
            avatar.style.width = "30px";
            avatar.style.height = "30px";
            avatar.style.borderRadius = "50%";
            welcomeDiv.appendChild(avatar);

            const textSpan = document.createElement("span");
            textSpan.textContent = " Hi! I'm ALVA, your AutoLog Vehicle Agent. Ask me anything about your car, KES costs, NTSA, or maintenance in Kenya!";
            welcomeDiv.appendChild(textSpan);

            aiMessages.appendChild(welcomeDiv);
            aiMessages.scrollTop = aiMessages.scrollHeight;
            conversationHistory.push({ role: "assistant", content: textSpan.textContent });
        }

    } else {
        aiChat.style.display = "none";
    }
});
aiClose?.addEventListener("click", ()=> aiChat.style.display='none');

aiSend.addEventListener("click", sendMessage);
aiInput.addEventListener("keydown", e=> {if(e.key==='Enter') sendMessage();});

async function sendMessage() {
    const message = aiInput.value.trim();
    if(!message) return;

    const userDiv = document.createElement("div");
    userDiv.className = "message-user";
    userDiv.textContent = message;
    aiMessages.appendChild(userDiv);
    aiMessages.scrollTop = aiMessages.scrollHeight;
    aiInput.value = "";

    conversationHistory.push({role:"user", content:message});

    const loadingDiv = document.createElement("div");
    loadingDiv.className = "message-loading";
    aiMessages.appendChild(loadingDiv);
    aiMessages.scrollTop = aiMessages.scrollHeight;

    try {
        const response = await fetch("ai_bot.php", {
            method: "POST",
            headers: {"Content-Type":"application/json"},
            body: JSON.stringify({conversation: conversationHistory})
        });
        const data = await response.json();
        const reply = data.reply || "No response from AI.";

        loadingDiv.remove();

        const aiDiv = document.createElement("div");
        aiDiv.className = "message-ai";

        const avatar = document.createElement("img");
        avatar.src = "allo_avatar.png";
        avatar.style.width = "30px";
        avatar.style.height = "30px";
        avatar.style.borderRadius = "50%";
        aiDiv.appendChild(avatar);

        const textSpan = document.createElement("span");
        textSpan.textContent = " " + reply;
        aiDiv.appendChild(textSpan);

        aiMessages.appendChild(aiDiv);
        aiMessages.scrollTop = aiMessages.scrollHeight;

        conversationHistory.push({role:"assistant", content:reply});

    } catch(err) {
        loadingDiv.textContent="Error fetching response";
        loadingDiv.style.background="red";
        console.error(err);
    }
}
</script>