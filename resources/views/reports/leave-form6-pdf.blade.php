<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application for Leave — {{ $staff->name ?? 'Staff' }}</title>
    <style>
        @page { margin: 28px 32px; size: legal portrait; }
        * { box-sizing: border-box; }
        body { font-family: Arial, DejaVu Sans, sans-serif; font-size: 10px; color: #000; margin: 0; }
        h1 { text-align: center; font-size: 14px; margin: 0 0 10px; letter-spacing: 0.5px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.meta td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        .label { font-size: 8px; color: #333; }
        .value { font-size: 11px; font-weight: bold; }
        .section { border: 1px solid #000; margin-top: 8px; }
        .section-title { background: #f3f3f3; font-weight: bold; padding: 4px 6px; border-bottom: 1px solid #000; font-size: 10px; }
        .cols { width: 100%; border-collapse: collapse; }
        .cols td { vertical-align: top; padding: 6px; border-right: 1px solid #000; }
        .cols td:last-child { border-right: none; }
        .check { font-size: 10px; line-height: 1.45; margin: 0; }
        .muted { color: #444; font-size: 9px; }
        .box { border: 1px solid #000; min-height: 18px; padding: 3px 5px; margin-top: 3px; }
        .sign { text-align: center; margin-top: 18px; }
        .sign-name { font-weight: bold; font-size: 11px; }
        .sign-line { margin-top: 2px; font-size: 9px; }
        .footer-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .footer-grid td { border: 1px solid #000; padding: 6px; vertical-align: top; width: 50%; }
        .small { font-size: 9px; }
    </style>
</head>
<body>
@php
    $selectedRow = $leaveTypeRows[$request->leave_type] ?? 11;
    $types = [
        11 => 'Vacation Leave',
        13 => 'Mandatory/Forced Leave',
        15 => 'Sick Leave',
        17 => 'Maternity Leave',
        19 => 'Paternity Leave',
        21 => 'Special Privilege Leave',
        23 => 'Solo Parent Leave',
        25 => 'Study Leave',
        27 => '10-Day VAWC Leave',
        29 => 'Rehabilitation Privilege',
        31 => 'Special Leave Benefits for Women',
        33 => 'Special Emergency (Calamity) Leave',
        35 => 'Adoption Leave',
        39 => 'Others',
    ];
@endphp
<h1>APPLICATION FOR LEAVE</h1>

<table class="meta">
    <tr>
        <td style="width:40%">
            <div class="label">1. OFFICE/DEPARTMENT</div>
            <div class="value">{{ $office }}</div>
        </td>
        <td>
            <div class="label">2. NAME (Last) / (First) / (Middle)</div>
            <div class="value">{{ strtoupper($parts['last']) }} / {{ strtoupper($parts['first']) }} / {{ strtoupper($parts['middle'] ?: '—') }}</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="label">3. DATE OF FILING</div>
            <div class="value">{{ $filingDate }}</div>
        </td>
        <td>
            <div class="label">4. POSITION &nbsp;&nbsp; 5. SALARY</div>
            <div class="value">{{ $position }} &nbsp;&nbsp; {{ $salary !== '' ? $salary : '_______________' }}</div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">6. DETAILS OF APPLICATION</div>
    <table class="cols">
        <tr>
            <td style="width:48%">
                <div class="small"><strong>6.A TYPE OF LEAVE TO BE AVAILED OF</strong></div>
                @foreach($types as $row => $label)
                    <p class="check">{{ $selectedRow === $row ? '☑' : '☐' }} {{ $label }}</p>
                @endforeach
            </td>
            <td>
                <div class="small"><strong>6.B DETAILS OF LEAVE</strong></div>
                <p class="muted">In case of Vacation/Special Privilege Leave:</p>
                <div class="box">Within the Philippines {{ str_contains(strtolower($request->leave_type), 'sick') ? '' : $details }}</div>
                <p class="muted" style="margin-top:6px">In case of Sick Leave:</p>
                <div class="box">In Hospital (Specify Illness) {{ str_contains(strtolower($request->leave_type), 'sick') ? $details : '' }}</div>
                <p class="muted" style="margin-top:6px">Other purpose / notes:</p>
                <div class="box">{{ $details }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="small"><strong>6.C NUMBER OF WORKING DAYS APPLIED FOR</strong></div>
                <div class="box">{{ $request->dayCount() }} working day(s)</div>
                <div class="small" style="margin-top:6px"><strong>INCLUSIVE DATES</strong></div>
                <div class="box">{{ $request->date_from->format('F d, Y') }} – {{ $request->date_to->format('F d, Y') }}</div>
            </td>
            <td>
                <div class="small"><strong>6.D COMMUTATION</strong></div>
                <p class="check">{{ ($request->commutation ?? 'not_requested') === 'requested' ? '☐' : '☑' }} Not Requested</p>
                <p class="check">{{ ($request->commutation ?? 'not_requested') === 'requested' ? '☑' : '☐' }} Requested</p>
                <div class="sign">
                    <div class="sign-name">{{ $staff?->officialLetterName() ?: strtoupper((string) $staff->name) }}</div>
                    <div class="sign-line">______________________________</div>
                    <div class="sign-line">(Signature of Applicant)</div>
                </div>
            </td>
        </tr>
    </table>
</div>

<table class="footer-grid">
    <tr>
        <td>
            <div class="small"><strong>7.A CERTIFICATION OF LEAVE CREDITS</strong></div>
            <div class="box">As of {{ now()->format('F d, Y') }}</div>
            <p class="muted">Vacation Leave / Sick Leave totals to be filled by certifying officer.</p>
        </td>
        <td>
            <div class="small"><strong>7.B RECOMMENDATION</strong></div>
            <p class="check">{{ $request->status === 'approved' ? '☑' : '☐' }} For approval</p>
            <p class="check">{{ $request->status === 'rejected' ? '☑' : '☐' }} For disapproval due to {{ $request->admin_notes ?: '________________' }}</p>
            <div class="sign">
                <div class="sign-name">{{ $longLeave ? $assistantSds : $schoolHead }}</div>
                <div class="sign-line">{{ $longLeave ? 'Assistant Schools Division Superintendent' : 'School Head / Immediate Supervisor' }}</div>
            </div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="small"><strong>7.C APPROVED FOR</strong></div>
            <div class="box">{{ $request->status === 'approved' ? $request->dayCount().' days with pay' : '_______ days with pay' }}</div>
            <div class="box" style="margin-top:4px">_______ days without pay</div>
        </td>
        <td>
            <div class="small"><strong>7.D DISAPPROVED DUE TO</strong></div>
            <div class="box">{{ $request->status === 'rejected' ? ($request->admin_notes ?: '') : '' }}</div>
            <div class="sign">
                <div class="sign-name">{{ $longLeave ? $sds : $assistantSds }}</div>
                <div class="sign-line">{{ $longLeave ? 'Schools Division Superintendent' : 'Assistant Schools Division Superintendent' }}</div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
