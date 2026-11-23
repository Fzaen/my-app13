<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

defineProps({
    manual: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Manual Book · FAQ · ${manual.title}`" />

        <main class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
            <nav
                aria-label="Breadcrumb"
                class="text-sm text-gray-500 dark:text-gray-400"
            >
                <span>Manual Book</span>
                <span class="px-2">/</span>
                <span>FAQ</span>
                <span class="px-2">/</span>
                <span class="font-semibold text-indigo-700 dark:text-cyan-300">
                    {{ manual.title }}
                </span>
            </nav>

            <header
                class="rounded-2xl bg-gradient-to-r from-indigo-700 to-indigo-500 p-6 text-white shadow-lg sm:p-8"
            >
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-100">
                    Manual Book · FAQ
                </p>
                <h1 class="mt-2 text-3xl font-bold">{{ manual.title }}</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-indigo-50 sm:text-base">
                    {{ manual.summary }}
                </p>
            </header>

            <section
                class="rounded-2xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40"
            >
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">
                    Informasi
                </p>
                <p class="mt-2 text-gray-800 dark:text-gray-100">
                    {{ manual.announcement }}
                </p>
            </section>

            <section class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        Cara mengakses
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Scope saat ini: {{ manual.scope }}. {{ manual.credentials }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="access in manual.access"
                        :key="access.url"
                        class="flex flex-col justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                {{ access.label }}
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {{ access.description }}
                            </p>
                        </div>
                        <a
                            :href="access.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="break-all font-medium text-indigo-700 underline decoration-indigo-300 underline-offset-4 hover:text-indigo-900 dark:text-cyan-300 dark:hover:text-cyan-200"
                        >
                            {{ access.url }}
                        </a>
                    </article>
                </div>
            </section>

            <section
                class="grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]"
            >
                <article
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6"
                >
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        Panduan dan pendaftaran
                    </h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                                {{ manual.guide.label }}
                            </h3>
                            <a
                                v-if="manual.guide.url"
                                :href="manual.guide.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-flex text-sm font-medium text-indigo-700 underline underline-offset-4 dark:text-cyan-300"
                            >
                                Buka juklak
                            </a>
                            <p
                                v-else
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                File juklak belum tersedia. Tambahkan URL-nya
                                pada resources/manual-book/ba-online.json.
                            </p>
                        </div>

                        <a
                            :href="manual.registration.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center justify-between gap-4 rounded-xl bg-indigo-50 px-4 py-3 font-medium text-indigo-800 transition hover:bg-indigo-100 dark:bg-gray-800 dark:text-cyan-200 dark:hover:bg-gray-700"
                        >
                            <span>{{ manual.registration.label }}</span>
                            <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </article>

                <aside
                    class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6"
                >
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            Bantuan
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">
                            PIC: <span class="font-semibold">{{ manual.pic }}</span>
                        </p>
                    </div>
                    <p class="text-sm leading-6 text-gray-700 dark:text-gray-300">
                        {{ manual.support }}
                    </p>
                    <a
                        :href="manual.whatsapp.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    >
                        Buka Group WhatsApp
                    </a>
                </aside>
            </section>

            <p class="pb-4 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ manual.closing }}
            </p>
        </main>
    </AuthenticatedLayout>
</template>
