<?php

namespace App\Http\Livewire\Portal\Concerns;

use App\Models\Admin\Student;
use App\Support\PortalProgress;
use Illuminate\Support\Facades\Auth;

/**
 * Shared helpers of the signed-in student pages (route middleware "student" guarantees
 * a student account with an application).
 */
trait StudentPortalPage
{
    protected function student(): Student
    {
        return Student::where('user_id', Auth::id())->firstOrFail();
    }

    /**
     * Details can be changed at any stage until a fee payment is confirmed.
     */
    protected function canEditDetails(Student $student): bool
    {
        return PortalProgress::canEdit($student);
    }

    /**
     * Autosave of fields the student typed but did not save yet (restored after sign-in).
     * Pages list their draftable fields in $draftFields and their step in $step.
     */
    public function updated($property, $value)
    {
        if (property_exists($this, 'draftFields') && in_array($property, $this->draftFields, true) && Auth::check()) {
            $student = $this->student();
            if ($this->canEditDetails($student)) {
                PortalProgress::saveDraftField($student, $this->step, $property, $value);
            }
        }

        if (method_exists($this, 'afterUpdated')) {
            $this->afterUpdated($property, $value);
        }
    }

    /**
     * Put back unsaved draft values over the saved ones.
     */
    protected function restoreDraft(Student $student): bool
    {
        $draft = array_intersect_key(PortalProgress::draft($student, $this->step), array_flip($this->draftFields));
        foreach ($draft as $field => $value) {
            $this->{$field} = $value;
        }

        return !empty($draft);
    }

    protected function toast(string $type, string $message): void
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }
}
