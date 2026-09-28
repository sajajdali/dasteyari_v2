<x-layouts.public :title="$campaign->title.' — دست یاری'" :description="$campaign->short" active="campaigns">
    <livewire:site.campaign-detail :campaign="$campaign" />
</x-layouts.public>
