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
import { documents } from '@/routes';

type Evidence = {
    artifact_identifier: string;
    artifact_revision: string | number;
    artifact_type: string;
    source_path: string;
    value_path: string;
};
type Field = {
    identifier: string;
    label: string;
    value: unknown;
    evidence: Evidence;
};
type Section = { identifier: string; title: string; fields: Field[] };
type Projection = {
    identifier: string;
    title: string;
    status: string;
    sections: Section[];
    actions: Array<{ identifier: string; label: string; intent: string }>;
    metadata: {
        document_definition: string;
        definition_identifier: string;
        definition_revision: string | number;
        resolution_fingerprint: string;
        primary_artifact: {
            identifier: string;
            revision: string | number;
            type: string;
            source_path: string;
        };
        profile: string;
        scenario: string;
        audience: string[];
    };
};

defineProps<{
    document: {
        identifier: string;
        evidence: Array<Omit<Evidence, 'value_path'>>;
        resolution_fingerprint: string;
    };
    projection: Projection;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Resolved Documents', href: documents() }],
    },
});
</script>

<template>
    <Head :title="projection.title" />
    <main class="flex flex-1 flex-col gap-6 p-4 md:p-8">
        <header class="flex flex-col gap-3">
            <Link
                :href="documents()"
                class="text-sm text-muted-foreground hover:text-foreground"
            >
                ← All resolved documents
            </Link>
            <div class="flex flex-wrap items-center gap-3">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                >
                    Browser projection of ResolvedDocument
                </p>
                <Badge variant="outline">{{ projection.status }}</Badge>
            </div>
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ projection.title }}
            </h1>
            <p class="font-mono text-xs text-muted-foreground">
                {{ document.identifier }}
            </p>
        </header>

        <section class="grid gap-4">
            <Card
                v-for="section in projection.sections"
                :key="section.identifier"
            >
                <CardHeader>
                    <CardTitle>{{ section.title }}</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="field in section.fields"
                        :key="field.identifier"
                        class="rounded-lg border p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ field.label }}
                        </p>
                        <p class="mt-1 text-lg font-medium">
                            {{ field.value }}
                        </p>
                        <div
                            class="mt-3 grid gap-1 border-t pt-3 font-mono text-xs text-muted-foreground"
                        >
                            <span>
                                {{ field.evidence.artifact_identifier }} · r{{
                                    field.evidence.artifact_revision
                                }}
                            </span>
                            <span>{{ field.evidence.value_path }}</span>
                            <span class="break-all">
                                {{ field.evidence.source_path }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Card v-if="projection.actions.length">
            <CardHeader>
                <CardTitle>Declared actions</CardTitle>
                <CardDescription>
                    Semantic intents only; this workbench does not execute them.
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <Badge
                    v-for="action in projection.actions"
                    :key="action.identifier"
                    variant="secondary"
                >
                    {{ action.label }} · {{ action.intent }}
                </Badge>
            </CardContent>
        </Card>

        <footer class="grid gap-1 text-xs text-muted-foreground">
            <span>
                Definition: {{ projection.metadata.document_definition }}
            </span>
            <span>Profile: {{ projection.metadata.profile }}</span>
            <span>Scenario: {{ projection.metadata.scenario }}</span>
            <span class="break-all">
                Resolution fingerprint:
                {{ document.resolution_fingerprint }}
            </span>
        </footer>
    </main>
</template>
