<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { show } from '@/routes/rostering/periods';
import { index } from '@/routes/rostering/periods/assignments';
const props = defineProps<{ period: { identifier: string; title: string }; assignmentCount: number }>();
const breadcrumbs = [{ title: props.period.title, href: show(props.period.identifier) }, { title: 'Assignments', href: index(props.period.identifier) }];
</script>
<template><Head title="Roster assignments" /><AppLayout :breadcrumbs="breadcrumbs"><main class="flex flex-col gap-5 p-4 md:p-6"><div><h1 class="text-2xl font-semibold">Roster assignments</h1><p class="text-muted-foreground">{{ assignmentCount }} primary daily assignments are stored.</p></div><div class="rounded-lg border border-dashed p-10 text-center"><h2 class="font-semibold">Manual roster editing arrives in a later slice</h2><p class="mx-auto mt-2 max-w-xl text-sm text-muted-foreground">The persistence seam enforces one counted primary assignment per doctor and date. No roster grid, generation, call logic, or multiple same-day duties are active.</p></div></main></AppLayout></template>
