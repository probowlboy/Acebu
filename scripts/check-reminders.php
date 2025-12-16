<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Models\Appointment;

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apps = Appointment::where('patient_id', 2)->get()->toArray();
echo "Appointments: " . count($apps) . PHP_EOL;
foreach ($apps as $a) {
    echo "- id={$a['id']} service={$a['service_name']} status={$a['status']} date={$a['appointment_date']}\n";
}

function parseDate($s){
    if (!$s) return null;
    // strip timezone and fractional seconds (e.g., 2025-12-15T10:00:00.000000Z)
    $s = preg_replace('/(\.\d+)?Z$/', '', $s);
    $parts = explode('T', $s);
    $datePart = $parts[0];
    $timePart = isset($parts[1]) ? $parts[1] : '00:00:00';
    // trim fractional seconds if present
    $timePart = preg_replace('/\.(\d+)$/', '', $timePart);
    $d = explode('-', $datePart);
    if (count($d) < 3) return null;
    $y = $d[0]; $m = $d[1]; $day = $d[2];
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', "$y-$m-$day $timePart");
    return $dt ?: null;
}

function localIsoDate(){ $dt = new DateTime(); return $dt->format('Y-m-d'); }
function dateIsToday($s){ $d = parseDate($s); if(!$d) return false; return $d->format('Y-m-d') === localIsoDate(); }
function dateIsTomorrow($s){ $d = parseDate($s); if(!$d) return false; $tom = new DateTime(); $tom->modify('+1 day'); return $d->format('Y-m-d') === $tom->format('Y-m-d'); }
function dateIsThisWeek($s){ $d = parseDate($s); if(!$d) return false; $now = new DateTime(); $day = (int)$now->format('w'); $diffToMonday = ($day + 6) % 7; $start = clone $now; $start->modify('-' . $diffToMonday . ' days'); $start->setTime(0,0,0); $end = clone $start; $end->modify('+6 days'); $end->setTime(23,59,59); $check = new DateTime($d->format('Y-m-d') . ' 00:00:00'); return $check >= $start && $check <= $end; }
function dateMatchesFilter($s, $f){ if(!$s) return false; if(!$f || $f === 'all') return true; if($f === 'today') return dateIsToday($s); if($f === 'tomorrow') return dateIsTomorrow($s); if($f === 'week') return dateIsThisWeek($s); return true; }

$filters = ['all', 'today', 'tomorrow', 'week'];
foreach ($filters as $f) {
    echo "\nFilter: $f\n";
    $hasToday = false;
    foreach ($apps as $app) {
        if (!$app['appointment_date']) continue;
        if (!dateMatchesFilter($app['appointment_date'], $f)) continue;
        $status = $app['status'] ?? '';
        if ($f === 'all' ? $status === 'confirmed' : ($status === 'confirmed' || $status === 'pending')) { $hasToday = true; break; }
    }
    echo "hasToday: " . ($hasToday ? 'yes' : 'no') . "\n";
    $upcoming = array_filter($apps, function($app) use($f){
        $appDate = $app['appointment_date'] ? explode('T', $app['appointment_date'])[0] : '';
        $today = localIsoDate();
        $status = $app['status'] ?? '';
        $statusOk = ($f === 'all') ? ($status === 'confirmed') : ($status === 'confirmed' || $status === 'pending');
        if (!$statusOk) return false;
        if ($appDate > $today && dateMatchesFilter($app['appointment_date'], $f)) return true;
        return false;
    });
    echo 'upcoming count: ' . count($upcoming) . "\n";
}

echo "\nDone\n";
