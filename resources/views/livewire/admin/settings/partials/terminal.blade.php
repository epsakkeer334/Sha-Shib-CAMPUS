{{--
    macOS-style terminal window.
    $title   window title
    $lines   array of output lines (null = waiting)
    $status  null | 'ok' | 'fail' | 'static'
    $running [wire:target => command shown while it runs]   (optional)
--}}
@php
    $paint = function (string $line): string {
        if (str_starts_with($line, '$ ')) {
            return '<span class="t-prompt">$</span> <span class="t-cmd">' . e(substr($line, 2)) . '</span>';
        }
        if (str_starts_with($line, '# ')) {
            return '<span class="t-comment">' . e($line) . '</span>';
        }
        if (preg_match('/^(Error|Stopped|Could not)/', $line)) {
            return '<span class="t-err">' . e($line) . '</span>';
        }
        $html = e($line);
        $html = preg_replace('/^(\s*)(INFO|WARN|ERROR)\b/', '$1<span class="t-badge t-$2">$2</span>', $html);
        $html = preg_replace('/\bDONE\b/', '<span class="t-ok">DONE</span>', $html);
        $html = preg_replace('/\bFAIL(ED)?\b/', '<span class="t-err">$0</span>', $html);

        return preg_replace('/(\d+(?:\.\d+)?ms)/', '<span class="t-dim">$1</span>', $html);
    };
    $running = $running ?? [];
    $targets = implode(',', array_keys($running));
@endphp
<div class="ss-term-win">
    <div class="ss-term-bar">
        <span class="dots" aria-hidden="true"><i class="r"></i><i class="y"></i><i class="g"></i></span>
        <span class="ttl">{{ $title }}</span>
        <span class="ms-auto d-inline-flex align-items-center gap-2">
            @if($targets)
                <span class="ss-term-status run" wire:loading.flex wire:target="{{ $targets }}"><span class="pulse"></span> running</span>
            @endif
            @if($status !== 'static')
                <span class="ss-term-status {{ $status === 'ok' ? 'ok' : ($status === 'fail' ? 'fail' : 'idle') }}" @if($targets) wire:loading.remove wire:target="{{ $targets }}" @endif>
                    {{ $status === 'ok' ? 'done' : ($status === 'fail' ? 'failed' : 'idle') }}
                </span>
            @endif
            <button type="button" class="ss-term-copy" data-ss-copy title="Copy" aria-label="Copy output"><i class="ti ti-copy"></i></button>
        </span>
    </div>
    @php
        $body = $lines === null
            ? '<span class="t-wait"><span class="t-prompt">$</span> waiting for a command…<span class="t-cursor"></span></span>'
            : implode("\n", array_map($paint, $lines)) . ($status === 'static' ? '' : "\n" . '<span class="t-prompt">$</span> <span class="t-cursor"></span>');
        if ($targets) {
            $html = '<span wire:loading.remove wire:target="' . e($targets) . '">' . $body . '</span>';
            foreach ($running as $target => $command) {
                $html .= '<span wire:loading wire:target="' . e($target) . '"><span class="t-prompt">$</span> <span class="t-cmd">php artisan ' . e($command) . '</span>'
                    . "\n" . '<span class="t-dim">running…</span><span class="t-cursor"></span></span>';
            }
            $body = $html;
        }
    @endphp
    <pre class="ss-term-body" data-ss-copy-src>{!! $body !!}</pre>
</div>
