{{-- Student sub-navigation: Onboarding · Fees & Payments · ER & ID card. Params: $student, $active --}}
@php $user = auth()->user(); @endphp
<ul class="nav nav-pills gap-1 mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $active === 'onboarding' ? 'active' : 'bg-light text-dark' }}" href="{{ route('admin.students.edit', $student->id) }}">
            <i class="ti ti-user me-1"></i> Onboarding
        </a>
    </li>
    @if($user->can('fees.manage') || $user->can('payments.collect') || $user->can('payments.verify'))
        <li class="nav-item">
            <a class="nav-link {{ $active === 'fees' ? 'active' : 'bg-light text-dark' }}" href="{{ route('admin.students.fees', $student->id) }}">
                <i class="ti ti-cash me-1"></i> Fees &amp; Payments
            </a>
        </li>
    @endif
    <li class="nav-item">
        <a class="nav-link {{ $active === 'enrollment' ? 'active' : 'bg-light text-dark' }}" href="{{ route('admin.students.enrollment', $student->id) }}">
            <i class="ti ti-id me-1"></i> Gates, ER &amp; ID card
        </a>
    </li>
</ul>
