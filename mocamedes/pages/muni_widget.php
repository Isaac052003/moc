<?php
/**
 * =============================================
 * MUNI — WIDGET ULTRA MODERN (v2.0)
 * Portal do Moçamedense
 * =============================================
 * Incluir no cidadao.php antes do </body>:
 *   <?php include 'muni_widget.php'; ?>
 * =============================================
 */

// Nome do primeiro cidadão para o JS
$muni_nome = htmlspecialchars(explode(' ', $_SESSION['nome'] ?? 'Cidadão')[0]);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

:root {
    /* Light Mode - Paleta Elegante */
    --muni-bg: #ffffff;
    --muni-panel-bg: rgba(255, 255, 255, 0.85);
    --muni-text: #1e293b;
    --muni-text-muted: #64748b;
    --muni-primary: #0f172a;
    --muni-accent: #3b82f6;
    --muni-accent-gradient: linear-gradient(135deg, #3b82f6 0%, #2dd4bf 100%);
    --muni-bot-msg: #f1f5f9;
    --muni-border: rgba(0, 0, 0, 0.08);
    --muni-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    --muni-blur: blur(16px);
    --muni-chat-bg: #ffffff;
}

[data-theme="dark"] {
    /* Dark Mode - Estilo Profundo */
    --muni-bg: #0f172a;
    --muni-panel-bg: rgba(15, 23, 42, 0.9);
    --muni-text: #f8fafc;
    --muni-text-muted: #94a3b8;
    --muni-primary: #ffffff;
    --muni-bot-msg: #1e293b;
    --muni-border: rgba(255, 255, 255, 0.1);
    --muni-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    --muni-chat-bg: #0f172a;
}

body {
    transition: background 0.3s ease;
}

#muni-wrapper {
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: var(--muni-text);
}

/* --- Botão Flutuante (FAB) --- */
#muni-fab {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 64px;
    height: 64px;
    background: var(--muni-accent-gradient);
    border-radius: 22px;
    border: none;
    cursor: pointer;
    box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

#muni-fab:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 20px 30px -5px rgba(59, 130, 246, 0.5);
}

#muni-fab svg {
    width: 28px;
    height: 28px;
    fill: white;
}

/* --- Painel de Chat --- */
#muni-panel {
    position: fixed;
    bottom: 7rem;
    right: 2rem;
    width: 420px;
    height: 70vh;
    max-height: 680px;
    background: var(--muni-panel-bg);
    backdrop-filter: var(--muni-blur);
    -webkit-backdrop-filter: var(--muni-blur);
    border: 1px solid var(--muni-border);
    border-radius: 32px;
    box-shadow: var(--muni-shadow);
    display: flex;
    flex-direction: column;
    z-index: 9998;
    overflow: hidden;
    opacity: 0;
    transform: translateY(30px) scale(0.95);
    pointer-events: none;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
}

#muni-panel.show {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: all;
}

/* --- Cabeçalho --- */
.mh-header {
    padding: 1.2rem 1.5rem;
    background: var(--muni-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--muni-border);
}

.mh-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.mh-avatar {
    width: 48px;
    height: 48px;
    background: var(--muni-accent-gradient);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.mh-status h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--muni-text);
}

.mh-status span {
    font-size: 12px;
    color: var(--muni-text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
}

.online-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}

/* --- Botão Dark Mode --- */
.muni-theme-toggle {
    background: transparent;
    border: 1px solid var(--muni-border);
    color: var(--muni-text);
    padding: 8px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.muni-theme-toggle:hover {
    background: var(--muni-bg);
    transform: scale(1.05);
}

/* --- Chips de Sugestão --- */
.muni-chips {
    padding: 0.8rem 1rem;
    background: var(--muni-border);
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--muni-border);
}

.muni-chip {
    background: var(--muni-bg);
    border: 1px solid var(--muni-border);
    color: var(--muni-text);
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}

.muni-chip:hover {
    background: var(--muni-accent);
    color: white;
    border-color: var(--muni-accent);
    transform: translateY(-2px);
}

/* --- Mensagens --- */
.mmsgs-container {
    flex: 1;
    overflow-y: auto;
    padding: 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    background: var(--muni-chat-bg);
}

/* Scrollbar Custom */
.mmsgs-container::-webkit-scrollbar {
    width: 5px;
}

.mmsgs-container::-webkit-scrollbar-track {
    background: var(--muni-border);
    border-radius: 10px;
}

.mmsgs-container::-webkit-scrollbar-thumb {
    background: var(--muni-accent);
    border-radius: 10px;
}

.mmsg {
    max-width: 85%;
    padding: 12px 18px;
    font-size: 14px;
    line-height: 1.5;
    animation: slideUp 0.3s ease-out;
    word-wrap: break-word;
    white-space: pre-wrap;
}

.mmsg.bot {
    background: var(--muni-bot-msg);
    color: var(--muni-text);
    border-radius: 4px 20px 20px 20px;
    align-self: flex-start;
    border: 1px solid var(--muni-border);
}

.mmsg.user {
    background: var(--muni-accent-gradient);
    color: white;
    border-radius: 20px 20px 4px 20px;
    align-self: flex-end;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
}

.mmsg strong {
    font-weight: 700;
    color: inherit;
}

.mmsg.user strong {
    color: white;
}

/* --- Indicador de Digitação --- */
.typing-indicator {
    background: var(--muni-bot-msg);
    border-radius: 4px 20px 20px 20px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    border: 1px solid var(--muni-border);
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: var(--muni-accent);
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out;
}

.typing-indicator span:nth-child(1) { animation-delay: 0s; }
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0);
        opacity: 0.4;
    }
    30% {
        transform: translateY(-6px);
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* --- Input Area --- */
.muni-input-area {
    padding: 1rem 1.2rem;
    background: var(--muni-border);
    display: flex;
    align-items: flex-end;
    gap: 10px;
    border-top: 1px solid var(--muni-border);
}

.muni-input-area textarea {
    flex: 1;
    background: var(--muni-bg);
    border: 1px solid var(--muni-border);
    border-radius: 20px;
    padding: 12px 16px;
    color: var(--muni-text);
    resize: none;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    max-height: 100px;
    min-height: 44px;
    transition: all 0.2s;
}

.muni-input-area textarea:focus {
    border-color: var(--muni-accent);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.muni-send-btn {
    width: 44px;
    height: 44px;
    border-radius: 15px;
    background: var(--muni-accent-gradient);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.muni-send-btn svg {
    fill: white;
    width: 20px;
    height: 20px;
}

.muni-send-btn:hover {
    transform: rotate(-10deg) scale(1.05);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.muni-send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Footer */
.muni-footer {
    text-align: center;
    font-size: 0.65rem;
    color: var(--muni-text-muted);
    padding: 0.5rem 0;
    background: var(--muni-border);
}

/* Responsivo */
@media (max-width: 480px) {
    #muni-panel {
        width: calc(100vw - 2rem);
        right: 1rem;
        bottom: 6rem;
        max-height: 60vh;
    }
    #muni-fab {
        bottom: 1.25rem;
        right: 1.25rem;
        width: 56px;
        height: 56px;
    }
    .mh-header {
        padding: 1rem;
    }
    .mmsgs-container {
        padding: 1rem;
    }
    .mmsg {
        max-width: 90%;
    }
}
</style>

<div id="muni-wrapper">
    <button id="muni-fab" onclick="toggleMuni()">
        <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
    </button>

    <div id="muni-panel">
        <header class="mh-header">
            <div class="mh-user-info">
                <div class="mh-avatar">🤖</div>
                <div class="mh-status">
                    <h4>Assistente MUNI</h4>
                    <span><div class="online-dot"></div> Ativo agora · Groq IA</span>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="muni-theme-toggle" onclick="toggleTheme()" title="Mudar Tema">
                    <svg id="theme-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"/>
                        <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                    </svg>
                </button>
                <button class="muni-theme-toggle" onclick="toggleMuni()">✕</button>
            </div>
        </header>

        <!-- Chips de Sugestão -->
        <div class="muni-chips" id="muniChips">
            <button class="muni-chip" onclick="muniChip('Quais são os meus processos?')">📋 Meus processos</button>
            <button class="muni-chip" onclick="muniChip('Documentos para regularização de terreno')">🏠 Terreno</button>
            <button class="muni-chip" onclick="muniChip('Documentos para licença de construção')">🏗️ Construção</button>
            <button class="muni-chip" onclick="muniChip('Como redigir um requerimento?')">📝 Requerimento</button>
            <button class="muni-chip" onclick="muniChip('Explicar as fases do processo')">🔄 Fases</button>
        </div>

        <div class="mmsgs-container" id="muniMsgs">
            <!-- Mensagem inicial será inserida pelo JS -->
        </div>

        <div class="muni-input-area">
            <textarea id="muniInput" placeholder="Como posso ajudar?" rows="1" oninput="autoGrow(this)"></textarea>
            <button class="muni-send-btn" onclick="muniEnviar()">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </div>
        <div class="muni-footer">✨ Powered by Groq · llama-3.3-70b · Cloudflare</div>
    </div>
</div>

<script>
    // Elementos DOM
    const panel = document.getElementById('muni-panel');
    const msgs = document.getElementById('muniMsgs');
    const input = document.getElementById('muniInput');
    const chips = document.getElementById('muniChips');
    let pensando = false;
    let historico = [];
    let isDark = false;

    // Carregar tema salvo
    const savedTheme = localStorage.getItem('muni_theme');
    if (savedTheme === 'dark') {
        isDark = true;
        document.body.setAttribute('data-theme', 'dark');
        updateThemeIcon();
    }

    function toggleMuni() {
        panel.classList.toggle('show');
        if (panel.classList.contains('show') && msgs.children.length === 0) {
            setTimeout(() => addMsg('bot', 'Olá, **<?php echo $muni_nome; ?>**! 👋\n\nSou o MUNI, o seu Assistente Virtual da Administração Municipal de Moçâmedes.\n\nComo posso ajudá-lo hoje?'), 400);
        }
    }

    function toggleTheme() {
        isDark = !isDark;
        document.body.setAttribute('data-theme', isDark ? 'dark' : 'light');
        localStorage.setItem('muni_theme', isDark ? 'dark' : 'light');
        updateThemeIcon();
    }

    function updateThemeIcon() {
        const icon = document.getElementById('theme-icon');
        if (isDark) {
            icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
        } else {
            icon.innerHTML = '<circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>';
        }
    }

    function addMsg(type, text) {
        const div = document.createElement('div');
        div.className = `mmsg ${type}`;
        div.innerHTML = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
        msgs.appendChild(div);
        msgs.scrollTop = msgs.scrollHeight;
        return div;
    }

    function showTyping() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.id = 'muniTyping';
        typingDiv.innerHTML = '<span></span><span></span><span></span>';
        msgs.appendChild(typingDiv);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function hideTyping() {
        const typing = document.getElementById('muniTyping');
        if (typing) typing.remove();
    }

    function autoGrow(el) {
        el.style.height = "auto";
        el.style.height = Math.min(el.scrollHeight, 100) + "px";
    }

    window.muniChip = (t) => {
        chips.style.display = 'none';
        input.value = t;
        muniEnviar();
    };

    function muniEnviar() {
        if (pensando) return;
        const txt = input.value.trim();
        if (!txt) return;

        chips.style.display = 'none';
        addMsg('user', txt);
        historico.push({ role: 'user', content: txt });
        input.value = '';
        input.style.height = 'auto';
        pensando = true;
        showTyping();

        const formData = new FormData();
        formData.append('muni_action', 'chat');
        formData.append('mensagem', txt);
        formData.append('historico', JSON.stringify(historico.slice(-10)));

        fetch('muni_chat.php', { method: 'POST', body: formData })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(d => {
                hideTyping();
                const resp = d.resposta || 'Sem resposta.';
                addMsg('bot', resp);
                historico.push({ role: 'assistant', content: resp });
            })
            .catch(e => {
                hideTyping();
                addMsg('bot', '⚠️ Erro de ligação. Tente novamente.\n(' + e.message + ')');
            })
            .finally(() => {
                pensando = false;
                input.focus();
            });
    }

    // Eventos
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            muniEnviar();
        }
    });
</script>