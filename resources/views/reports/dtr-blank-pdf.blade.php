<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>DTR — {{ $monthLabel }}</title>
    <style>
        /*
         * Same sheet as dtr-blank forms.xlsx: four Civil Service Form No. 48
         * copies across landscape long bond (13 in × 8.5 in).
         * Download All puts one employee on the left pair and the next on the right pair.
         */
        @page { margin: 8px 10px; size: 13in 8.5in; }
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", Times, DejaVu Serif, serif;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .sheet { page-break-after: always; width: 100%; }
        .sheet:last-child { page-break-after: auto; }
        table.copies { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.copies > tbody > tr > td.copy {
            width: 24.2%;
            vertical-align: top;
            padding: 0 3px;
        }
        table.copies > tbody > tr > td.gap { width: 0.6%; }
        table.copies > tbody > tr > td.cut {
            width: 0.8%;
            border-left: 0.8px dashed #c00000;
        }
        .cs-no { font-size: 7px; margin: 0; letter-spacing: 0.1px; }
        .title-lg {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 1px 0;
            letter-spacing: 0.4px;
        }
        .name {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            margin: 2px 0 0;
            min-height: 13px;
            border-bottom: 0.7px solid #000;
        }
        .name-sub { font-size: 6.5px; text-align: center; margin: 0 0 2px; }
        .month, .hours {
            font-size: 7px;
            margin: 0;
            line-height: 1.2;
            border-bottom: 0.6px solid #000;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            table-layout: fixed;
        }
        table.grid th, table.grid td {
            border: 0.5px solid #000;
            padding: 0;
            text-align: center;
            vertical-align: middle;
            font-size: 6.2px;
            height: 13.5px;
            line-height: 1;
        }
        table.grid thead th { font-weight: bold; font-size: 6px; height: 11px; }
        table.grid .day-head { width: 8%; font-size: 6px; line-height: 1; }
        table.grid .day-num { font-weight: bold; font-size: 6.4px; }
        table.grid .holiday { font-size: 5.5px; text-align: left; padding-left: 1px; }
        table.grid .total-row td {
            text-align: left;
            padding-left: 2px;
            font-weight: bold;
            height: 12px;
            font-size: 6.4px;
        }
        .cert { font-size: 6px; font-style: italic; margin-top: 4px; line-height: 1.2; text-align: justify; }
        .sign { text-align: center; margin-top: 7px; font-size: 8px; font-weight: bold; }
        .line { text-align: center; margin: 0 8px; font-size: 6px; border-top: 0.7px solid #000; }
        .verify { font-size: 6.2px; margin-top: 5px; }
        .incharge { text-align: center; margin-top: 8px; font-size: 8px; font-weight: bold; }
        .incharge-sub { text-align: center; font-size: 6.5px; margin: 0; }
        .blank-copy { height: 100%; }
    </style>
</head>
<body>
@php
    $pages = [];
    if (($layout ?? 'pair') === 'copies') {
        foreach ($records as $record) {
            $pages[] = [$record, $record, $record, $record];
        }
    } else {
        foreach (array_chunk($records, 2) as $pair) {
            if (count($pair) === 1) {
                $pages[] = [$pair[0], $pair[0], $pair[0], $pair[0]];
            } else {
                $pages[] = [$pair[0], $pair[0], $pair[1], $pair[1]];
            }
        }
    }
@endphp
@foreach($pages as $slots)
<div class="sheet">
    <table class="copies">
        <tr>
            @foreach($slots as $index => $record)
            @php
                $p = $record['personnel'];
                $dayMap = $record['dayMap'];
            @endphp
            <td class="copy">
                <div class="cs-no">CIVIL SERVICE FORM NO. 48</div>
                <div class="title-lg">DAILY TIME RECORD</div>
                <div class="name">{{ $p->name }}</div>
                <div class="name-sub">(Name)</div>
                <div class="month">{{ $monthLabel }}</div>
                <div class="hours">Official hours of arrival</div>
                <div class="hours">and departure</div>

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
                                    <td>{{ $amIn }}</td>
                                    <td>{{ $amOut }}</td>
                                    <td>{{ $pmIn }}</td>
                                    <td>{{ $pmOut }}</td>
                                    <td>{{ $otIn }}</td>
                                    <td>{{ $otOut }}</td>
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
                <div class="line">&nbsp;</div>
                <div class="verify">Verified as to the prescribed office hours.</div>
                <div class="incharge">{{ $verifier }}</div>
                <div class="incharge-sub">(In Charge)</div>
            </td>
            @if($index < 3)
            <td class="{{ $index === 1 ? 'cut' : 'gap' }}"></td>
            @endif
            @endforeach
        </tr>
    </table>
</div>
@endforeach
</body>
</html>
