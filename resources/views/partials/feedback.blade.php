<div class="toast-stack" aria-live="polite" aria-atomic="true">
@if(session('status'))
<div class="toast" role="status" x-data="{ visible: true }" x-show="visible" x-init="setTimeout(() => visible = false, 8000)">
<span class="toast-mark" aria-hidden="true">✓</span><span>{{ session('status') }}</span><button class="icon-button" type="button" aria-label="Dismiss notification" @click="visible = false">×</button>
</div>
@endif
@if($errors->any())
<div class="toast toast-error" role="alert" x-data="{ visible: true }" x-show="visible"><span>{{ $errors->first() }}</span><button class="icon-button" type="button" aria-label="Dismiss error" @click="visible = false">×</button></div>
@endif
</div>
<dialog id="action-confirm" class="shop-modal" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div class="modal-content"><p class="eyebrow">Please review</p><h2 id="confirm-title">Confirm action</h2><p class="muted" id="confirm-message"></p>
    <div class="actions justify-end"><button type="button" class="secondary-button" data-confirm-cancel>Cancel</button><button type="button" data-confirm-accept>Confirm</button></div></div>
</dialog>

<script type="application/json" id="field-errors">{!! json_encode($errors->messages(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
