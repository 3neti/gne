<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, index, show } from '@/routes/rostering/periods';
type Period = { identifier: string; title: string; start_date: string; end_date: string; status: string; days_count: number };
defineProps<{ periods: Period[] }>();
const breadcrumbs = [{ title: 'Roster periods', href: index() }];
</script>
<template><Head title="Roster periods" /><AppLayout :breadcrumbs="breadcrumbs"><main class="flex flex-col gap-5 p-4 md:p-6"><header class="flex items-center justify-between gap-4"><div><h1 class="text-2xl font-semibold">Roster periods</h1><p class="text-muted-foreground">Explicit planning windows and lifecycle state.</p></div><Link class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground" :href="create()">Create period</Link></header><div class="grid gap-3"><Link v-for="period in periods" :key="period.identifier" :href="show(period.identifier)" class="grid gap-2 rounded-lg border p-4 hover:bg-muted/40 sm:grid-cols-4"><div class="sm:col-span-2"><p class="font-semibold">{{ period.title }}</p><p class="text-xs text-muted-foreground">{{ period.identifier }}</p></div><p class="text-sm">{{ period.start_date }} – {{ period.end_date }}</p><div class="text-sm sm:text-right"><p>{{ period.status.replaceAll('_', ' ') }}</p><p class="text-muted-foreground">{{ period.days_count }} days</p></div></Link><p v-if="periods.length === 0" class="rounded-lg border p-8 text-center text-muted-foreground">No roster periods exist.</p></div></main></AppLayout></template>
