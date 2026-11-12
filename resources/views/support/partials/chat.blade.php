{{--
    The chat box. @include('support.partials.chat', ['id' => 'chat', 'head' => '…html…', 'placeholder' => '…'])
    Wired up by public/site/chat.js (new SupportChat(el, {...})).
--}}
<div class="ch" id="{{ $id ?? 'chat' }}">
    @if(!empty($head))<div class="ch-head">{!! $head !!}</div>@endif
    <div class="ch-list" data-chat-list role="log" aria-live="polite" aria-label="Messages">
        <div class="ch-empty"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
    </div>
    <div class="ch-typing" data-chat-typing hidden><i><b></b><b></b><b></b></i><span></span></div>
    <button type="button" class="ch-new" data-chat-new hidden>New messages ↓</button>
    <div class="ch-preview" data-chat-preview hidden></div>
    <form class="ch-form" data-chat-form autocomplete="off">
        <label class="ch-icon" title="Attach a photo or PDF" aria-label="Attach a photo or PDF">
            <i class="ri-attachment-2"></i><input type="file" data-chat-file accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" hidden>
        </label>
        @if(!empty($txnPicker))
            <button type="button" class="ch-icon d-lg-none" data-bs-toggle="modal" data-bs-target="#pickTxn" title="Attach a payment" aria-label="Attach a payment"><i class="ri-receipt-line"></i></button>
        @endif
        <textarea data-chat-input rows="1" maxlength="4000" placeholder="{{ $placeholder ?? 'Type a message…' }}" aria-label="Message"></textarea>
        <button type="submit" class="ch-send" data-chat-send aria-label="Send"><i class="ri-send-plane-2-fill"></i></button>
    </form>
</div>
