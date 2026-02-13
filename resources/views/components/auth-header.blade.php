@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h2 class="text-xl font-bold">{{ $title }}</h2>
    <p class="text-sm opacity-60 mt-1">{{ $description }}</p>
</div>
