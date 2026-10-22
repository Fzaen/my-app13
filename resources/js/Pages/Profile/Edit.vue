<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

defineProps({
    userId: {
        type: [String, Number],
        required: true,
    },
    databaseLabel: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Database session
            </h2>
        </template>

        <div class="px-4 py-10 sm:px-6 lg:px-8">
            <div
                class="grid gap-px overflow-hidden rounded-2xl border border-gray-200 bg-gray-200 sm:grid-cols-2"
            >
                <div class="bg-white p-6 sm:p-8">
                    <p class="text-sm font-medium text-gray-500">
                        Connected database
                    </p>
                    <p class="mt-2 text-lg font-semibold text-gray-900">
                        {{ databaseLabel }}
                    </p>
                </div>
                <div class="bg-white p-6 sm:p-8">
                    <p class="text-sm font-medium text-gray-500">
                        Signed-in user
                    </p>
                    <p
                        class="mt-2 break-all text-lg font-semibold text-gray-900"
                    >
                        {{ userId }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
