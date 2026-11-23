@extends('layouts.admin')

@section('title', 'DTR Export - SMARTDTR')

@section('content')
<x-page-header title="DepEd DTR Export" subtitle="Civil Service Form No. 48 — two identical copies on landscape long bond (matches blank Form 48)" />

<div class="grid md:grid-cols-2 gap-6 max-w-4xl">
    <div class="app-card md:col-span-2">
        <div class="app-card-header">
            <h2 class="app-card-title">Print All DTRs</h2>
        </div>
        <div class="app-card-body">
            <p class="text-sm text-slate-500 mb-4">
                Download every employee on the blank Form 48 workbook. Each sheet is the dtr-blank forms.xlsx layout, with all four copies filled.
            </p>
            <form method="GET" action="{{ route('admin.dtr.bulk') }}" class="grid sm:grid-cols-[1fr_1fr] gap-4 items-end">
                <div class="form-group mb-0">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" value="{{ now()->format('Y-m') }}" required class="form-input">
                </div>
                <button type="submit" class="btn-primary w-full">Download All</button>
            </form>
        </div>
    </div>

    <div class="app-card md:col-span-2">
        <div class="app-card-header">
            <h2 class="app-card-title">Single Employee</h2>
        </div>
        <div class="app-card-body">
            <p class="text-sm text-slate-500 mb-4">
                Official Form 48 workbook from dtr-blank forms.xlsx.
            </p>
            <form method="GET" action="{{ route('admin.dtr.pdf') }}" class="space-y-4">
                <div class="form-group">
                    <label class="form-label">Employee</label>
                    <select name="personnel_id" required class="form-select">
                        @foreach($personnel as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->employee_number ?? 'N/A' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" value="{{ now()->format('Y-m') }}" required class="form-input">
                </div>
                <button type="submit" class="btn-primary w-full">Download</button>
            </form>
        </div>
    </div>
</div>
@endsection
