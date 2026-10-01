<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h3 class="page-title">Dashboard</h3>
                <p class="text-muted mb-0">Sha Shib CAMPUS administration overview</p>
            </div>
            <div class="col-auto">
                <span class="badge bg-primary">{{ auth()->user()->getRoleNames()->first() ?? 'User' }}</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted">Institutions</span>
                            <h3 class="mb-0">{{ \App\Models\Admin\Institute::count() }}</h3>
                        </div>
                        <span class="avatar avatar-lg bg-primary text-white"><i class="ti ti-building-community"></i></span>
                    </div>
                    @hasrole('super-admin')
                        <a href="{{ route('admin.institutes') }}" class="btn btn-sm btn-outline-primary mt-3">Manage Institutes</a>
                    @endhasrole
                </div>
            </div>
        </div>
    </div>
</div>



