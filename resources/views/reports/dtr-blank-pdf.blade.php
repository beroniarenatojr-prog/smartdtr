<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>DTR — {{ $monthLabel }}</title>
    <style>
        /* Folio landscape 13in × 8.5in — two Form 48 copies on the left half (matches blank xlsx). */
        @page { margin: 10px 12px 10px 14px; size: 13in 8.5in landscape; }
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", Times, DejaVu Serif, serif;
            font-size: 7.2px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .sheet {
            page-break-after: always;
            width: 100%;
        }
        .sheet:last-child { page-break-after: auto; }
        .page-wrap {
            width: 50%; /* left half of long bond — same idea as clearing right half of the xlsx */
            max-width: 6.4in;
        }
        .forms {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .forms > tbody > tr > td.form {
            width: 50%;
            vertical-align: top;
            padding: 0 6px 0 0;
        }
        .forms > tbody > tr > td.form + td.form {
            padding: 0 0 0 6px;
            border-left: 0.7px solid #000;
        }
        .cs-no {
            font-size: 6.2px;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 0.2px;
        }
        .title-lg {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 2px 0 3px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .name {
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            margin: 1px 0 0;
            min-height: 11px;
        }
        .name-sub {
            font-size: 6px;
            text-align: center;
            margin: 0 0 2px;
        }
        .month {
            font-size: 7px;
            text-align: left;
            margin: 0 0 1px;
        }
        .hours {
            font-size: 6.2px;
            margin: 0;
            line-height: 1.2;
            text-align: left;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            table-layout: fixed;
        }
        table.grid th, table.grid td {
            border: 0.65px solid #000;
            padding: 0 1px;
            text-align: center;
            vertical-align: middle;
            font-size: 6.2px;
            height: 9.6px;
            overflow: hidden;
        }
        table.grid thead th {
            font-weight: bold;
            font-size: 6px;
        }
        table.grid .day-head {
            width: 9%;
            font-size: 5.8px;
            line-height: 1.05;
            letter-spacing: 0.5px;
        }
        table.grid .day-num {
            width: 9%;
            font-weight: bold;
            font-size: 6.4px;
        }
        table.grid .slot {
            font-size: 5.9px;
        }
        table.grid .holiday {
            font-size: 5.2px;
            text-align: left;
            padding-left: 2px;
        }
        table.grid .total-row td {
            text-align: left;
            padding-left: 3px;
            font-weight: bold;
            height: 10px;
            font-size: 6.4px;
        }
        .cert {
            font-size: 5.6px;
            margin-top: 4px;
            line-height: 1.25;
            text-align: justify;
        }
        .sign {
            text-align: center;
            margin-top: 8px;
            font-size: 7px;
            font-weight: bold;
        }
        .line {
            text-align: center;
            margin-top: 1px;
            font-size: 6px;
            letter-spacing: -0.5px;
        }
        .verify {
            font-size: 5.8px;
            margin-top: 6px;
            text-align: left;
        }
        .incharge {
            text-align: center;
            margin-top: 8px;
            font-size: 7px;
            font-weight: bold;
        }
        .incharge-sub {
            text-align: center;
            font-size: 6px;
            margin-top: 0;
        }
    </style>
</head>
<body>
@foreach($records as $record)
@php
    $p = $record['personnel'];
    $dayMap = $record['dayMap'];
@endphp
<div class="sheet">
    <div class="page-wrap">
        <table class="forms">
            <tr>
                @for($copy = 0; $copy < 2; $copy++)
                <td class="form">
                    <div class="cs-no">CIVIL SERVICE FORM NO. 48</div>
                    <div class="title-lg">DAILY TIME RECORD</div>
                    <div class="name">{{ $p->name }}</div>
                    <div class="name-sub">(Name)</div>
                    <div class="month">{{ $monthLabel }}</div>
                    <div class="hours">Official hours of arrival and departure</div>
                    <div class="hours">Regular days: {{ $record['regular'] }}&nbsp;&nbsp;&nbsp;Saturdays: {{ $record['saturday'] }}</div>

                    <table class="grid">
                        <thead>
                            <tr>
                                <th class="day-head" rowspan="2">D<br>A<br>Y</th>
                                <th colspan="2">A.M.</th>
                                <th colspan="2">P.M.</th>
                                <th colspan="2">OVERTIME</th>
                            </tr>
                            <tr>
                                <th>Arrival</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Departure</th>
                                <th>Arrival</th>
                                <th>Departure</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for($day = 1; $day <= 31; $day++)
                                @php
                                    $amIn = $amOut = $pmIn = $pmOut = $otIn = $otOut = '';
                                    $holidayOnly = false;
                                    if ($day <= $daysInMonth) {
                                        $d = $dayMap[$day] ?? [];
                                        $amIn = $d['am_arrival'] ?? '';
                                        $amOut = $d['am_departure'] ?? '';
                                        $pmIn = $d['pm_arrival'] ?? '';
                                        $pmOut = $d['pm_departure'] ?? '';
                                        $otIn = $d['ot_arrival'] ?? '';
                                        $otOut = $d['ot_departure'] ?? '';
                                        $hasPunches = $amIn !== '' || $amOut !== '' || $pmIn !== '' || $pmOut !== '' || $otIn !== '' || $otOut !== '';
                                        if (! $hasPunches && ! empty($d['label']) && ! in_array($d['label'], ['Saturday', 'Sunday'], true)) {
                                            $amIn = $d['label'];
                                            $holidayOnly = true;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="day-num">{{ $day <= $daysInMonth ? $day : '' }}</td>
                                    @if($holidayOnly)
                                        <td class="holiday" colspan="6">{{ $amIn }}</td>
                                    @else
                                        <td class="slot">{{ $amIn }}</td>
                                        <td class="slot">{{ $amOut }}</td>
                                        <td class="slot">{{ $pmIn }}</td>
                                        <td class="slot">{{ $pmOut }}</td>
                                        <td class="slot">{{ $otIn }}</td>
                                        <td class="slot">{{ $otOut }}</td>
                                    @endif
                                </tr>
                            @endfor
                            <tr class="total-row">
                                <td colspan="7">TOTAL :</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="cert">
                        I CERTIFY on my honor that the above is a true and correct report of hours of worked performed, record of which was made daily at the time of arrival at and departure from office.
                    </div>
                    <div class="sign">{{ $p->name }}</div>
                    <div class="line">______________________________</div>
                    <div class="verify">Verified as to the prescribed office hours.</div>
                    <div class="incharge">{{ $verifier }}</div>
                    <div class="incharge-sub">(In Charge)</div>
                </td>
                @endfor
            </tr>
        </table>
    </div>
</div>
@endforeach
</body>
</html>
