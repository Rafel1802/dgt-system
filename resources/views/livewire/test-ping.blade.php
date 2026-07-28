<?php

new class extends Livewire\Component {
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }
};
?>

<div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
    <p class="text-sm text-slate-600 dark:text-slate-300">Livewire test component (internal, not linked from any menu)</p>
    <p class="mt-2 text-2xl font-semibold" wire:loading.class="opacity-50">Count: {{ $count }}</p>
    <button type="button" wire:click="increment" wire:loading.attr="disabled"
        class="mt-2 rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
        Increment
    </button>
</div>
