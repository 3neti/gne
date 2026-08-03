<?php

namespace Database\Seeders;

use App\Application\Rostering\CreateRosterPeriod;
use App\Application\Rostering\SetDoctorRosterRequirement;
use App\Domain\Rostering\EmploymentType;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnaesthesiaRosteringDemoSeeder extends Seeder
{
    public function run(CreateRosterPeriod $createPeriod, SetDoctorRosterRequirement $setRequirement): void
    {
        $administrator = User::query()->firstOrCreate(
            ['email' => 'roster.demo@example.test'],
            ['name' => 'Roster Demo Administrator', 'password' => 'password', 'email_verified_at' => now(), 'is_roster_administrator' => true],
        );
        $administrator->forceFill(['is_roster_administrator' => true])->save();
        $names = ['Ari Santos', 'Bea Navarro', 'Cleo Ramos', 'Dani Lim', 'Eli Cruz', 'Fran Reyes', 'Gio Tan', 'Hana Yu', 'Ira Flores', 'Jules Co'];
        $doctors = collect($names)->map(function (string $name, int $index): Doctor {
            return Doctor::query()->firstOrCreate(['identifier' => sprintf('DOCTOR-%06d', $index + 1)], [
                'full_name' => $name, 'employee_identifier' => sprintf('00%04d', $index + 1), 'employment_type' => $index >= 7 ? EmploymentType::PartTime : EmploymentType::FullTime,
                'active' => true, 'contracted_hours' => $index >= 7 ? 20 : 40, 'contracted_hours_period' => 'weekly', 'standard_daily_hours' => 8, 'notes' => 'Fictional demonstration data.',
            ]);
        });
        $period = $createPeriod->handle($administrator, ['identifier' => 'ROSTER-2026-09', 'title' => 'September 2026 demonstration roster', 'start_date' => '2026-09-01', 'end_date' => '2026-09-28', 'default_weekday_requirement' => 6, 'default_weekend_requirement' => 3, 'notes' => 'Fictional demonstration period; no assignments generated.']);
        $doctors->each(fn (Doctor $doctor): mixed => $setRequirement->handle($administrator, $period, $doctor, $doctor->employment_type === EmploymentType::PartTime ? 80 : 160));
    }
}
