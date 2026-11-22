@extends('layouts.staff')

@section('title', 'My Profile - SMARTDTR')

@section('content')
<x-page-header title="My Profile" subtitle="Your account and personnel information" />

<x-avatar-upload :user="$staff" guard="staff" />

<div class="app-card max-w-2xl">
    <div class="app-card-header">
        <h2 class="app-card-title">Account Details</h2>
    </div>
    <div class="app-card-body space-y-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-slate-500">Name</span><p class="font-medium">{{ $staff->name }}</p></div>
            <div><span class="text-slate-500">Email</span><p class="font-medium">{{ $staff->email }}</p></div>
            <div><span class="text-slate-500">Mobile</span><p class="font-medium">{{ $staff->phone ?? '—' }}</p></div>
            <div><span class="text-slate-500">Account Type</span><p class="font-medium">{{ $staff->roleLabel() }}</p></div>
            <div><span class="text-slate-500">Department</span><p class="font-medium">{{ $staff->department ?? '—' }}</p></div>
            <div><span class="text-slate-500">Employee No.</span><p class="font-medium">{{ $staff->employee_number ?? '—' }}</p></div>
            <div><span class="text-slate-500">Account Status</span><p><span class="badge badge-{{ $staff->status === 'active' ? 'active' : 'pending' }}">{{ ucfirst($staff->status) }}</span></p></div>
            <div><span class="text-slate-500">Personnel Link</span><p class="font-medium">{{ $staff->personnel ? '#'.$staff->personnel_id.' '.$staff->personnel->name : 'Not linked' }}</p></div>
            @if($leaveCredits)
            <div><span class="text-slate-500">VL Remaining</span><p class="font-medium">{{ \App\Services\LeaveCardService::formatCredit($leaveCredits['vl']) }} days</p></div>
            <div><span class="text-slate-500">SL Remaining</span><p class="font-medium">{{ \App\Services\LeaveCardService::formatCredit($leaveCredits['sl']) }} days</p></div>
            @endif
        </div>
    </div>
</div>

<div class="app-card max-w-2xl mt-6">
    <div class="app-card-header">
        <h2 class="app-card-title">Change Gmail</h2>
    </div>
    <div class="app-card-body space-y-4">
        <p class="text-sm text-slate-500">
            Request a new login Gmail. HR must approve it before your account email updates. Keep signing in with
            <strong>{{ $staff->email }}</strong> until then.
        </p>

        @if($pendingEmailChange)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Pending HR approval: change to <strong>{{ $pendingEmailChange->new_email }}</strong>
                (requested {{ $pendingEmailChange->created_at?->format('M d, Y g:i A') }}).
            </div>
        @else
            <form method="POST" action="{{ route('staff.profile.email.request') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="form-label" for="new_email">New Gmail</label>
                    <input id="new_email" type="email" name="new_email" value="{{ old('new_email') }}" required
                           class="form-input" placeholder="name@gmail.com" autocomplete="email">
                    @error('new_email')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="form-label" for="new_email_confirmation">Confirm new Gmail</label>
                    <input id="new_email_confirmation" type="email" name="new_email_confirmation" required
                           class="form-input" placeholder="name@gmail.com" autocomplete="email">
                </div>
                <button type="submit" class="btn-primary">Submit for HR approval</button>
            </form>
        @endif

        @if($recentEmailChanges->isNotEmpty())
            <div class="pt-2 border-t border-slate-100">
                <h3 class="text-sm font-semibold text-slate-700 mb-2">Recent requests</h3>
                <ul class="space-y-2 text-sm">
                    @foreach($recentEmailChanges as $change)
                        <li class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-{{ $change->status }}">{{ ucfirst($change->status) }}</span>
                            <span>{{ $change->current_email }} → {{ $change->new_email }}</span>
                            <span class="text-xs text-slate-400">{{ $change->created_at?->format('M d, Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
