<x-layouts.public :title="$title" :active="$active ?? null">
    <div style="max-width:1240px;margin-inline:auto;padding:clamp(30px,5vw,60px) clamp(14px,3vw,24px)">
        <x-partials.soon :title="$title" />
    </div>
</x-layouts.public>
