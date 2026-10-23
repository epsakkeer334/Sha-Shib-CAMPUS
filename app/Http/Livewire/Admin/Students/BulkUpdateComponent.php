<?php

namespace App\Http\Livewire\Admin\Students;

use App\Models\Admin\Batch;
use App\Models\Admin\Institute;
use App\Models\Admin\StudentImport;
use App\Services\StudentBulkService;
use App\Support\SecureUpload;
use App\Traits\RecordsAuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Students ▸ Bulk Update (Module 2 step 2.9): download a CSV template (pre-filled with the filtered students
 * or blank) → upload → validation preview → apply the valid rows. Import history with error reports.
 */
class BulkUpdateComponent extends Component
{
    use WithFileUploads, RecordsAuditTrail;

    // template filters
    public $institute = '';
    public $course = '';
    public $batch = '';
    public $status = '';

    public $file;
    public $importId = null;
    public $show = 'all'; // all | error | new | update | unchanged
    public $result = null;

    protected $queryString = ['importId' => ['except' => null, 'as' => 'import']];

    public function mount()
    {
        abort_unless(Auth::user()->can('students.bulk_update'), 403);
        if (!Auth::user()->isSuperAdmin()) {
            $this->institute = (string) Auth::user()->institute_id;
        }
        if ($this->importId && !StudentImport::find($this->importId)) {
            $this->importId = null;
        }
    }

    public function hydrate()
    {
        abort_unless(Auth::user()->can('students.bulk_update'), 403);
    }

    public function updatedInstitute()
    {
        $this->course = $this->batch = '';
    }

    public function updatedCourse()
    {
        $this->batch = '';
    }

    protected function filters(): array
    {
        return ['institute' => Auth::user()->isSuperAdmin() ? $this->institute : Auth::user()->institute_id, 'course' => $this->course, 'batch' => $this->batch, 'status' => $this->status];
    }

    // ------------------------------------------------------------------ downloads

    public function downloadStudents()
    {
        $service = app(StudentBulkService::class);
        $csv = $service->templateCsv($service->studentsQuery($this->filters())->get());
        $this->audit('export', 'students', null, ['description' => 'Downloaded the bulk update template with students']);

        return response()->streamDownload(fn () => print($csv), 'camp-students-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadBlank()
    {
        $csv = app(StudentBulkService::class)->templateCsv();

        return response()->streamDownload(fn () => print($csv), 'camp-students-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadErrors($id)
    {
        $import = StudentImport::findOrFail($id);
        $csv = app(StudentBulkService::class)->errorReportCsv($import->preview ?? []);

        return response()->streamDownload(fn () => print($csv), "camp-import-{$import->id}-errors.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ upload → preview

    public function updatedFile()
    {
        $this->resetValidation();
        $this->validate(['file' => 'required|file|mimes:csv,txt|max:2048'], [
            'file.mimes' => 'Upload the template saved as CSV (.csv).',
            'file.max' => 'The file may not be larger than 2 MB (about 1,000 students).',
        ]);

        $service = app(StudentBulkService::class);
        $parsed = $service->parse($this->file->getRealPath());
        if ($parsed['error']) {
            $this->addError('file', $parsed['error']);
            $this->reset('file');

            return;
        }

        $preview = $service->validateRows($parsed['rows'], Auth::user());
        $preview['warnings'] = $parsed['warnings'];
        $path = SecureUpload::store($this->file, 'student-imports');

        $import = StudentImport::create([
            'institute_id' => Auth::user()->isSuperAdmin() ? ($this->institute ?: null) : Auth::user()->institute_id,
            'user_id' => Auth::id(),
            'file_name' => mb_substr($this->file->getClientOriginalName(), 0, 200),
            'file_path' => $path,
            'status' => 'previewed',
            'rows_total' => $preview['counts']['total'],
            'rows_new' => $preview['counts']['new'],
            'rows_updated' => $preview['counts']['update'],
            'rows_unchanged' => $preview['counts']['unchanged'],
            'rows_failed' => $preview['counts']['error'],
            'preview' => $preview,
        ]);
        $this->audit('upload', 'student_imports', $import, ['description' => "Bulk update file uploaded: {$import->file_name} ({$import->rows_total} rows)"]);

        $this->reset('file');
        $this->importId = $import->id;
        $this->result = null;
        $this->show = $preview['counts']['error'] ? 'error' : 'all';
    }

    public function apply()
    {
        $import = StudentImport::findOrFail($this->importId);
        if ($import->status !== 'previewed' || $import->expired) {
            $this->dispatchBrowserEvent('close-bulk-apply-modal');
            $this->dispatchBrowserEvent('show-toast', ['type' => 'warning', 'message' => $import->expired ? 'This preview is older than 24 hours — upload the file again.' : 'This file was already applied.']);

            return;
        }

        $result = app(StudentBulkService::class)->apply($import->preview['rows'] ?? [], $import->id);
        $import->update(['status' => 'applied', 'applied_at' => now(), 'result' => $result]);
        $this->audit('apply', 'student_imports', $import, ['description' => "Bulk update #{$import->id} applied: {$result['created']} created, {$result['updated']} updated, " . count($result['skipped']) . ' skipped']);

        $this->result = $result;
        $this->dispatchBrowserEvent('close-bulk-apply-modal');
        $this->dispatchBrowserEvent('show-toast', ['type' => $result['skipped'] ? 'warning' : 'success',
            'message' => "Applied: {$result['created']} created, {$result['updated']} updated" . ($result['skipped'] ? ', ' . count($result['skipped']) . ' skipped' : '') . '.']);
    }

    public function cancelImport()
    {
        $import = StudentImport::find($this->importId);
        if ($import && $import->status === 'previewed') {
            $import->update(['status' => 'cancelled']);
            if ($import->file_path) {
                Storage::disk('local')->delete($import->file_path);
            }
        }
        $this->importId = null;
        $this->result = null;
    }

    public function open($id)
    {
        $this->importId = StudentImport::findOrFail($id)->id;
        $this->result = null;
        $this->show = 'all';
    }

    public function render()
    {
        $user = Auth::user();
        $service = app(StudentBulkService::class);
        $import = $this->importId ? StudentImport::find($this->importId) : null;
        $rows = collect(optional($import)->preview['rows'] ?? []);
        $institutes = $user->isSuperAdmin() ? Institute::orderBy('name')->get(['id', 'name', 'code']) : collect();
        $instituteId = $user->isSuperAdmin() ? $this->institute : $user->institute_id;
        $courses = $instituteId
            ? optional(Institute::find($instituteId))->offeredCourses()?->orderBy('name')->get(['courses.id', 'courses.name', 'courses.code']) ?? collect()
            : \App\Models\Admin\Course::orderBy('name')->get(['id', 'name', 'code']);

        return view('livewire.admin.students.bulk-update-component', [
            'isSuperAdmin' => $user->isSuperAdmin(),
            'canEr' => $user->can('students.manage_er'),
            'institutes' => $institutes,
            'courses' => $courses,
            'batches' => $this->course ? Batch::where('course_id', $this->course)->when($instituteId, fn ($q) => $q->where('institute_id', $instituteId))->orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'matching' => $service->studentsQuery($this->filters())->count(),
            'columns' => StudentBulkService::COLUMNS,
            'import' => $import,
            'rows' => $this->show === 'all' ? $rows : $rows->where('action', $this->show),
            'counts' => optional($import)->preview['counts'] ?? null,
            'history' => StudentImport::with('user')->latest('id')->take(12)->get(),
        ])->layout('layouts.admin.master');
    }
}
