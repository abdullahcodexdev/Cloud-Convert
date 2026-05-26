@php
    $whatsappChatUrl = route('whatsapp.chat');
@endphp

<div class="whatsapp-widget">
    <a class="whatsapp-float-btn whatsapp-float-btn-main" href="{{ $whatsappChatUrl }}" aria-label="Open WhatsApp support">
        <i class="bi bi-whatsapp"></i>
    </a>
</div>
