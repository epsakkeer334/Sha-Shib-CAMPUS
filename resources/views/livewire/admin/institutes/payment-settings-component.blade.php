@php
    $icons = ['gpay' => 'ti ti-qrcode', 'cash' => 'ti ti-cash', 'bank_transfer' => 'ti ti-building-bank', 'cheque' => 'ti ti-file-dollar'];
    $iconFor = fn ($g) => $icons[$g->code] ?? ($g->type === 'online' ? 'ti ti-credit-card' : ($g->type === 'upi' ? 'ti ti-qrcode' : 'ti ti-wallet'));
    $toneFor = fn ($g) => ['upi' => 'indigo', 'offline' => 'green', 'online' => 'sky'][$g->type] ?? 'gray';
@endphp
<div class="content ps-ui">
    {{-- Header --}}
    <div class="ps-hero mb-3">
        <div class="min-w-0">
            <h2 class="mb-1 fw-bold">Payment settings</h2>
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Institute Management</li>
                    @if($isSuperAdmin && $currentInstitute)
                        <li class="breadcrumb-item"><a href="#" wire:click.prevent="backToInstitutes">Payment settings</a></li>
                        <li class="breadcrumb-item active">{{ $currentInstitute->name }}</li>
                    @else
                        <li class="breadcrumb-item active">Payment settings</li>
                    @endif
                </ol>
            </nav>
            <div class="text-muted small">Choose how students can pay their fees. Enabled methods appear on the payment step of the admissions portal.</div>
        </div>
        @if($currentInstitute)
            <div class="ps-inst-card">
                <span class="ps-inst-icon"><i class="ti ti-building-community"></i></span>
                <div class="min-w-0">
                    <div class="fw-semibold text-truncate">{{ $currentInstitute->name }}</div>
                    <div class="d-flex align-items-center gap-2">
                        @if($currentInstitute->code)<span class="ps-inst-code">{{ $currentInstitute->code }}</span>@endif
                        @if($isSuperAdmin)<a href="#" class="small" wire:click.prevent="backToInstitutes">Change</a>@endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($isSuperAdmin && !$currentInstitute)
        {{-- Super Admin overview: every institute and the methods it accepts --}}
        <div class="ps-panel">
            <div class="ps-panel-head">
                <div class="ps-panel-title">Institutes <span class="ps-count">{{ $overview->count() }}</span></div>
                <div class="ps-search">
                    <i class="ti ti-search"></i>
                    <input type="search" class="form-control form-control-sm" placeholder="Institute name or code" wire:model.debounce.400ms="search" aria-label="Search institutes">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 ps-table">
                    <thead><tr><th>Institute</th><th>Accepted methods</th><th>UPI</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                        @forelse($overview as $inst)
                            <tr wire:key="inst-{{ $inst->id }}" class="{{ $inst->accepted->isEmpty() ? 'ps-row-warn' : '' }}">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="ps-inst-icon ps-inst-icon-sm"><i class="ti ti-building"></i></span>
                                        <div class="min-w-0">
                                            <div class="fw-semibold">{{ $inst->name }}</div>
                                            <div class="d-flex align-items-center gap-2 small text-muted">
                                                @if($inst->code)<span class="ps-inst-code">{{ $inst->code }}</span>@endif {{ $inst->city }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @forelse($inst->accepted as $g)
                                        <span class="ps-method-chip ps-tone-{{ $toneFor($g) }}"><i class="{{ $iconFor($g) }}"></i> {{ $g->name }}</span>
                                    @empty
                                        <span class="ps-chip ps-chip-warn"><i class="ti ti-alert-triangle"></i> No payment method — students can't pay</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if($inst->upi_ready)
                                        <span class="ps-chip ps-chip-ok"><i class="ti ti-circle-check"></i> Ready</span>
                                        @if($inst->has_qr)<span class="ps-chip ps-chip-info ms-1"><i class="ti ti-qrcode"></i> QR</span>@endif
                                    @else
                                        <span class="ps-chip ps-chip-muted">Not set up</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="selectInstitute({{ $inst->id }})">Manage <i class="ti ti-chevron-right"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ps-empty-row"><i class="ti ti-building-off"></i><div>No institutes found.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($currentInstitute)
        @php
            $usable = $gateways->where('status', true);
            $acceptedGateways = $usable->filter(fn ($g) => optional($settings->get($g->id))->status && $g->type !== 'online');
            $upiSetting = $usable->where('type', 'upi')->map(fn ($g) => $settings->get($g->id))->filter(fn ($s) => $s && $s->status && $s->upi_id)->first();
        @endphp

        {{-- Summary --}}
        <div class="ps-summary mb-3">
            <div class="ps-summary-item">
                <span class="ps-summary-icon ps-tone-green"><i class="ti ti-circle-check"></i></span>
                <div><div class="ps-summary-value">{{ $acceptedGateways->count() }}</div><div class="ps-summary-label">Methods accepted</div></div>
            </div>
            <div class="ps-summary-item">
                <span class="ps-summary-icon ps-tone-indigo"><i class="ti ti-qrcode"></i></span>
                <div><div class="ps-summary-value">{{ $upiSetting ? 'Ready' : 'Not set up' }}</div><div class="ps-summary-label">UPI payments{{ $upiSetting && $upiSetting->qr_code_path ? ' · QR added' : '' }}</div></div>
            </div>
            <div class="ps-summary-item">
                <span class="ps-summary-icon ps-tone-sky"><i class="ti ti-credit-card"></i></span>
                @php
                    $onlineAll = $gateways->where('type', 'online');
                    $onlineReady = $onlineAll->filter(fn ($g) => \App\Http\Livewire\Admin\Institutes\PaymentSettingsComponent::onlineConfigured($settings->get($g->id)))->count();
                @endphp
                <div><div class="ps-summary-value">{{ $onlineReady }} / {{ $onlineAll->count() }}</div><div class="ps-summary-label">Online gateways configured</div></div>
            </div>
        </div>

        @if($acceptedGateways->isEmpty())
            <div class="ps-alert mb-3"><i class="ti ti-alert-triangle"></i> No payment method is accepted yet — students won't be able to pay their fees on the portal.</div>
        @endif

        @php
            // UPI first, then offline; online gateways go in a slim "coming soon" strip
            $manual = $gateways->where('type', '!=', 'online')->sortBy(fn ($g) => [$g->type === 'upi' ? 0 : 1, $g->sort_order])->values();
            $online = $gateways->where('type', 'online');
        @endphp
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="ps-section-title">
                    <span class="ps-section-icon ps-tone-green"><i class="ti ti-hand-finger"></i></span>
                    <div><div class="fw-semibold">Manual payments</div><div class="small text-muted">UPI and offline payments. Accounts verifies each one before a receipt is issued.</div></div>
                </div>
                <div class="ps-grid">
                    @foreach($manual as $gateway)
                        @php
                            $setting = $settings->get($gateway->id);
                            $accepted = $gateway->status && optional($setting)->status;
                            [$stateLabel, $stateTone] = match (true) {
                                !$gateway->status => ['Inactive in Master Data', 'muted'],
                                $accepted => ['Accepted', 'ok'],
                                (bool) $setting => ['Turned off', 'bad'],
                                default => ['Not set up', 'muted'],
                            };
                        @endphp
                        <div class="ps-method {{ $accepted ? 'is-on' : '' }} {{ !$gateway->status ? 'is-disabled' : '' }}" wire:key="gw-{{ $gateway->id }}">
                            <div class="d-flex align-items-center gap-2">
                                <span class="ps-method-icon ps-tone-{{ $toneFor($gateway) }}"><i class="{{ $iconFor($gateway) }}"></i></span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="ps-method-name text-truncate" title="{{ $gateway->name }}">{{ $gateway->name }}</div>
                                    <div class="ps-method-type">{{ $gateway->type === 'upi' ? 'UPI' : 'Offline' }}</div>
                                </div>
                                @if($gateway->status)
                                    <div class="form-check form-switch m-0" title="{{ $accepted ? 'Stop accepting' : 'Accept this method' }}">
                                        <input class="form-check-input ps-switch" type="checkbox" role="switch" id="accept{{ $gateway->id }}" aria-label="Accept {{ $gateway->name }}"
                                               @checked($accepted) wire:click="toggleAccept({{ $gateway->id }})" wire:loading.attr="disabled">
                                    </div>
                                @endif
                            </div>

                            <div class="ps-method-body">
                                @if($gateway->type === 'upi')
                                    @if(optional($setting)->upi_id)
                                        <div class="d-flex gap-2 align-items-center min-w-0">
                                            @if($setting->qr_code_path)
                                                <img src="{{ route('admin.institute-payment-settings.qr', $setting->id) }}" alt="UPI QR code" class="ps-qr-thumb">
                                            @else
                                                <span class="ps-qr-thumb ps-qr-empty" title="No QR uploaded"><i class="ti ti-qrcode-off"></i></span>
                                            @endif
                                            <div class="min-w-0">
                                                <div class="ps-mono text-truncate" title="{{ $setting->upi_id }}">{{ $setting->upi_id }}</div>
                                                @if($setting->payee_name)<div class="small text-muted text-truncate">{{ $setting->payee_name }}</div>@endif
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted"><i class="ti ti-info-circle"></i> Add your UPI ID to accept UPI.</span>
                                    @endif
                                @elseif(optional($setting)->instructions)
                                    <span class="ps-clamp" title="{{ $setting->instructions }}">{{ $setting->instructions }}</span>
                                @else
                                    <span class="text-muted"><i class="ti ti-info-circle"></i> Add desk timings or bank details.</span>
                                @endif
                            </div>

                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="ps-chip ps-chip-{{ $stateTone }}">{{ $stateLabel }}</span>
                                <button type="button" class="btn btn-sm {{ $setting ? 'btn-light' : 'btn-primary' }} ps-btn" wire:click="edit({{ $gateway->id }})">
                                    <i class="ti ti-{{ $setting ? 'edit' : 'settings' }}"></i> {{ $setting ? 'Edit' : 'Set up' }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($online->isNotEmpty())
                    <div class="ps-section-title mt-4">
                        <span class="ps-section-icon ps-tone-sky"><i class="ti ti-world-www"></i></span>
                        <div class="min-w-0">
                            <div class="fw-semibold">Online payment gateways</div>
                            <div class="small text-muted">Card / netbanking / UPI checkout. Save the API credentials here; credentials are stored encrypted and never shown again.</div>
                        </div>
                    </div>
                    <div class="ps-online-note mb-2"><i class="ti ti-info-circle"></i> Online checkout on the student portal goes live once the gateway integration is released. Until then students use the manual methods above.</div>
                    <div class="ps-grid">
                        @foreach($online as $gateway)
                            @php
                                $setting = $settings->get($gateway->id);
                                $configured = \App\Http\Livewire\Admin\Institutes\PaymentSettingsComponent::onlineConfigured($setting);
                                $enabled = $gateway->status && $configured && optional($setting)->status;
                                [$idLabel] = \App\Http\Livewire\Admin\Institutes\PaymentSettingsComponent::ONLINE_FIELDS[$gateway->code] ?? \App\Http\Livewire\Admin\Institutes\PaymentSettingsComponent::ONLINE_DEFAULT;
                                [$stateLabel, $stateTone] = match (true) {
                                    $enabled => ['Enabled', 'ok'],
                                    $configured && !$gateway->status => ['Configured · inactive in Master Data', 'muted'],
                                    $configured => ['Configured · off', 'info'],
                                    !$gateway->status => ['Inactive in Master Data', 'muted'],
                                    default => ['Not set up', 'muted'],
                                };
                            @endphp
                            <div class="ps-method ps-method-online {{ $enabled ? 'is-on' : '' }} {{ !$gateway->status ? 'is-disabled' : '' }}" wire:key="gw-{{ $gateway->id }}">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="ps-method-icon ps-tone-sky"><i class="{{ $iconFor($gateway) }}"></i></span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="ps-method-name text-truncate">{{ $gateway->name }}</div>
                                        <div class="ps-method-type">Online</div>
                                    </div>
                                    @if($gateway->status)
                                        <div class="form-check form-switch m-0" title="{{ $configured ? ($enabled ? 'Disable' : 'Enable') : 'Add credentials first' }}">
                                            <input class="form-check-input ps-switch" type="checkbox" role="switch" id="accept{{ $gateway->id }}" aria-label="Enable {{ $gateway->name }}"
                                                   @checked($enabled) wire:click="toggleAccept({{ $gateway->id }})" wire:loading.attr="disabled">
                                        </div>
                                    @endif
                                </div>
                                <div class="ps-method-body">
                                    @if($configured)
                                        <div class="min-w-0 w-100">
                                            <div class="d-flex justify-content-between gap-2">
                                                <span class="text-muted">{{ $idLabel }}</span>
                                                <span class="ps-mono text-truncate">{{ \Illuminate\Support\Str::mask($setting->merchant_id, '•', 4, max(0, strlen($setting->merchant_id) - 8)) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between gap-2 mt-1">
                                                <span class="text-muted">Secret</span>
                                                <span class="text-success"><i class="ti ti-lock"></i> Saved</span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted"><i class="ti ti-key"></i> Add the API credentials to set up {{ $gateway->name }}.</span>
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="ps-chip ps-chip-{{ $stateTone }}">{{ $stateLabel }}</span>
                                        @if($configured)
                                            <span class="ps-chip {{ $setting->is_test_mode ? 'ps-chip-warn' : 'ps-chip-ok' }}">{{ $setting->is_test_mode ? 'Test mode' : 'Live' }}</span>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-sm {{ $configured ? 'btn-light' : 'btn-primary' }} ps-btn" wire:click="edit({{ $gateway->id }})">
                                        <i class="ti ti-{{ $configured ? 'edit' : 'settings' }}"></i> {{ $configured ? 'Edit' : 'Set up' }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Student preview --}}
            <div class="col-xl-4">
                <div class="ps-panel ps-preview">
                    <div class="ps-panel-head">
                        <div class="ps-panel-title"><i class="ti ti-device-mobile"></i> What students see</div>
                        <span class="small text-muted">Portal · Pay now</span>
                    </div>
                    <div class="p-3">
                        @if($acceptedGateways->isEmpty())
                            <div class="ps-empty-row py-4"><i class="ti ti-eye-off"></i><div>No payment options are shown to students.</div></div>
                        @else
                            <div class="ps-label mb-2">Choose a payment method</div>
                            @php
                                // Open option: the one clicked (previewGatewayId), else the first; -1 = all collapsed
                                $openId = $previewGatewayId == -1 ? null
                                    : ($acceptedGateways->contains('id', (int) $previewGatewayId) ? (int) $previewGatewayId : $acceptedGateways->first()->id);
                            @endphp
                            <div class="d-flex flex-column gap-2">
                                @foreach($acceptedGateways as $g)
                                    @php $s = $settings->get($g->id); $open = $openId === $g->id; @endphp
                                    <div class="ps-preview-item {{ $open ? 'is-open' : '' }}" wire:key="preview-{{ $g->id }}">
                                        <button type="button" class="ps-preview-option" wire:click="previewMethod({{ $g->id }}, {{ $open ? 'true' : 'false' }})" aria-expanded="{{ $open ? 'true' : 'false' }}">
                                            <span class="ps-radio"></span>
                                            <i class="{{ $iconFor($g) }}"></i>
                                            <span class="fw-medium flex-grow-1 text-start">{{ $g->name }}</span>
                                            <i class="ti ti-chevron-down ps-chevron"></i>
                                        </button>
                                        @if($open)
                                            <div class="ps-preview-detail">
                                                @if($g->type === 'upi')
                                                    @if($s->qr_code_path)
                                                        <img src="{{ route('admin.institute-payment-settings.qr', $s->id) }}" alt="UPI QR code" class="ps-preview-qr">
                                                    @endif
                                                    <div class="small text-muted">Pay to</div>
                                                    <div class="ps-mono fw-semibold">{{ $s->upi_id }}</div>
                                                    @if($s->payee_name)<div class="small">{{ $s->payee_name }}</div>@endif
                                                    @if($s->instructions)<div class="small text-muted mt-1">{{ $s->instructions }}</div>@endif
                                                    <div class="ps-preview-step"><i class="ti ti-upload"></i> Then upload the payment screenshot &amp; UTR</div>
                                                @else
                                                    <div class="ps-preview-offline">
                                                        <span class="ps-method-icon ps-method-icon-sm ps-tone-{{ $toneFor($g) }}"><i class="{{ $iconFor($g) }}"></i></span>
                                                        <div class="text-start min-w-0">
                                                            <div class="fw-semibold small">How to pay</div>
                                                            <div class="small" style="white-space: pre-line;">{{ $s->instructions ?: 'Pay at the accounts desk of the institute.' }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="ps-preview-step"><i class="ti ti-receipt"></i> Accounts records the payment and issues a receipt</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Set-up modal --}}
    <div class="modal fade" id="paymentSettingModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content ps-modal">
                <div class="modal-header">
                    @if($editing)
                        <div class="d-flex align-items-center gap-2">
                            <span class="ps-method-icon ps-method-icon-sm ps-tone-{{ $toneFor($editing) }}"><i class="{{ $iconFor($editing) }}"></i></span>
                            <div>
                                <h5 class="modal-title mb-0">{{ $editing->name }}</h5>
                                <div class="small text-muted">{{ optional($currentInstitute)->name }}</div>
                            </div>
                        </div>
                    @endif
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($editing)
                        <div class="row g-3">
                            @if($editing->type === 'upi')
                                @include('livewire.admin.students.partials.input', ['name' => 'upi_id', 'label' => 'UPI ID', 'required' => true, 'placeholder' => 'institute@okaxis'])
                                @include('livewire.admin.students.partials.input', ['name' => 'payee_name', 'label' => 'Payee name', 'placeholder' => 'Name shown in the UPI app'])
                                <div class="col-12">
                                    <label class="form-label fw-medium small">UPI QR code <span class="text-muted fw-normal">(optional · PNG/JPG up to 1 MB)</span></label>
                                    <div class="ps-upload">
                                        @if($qr_upload && !$errors->has('qr_upload'))
                                            <img src="{{ $qr_upload->temporaryUrl() }}" alt="New QR preview" class="ps-qr-thumb">
                                        @elseif($editingSetting && $editingSetting->qr_code_path)
                                            <img src="{{ route('admin.institute-payment-settings.qr', $editingSetting->id) }}" alt="Current QR" class="ps-qr-thumb">
                                        @else
                                            <span class="ps-qr-thumb ps-qr-empty"><i class="ti ti-qrcode"></i></span>
                                        @endif
                                        <div class="min-w-0 flex-grow-1">
                                            <input type="file" class="form-control form-control-sm @error('qr_upload') is-invalid @enderror" wire:model="qr_upload" accept=".png,.jpg,.jpeg">
                                            @error('qr_upload') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            <div wire:loading wire:target="qr_upload" class="small text-muted mt-1">Uploading…</div>
                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                <small class="text-muted">Without a QR, students get a "Pay with UPI app" link and the UPI ID to copy.</small>
                                            </div>
                                            @if($editingSetting && $editingSetting->qr_code_path && !$qr_upload)
                                                <button type="button" class="btn btn-link btn-sm text-danger p-0 mt-1" wire:click="removeQr"><i class="ti ti-trash"></i> Remove current QR</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if($editing->type === 'online')
                                @php
                                    [$idLabel, $secretLabel, $help] = \App\Http\Livewire\Admin\Institutes\PaymentSettingsComponent::ONLINE_FIELDS[$editing->code] ?? \App\Http\Livewire\Admin\Institutes\PaymentSettingsComponent::ONLINE_DEFAULT;
                                    $savedCreds = $editingSetting ? ($editingSetting->credentials ?? []) : [];
                                @endphp
                                <div class="col-12">
                                    <div class="ps-online-note"><i class="ti ti-shield-lock"></i> Credentials are stored encrypted. Saved secrets are never shown again — leave a secret blank to keep it. <span class="text-muted">{{ $help }}</span></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium small" for="pgMerchant">{{ $idLabel }} <span class="text-danger">*</span></label>
                                    <input id="pgMerchant" type="text" class="form-control ps-mono @error('merchant_id') is-invalid @enderror" wire:model.defer="merchant_id" autocomplete="off" spellcheck="false">
                                    @error('merchant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small" for="pgSecret">{{ $secretLabel }} @if(empty($savedCreds['secret']))<span class="text-danger">*</span>@endif</label>
                                    <input id="pgSecret" type="password" class="form-control @error('secret') is-invalid @enderror" wire:model.defer="secret" autocomplete="new-password"
                                           placeholder="{{ !empty($savedCreds['secret']) ? '•••••••• saved — leave blank to keep' : '' }}">
                                    @error('secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small" for="pgWebhook">Webhook secret <span class="text-muted fw-normal">(optional)</span></label>
                                    <input id="pgWebhook" type="password" class="form-control @error('webhook_secret') is-invalid @enderror" wire:model.defer="webhook_secret" autocomplete="new-password"
                                           placeholder="{{ !empty($savedCreds['webhook_secret']) ? '•••••••• saved' : '' }}">
                                    @error('webhook_secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium small d-block">Mode</label>
                                    <div class="ps-mode">
                                        <input type="radio" class="btn-check" name="pgMode" id="pgModeTest" value="1" wire:model="is_test_mode">
                                        <label class="btn btn-sm btn-outline-warning" for="pgModeTest"><i class="ti ti-flask me-1"></i> Test / sandbox</label>
                                        <input type="radio" class="btn-check" name="pgMode" id="pgModeLive" value="0" wire:model="is_test_mode">
                                        <label class="btn btn-sm btn-outline-success" for="pgModeLive"><i class="ti ti-bolt me-1"></i> Live</label>
                                    </div>
                                </div>
                                @if(!$editing->status)
                                    <div class="col-12"><div class="ps-alert"><i class="ti ti-alert-triangle"></i> {{ $editing->name }} is inactive in Master Data — you can save credentials, but it can't be enabled yet.</div></div>
                                @endif
                            @else
                                @include('livewire.admin.students.partials.input', ['name' => 'instructions', 'label' => $editing->type === 'upi' ? 'Note for students' : 'Instructions for students', 'type' => 'textarea', 'col' => 12,
                                    'placeholder' => $editing->type === 'upi' ? 'Optional' : 'e.g. Accounts desk, 10 am – 4 pm, Mon–Sat. Bank: …'])
                            @endif
                            <div class="col-12">
                                <div class="ps-accept-row">
                                    <div>
                                        <div class="fw-semibold small">{{ $editing->type === 'online' ? 'Enable this gateway' : 'Accept this method' }}</div>
                                        <div class="small text-muted">{{ $editing->type === 'online' ? ($status ? 'Used for online checkout once it goes live.' : 'Credentials saved, gateway off.') : ($status ? 'Shown to students on the payment step.' : 'Hidden from students.') }}</div>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input ps-switch" type="checkbox" id="pgStatus" wire:model="status" value="1" aria-label="Accept this method">
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:target="save,qr_upload"><i class="ti ti-check me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ps-ui { --ps-border: #E5E7EB; --ps-soft: #F1F2F4; --ps-ink: #111827; --ps-muted: #6B7280; --ps-accent: #F26522; }
        .ps-ui .min-w-0 { min-width: 0; }
        .ps-ui .ps-hero { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; padding: 18px 22px; border-radius: 14px; border: 1px solid var(--ps-border);
            background: radial-gradient(circle at 100% 0, rgba(242, 101, 34, .12), transparent 45%), linear-gradient(135deg, #FFFFFF 0%, #FFF8F3 100%); }
        .ps-ui .ps-hero h2 { font-size: 22px; color: var(--ps-ink); }
        .ps-ui .ps-inst-card { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff; border: 1px solid var(--ps-border); border-radius: 12px; max-width: 320px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .ps-ui .ps-inst-icon { width: 40px; height: 40px; border-radius: 10px; background: #FEF0E7; color: var(--ps-accent); display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .ps-ui .ps-inst-icon-sm { width: 34px; height: 34px; font-size: 17px; }
        .ps-ui .ps-inst-code { padding: 1px 8px; border-radius: 6px; background: #EEF2FF; color: #4338CA; font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 11.5px; font-weight: 500; }

        .ps-ui .ps-panel { background: #fff; border: 1px solid var(--ps-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); overflow: hidden; }
        .ps-ui .ps-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 16px; border-bottom: 1px solid var(--ps-soft); }
        .ps-ui .ps-panel-title { font-weight: 600; font-size: 15px; color: var(--ps-ink); display: flex; align-items: center; gap: 8px; }
        .ps-ui .ps-count { min-width: 26px; height: 22px; padding: 0 8px; border-radius: 999px; background: #F3F4F6; color: #374151; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; }
        .ps-ui .ps-search { position: relative; width: 260px; max-width: 100%; }
        .ps-ui .ps-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
        .ps-ui .ps-search input { padding-left: 32px; border-radius: 8px; }
        .ps-ui .ps-table thead th { background: #F9FAFB; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--ps-muted); border-bottom: 1px solid var(--ps-border); padding: 10px 14px; }
        .ps-ui .ps-table td { padding: 12px 14px; border-color: var(--ps-soft); font-size: 14px; }
        .ps-ui .ps-table tbody tr:last-child td { border-bottom: 0; }
        .ps-ui .ps-row-warn td { background: #FFFBEB; }
        .ps-ui .ps-row-warn td:first-child { box-shadow: inset 3px 0 0 #F59E0B; }
        .ps-ui .ps-empty-row { text-align: center; color: var(--ps-muted); padding: 40px 12px; }
        .ps-ui .ps-empty-row i { font-size: 34px; color: #D1D5DB; display: block; margin-bottom: 6px; }

        .ps-ui .ps-tone-indigo { background: #EEF2FF; color: #4338CA; }
        .ps-ui .ps-tone-green { background: #DCFCE7; color: #15803D; }
        .ps-ui .ps-tone-sky { background: #E0F2FE; color: #0369A1; }
        .ps-ui .ps-tone-gray { background: #F3F4F6; color: #374151; }
        .ps-ui .ps-method-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 12px; font-weight: 500; margin: 2px 4px 2px 0; white-space: nowrap; }
        .ps-ui .ps-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .ps-ui .ps-chip-ok { background: #DCFCE7; color: #15803D; }
        .ps-ui .ps-chip-warn { background: #FEF3C7; color: #B45309; }
        .ps-ui .ps-chip-info { background: #E0F2FE; color: #0369A1; }
        .ps-ui .ps-chip-bad { background: #FEE2E2; color: #DC2626; }
        .ps-ui .ps-chip-muted { background: #F3F4F6; color: var(--ps-muted); }

        .ps-ui .ps-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .ps-ui .ps-summary-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: #fff; border: 1px solid var(--ps-border); border-radius: 14px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
        .ps-ui .ps-summary-icon { width: 42px; height: 42px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .ps-ui .ps-summary-value { font-size: 18px; font-weight: 700; color: var(--ps-ink); line-height: 1.2; }
        .ps-ui .ps-summary-label { font-size: 12.5px; color: var(--ps-muted); }
        .ps-ui .ps-alert { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-radius: 12px; background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; font-size: 13.5px; }

        .ps-ui .ps-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
        .ps-ui .ps-method { display: flex; flex-direction: column; gap: 10px; padding: 12px 14px; background: #fff; border: 1px solid var(--ps-border); border-radius: 12px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); transition: border-color .15s, box-shadow .15s; min-width: 0; }
        .ps-ui .ps-method:hover { box-shadow: 0 6px 16px rgba(16, 24, 40, .07); }
        .ps-ui .ps-method.is-on { border-color: #86EFAC; background: linear-gradient(180deg, #F0FDF4 0%, #FFFFFF 55%); }
        .ps-ui .ps-method.is-disabled { background: #FAFAFA; }
        .ps-ui .ps-method.is-disabled .ps-method-icon { filter: grayscale(1); opacity: .6; }
        .ps-ui .ps-method-icon { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .ps-ui .ps-method-icon-sm { width: 32px; height: 32px; font-size: 16px; }
        .ps-ui .ps-method-name { font-weight: 600; font-size: 14px; color: var(--ps-ink); line-height: 1.25; }
        .ps-ui .ps-method-type { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--ps-muted); }
        .ps-ui .ps-method-body { flex-grow: 1; min-height: 52px; display: flex; align-items: center; padding: 8px 10px; border-radius: 9px; background: #F9FAFB; border: 1px dashed var(--ps-border); font-size: 12.5px; color: #374151; min-width: 0; }
        .ps-ui .ps-method.is-on .ps-method-body { background: #fff; }
        .ps-ui .ps-btn { padding: 2px 10px; font-size: 12px; border-radius: 8px; }
        .ps-ui .ps-section-title { display: flex; align-items: center; gap: 10px; margin: 0 2px 12px; }
        .ps-ui .ps-section-icon { width: 34px; height: 34px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
        .ps-ui .ps-online-note { display: flex; align-items: flex-start; gap: 6px; padding: 8px 12px; border-radius: 9px; background: #F0F9FF; border: 1px solid #BAE6FD; color: #075985; font-size: 12.5px; }
        .ps-ui .ps-method-online.is-on { border-color: #7DD3FC; background: linear-gradient(180deg, #F0F9FF 0%, #FFFFFF 55%); }
        .ps-ui .ps-mode { display: inline-flex; gap: 6px; }
        .ps-ui .ps-online { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px; border: 1px dashed #D1D5DB; border-radius: 12px; background: #FAFAFB; }
        .ps-ui .ps-switch { width: 2.4em; height: 1.3em; cursor: pointer; }
        .ps-ui .ps-switch:checked { background-color: #16A34A; border-color: #16A34A; }
        .ps-ui .ps-label { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--ps-muted); }
        .ps-ui .ps-mono { font-family: 'IBM Plex Mono', ui-monospace, monospace; font-size: 13px; color: var(--ps-ink); }
        .ps-ui .ps-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .ps-ui .ps-qr-thumb { width: 44px; height: 44px; border-radius: 8px; object-fit: contain; background: #fff; border: 1px solid var(--ps-border); padding: 3px; flex-shrink: 0; }
        .ps-ui .ps-qr-empty { display: inline-flex; align-items: center; justify-content: center; color: #D1D5DB; font-size: 20px; }
        .ps-ui .ps-upload .ps-qr-thumb { width: 64px; height: 64px; }

        .ps-ui .ps-preview { position: sticky; top: 80px; }
        .ps-ui .ps-preview-item { border: 1px solid var(--ps-border); border-radius: 10px; overflow: hidden; transition: border-color .15s; }
        .ps-ui .ps-preview-item.is-open { border-color: var(--ps-accent); box-shadow: 0 0 0 3px rgba(242, 101, 34, .08); }
        .ps-ui .ps-preview-option { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 12px; border: 0; background: #fff; font-size: 13.5px; color: #374151; cursor: pointer; }
        .ps-ui .ps-preview-option:hover { background: #FAFAFB; }
        .ps-ui .ps-preview-item.is-open .ps-preview-option { background: #FFF7F2; }
        .ps-ui .ps-radio { width: 16px; height: 16px; border-radius: 50%; border: 2px solid #D1D5DB; flex-shrink: 0; transition: border .15s; }
        .ps-ui .ps-preview-item.is-open .ps-radio { border: 5px solid var(--ps-accent); }
        .ps-ui .ps-chevron { color: #9CA3AF; transition: transform .2s; }
        .ps-ui .ps-preview-item.is-open .ps-chevron { transform: rotate(180deg); color: var(--ps-accent); }
        .ps-ui .ps-preview-detail { padding: 14px 12px; background: #F9FAFB; border-top: 1px solid var(--ps-soft); text-align: center; animation: psSlide .2s ease-out; }
        @keyframes psSlide { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
        .ps-ui .ps-preview-offline { display: flex; gap: 10px; align-items: flex-start; padding: 10px; border-radius: 9px; background: #fff; border: 1px solid var(--ps-soft); }
        .ps-ui .ps-preview-step { margin-top: 10px; font-size: 11.5px; color: var(--ps-muted); display: flex; align-items: center; justify-content: center; gap: 4px; }
        .ps-ui .ps-preview-qr { width: 120px; height: 120px; object-fit: contain; background: #fff; border: 1px solid var(--ps-border); border-radius: 10px; padding: 6px; margin-bottom: 8px; }

        .ps-ui .ps-modal { border: 0; border-radius: 14px; }
        .ps-ui .ps-upload { display: flex; gap: 12px; align-items: flex-start; padding: 12px; border: 1px dashed #D1D5DB; border-radius: 10px; background: #FAFAFB; }
        .ps-ui .ps-accept-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 10px; background: #F9FAFB; border: 1px solid var(--ps-soft); }
    </style>
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
