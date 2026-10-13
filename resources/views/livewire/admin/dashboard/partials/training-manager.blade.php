{{-- Training Manager dashboard: ER forms & ID cards to sign. Data: DashboardService::trainingManager() --}}
@php
    $Fm = $d['forms']; $C = $d['cards'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $formTone = ['generated' => 'sky', 'printed' => 'amber', 'signed' => 'violet', 'archived' => 'green'];
@endphp
<div class="content db-ui">
    @include('livewire.admin.dashboard.partials.shared.hero', [
        'title' => 'Training Manager Dashboard', 'user' => $d['user'],
        'line' => '<i class="ti ti-building"></i> ' . e(optional($d['institute'])->name) . ' · ER forms &amp; ID cards',
        'actions' => [['All Students', 'admin.students', 'ti ti-school', false], ['ER & ID Cards', 'admin.onboarding.enrollment', 'ti ti-id-badge-2', true]],
    ])

    @include('livewire.admin.dashboard.partials.shared.pending', ['pending' => $d['pending']])

    <div class="db-kpis mb-3">
        @foreach([
            ['ER Forms to Sign', $Fm['to_sign'], $Fm['to_print'] . ' still to print', 'ti ti-signature', 'amber', ['formStage' => 'printed']],
            ['ID Cards to Sign', $C['pending'], 'printed or waiting to print', 'ti ti-id-badge-2', 'red', ['cardStage' => 'to_sign']],
            ['Cards Issued', $C['issued_month'], 'this month · ' . $C['signed_by_me'] . ' signed by you', 'ti ti-circle-check', 'green', ['cardStage' => 'issued']],
            ['Forms Archived', $Fm['archived'], $Fm['to_archive'] . ' signed, to archive', 'ti ti-archive', 'slate', ['formStage' => 'archived']],
        ] as [$kLabel, $kValue, $kHint, $kIcon, $kTone, $kParams])
            @include('livewire.admin.dashboard.partials.shared.kpi', ['label' => $kLabel, 'value' => $kValue, 'hint' => $kHint, 'icon' => $kIcon, 'tone' => $kTone, 'route' => 'admin.onboarding.enrollment', 'params' => $kParams])
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="db-panel">
                <div class="db-panel-head">
                    <div class="d-flex align-items-center gap-2"><span class="db-ph-icon"><i class="ti ti-id"></i></span><div><h5>Students Awaiting ID Card</h5><small>ER issued, card not yet signed</small></div></div>
                    <a href="{{ route('admin.onboarding.enrollment') }}" class="db-link">Open queue <i class="ti ti-arrow-right"></i></a>
                </div>
                <div class="db-panel-body pt-1 pb-1">
                    @forelse($d['awaiting'] as $s)
                        @php $form = $s->erRequest; $card = $s->idCard; @endphp
                        <div class="db-row">
                            <span class="db-av tone-indigo">{{ $initials($s->full_name) }}</span>
                            <div class="db-row-main">
                                <div class="db-row-title"><a href="{{ route('admin.onboarding.enrollment.student', $s->id) }}">{{ $s->full_name }}</a></div>
                                <div class="db-row-sub"><span class="font-monospace">{{ $s->er_number }}</span> · {{ optional($s->course)->code }}</div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <span class="db-chip tone-{{ $formTone[optional($form)->status] ?? 'slate' }}">Form: {{ ucfirst(optional($form)->status ?? '—') }}</span>
                                <span class="db-chip {{ optional($card)->print_count ? 'tone-sky' : 'tone-slate' }}">Card: {{ optional($card)->print_count ? 'printed' : 'not printed' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="db-empty is-ok"><i class="ti ti-circle-check"></i>No students waiting for an ID card.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4">@include('livewire.admin.dashboard.partials.shared.activity', ['entries' => $d['recent'], 'title' => 'ER & ID Card Activity'])</div>
        <div class="col-xl-3">@include('livewire.admin.dashboard.partials.shared.notifications', ['notifications' => $d['notifications']])</div>
    </div>

    @include('livewire.admin.dashboard.partials.shared.styles')
</div>
