@extends('layouts.app')

@section('body_class', 'landing-body')
@section('main_class', '')
@section('hide_footer', true)
@section('title', 'SMART-DTR — DepEd Daily Time Record')

@section('content')
<div class="landing-page">
    <x-landing-background />

    {{-- Navbar --}}
    <header class="landing-nav">
        <div class="landing-nav-inner">
            <a href="{{ route('home') }}" class="landing-brand">
                <x-logo size="md" variant="light" class="landing-brand-logo" />
                <span class="landing-brand-text">SMART-DTR</span>
            </a>
            <nav class="landing-nav-links">
                <button type="button" hidden data-pwa-install class="landing-nav-btn landing-nav-btn-ghost">Install App</button>
                <a href="{{ route('login') }}" class="landing-nav-btn landing-nav-btn-ghost">Sign In</a>
                <a href="{{ route('staff.register') }}" class="landing-nav-btn landing-nav-btn-solid">Sign Up</a>
            </nav>
        </div>
    </header>

    {{-- Hero --}}
    <section class="landing-hero">
        <div class="landing-hero-inner">
            <div class="landing-hero-badge">
                <span class="landing-hero-badge-dot"></span>
                DepEd Senior High School &bull; INHS
            </div>
            <h1 class="landing-hero-title">
                Smart Daily Time Record
                <span class="landing-hero-highlight">for Modern Schools</span>
            </h1>
            <p class="landing-hero-desc">
                Face recognition attendance, automated DepEd CS Form No. 48 DTR generation,
                leave management, and real-time HR monitoring — built for INHS personnel.
            </p>
            <div class="landing-hero-stats">
                <div class="landing-stat">
                    <span class="landing-stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                    </span>
                    <span class="landing-stat-value">Pi</span>
                    <span class="landing-stat-label">Face Scan Device</span>
                </div>
                <div class="landing-stat-divider"></div>
                <div class="landing-stat">
                    <span class="landing-stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </span>
                    <span class="landing-stat-value">CS 48</span>
                    <span class="landing-stat-label">Official DTR Form</span>
                </div>
                <div class="landing-stat-divider"></div>
                <div class="landing-stat">
                    <span class="landing-stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </span>
                    <span class="landing-stat-value">24/7</span>
                    <span class="landing-stat-label">Attendance Logs</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="landing-section">
        <div class="landing-section-inner">
            <div class="landing-section-head">
                <span class="landing-section-tag">Features</span>
                <h2 class="landing-section-title">Everything HR needs in one system</h2>
                <p class="landing-section-desc">From Raspberry Pi face scans to official DepEd exports — streamlined for school administrators and employees.</p>
            </div>
            <div class="landing-features-grid">
                <article class="landing-feature-card">
                    <div class="landing-feature-icon landing-feature-icon-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
                    </div>
                    <h3 class="landing-feature-title">Face Recognition</h3>
                    <p class="landing-feature-text">Raspberry Pi attendance kiosk with blink liveness detection. Accurate, contactless time-in and time-out.</p>
                </article>
                <article class="landing-feature-card">
                    <div class="landing-feature-icon landing-feature-icon-green">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <h3 class="landing-feature-title">DepEd DTR Export</h3>
                    <p class="landing-feature-text">Generate CS Form No. 48 Daily Time Record in Excel or PDF — ready for official personnel files.</p>
                </article>
                <article class="landing-feature-card">
                    <div class="landing-feature-icon landing-feature-icon-purple">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <h3 class="landing-feature-title">Leave Management</h3>
                    <p class="landing-feature-text">Staff submit leave and travel requests online. HR approves with one click — records sync automatically.</p>
                </article>
                <article class="landing-feature-card">
                    <div class="landing-feature-icon landing-feature-icon-orange">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </div>
                    <h3 class="landing-feature-title">Reports & Analytics</h3>
                    <p class="landing-feature-text">Daily overview dashboard, attendance logs, schedule management, and exportable HR reports.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="landing-section landing-section-alt">
        <div class="landing-section-inner">
            <div class="landing-section-head">
                <span class="landing-section-tag">How It Works</span>
                <h2 class="landing-section-title">Simple workflow for everyone</h2>
            </div>
            <div class="landing-steps">
                <div class="landing-step">
                    <div class="landing-step-num">1</div>
                    <h3 class="landing-step-title">Staff Registers</h3>
                    <p class="landing-step-text">Employees create an account using their email. HR approves and links it to a personnel record.</p>
                </div>
                <div class="landing-step-arrow">→</div>
                <div class="landing-step">
                    <div class="landing-step-num">2</div>
                    <h3 class="landing-step-title">Face Scan Daily</h3>
                    <p class="landing-step-text">Personnel clock in/out at the Pi kiosk. Attendance is logged and status computed automatically.</p>
                </div>
                <div class="landing-step-arrow">→</div>
                <div class="landing-step">
                    <div class="landing-step-num">3</div>
                    <h3 class="landing-step-title">HR Manages & Exports</h3>
                    <p class="landing-step-text">Admin monitors attendance, approves leave, and exports official DepEd DTR forms anytime.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Get started --}}
    <section id="get-started" class="landing-section">
        <div class="landing-section-inner">
            <div class="landing-section-head">
                <span class="landing-section-tag">Get Started</span>
                <h2 class="landing-section-title">One secure sign-in for everyone</h2>
                <p class="landing-section-desc">Employees and admins use the same Sign In page. New staff create an account with Sign Up, then wait for HR approval.</p>
            </div>
            <div class="landing-portals">
                <a href="{{ route('login') }}" class="landing-portal-card landing-portal-staff">
                    <div class="landing-portal-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    </div>
                    <h3 class="landing-portal-title">Sign In</h3>
                    <p class="landing-portal-desc">Use your registered email or admin username. You are taken to the correct portal automatically.</p>
                    <span class="landing-portal-cta">Sign In →</span>
                </a>
                <a href="{{ route('staff.register') }}" class="landing-portal-card landing-portal-admin">
                    <div class="landing-portal-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    </div>
                    <h3 class="landing-portal-title">Sign Up</h3>
                    <p class="landing-portal-desc">Create an employee account with face registration. HR reviews and activates your access.</p>
                    <span class="landing-portal-cta">Create Account →</span>
                </a>
                <button type="button" hidden data-pwa-install class="landing-portal-card landing-portal-staff text-left w-full">
                    <div class="landing-portal-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M4 19h16"/></svg>
                    </div>
                    <h3 class="landing-portal-title">Install App</h3>
                    <p class="landing-portal-desc">Add SMART-DTR to your phone, tablet, or computer home screen for quick access like a native app.</p>
                    <span class="landing-portal-cta">Install →</span>
                </button>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="landing-footer">
        <div class="landing-footer-inner">
            <div class="landing-footer-brand">
                <x-logo size="md" variant="light" class="landing-brand-logo" />
                <span>SMART-DTR</span>
            </div>
            <p class="landing-footer-text">Smart Daily Time Record System &bull; Isabela National High School</p>
            <p class="landing-footer-copy">&copy; {{ date('Y') }} Isabela National High School. All rights reserved.</p>
        </div>
    </footer>
</div>
@endsection
