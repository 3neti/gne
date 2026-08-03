<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { preview as previewGeneration, store } from '@/actions/App/Http/Controllers/RosterGenerationController';
import AppLayout from '@/layouts/AppLayout.vue';

interface Props { period: { identifier: string; title: string; status: string }; readiness: { doctor_count: number; date_count: number; required_slots: number; required_hours_complete: boolean; existing_assignments: number }; generator: Record<string, unknown>; preview?: any; committed?: any }
const props = defineProps<Props>();
</script>

<template>
    <AppLayout>
        <Head title="Draft roster generation" />
        <main class="mx-auto flex max-w-7xl flex-col gap-6 p-6">
            <header><p class="text-sm font-medium text-teal-700 dark:text-teal-300">Anaesthesia rostering</p><h1 class="text-3xl font-semibold">Generate draft roster</h1><p>{{ period.title }} · {{ period.status }}</p></header>
            <aside class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"><strong>This generator creates an editable draft.</strong> It does not guarantee mathematical optimality and must be reviewed before publication. Unspecified availability remains provisionally eligible.</aside>
            <section class="grid gap-4 md:grid-cols-4"><div class="rounded-lg border p-4"><b>Doctors</b><p class="text-2xl">{{ readiness.doctor_count }}</p></div><div class="rounded-lg border p-4"><b>Dates</b><p class="text-2xl">{{ readiness.date_count }}</p></div><div class="rounded-lg border p-4"><b>Required slots</b><p class="text-2xl">{{ readiness.required_slots }}</p></div><div class="rounded-lg border p-4"><b>Existing assignments</b><p class="text-2xl">{{ readiness.existing_assignments }}</p></div></section>
            <div class="flex gap-3"><button class="rounded bg-slate-700 px-4 py-2 text-white" @click="router.post(previewGeneration(props.period.identifier).url)">Preview generation</button><button class="rounded bg-teal-700 px-4 py-2 text-white" :disabled="!preview" @click="router.post(store(props.period.identifier).url)">Commit generated draft</button></div>
            <section v-if="preview" class="space-y-4"><h2 class="text-2xl font-semibold">Preview</h2><p class="font-mono text-xs">{{ preview.fingerprint }}</p><div class="grid gap-3 md:grid-cols-4"><div v-for="(value, key) in preview.summary" :key="key" class="rounded border p-3"><b>{{ String(key).replaceAll('_', ' ') }}</b><p>{{ value }}</p></div></div><h3 class="text-xl font-semibold">Findings</h3><ul class="space-y-2"><li v-for="finding in preview.findings" :key="`${finding.code}-${finding.date}-${finding.doctor_identifier}`" class="rounded border p-3">{{ finding.severity }} · {{ finding.code }} — {{ finding.message }}</li></ul></section>
            <p v-if="committed" class="rounded border border-green-300 bg-green-50 p-4">Committed {{ committed.generation_identifier }} as {{ committed.revision_identifier }}.</p>
        </main>
    </AppLayout>
</template>
