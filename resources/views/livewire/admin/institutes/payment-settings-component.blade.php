<div class="content">
    <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="my-auto mb-2">
            <h2 class="mb-1 fw-semibold">Payment settings</h2>
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    <li class="breadcrumb-item active">Payment settings</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body row g-3">
            @if($isSuperAdmin)
                @include('livewire.admin.students.partials.select', ['name' => 'instituteId', 'label' => 'Institute', 'live' => true,
                    'options' => $institutes->mapWithKeys(fn ($i) => [$i->id => "{$i->name} ({$i->code})"])])
            @else
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Institute</label>
                    <input type="text" class="form-control bg-light" readonly value="{{ optional($institutes->first())->name }}">
                </div>
            @endif
            <div class="col-md-6 small text-muted d-flex align-items-end">
                Students see the methods enabled here on the payment step of the admissions portal.
            </div>
        </div>
    </div>

    @if($instituteId)
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Method</th><th>Type</th><th>Details shown to students</th><th>Status</th><th class="text-end"></th></tr>
                    </thead>
                    <tbody>
                        @foreach($gateways as $gateway)
                            @php $setting = $settings->get($gateway->id); @endphp
                            <tr class="{{ !$gateway->status ? 'text-muted' : '' }}">
                                <td class="fw-semibold">{{ $gateway->name }}</td>
                                <td class="small">{{ $gateway->type_label }}</td>
                                <td class="small">
                                    @if($gateway->type === 'online')
                                        <span class="text-muted">Online gateway integration is not set up yet.</span>
                                    @elseif($setting)
                                        @if($setting->upi_id)<span class="amount">{{ $setting->upi_id }}</span>@if($setting->payee_name) · {{ $setting->payee_name }}@endif<br>@endif
                                        @if($setting->qr_code_path)<span class="badge badge-soft-info">QR uploaded</span>@endif
                                        @if($setting->instructions){{ \Illuminate\Support\Str::limit($setting->instructions, 80) }}@endif
                                    @else
                                        <span class="text-muted">Not set up</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$gateway->status)
                                        <span class="badge badge-soft-secondary">Inactive in Master Data</span>
                                    @elseif($setting && $setting->status)
                                        <span class="badge badge-soft-success">Accepted</span>
                                    @else
                                        <span class="badge badge-soft-danger">Not accepted</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($gateway->type !== 'online')
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="edit({{ $gateway->id }})">Set up</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="modal fade" id="paymentSettingModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ optional($editing)->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    @if($editing)
                        <div class="row g-3">
                            @if($editing->type === 'upi')
                                @include('livewire.admin.students.partials.input', ['name' => 'upi_id', 'label' => 'UPI ID', 'required' => true, 'placeholder' => 'institute@okaxis'])
                                @include('livewire.admin.students.partials.input', ['name' => 'payee_name', 'label' => 'Payee name', 'placeholder' => 'Name shown in the UPI app'])
                                <div class="col-12">
                                    <label class="form-label fw-medium small">UPI QR code <span class="text-muted">(optional, PNG/JPG up to 1 MB)</span></label>
                                    @if($editingSetting && $editingSetting->qr_code_path)
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <img src="{{ route('admin.institute-payment-settings.qr', $editingSetting->id) }}" alt="QR" style="width: 90px; height: 90px; object-fit: contain;" class="border rounded">
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeQr">Remove</button>
                                        </div>
                                    @endif
                                    <input type="file" class="form-control @error('qr_upload') is-invalid @enderror" wire:model="qr_upload" accept=".png,.jpg,.jpeg">
                                    @error('qr_upload') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <small class="text-muted">Without a QR, students get a "Pay with UPI app" link and the UPI ID to copy.</small>
                                </div>
                            @endif
                            @include('livewire.admin.students.partials.input', ['name' => 'instructions', 'label' => $editing->type === 'upi' ? 'Note for students' : 'Instructions for students', 'type' => 'textarea', 'col' => 12,
                                'placeholder' => $editing->type === 'upi' ? 'Optional' : 'e.g. Accounts desk, 10 am – 4 pm, Mon–Sat. Bank: …'])
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="pgStatus" wire:model="status" value="1">
                                    <label class="form-check-label" for="pgStatus">{{ $status ? 'Accepted by this institute' : 'Not accepted' }}</label>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,qr_upload">Save</button>
                </div>
            </div>
        </div>
    </div>

    @include('livewire.admin.onboarding.partials.styles')
</div>

<script>
document.addEventListener('livewire:load', function () {
    window.addEventListener('open-payment-setting-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentSettingModal')).show());
    window.addEventListener('close-payment-setting-modal', () => {
        const instance = bootstrap.Modal.getInstance(document.getElementById('paymentSettingModal'));
        if (instance) {
            instance.hide();
        }
    });
});
</script>
