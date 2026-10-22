<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

defineProps({
    rows: {
        type: Array,
        required: true,
    },
});

const columns = [
    { key: "tgl", label: "TGL", date: true },
    { key: "pb", label: "ALL PB" },
    { key: "sudahsend", label: "SDH_SEND" },
    { key: "batal", label: "BATAL" },
    { key: "belum_sendhh", label: "BELUM_SEND" },
    { key: "sp_ambil", label: "PICKING AMBIL" },
    { key: "sp_indopaket", label: "PICKING INDOPAKET" },
    { key: "siap_packing", label: "SIAP PACKING" },
    { key: "siap_dsp", label: "SIAP DSP" },
    { key: "siap_struk_spi", label: "SIAP STRUK SPI" },
    { key: "siap_struk_ipp", label: "SIAP STRUK IPP" },
    { key: "selesai_struk", label: "SELESAI STRUK" },
    { key: "indo_paket", label: "INDO PAKET" },
    { key: "ambil_ditoko", label: "AMBIL DI SPI" },
];

const formatNumber = (value) =>
    new Intl.NumberFormat("id-ID").format(value ?? 0);

const formatDate = (value) =>
    new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(`${value}T00:00:00`));
</script>

<template>
    <Head title="PB All" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-xl font-semibold leading-tight text-gray-800">
                    PB All
                </h1>
                <p class="text-sm text-gray-500">7 hari terakhir</p>
            </div>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div
                class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <table class="min-w-300 w-full text-sm">
                    <thead
                        class="bg-cyan-100 text-gray-800 dark:bg-slate-800 dark:text-gray-100"
                    >
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                scope="col"
                                class="whitespace-nowrap px-4 py-3 text-center text-xs font-semibold uppercase"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.tgl"
                            class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-700 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="whitespace-nowrap px-4 py-3 text-center text-gray-700"
                            >
                                {{
                                    column.date
                                        ? formatDate(row[column.key])
                                        : formatNumber(row[column.key])
                                }}
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td
                                :colspan="columns.length"
                                class="px-4 py-10 text-center text-sm text-gray-500"
                            >
                                Tidak ada data PB pada periode ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
