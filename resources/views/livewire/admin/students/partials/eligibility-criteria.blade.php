{{-- Course eligibility (12th) of the chosen course. Params: criteria (EligibilityService::criteriaFor) --}}
@if($criteria->isNotEmpty())
    <div class="el-card mt-3">
        <div class="el-card-head">
            <span><i class="ti ti-school"></i> Course eligibility (12th)</span>
            <small class="text-muted">One of these streams is required · CGPA × board factor = %</small>
        </div>
        <div class="el-crit-list">
            @foreach($criteria as $criterion)
                <div class="el-crit">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="el-group">{{ optional($criterion->group)->code }}</span>
                        <span class="small text-muted">{{ optional($criterion->group)->name }}</span>
                        <span class="el-board">{{ $criterion->board_label }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($criterion->subjects as $s)
                            <span class="el-pill">{{ optional($s->subject)->name }} <b>≥ {{ rtrim(rtrim(number_format($s->min_percent, 2), '0'), '.') }}%</b></span>
                        @endforeach
                        @if($criterion->min_aggregate !== null)
                            <span class="el-pill el-pill-agg">Average <b>≥ {{ rtrim(rtrim(number_format($criterion->min_aggregate, 2), '0'), '.') }}%</b></span>
                        @endif
                    </div>
                    @if($criterion->remarks)<div class="small text-muted mt-1">{{ $criterion->remarks }}</div>@endif
                </div>
            @endforeach
        </div>
    </div>
@endif
