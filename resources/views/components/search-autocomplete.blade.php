@props(['id' => 'q', 'label', 'endpoint', 'placeholder' => '', 'describedby' => ''])
<div class="search-autocomplete" data-search-autocomplete data-suggestion-url="{{ $endpoint }}">
    <label for="{{ $id }}">{{ $label }}</label>
    <input type="search" id="{{ $id }}" name="q" value="{{ request('q') }}"
        maxlength="100" placeholder="{{ $placeholder }}" autocomplete="off" spellcheck="false"
        role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false"
        aria-controls="{{ $id }}-suggestions" aria-describedby="{{ trim($id.'-search-help '.$describedby) }}">
    <div class="search-suggestions" data-suggestion-panel hidden>
        <div id="{{ $id }}-suggestions" role="listbox" aria-label="{{ $label }} suggestions"></div>
        <p class="search-suggestion-feedback" data-suggestion-feedback role="status" aria-live="polite"></p>
    </div>
    <span class="sr-only" id="{{ $id }}-search-help">Type to see matching records. Use Up and Down arrows to choose, Enter to search, and Escape to close suggestions.</span>
</div>