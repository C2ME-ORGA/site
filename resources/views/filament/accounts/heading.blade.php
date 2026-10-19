<div style="display:flex;align-items:center;gap:12px;">
    @if ($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt=""
            width="40"
            height="40"
            loading="lazy"
            decoding="async"
            style="width:40px;height:40px;border-radius:6px;object-fit:contain;background:#fff;box-shadow:0 0 0 1px rgba(0,0,0,.06);"
        />
    @endif

    <span>{{ $title }}</span>
</div>
