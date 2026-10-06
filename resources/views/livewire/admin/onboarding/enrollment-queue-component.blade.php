@php
    $cards = [
        'all' => ['ER numbers issued', $counts['all'], 'ti ti-id-badge-2', 'all', $counts['issuedThisMonth'] . ' this month'],
        'form' => ['ER forms pending', $counts['form'], 'ti ti-file-pencil', 'warn', $counts['formToSign'] . ' awaiting TM signature'],
        'card' => ['ID cards to issue', $counts['card'], 'ti ti-id', 'info', 'Print, sign & hand over'],
        'done' => ['Completed', $counts['done'], 'ti ti-circle-check', 'ok', 'Form archived · card issued'],
    ];
    $formStages = \App\Http\Livewire\Admin\Onboarding\EnrollmentQueueComponent::FORM_STAGES;
    $cardStages = \App\Http\Livewire\Admin\Onboarding\EnrollmentQueueComponent::CARD_STAGES;
    $formOrder = array_keys($formStages);
@endphp
<div class="content er-ui er-queue">
    {{-- Header --}}
    <div class="er-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">ER &amp; ID cards</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Student Onboarding</li>
                    <li class="breadcrumb-item active">ER &amp; ID cards</li>
                </ol>
            </nav>
            <div class="text-muted small">Print and archive ER request forms, then print, sign and issue student ID cards.</div>
        </div>
        @if($counts['form'] + $counts['card'])
            <button type="button" class="er-hero-cta" wire:click="$set('tab', '{{ $counts['form'] ? 'form' : 'card' }}')">
                <span class="er-pulse"></span>
                <span><strong>{{ $counts['form'] }}</strong> forms · <strong>{{ $counts['card'] }}</strong> cards pending</span>
                <i class="ti ti-arrow-right"></i>
            </button>
        @else
            <span class="er-hero-done"><i class="ti ti-circle-check"></i> Everything is up to date</span>
        @endif
    </div>

    {{-- Status cards --}}
    <div class="er-cards mb-3">
        @foreach($cards as $key => [$label, $count, $icon, $tone, $hint])
            <button type="button" wire:click="$set('tab', '{{ $key }}')" class="er-card er-card-{{ $tone }} {{ $tab === $key ? 'is-active' : '' }}"
                    aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">
                <span class="er-card-top">
                    <span class="er-card-label">{{ $label }}</span>
                    <span class="er-card-icon"><i class="{{ $icon }}"></i></span>
                </span>
                <span class="er-card-count">{{ number_format($count) }}</span>
                <span class="er-card-hint text-truncate">{{ $hint }}</span>
            </button>
        @endforeach
    </div>

    <div class="er-panel">
        {{-- Filters --}}
        <div class="er-filters">
            <div class="er-search">
                <i class="ti ti-search"></i>
                <input type="search" class="form-control form-control-sm" placeholder="Name, ER number or phone" wire:model.debounce.400ms="search" aria-label="Search">
            </div>
            @if($isSuperAdmin)
                <select class="form-select form-select-sm" wire:model="institute" aria-label="Institute">
                    <option value="">All institutes</option>
                    @foreach($institutes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            @endif
            <select class="form-select form-select-sm" wire:model="course" aria-label="Course">
                <option value="">All courses</option>
                @foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm" wire:model="formStage" aria-label="ER form stage">
                <option value="">ER form: any</option>
                @foreach($formStages as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm" wire:model="cardStage" aria-label="ID card stage">
                <option value="">ID card: any</option>
                @foreach($cardStages as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <div class="er-dates" title="ER issued between">
                <input type="date" class="form-control form-control-sm" wire:model="issuedFrom" aria-label="ER issued from">
                <span class="text-muted small">to</span>
                <input type="date" class="form-control form-control-sm" wire:model="issuedTo" aria-label="ER issued to">
            </div>
            @if($hasFilters)
                <button type="button" class="btn btn-sm btn-light" wire:click="clearFilters"><i class="ti ti-x me-1"></i> Clear</button>
            @endif
        </div>

        <div class="er-panel-head">
            <div class="er-panel-title">{{ $cards[$tab][0] ?? 'Students' }} <span class="er-count">{{ number_format($students->total()) }}</span></div>
            <span class="small text-muted"><span class="er-legend"></span> Needs action — shown first</span>
        </div>

        <div class="table-responsive position-relative">
            <div class="er-loading" wire:loading.delay.flex wire:target="tab, search, institute, course, formStage, cardStage, issuedFrom, issuedTo, clearFilters, gotoPage, nextPage, previousPage">
                <span class="spinner-border spinner-border-sm text-secondary"></span>
            </div>
            <table class="table align-middle mb-0 er-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        @if($isSuperAdmin)<th>Institute</th>@endif
                        <th>Course</th>
                        <th>ER number</th>
                        <th>ER form</th>
                        <th>ID card</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $item)
                        @php
                            $form = $item->erRequest;
                            $card = $item->idCard;
                            $needs = \App\Http\Livewire\Admin\Onboarding\EnrollmentQueueComponent::needsAction($item);
                            $formStep = $form ? array_search($form->status, $formOrder, true) : -1;
                            $formTone = match (optional($form)->status) { 'archived' => 'ok', 'signed' => 'info', 'printed' => 'warn', default => 'muted' };
                            [$cardLabel, $cardTone, $cardIcon] = match (true) {
                                !$card => ['—', 'muted', 'ti ti-minus'],
                                $card->tm_signature_status === 'physically_signed' => ['Issued', 'ok', 'ti ti-circle-check'],
                                $card->status === 'reprinted' => ['Reprint — to sign', 'warn', 'ti ti-refresh'],
                                $card->print_count > 0 => ['Printed — to sign', 'info', 'ti ti-signature'],
                                default => ['To print', 'muted', 'ti ti-printer'],
                            };
                            $photo = $item->documents->where('document_type', 'kyc_photo')->sortByDesc('uploaded_at')->first();
                        @endphp
                        <tr class="{{ $needs ? 'er-row-needs' : '' }}" wire:key="er-{{ $item->id }}">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($photo && $photo->is_image)
                                        <img src="{{ route('admin.students.documents.show', $photo->id) }}" class="er-avatar er-avatar-img" alt="" loading="lazy">
                                    @else
                                        <span class="er-avatar">{{ $item->initials }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.onboarding.enrollment.student', $item->id) }}" class="er-name er-ellipsis">{{ $item->full_name }}</a>
                                        <div class="er-sub"><i class="ti ti-phone"></i> {{ $item->phone ?: '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            @if($isSuperAdmin)
                                <td>
                                    <div class="small fw-medium er-ellipsis" style="max-width: 150px;">{{ optional($item->institute)->name }}</div>
                                    @if(optional($item->institute)->code)<span class="er-inst-code">{{ $item->institute->code }}</span>@endif
                                </td>
                            @endif
                            <td>
                                <span class="er-code">{{ optional($item->course)->code }}</span>
                                <div class="er-sub er-ellipsis" style="max-width: 150px;" title="{{ optional($item->course)->name }}">{{ optional($item->course)->name }}</div>
                            </td>
                            <td>
                                <span class="er-badge" title="ER number"><i class="ti ti-id-badge-2"></i>{{ $item->er_number }}</span>
                                <div class="er-sub mt-1" style="color: #15803D;"><i class="ti ti-circle-check"></i> Issued {{ optional(optional($form)->generated_at)->format('d M Y') ?? '—' }}</div>
                            </td>
                            <td>
                                <div class="er-steps" title="Generated → Printed → TM signed → Archived">
                                    @foreach($formOrder as $i => $stage)
                                        <span class="er-step {{ $i <= $formStep ? 'is-done er-step-' . $formTone : '' }}"></span>
                                    @endforeach
                                </div>
                                <span class="er-chip er-chip-{{ $formTone }}">{{ $form ? $formStages[$form->status] ?? ucfirst($form->status) : '—' }}</span>
                            </td>
                            <td>
                                <span class="er-chip er-chip-{{ $cardTone }}"><i class="{{ $cardIcon }}"></i> {{ $cardLabel }}</span>
                                <div class="er-sub mt-1">
                                    @if(optional($card)->issue_date) {{ $card->issue_date->format('d M Y') }} @endif
                                    @if(optional($card)->print_count) · printed {{ $card->print_count }}× @endif
                                </div>
                            </td>
                            <td>{!! $item->status_html !!}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.students.er-form', $item->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="ER request form"><i class="ti ti-file-text"></i></a>
                                <a href="{{ route('admin.students.id-card', $item->id) }}" target="_blank" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="ID card"><i class="ti ti-id"></i></a>
                                <a href="{{ route('admin.onboarding.enrollment.student', $item->id) }}" class="btn btn-sm {{ $needs ? 'btn-primary' : 'btn-outline-primary' }}">Open <i class="ti ti-chevron-right"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isSuperAdmin ? 8 : 7 }}" class="er-empty-row"><i class="ti ti-id-badge-off"></i><div>{{ $hasFilters ? 'No students match these filters.' : 'No ER numbers issued yet.' }}</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())<div class="er-panel-foot">{{ $students->links() }}</div>@endif
    </div>

    @include('livewire.admin.onboarding.partials.enrollment-styles')
    <style>
        .er-queue .er-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--er-soft); background: #FCFCFD; }
        .er-queue .er-filters .form-select { width: auto; min-width: 140px; max-width: 220px; border-radius: 8px; }
        .er-queue .er-search { position: relative; width: 240px; max-width: 100%; }
        .er-queue .er-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .er-queue .er-search input { padding-left: 32px; border-radius: 8px; }
        .er-queue .er-dates { display: flex; align-items: center; gap: 6px; }
        .er-queue .er-dates input { width: 140px; border-radius: 8px; }
        .er-queue .er-legend { display: inline-block; width: 10px; height: 10px; border-radius: 3px; background: #FFFBEB; border-left: 3px solid #F59E0B; vertical-align: -1px; margin-right: 4px; }
        .er-queue .er-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--er-muted); border-bottom: 1px solid var(--er-border); padding: 10px 14px; white-space: nowrap; }
        .er-queue .er-table td { padding: 12px 14px; border-color: var(--er-soft); font-size: 14px; }
        .er-queue .er-table tbody tr:last-child td { border-bottom: 0; }
        .er-queue .er-table tbody tr:hover td { background: #FAFAFB; }
        .er-queue .er-row-needs td, .er-queue .er-row-needs:hover td { background: #FFFBEB; }
        .er-queue .er-row-needs td:first-child { box-shadow: inset 3px 0 0 #F59E0B; }
        .er-queue .er-row-needs .er-name { font-weight: 700; }
        .er-queue .er-avatar { width: 38px; height: 38px; border-radius: 50%; background: #FEF0E7; color: var(--er-accent); font-weight: 600; font-size: 12.5px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .er-queue .er-avatar-img { object-fit: cover; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--er-border); }
        .er-queue .er-name { font-weight: 600; color: var(--er-ink); max-width: 190px; }
        .er-queue a.er-name:hover { color: var(--er-accent); }
        .er-queue .er-steps { display: flex; gap: 3px; margin-bottom: 5px; }
        .er-queue .er-step { width: 18px; height: 4px; border-radius: 2px; background: #E5E7EB; }
        .er-queue .er-step.is-done { background: #9CA3AF; }
        .er-queue .er-step.er-step-ok { background: #22C55E; }
        .er-queue .er-step.er-step-info { background: #0EA5E9; }
        .er-queue .er-step.er-step-warn { background: #F59E0B; }
        .er-queue .er-loading { display: none; position: absolute; inset: 0; z-index: 2; background: rgba(255, 255, 255, .6); align-items: center; justify-content: center; }
        .er-queue .er-empty-row { text-align: center; color: var(--er-muted); padding: 48px 12px !important; }
        .er-queue .er-empty-row i { font-size: 36px; color: #D1D5DB; display: block; margin-bottom: 6px; }
        @media (max-width: 575.98px) { .er-queue .er-search, .er-queue .er-filters .form-select { width: 100%; max-width: none; } }
    </style>
</div>
