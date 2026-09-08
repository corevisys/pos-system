@props([
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'selected' => null,
    'placeholder' => 'Search or select...',
    'emptyOption' => null,
    'emptyValue' => '',
    'model' => null,
    'change' => null,
    'required' => false,
    'disabled' => false,
    'labelKey' => null,
    'valueKey' => null,
    'subtextKey' => null,
    'optionsExpression' => null,
    'quickAddClick' => null,
    'class' => '',
    'inputClass' => '',
    'maxHeight' => 'max-h-40',
])

@php
    $initialValue = old($name, $value ?? $selected ?? ($model ? null : ''));
    if (is_iterable($initialValue)) {
        $first = null;
        foreach ($initialValue as $v) {
            $first = $v;
            break;
        }
        $initialValue = $first;
    }
    if (is_object($initialValue)) {
        if (method_exists($initialValue, '__toString')) {
            $initialValue = (string) $initialValue;
        } else {
            $initialValue = $initialValue->{$valueKey ?? 'id'} ?? $initialValue->id ?? '';
        }
    }
    if ($initialValue === null && $emptyOption !== null) {
        $initialValue = $emptyValue;
    }
    $initialValue = is_scalar($initialValue) ? (string)$initialValue : '';

    $formattedOptions = [];
    if (is_iterable($options)) {
        foreach ($options as $key => $opt) {
            if (is_array($opt)) {
                $val = $opt[$valueKey ?? 'id'] ?? $opt['value'] ?? $key;
                $val = is_scalar($val) ? (string)$val : json_encode($val);

                $lbl = $opt[$labelKey ?? 'name'] ?? $opt['label'] ?? $opt['title'] ?? $opt['warehouse_name'] ?? $opt['customer_name'] ?? $opt['supplier_name'] ?? $opt['category_name'] ?? $opt['brand_name'] ?? $opt['unit_name'] ?? $opt['tax_name'] ?? $opt['account_name'] ?? $opt['payment_type'] ?? $opt['role_name'] ?? $opt['country_name'] ?? $opt['state_name'] ?? $opt['currency_name'] ?? $opt['language_name'] ?? $opt['template_name'] ?? $val;
                $lbl = is_scalar($lbl) ? (string)$lbl : (is_object($lbl) && method_exists($lbl, '__toString') ? (string)$lbl : json_encode($lbl));

                $sub = isset($opt[$subtextKey ?? 'mobile']) ? $opt[$subtextKey ?? 'mobile'] : ($opt['mobile'] ?? $opt['phone'] ?? $opt['account_number'] ?? $opt['account_code'] ?? $opt['item_code'] ?? $opt['sku'] ?? $opt['email'] ?? null);
                if (!$sub && isset($opt['tax']) && $opt['tax'] !== null && is_scalar($opt['tax']) && !str_contains($lbl, '%')) {
                    $sub = $opt['tax'] . '%';
                }
                $sub = is_scalar($sub) ? (string)$sub : null;

                $formattedOptions[] = [
                    'id' => $val,
                    'label' => $lbl,
                    'subtext' => $sub
                ];
            } elseif (is_object($opt)) {
                $val = $opt->{$valueKey ?? 'id'} ?? $opt->id ?? $opt->payment_type ?? $opt->value ?? $key;
                $val = is_scalar($val) ? (string)$val : (method_exists($val, '__toString') ? (string)$val : json_encode($val));

                $lbl = $opt->{$labelKey ?? 'name'} ?? $opt->warehouse_name ?? $opt->customer_name ?? $opt->supplier_name ?? $opt->category_name ?? $opt->brand_name ?? $opt->unit_name ?? $opt->tax_name ?? $opt->account_name ?? $opt->payment_type ?? $opt->role_name ?? $opt->country_name ?? $opt->state_name ?? $opt->currency_name ?? $opt->language_name ?? $opt->template_name ?? $opt->name ?? $opt->label ?? $val;
                $lbl = is_scalar($lbl) ? (string)$lbl : (is_object($lbl) && method_exists($lbl, '__toString') ? (string)$lbl : json_encode($lbl));

                $sub = isset($opt->{$subtextKey ?? 'mobile'}) ? $opt->{$subtextKey ?? 'mobile'} : ($opt->mobile ?? $opt->phone ?? $opt->account_number ?? $opt->account_code ?? $opt->item_code ?? $opt->sku ?? $opt->email ?? null);
                if (!$sub && isset($opt->tax) && $opt->tax !== null && is_scalar($opt->tax) && !str_contains($lbl, '%')) {
                    $sub = $opt->tax . '%';
                }
                $sub = is_scalar($sub) ? (string)$sub : null;

                $formattedOptions[] = [
                    'id' => $val,
                    'label' => $lbl,
                    'subtext' => $sub
                ];
            } else {
                // Scalar key => value or flat list
                $val = is_numeric($key) ? (string)$opt : (string)$key;
                $lbl = is_scalar($opt) ? (string)$opt : json_encode($opt);
                $formattedOptions[] = [
                    'id' => $val,
                    'label' => $lbl,
                    'subtext' => null
                ];
            }
        }
    }
@endphp

<div class="flex gap-2 w-full {{ $class }}"
     x-data="{
        dropdownOpen: false,
        options: {{ json_encode($formattedOptions) }},
        selectedValue: {{ json_encode($initialValue) }},
        search: '',
        highlightedIndex: -1,
        placeholder: {{ json_encode($placeholder) }},
        emptyOption: {{ json_encode($emptyOption) }},
        emptyValue: {{ json_encode(is_scalar($emptyValue) ? (string)$emptyValue : '') }},
        init() {
            @if($optionsExpression)
                const parseOptions = (rawList) => {
                    if (!Array.isArray(rawList)) return [];
                    return rawList.map(item => {
                        if (typeof item === 'object' && item !== null) {
                            return {
                                id: String(item.{{ $valueKey ?? 'id' }} ?? item.id ?? item.value ?? item.state ?? item.country ?? ''),
                                label: String(item.{{ $labelKey ?? 'name' }} ?? item.name ?? item.label ?? item.state ?? item.country ?? ''),
                                subtext: item.{{ $subtextKey ?? 'mobile' }} ?? null
                            };
                        }
                        return { id: String(item), label: String(item), subtext: null };
                    });
                };

                this.$watch('{{ $optionsExpression }}', (val) => {
                    this.options = parseOptions(val);
                    this.syncSearchFromSelected();
                });

                if (typeof {{ $optionsExpression }} !== 'undefined' && Array.isArray({{ $optionsExpression }})) {
                    this.options = parseOptions({{ $optionsExpression }});
                }
            @endif

            @if($model)
                // Watch external parent model if provided
                this.$watch('{{ $model }}', (val) => {
                    if (val !== undefined && String(val) !== String(this.selectedValue)) {
                        this.selectedValue = val === null ? '' : String(val);
                        this.syncSearchFromSelected();
                    }
                });
                if (typeof {{ $model }} !== 'undefined' && {{ $model }} !== null && {{ $model }} !== '') {
                    this.selectedValue = String({{ $model }});
                }
            @endif

            this.syncSearchFromSelected();
        },
        syncSearchFromSelected() {
            if (this.selectedValue !== null && this.selectedValue !== '' && this.selectedValue !== this.emptyValue) {
                const found = this.options.find(o => String(o.id) === String(this.selectedValue));
                if (found) {
                    this.search = found.label;
                    return;
                }
            }
            if (this.emptyOption && (this.selectedValue === this.emptyValue || this.selectedValue === '' || this.selectedValue === null)) {
                this.search = this.emptyOption;
            } else {
                this.search = '';
            }
        },
        filteredOptions() {
            if (!this.search || this.search === this.emptyOption) {
                return this.options;
            }
            const query = this.search.toLowerCase().trim();
            const currentSelected = this.options.find(o => String(o.id) === String(this.selectedValue));
            if (currentSelected && this.search === currentSelected.label) {
                return this.options;
            }
            return this.options.filter(o => {
                const matchLabel = o.label && o.label.toLowerCase().includes(query);
                const matchSub = o.subtext && o.subtext.toLowerCase().includes(query);
                return matchLabel || matchSub;
            });
        },
        select(option) {
            if (option) {
                this.selectedValue = String(option.id);
                this.search = option.label;
            } else {
                this.selectedValue = this.emptyValue;
                this.search = this.emptyOption || '';
            }
            this.dropdownOpen = false;
            this.highlightedIndex = -1;

            @if($model)
                {{ $model }} = this.selectedValue;
            @endif

            this.$nextTick(() => {
                if (this.$refs.hiddenInput) {
                    this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                this.$dispatch('select', { value: this.selectedValue, option: option });
                this.$dispatch('input', this.selectedValue);
                this.$dispatch('change', this.selectedValue);

                @if($change)
                    {!! $change !!};
                @endif
            });
        },
        handleInputClick() {
            if (this.emptyOption && this.search === this.emptyOption) {
                this.search = '';
            } else {
                const currentSelected = this.options.find(o => String(o.id) === String(this.selectedValue));
                if (currentSelected && this.search === currentSelected.label) {
                    // select all text for fast replacement on typing
                    if (this.$refs.textInput) this.$refs.textInput.select();
                }
            }
            this.dropdownOpen = true;
            this.highlightedIndex = -1;
        },
        handleArrowDown() {
            if (!this.dropdownOpen) {
                this.dropdownOpen = true;
            }
            const filtered = this.filteredOptions();
            if (filtered.length === 0) return;
            this.highlightedIndex = (this.highlightedIndex + 1) % filtered.length;
        },
        handleArrowUp() {
            if (!this.dropdownOpen) {
                this.dropdownOpen = true;
            }
            const filtered = this.filteredOptions();
            if (filtered.length === 0) return;
            this.highlightedIndex = this.highlightedIndex <= 0 ? filtered.length - 1 : this.highlightedIndex - 1;
        },
        handleClickAway() {
            this.dropdownOpen = false;
            this.syncSearchFromSelected();
        },
        handleEnter() {
            if (!this.dropdownOpen) return;
            const filtered = this.filteredOptions();
            if (filtered.length > 0) {
                this.select(filtered[this.highlightedIndex >= 0 ? this.highlightedIndex : 0]);
            } else if (this.emptyOption && this.search.toLowerCase().includes(this.emptyOption.toLowerCase())) {
                this.select(null);
            }
        }
     }"
     @option-added.window="if($event.detail.name === '{{ $name ?? '' }}' || $event.detail.target === '{{ $name ?? '' }}' || $event.detail.model === '{{ $model ?? '' }}') {
        const newOpt = {
            id: String($event.detail.id ?? $event.detail.value),
            label: String($event.detail.label ?? $event.detail.name),
            subtext: $event.detail.subtext ? String($event.detail.subtext) : null
        };
        if (!options.some(o => String(o.id) === String(newOpt.id))) {
            options.push(newOpt);
        }
        select(newOpt);
     }">

    <div class="relative flex-1">
        @if($name)
            <input type="hidden"
                   x-ref="hiddenInput"
                   name="{{ $name }}"
                   @if($id) id="{{ $id }}" @endif
                   :value="selectedValue"
                   @if($required) required @endif>
        @endif

        <input type="text"
               x-ref="textInput"
               x-model="search"
               @click="handleInputClick()"
               @input="dropdownOpen = true; highlightedIndex = -1"
               @click.away="handleClickAway()"
               @keydown.arrowdown.prevent="handleArrowDown()"
               @keydown.arrowup.prevent="handleArrowUp()"
               @keydown.enter.prevent="handleEnter()"
               @keydown.escape="handleClickAway()"
               placeholder="{{ $placeholder }}"
               @if($disabled) disabled @endif
               class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-dark-border rounded-xl py-2 px-4 pr-9 text-[11px] font-bold text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-1 focus:ring-primary-500 transition-all outline-none {{ $inputClass }}">

        <!-- Chevron Icon -->
        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
            <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="dropdownOpen ? 'rotate-180 text-primary-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>

        <!-- Dropdown Menu -->
        <div x-show="dropdownOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="absolute z-50 w-full mt-1 bg-white dark:bg-dark-card border border-slate-100 dark:border-dark-border rounded-xl shadow-xl {{ $maxHeight }} overflow-y-auto custom-scrollbar">
            <div class="p-1">
                @if($emptyOption !== null)
                    <div @click="select(null)"
                         class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer rounded-lg text-[10px] font-bold text-slate-400 italic transition-colors border-b border-slate-50 dark:border-dark-border mb-1"
                         :class="(selectedValue === emptyValue || !selectedValue) ? 'bg-primary-50/50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400' : ''">
                        {{ $emptyOption }}
                    </div>
                @endif

                <template x-for="option in filteredOptions()" :key="option.id">
                    <div @click="select(option)"
                         class="px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer rounded-lg text-[11px] font-bold transition-colors"
                        :class="String(selectedValue) === String(option.id) ? 'bg-primary-50 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400' : (filteredOptions().indexOf(option) === highlightedIndex ? 'bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300' : 'text-slate-600 dark:text-slate-300')">
                        <div class="flex flex-col">
                            <span x-text="option.label" class="truncate"></span>
                            <span x-show="option.subtext" class="text-[9px] text-slate-400" x-text="option.subtext"></span>
                        </div>
                    </div>
                </template>

                <div x-show="filteredOptions().length === 0" class="px-4 py-2 text-[10px] text-slate-400 italic text-center">
                    No results found
                </div>
            </div>
        </div>
    </div>

    @if($quickAddClick)
        <button type="button"
                @click="{!! $quickAddClick !!}"
                class="p-2 bg-primary-50 dark:bg-primary-500/10 text-primary-600 rounded-xl hover:bg-primary-100 dark:hover:bg-primary-500/20 transition-colors border border-primary-100 dark:border-primary-500/20 flex-shrink-0 flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
            </svg>
        </button>
    @endif
</div>
