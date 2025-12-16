@props(['class' => '', 'hover' => true])

<div class="medical-card {{ $hover ? 'hover:shadow-lg' : '' }} {{ $class }}" style="transition: all 120ms ease-out; min-height: fit-content;">
    {{ $slot }}
</div>

