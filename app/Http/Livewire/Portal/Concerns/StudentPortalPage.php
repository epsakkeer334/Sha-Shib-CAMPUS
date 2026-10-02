<?php

namespace App\Http\Livewire\Portal\Concerns;

use App\Models\Admin\Student;
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
     * Personal / academic details can be changed until the application is submitted,
     * or again when it was returned for corrections.
     */
    protected function canEditDetails(Student $student): bool
    {
        return in_array($student->status, ['draft', 'rejected'], true);
    }

    protected function toast(string $type, string $message): void
    {
        $this->dispatchBrowserEvent('show-toast', ['type' => $type, 'message' => $message]);
    }
}
