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
    public $search = ''; // Super Admin institute overview
    public $previewGatewayId = null; // "What students see": option opened in the preview (-1 = all collapsed)

    protected $queryString = ['instituteId' => ['except' => null, 'as' => 'institute']];

    // form (one gateway at a time)
    public $editingGatewayId = null;
    public $payee_name, $upi_id, $instructions, $qr_upload;
    public $status = 1;
    // online gateways: public key / merchant id in merchant_id; secrets go to the encrypted credentials column
    // and are never sent back to the browser (blank = keep the saved value)
    public $merchant_id, $secret, $webhook_secret;
    public $is_test_mode = 1;

    /**
     * Field labels per online gateway code: [public id label, secret label, help].
     */
    const ONLINE_FIELDS = [
        'razorpay' => ['Key ID', 'Key secret', 'Razorpay Dashboard → Account & Settings → API keys.'],
        'payu' => ['Merchant key', 'Merchant salt', 'PayU Dashboard → Developers → API keys.'],
        'phonepe' => ['Merchant ID', 'Salt key', 'PhonePe Business Dashboard → Developer settings.'],
    ];
    const ONLINE_DEFAULT = ['Merchant / key ID', 'Secret key', 'From the gateway\'s merchant dashboard.'];

    public function mount()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        // Super Admin: overview first, or the institute from ?institute= ; others: own institute
        $this->instituteId = Auth::user()->isSuperAdmin()
            ? optional(Institute::find($this->instituteId ?: request()->query('institute')))->id
            : Auth::user()->institute_id;
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('fees.manage'), 403);
        if (!Auth::user()->isSuperAdmin()) {
            $this->instituteId = Auth::user()->institute_id;
        }
    }

    public function selectInstitute($id)
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
        $this->instituteId = Institute::findOrFail($id)->id;
        $this->editingGatewayId = null;
    }

    /** Preview panel: open a method's details, or collapse it when it is already open. */
    public function previewMethod($gatewayId, $isOpen = false)
    {
        $this->previewGatewayId = $isOpen ? -1 : (int) $gatewayId;
    }

    public function backToInstitutes()
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);
        $this->instituteId = null;
        $this->editingGatewayId = null;
    }

    /**
     * Quick on/off from the method card. Turning UPI on without a UPI ID opens the set-up form instead.
     */
    public function toggleAccept($gatewayId)
    {
        abort_unless($this->instituteId, 404);
        $gateway = PaymentGateway::findOrFail($gatewayId);
        $setting = $this->setting($gateway->id);
        $enable = !optional($setting)->status;

        if ($enable && !$gateway->status) {
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => "{$gateway->name} is inactive in Master Data."]);

            return;
        }
        if ($enable && $gateway->type === 'upi' && !optional($setting)->upi_id) {
            $this->edit($gateway->id);
            $this->dispatchBrowserEvent('show-toast', ['type' => 'info', 'message' => "Add the UPI ID to accept {$gateway->name}."]);

            return;
        }
        if ($enable && $gateway->type === 'online' && !static::onlineConfigured($setting)) {
            $this->edit($gateway->id);
            $this->dispatchBrowserEvent('show-toast', ['type' => 'info', 'message' => "Add the {$gateway->name} API credentials first."]);

            return;
        }

        $setting = $setting ?: new InstitutePaymentGateway(['institute_id' => $this->instituteId, 'payment_gateway_id' => $gateway->id]);
        $old = $setting->exists ? ['status' => $setting->status] : [];
        $setting->status = $enable;
        $setting->save();
        $this->auditUpdate($setting, 'institute_payment_gateways', $old, ['status' => $enable], ($enable ? 'Accepted ' : 'Stopped accepting ') . $gateway->name);
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => $enable ? "{$gateway->name} is now accepted." : "{$gateway->name} is no longer accepted."]);
    }

    public function edit($gatewayId)
    {
        abort_unless($this->instituteId, 404);
        $gateway = PaymentGateway::findOrFail($gatewayId);
        $setting = $this->setting($gateway->id);

        $this->resetValidation();
        $this->editingGatewayId = $gateway->id;
        $this->payee_name = optional($setting)->payee_name;
        $this->upi_id = optional($setting)->upi_id;
        $this->instructions = optional($setting)->instructions;
        $this->merchant_id = optional($setting)->merchant_id;
        $this->secret = $this->webhook_secret = null; // never sent to the browser
        $this->is_test_mode = $setting ? ($setting->is_test_mode ? 1 : 0) : 1;
        // online gateways start switched off until the credentials are saved
        $this->status = $setting ? ($setting->status ? 1 : 0) : ($gateway->type === 'online' ? 0 : 1);
        $this->qr_upload = null;
        $this->dispatchBrowserEvent('open-payment-setting-modal');
    }

    protected function setting($gatewayId): ?InstitutePaymentGateway
    {
        return InstitutePaymentGateway::where('institute_id', $this->instituteId)->where('payment_gateway_id', $gatewayId)->first();
    }

    /** An online gateway is configured when its public id and secret are saved. */
    public static function onlineConfigured(?InstitutePaymentGateway $setting): bool
    {
        return $setting && $setting->merchant_id && !empty(($setting->credentials ?? [])['secret']);
    }

    protected function saveOnline(PaymentGateway $gateway)
    {
        $setting = InstitutePaymentGateway::firstOrNew(['institute_id' => $this->instituteId, 'payment_gateway_id' => $gateway->id]);
        $saved = $setting->credentials ?? [];
        [$idLabel, $secretLabel] = self::ONLINE_FIELDS[$gateway->code] ?? self::ONLINE_DEFAULT;

        $this->validate([
            'merchant_id' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9_\-.:]+$/'],
            'secret' => [empty($saved['secret']) ? 'required' : 'nullable', 'string', 'min:6', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'is_test_mode' => 'boolean',
            'status' => ['boolean', function ($attribute, $value, $fail) use ($gateway) {
                if ($value && !$gateway->status) {
                    $fail("{$gateway->name} is inactive in Master Data, so it can't be enabled.");
                }
            }],
        ], ['merchant_id.regex' => "The {$idLabel} may only contain letters, numbers and - _ . :"], ['merchant_id' => $idLabel, 'secret' => $secretLabel, 'webhook_secret' => 'webhook secret']);

        $old = $setting->exists ? $setting->only(['merchant_id', 'is_test_mode', 'status']) : [];
        $setting->fill([
            'merchant_id' => trim($this->merchant_id),
            'credentials' => array_filter([
                'secret' => $this->secret ?: ($saved['secret'] ?? null),
                'webhook_secret' => $this->webhook_secret ?: ($saved['webhook_secret'] ?? null),
            ]),
            'is_test_mode' => (bool) $this->is_test_mode,
            'status' => (bool) $this->status,
        ]);
        $setting->save();

        // Audit without secrets
        $this->auditUpdate($setting, 'institute_payment_gateways', $old, $setting->only(['merchant_id', 'is_test_mode', 'status']) + ['credentials' => 'updated'],
            "Online gateway {$gateway->name} configured");

        $this->secret = $this->webhook_secret = null;
        $this->dispatchBrowserEvent('close-payment-setting-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => 'success', 'message' => "{$gateway->name} credentials saved securely."]);
    }

    public function save()
    {
        abort_unless($this->instituteId && $this->editingGatewayId, 404);
        $gateway = PaymentGateway::findOrFail($this->editingGatewayId);
        if ($gateway->type === 'online') {
            return $this->saveOnline($gateway);
        }
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

        // Super Admin overview (no institute chosen): every institute with the methods it accepts
        $overview = collect();
        if ($user->isSuperAdmin() && !$this->instituteId) {
            $accepted = InstitutePaymentGateway::with('gateway')->where('status', true)->get()->groupBy('institute_id');
            $overview = Institute::when(trim($this->search), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
                ->orderBy('name')->get(['id', 'name', 'code', 'city'])
                ->map(function ($institute) use ($accepted) {
                    $rows = $accepted->get($institute->id, collect())->filter(fn ($r) => $r->gateway && $r->gateway->status);
                    $institute->accepted = $rows->pluck('gateway')->sortBy('sort_order')->values();
                    $institute->upi_ready = $rows->contains(fn ($r) => $r->gateway->type === 'upi' && $r->upi_id);
                    $institute->has_qr = $rows->contains(fn ($r) => $r->qr_code_path);

                    return $institute;
                });
        }

        return view('livewire.admin.institutes.payment-settings-component', [
            'overview' => $overview,
            'currentInstitute' => $this->instituteId ? Institute::find($this->instituteId, ['id', 'name', 'code', 'city']) : null,
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
