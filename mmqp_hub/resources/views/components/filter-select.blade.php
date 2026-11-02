@props(['id', 'name', 'label', 'options', 'value' => ''])

<div class="relative" :class="{ 'z-40': open }" x-data="{
    open: false,
    value: @js($value ?? ''),
    options: @js($options),
    show() {
        this.open = true;
        this.$nextTick(() => this.$refs.list.querySelector('[aria-selected=true]').focus());
    },
    close() { this.open = false; this.$refs.trigger.focus(); },
    move(step) {
        const items = [...this.$refs.list.querySelectorAll('[role=option]')];
        const index = items.indexOf(document.activeElement);
        items[(index + step + items.length) % items.length].focus();
    }
}" @click.outside="open = false" @keydown.escape.stop.prevent="close()"
    @focusout="if (!$el.contains($event.relatedTarget)) open = false">
    <label id="{{ $id }}-label" for="{{ $id }}" class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}</label>
    <select id="{{ $id }}-native" name="{{ $name }}" x-model="value" x-show="false" aria-labelledby="{{ $id }}-label"
        class="min-h-[44px] w-full rounded-xl border-slate-300 bg-slate-50 text-slate-800 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:[color-scheme:dark]">
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(($value ?? '') === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    <button x-cloak x-ref="trigger" id="{{ $id }}" type="button" aria-haspopup="listbox" :aria-expanded="open" aria-controls="{{ $id }}-options"
        aria-labelledby="{{ $id }}-label {{ $id }}-value" @click="open ? close() : show()" @keydown.arrow-down.prevent="show()" @keydown.arrow-up.prevent="show()"
        class="flex min-h-[48px] w-full items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-left text-sm text-slate-800 shadow-sm hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:border-slate-400"
        :class="{ 'ring-2 ring-red-500 border-red-500': open }">
        <span id="{{ $id }}-value" class="min-w-0 break-words" x-text="options[value]"></span>
        <svg aria-hidden="true" class="h-4 w-4 shrink-0 text-slate-500 dark:text-slate-400" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
    </button>
    <div x-cloak x-show="open" x-ref="list" id="{{ $id }}-options" role="listbox" aria-labelledby="{{ $id }}-label"
        @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
        @keydown.home.prevent="$refs.list.firstElementChild.focus()" @keydown.end.prevent="$refs.list.lastElementChild.focus()"
        @keydown="if ($event.key.length === 1 && /\S/.test($event.key)) { const match = [...$refs.list.children].find(item => item.textContent.trim().toLocaleLowerCase().startsWith($event.key.toLocaleLowerCase())); if (match) { $event.preventDefault(); match.focus(); } }"
        class="absolute left-0 right-0 top-full z-50 mt-2 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-600 dark:bg-slate-800">
        @foreach($options as $optionValue => $optionLabel)
            <button type="button" role="option" tabindex="-1" :aria-selected="value === @js((string) $optionValue)"
                @click="value = @js((string) $optionValue); close()"
                class="flex min-h-[44px] w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-200 dark:hover:bg-slate-700 dark:focus:bg-slate-700"
                :class="value === @js((string) $optionValue) ? 'bg-red-50 font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-300' : ''">
                <span>{{ $optionLabel }}</span>
                <svg aria-hidden="true" class="h-4 w-4 shrink-0" :class="{ 'invisible': value !== @js((string) $optionValue) }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 12 4 4L19 6" /></svg>
            </button>
        @endforeach
    </div>
</div>
