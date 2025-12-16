@props(['icon', 'label', 'value' => null, 'color' => 'teal', 'href' => null])

@php
    $colorClasses = [
        'teal' => 'bg-teal-100 text-teal-600',
        'green' => 'bg-green-100 text-green-600',
        'red' => 'bg-red-100 text-red-600',
        'blue' => 'bg-blue-100 text-blue-600',
        'purple' => 'bg-purple-100 text-purple-600',
    ];
    $colorClass = $colorClasses[$color] ?? $colorClasses['teal'];
@endphp

@if($href)
    <a href="{{ $href }}" class="stat-card cursor-pointer block">
        <div class="flex items-center">
            <div class="stat-icon {{ $colorClass }}">
                <i data-lucide="{{ $icon }}" class="h-6 w-6"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">{{ $label }}</p>
                @if(isset($value))
                    <p class="text-2xl font-semibold text-gray-900">{{ $value }}</p>
                @else
                    <p class="text-2xl font-semibold text-gray-900">{{ $slot }}</p>
                @endif
            </div>
        </div>
    </a>
@else
    <div class="stat-card">
        <div class="flex items-center">
            <div class="stat-icon {{ $colorClass }}">
                <i data-lucide="{{ $icon }}" class="h-6 w-6"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">{{ $label }}</p>
                @if(isset($value))
                    <p class="text-2xl font-semibold text-gray-900">{{ $value }}</p>
                @else
                    <p class="text-2xl font-semibold text-gray-900">{{ $slot }}</p>
                @endif
            </div>
        </div>
    </div>
@endif

