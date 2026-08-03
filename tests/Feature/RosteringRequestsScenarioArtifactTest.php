<?php

use App\Models\Doctor;
use App\Models\DoctorScheduleRequest;

it('runs the requests scenario with rollback by default', function () {
    $before = [Doctor::query()->count(), DoctorScheduleRequest::query()->count()];
    $this->artisan('gne:roster:lifecycle:run', ['--scenario' => 'ANAESTHESIA-ROSTER-REQUESTS-AND-AVAILABILITY', '--json' => true])->assertSuccessful();
    expect([Doctor::query()->count(), DoctorScheduleRequest::query()->count()])->toBe($before);
});

it('generates finalized JSON HTML and PDF artifacts that disclaim assignments', function () {
    $root = storage_path('framework/testing/requests-availability-report');
    $this->artisan('gne:roster:lifecycle:run', ['--scenario' => 'ANAESTHESIA-ROSTER-REQUESTS-AND-AVAILABILITY', '--artifact' => true, '--output' => $root, '--json' => true])->assertSuccessful();
    expect($root.'/report.json')->toBeFile()->and($root.'/html/index.html')->toBeFile()->and($root.'/html/visual-calendar.html')->toBeFile()->and($root.'/html/doctor-matrix.html')->toBeFile()->and($root.'/html/doctor-availability.html')->toBeFile()->and($root.'/html/conflicts.html')->toBeFile()->and($root.'/pdf/anaesthesia-requests-and-availability.pdf')->toBeFile();
    $calendar = file_get_contents($root.'/html/visual-calendar.html');
    $matrix = file_get_contents($root.'/html/doctor-matrix.html');
    $details = file_get_contents($root.'/html/doctor-availability.html');
    $json = file_get_contents($root.'/report.json');
    expect($calendar)->toContain('No assignments generated', 'Week 1', 'Week 2', 'Week 3', 'Week 4', 'Eligible', 'Explicit', 'Unspecified', '2026-09-05', '2026-09-28')
        ->and(substr_count($calendar, 'class="day '))->toBe(28)
        ->and($matrix)->toContain('Doctor-by-date Availability Matrix', 'Dr. Ana Reyes', 'Dr. Ben Cruz', 'A explicit', '– unspecified')
        ->and($details)->toContain('Dr. Ana Reyes', 'Dr. Ben Cruz', 'Dr. Carla Santos', 'Explicitly available', 'Unspecified dates')
        ->and($json)->not->toContain('/Users/', 'password')->toContain('"assignment_count": 0', '"weeks"', '"doctor_availability_matrix"', '"availability_summary"');
    foreach (['index.html', 'visual-calendar.html', 'doctor-matrix.html', 'doctor-availability.html', 'conflicts.html'] as $file) {
        expect(file_get_contents($root.'/html/'.$file))->not->toContain('href="/', 'file://', 'http://', 'https://');
    }
});
