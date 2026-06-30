@props(['type', 'message'])


@php
    $colors = [
        'success' => 'bg-green-100 border-green-700 text-green-700',
        'error' => 'bg-red-100 border-red-700 text-red-700',
      
    ];
    $class = $colors[$type] ?? $colors['success'];
    
@endphp

@if($message)
    <p class="my-10 text-center border-l-8 py-3 text-sm font-bold uppercase {{ $class }}">
        {{ $message }}
    </p>
@endif
