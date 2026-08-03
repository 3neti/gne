<?php

namespace App\Http\Controllers;

use App\Application\Rostering\DeactivateDoctor;
use App\Application\Rostering\RegisterDoctor;
use App\Application\Rostering\UpdateDoctor;
use App\Domain\Rostering\ContractedHoursPeriod;
use App\Domain\Rostering\EmploymentType;
use App\Http\Requests\StoreDoctorRequest;
use App\Http\Requests\UpdateDoctorRequest;
use App\Models\Doctor;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DoctorController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Doctor::class);

        return Inertia::render('rostering/doctors/Index', ['doctors' => Doctor::query()->orderByDesc('active')->orderBy('full_name')->get()->map(fn (Doctor $doctor): array => $this->doctorData($doctor))]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Doctor::class);

        return Inertia::render('rostering/doctors/Form', ['doctor' => null, 'employmentTypes' => $this->enumOptions(EmploymentType::cases()), 'contractPeriods' => $this->enumOptions(ContractedHoursPeriod::cases())]);
    }

    public function store(StoreDoctorRequest $request, RegisterDoctor $register): RedirectResponse
    {
        $doctor = $register->handle($request->user(), $request->validated());

        return to_route('rostering.doctors.edit', $doctor)->with('success', 'Doctor registered.');
    }

    public function edit(Doctor $doctor): Response
    {
        Gate::authorize('update', $doctor);

        return Inertia::render('rostering/doctors/Form', ['doctor' => $this->doctorData($doctor), 'employmentTypes' => $this->enumOptions(EmploymentType::cases()), 'contractPeriods' => $this->enumOptions(ContractedHoursPeriod::cases())]);
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor, UpdateDoctor $update): RedirectResponse
    {
        $attributes = $request->safe()->except('reason');
        $update->handle($request->user(), $doctor, $attributes, $request->validated('reason'));

        return back()->with('success', 'Doctor updated.');
    }

    public function destroy(Request $request, Doctor $doctor, DeactivateDoctor $deactivate): RedirectResponse
    {
        Gate::authorize('update', $doctor);
        $deactivate->handle($request->user(), $doctor, $request->string('reason')->toString() ?: null);

        return to_route('rostering.doctors.index')->with('success', 'Doctor deactivated.');
    }

    /** @return array<string, mixed> */
    private function doctorData(Doctor $doctor): array
    {
        return ['identifier' => $doctor->identifier, 'full_name' => $doctor->full_name, 'employee_identifier' => $doctor->employee_identifier, 'employment_type' => $doctor->employment_type->value, 'active' => $doctor->active, 'contracted_hours' => $doctor->contracted_hours, 'contracted_hours_period' => $doctor->contracted_hours_period?->value, 'standard_daily_hours' => $doctor->standard_daily_hours, 'notes' => $doctor->notes];
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(fn (BackedEnum $case): array => ['value' => (string) $case->value, 'label' => str((string) $case->value)->replace('_', ' ')->title()->toString()], $cases);
    }
}
