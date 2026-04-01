<?php
// ai_bot.php

// Handle AI POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if(empty($input['conversation'])) {
        echo json_encode(['reply' => 'No conversation provided']);
        exit;
    }

    // Groq API key - only on server
    $GROQ_API_KEY = "YOUR_SECRET_GROQ_KEY_HERE";

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
<div id="aiBot" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
    <button id="aiToggle" style="background:#4F46E5;color:white;border:none;padding:12px 18px;border-radius:50px;cursor:pointer;box-shadow:0 4px 6px rgba(0,0,0,0.2)">
        AI Bot
    </button>

    <div id="aiChat" style="display:none; width:300px; max-height:400px; background:#1E1F23; color:white; border-radius:15px; box-shadow:0 8px 20px rgba(0,0,0,0.3); overflow:hidden; margin-top:10px;">
        <div id="aiMessages" style="padding:10px; height:300px; overflow-y:auto;"></div>
        <div style="display:flex; border-top:1px solid #333;">
            <input id="aiInput" type="text" placeholder="Ask me anything..." style="flex:1; padding:8px; border:none; outline:none; background:#2A2B2F; color:white;">
            <button id="aiSend" style="padding:8px 12px; background:#4F46E5; color:white; border:none; cursor:pointer;">Send</button>
        </div>
    </div>
</div>

<script>
const aiToggle = document.getElementById("aiToggle");
const aiChat = document.getElementById("aiChat");
const aiMessages = document.getElementById("aiMessages");
const aiInput = document.getElementById("aiInput");
const aiSend = document.getElementById("aiSend");

// Keep conversation history
let conversationHistory = [
    { role: "system", content: "You are a professional car expert. Answer naturally, concisely, and remember the conversation context." }
];

// Toggle chat
aiToggle.addEventListener("click", () => {
    aiChat.style.display = aiChat.style.display === "none" ? "block" : "none";
});

// Send message
aiSend.addEventListener("click", sendMessage);
aiInput.addEventListener("keydown", (e) => { if(e.key === "Enter") sendMessage(); });

async function sendMessage() {
    const message = aiInput.value.trim();
    if(!message) return;

    // Show user message
    aiMessages.innerHTML += `<div style="margin:5px 0; text-align:right;"><span style="background:#4F46E5;padding:5px 8px;border-radius:12px;">${message}</span></div>`;
    aiMessages.scrollTop = aiMessages.scrollHeight;
    aiInput.value = "";

    // Add to conversation history
    conversationHistory.push({ role: "user", content: message });

    // Loading placeholder
    const loadingId = "loading-" + Date.now();
    aiMessages.innerHTML += `<div id="${loadingId}" style="margin:5px 0;"><span style="background:#333;padding:5px 8px;border-radius:12px;">...</span></div>`;
    aiMessages.scrollTop = aiMessages.scrollHeight;

    try {
        const response = await fetch("ai_bot.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ conversation: conversationHistory })
        });

        const data = await response.json();
        const reply = data.reply || "No response from AI.";

        // Remove loading
        document.getElementById(loadingId).remove();

        // Show AI reply
        aiMessages.innerHTML += `<div style="margin:5px 0; text-align:left;"><span style="background:#2A2B2F;padding:5px 8px;border-radius:12px;">${reply}</span></div>`;
        aiMessages.scrollTop = aiMessages.scrollHeight;

        // Add AI reply to conversation
        conversationHistory.push({ role: "assistant", content: reply });

    } catch(err) {
        document.getElementById(loadingId).innerHTML = `<span style="background:red;padding:5px 8px;border-radius:12px;">Error fetching response</span>`;
        console.error(err);
    }
}
</script>