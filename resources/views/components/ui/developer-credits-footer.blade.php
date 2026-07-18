<footer {{ $attributes->merge(['class' => 'border-t border-slate-200 bg-slate-100']) }}>
    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-1.5 px-4 py-2.5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
            <span class="text-slate-500">Desenvolvido por</span>
            <a href="https://ghinformatica.com.br" target="_blank" rel="noopener"
                class="font-semibold text-brand-700 hover:text-brand-600 hover:underline">
                GH Informática
            </a>
            <span class="font-mono text-slate-600 tabular">29.426.759/0001-12</span>
        </p>

        <a href="https://www.instagram.com/ghinformatica/" target="_blank" rel="noopener"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition-colors hover:border-brand-300 hover:text-brand-700"
            aria-label="Instagram da GH Informática" title="Instagram da GH Informática">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                aria-hidden="true">
                <rect x="3.5" y="3.5" width="17" height="17" rx="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none" />
            </svg>
            <span class="sr-only">Instagram da GH Informática</span>
        </a>
    </div>
</footer>
