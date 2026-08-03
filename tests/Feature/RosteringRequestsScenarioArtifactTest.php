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
    expect($root.'/report.json')->toBeFile()->and($root.'/html/index.html')->toBeFile()->and($root.'/html/availability-calendar.html')->toBeFile()->and($root.'/html/doctor-availability.html')->toBeFile()->and($root.'/html/conflicts.html')->toBeFile()->and($root.'/pdf/anaesthesia-requests-and-availability.pdf')->toBeFile();
    expect(file_get_contents($root.'/html/availability-calendar.html'))->toContain('No assignments generated')->toContain('2026-09-28');
    expect(file_get_contents($root.'/html/doctor-availability.html'))->toContain('Dr. Ana Reyes')->toContain('Dr. Ben Cruz')->toContain('Dr. Carla Santos');
    expect(file_get_contents($root.'/report.json'))->not->toContain('/Users/')->not->toContain('password')->toContain('"assignment_count": 0');
});
