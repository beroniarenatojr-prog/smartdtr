@extends('layouts.admin')

@section('title', 'Edit Personnel - SMARTDTR')

@section('content')
<x-page-header title="Edit Personnel" :subtitle="$personnel->name" />

<div class="app-card max-w-2xl">
    <div class="app-card-body">
        <form method="POST" action="{{ route('admin.personnel.update', $personnel) }}" class="space-y-4"
            data-confirm="Save changes to this personnel record?"
            data-confirm-button="Save">
            @csrf @method('PUT')
            <div class="form-group">
                <label class="form-label">Name</label>
                <input type="text" name="name" value="{{ old('name', $personnel->name) }}" required class="form-input"
                    pattern="[A-Za-zÑñ]+(?:[ .'\-][A-Za-zÑñ]+)*" title="Letters only">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" name="role" value="{{ old('role', $personnel->role) }}" required class="form-input"
                    pattern="[A-Za-zÑñ0-9]+(?:[ .\/\-][A-Za-zÑñ0-9]+)*" title="Letters and numbers only">
            </div>
            <div class="form-group">
                <label class="form-label">Employee Number</label>
                <input type="text" name="employee_number" value="{{ old('employee_number', $personnel->employee_number) }}" class="form-input"
                    maxlength="50" pattern="[0-9]+(-[0-9]+)*" title="Digits and hyphens only, e.g. 00-001">
            </div>
            <x-official-hours :regular="$personnel->regularHoursParts()" :saturday="$personnel->saturdayHoursParts()" />
            @if($personnel->eligibleForLeaveCard())
            <p class="text-sm text-slate-600">This employee uses the administrative Leave Card. Each month earns 1.25 vacation leave and 1.25 sick leave, and twelve months earn 15 of each. <a href="{{ route('admin.leave-cards.show', $personnel) }}" class="text-primary-700 underline">Open the leave card</a>.</p>
            @endif
            <div class="grid grid-cols-2 gap-4 pt-2 border-t">
                <div class="form-group">
                    <label class="form-label">VL Balance (days)</label>
                    <input type="number" step="0.5" name="vl_balance" value="{{ old('vl_balance', $balance->vl_balance) }}" class="form-input">
                    <p class="text-xs text-slate-400 mt-1">Used: {{ $balance->vl_used }} · Remaining: {{ $balance->vlRemaining() }}</p>
                </div>
                <div class="form-group">
                    <label class="form-label">SL Balance (days)</label>
                    <input type="number" step="0.5" name="sl_balance" value="{{ old('sl_balance', $balance->sl_balance) }}" class="form-input">
                    <p class="text-xs text-slate-400 mt-1">Used: {{ $balance->sl_used }} · Remaining: {{ $balance->slRemaining() }}</p>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary">Save Changes</button>
                <a href="{{ route('admin.personnel') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
