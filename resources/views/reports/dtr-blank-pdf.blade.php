<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>DTR — {{ $monthLabel }}</title>
    <style>
        /*
         * Civil Service Form No. 48 on landscape long bond (13 in × 8.5 in).
         * Download All places two employees side by side. A single employee
         * is printed as two identical copies.
         */
        @page { margin: 10px 12px; size: 13in 8.5in; }
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", Times, DejaVu Serif, serif;
            font-size: 8px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .sheet { page-break-after: always; width: 100%; }
        .sheet:last-child { page-break-after: auto; }
        .forms { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .forms > tbody > tr > td.form {
            width: 50%;
            vertical-align: top;
            padding: 0 10px 0 0;
        }
        .forms > tbody > tr > td.form + td.form {
            padding: 0 0 0 10px;
            border-left: 1px solid #000;
        }
        .cs-no { font-size: 7px; text-transform: uppercase; margin: 0; letter-spacing: 0.3px; }
        .title-lg {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            margin: 2px 0 4px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .name { font-size: 11px; font-weight: bold; text-align: center; margin: 2px 0 0; min-height: 13px; }
        .name-sub { font-size: 7px; text-align: center; margin: 0 0 3px; }
        .month { font-size: 8.5px; text-align: left; margin: 0 0 1px; }
        .hours { font-size: 7.5px; margin: 0; line-height: 1.25; text-align: left; }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            table-layout: fixed;
        }
        table.grid th, table.grid td {
            border: 0.8px solid #000;
            padding: 0 1px;
            text-align: center;
            vertical-align: middle;
            font-size: 7.2px;
            height: 11.5px;
        }
        table.grid thead th { font-weight: bold; font-size: 7px; height: 12px; }
        table.grid .day-head {
            width: 8%;
            font-size: 6.5px;
            line-height: 1.05;
            letter-spacing: 0.6px;
        }
        table.grid .day-num { width: 8%; font-weight: bold; font-size: 7.5px; }
        table.grid .slot { font-size: 6.8px; }
        table.grid .holiday { font-size: 6.2px; text-align: left; padding-left: 3px; }
        table.grid .total-row td {
            text-align: left;
            padding-left: 4px;
            font-weight: bold;
            height: 12px;
            font-size: 7.5px;
        }
        .cert { font-size: 6.5px; margin-top: 5px; line-height: 1.3; text-align: justify; }
        .sign { text-align: center; margin-top: 10px; font-size: 8.5px; font-weight: bold; }
        .line { text-align: center; margin-top: 1px; font-size: 7px; }
        .verify { font-size: 7px; margin-top: 8px; text-align: left; }
        .incharge { text-align: center; margin-top: 10px; font-size: 8.5px; font-weight: bold; }
        .incharge-sub { text-align: center; font-size: 7px; margin-top: 0; }
    </style>
</head>
<body>
@php
    $pages = ($layout ?? 'pair') === 'copies'
        ? array_map(fn ($record) => [$record, $record], $records)
        : array_chunk($records, 2);
@endphp
@foreach($pages as $pair)
<div class="sheet">
    <table class="forms">
        <tr>
            @foreach($pair as $record)
            @php
                $p = $record['personnel'];
                $dayMap = $record['dayMap'];
            @endphp
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
            @endforeach
            @if(count($pair) === 1)
            <td></td>
            @endif
        </tr>
    </table>
</div>
@endforeach
</body>
</html>
