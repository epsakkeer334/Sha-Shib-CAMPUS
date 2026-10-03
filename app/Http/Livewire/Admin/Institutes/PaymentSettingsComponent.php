<?php

namespace App\Http\Livewire\Admin\Institutes;

use App\Models\Admin\Institute;
use App\Models\Admin\InstitutePaymentGateway;
use App\Models\Admin\PaymentGateway;
use App\Support\SecureUpload;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Institute Management → Payment settings: which payment methods the institute accepts and its
 * UPI ID / QR code / office instructions shown to students on the portal payment step.
 * Super Admin: any institute; others with fees.manage: own institute.
 */
class PaymentSettingsComponent extends Component
{
    use WithFileUploads, RecordsAuditTrail;

    public $instituteId = null;

    // form (one gateway at a time)
    public $editingGatewayId = null;
    public $payee_name, $upi_id, $instructions, $qr_upload;
    public $status = 1;

    public function mount()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        $this->instituteId = Auth::user()->isSuperAdmin() ? null : Auth::user()->institute_id;
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
        }
    }

    public function edit($gatewayId)
    {
        abort_unless($this->instituteId, 404);
        $gateway = PaymentGateway::whereIn('type', ['upi', 'offline'])->findOrFail($gatewayId);
        $setting = $this->setting($gateway->id);

        $this->resetValidation();
        $this->editingGatewayId = $gateway->id;
        $this->payee_name = optional($setting)->payee_name;
        $this->upi_id = optional($setting)->upi_id;
        $this->instructions = optional($setting)->instructions;
        $this->status = $setting ? ($setting->status ? 1 : 0) : 1;
        $this->qr_upload = null;
        $this->dispatchBrowserEvent('open-payment-setting-modal');
    }

    protected function setting($gatewayId): ?InstitutePaymentGateway
    {
        return InstitutePaymentGateway::where('institute_id', $this->instituteId)->where('payment_gateway_id', $gatewayId)->first();
    }

    public function save()
    {
        abort_unless($this->instituteId && $this->editingGatewayId, 404);
        $gateway = PaymentGateway::findOrFail($this->editingGatewayId);
        $isUpi = $gateway->type === 'upi';

        $this->validate([
            'upi_id' => [$isUpi && $this->status ? 'required' : 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/'],
            'payee_name' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'qr_upload' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'status' => 'boolean',
        ], ['upi_id.regex' => 'Enter a valid UPI ID, e.g. institute@okaxis.'], ['upi_id' => 'UPI ID', 'qr_upload' => 'QR code']);

        $setting = InstitutePaymentGateway::firstOrNew(['institute_id' => $this->instituteId, 'payment_gateway_id' => $gateway->id]);
        $old = $setting->exists ? $setting->only(['payee_name', 'upi_id', 'instructions', 'status']) : [];

        $setting->fill([
            'payee_name' => $this->payee_name ?: null,
            'upi_id' => $isUpi ? ($this->upi_id ?: null) : null,
            'instructions' => $this->instructions ?: null,
            'status' => (bool) $this->status,
        ]);

        if ($this->qr_upload) {
            if ($setting->qr_code_path) {
                Storage::disk('local')->delete($setting->qr_code_path);
            }
            $setting->qr_code_path = SecureUpload::store($this->qr_upload, 'institutes/payment-qr');
        }
        $setting->save();

        $this->auditUpdate($setting, 'institute_payment_gateways', $old, $setting->only(['payee_name', 'upi_id', 'instructions', 'status']),
            "Payment settings for {$gateway->name} saved");

        $this->dispatchBrowserEvent('close-payment-setting-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => "{$gateway->name} settings saved."]);
    }

    public function removeQr()
    {
        $setting = $this->editingGatewayId ? $this->setting($this->editingGatewayId) : null;
        if ($setting && $setting->qr_code_path) {
            Storage::disk('local')->delete($setting->qr_code_path);
            $setting->update(['qr_code_path' => null]);
            $this->auditUpdate($setting, 'institute_payment_gateways', ['qr_code_path' => 'set'], ['qr_code_path' => null], 'UPI QR code removed');
        }
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.admin.institutes.payment-settings-component', [
            'institutes' => $user->isSuperAdmin() ? Institute::orderBy('name')->get(['id', 'name', 'code']) : Institute::whereKey($user->institute_id)->get(['id', 'name', 'code']),
            'gateways' => PaymentGateway::orderBy('sort_order')->get(),
            'settings' => $this->instituteId
                ? InstitutePaymentGateway::where('institute_id', $this->instituteId)->get()->keyBy('payment_gateway_id')
                : collect(),
            'editing' => $this->editingGatewayId ? PaymentGateway::find($this->editingGatewayId) : null,
            'editingSetting' => $this->editingGatewayId && $this->instituteId ? $this->setting($this->editingGatewayId) : null,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ])->layout('layouts.admin.master');
    }
}
