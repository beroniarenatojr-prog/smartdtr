@extends('layouts.admin')

@section('title', 'Employee Accounts - SMARTDTR')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-primary-900">Employee Account Management</h1>
    <p class="text-slate-500 text-sm">Review and approve employee registrations</p>
</div>

<div class="app-card">
    <div class="overflow-x-auto">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Employee No.</th>
                    <th>Account Type</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Personnel</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($staff as $user)
                <tr>
                    <td class="font-medium">{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td class="font-mono text-sm">{{ $user->employee_number ?? '—' }}</td>
                    <td>{{ $user->roleLabel() }}</td>
                    <td>{{ $user->department ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $user->status === 'active' ? 'active' : ($user->status === 'pending' ? 'pending' : 'rejected') }}">{{ ucfirst($user->status) }}</span>
                        @if($user->status === 'pending' && ! $user->hasVerifiedEmail())
                            <span class="block text-xs text-amber-600 mt-0.5">Email not verified</span>
                        @endif
                    </td>
                    <td>
                        {{ $user->personnel?->name ?? '—' }}
                        @if($user->personnel)
                            <span class="block text-xs text-slate-400">{{ $user->personnel->faceSampleCount() }} face samples</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">
                        @if($user->status === 'pending' && $user->hasVerifiedEmail())
                        <div class="flex items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.hr.staff.activate', $user) }}" class="flex items-center gap-1.5">
                                @csrf
                                <select name="personnel_id" class="form-select w-auto min-w-[8.5rem] max-w-[11rem] h-8 text-xs py-1 px-2">
                                    <option value="">{{ $user->personnel_id ? 'Linked registration record' : 'New personnel' }}</option>
                                    @foreach($personnel as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-success btn-sm shrink-0">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.hr.staff.reject', $user) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn-danger btn-sm shrink-0">Reject</button>
                            </form>
                        </div>
                        @elseif($user->status === 'pending')
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-slate-400">Waiting for email code</span>
                            <form method="POST" action="{{ route('admin.hr.staff.reject', $user) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn-danger btn-sm shrink-0">Reject</button>
                            </form>
                        </div>
                        @else
                        <span class="text-slate-400 text-xs">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="empty-state-text py-8 text-center">No staff accounts yet. New registrations will appear here for approval.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
