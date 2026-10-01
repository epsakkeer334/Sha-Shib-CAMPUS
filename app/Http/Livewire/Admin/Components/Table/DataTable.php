<?php

namespace App\Http\Livewire\Admin\Components\Table;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class DataTable extends Component
{
    use WithPagination;

    public $columns = [];
    public $search = '';
    public $sortField = 'id';
    public $sortDirection = 'asc';
    public $perPage = 10;
    public $filters = [];
    public $filter = 'All Status';
    public $filterField = 'status';
    public $modelClass;
    public $actionView = null;
    public $title = 'Data List';
    public $extraFilters = [];
    public $queryModifier = null;

    // Add these properties with defaults to prevent errors
    public $showVendorColumn = false;
    public $vendorFilterEnabled = false;
    public $vendorId = null;

    // Soft delete properties
    public bool $showDeletedButton = false;
    public ?string $deletedModelClass = null;
    public array $deletedColumns = [];
    public string $deletedTitle = 'Deleted Records';
    public array $deletedConfig = [];

    protected $paginationTheme = 'bootstrap';
    protected $listeners = ['refreshTable' => '$refresh', 'filterUpdated' => 'setFilter'];

    // --- Mount component ---
    public function mount(
        $modelClass,
        $columns,
        $filters = [],
        $filterField = 'status',
        $actionView = null,
        $title = null,
        $extraFilters = [],
        $queryModifier = null,
        $perPage = 10,
        $sortField = 'id',
        $sortDirection = 'asc',
        $search = '',
        $deletedConfig = []
    ) {
        $this->modelClass = $modelClass;
        $this->columns = $columns;
        $this->filters = $filters;
        $this->filterField = $filterField;
        $this->actionView = $actionView;
        if ($title) $this->title = $title;
        $this->extraFilters = $extraFilters;
        $this->queryModifier = $queryModifier;
        $this->perPage = $perPage;
        $this->sortField = $sortField;
        $this->sortDirection = $sortDirection;
        $this->search = $search;
        $this->deletedConfig = $deletedConfig ?: ['enabled' => false];

        // Initialize vendor-related properties to false by default
        $this->showVendorColumn = false;
        $this->vendorFilterEnabled = false;
        $this->vendorId = null;
    }

    // Check if user is super admin - simplified since no vendor functionality
    public function isSuperAdmin()
    {
        $user = auth()->user();
        if (!$user) return false;

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('super-admin');
        }

        return strtolower($user->role ?? '') === 'super-admin';
    }

    // Get vendor ID for vendor users - return null since no vendor functionality
    public function getVendorId()
    {
        return null; // No vendor functionality in this project
    }

    // Determine if vendor column should be shown - always false
    public function shouldShowVendorColumn()
    {
        return false; // Disable vendor column since no vendor functionality
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    // --- Sorting ---
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    // --- Get all relations to eager load ---
    protected function getRelationsToLoad()
    {
        $relations = [];

        foreach ($this->columns as $column) {
            if (Str::contains($column['field'], '.')) {
                $relation = explode('.', $column['field'])[0];
                $relations[] = $relation;
            }
        }

        // Check extra filters for relation fields
        foreach ($this->extraFilters as $filter) {
            if (isset($filter['field']) && Str::contains($filter['field'], '.')) {
                $relation = explode('.', $filter['field'])[0];
                $relations[] = $relation;
            }
        }

        // Remove vendor relation since it's not needed
        // if ($this->showVendorColumn || $this->vendorFilterEnabled) {
        //     $relations[] = 'vendor';
        // }

        return array_unique($relations);
    }

    // --- Apply search with relation support ---
    protected function applySearch($query)
    {
        if (!$this->search) return;

        $query->where(function ($q) {
            foreach ($this->columns as $column) {
                if ($column['field'] === 'actions' || !isset($column['field'])) continue;

                $field = $column['field'];

                // Skip if the column is not searchable (you can add a 'searchable' flag to columns if needed)
                if (isset($column['searchable']) && $column['searchable'] === false) continue;

                if (Str::contains($field, '.')) {
                    // Handle relationship search (e.g., 'user.name')
                    $this->applyRelationshipSearch($q, $field);
                } else {
                    // Handle regular field search
                    $table = (new $this->modelClass)->getTable();

                    // Check if column exists in table
                    if (Schema::hasColumn($table, $field)) {
                        $q->orWhere("{$table}.{$field}", 'like', '%' . $this->search . '%');
                    }
                }
            }
        });
    }

    // --- Apply search on relationship fields ---
    protected function applyRelationshipSearch($query, $field)
    {
        $parts = explode('.', $field);
        $relation = $parts[0];
        $relatedField = $parts[1];

        // Check if it's a nested relation (e.g., 'user.profile.name')
        if (count($parts) > 2) {
            // Handle nested relations by building the whereHas chain
            $this->applyNestedRelationshipSearch($query, $parts);
        } else {
            // Simple relation search
            $query->orWhereHas($relation, function ($relationQuery) use ($relatedField) {
                $relationQuery->where($relatedField, 'like', '%' . $this->search . '%');
            });
        }
    }

    // --- Apply search on nested relationship fields ---
    protected function applyNestedRelationshipSearch($query, $parts)
    {
        $relationPath = implode('.', array_slice($parts, 0, -1));
        $field = end($parts);

        $query->orWhereHas($relationPath, function ($relationQuery) use ($field) {
            $relationQuery->where($field, 'like', '%' . $this->search . '%');
        });
    }

    // --- Apply filters with relation support ---
    protected function applyFilters($query)
    {
        // Main filter (status filter)
        if ($this->filter !== 'All Status' && $this->filter !== 'All Category' && $this->filter !== '') {
            $filterValue = $this->filter;

            // Check if we're filtering by a relation or direct column
            if (Str::contains($this->filterField, '.')) {
                $parts = explode('.', $this->filterField);
                $relation = $parts[0];
                $field = $parts[1];

                $query->whereHas($relation, function ($q) use ($field, $filterValue) {
                    if (in_array($filterValue, ['Active', 'Inactive'])) {
                        $q->where($field, $filterValue === 'Active' ? 1 : 0);
                    } else {
                        $q->where($field, $filterValue);
                    }
                });
            } else {
                // Direct column filter
                if (in_array($filterValue, ['Active', 'Inactive'])) {
                    $query->where($this->filterField, $filterValue === 'Active' ? 1 : 0);
                } else {
                    $query->where($this->filterField, $filterValue);
                }
            }
        }

        // Apply extra filters
        foreach ($this->extraFilters as $extraFilter) {
            $field = $extraFilter['field'] ?? null;
            $dbField = $extraFilter['db_field'] ?? $field;
            $value = $extraFilter['value'] ?? null;

            // If value is not set in the filter config, check if it's a property in the component
            if ($value === null && property_exists($this, $field)) {
                $value = $this->$field;
            }

            if ($value && $value !== '' && $value !== null) {
                if (Str::contains($dbField, '.')) {
                    // Relation filter
                    $parts = explode('.', $dbField);
                    $relation = $parts[0];
                    $relatedField = $parts[1];

                    $query->whereHas($relation, function ($q) use ($relatedField, $value) {
                        $q->where($relatedField, $value);
                    });
                } else {
                    // Direct column filter
                    $query->where($dbField, $value);
                }
            }
        }
    }

    // --- Apply sorting with relation support ---
    protected function applySorting($query)
    {
        if (Str::contains($this->sortField, '.')) {
            $parts = explode('.', $this->sortField);
            $relation = $parts[0];
            $field = $parts[1];

            // Handle sorting on relationship fields
            $model = app($this->modelClass);

            // Get the related model and table
            if (method_exists($model, $relation)) {
                $relatedModel = $model->$relation()->getRelated();
                $relatedTable = $relatedModel->getTable();

                // Get the foreign key
                $foreignKey = $model->$relation()->getForeignKeyName();

                // Perform the join and order
                $query->leftJoin($relatedTable, "{$relatedTable}.id", '=', $foreignKey)
                    ->orderBy("{$relatedTable}.{$field}", $this->sortDirection)
                    ->select($model->getTable() . '.*');
            }
        } else {
            // Direct sorting
            $query->orderBy($this->sortField, $this->sortDirection);
        }
    }

    // --- Render ---
    public function render()
    {
        $query = app($this->modelClass)::query();

        // Apply query modifier if provided
        if ($this->queryModifier && is_callable($this->queryModifier)) {
            $query = call_user_func($this->queryModifier, $query);
        }

        // Get all relations to eager load
        $relationsToLoad = $this->getRelationsToLoad();
        if (!empty($relationsToLoad)) {
            $query->with($relationsToLoad);
        }

        // Remove vendor filtering since no vendor functionality
        // Apply vendor filtering
        // if ($this->vendorFilterEnabled && $this->vendorId && !$this->isSuperAdmin()) {
        //     $query->where('vendor_id', $this->vendorId);
        // }

        // Apply search
        $this->applySearch($query);

        // Apply filters
        $this->applyFilters($query);

        // Apply sorting
        $this->applySorting($query);

        $rows = $query->paginate($this->perPage);

        return view('livewire.admin.components.table.data-table', compact('rows'));
    }

    // --- Render action buttons for each row ---
    public function getActionHtml($row)
    {
        if ($this->actionView) {
            return view($this->actionView, ['row' => $row])->render();
        }
        return '';
    }
}
