<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>WhatsApp Support | FluxConvert</title>
    <meta name="description" content="Contact FluxConvert support on WhatsApp for file conversion help.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
</head>
<body>
    @include('partials.header')

    <main class="whatsapp-page">
        <section class="whatsapp-launch-section">
            <div class="container">
                <div class="whatsapp-support-layout">
                    <div class="whatsapp-launch-card">
                        <p class="whatsapp-launch-copy">
                            Need help with file conversion, download issues, or supported formats? Chat with FluxConvert support on WhatsApp.
                        </p>
                        <div class="whatsapp-scan-note">
                            <i class="bi bi-qr-code-scan"></i>
                            <span>If WhatsApp Web asks for a QR scan, scan it with your phone. This FluxConvert page will stay open so you can return anytime.</span>
                        </div>

                        <div class="whatsapp-launch-actions">
                            <a class="whatsapp-launch-btn whatsapp-launch-btn-primary" href="{{ $whatsappAppUrl }}" target="_blank" rel="noopener noreferrer">
                                Open app
                            </a>
                            <a class="whatsapp-launch-btn whatsapp-launch-btn-secondary" href="{{ $whatsappWebUrl }}" target="_blank" rel="noopener noreferrer">
                                Continue to WhatsApp Web
                            </a>
                            <a class="whatsapp-launch-btn whatsapp-launch-btn-back" href="/#converter">
                                Back to FluxConvert
                            </a>
                        </div>

                        <p class="whatsapp-download-copy">
                            <i class="bi bi-whatsapp"></i>
                            <span>Don't have the app?</span>
                            <a href="{{ $whatsappDownloadUrl }}" target="_blank" rel="noopener noreferrer">Download it now</a>
                        </p>

                        @if(!$whatsappNumber)
                            <div class="whatsapp-config-note">
                                Add <strong>WHATSAPP_NUMBER</strong> in your <strong>.env</strong> file to send users directly to your chat.
                            </div>
                        @endif
                    </div>

                    <section class="ai-support-card" aria-label="FluxConvert AI support chat">
                        <div class="ai-support-head">
                            <span class="ai-support-avatar">
                                <i class="bi bi-stars"></i>
                            </span>
                            <div>
                                <h2>FluxConvert AI Support</h2>
                                <p>Ask a question before opening WhatsApp.</p>
                            </div>
                        </div>
                        <div class="ai-chat-window" id="aiChatWindow">
                            <div class="ai-message ai-message-assistant">
                                Hi, I am FluxConvert Support. How can I help you today?
                            </div>
                        </div>
                        <form class="ai-chat-form" id="aiChatForm">
                            <input id="aiChatInput" type="text" maxlength="1000" autocomplete="off" placeholder="Type your question..." required>
                            <button type="submit">
                                <i class="bi bi-send-fill"></i>
                                <span>Send</span>
                            </button>
                        </form>
                    </section>
                </div>
            </div>
        </section>
    </main>

    @include('partials.footer')

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    <script>
        (() => {
            const form = document.getElementById("aiChatForm");
            const input = document.getElementById("aiChatInput");
            const windowEl = document.getElementById("aiChatWindow");
            const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
            const history = [];

            const scrollChatToBottom = () => {
                requestAnimationFrame(() => {
                    windowEl.scrollTop = windowEl.scrollHeight;
                });
            };

            const addMessage = (role, content, store = true) => {
                const message = document.createElement("div");
                message.className = `ai-message ai-message-${role}`;
                message.textContent = content;
                windowEl.appendChild(message);
                scrollChatToBottom();
                if (store && (role === "user" || role === "assistant")) {
                    history.push({ role, content });
                    if (history.length > 12) {
                        history.shift();
                    }
                }
                return message;
            };

            form?.addEventListener("submit", async (event) => {
                event.preventDefault();
                const message = input.value.trim();
                if (!message) {
                    return;
                }

                input.value = "";
                addMessage("user", message);
                const requestHistory = history.slice();
                const pending = addMessage("assistant", "Typing...", false);

                try {
                    const response = await fetch("{{ route('ai.chat') }}", {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": token,
                            "Accept": "application/json",
                        },
                        body: JSON.stringify({ message, history: requestHistory.slice(0, -1) }),
                    });
                    const data = await response.json();
                    pending.textContent = data.reply || "I could not answer that. Please try again.";
                    scrollChatToBottom();
                    history.push({ role: "assistant", content: pending.textContent });
                } catch (error) {
                    pending.textContent = "Chat is not available right now. You can still open WhatsApp above.";
                    scrollChatToBottom();
                    history.push({ role: "assistant", content: pending.textContent });
                } finally {
                    while (history.length > 12) {
                        history.shift();
                    }
                }
            });
        })();
    </script>
    @include('partials.performance')
</body>
</html>
