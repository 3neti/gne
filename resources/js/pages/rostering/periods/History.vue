<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { index as periodsIndex, show as periodShow } from '@/routes/rostering/periods';
import { show as rosterShow } from '@/routes/rostering/periods/roster';

type HistoryEntry = {
    identifier?: string;
    revision_number?: number;
    action?: string;
    entity_type?: string;
    entity_identifier?: string;
    reason: string | null;
    validation_status?: string;
    created_at: string | null;
    changes?: number;
};

const props = defineProps<{ period: { identifier: string; title: string }; section: 'revisions' | 'audit'; entries: HistoryEntry[] }>();
const breadcrumbs = [{ title: 'Roster periods', href: periodsIndex() }, { title: props.period.title, href: periodShow(props.period.identifier) }, { title: 'Manual roster', href: rosterShow(props.period.identifier) }, { title: props.section === 'revisions' ? 'Revision history' : 'Audit history', href: rosterShow(props.period.identifier) }];
</script>

<template>
    <Head :title="section === 'revisions' ? 'Roster revision history' : 'Roster audit history'" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="flex flex-col gap-6 p-4 md:p-6">
            <header><p class="text-sm text-muted-foreground">{{ period.identifier }}</p><h1 class="text-2xl font-semibold">{{ section === 'revisions' ? 'Complete revision history' : 'Complete period audit history' }}</h1><p class="text-muted-foreground">{{ entries.length }} exact-period records. Revision validation describes the roster state after each command.</p></header>
            <Link :href="rosterShow(period.identifier)" class="text-sm font-medium text-primary underline">Back to manual roster</Link>
            <div class="grid gap-3"><article v-for="(entry, index) in entries" :key="entry.identifier ?? `${entry.action}-${entry.entity_identifier}-${entry.created_at}-${index}`" class="rounded-xl border p-4"><strong v-if="section === 'revisions'">Revision {{ entry.revision_number }} · {{ entry.validation_status?.replaceAll('_', ' ') }}</strong><strong v-else>{{ entry.action }}</strong><p>{{ entry.reason }}</p><dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 text-xs text-muted-foreground"><dt>Identity</dt><dd>{{ entry.identifier ?? entry.entity_identifier }}</dd><dt>Recorded</dt><dd>{{ entry.created_at }}</dd><template v-if="entry.entity_type"><dt>Entity type</dt><dd>{{ entry.entity_type }}</dd></template><template v-if="entry.changes !== undefined"><dt>Changes</dt><dd>{{ entry.changes }}</dd></template></dl></article></div>
        </main>
    </AppLayout>
</template>
