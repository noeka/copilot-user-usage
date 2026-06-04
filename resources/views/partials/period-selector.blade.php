<div style="display:flex; gap:4px; flex-wrap:wrap; margin-bottom:20px;">
    @foreach(\App\Services\Period::cases() as $p)
        <a href="{{ request()->fullUrlWithQuery(['period' => $p->value]) }}"
           class="btn btn-sm {{ $period === $p ? 'badge-blue' : '' }}"
           style="{{ $period === $p ? 'border-color:#58a6ff; color:#58a6ff;' : '' }}">
            {{ $p->label() }}
        </a>
    @endforeach
</div>
