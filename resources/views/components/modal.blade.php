@props(['name', 'title', 'open' => false])
<dialog class="shop-modal" id="{{ $name }}" aria-labelledby="{{ $name }}-title"
    x-data="{ initial: @js((bool)$open || ($errors->any() && old('_modal') === $name)) }"
    x-init="if (initial) $nextTick(() => $el.showModal())"
    @open-modal.window="if ($event.detail === @js($name)) $el.showModal()"
    @click="if ($event.target === $el) $el.close()">
    <div class="modal-content">
        <header class="modal-heading"><h2 id="{{ $name }}-title">{{ $title }}</h2><button type="button" class="icon-button secondary-button" aria-label="Close dialog" @click="$el.closest('dialog').close()">×</button></header>
        {{ $slot }}
    </div>
</dialog>
