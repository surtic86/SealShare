{{-- Split auth layout - delegates to simple layout --}}
<x-layouts::auth.simple :title="$title ?? null">
    {{ $slot }}
</x-layouts::auth.simple>
