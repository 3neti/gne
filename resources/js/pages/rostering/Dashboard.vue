<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Stethoscope } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes/rostering';
import { index as doctorsIndex } from '@/routes/rostering/doctors';
import { index as periodsIndex, show as periodShow } from '@/routes/rostering/periods';

defineProps<{ doctorCount: number; period: null | { identifier: string; title: string; start_date: string; end_date: string; status: string; roster_day_count: number; doctor_requirement_count: number; missing_staffing_count: number; finding_count: number } }>();
const breadcrumbs = [{ title: 'Anaesthesia Rostering', href: dashboard() }];
</script>

<template>
    <Head title="Anaesthesia Rostering" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="flex flex-col gap-6 p-4 md:p-6">
            <div><p class="text-sm font-medium text-muted-foreground">Operational foundation</p><h1 class="text-2xl font-semibold">Anaesthesia Rostering</h1><p class="text-muted-foreground">Explicit staffing and required-hour inputs. Generation is not yet available.</p></div>
            <div class="grid gap-4 md:grid-cols-3">
                <Card><CardHeader><CardTitle class="flex items-center gap-2"><Stethoscope class="size-5" />Active doctors</CardTitle></CardHeader><CardContent><p class="text-3xl font-semibold">{{ doctorCount }}</p><Link class="text-sm text-primary underline" :href="doctorsIndex()">Manage doctors</Link></CardContent></Card>
                <Card class="md:col-span-2"><CardHeader><CardTitle class="flex items-center gap-2"><CalendarDays class="size-5" />Latest roster period</CardTitle></CardHeader><CardContent v-if="period" class="grid gap-3 sm:grid-cols-2"><div><Link class="font-semibold text-primary underline" :href="periodShow(period.identifier)">{{ period.title }}</Link><p class="text-sm text-muted-foreground">{{ period.start_date }} – {{ period.end_date }}</p><p class="mt-2 text-sm">Status: <span class="font-medium">{{ period.status.replaceAll('_', ' ') }}</span></p></div><dl class="grid grid-cols-2 gap-2 text-sm"><div><dt class="text-muted-foreground">Roster days</dt><dd class="font-semibold">{{ period.roster_day_count }}</dd></div><div><dt class="text-muted-foreground">Targets</dt><dd class="font-semibold">{{ period.doctor_requirement_count }}</dd></div><div><dt class="text-muted-foreground">Zero staffing</dt><dd class="font-semibold">{{ period.missing_staffing_count }}</dd></div><div><dt class="text-muted-foreground">Findings</dt><dd class="font-semibold">{{ period.finding_count }}</dd></div></dl></CardContent><CardContent v-else><p class="text-muted-foreground">No roster period exists yet.</p><Link class="text-primary underline" :href="periodsIndex()">Open roster periods</Link></CardContent></Card>
            </div>
        </main>
    </AppLayout>
</template>
