<?php

namespace App\Http\Livewire\Admin\Institutes;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Admin\Institute;
use App\Models\Admin\Country;
use App\Models\Admin\State;
use Illuminate\Support\Facades\Storage;
use App\Traits\LogsActivity;

class InstitutesComponent extends Component
{
    use WithPagination, WithFileUploads, LogsActivity;

    protected $paginationTheme = 'bootstrap';

    // Form fields
    public $name, $code, $description, $address, $city, $state_id, $country_id, $postal_code;
    public $contact_person, $email, $phone, $website, $logo, $banner, $about, $status = 1;
    public $recordId;
    public $isEdit = false;
    public $confirmingDeleteId = null;

    // Relationships
    public $countries = [];
    public $states = [];

    // UI state
    public $search = '';
    public $filter = 'All';
    public $sortField = 'name';
    public $sortDirection = 'asc';
    public $perPage = 10;

    protected $listeners = [
        'editRecord' => 'edit',
        'deleteRecord' => 'confirmDelete',
        'refreshTable' => '$refresh',
        'summernoteUpdated' => 'updateAboutValue',
    ];

    protected function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state_id' => 'required|exists:states,id',
            'country_id' => 'required|exists:countries,id',
            'postal_code' => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'website' => 'nullable|url|max:255',
            'about' => 'nullable|string',
            'status' => 'boolean',
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];

        // Add unique validation for name, code, email, phone
        if (!$this->isEdit) {
            // For create: check uniqueness
            $rules['name'] = 'required|string|max:255|unique:institutes,name';
            $rules['code'] = 'required|string|max:50|unique:institutes,code';
            $rules['email'] = 'required|email|max:255|unique:institutes,email';
            $rules['phone'] = 'required|string|max:20|unique:institutes,phone';
        } else {
            // For update: check uniqueness except current record
            $rules['name'] = 'required|string|max:255|unique:institutes,name,' . $this->recordId;
            $rules['code'] = 'required|string|max:50|unique:institutes,code,' . $this->recordId;
            $rules['email'] = 'required|email|max:255|unique:institutes,email,' . $this->recordId;
            $rules['phone'] = 'required|string|max:20|unique:institutes,phone,' . $this->recordId;

            // Logo is not required on update
            $rules['logo'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048';
        }

        // Banner validation (always optional)
        if (!$this->isEdit || $this->banner) {
            $rules['banner'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';
        }

        return $rules;
    }

    protected $messages = [
        'name.required' => 'Institute name is required.',
        'name.max' => 'Name must not exceed 255 characters.',
        'name.unique' => 'This institute name is already taken.',

        'code.required' => 'Institute code is required.',
        'code.max' => 'Code must not exceed 50 characters.',
        'code.unique' => 'This institute code is already taken.',

        'email.required' => 'Email address is required.',
        'email.email' => 'Please enter a valid email address.',
        'email.unique' => 'This email is already registered with another institute.',

        'phone.required' => 'Phone number is required.',
        'phone.unique' => 'This phone number is already registered with another institute.',

        'country_id.required' => 'Please select a country.',
        'country_id.exists' => 'Selected country is invalid.',

        'state_id.required' => 'Please select a state.',
        'state_id.exists' => 'Selected state is invalid.',

        'logo.required' => 'Institute logo is required.',
        'logo.image' => 'The logo must be an image.',
        'logo.mimes' => 'The logo must be a jpeg, png, jpg, gif, or webp file.',
        'logo.max' => 'The logo size must not exceed 2MB.',

        'website.url' => 'Please enter a valid website URL.',

        'banner.image' => 'The banner must be an image.',
        'banner.mimes' => 'The banner must be a jpeg, png, jpg, gif, or webp file.',
        'banner.max' => 'The banner size must not exceed 5MB.',
    ];

    public function mount()
    {
        $this->loadCountries();
    }

    protected function loadCountries()
    {
        $this->countries = Country::where('status', true)
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function loadStates($countryId)
    {
        if ($countryId) {
            $this->states = State::where('country_id', $countryId)
                ->where('status', true)
                ->orderBy('name')
                ->get()
                ->pluck('name', 'id')
                ->toArray();
        } else {
            $this->states = [];
        }
    }

    public function updatedCountryId($value)
    {
        $this->loadStates($value);
        $this->state_id = null;
    }

    public function updateAboutValue($value)
    {
        $this->about = $value;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortField = $field;
    }

    public function openModal()
    {
        $this->resetValidation();
        $this->resetFields();
        $this->isEdit = false;
        $this->dispatchBrowserEvent('open-institute-modal');
        $this->dispatchBrowserEvent('init-summernote', ['content' => '']);
    }

    public function edit($id)
    {
        $this->resetValidation();
        $this->isEdit = true;

        $institute = Institute::with(['country', 'state'])->findOrFail($id);

        $this->fill([
            'recordId' => $institute->id,
            'name' => $institute->name,
            'code' => $institute->code,
            'description' => $institute->description,
            'address' => $institute->address,
            'city' => $institute->city,
            'state_id' => $institute->state_id,
            'country_id' => $institute->country_id,
            'postal_code' => $institute->postal_code,
            'contact_person' => $institute->contact_person,
            'email' => $institute->email,
            'phone' => $institute->phone,
            'website' => $institute->website,
            'about' => $institute->about,
            'status' => $institute->status ? 1 : 0,
        ]);

        // Load states based on selected country
        if ($this->country_id) {
            $this->loadStates($this->country_id);
        }

        $this->dispatchBrowserEvent('open-institute-modal');
        $this->dispatchBrowserEvent('init-summernote', ['content' => $this->about]);
    }

    public function closeModal()
    {
        $this->resetFields();
        $this->dispatchBrowserEvent('close-institute-modal');
        $this->dispatchBrowserEvent('destroy-summernote');
    }

    protected function resetFields()
    {
        $this->reset([
            'recordId', 'name', 'code', 'description', 'address', 'city', 'state_id', 'country_id',
            'postal_code', 'contact_person', 'email', 'phone', 'website', 'logo', 'banner',
            'about', 'status', 'isEdit'
        ]);
        $this->status = 1;
        $this->states = [];
    }

    protected function storeImage($image, $folder = 'institutes')
    {
        // Generate unique filename
        $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

        // Store the image
        $image->storeAs("public/{$folder}", $imageName);

        return $imageName;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'postal_code' => $this->postal_code,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'about' => $this->about,
            'status' => $this->status,
        ];

        // Handle institute logo upload (required)
        if ($this->logo) {
            $logoName = $this->storeImage($this->logo, 'institutes/logos');
            $data['logo'] = $logoName;
        }

        // Handle institute banner upload (optional)
        if ($this->banner) {
            $bannerName = $this->storeImage($this->banner, 'institutes/banners');
            $data['banner'] = $bannerName;
        }

        $institute = Institute::create($data);

        // Log the activity
        $this->logCreate($institute, 'institutes', "Created new institute: {$institute->name} ({$institute->code})");

        $this->closeModal();
        $this->emit('refreshTable');
        $this->dispatchBrowserEvent('show-toast', [
            'type' => 'success',
            'message' => 'Institute created successfully.',
        ]);
    }

    public function update()
    {
        $this->validate();

        $institute = Institute::findOrFail($this->recordId);

        // Capture old values before update
        $oldValues = $institute->only([
            'name', 'code', 'description', 'address', 'city', 'state_id', 'country_id',
            'postal_code', 'contact_person', 'email', 'phone', 'website', 'logo',
            'banner', 'about', 'status'
        ]);

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'postal_code' => $this->postal_code,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'about' => $this->about,
            'status' => $this->status,
        ];

        // Handle institute logo upload (optional on update)
        if ($this->logo) {
            // Delete old logo
            if ($institute->logo) {
                Storage::delete('public/institutes/logos/' . $institute->logo);
            }
            $logoName = $this->storeImage($this->logo, 'institutes/logos');
            $data['logo'] = $logoName;
        }

        // Handle institute banner upload (optional)
        if ($this->banner) {
            // Delete old banner
            if ($institute->banner) {
                Storage::delete('public/institutes/banners/' . $institute->banner);
            }
            $bannerName = $this->storeImage($this->banner, 'institutes/banners');
            $data['banner'] = $bannerName;
        }

        $institute->update($data);

        // Log the update with old and new values
        $newValues = $institute->only([
            'name', 'code', 'description', 'address', 'city', 'state_id', 'country_id',
            'postal_code', 'contact_person', 'email', 'phone', 'website', 'logo',
            'banner', 'about', 'status'
        ]);
        $this->logActivity(
            action: 'update',
            module: 'institutes',
            model: $institute,
            oldValues: $oldValues,
            newValues: $newValues,
            description: "Updated institute: {$institute->name} ({$institute->code})"
        );

        $this->closeModal();
        $this->emit('refreshTable');
        $this->dispatchBrowserEvent('show-toast', [
            'type' => 'success',
            'message' => 'Institute updated successfully.',
        ]);
    }

    public function confirmDelete($id)
    {
        $this->confirmingDeleteId = $id;
        $this->dispatchBrowserEvent('open-delete-modal');
    }

    public function delete()
    {
        $institute = Institute::find($this->confirmingDeleteId);

        if ($institute) {
            // Delete institute logo if exists
            if ($institute->logo) {
                Storage::delete('public/institutes/logos/' . $institute->logo);
            }

            // Delete institute banner if exists
            if ($institute->banner) {
                Storage::delete('public/institutes/banners/' . $institute->banner);
            }

            // Log the delete activity
            $this->logDelete($institute, 'institutes', "Deleted institute: {$institute->name} ({$institute->code})");

            $institute->delete();

            $this->emit('refreshTable');
            $this->dispatchBrowserEvent('show-toast', [
                'type' => 'danger',
                'message' => 'Institute deleted successfully.',
            ]);
        }

        $this->confirmingDeleteId = null;
        $this->dispatchBrowserEvent('close-delete-modal');
    }

    public function getInstitutesProperty()
    {
        $query = Institute::with(['country', 'state']);

        // Filter by status
        if ($this->filter !== 'All') {
            $query->where('status', $this->filter === 'Active' ? 1 : 0);
        }

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('code', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%")
                  ->orWhere('city', 'like', "%{$this->search}%")
                  ->orWhere('address', 'like', "%{$this->search}%")
                  ->orWhereHas('country', function ($countryQuery) {
                      $countryQuery->where('name', 'like', "%{$this->search}%");
                  })
                  ->orWhereHas('state', function ($stateQuery) {
                      $stateQuery->where('name', 'like', "%{$this->search}%");
                  })
                  ->orWhere('contact_person', 'like', "%{$this->search}%");
            });
        }

        // Sort
        $query->orderBy($this->sortField, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.admin.institutes.institutes-component', [
            'institutes' => $this->institutes,
        ])->layout('layouts.admin.master');
    }
}
