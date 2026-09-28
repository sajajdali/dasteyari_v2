<x-layouts.public :title="(isset($group) ? $group->title.' — ' : '').'گروه‌های کمک — دست یاری'" active="groups">
    <livewire:site.groups :group="$group ?? null" />
</x-layouts.public>
