<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as showDocument } from '@/routes/documents';

type InventoryItem = {
    identifier: string;
    title: string;
    description?: string;
    path: string;
    type?: string;
    revision?: string | number;
    status?: string;
};
type RepositoryExplanation = {
    milestone: string;
    thesis: string;
    canonical_source_path: string;
    generated_projection_path: string;
    validation: { valid: boolean; findings: number };
    profiles: InventoryItem[];
    scenarios: InventoryItem[];
    artifact_types: string[];
    artifact_count: number;
    semantic_index: { available: boolean; path: string };
    materialization: {
        status: string;
        fingerprint?: string;
        completed_at?: string;
    };
};

defineProps<{
    section: string;
    repository: RepositoryExplanation;
    findings: Array<{
        severity: string;
        code: string;
        message: string;
        source_path?: string;
        location?: string;
        remediation?: string;
    }>;
    documents: Array<{
        identifier: string;
        status: 'resolved' | 'unresolved';
        resolved_document?: string;
        subject: { identifier: string; type: string };
        reason?: string;
    }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'GNE Workbench', href: dashboard() }] },
});
</script>

<template>
    <Head :title="`GNE · ${section}`" />
    <main class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <header class="flex flex-col gap-2">
            <div class="flex flex-wrap items-center gap-3">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                >
                    Repository workbench
                </p>
                <Badge
                    :variant="
                        repository.validation.valid ? 'default' : 'destructive'
                    "
                    >{{
                        repository.validation.valid ? 'Valid' : 'Invalid'
                    }}</Badge
                >
            </div>
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ repository.milestone }}
            </h1>
            <p class="max-w-3xl text-muted-foreground">
                {{ repository.thesis }}
            </p>
        </header>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card
                ><CardHeader
                    ><CardDescription>Canonical source</CardDescription
                    ><CardTitle class="font-mono text-base">{{
                        repository.canonical_source_path
                    }}</CardTitle></CardHeader
                ></Card
            >
            <Card
                ><CardHeader
                    ><CardDescription>Generated state</CardDescription
                    ><CardTitle class="font-mono text-base">{{
                        repository.generated_projection_path
                    }}</CardTitle></CardHeader
                ></Card
            >
            <Card
                ><CardHeader
                    ><CardDescription>Profiles / scenarios</CardDescription
                    ><CardTitle
                        >{{ repository.profiles.length }} /
                        {{ repository.scenarios.length }}</CardTitle
                    ></CardHeader
                ></Card
            >
            <Card
                ><CardHeader
                    ><CardDescription>Artifact revisions</CardDescription
                    ><CardTitle>{{
                        repository.artifact_count
                    }}</CardTitle></CardHeader
                ></Card
            >
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader
                    ><CardTitle>Repository inventory</CardTitle
                    ><CardDescription
                        >Derived from authored profile and scenario
                        definitions.</CardDescription
                    ></CardHeader
                >
                <CardContent class="grid gap-5">
                    <div>
                        <h2 class="mb-2 text-sm font-medium">Profiles</h2>
                        <div
                            v-for="profile in repository.profiles"
                            :key="profile.identifier"
                            class="rounded-lg border p-3"
                        >
                            <p class="font-medium">{{ profile.title }}</p>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ profile.identifier }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <h2 class="mb-2 text-sm font-medium">Scenarios</h2>
                        <div
                            v-for="scenario in repository.scenarios"
                            :key="scenario.identifier"
                            class="rounded-lg border p-3"
                        >
                            <p class="font-medium">{{ scenario.title }}</p>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ scenario.identifier }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader
                    ><CardTitle>Projection health</CardTitle
                    ><CardDescription
                        >Disposable state can be rebuilt from repository
                        evidence.</CardDescription
                    ></CardHeader
                >
                <CardContent class="grid gap-4 text-sm">
                    <div class="flex justify-between gap-4 border-b pb-3">
                        <span>Semantic index</span
                        ><Badge variant="outline">{{
                            repository.semantic_index.available
                                ? 'available'
                                : 'not built'
                        }}</Badge>
                    </div>
                    <div class="flex justify-between gap-4 border-b pb-3">
                        <span>Materialization</span
                        ><Badge variant="outline">{{
                            repository.materialization.status
                        }}</Badge>
                    </div>
                    <div>
                        <p class="mb-2 font-medium">Artifact types</p>
                        <div class="flex flex-wrap gap-2">
                            <Badge
                                v-for="type in repository.artifact_types"
                                :key="type"
                                variant="secondary"
                                >{{ type }}</Badge
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Card>
            <CardHeader>
                <CardTitle>Resolved Documents</CardTitle>
                <CardDescription
                    >Compiler IR available from accepted repository
                    artifacts.</CardDescription
                >
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="document in documents"
                    :key="`${document.subject.identifier}-${document.identifier}`"
                    class="flex items-center justify-between gap-4 rounded-lg border p-3"
                >
                    <div class="min-w-0">
                        <p class="truncate font-mono text-sm">
                            {{ document.identifier }}
                        </p>
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ document.subject.identifier }}
                        </p>
                        <p
                            v-if="document.reason"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ document.reason }}
                        </p>
                    </div>
                    <Link
                        v-if="document.status === 'resolved'"
                        :href="showDocument({ document: document.identifier, subject: document.subject.identifier })"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        View
                    </Link>
                    <Badge v-else variant="outline">Deferred evidence</Badge>
                </div>
            </CardContent>
        </Card>

        <Card v-if="findings.length"
            ><CardHeader><CardTitle>Validation findings</CardTitle><CardDescription>{{ findings.filter((finding) => finding.severity === 'error').length }} errors · {{ findings.filter((finding) => finding.severity === 'warning').length }} warnings</CardDescription></CardHeader
            ><CardContent class="grid gap-2"
                ><div
                    v-for="finding in findings"
                    :key="`${finding.code}-${finding.source_path}`"
                    class="rounded-lg border p-3 text-sm"
                >
                    <strong>{{ finding.code }}</strong> — {{ finding.message }}
                    <p v-if="finding.source_path" class="mt-1 font-mono text-xs text-muted-foreground">{{ finding.source_path }}{{ finding.location ? `:${finding.location}` : '' }}</p>
                    <p v-if="finding.remediation" class="mt-1 text-xs text-muted-foreground">Remediation: {{ finding.remediation }}</p>
                </div></CardContent
            ></Card
        >
        <p class="text-xs text-muted-foreground">
            This workbench is read-only. Canonical business source remains
            file-authored and version controlled.
        </p>
    </main>
</template>
