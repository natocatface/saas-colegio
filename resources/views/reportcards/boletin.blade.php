<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boletín de Notas</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color:#2c3e50; font-size:12px; margin:0; }
        .header { background:#1f2a36; color:#fff; padding:18px 24px; }
        .header h1 { margin:0; font-size:18px; }
        .header .sub { color:#9fb0bf; font-size:11px; margin-top:2px; }
        .brand { color:#2ecc71; font-weight:bold; font-size:20px; }
        .wrap { padding:0 24px; }
        .student-box { border:1px solid #e3e8ec; border-radius:6px; margin-top:18px; padding:12px 16px; background:#f9fbfc; }
        .student-box table { width:100%; }
        .student-box td { padding:3px 0; font-size:12px; }
        .student-box .lbl { color:#7b8a99; width:90px; }
        table.grades { width:100%; border-collapse:collapse; margin-top:18px; }
        table.grades th { background:#2ecc71; color:#fff; padding:8px 6px; font-size:11px; text-align:center; }
        table.grades th.left, table.grades td.left { text-align:left; padding-left:10px; }
        table.grades td { border-bottom:1px solid #eceff2; padding:7px 6px; text-align:center; }
        table.grades tr:nth-child(even) td { background:#f7f9fb; }
        .final-col { font-weight:bold; }
        .ok { color:#1e8e57; } .bad { color:#c0392b; }
        .summary { margin-top:18px; border:2px solid #2ecc71; border-radius:6px; padding:12px 16px; text-align:right; }
        .summary .big { font-size:22px; font-weight:bold; color:#1f2a36; }
        .footer { margin-top:50px; padding:0 24px; color:#7b8a99; font-size:10px; }
        .sign { margin-top:46px; width:100%; }
        .sign td { text-align:center; padding-top:6px; border-top:1px solid #2c3e50; font-size:11px; width:33%; }
        .sign-row td { border:none; padding-top:40px; }
    </style>
</head>
<body>
    <div class="header">
        @if(optional($appSettings)->logo_base64)<img src="{{ $appSettings->logo_base64 }}" style="height:32px;vertical-align:middle;margin-right:8px;border-radius:6px"> <span class="brand" style="vertical-align:middle">{{ $appSettings->school_name }}</span>@else<span class="brand">&#127891; {{ $appSettings->school_name ?? 'Colegio SaaS' }}</span>@endif
        <h1 style="margin-top:6px">BOLETÍN DE CALIFICACIONES</h1>
        <div class="sub">Gestión {{ $appSettings->academic_year ?? ($student->course->academic_year ?? date('Y')) }} · Emitido el {{ $date->format('d/m/Y') }}</div>
    </div>

    <div class="wrap">
        <div class="student-box">
            <table>
                <tr>
                    <td class="lbl">Estudiante:</td><td><strong>{{ $student->full_name }}</strong></td>
                    <td class="lbl">Código:</td><td>{{ $student->code }}</td>
                </tr>
                <tr>
                    <td class="lbl">Curso:</td><td>{{ optional($student->course)->name }} "{{ optional($student->course)->section }}"</td>
                    <td class="lbl">Nivel:</td><td>{{ optional($student->course)->level }}</td>
                </tr>
                <tr>
                    <td class="lbl">Tutor:</td><td>{{ optional(optional($student->course)->tutor)->full_name ?? '—' }}</td>
                    <td class="lbl">Turno:</td><td>{{ optional($student->course)->shift }}</td>
                </tr>
            </table>
        </div>

        <table class="grades">
            <thead>
                <tr>
                    <th class="left">Materia</th>
                    @foreach($periods as $p)<th>{{ $p }}</th>@endforeach
                    <th>Promedio</th>
                    <th>Situación</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td class="left">{{ $r['subject'] }}</td>
                        @foreach($periods as $p)
                            <td>{{ $r['periods'][$p] ?? '—' }}</td>
                        @endforeach
                        <td class="final-col">{{ $r['final'] ?? '—' }}</td>
                        <td class="{{ $r['status']==='Aprobado' ? 'ok' : ($r['status']==='Reprobado' ? 'bad' : '') }}">{{ $r['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($periods)+3 }}" style="padding:20px;color:#9fb0bf">Este estudiante aún no tiene calificaciones registradas.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="summary">
            Promedio general &nbsp; <span class="big {{ $generalAverage!==null ? ($generalAverage>=51?'ok':'bad') : '' }}">{{ $generalAverage ?? '—' }}</span>
        </div>

        <table class="sign">
            <tr class="sign-row"><td></td><td></td><td></td></tr>
            <tr><td>Tutor de curso</td><td>Dirección Académica</td><td>Padre / Apoderado</td></tr>
        </table>
    </div>

    <div class="footer">
        Escala de calificación: 51-100 Aprobado · 0-50 Reprobado. Documento generado por el Sistema de Gestión Escolar Colegio SaaS.
    </div>
</body>
</html>
