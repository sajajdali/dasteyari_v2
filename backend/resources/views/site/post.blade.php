<x-layouts.public :title="$post->title.' — دست یاری'" :description="$post->excerpt" active="news">
    <livewire:site.post-detail :post="$post" />
</x-layouts.public>
