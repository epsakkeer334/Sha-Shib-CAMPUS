{{-- Accounts dashboard: fees & payments of the own institute. Data: DashboardService::accounts() --}}
@php
    $F = $d['fees']; $A = $d['admissions'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<div class="content db-ui">
    @include('livewire.admin.dashboard.partials.shared.hero', [
        'title' => 'Accounts Dashboard', 'user' => $d['user'],
        'line' => '<i class="ti ti-building"></i> ' . e(optional($d['institute'])->name) . ' · fees, payments &amp; receipts',
        'actions' => [['Fee Structure', 'admin.onboarding.fee-structure', 'ti ti-receipt-2', false], ['Payment Verification', 'admin.onboarding.payments', 'ti ti-cash', true]],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="db-kpis mb-3">
        @foreach([
            ['Payments to Verify', $F['to_verify'], e(money_inr($F['to_verify_amount'], false)) . ' waiting', 'ti ti-clock-dollar', 'amber', 'admin.onboarding.payments', ['tab' => 'pending']],
            ['Collected This Month', money_inr($F['collected_month'], false), $F['receipts_month'] . ' receipts · ' . e(money_inr($F['collected_today'], false)) . ' today', 'ti ti-cash', 'green', 'admin.onboarding.payments', ['tab' => 'approved']],
            ['Outstanding Fees', money_inr($F['outstanding'], false), $F['overdue'] > 0 ? '<span class="text-danger">' . e(money_inr($F['overdue'], false)) . ' overdue (' . $F['overdue_count'] . ')</span>' : 'nothing overdue', 'ti ti-currency-rupee', 'orange', 'admin.students', []],
            ['Fee Gate Pending', $A['fees_pending'], 'students waiting for Gate 2', 'ti ti-shield-check', 'sky', 'admin.onboarding.payments', ['tab' => 'gate']],
        ] as [$kLabel, $kValue, $kHint, $kIcon, $kTone, $kRoute, $kParams])
            @include('livewire.admin.dashboard.partials.shared.kpi', ['label' => $kLabel, 'value' => $kValue, 'hint' => $kHint, 'icon' => $kIcon, 'tone' => $kTone, 'route' => $kRoute, 'params' => $kParams])
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-chart-bar"></i></span><div><h5>Collections</h5><small>Confirmed payments · last 6 months</small></div></div>
                    <span class="db-tag">{{ money_inr(collect($d['collection'])->sum('amount'), false) }} in 6 months</span>
                </div>
                <div class="db-panel-body"><div id="dash-collection"></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-clock-dollar"></i></span><div><h5>Waiting for Verification</h5><small>Oldest first</small></div></div>
                    <a href="{{ route('admin.onboarding.payments', ['tab' => 'pending']) }}" class="db-link">Verify <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body pt-1 pb-1">
                    @forelse($d['toVerify'] as $p)
                        <div class="db-row">
                            <span class="db-av db-av-round tone-amber">{{ $initials(optional($p->student)->full_name) }}</span>
                            <div class="db-row-main"><div class="db-row-title">{{ optional($p->student)->full_name }}</div>
                                <div class="db-row-sub">{{ optional($p->due)->fee_head }} · {{ optional($p->gateway)->name }} · {{ $p->created_at->diffForHumans(null, true) }}</div></div>
                            <span class="db-amount">{{ money_inr($p->amount, false) }}</span>
                        </div>
                    @empty
                        <div class="db-empty is-ok"><i class="ti ti-circle-check"></i>Nothing to verify. You're all caught up.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-receipt"></i></span><div><h5>Recent Receipts</h5><small>Latest confirmed payments</small></div></div>
                    <a href="{{ route('admin.onboarding.payments', ['tab' => 'approved']) }}" class="db-link">View all <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body pt-1 pb-1">
                    @forelse($d['recentPayments'] as $p)
                        <div class="db-row">
                            <span class="db-av db-av-round tone-green"><i class="ti ti-check"></i></span>
                            <div class="db-row-main"><div class="db-row-title">{{ optional($p->student)->full_name }}</div>
                                <div class="db-row-sub"><span class="font-monospace">{{ $p->receipt_number }}</span> · {{ optional($p->verified_at)->format('d M') }}</div></div>
                            <span class="db-amount">{{ money_inr($p->amount, false) }}</span>
                        </div>
                    @empty
                        <div class="db-empty"><i class="ti ti-receipt-off"></i>No confirmed payments yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-alarm"></i></span><div><h5>Overdue Fees</h5><small>{{ $F['overdue_count'] }} {{ \Illuminate\Support\Str::plural('fee', $F['overdue_count']) }} past the due date</small></div></div>
                </div>
                <div class="db-panel-body pt-1 pb-1">
                    @forelse($d['overdueDues'] as $due)
                        <div class="db-row">
                            <span class="db-av db-av-round tone-red">{{ $initials(optional($due->student)->full_name) }}</span>
                            <div class="db-row-main">
                                <div class="db-row-title">@if($due->student)<a href="{{ route('admin.students.fees', $due->student->id) }}">{{ $due->student->full_name }}</a>@endif</div>
                                <div class="db-row-sub">{{ $due->fee_head }} · {{ $due->due_date->diffForHumans(null, true) }} late</div>
                            </div>
                            <span class="db-amount text-danger">{{ money_inr($due->balance, false) }}</span>
                        </div>
                    @empty
                        <div class="db-empty is-ok"><i class="ti ti-mood-happy"></i>No overdue fees.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4">@include('livewire.admin.dashboard.partials.shared.notifications', ['notifications' => $d['notifications']])</div>
    </div>

    @include('livewire.admin.dashboard.partials.shared.styles')

    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') { return; }
            const rows = @json($d['collection']);
            new ApexCharts(document.querySelector('#dash-collection'), {
                chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: 'Collected', data: rows.map(r => r.amount) }],
                xaxis: { categories: rows.map(r => r.label) }, colors: ['#F26522'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '42%' } }, dataLabels: { enabled: false },
                yaxis: { labels: { formatter: v => '₹' + Math.round(v).toLocaleString('en-IN') } },
                tooltip: { y: { formatter: v => '₹' + Number(v).toLocaleString('en-IN') } },
                grid: { borderColor: '#F1F2F4', strokeDashArray: 4 }
            }).render();
        });
    </script>
</div>
