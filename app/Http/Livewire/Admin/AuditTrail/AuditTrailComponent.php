<?php

namespace App\Http\Livewire\Admin\AuditTrail;

use App\Models\Admin\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Read-only audit trail. Super Admin sees all institutes; Institute Admin only their own.
 */
class AuditTrailComponent extends Component
{
    public $selected = null;

    protected $listeners = ['viewRecord' => 'view'];

    public function mount()
    {
        abort_unless(Auth::user()->can('audit.view'), 403);
    }

    public function view($id)
    {
        $entry = AuditTrail::visibleTo(Auth::user())->with(['user', 'institute'])->findOrFail($id);

        $this->selected = [
            'when' => $entry->formatted_created_at,
            'user' => optional($entry->user)->name ?? 'System',
            'institute' => optional($entry->institute)->name ?? '—',
            'action' => $entry->action,
            'module' => $entry->module,
            'reference' => $entry->reference_label,
            'ip' => $entry->ip_address,
            'meta' => $entry->meta ?? [],
        ];

        $this->dispatchBrowserEvent('open-audit-modal');
    }

    public function render()
    {
        return view('livewire.admin.audit-trail.audit-trail-component')->layout('layouts.admin.master');
    }
}
