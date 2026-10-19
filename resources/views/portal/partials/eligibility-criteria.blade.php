{{-- Course eligibility (12th) of the chosen course. Params: criteria (EligibilityService::criteriaFor), compact=false --}}
@if($criteria->isNotEmpty())
    <div class="elig-card">
        <div class="row-between">
            <span style="font-weight: 600;">Eligibility (12th)</span>
            <span class="badge b-info">{{ $criteria->count() === 1 ? 'One accepted stream' : $criteria->pluck('group.code')->unique()->count() . ' accepted streams' }}</span>
        </div>
        <span class="hint">You need one of these in Class 12. Minimum marks per subject{{ $criteria->contains(fn ($c) => $c->min_aggregate !== null) ? ' and an average' : '' }}:</span>
        <div class="elig-list">
            @foreach($criteria as $criterion)
                <div class="elig-item">
                    <div class="row-between">
                        <span><strong>{{ optional($criterion->group)->code }}</strong> <span class="muted">· {{ optional($criterion->group)->name }}</span></span>
                        <span class="badge b-muted">{{ $criterion->board_label }}</span>
                    </div>
                    <div class="elig-subjects">
                        @foreach($criterion->subjects as $s)
                            <span class="elig-pill">{{ optional($s->subject)->name }} <b>≥ {{ rtrim(rtrim(number_format($s->min_percent, 2), '0'), '.') }}%</b></span>
                        @endforeach
                        @if($criterion->min_aggregate !== null)
                            <span class="elig-pill elig-pill-agg">Average <b>≥ {{ rtrim(rtrim(number_format($criterion->min_aggregate, 2), '0'), '.') }}%</b></span>
                        @endif
                    </div>
                    @if($criterion->remarks)<span class="hint">{{ $criterion->remarks }}</span>@endif
                </div>
            @endforeach
        </div>
        <span class="hint">CGPA is converted to a percentage using your board's factor (usually CGPA × 9.5).</span>
    </div>
@endif
