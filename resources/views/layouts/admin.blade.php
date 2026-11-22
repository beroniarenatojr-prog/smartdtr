@extends('layouts.app')

@section('sidebar')
<aside class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
    <div class="sidebar-brand">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand-link">
            <x-logo variant="sidebar" class="sidebar-brand-logo" />
            <div>
                <div class="sidebar-brand-title">SMART-DTR</div>
                <div class="sidebar-brand-sub">Admin Portal</div>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        <p class="sidebar-section">Overview</p>
        @if(adminCan('dashboard'))
        <x-sidebar-link href="{{ route('admin.dashboard') }}" label="Dashboard" :active="request()->routeIs('admin.dashboard')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @php
            $safeCount = function ($query) {
                try {
                    return (int) $query();
                } catch (\Throwable $e) {
                    return 0;
                }
            };
            $adminUnread = $safeCount(function () {
                return auth('admin')->user() ? auth('admin')->user()->unreadNotifications()->count() : 0;
            });
            $pendingStaff = $safeCount(function () {
                return \App\Models\StaffUser::where('status', 'pending')->count();
            });
            $pendingLeave = $safeCount(function () {
                return \App\Models\LeaveRequest::where('status', 'pending')->count();
            });
            $pendingLocator = $safeCount(function () {
                return \App\Models\LocatorRequest::where('status', 'pending')->count();
            });
            $pendingTravel = $safeCount(function () {
                return \App\Models\TravelOrder::where('status', 'pending')->count();
            });
            $pendingEmailChanges = $safeCount(function () {
                return \App\Models\EmailChangeRequest::where('status', 'pending')->count();
            });
        @endphp
        <x-sidebar-link href="{{ route('admin.notifications') }}" label="Notifications" :badge="$adminUnread" badge-variant="danger" :active="request()->routeIs('admin.notifications*')" title="{{ $adminUnread ? $adminUnread.' unread notification'.($adminUnread === 1 ? '' : 's') : 'Notifications' }}">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg></x-slot:icon>
        </x-sidebar-link>

        <p class="sidebar-section">HR & People</p>
        @if(adminCan('personnel'))
        <x-sidebar-link href="{{ route('admin.personnel') }}" label="Personnel" :active="request()->routeIs('admin.personnel*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @if(adminCan('hr'))
        <x-sidebar-link href="{{ route('admin.hr.faces') }}" label="Face Enrollment" :active="request()->routeIs('admin.hr.faces*')" title="Admin camera face enrollment">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M4 7h4"/><path d="M16 7h4"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.hr.staff') }}" label="Employee Accounts" :badge="$pendingStaff" badge-variant="danger" :active="request()->routeIs('admin.hr.staff*')" title="{{ $pendingStaff ? $pendingStaff.' pending employee account'.($pendingStaff === 1 ? '' : 's') : 'Employee Accounts' }}">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.hr.email-changes') }}" label="Gmail Changes" :badge="$pendingEmailChanges" badge-variant="danger" :active="request()->routeIs('admin.hr.email-changes*')" title="{{ $pendingEmailChanges ? $pendingEmailChanges.' pending Gmail change'.($pendingEmailChanges === 1 ? '' : 's') : 'Gmail Changes' }}">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.hr.leave-requests') }}" label="Leave Requests" :badge="$pendingLeave" badge-variant="danger" :active="request()->routeIs('admin.hr.leave-requests*')" title="{{ $pendingLeave ? $pendingLeave.' pending leave request'.($pendingLeave === 1 ? '' : 's') : 'Leave Requests' }}">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.leave-cards.index') }}" label="Leave Card" :active="request()->routeIs('admin.leave-cards*')" title="Employee's Leave Card for administrative staff">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.hr.locator-requests') }}" label="Locator Slip" :badge="$pendingLocator" badge-variant="danger" :active="request()->routeIs('admin.hr.locator*')" title="{{ $pendingLocator ? $pendingLocator.' pending locator slip'.($pendingLocator === 1 ? '' : 's') : 'Locator Slip' }}">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.hr.travel-orders') }}" label="Travel Order" :badge="$pendingTravel" badge-variant="danger" :active="request()->routeIs('admin.hr.travel-orders*')" title="{{ $pendingTravel ? $pendingTravel.' pending travel order'.($pendingTravel === 1 ? '' : 's') : 'Travel Order' }}">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.files201.index') }}" label="201 Files" :active="request()->routeIs('admin.files201*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif

        <p class="sidebar-section">Attendance</p>
        @if(adminCan('attendance.correct'))
        <x-sidebar-link href="{{ route('admin.attendance.correction') }}" label="Correction" :active="request()->routeIs('admin.attendance*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @if(adminCan('logs'))
        <x-sidebar-link href="{{ route('admin.logs') }}" label="Scan Logs" :active="request()->routeIs('admin.logs')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @if(adminCan('leave'))
        <x-sidebar-link href="{{ route('admin.leave') }}" label="Leave Records" :active="request()->routeIs('admin.leave*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5v14"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @if(adminCan('holidays'))
        <x-sidebar-link href="{{ route('admin.holidays') }}" label="Holidays" :active="request()->routeIs('admin.holidays*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @if(adminCan('schedule'))
        <x-sidebar-link href="{{ route('admin.schedule') }}" label="Schedule" :active="request()->routeIs('admin.schedule*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif

        <p class="sidebar-section">Reports</p>
        @if(adminCan('dtr'))
        <x-sidebar-link href="{{ route('admin.dtr') }}" label="DTR Export" :active="request()->routeIs('admin.dtr*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @if(adminCan('reports'))
        <x-sidebar-link href="{{ route('admin.rsmi.index') }}" label="RSMI" :active="request()->routeIs('admin.rsmi*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.ris.index') }}" label="RIS" :active="request()->routeIs('admin.ris*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></x-slot:icon>
        </x-sidebar-link>
        <x-sidebar-link href="{{ route('admin.reports') }}" label="Reports" :active="request()->routeIs('admin.reports*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
        @if(adminCan('audit'))
        <x-sidebar-link href="{{ route('admin.audit') }}" label="Audit Log" :active="request()->routeIs('admin.audit')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif

        @if(adminCan('settings'))
        <p class="sidebar-section">Settings</p>
        <x-sidebar-link href="{{ route('admin.settings.signatories') }}" label="Document Signatories" :active="request()->routeIs('admin.settings.signatories*')">
            <x-slot:icon><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/><path d="m15 5 3 3"/></svg></x-slot:icon>
        </x-sidebar-link>
        @endif
    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('admin.profile') }}" class="sidebar-user">
            <x-user-avatar :user="auth('admin')->user()" size="sm" />
            <div class="sidebar-user-info">
                <span class="sidebar-user-name">{{ auth('admin')->user()->username }}</span>
                <span class="sidebar-user-role">{{ auth('admin')->user()->roleLabel() }}</span>
            </div>
        </a>
        <div class="sidebar-footer-links">
            <a href="{{ route('admin.profile') }}" class="sidebar-footer-link {{ request()->routeIs('admin.profile') ? 'sidebar-footer-link-active' : '' }}">Profile</a>
            <a href="{{ route('admin.password') }}" class="sidebar-footer-link">Password</a>
        </div>
        <form method="POST" action="{{ route('logout') }}" data-confirm="Are you sure you want to log out?" data-confirm-button="Log Out">
            @csrf
            <button type="submit" class="sidebar-logout-btn">Logout</button>
        </form>
    </div>
</aside>
@endsection

@section('topbar')
<header class="app-topbar">
    <button type="button" class="sidebar-toggle" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="app-topbar-brand">
        <span class="app-topbar-school">Isabela National High School</span>
        <span class="app-topbar-system">SMART-DTR: Web-Based HR Management System</span>
    </div>
    <div class="app-topbar-actions">
        <x-notification-bell guard="admin" variant="light" />
        <a href="{{ route('admin.profile') }}" class="app-topbar-user no-underline">
            <x-user-avatar :user="auth('admin')->user()" size="sm" />
            <div class="app-topbar-user-info">
                <span class="app-topbar-user-name">{{ auth('admin')->user()->username }}</span>
                <span class="app-topbar-user-role">{{ auth('admin')->user()->roleLabel() }}</span>
            </div>
        </a>
    </div>
</header>
@endsection
