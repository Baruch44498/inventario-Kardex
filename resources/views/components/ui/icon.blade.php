@props([
    'name',
    'size' => 20,
])

@php
    $attributes = $attributes->class('ui-icon');
@endphp

<svg
    {{ $attributes }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.9"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
            @break

        @case('products')
        @case('box')
            <path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Z"></path>
            <path d="M4 7.5V16.5L12 21l8-4.5V7.5"></path>
            <path d="M12 12v9"></path>
            @break

        @case('box-off')
            <path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Z"></path>
            <path d="M4 7.5V16.5L12 21l8-4.5V7.5"></path>
            <path d="M3 3l18 18"></path>
            @break

        @case('inventory')
            <path d="M4 7h16v13H4z"></path>
            <path d="M7 4h10v3H7z"></path>
            <path d="M8 11h8"></path>
            <path d="M8 15h5"></path>
            @break

        @case('movements')
            <path d="M7 7h11"></path>
            <path d="m14 4 4 3-4 3"></path>
            <path d="M17 17H6"></path>
            <path d="m10 14-4 3 4 3"></path>
            @break

        @case('entry')
            <path d="M12 3v12"></path>
            <path d="m7 10 5 5 5-5"></path>
            <path d="M5 21h14"></path>
            @break

        @case('exit')
            <path d="M12 21V9"></path>
            <path d="m7 14 5-5 5 5"></path>
            <path d="M5 3h14"></path>
            @break

        @case('alerts')
        @case('warning')
            <path d="M10.3 4.2 2.8 17.5A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.5L13.7 4.2a2 2 0 0 0-3.4 0Z"></path>
            <path d="M12 9v4"></path>
            <path d="M12 17h.01"></path>
            @break

        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
            <path d="M10 21h4"></path>
            @break

        @case('orders')
        @case('clipboard')
            <path d="M7 3h10"></path>
            <path d="M9 3v2"></path>
            <path d="M15 3v2"></path>
            <rect x="5" y="5" width="14" height="16" rx="2"></rect>
            <path d="M8 10h8"></path>
            <path d="M8 14h8"></path>
            <path d="M8 18h5"></path>
            @break

        @case('suppliers')
            <path d="M3 21h18"></path>
            <path d="M5 21V8l7-4 7 4v13"></path>
            <path d="M9 21v-6h6v6"></path>
            @break

        @case('requisitions')
            <path d="M6 3h12v18H6z"></path>
            <path d="M9 7h6"></path>
            <path d="M9 11h6"></path>
            <path d="M9 15h4"></path>
            @break

        @case('quotes')
            <path d="M6 3h12v18H6z"></path>
            <path d="M9 8h6"></path>
            <path d="M9 12h6"></path>
            <path d="M12 17c1.7 0 3-1 3-2.2 0-1.1-.9-1.8-3-2.2-2.1-.4-3-1.1-3-2.2C9 9.2 10.3 8.2 12 8.2"></path>
            @break

        @case('purchase-request')
            <path d="M4 5h16v14H4z"></path>
            <path d="M8 9h8"></path>
            <path d="M8 13h5"></path>
            <path d="M8 17h3"></path>
            @break

        @case('purchase-order')
            <path d="M3 6h18"></path>
            <path d="M5 6v14h14V6"></path>
            <path d="M8 10h8"></path>
            <path d="M8 14h5"></path>
            @break

        @case('invoice')
            <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"></path>
            <path d="M9 8h6"></path>
            <path d="M9 12h6"></path>
            <path d="M9 16h4"></path>
            @break

        @case('users')
        @case('user')
            <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
            <circle cx="9.5" cy="7" r="4"></circle>
            <path d="M17 11a4 4 0 0 1 4 4v2"></path>
            @break

        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
            <path d="m3 7 9 6 9-6"></path>
            @break

        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2"></rect>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
            @break

        @case('eye')
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
            <circle cx="12" cy="12" r="2.5"></circle>
            @break

        @case('eye-off')
            <path d="M3 3l18 18"></path>
            <path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6.5 0 10 6 10 6a17.5 17.5 0 0 1-2.1 2.8"></path>
            <path d="M6.5 6.5C3.6 8.3 2 12 2 12s3.5 6 10 6a10.5 10.5 0 0 0 4.1-.8"></path>
            @break

        @case('check')
        @case('check-circle')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="m8 12 2.7 2.7L16 9.5"></path>
            @break

        @case('error')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="m9 9 6 6"></path>
            <path d="m15 9-6 6"></path>
            @break

        @case('info')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 11v5"></path>
            <path d="M12 8h.01"></path>
            @break

        @case('close')
            <path d="m6 6 12 12"></path>
            <path d="m18 6-12 12"></path>
            @break

        @case('chevron-down')
            <path d="m6 9 6 6 6-6"></path>
            @break

        @case('menu')
            <path d="M4 7h16"></path>
            <path d="M4 12h16"></path>
            <path d="M4 17h16"></path>
            @break



        @case('hash')
            <path d="M5 9h14"></path>
            <path d="M4 15h14"></path>
            <path d="M10 3 8 21"></path>
            <path d="m16 3-2 18"></path>
            @break

        @case('ruler')
            <path d="m4 17 13-13 3 3L7 20H4v-3Z"></path>
            <path d="m13 8 3 3"></path>
            <path d="m10 11 2 2"></path>
            <path d="m7 14 2 2"></path>
            @break

        @case('tag')
            <path d="M20 13 13 20l-9-9V4h7l9 9Z"></path>
            <circle cx="8.5" cy="8.5" r="1.3"></circle>
            @break

        @case('align-left')
            <path d="M4 6h16"></path>
            <path d="M4 10h11"></path>
            <path d="M4 14h16"></path>
            <path d="M4 18h9"></path>
            @break

        @case('banknote')
            <rect x="3" y="6" width="18" height="12" rx="2"></rect>
            <circle cx="12" cy="12" r="2.5"></circle>
            <path d="M7 9H6a1 1 0 0 1-1-1"></path>
            <path d="M17 15h1a1 1 0 0 1 1 1"></path>
            @break

        @case('plus')
            <path d="M12 5v14"></path>
            <path d="M5 12h14"></path>
            @break

        @case('search')
            <circle cx="11" cy="11" r="7"></circle>
            <path d="m20 20-4-4"></path>
            @break

        @case('filter')
            <path d="M4 5h16"></path>
            <path d="M7 12h10"></path>
            <path d="M10 19h4"></path>
            @break

        @case('edit')
            <path d="M12 20h9"></path>
            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path>
            @break

        @case('power')
            <path d="M12 2v10"></path>
            <path d="M18.4 6.6a9 9 0 1 1-12.8 0"></path>
            @break

        @case('save')
            <path d="M5 3h12l2 2v16H5z"></path>
            <path d="M8 3v6h8V3"></path>
            <path d="M8 21v-7h8v7"></path>
            @break

        @case('arrow-left')
            <path d="m15 18-6-6 6-6"></path>
            <path d="M9 12h11"></path>
            @break

        @case('shelf')
            <path d="M4 4v16"></path>
            <path d="M20 4v16"></path>
            <path d="M4 9h16"></path>
            <path d="M4 15h16"></path>
            <path d="M8 6h3"></path>
            <path d="M13 12h4"></path>
            <path d="M7 18h5"></path>
            @break

        @case('settings')
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21h-4v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1L7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1v4H21a1.7 1.7 0 0 0-1.6 1Z"></path>
            @break

        @case('coins')
            <circle cx="9" cy="9" r="5"></circle>
            <path d="M14 7.5a5 5 0 1 1-1.5 7.5"></path>
            <path d="M9 6.5v5"></path>
            <path d="M7.5 8h2.2a1.2 1.2 0 0 1 0 2.4H8.2a1.2 1.2 0 0 0 0 2.4h2.3"></path>
            @break


        @case('activity')
            <path d="M3 12h4l2.2-6 4.1 12 2.2-6H21"></path>
            @break

        @case('refresh')
            <path d="M20 7v5h-5"></path>
            <path d="M4 17v-5h5"></path>
            <path d="M6.1 8.5A7 7 0 0 1 18.8 7L20 12"></path>
            <path d="M17.9 15.5A7 7 0 0 1 5.2 17L4 12"></path>
            @break

        @case('clock')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 7v5l3 2"></path>
            @break

        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
            <path d="M16 3v4"></path>
            <path d="M8 3v4"></path>
            <path d="M3 10h18"></path>
            @break

        @case('arrow-right')
            <path d="M5 12h14"></path>
            <path d="m14 7 5 5-5 5"></path>
            @break

        @default
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M9 12h6"></path>
    @endswitch
</svg>
