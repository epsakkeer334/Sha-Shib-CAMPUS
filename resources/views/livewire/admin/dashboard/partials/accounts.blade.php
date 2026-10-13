{{-- Accounts dashboard: fees & payments of the own institute. Data: DashboardService::accounts() --}}
@php
    $F = $d['fees']; $A = $d['admissions'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<div class="content">
    @include('livewire.admin.dashboard.partials.shared.head', ['title' => 'Accounts Dashboard'])

    @include('livewire.admin.dashboard.partials.shared.welcome', [
        'user' => $d['user'],
        'line' => e(optional($d['institute'])->name) . '<br>You have <a href="' . e(route('admin.onboarding.payments', ['tab' => 'pending'])) . '" class="text-primary text-decoration-underline">' . $F['to_verify'] . '</a> payments to verify'
            . ' (' . e(money_inr($F['to_verify_amount'], false)) . ') &amp; <span class="text-danger">' . $F['overdue_count'] . '</span> overdue fees.',
        'actions' => [['Payment Verification', 'admin.onboarding.payments', 'ti ti-cash', 'btn-primary'], ['Fee Structure', 'admin.onboarding.fee-structure', 'ti ti-receipt-2', 'btn-secondary']],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="row">
        @foreach([
            ['ti ti-clock-dollar', 'warning', 'Payments to Verify', $F['to_verify'], '<span class="text-muted">' . e(money_inr($F['to_verify_amount'], false)) . '</span>', 'Verify now', 'admin.onboarding.payments'],
            ['ti ti-cash', 'success', 'Collected Today', money_inr($F['collected_today'], false), '', 'Payments', 'admin.onboarding.payments'],
            ['ti ti-calendar-dollar', 'primary', 'Collected This Month', money_inr($F['collected_month'], false), '<span class="text-muted">' . $F['receipts_month'] . ' receipts</span>', 'Payments', 'admin.onboarding.payments'],
            ['ti ti-currency-rupee', 'danger', 'Outstanding Fees', money_inr($F['outstanding'], false), $F['overdue'] > 0 ? '<span class="text-danger">' . e(money_inr($F['overdue'], false)) . ' overdue</span>' : '<span class="text-success">nothing overdue</span>', 'Students', 'admin.students'],
            ['ti ti-shield-check', 'info', 'Fee Gate Pending', $A['fees_pending'], '<span class="text-muted">students</span>', 'Fee gate', 'admin.onboarding.payments'],
            ['ti ti-school', 'secondary', 'Students', number_format($A['students']), '<span class="text-success">+' . $A['this_month'] . ' this month</span>', 'View All', 'admin.students'],
        ] as [$tIcon, $tTone, $tLabel, $tValue, $tExtra, $tLink, $tRoute])
            @include('livewire.admin.dashboard.partials.shared.tile', ['icon' => $tIcon, 'tone' => $tTone, 'label' => $tLabel, 'value' => $tValue, 'extra' => $tExtra, 'link' => $tLink, 'route' => $tRoute, 'col' => 'col-xl-2 col-md-4'])
        @endforeach
    </div>

    <div class="row">
        {{-- Collection chart --}}
        <div class="col-xl-7 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Collections</h5>
                    <span class="btn btn-white border btn-sm d-inline-flex align-items-center mb-2"><i class="ti ti-calendar me-1"></i>Last 6 months</span>
                </div>
                <div class="card-body pb-0">
                    <p class="fs-13 text-gray-9 mb-1"><i class="ti ti-square-filled me-2 text-primary"></i>Confirmed payments (₹)</p>
                    <div id="dash-collection"></div>
                </div>
            </div>
        </div>

        {{-- Payments to verify --}}
        <div class="col-xl-5 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Waiting for Verification</h5>
                    <a href="{{ route('admin.onboarding.payments') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body">
                    @forelse($d['toVerify'] as $p)
                        <div class="d-flex align-items-center justify-content-between mb-3 p-2 border border-dashed br-5">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar flex-shrink-0 rounded-circle bg-warning-transparent text-warning fw-semibold">{{ $initials(optional($p->student)->full_name) }}</span>
                                <div class="ms-2 overflow-hidden">
                                    <h6 class="fs-14 fw-medium text-truncate">{{ optional($p->student)->full_name }}</h6>
                                    <p class="fs-13 text-truncate mb-0">{{ optional($p->due)->fee_head }} · {{ optional($p->gateway)->name }} · {{ $p->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <span class="fw-semibold text-nowrap ms-2">{{ money_inr($p->amount, false) }}</span>
                        </div>
                    @empty
                        <div class="text-center py-4"><i class="ti ti-circle-check text-success fs-1 d-block mb-1"></i><p class="text-muted mb-0">Nothing to verify. You're all caught up.</p></div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Recent confirmed payments --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Recent Receipts</h5>
                    <a href="{{ route('admin.onboarding.payments', ['tab' => 'approved']) }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-nowrap mb-0">
                            <thead><tr><th>Student</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                @forelse($d['recentPayments'] as $p)
                                    <tr>
                                        <td class="{{ $loop->last ? 'border-0' : '' }}">
                                            <h6 class="fw-medium mb-0">{{ \Illuminate\Support\Str::limit(optional($p->student)->full_name, 24) }}</h6>
                                            <span class="fs-12 text-muted font-monospace">{{ $p->receipt_number }}</span> <span class="fs-12">· {{ optional($p->verified_at)->format('d M') }}</span>
                                        </td>
                                        <td class="text-end fw-semibold {{ $loop->last ? 'border-0' : '' }}">{{ money_inr($p->amount, false) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-muted py-4">No confirmed payments yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overdue fees --}}
        <div class="col-xxl-4 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Overdue Fees</h5>
                    <span class="badge badge-danger mb-2">{{ $F['overdue_count'] }}</span>
                </div>
                <div class="card-body">
                    @forelse($d['overdueDues'] as $due)
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar flex-shrink-0 rounded-circle bg-danger-transparent text-danger fw-semibold">{{ $initials(optional($due->student)->full_name) }}</span>
                                <div class="ms-2 overflow-hidden">
                                    <p class="text-dark fw-medium text-truncate mb-0">
                                        @if($due->student)<a href="{{ route('admin.students.fees', $due->student->id) }}">{{ $due->student->full_name }}</a>@endif
                                    </p>
                                    <span class="fs-12">{{ $due->fee_head }} · due {{ $due->due_date->format('d M') }} ({{ $due->due_date->diffForHumans(null, true) }} late)</span>
                                </div>
                            </div>
                            <span class="fw-semibold text-danger text-nowrap ms-2">{{ money_inr($due->balance, false) }}</span>
                        </div>
                    @empty
                        <div class="text-center py-4"><i class="ti ti-mood-happy text-success fs-1 d-block mb-1"></i><p class="text-muted mb-0">No overdue fees.</p></div>
                    @endforelse
                </div>
            </div>
        </div>

        @include('livewire.admin.dashboard.partials.shared.notifications', ['notifications' => $d['notifications'], 'col' => 'col-xxl-4 col-xl-12'])
    </div>

    <script>
        window.addEventListener('load', function () {
            if (typeof ApexCharts === 'undefined') { return; }
            const rows = @json($d['collection']);
            new ApexCharts(document.querySelector('#dash-collection'), {
                chart: { type: 'bar', height: 290, toolbar: { show: false } },
                series: [{ name: 'Collected', data: rows.map(r => r.amount) }],
                xaxis: { categories: rows.map(r => r.label) }, colors: ['#F26522'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } }, dataLabels: { enabled: false },
                yaxis: { labels: { formatter: v => '₹' + Math.round(v).toLocaleString('en-IN') } },
                tooltip: { y: { formatter: v => '₹' + Number(v).toLocaleString('en-IN') } },
                grid: { borderColor: '#E5E7EB', strokeDashArray: 4 }
            }).render();
        });
    </script>
</div>
