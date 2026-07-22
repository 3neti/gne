<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as documentSetsIndex, show as showDocumentSet } from '@/routes/document_sets';
import { show as showDocument } from '@/routes/documents';

type DocumentSet = {
    identifier: string;
    fingerprint: string;
    subject: { identifier: string; type: string };
    profile: string;
    scenario: string;
    lifecycle: {
        lifecycle_identifier: string;
        current_stage: string | null;
        next_stage: string | null;
        gaps: string[];
        stages: Array<{ identifier: string; artifact_type: string; position: number; status: string }>;
    };
    counts: Record<string, number>;
    entries: Array<{
        definition_identifier: string;
        definition_revision: number | string;
        title: string;
        readiness: 'resolved' | 'pending' | 'unavailable' | 'not_applicable';
        explanation: string;
        missing_evidence: Array<{ artifact_type: string; reason: string }>;
        resolved_document_identifier: string | null;
    }>;
};

defineProps<{ documentSets: DocumentSet[]; selectedSubject: string | null }>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Document Sets', href: documentSetsIndex() }] } });
</script>

<template>
    <Head title="Resolved Document Sets" />
    <main class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <header class="flex flex-col gap-2">
            <p class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">Derived lifecycle projection</p>
            <h1 class="text-3xl font-semibold tracking-tight">Resolved Document Sets</h1>
            <p class="max-w-3xl text-muted-foreground">Subject-scoped document readiness derived from validated definitions and accepted artifact chains.</p>
            <Link v-if="selectedSubject" :href="documentSetsIndex()" class="text-sm font-medium text-primary hover:underline">View all compilation subjects</Link>
        </header>

        <Card v-for="set in documentSets" :key="set.identifier">
            <CardHeader>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <CardTitle>{{ set.subject.identifier }}</CardTitle>
                        <CardDescription>{{ set.subject.type }} · {{ set.scenario }}</CardDescription>
                    </div>
                    <Link v-if="!selectedSubject" :href="showDocumentSet(set.subject.identifier)" class="text-sm font-medium text-primary hover:underline">Open inventory</Link>
                </div>
            </CardHeader>
            <CardContent class="grid gap-5">
                <section class="grid gap-3 md:grid-cols-4">
                    <div class="rounded-lg border p-3"><p class="text-xs text-muted-foreground">Current stage</p><p class="font-medium">{{ set.lifecycle.current_stage ?? 'Not started' }}</p></div>
                    <div class="rounded-lg border p-3"><p class="text-xs text-muted-foreground">Next stage</p><p class="font-medium">{{ set.lifecycle.next_stage ?? 'Complete' }}</p></div>
                    <div class="rounded-lg border p-3"><p class="text-xs text-muted-foreground">Resolved</p><p class="font-medium">{{ set.counts.resolved }}</p></div>
                    <div class="rounded-lg border p-3"><p class="text-xs text-muted-foreground">Pending</p><p class="font-medium">{{ set.counts.pending }}</p></div>
                </section>

                <div v-if="set.lifecycle.gaps.length" class="rounded-lg border border-destructive/40 p-3 text-sm text-destructive">Lifecycle gaps: {{ set.lifecycle.gaps.join(', ') }}</div>

                <section class="grid gap-3 md:grid-cols-2">
                    <div v-for="entry in set.entries" :key="entry.definition_identifier" class="flex items-start justify-between gap-4 rounded-lg border p-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><p class="font-medium">{{ entry.title }}</p><Badge variant="outline">{{ entry.readiness }}</Badge></div>
                            <p class="mt-1 font-mono text-xs text-muted-foreground">{{ entry.definition_identifier }} · r{{ entry.definition_revision }}</p>
                            <p class="mt-2 text-sm text-muted-foreground">{{ entry.explanation }}</p>
                            <p v-if="entry.missing_evidence.length" class="mt-1 text-xs text-muted-foreground">Missing: {{ entry.missing_evidence.map((item) => item.artifact_type).join(', ') }}</p>
                        </div>
                        <Link v-if="entry.readiness === 'resolved'" :href="showDocument({ document: entry.definition_identifier, subject: set.subject.identifier })" class="shrink-0 text-sm font-medium text-primary hover:underline">View</Link>
                    </div>
                </section>
                <p class="break-all font-mono text-xs text-muted-foreground">Set fingerprint: {{ set.fingerprint }}</p>
            </CardContent>
        </Card>
    </main>
</template>
