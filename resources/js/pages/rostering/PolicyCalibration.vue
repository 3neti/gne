<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/RosterPolicyCalibrationController';
import AppLayout from '@/layouts/AppLayout.vue';

type Policy = { identifier: string; key: string; revision: number; status: string; selected_value: string; effective_date: string | null; decision_authority: string; source_reference: string; provisional: boolean; question: string; generation_impact: string };
const { policy, abilities } = defineProps<{ policy: { policies: Record<string, Policy>; calibration: { confirmed: string[]; provisional: string[]; unresolved_mandatory: string[]; unresolved_quality: string[]; blocks_generation: boolean; effective_policy_fingerprint: string } }; abilities: { edit_policy_calibration: boolean; confirm_policy_calibration: boolean } }>();
const form = useForm({ policy_key: '', selected_value: '', effective_from: '', source_reference: '', notes: '' });
function select(policy: Policy) {
    form.policy_key = policy.key;
    form.selected_value = policy.selected_value;
    form.effective_from = policy.effective_date ?? '';
    form.source_reference = policy.source_reference;
    form.notes = '';
}
function confirm() {
    form.post(store().url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout><Head title="Generation policy calibration" /><main class="mx-auto max-w-6xl space-y-6 p-6">
        <header><p class="text-sm font-semibold text-teal-700">Anaesthesia rostering</p><h1 class="text-3xl font-semibold">Generation policy calibration</h1><p class="text-muted-foreground">Department-wide choices used consistently by generation, validation, quality analysis, and explanations.</p></header>
        <section class="grid gap-3 md:grid-cols-4"><div class="rounded border p-4"><b>Confirmed</b><p class="text-2xl">{{ policy.calibration.confirmed.length }}</p></div><div class="rounded border border-amber-300 bg-amber-50 p-4"><b>Provisional</b><p class="text-2xl">{{ policy.calibration.provisional.length }}</p></div><div class="rounded border p-4"><b>Mandatory unresolved</b><p class="text-2xl">{{ policy.calibration.unresolved_mandatory.length }}</p></div><div class="rounded border p-4"><b>Quality unresolved</b><p class="text-2xl">{{ policy.calibration.unresolved_quality.length }}</p></div></section>
        <p class="break-all rounded bg-slate-100 p-3 font-mono text-xs">{{ policy.calibration.effective_policy_fingerprint }}</p>
        <section class="space-y-3"><article v-for="item in policy.policies" :key="item.key" class="rounded-xl border p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-semibold">{{ item.key.replaceAll('_', ' ') }}</h2><p>{{ item.question }}</p></div><span class="rounded-full px-3 py-1 text-sm" :class="item.provisional ? 'bg-amber-100 text-amber-900' : 'bg-green-100 text-green-900'">{{ item.status }}</span></div><dl class="mt-3 grid gap-2 text-sm md:grid-cols-3"><div><dt class="font-semibold">Current choice</dt><dd>{{ item.selected_value.replaceAll('_', ' ') }}</dd></div><div><dt class="font-semibold">Generation impact</dt><dd>{{ item.generation_impact }}</dd></div><div><dt class="font-semibold">Provenance</dt><dd>{{ item.source_reference }}</dd></div></dl><p v-if="item.provisional" class="mt-3 text-sm text-amber-800">This choice is provisional and has not been confirmed by the department.</p><button v-if="abilities.edit_policy_calibration" class="mt-3 rounded border px-3 py-2" @click="select(item)">Review and confirm</button></article></section>
        <form v-if="form.policy_key" class="space-y-3 rounded-xl border p-5" @submit.prevent="confirm"><h2 class="text-xl font-semibold">Explicit department confirmation</h2><label class="block">Selected choice<input v-model="form.selected_value" class="mt-1 w-full rounded border p-2" /></label><label class="block">Effective date<input v-model="form.effective_from" type="date" class="mt-1 w-full rounded border p-2" /></label><label class="block">Meeting or evidence reference<input v-model="form.source_reference" class="mt-1 w-full rounded border p-2" /></label><label class="block">Decision notes and reason<textarea v-model="form.notes" class="mt-1 w-full rounded border p-2" /></label><button :disabled="!abilities.confirm_policy_calibration || form.processing" class="rounded bg-teal-700 px-4 py-2 text-white disabled:opacity-50">Confirm as a new immutable revision</button></form>
    </main></AppLayout>
</template>
