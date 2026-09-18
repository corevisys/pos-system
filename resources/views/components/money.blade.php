{{--
    Compact money display (single source of truth for the FORMAT is
    window.formatCompactAmount() in resources/js/format-compact-amount.js).

    Renders a compact label (e.g. 12.5K) and always carries the exact
    value via:
      * a hover tooltip (desktop)
      * a tap/click tooltip (touch — no hover)
      * a :data-exact-amount attribute (machine-readable, never lossy)

    Props:
      value     : a RAW Alpine expression (NOT a PHP value). It is interpolated
                  verbatim into x-text / :data-exact-amount, e.g.
                  "subtotal", "parseFloat(cashAmount) || 0",
                  "totalDiscount - couponAmount".
                  IMPORTANT: use the plain `value="..."` attribute (or
                  :value="'...'"), never `:value="subtotal"` — a leading colon
                  makes Blade evaluate the RHS as PHP, but these are Alpine
                  runtime getters that do not exist server-side.
      symbol    : currency symbol override (defaults to view-shared $currencySymbol)
      intercept : when true the click toggles the tooltip and stops the event
                  (used for amounts inside clickable rows/links)
--}}
@props([
    'value' => '0',
    'symbol' => null,
    'intercept' => false,
])

@php
    // Fall back to the globally view-shared currency symbol (AppServiceProvider::resolveCurrencySymbol).
    $symbol = $symbol ?? ($currencySymbol ?? '');
    // The value is a raw client-side (Alpine) expression; keep it as-is.
    $expr = $value;
@endphp

<span
    x-data="{ amountTipOpen: false }"
    @if($intercept)
        @click="amountTipOpen = !amountTipOpen; $event.preventDefault(); $event.stopPropagation()"
    @else
        @click="amountTipOpen = !amountTipOpen"
    @endif
    @click.away="amountTipOpen = false"
    @keydown.escape.window="amountTipOpen = false"
    :data-exact-amount="(Number({{ $expr }}) || 0).toFixed(2)"
    {{ $attributes->merge(['class' => 'relative inline-flex group cursor-help']) }}
>
    <span class="tabular-nums" x-text="'{{ $symbol }}' + window.formatCompactAmount({{ $expr }}).compact"></span>

    <span class="amount-tip" :class="{ 'tip-open': amountTipOpen }" x-cloak role="tooltip">
        <span class="text-slate-400">{{ $symbol }}</span><span class="font-black" x-text="window.formatCompactAmount({{ $expr }}).exact"></span>
    </span>
</span>