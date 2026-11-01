{{-- Course eligibility (12th) of the chosen course — one summary line, details folded.
     Criteria with the same stream and the same minimum marks are merged, their boards listed together.
     Params: criteria (EligibilityService::criteriaFor) --}}
@if($criteria->isNotEmpty())
    @php
        $num = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
        $rows = $criteria->groupBy(function ($c) use ($num) {
            return optional($c->group)->code . '|' . $c->subjects->map(fn ($s) => $s->qualifying_subject_id . ':' . $num($s->min_percent))->sort()->implode(',')
                . '|' . ($c->min_aggregate === null ? '' : $num($c->min_aggregate)) . '|' . $c->remarks;
        })->map(fn ($same) => [
            'criterion' => $same->first(),
            'boards' => $same->pluck('board_label')->unique()->values(),
        ])->sortBy(fn ($r) => optional($r['criterion']->group)->code)->values();
        $streams = $rows->map(fn ($r) => optional($r['criterion']->group)->code)->unique()->values();
        $mins = $criteria->flatMap(fn ($c) => $c->subjects->pluck('min_percent'))->map(fn ($v) => (float) $v)->unique();
        $minText = $mins->isEmpty() ? null : ($mins->count() === 1 ? 'min ' . $num($mins->first()) . '% per subject' : 'min ' . $num($mins->min()) . '–' . $num($mins->max()) . '% per subject');
    @endphp
    <details class="elig-card" wire:ignore.self>
        <summary class="elig-head">
            <span class="ico" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 8 L10 4.5 L17.5 8 L10 11.5 Z"></path><path d="M5.5 9.5 V13 C6.9 14.4 8.4 15 10 15 S13.1 14.4 14.5 13 V9.5"></path></svg></span>
            <b>Eligibility (12th)</b>
            <span class="elig-streams">@foreach($streams as $code)<span class="elig-code">{{ $code }}</span>@endforeach</span>
            @if($minText)<span class="hint">{{ $minText }}</span>@endif
            <span class="elig-toggle"><span class="when-closed">View criteria</span><span class="when-open">Hide</span></span>
        </summary>
        <div class="elig-list">
            @foreach($rows as $row)
                @php $criterion = $row['criterion']; @endphp
                <div class="elig-item">
                    <span class="elig-code" title="{{ optional($criterion->group)->name }}">{{ optional($criterion->group)->code }}</span>
                    <div class="elig-subjects">
                        @foreach($criterion->subjects as $s)
                            <span class="elig-pill">{{ optional($s->subject)->name }} <b>≥ {{ $num($s->min_percent) }}%</b></span>
                        @endforeach
                        @if($criterion->min_aggregate !== null)
                            <span class="elig-pill elig-pill-agg">Average <b>≥ {{ $num($criterion->min_aggregate) }}%</b></span>
                        @endif
                        <span class="elig-board">· {{ $row['boards']->implode(', ') }}</span>
                        @if($criterion->remarks)<span class="elig-board">· {{ $criterion->remarks }}</span>@endif
                    </div>
                </div>
            @endforeach
            <span class="hint">Any one stream is enough. CGPA is converted to % with your board's factor (usually × 9.5).</span>
        </div>
    </details>
@endif
