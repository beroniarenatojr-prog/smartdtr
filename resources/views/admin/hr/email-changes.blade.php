@extends('layouts.admin')

@section('title', 'Gmail Changes - SMART-DTR')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-primary-900">Gmail Change Requests</h1>
    <p class="text-slate-500 text-sm">Approve or reject employee requests to change their login Gmail</p>
</div>

<div class="app-card">
    <div class="overflow-x-auto">
        <table class="app-table">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Current Gmail</th>
                    <th>Requested Gmail</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                <tr>
                    <td>
                        <span class="font-medium">{{ $req->staffUser?->name ?? '—' }}</span><br>
                        <span class="text-xs text-slate-400">{{ $req->staffUser?->roleLabel() ?? '' }}</span>
                    </td>
                    <td class="text-sm">{{ $req->current_email }}</td>
                    <td class="text-sm font-medium">{{ $req->new_email }}</td>
                    <td class="text-sm text-slate-500">{{ $req->created_at?->format('M d, Y g:i A') }}</td>
                    <td><span class="badge badge-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
                    <td>
                        @if($req->status === 'pending')
                        <div class="flex flex-col gap-2 min-w-[180px]">
                            <form method="POST" action="{{ route('admin.hr.email-changes.approve', $req) }}">
                                @csrf
                                <input type="text" name="admin_notes" placeholder="Optional note" class="form-input text-xs mb-1 py-1">
                                <button type="submit" class="btn-success text-xs w-full py-1">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.hr.email-changes.reject', $req) }}">
                                @csrf
                                <input type="text" name="admin_notes" placeholder="Rejection reason" class="form-input text-xs mb-1 py-1">
                                <button type="submit" class="btn-danger text-xs w-full py-1">Reject</button>
                            </form>
                        </div>
                        @else
                        <div class="text-xs text-slate-400">
                            {{ $req->reviewer?->username ?? '—' }}<br>
                            {{ $req->reviewed_at?->format('M d, Y g:i A') }}
                            @if($req->admin_notes)
                            <br><span class="text-slate-500">{{ $req->admin_notes }}</span>
                            @endif
                        </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-8">No Gmail change requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
