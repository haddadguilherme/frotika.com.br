<section
    {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] border border-slate-200 bg-white p-6 shadow-sm']) }}>
    {{ $slot }}
</section>
