{{-- Training Manager dashboard: ER forms & ID cards to sign. Data: DashboardService::trainingManager() --}}
@php
    $Fm = $d['forms']; $C = $d['cards'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $toSign = $Fm['to_sign'] + $C['pending'];
@endphp
<div class="content">
    @include('livewire.admin.dashboard.partials.shared.head', ['title' => 'Training Manager Dashboard'])

    @include('livewire.admin.dashboard.partials.shared.welcome', [
        'user' => $d['user'],
        'line' => e(optional($d['institute'])->name) . '<br>You have <a href="' . e(route('admin.onboarding.enrollment')) . '" class="text-primary text-decoration-underline">' . $toSign . '</a> ER forms and ID cards waiting for your signature.',
        'actions' => [['ER & ID Cards', 'admin.onboarding.enrollment', 'ti ti-id-badge-2', 'btn-primary'], ['All Students', 'admin.students', 'ti ti-school', 'btn-secondary']],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="row">
        @foreach([
            ['ti ti-printer', 'info', 'ER Forms to Print', $Fm['to_print'], '', 'Open queue', 'admin.onboarding.enrollment'],
            ['ti ti-signature', 'warning', 'ER Forms to Sign', $Fm['to_sign'], '', 'Open queue', 'admin.onboarding.enrollment'],
            ['ti ti-archive', 'secondary', 'Forms to Archive', $Fm['to_archive'], '<span class="text-muted">' . $Fm['archived'] . ' archived</span>', 'Open queue', 'admin.onboarding.enrollment'],
            ['ti ti-id-badge-2', 'danger', 'ID Cards to Sign', $C['pending'], '', 'Open queue', 'admin.onboarding.enrollment'],
            ['ti ti-circle-check', 'success', 'Cards Issued', $C['issued_month'], '<span class="text-muted">this month</span>', 'Students', 'admin.students'],
            ['ti ti-user-check', 'primary', 'Signed by You', $C['signed_by_me'], '<span class="text-muted">ID cards</span>', '', ''],
        ] as [$tIcon, $tTone, $tLabel, $tValue, $tExtra, $tLink, $tRoute])
            @include('livewire.admin.dashboard.partials.shared.tile', ['icon' => $tIcon, 'tone' => $tTone, 'label' => $tLabel, 'value' => $tValue, 'extra' => $tExtra, 'link' => $tLink, 'route' => $tRoute, 'col' => 'col-xl-2 col-md-4'])
        @endforeach
    </div>

    <div class="row">
        {{-- Awaiting signature --}}
        <div class="col-xxl-5 col-xl-6 d-flex">
            <div class="card flex-fill">
                <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                    <h5 class="mb-2">Students Awaiting ID Card</h5>
                    <a href="{{ route('admin.onboarding.enrollment') }}" class="btn btn-light btn-md mb-2">View All</a>
                </div>
                <div class="card-body">
                    @forelse($d['awaiting'] as $s)
                        @php $form = $s->erRequest; $card = $s->idCard; @endphp
                        <div class="d-flex align-items-center justify-content-between mb-3 p-2 border br-5">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar flex-shrink-0 rounded-circle bg-primary-transparent text-primary fw-semibold">{{ $initials($s->full_name) }}</span>
                                <div class="ms-2 overflow-hidden">
                                    <h6 class="fs-14 fw-medium text-truncate"><a href="{{ route('admin.onboarding.enrollment.student', $s->id) }}">{{ $s->full_name }}</a></h6>
                                    <p class="fs-13 mb-0 text-truncate"><span class="font-monospace">{{ $s->er_number }}</span> · {{ optional($s->course)->code }}</p>
                                </div>
                            </div>
                            <div class="d-flex gap-1 flex-wrap justify-content-end ms-2">
                                <span class="badge {{ optional($form)->status === 'archived' ? 'badge-success' : 'badge-warning' }} badge-xs">Form: {{ ucfirst(optional($form)->status ?? '—') }}</span>
                                <span class="badge {{ optional($card)->print_count ? 'badge-info' : 'badge-secondary' }} badge-xs">Card: {{ optional($card)->print_count ? 'printed' : 'not printed' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4"><i class="ti ti-circle-check text-success fs-1 d-block mb-1"></i><p class="text-muted mb-0">No students waiting for an ID card.</p></div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Recent signatures --}}
        @include('livewire.admin.dashboard.partials.shared.activity', ['entries' => $d['recent'], 'col' => 'col-xxl-4 col-xl-6'])

        @include('livewire.admin.dashboard.partials.shared.notifications', ['notifications' => $d['notifications'], 'col' => 'col-xxl-3 col-xl-12'])
    </div>
</div>
