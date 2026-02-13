{{-- Header layout not used - redirects to sidebar layout --}}
<x-layouts::app.sidebar :title="$title ?? null">
    {{ $slot }}
</x-layouts::app.sidebar>
