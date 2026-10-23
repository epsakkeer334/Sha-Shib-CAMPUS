<div class="content bu-ui">
    <!-- Header -->
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Bulk Update Students</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.students') }}">Students</a></li>
                    <li class="breadcrumb-item active">Bulk Update</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('admin.students') }}" class="btn btn-light border"><i class="ti ti-arrow-left me-1"></i> Students</a>
    </div>

    <!-- Steps -->
    <div class="bu-steps mb-3">
        @foreach([['Download', 'Template with your students', 'ti ti-download'], ['Edit', 'In Excel — save as CSV', 'ti ti-table'], ['Upload', 'Checked row by row', 'ti ti-upload'], ['Apply', 'Valid rows are saved', 'ti ti-circle-check']] as $i => [$t, $s, $icon])
            <div class="bu-step {{ ($import && $i < 3) || (!$import && $i === 0) || ($import && $import->status === 'applied') ? 'is-on' : '' }}">
                <span class="bu-step-num">{{ $i + 1 }}</span>
                <div><div class="fw-semibold">{{ $t }}</div><small>{{ $s }}</small></div>
                <i class="{{ $icon }} ms-auto"></i>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <!-- Left: template + upload -->
        <div class="col-xl-4">
            <div class="bu-panel mb-3">
                <div class="bu-panel-head"><span class="bu-icon"><i class="ti ti-download"></i></span><div><h6 class="mb-0">1 · Download template</h6><small>Pre-filled with the students you choose</small></div></div>
                <div class="bu-panel-body">
                    <div class="row g-2">
                        @if($isSuperAdmin)
                            <div class="col-12">
                                <select class="form-select form-select-sm" wire:model="institute">
                                    <option value="">All institutes</option>
                                    @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }} ({{ $inst->code }})</option>@endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-12">
                            <select class="form-select form-select-sm" wire:model="course">
                                <option value="">All courses</option>
                                @foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model="batch" @if(!$course) disabled @endif>
                                <option value="">{{ $course ? 'All batches' : 'Batch (choose course)' }}</option>
                                @foreach($batches as $b)<option value="{{ $b->id }}">{{ $b->code }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model="status">
                                <option value="">All statuses</option>
                                @foreach(config('camp.student_statuses') as $key => [$label])<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <button type="button" class="btn btn-primary" wire:click="downloadStudents" @if(!$matching) disabled @endif>
                            <i class="ti ti-file-spreadsheet me-1"></i> Download {{ number_format($matching) }} {{ \Illuminate\Support\Str::plural('student', $matching) }}
                        </button>
                        <button type="button" class="btn btn-light border" wire:click="downloadBlank"><i class="ti ti-file-plus me-1"></i> Blank template (new students)</button>
                    </div>
                    <div class="bu-note mt-3">
                        <i class="ti ti-bulb"></i>
                        <div>Change only what you need — a <b>blank cell keeps</b> the current value. Leave <code>student_id</code> empty for a new student. Masters can be typed by name or code.</div>
                    </div>
                </div>
            </div>

            <div class="bu-panel mb-3">
                <div class="bu-panel-head"><span class="bu-icon bu-icon-up"><i class="ti ti-upload"></i></span><div><h6 class="mb-0">2 · Upload the edited file</h6><small>CSV · up to 1,000 rows · 2 MB</small></div></div>
                <div class="bu-panel-body">
                    <label class="bu-drop @error('file') is-invalid @enderror" for="buFile">
                        <i class="ti ti-cloud-upload"></i>
                        <span class="fw-semibold">Choose the CSV file</span>
                        <small>Nothing is saved yet — you will see a preview first</small>
                        <input id="buFile" type="file" accept=".csv,text/csv" class="d-none" wire:model="file">
                    </label>
                    <div wire:loading wire:target="file" class="small text-muted mt-2"><span class="spinner-border spinner-border-sm me-1"></span> Checking every row…</div>
                    @error('file')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @unless($canEr)<div class="small text-muted mt-2"><i class="ti ti-lock"></i> ER number changes in the file are ignored (no ER permission).</div>@endunless
                </div>
            </div>

            <div class="bu-panel">
                <div class="bu-panel-head"><span class="bu-icon bu-icon-hist"><i class="ti ti-history"></i></span><div><h6 class="mb-0">Import history</h6><small>Last uploads</small></div></div>
                <div class="bu-panel-body p-0">
                    @forelse($history as $h)
                        <button type="button" class="bu-hist {{ $import && $import->id === $h->id ? 'is-on' : '' }}" wire:click="open({{ $h->id }})">
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">#{{ $h->id }} · {{ $h->file_name }}</div>
                                <small class="text-muted">{{ optional($h->user)->name }} · {{ $h->created_at->format('d M, h:i A') }}</small>
                            </div>
                            <span class="bu-st bu-st-{{ $h->status }}">{{ ucfirst($h->status) }}</span>
                        </button>
                    @empty
                        <div class="small text-muted p-3">No uploads yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right: preview -->
        <div class="col-xl-8">
            @if(!$import)
                <div class="bu-panel bu-empty">
                    <i class="ti ti-table-import"></i>
                    <div class="fw-semibold">Upload a file to see the preview</div>
                    <div class="small text-muted mb-3">Each row is checked with the same rules as the student form. You decide what is saved.</div>
                    <details class="bu-guide text-start">
                        <summary><i class="ti ti-list-details me-1"></i> Column guide ({{ count($columns) }} columns)</summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Column</th><th>New student</th><th>Accepted values</th></tr></thead>
                                <tbody>
                                    @foreach($columns as $col => [$label, $required, $help])
                                        <tr><td><code>{{ $col }}</code><div class="small text-muted">{{ $label }}</div></td><td>{!! $required ? '<span class="text-danger fw-semibold">Required</span>' : '<span class="text-muted">Optional</span>' !!}</td><td class="small">{{ $help }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            @else
                <div class="bu-panel">
                    <div class="bu-panel-head flex-wrap">
                        <span class="bu-icon"><i class="ti ti-eye-check"></i></span>
                        <div class="min-w-0">
                            <h6 class="mb-0 text-truncate">3 · Preview — {{ $import->file_name }}</h6>
                            <small>Import #{{ $import->id }} · {{ optional($import->user)->name }} · {{ $import->created_at->format('d M Y, h:i A') }}</small>
                        </div>
                        <span class="bu-st bu-st-{{ $import->status }} ms-auto">{{ ucfirst($import->status) }}</span>
                    </div>
                    <div class="bu-panel-body">
                        @foreach($import->preview['warnings'] ?? [] as $w)<div class="bu-note bu-note-warn mb-2"><i class="ti ti-alert-triangle"></i><div>{{ $w }}</div></div>@endforeach

                        <div class="bu-counts mb-3">
                            @foreach([['all', 'All rows', $counts['total'], 'slate'], ['new', 'New', $counts['new'], 'green'], ['update', 'Updates', $counts['update'], 'blue'], ['unchanged', 'No change', $counts['unchanged'], 'muted'], ['error', 'Errors', $counts['error'], 'red']] as [$key, $label, $n, $tone])
                                <button type="button" class="bu-count bu-tone-{{ $tone }} {{ $show === $key ? 'is-on' : '' }}" wire:click="$set('show', '{{ $key }}')">
                                    <b>{{ $n }}</b><span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>

                        @if($result)
                            <div class="bu-note bu-note-ok mb-3"><i class="ti ti-circle-check"></i>
                                <div><b>Applied:</b> {{ $result['created'] }} created · {{ $result['updated'] }} updated{{ $result['skipped'] ? ' · ' . count($result['skipped']) . ' skipped' : '' }}.
                                    @foreach($result['skipped'] as $line => $why)<div class="small">Row {{ $line }} skipped: {{ $why }}</div>@endforeach
                                    @foreach(array_slice($result['warnings'], 0, 10) as $w)<div class="small text-muted">{{ $w }}</div>@endforeach
                                </div></div>
                        @elseif($import->status === 'applied')
                            @php $r = $import->result ?? []; @endphp
                            <div class="bu-note bu-note-ok mb-3"><i class="ti ti-circle-check"></i><div><b>Applied {{ optional($import->applied_at)->format('d M Y, h:i A') }}:</b> {{ $r['created'] ?? 0 }} created · {{ $r['updated'] ?? 0 }} updated{{ !empty($r['skipped']) ? ' · ' . count($r['skipped']) . ' skipped' : '' }}.</div></div>
                        @endif

                        <div class="table-responsive bu-table-wrap">
                            <table class="table table-sm align-middle mb-0 bu-table">
                                <thead><tr><th style="width: 60px;">Row</th><th style="width: 92px;">Result</th><th>Student</th><th>Changes / messages</th></tr></thead>
                                <tbody>
                                    @forelse($rows->take(300) as $r)
                                        <tr class="bu-row-{{ $r['action'] }}" wire:key="r-{{ $r['line'] }}">
                                            <td class="text-muted">{{ $r['line'] }}</td>
                                            <td><span class="bu-badge bu-badge-{{ $r['action'] }}">{{ ['new' => 'New', 'update' => 'Update', 'unchanged' => 'No change', 'error' => 'Error'][$r['action']] }}</span></td>
                                            <td>
                                                <div class="fw-semibold">{{ $r['label'] ?: '—' }}</div>
                                                <small class="text-muted">{{ $r['student_id'] ? '#' . $r['student_id'] : 'new' }}{{ !empty($r['raw']['er_number']) ? ' · ' . $r['raw']['er_number'] : '' }}</small>
                                            </td>
                                            <td>
                                                @foreach($r['errors'] as $col => $msg)<div class="bu-msg bu-msg-err"><code>{{ $col }}</code> {{ $msg }}</div>@endforeach
                                                @if($r['action'] === 'new' && !$r['errors'])
                                                    <div class="small text-muted">{{ $r['raw']['course_code'] ?? '' }}{{ !empty($r['raw']['batch_code']) ? ' · ' . $r['raw']['batch_code'] : '' }} · {{ $r['er'] ? 'admitted with ER ' . $r['er'] : 'draft (onboarding)' }}</div>
                                                @endif
                                                @foreach($r['changes'] as $col => [$from, $to])
                                                    <div class="bu-chg"><code>{{ $col }}</code> <s>{{ $from !== '' ? $from : 'empty' }}</s> <i class="ti ti-arrow-right"></i> <b>{{ $to }}</b></div>
                                                @endforeach
                                                @foreach($r['warnings'] as $w)<div class="bu-msg bu-msg-warn">{{ $w }}</div>@endforeach
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-4">No rows in this view.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($rows->count() > 300)<div class="small text-muted mt-2">Showing the first 300 of {{ $rows->count() }} rows.</div>@endif
                    </div>
                    <div class="bu-panel-foot">
                        <div class="d-flex gap-2 flex-wrap">
                            @if($counts['error'])<button type="button" class="btn btn-sm btn-light border" wire:click="downloadErrors({{ $import->id }})"><i class="ti ti-file-alert me-1"></i> Error report ({{ $counts['error'] }})</button>@endif
                            @if($import->status === 'previewed')<button type="button" class="btn btn-sm btn-light border" wire:click="cancelImport">Discard</button>@endif
                        </div>
                        @if($import->status === 'previewed')
                            @php $valid = $counts['new'] + $counts['update']; @endphp
                            @if($import->expired)
                                <span class="small text-danger">Older than 24 hours — upload the file again.</span>
                            @else
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkApplyModal" @if(!$valid) disabled @endif>
                                    <i class="ti ti-check me-1"></i> 4 · Apply {{ $valid }} valid {{ \Illuminate\Support\Str::plural('row', $valid) }}
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Apply confirmation -->
    @if($import && $import->status === 'previewed')
        <div class="modal fade" id="bulkApplyModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <i class="ti ti-database-import text-success fs-1 mb-3"></i>
                        <h5>Apply this file?</h5>
                        <p class="text-muted mb-1">{{ $counts['new'] }} new {{ \Illuminate\Support\Str::plural('student', $counts['new']) }} and {{ $counts['update'] }} {{ \Illuminate\Support\Str::plural('update', $counts['update']) }} will be saved.</p>
                        @if($counts['error'])<p class="small text-danger mb-1">{{ $counts['error'] }} {{ \Illuminate\Support\Str::plural('row', $counts['error']) }} with errors will be skipped.</p>@endif
                        <p class="small text-muted mb-4">Every change is recorded in the audit trail.</p>
                        <div class="d-flex justify-content-center gap-2">
                            <button class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-success px-4" wire:click="apply" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="apply">Yes, apply</span>
                                <span wire:loading wire:target="apply"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
        .bu-ui .min-w-0 { min-width: 0; }
        .bu-ui .bu-steps { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        .bu-ui .bu-step { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; }
        .bu-ui .bu-step small { color: #6B7280; font-size: 12px; } .bu-ui .bu-step > i { font-size: 20px; color: #D1D5DB; }
        .bu-ui .bu-step-num { width: 30px; height: 30px; border-radius: 50%; background: #F3F4F6; color: #6B7280; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .bu-ui .bu-step.is-on { border-color: #FDBA8C; background: linear-gradient(180deg, #FFF7F2, #fff); }
        .bu-ui .bu-step.is-on .bu-step-num { background: #F26522; color: #fff; } .bu-ui .bu-step.is-on > i { color: #F26522; }
        .bu-ui .bu-panel { background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .bu-ui .bu-panel-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #F1F2F4; }
        .bu-ui .bu-panel-head small { color: #6B7280; }
        .bu-ui .bu-panel-body { padding: 16px 18px; }
        .bu-ui .bu-panel-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 12px 18px; border-top: 1px solid #F1F2F4; background: #FAFAFA; border-radius: 0 0 14px 14px; }
        .bu-ui .bu-icon { width: 38px; height: 38px; border-radius: 10px; background: #FEF0E7; color: #F26522; display: inline-flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0; }
        .bu-ui .bu-icon-up { background: #DBEAFE; color: #1D4ED8; } .bu-ui .bu-icon-hist { background: #F3F4F6; color: #4B5563; }
        .bu-ui .bu-note { display: flex; gap: 8px; padding: 10px 12px; border-radius: 10px; background: #F8FAFC; border: 1px solid #E2E8F0; font-size: 13px; color: #334155; }
        .bu-ui .bu-note > i { font-size: 18px; color: #F59E0B; }
        .bu-ui .bu-note-warn { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
        .bu-ui .bu-note-ok { background: #F0FDF4; border-color: #BBF7D0; color: #166534; } .bu-ui .bu-note-ok > i { color: #16A34A; }
        .bu-ui .bu-drop { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 22px 16px; border: 2px dashed #CBD5E1; border-radius: 12px; background: #F8FAFC; cursor: pointer; text-align: center; transition: border-color .15s, background .15s; }
        .bu-ui .bu-drop:hover { border-color: #F26522; background: #FFF7F2; } .bu-ui .bu-drop.is-invalid { border-color: #DC2626; }
        .bu-ui .bu-drop > i { font-size: 32px; color: #94A3B8; } .bu-ui .bu-drop small { color: #6B7280; }
        .bu-ui .bu-hist { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; border: 0; border-bottom: 1px solid #F1F2F4; background: #fff; padding: 10px 18px; text-align: left; }
        .bu-ui .bu-hist:hover, .bu-ui .bu-hist.is-on { background: #FFF7F2; }
        .bu-ui .bu-st { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: #F3F4F6; color: #4B5563; white-space: nowrap; }
        .bu-ui .bu-st-applied { background: #DCFCE7; color: #166534; } .bu-ui .bu-st-previewed { background: #DBEAFE; color: #1D4ED8; } .bu-ui .bu-st-cancelled { background: #F3F4F6; color: #9CA3AF; }
        .bu-ui .bu-empty { text-align: center; padding: 40px 24px; } .bu-ui .bu-empty > i { font-size: 44px; color: #CBD5E1; }
        .bu-ui .bu-guide summary { cursor: pointer; font-weight: 600; font-size: 13.5px; color: #1D4ED8; }
        .bu-ui .bu-counts { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; }
        .bu-ui .bu-count { display: flex; flex-direction: column; align-items: flex-start; padding: 10px 12px; border: 1px solid #E5E7EB; border-radius: 12px; background: #fff; }
        .bu-ui .bu-count b { font-size: 20px; line-height: 1.1; } .bu-ui .bu-count span { font-size: 12px; color: #6B7280; }
        .bu-ui .bu-count.is-on { border-width: 2px; padding: 9px 11px; }
        .bu-ui .bu-tone-green b { color: #15803D; } .bu-ui .bu-tone-green.is-on { border-color: #16A34A; background: #F0FDF4; }
        .bu-ui .bu-tone-blue b { color: #1D4ED8; } .bu-ui .bu-tone-blue.is-on { border-color: #2563EB; background: #EFF6FF; }
        .bu-ui .bu-tone-red b { color: #B91C1C; } .bu-ui .bu-tone-red.is-on { border-color: #DC2626; background: #FEF2F2; }
        .bu-ui .bu-tone-slate.is-on, .bu-ui .bu-tone-muted.is-on { border-color: #64748B; background: #F8FAFC; } .bu-ui .bu-tone-muted b { color: #9CA3AF; }
        .bu-ui .bu-table-wrap { max-height: 560px; overflow: auto; border: 1px solid #F1F2F4; border-radius: 10px; }
        .bu-ui .bu-table thead th { position: sticky; top: 0; background: #F9FAFB; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #6B7280; z-index: 1; }
        .bu-ui .bu-table td { font-size: 13px; vertical-align: top; white-space: normal; }
        .bu-ui .bu-row-error td { background: #FFFBFB; }
        .bu-ui .bu-badge { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
        .bu-ui .bu-badge-new { background: #DCFCE7; color: #166534; } .bu-ui .bu-badge-update { background: #DBEAFE; color: #1D4ED8; }
        .bu-ui .bu-badge-unchanged { background: #F3F4F6; color: #6B7280; } .bu-ui .bu-badge-error { background: #FEE2E2; color: #991B1B; }
        .bu-ui .bu-chg { font-size: 12.5px; margin-bottom: 2px; } .bu-ui .bu-chg s { color: #9CA3AF; } .bu-ui .bu-chg i { color: #9CA3AF; font-size: 12px; }
        .bu-ui .bu-msg { font-size: 12.5px; margin-bottom: 2px; } .bu-ui .bu-msg-err { color: #B91C1C; } .bu-ui .bu-msg-warn { color: #B45309; }
        .bu-ui code { font-size: 11.5px; color: #7C3AED; background: #F5F3FF; padding: 0 4px; border-radius: 4px; }
        @media (max-width: 767px) { .bu-ui .bu-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } .bu-ui .bu-counts { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    </style>

    <script>
        document.addEventListener('livewire:load', function () {
            window.addEventListener('close-bulk-apply-modal', () => {
                const el = document.getElementById('bulkApplyModal');
                const instance = el && bootstrap.Modal.getInstance(el);
                if (instance) { instance.hide(); }
                document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                document.body.classList.remove('modal-open');
            });
        });
    </script>
</div>
