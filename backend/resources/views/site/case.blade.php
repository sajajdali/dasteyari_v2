<x-layouts.public :title="$request->title.' — دست یاری'" :description="'کمک به این پرونده: '.$request->title.' — مانده '.money($request->remaining)" active="cases">
    <livewire:site.case-detail :request="$request" />
</x-layouts.public>
