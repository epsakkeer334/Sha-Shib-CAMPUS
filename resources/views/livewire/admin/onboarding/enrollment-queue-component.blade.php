<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">ER &amp; ID cards</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Student Onboarding</li>
                    <li class="breadcrumb-item active">ER &amp; ID cards</li>
                </ol>
            </nav>
        </div>
        @include('livewire.admin.onboarding.partials.queue-tabs', ['tabs' => [
            'form' => 'ER forms to complete · ' . $counts['form'],
            'card' => 'ID cards to issue · ' . $counts['card'],
            'done' => 'Completed',
        ]])
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <input type="search" class="form-control" style="max-width: 360px;" placeholder="Search name or ER number" aria-label="Search" wire:model.debounce.400ms="search">
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Student</th><th>ER number</th><th>ER form</th><th>ID card</th><th>Status</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                    @forelse($students as $item)
                        @php
                            $form = $item->erRequest;
                            $card = $item->idCard;
                            $formLabel = !$form ? '—' : match ($form->status) {
                                'archived' => 'Archived', 'signed' => 'Signed — to archive', 'printed' => 'Awaiting TM signature', default => 'To print',
                            };
                        @endphp
                        <tr>
                            <td><span class="fw-semibold">{{ $item->full_name }}</span><div class="small text-muted">{{ optional($item->course)->code }}</div></td>
                            <td class="amount">{{ $item->er_number }}</td>
                            <td><span class="badge badge-soft-{{ optional($form)->status === 'archived' ? 'success' : 'warning' }}">{{ $formLabel }}</span></td>
                            <td>
                                <span class="badge badge-soft-{{ optional($card)->tm_signature_status === 'physically_signed' ? 'success' : 'secondary' }}">
                                    {{ optional($card)->tm_signature_status === 'physically_signed' ? 'Issued ' . optional($card->issue_date)->format('d M') : (optional($card)->status === 'reprinted' ? 'Reprint — to sign' : 'Pending') }}
                                </span>
                            </td>
                            <td>{!! $item->status_html !!}</td>
                            <td class="text-end"><a href="{{ route('admin.students.enrollment', $item->id) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nothing here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $students->links() }}</div>
    </div>

    @include('livewire.admin.onboarding.partials.styles')
</div>
