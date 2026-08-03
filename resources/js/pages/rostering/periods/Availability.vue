<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

type Day = { date:string; weekday:string; day_type:string; required_doctor_count:number; active_doctor_count:number; eligible_doctor_count:number; explicit_available_count:number; unspecified_doctor_count:number; unavailable_doctor_count:number; leave_doctor_count:number; preferred_work_count:number; preferred_off_count:number; conflict_count:number; staffing_input_status:string };
type Week = { week:number; dates:Day[] };
type Doctor = { identifier:string; name:string; required_hours:string|null; standard_daily_hours:string; explicit_available_dates:string[]; unspecified_date_count:number; unavailable_dates:string[]; leave_dates:string[]; preferred_work_dates:string[]; preferred_off_dates:string[]; conflict_codes:string[] };
type MatrixDoctor = { doctor_identifier:string; doctor_name:string; dates:{date:string;state:string;effective_status:string;preference:string;conflicted:boolean}[] };
type Conflict = { severity:string; code:string; doctor_identifier:string; date:string; message:string; effective_state:string };

defineProps<{ period:{identifier:string;title:string;start_date:string;end_date:string;status:string}; calendar:Day[]; weeks:Week[]; doctors:Doctor[]; doctorAvailabilityMatrix:MatrixDoctor[]; availabilitySummary:{explicit_available_total:number;unspecified_total:number;unavailable_total:number;leave_total:number}; availability:unknown[]; conflicts:Conflict[] }>();

function statusClasses(status: string): string {
    if (status === 'request_conflict') {
return 'border-amber-500 bg-amber-50 dark:bg-amber-950/20';
}

    if (status === 'insufficient_eligible_pool') {
return 'border-red-500 bg-red-50 dark:bg-red-950/20';
}

    return 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/20';
}

function statusLabel(status: string): string {
    if (status === 'request_conflict') {
return 'Conflict';
}

    if (status === 'insufficient_eligible_pool') {
return 'Insufficient pool';
}

    return 'Pool sufficient';
}
</script>

<template>
    <Head :title="`${period.title} availability`" />
    <AppLayout :breadcrumbs="[{title:'Rostering',href:'/rostering'},{title:period.title,href:`/rostering/periods/${period.identifier}`},{title:'Availability',href:'#'}]">
        <main class="flex flex-col gap-6 p-4 md:p-6">
            <header><p class="text-sm text-muted-foreground">{{ period.identifier }} · {{ period.start_date }} to {{ period.end_date }}</p><h1 class="text-2xl font-semibold">Availability and Staffing Calendar — No assignments generated</h1><p class="text-muted-foreground">Accepted request evidence projected against daily staffing demand. Eligibility is not an assignment.</p></header>
            <aside class="grid gap-2 rounded-lg border border-teal-300 bg-teal-50 p-4 text-sm dark:bg-teal-950/20"><h2 class="font-semibold">Availability legend</h2><p><b>Explicit</b>: accepted available request. <b>Unspecified</b>: no accepted availability, leave, or unavailability. <b>Eligible</b>: active and not blocked; provisionally includes unspecified doctors.</p><p>Green = numeric pool sufficient · amber = request conflict/warning · red = insufficient eligible pool. Labels accompany every color.</p><p class="font-medium">Staffing input sufficiency does not prove that a valid or balanced roster can be generated.</p></aside>
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Explicit availability cells</p><strong class="text-2xl">{{ availabilitySummary.explicit_available_total }}</strong></article><article class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Unspecified cells</p><strong class="text-2xl">{{ availabilitySummary.unspecified_total }}</strong></article><article class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Unavailable cells</p><strong class="text-2xl">{{ availabilitySummary.unavailable_total }}</strong></article><article class="rounded-lg border p-4"><p class="text-xs text-muted-foreground">Leave cells</p><strong class="text-2xl">{{ availabilitySummary.leave_total }}</strong></article></section>
            <section v-for="week in weeks" :key="week.week" class="grid gap-3"><h2 class="text-lg font-semibold">Week {{ week.week }}</h2><div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7"><article v-for="day in week.dates" :key="day.date" class="rounded-lg border-2 p-3" :class="statusClasses(day.staffing_input_status)"><div class="flex items-start justify-between gap-2"><div><h3 class="font-semibold">{{ day.weekday }}</h3><p class="text-xs">{{ day.date }}</p></div><span class="rounded border bg-background/70 px-2 py-1 text-[10px] font-semibold">{{ statusLabel(day.staffing_input_status) }}</span></div><dl class="mt-3 grid grid-cols-[1fr_auto] gap-1 text-xs"><dt>Required</dt><dd class="font-semibold">{{ day.required_doctor_count }}</dd><dt>Eligible</dt><dd class="font-semibold">{{ day.eligible_doctor_count }}</dd><dt>Explicit</dt><dd>{{ day.explicit_available_count }}</dd><dt>Unspecified</dt><dd>{{ day.unspecified_doctor_count }}</dd><dt>Unavailable</dt><dd>{{ day.unavailable_doctor_count }}</dd><dt>Leave</dt><dd>{{ day.leave_doctor_count }}</dd><dt>Preferences</dt><dd>{{ day.preferred_work_count }} / {{ day.preferred_off_count }}</dd><dt>Conflicts</dt><dd>{{ day.conflict_count }}</dd></dl></article></div></section>
            <section class="grid gap-3"><h2 class="text-lg font-semibold">Doctor-by-date availability matrix</h2><p class="text-xs text-muted-foreground">A explicit · U unavailable · L leave · PW preferred work · PO preferred off · – unspecified · ! conflict. No assignment marks are shown.</p><div class="overflow-x-auto rounded-lg border"><table class="min-w-max text-center text-[10px]"><thead class="bg-muted/50"><tr><th class="sticky left-0 bg-muted p-2 text-left">Doctor</th><th v-for="day in calendar" :key="day.date" class="p-2"><span class="block">{{ day.weekday.slice(0,3) }}</span>{{ day.date.slice(5) }}</th></tr></thead><tbody><tr v-for="doctor in doctorAvailabilityMatrix" :key="doctor.doctor_identifier" class="border-t"><th class="sticky left-0 bg-background p-2 text-left"><span class="block whitespace-nowrap">{{ doctor.doctor_name }}</span><small>{{ doctor.doctor_identifier }}</small></th><td v-for="cell in doctor.dates" :key="cell.date" class="border-l p-2 font-semibold" :class="cell.conflicted ? 'bg-amber-100 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100' : ''">{{ cell.state }}</td></tr></tbody></table></div></section>
            <section class="grid gap-3"><h2 class="text-lg font-semibold">Doctor availability detail</h2><div class="grid gap-3 lg:grid-cols-2"><article v-for="doctor in doctors" :key="doctor.identifier" class="rounded-lg border p-4"><div class="flex justify-between gap-3"><div><h3 class="font-semibold">{{ doctor.name }}</h3><p class="text-xs text-muted-foreground">{{ doctor.identifier }}</p></div><p class="text-sm">{{ doctor.required_hours ?? 'No target' }} required hours</p></div><dl class="mt-3 grid grid-cols-[10rem_1fr] gap-1 text-xs"><dt>Explicitly available</dt><dd>{{ doctor.explicit_available_dates.join(', ') || 'None' }}</dd><dt>Unspecified dates</dt><dd>{{ doctor.unspecified_date_count }}</dd><dt>Unavailable</dt><dd>{{ doctor.unavailable_dates.join(', ') || 'None' }}</dd><dt>Leave</dt><dd>{{ doctor.leave_dates.join(', ') || 'None' }}</dd><dt>Preferred work</dt><dd>{{ doctor.preferred_work_dates.join(', ') || 'None' }}</dd><dt>Preferred off</dt><dd>{{ doctor.preferred_off_dates.join(', ') || 'None' }}</dd></dl></article></div></section>
            <section><h2 class="text-lg font-semibold">Request conflicts and warnings</h2><div class="mt-2 grid gap-2"><article v-for="conflict in conflicts" :key="`${conflict.doctor_identifier}-${conflict.date}-${conflict.code}`" class="rounded-lg border p-3 text-sm" :class="conflict.severity === 'error' ? 'border-red-500' : 'border-amber-500'"><b>{{ conflict.severity.toUpperCase() }} · {{ conflict.code }}</b><p>{{ conflict.doctor_identifier }} · {{ conflict.date }} · effective {{ conflict.effective_state }} — {{ conflict.message }}</p></article><p v-if="!conflicts.length" class="text-sm text-muted-foreground">No accepted request conflicts.</p></div></section>
        </main>
    </AppLayout>
</template>
