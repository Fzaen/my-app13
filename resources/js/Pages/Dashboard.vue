<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="px-4 py-10 sm:px-6 lg:px-8">
            <section
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-2 border-b border-gray-100 px-6 py-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium text-gray-500">
                            Signed in as
                        </p>
                        <h3 class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ $page.props.auth.user.name }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-500">Active database session</p>
                </div>
                <div
                    class="grid divide-y divide-gray-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0"
                >
                    <div class="px-6 py-5">
                        <p
                            class="text-xs font-semibold uppercase text-gray-500"
                        >
                            User ID
                        </p>
                        <p
                            class="mt-2 break-all text-sm font-medium text-gray-900"
                        >
                            {{ $page.props.auth.user.name }}
                        </p>
                    </div>
                    <div class="px-6 py-5">
                        <p
                            class="text-xs font-semibold uppercase text-gray-500"
                        >
                            Database
                        </p>
                        <p class="mt-2 text-sm font-medium text-gray-900">
                            {{ $page.props.selectedDatabase }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
