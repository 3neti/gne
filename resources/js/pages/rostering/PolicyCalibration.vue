<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { previewImpact, store } from '@/actions/App/Http/Controllers/RosterPolicyCalibrationController';
import AppLayout from '@/layouts/AppLayout.vue';

type Option = { value: string; label: string; description: string; impact: string; confirmation_required: boolean };
type Policy = { identifier: string; key: string; revision: number; status: string; selected_value: string; effective_date: string | null; effective_until: string | null; effective_state: string; decision_authority: string; source_reference: string; provisional: boolean; question: string; generation_impact: string; options: Option[] };
type History = { identifier: string; policy_key: string; revision: number; status: string; selected_value: string; effective_from: string | null; effective_until: string | null; effective_state: string; source_reference: string };
type Preview = { policy_key: string; current_value: string; candidate_value: string; evaluation_date: string; generation_impact: string; validation_impact: string; quality_impact: string; before_fingerprint: string; candidate_fingerprint: string; limitations: string[] };
type Calibration = { confirmed: string[]; provisional: string[]; future: string[]; expired: string[]; pending_department_decisions: number; unresolved_mandatory: string[]; unresolved_quality: string[]; blocks_generation: boolean; evaluation_date: string; effective_policy_fingerprint: string };

const { policy, abilities, impact_preview } = defineProps<{ policy: { policies: Record<string, Policy>; future_policies: History[]; expired_policies: History[]; calibration: Calibration }; abilities: { edit_policy_calibration: boolean; confirm_policy_calibration: boolean }; impact_preview?: Preview }>();
const form = useForm({ policy_key: '', selected_value: '', effective_from: '', effective_until: '', decision_authority: '', source_reference: '', notes: '' });
const previewForm = useForm({ policy_key: '', candidate_value: '' });

function humanize(value: string) {
 return value.replaceAll('_', ' ');
}
function select(item: Policy) {
    form.policy_key = item.key;
    form.selected_value = item.selected_value;
    form.effective_from = item.effective_date ?? policy.calibration.evaluation_date;
    form.effective_until = item.effective_until ?? '';
    form.decision_authority = item.provisional ? '' : item.decision_authority;
    form.source_reference = item.source_reference;
    form.notes = '';
}
function confirm() {
 form.post(store().url, { preserveScroll: true });
}
function preview() {
    previewForm.policy_key = form.policy_key;
    previewForm.candidate_value = form.selected_value;
    previewForm.post(previewImpact().url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Generation policy calibration" />
        <main class="mx-auto max-w-7xl space-y-6 p-6">
            <header><p class="text-sm font-semibold text-teal-700">Anaesthesia rostering</p><h1 class="text-3xl font-semibold">Generation policy calibration</h1><p class="text-muted-foreground">Closed department choices resolved for {{ policy.calibration.evaluation_date }}. Future decisions do not activate early.</p></header>
            <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl border p-4"><b>Confirmed decisions</b><p class="text-2xl">{{ policy.calibration.confirmed.length }}</p></div>
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4"><b>Working assumptions</b><p class="text-2xl">{{ policy.calibration.provisional.length }}</p></div>
                <div class="rounded-xl border border-rose-300 bg-rose-50 p-4"><b>Pending confirmation</b><p class="text-2xl">{{ policy.calibration.pending_department_decisions }}</p></div>
                <div class="rounded-xl border p-4"><b>Blocking unresolved</b><p class="text-2xl">{{ policy.calibration.unresolved_mandatory.length }}</p></div>
                <div class="rounded-xl border p-4"><b>Non-blocking unresolved</b><p class="text-2xl">{{ policy.calibration.unresolved_quality.length }}</p></div>
            </section>

            <section class="space-y-3">
                <article v-for="item in policy.policies" :key="item.key" class="rounded-xl border p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-semibold capitalize">{{ humanize(item.key) }}</h2><p>{{ item.question }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-sm">{{ item.effective_state }} · r{{ item.revision }}</span></div>
                    <dl class="mt-4 grid gap-3 text-sm md:grid-cols-4"><div><dt class="font-semibold">Current effective choice</dt><dd>{{ humanize(item.selected_value) }}</dd></div><div><dt class="font-semibold">Status</dt><dd>{{ item.status }}</dd></div><div><dt class="font-semibold">Effective window</dt><dd>{{ item.effective_date ?? 'repository fallback' }} — {{ item.effective_until ?? 'open' }}</dd></div><div><dt class="font-semibold">Authority and source</dt><dd>{{ item.decision_authority }}<br>{{ item.source_reference }}</dd></div></dl>
                    <p v-if="item.provisional" class="mt-3 rounded bg-amber-50 p-3 text-sm text-amber-900">Provisional fallback: usable for the demonstration, but still awaiting department confirmation.</p>
                    <div class="mt-4 grid gap-2 md:grid-cols-2"><div v-for="option in item.options" :key="option.value" class="rounded-lg border p-3"><p class="font-semibold">{{ option.label }}</p><p class="text-sm text-slate-600">{{ option.description }}</p><p class="mt-2 text-sm"><b>Impact:</b> {{ option.impact }}</p></div></div>
                    <button v-if="abilities.edit_policy_calibration" type="button" class="mt-4 rounded border px-3 py-2" @click="select(item)">Review decision</button>
                </article>
            </section>

            <form v-if="form.policy_key" class="space-y-4 rounded-xl border-2 border-teal-700 p-5" @submit.prevent="confirm">
                <h2 class="text-xl font-semibold">Department decision: {{ humanize(form.policy_key) }}</h2>
                <fieldset class="space-y-2"><legend class="font-semibold">Choose one registered option</legend><label v-for="option in policy.policies[form.policy_key].options" :key="option.value" class="flex cursor-pointer gap-3 rounded-lg border p-3" :class="form.selected_value === option.value ? 'border-teal-600 bg-teal-50' : ''"><input v-model="form.selected_value" type="radio" :value="option.value" /><span><b>{{ option.label }}</b><span class="block text-sm">{{ option.description }}</span><span class="block text-sm text-slate-600">{{ option.impact }}</span></span></label></fieldset>
                <div class="grid gap-3 md:grid-cols-2"><label>Effective from<input v-model="form.effective_from" type="date" class="mt-1 w-full rounded border p-2" /></label><label>Effective until (optional)<input v-model="form.effective_until" type="date" class="mt-1 w-full rounded border p-2" /></label><label>Decision authority<input v-model="form.decision_authority" class="mt-1 w-full rounded border p-2" placeholder="Name and designation" /></label><label>Meeting or evidence reference<input v-model="form.source_reference" class="mt-1 w-full rounded border p-2" /></label></div>
                <label class="block">Decision notes and reason<textarea v-model="form.notes" class="mt-1 w-full rounded border p-2" /></label>
                <div class="flex gap-3"><button type="button" :disabled="previewForm.processing" class="rounded border px-4 py-2" @click="preview">Preview impact without saving</button><button :disabled="!abilities.confirm_policy_calibration || form.processing" class="rounded bg-teal-700 px-4 py-2 text-white disabled:opacity-50">Confirm immutable revision</button></div>
            </form>

            <section v-if="impact_preview" class="rounded-xl border border-blue-300 bg-blue-50 p-5"><h2 class="text-xl font-semibold">No-mutation impact preview</h2><p>{{ humanize(impact_preview.current_value) }} → {{ humanize(impact_preview.candidate_value) }}</p><dl class="mt-3 grid gap-3 md:grid-cols-3"><div><dt class="font-semibold">Generation</dt><dd>{{ impact_preview.generation_impact }}</dd></div><div><dt class="font-semibold">Validation</dt><dd>{{ impact_preview.validation_impact }}</dd></div><div><dt class="font-semibold">Quality</dt><dd>{{ impact_preview.quality_impact }}</dd></div></dl><p class="mt-3 text-sm">{{ impact_preview.limitations.join(' ') }}</p></section>

            <section class="grid gap-4 md:grid-cols-2"><div class="rounded-xl border p-5"><h2 class="text-lg font-semibold">Future scheduled choices</h2><p v-if="!policy.future_policies.length" class="text-sm text-slate-600">None.</p><p v-for="item in policy.future_policies" :key="item.identifier" class="mt-2 text-sm">{{ humanize(item.policy_key) }} r{{ item.revision }}: {{ humanize(item.selected_value) }} from {{ item.effective_from }}</p></div><div class="rounded-xl border p-5"><h2 class="text-lg font-semibold">Expired and superseded history</h2><p v-if="!policy.expired_policies.length" class="text-sm text-slate-600">None.</p><p v-for="item in policy.expired_policies" :key="item.identifier" class="mt-2 text-sm">{{ humanize(item.policy_key) }} r{{ item.revision }}: {{ humanize(item.selected_value) }} until {{ item.effective_until }}</p></div></section>
            <details class="rounded border p-3"><summary>Technical policy identity</summary><p class="mt-2 break-all font-mono text-xs">{{ policy.calibration.effective_policy_fingerprint }}</p></details>
        </main>
    </AppLayout>
</template>
