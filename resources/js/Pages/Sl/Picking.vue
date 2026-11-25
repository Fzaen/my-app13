<script setup>
import { computed, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    statusRows: {
        type: Array,
        required: true,
    },
    slRows: {
        type: Array,
        required: true,
    },
});

const activeSection = ref("status");
const statusColumns = [
    ["y2", "Kemarin · 14:00"],
    ["y6", "Kemarin · 18:00"],
    ["yat", "Kemarin · Ambil toko"],
    ["td2", "Hari ini · 14:00"],
    ["td6", "Hari ini · 18:00"],
    ["tdat", "Hari ini · Ambil toko"],
    ["tm2", "Besok · 14:00"],
    ["tm6", "Besok · 18:00"],
    ["tmat", "Besok · Ambil toko"],
    ["cm", "Cek manual"],
    ["btl", "Batal"],
];
const slaGroups = [
    { prefix: "jam14", label: "Max sertim · 14:00 / 15:00" },
    { prefix: "jam18", label: "Max sertim · 18:00 / 19:00" },
    { prefix: "total", label: "Total" },
];
const slaMetrics = [
    ["jml_pb", "PB"],
    ["jml_item", "Item"],
    ["rph", "RPH"],
    ["sudah", "DSP sudah"],
    ["belum", "DSP belum"],
    ["sesuai", "SL sesuai"],
    ["telat", "SL late"],
    ["persentase", "%"],
];
const slaRows = computed(() =>
    props.slRows.map((row) => {
        const total = {};

        [
            "jml_pb",
            "jml_item",
            "rph",
            "sudah",
            "belum",
            "sesuai",
            "telat",
        ].forEach((metric) => {
            total[metric] =
                Number(row[`jam14_${metric}`] ?? 0) +
                Number(row[`jam18_${metric}`] ?? 0);
        });
        total.persentase = serviceLevel(total.sesuai, total.jml_pb);

        return { ...row, total };
    }),
);
const generatedAt = new Intl.DateTimeFormat("id-ID", {
    dateStyle: "long",
}).format(new Date());

function formatNumber(value) {
    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function serviceLevel(satisfiedOrders, totalOrders) {
    const orders = Number(totalOrders ?? 0);

    return orders > 0
        ? (Number(satisfiedOrders ?? 0) / orders) * 100
        : 0;
}

function formatPercent(value) {
    return `${Number(value ?? 0).toLocaleString("id-ID", {
        maximumFractionDigits: 0,
    })}%`;
}

function metricValue(row, group, metric) {
    if (metric === "persentase") {
        const values =
            group.prefix === "total"
                ? row.total
                : {
                      sesuai: row[`${group.prefix}_sesuai`],
                      jml_pb: row[`${group.prefix}_jml_pb`],
                  };

        return formatPercent(
            serviceLevel(values.sesuai, values.jml_pb),
        );
    }

    const value =
        group.prefix === "total"
            ? row.total?.[metric]
            : row[`${group.prefix}_${metric}`];

    return formatNumber(value);
}

function formatDate(value) {
    const dateString = String(value).slice(0, 10);
    const [year, month, day] = dateString.split("-").map(Number);

    if (!year || !month || !day) {
        return dateString;
    }

    return new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(year, month - 1, day));
}
</script>

<template>
    <Head title="SLPicking" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        SLPICKING
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Rekap status pengiriman dan service level picking.
                    </p>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Diperbarui {{ generatedAt }}
                </p>
            </div>
        </template>

        <div class="space-y-5">
            <nav
                class="flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                aria-label="Bagian laporan SLPICKING"
            >
                <button
                    type="button"
                    :aria-pressed="activeSection === 'status'"
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="
                        activeSection === 'status'
                            ? 'bg-emerald-600 text-white'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    "
                    @click="activeSection = 'status'"
                >
                    Rekap status
                </button>
                <button
                    type="button"
                    :aria-pressed="activeSection === 'sl'"
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition"
                    :class="
                        activeSection === 'sl'
                            ? 'bg-sky-600 text-white'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'
                    "
                    @click="activeSection = 'sl'"
                >
                    SL Klik bulanan
                </button>
            </nav>

            <section
                v-if="activeSection === 'status'"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">
                        Rekap status pengiriman
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Data PB tahun berjalan; slot kemarin, hari ini, dan besok
                        mengikuti max sertim.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1200px] w-full border-collapse text-sm">
                        <thead class="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <tr>
                                <th scope="col" class="sticky left-0 z-10 min-w-52 bg-gray-100 px-3 py-3 text-left dark:bg-gray-800">
                                    Keterangan
                                </th>
                                <th
                                    v-for="column in statusColumns"
                                    :key="column[0]"
                                    scope="col"
                                    class="min-w-28 px-3 py-3 text-right"
                                >
                                    {{ column[1] }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="row in statusRows"
                                :key="row.status"
                                class="text-gray-700 dark:text-gray-200"
                            >
                                <th scope="row" class="sticky left-0 z-[1] bg-white px-3 py-2 text-left font-semibold dark:bg-gray-900">
                                    {{ row.status || "BATAL" }}
                                </th>
                                <td
                                    v-for="column in statusColumns"
                                    :key="column[0]"
                                    class="px-3 py-2 text-right"
                                >
                                    {{ Number(row[column[0]] ?? 0) === 0 ? "-" : formatNumber(row[column[0]]) }}
                                </td>
                            </tr>
                            <tr v-if="statusRows.length === 0">
                                <td
                                    colspan="12"
                                    class="px-4 py-10 text-center text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada data status.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                v-else
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">
                        SL Klik bulanan
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Rekap harian bulan berjalan. Total merupakan gabungan slot
                        max sertim 14/15 dan 18/19.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1900px] w-full border-collapse text-xs">
                        <thead class="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <tr>
                                <th
                                    rowspan="2"
                                    scope="col"
                                    class="sticky left-0 z-20 min-w-32 bg-gray-100 px-3 py-2 text-left dark:bg-gray-800"
                                >
                                    Tanggal
                                </th>
                                <th
                                    v-for="group in slaGroups"
                                    :key="group.prefix"
                                    :colspan="slaMetrics.length"
                                    scope="colgroup"
                                    class="border-l border-gray-300 px-3 py-2 text-center dark:border-gray-600"
                                    :class="
                                        group.prefix === 'jam14'
                                            ? 'bg-amber-100 dark:bg-amber-950'
                                            : group.prefix === 'jam18'
                                              ? 'bg-orange-100 dark:bg-orange-950'
                                              : 'bg-sky-100 dark:bg-sky-950'
                                    "
                                >
                                    {{ group.label }}
                                </th>
                            </tr>
                            <tr>
                                <template
                                    v-for="group in slaGroups"
                                    :key="group.prefix"
                                >
                                    <th
                                        v-for="metric in slaMetrics"
                                        :key="`${group.prefix}-${metric[0]}`"
                                        scope="col"
                                        class="min-w-20 border-l border-gray-200 px-2 py-2 text-right dark:border-gray-700"
                                    >
                                        {{ metric[1] }}
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="row in slaRows"
                                :key="row.tanggal"
                                class="text-gray-700 dark:text-gray-200"
                            >
                                <th scope="row" class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-semibold dark:bg-gray-900">
                                    {{ formatDate(row.tanggal) }}
                                </th>
                                <template
                                    v-for="group in slaGroups"
                                    :key="group.prefix"
                                >
                                    <td
                                        v-for="metric in slaMetrics"
                                        :key="`${group.prefix}-${metric[0]}`"
                                        class="border-l border-gray-100 px-2 py-2 text-right dark:border-gray-800"
                                        :class="
                                            metric[0] === 'telat'
                                                ? 'text-red-700 dark:text-red-300'
                                                : metric[0] === 'persentase'
                                                  ? 'font-semibold text-emerald-700 dark:text-emerald-300'
                                                  : ''
                                        "
                                    >
                                        {{ metricValue(row, group, metric[0]) }}
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="slaRows.length === 0">
                                <td
                                    colspan="25"
                                    class="px-4 py-10 text-center text-gray-500 dark:text-gray-400"
                                >
                                    Belum ada data SL Klik pada bulan berjalan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
