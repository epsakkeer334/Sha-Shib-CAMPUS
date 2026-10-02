<main class="main" style="max-width: 520px;">
    <div style="display: flex; flex-direction: column; gap: 8px;">
        <span class="eyebrow">Admission application</span>
        <h1 class="title">Sign in</h1>
        <p class="lead">Continue your application, upload documents, pay fees and track your status.</p>
    </div>

    <form class="card" wire:submit.prevent="login">
        @if($errorMessage)
            <div class="notice danger" role="alert" style="padding: 12px 16px; border-radius: 10px;">{{ $errorMessage }}</div>
        @endif
        @include('portal.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'autocomplete' => 'username'])
        @include('portal.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password'])
        <label style="display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" wire:model.defer="remember"> <span>Keep me signed in on this device</span>
        </label>
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Sign in</button>
        <p class="muted" style="margin: 0; font-size: 14px;">
            New here? <a href="{{ route('portal.register') }}" style="font-weight: 600;">Start your application</a>.
            Forgot your password? Contact the admissions office.
        </p>
    </form>
</main>
