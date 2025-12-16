@props(['type' => 'consultation', 'patientName', 'date', 'time', 'caseType'])

@php
    $typeClasses = [
        'emergency' => 'emergency border-red-500',
        'consultation' => 'consultation border-teal-500',
    ];
    $typeClass = $typeClasses[$type] ?? $typeClasses['consultation'];
@endphp

<div class="request-card {{ $typeClass }}">
    <div class="flex items-start justify-between mb-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ $patientName }}</h3>
            <p class="text-sm font-medium {{ $type === 'emergency' ? 'text-red-600' : 'text-teal-600' }} mt-1">
                {{ $caseType }}
            </p>
        </div>
    </div>
    <div class="flex items-center text-sm text-gray-600 mb-4">
        <i data-lucide="calendar" class="h-4 w-4 mr-2"></i>
        <span>{{ $date }}</span>
        <i data-lucide="clock" class="h-4 w-4 ml-4 mr-2"></i>
        <span>{{ $time }}</span>
    </div>
    <button class="w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-all duration-120">
        See Details
    </button>
</div>

