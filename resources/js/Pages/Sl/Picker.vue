<script setup>
import { computed, ref } from "vue";
import Modal from "@/Components/Modal.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router, useForm } from "@inertiajs/vue3";

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    rows: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    tglstart: props.filters.tglstart,
    tglend: props.filters.tglend,
});
const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref("");
const detailRows = ref([]);
const detailPage = ref(1);
const detailHasNext = ref(false);
const detailHasPrevious = ref(false);
const activePicker = ref("");
const activePickerUnassigned = ref(false);

const numericColumns = [
    "jml_pb",
    "jml_item",
    "rph_pb",
    "item_terpicking",
    "item_sebagian",
    "item_nol",
    "rph_terpicking",
    "rph_real_sebagian",
    "rph_lost_sebagian",
    "rph_nol",
];
const summaryTotals = computed(() =>
    props.rows.reduce((totals, row) => {
        numericColumns.forEach((column) => {
            totals[column] += Number(row[column] ?? 0);
        });

        return totals;
    }, Object.fromEntries(numericColumns.map((column) => [column, 0]))),
);
const pickerLabel = computed(() =>
    activePickerUnassigned.value ? "- TANPA PICKER -" : activePicker.value,
);

function formatNumber(value) {
    return new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function formatPercent(value) {
    return `${Number(value ?? 0).toLocaleString("id-ID", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
}

function serviceLevel(row) {
    const orderedValue = Number(row.rph_pb ?? 0);

    return orderedValue > 0
        ? ((Number(row.rph_terpicking ?? 0) +
              Number(row.rph_real_sebagian ?? 0)) /
              orderedValue) *
              100
        : 0;
}

function submitFilters() {
    form.get(route("sl.picker"), {
        preserveScroll: true,
    });
}

function openDetails(row) {
    activePickerUnassigned.value =
        row.obi_picker == null || String(row.obi_picker).trim() === "";
    activePicker.value = activePickerUnassigned.value
        ? ""
        : String(row.obi_picker);
    detailOpen.value = true;
    loadDetails(1);
}

function closeDetails() {
    detailOpen.value = false;
    detailRows.value = [];
    detailError.value = "";
}

async function loadDetails(page = 1) {
    detailLoading.value = true;
    detailError.value = "";

    const url = new URL(route("sl.picker.details"), window.location.origin);
    url.search = new URLSearchParams({
        tglstart: form.tglstart,
        tglend: form.tglend,
        picker: activePicker.value,
        unassigned: activePickerUnassigned.value ? "1" : "0",
        page: String(page),
    }).toString();

    try {
        const response = await fetch(url, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        });
        const contentType = response.headers.get("content-type") ?? "";

        if (!contentType.includes("application/json")) {
            throw new Error(
                `Server mengembalikan respons yang tidak sesuai (HTTP ${response.status}).`,
            );
        }

        const result = await response.json();

        if (!response.ok) {
            const firstValidationError = Object.values(
                result.errors ?? {},
            ).flat()[0];

            throw new Error(
                firstValidationError ??
                    result.message ??
                    `Gagal memuat detail (HTTP ${response.status}).`,
            );
        }

        detailRows.value = result.data;
        detailPage.value = result.current_page;
        detailHasNext.value = Boolean(result.next_page_url);
        detailHasPrevious.value = Boolean(result.prev_page_url);
    } catch (error) {
        detailError.value =
            error instanceof Error
                ? error.message
                : "Gagal memuat detail picker.";
    } finally {
        detailLoading.value = false;
    }
}

function changeDetailPage(direction) {
    loadDetails(detailPage.value + direction);
}
</script>

<template>
    <Head title="SLPicker" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        SLPicker
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Rekap ketepatan picking berdasarkan picker dan tanggal
                        maksimal pengiriman.
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-5">
            <section
                class="rounded-xl border border-sky-200 bg-white p-4 shadow-sm dark:border-sky-900 dark:bg-slate-900"
            >
                <form
                    class="flex flex-wrap items-end gap-3"
                    @submit.prevent="submitFilters"
                >
                    <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tanggal mulai
                        <input
                            v-model="form.tglstart"
                            type="date"
                            required
                            class="rounded-lg border border-sky-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-sky-800 dark:bg-slate-950 dark:text-gray-100"
                        />
                    </label>
                    <label class="grid gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tanggal akhir
                        <input
                            v-model="form.tglend"
                            type="date"
                            required
                            class="rounded-lg border border-sky-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-sky-800 dark:bg-slate-950 dark:text-gray-100"
                        />
                    </label>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? "Memuat..." : "Tampilkan" }}
                    </button>
                    <p
                        v-if="form.errors.tglstart || form.errors.tglend"
                        class="w-full text-sm text-red-600 dark:text-red-400"
                        role="alert"
                    >
                        {{ form.errors.tglstart || form.errors.tglend }}
                    </p>
                </form>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-700"
                >
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">
                        Rekap per picker
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Klik nama picker untuk membuka rincian, maksimal 50 baris
                        per halaman.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1180px] w-full border-collapse text-sm">
                        <thead class="sticky top-0 bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <tr>
                                <th
                                    rowspan="2"
                                    scope="col"
                                    class="px-3 py-2 text-left"
                                >
                                    Picker
                                </th>
                                <th
                                    rowspan="2"
                                    scope="col"
                                    class="px-3 py-2 text-right"
                                >
                                    Jml PB
                                </th>
                                <th
                                    rowspan="2"
                                    scope="col"
                                    class="px-3 py-2 text-right"
                                >
                                    Jml item
                                </th>
                                <th
                                    rowspan="2"
                                    scope="col"
                                    class="px-3 py-2 text-right"
                                >
                                    RPH PB
                                </th>
                                <th
                                    colspan="3"
                                    scope="colgroup"
                                    class="bg-slate-200 px-3 py-2 text-center dark:bg-slate-700"
                                >
                                    SL Picking - Item
                                </th>
                                <th
                                    colspan="4"
                                    scope="colgroup"
                                    class="bg-amber-100 px-3 py-2 text-center dark:bg-amber-950"
                                >
                                    SL Picking - Rupiah
                                </th>
                                <th
                                    rowspan="2"
                                    scope="col"
                                    class="bg-sky-100 px-3 py-2 text-right dark:bg-sky-950"
                                >
                                    SL
                                </th>
                            </tr>
                            <tr>
                                <th class="px-3 py-2 text-right">100%</th>
                                <th class="px-3 py-2 text-right">Sebagian</th>
                                <th class="px-3 py-2 text-right">Nol</th>
                                <th class="px-3 py-2 text-right">100%</th>
                                <th class="px-3 py-2 text-right">Real sebagian</th>
                                <th class="px-3 py-2 text-right">Lost sebagian</th>
                                <th class="px-3 py-2 text-right">Nol</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="row in rows"
                                :key="row.obi_picker ?? 'unassigned'"
                                class="text-gray-700 hover:bg-sky-50 dark:text-gray-200 dark:hover:bg-slate-800"
                            >
                                <th scope="row" class="px-3 py-2 text-left">
                                    <button
                                        type="button"
                                        class="font-semibold text-sky-700 underline decoration-sky-300 underline-offset-2 hover:text-sky-900 dark:text-sky-300 dark:hover:text-sky-100"
                                        @click="openDetails(row)"
                                    >
                                        {{
                                            row.obi_picker ||
                                            "- TANPA PICKER -"
                                        }}
                                    </button>
                                </th>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.jml_pb) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.jml_item) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.rph_pb) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.item_terpicking) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.item_sebagian) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.item_nol) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.rph_terpicking) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.rph_real_sebagian) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.rph_lost_sebagian) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(row.rph_nol) }}</td>
                                <td class="bg-sky-50 px-3 py-2 text-right font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">
                                    {{ formatPercent(serviceLevel(row)) }}
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="12"
                                    class="px-4 py-10 text-center text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada data pada rentang tanggal ini.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot
                            v-if="rows.length"
                            class="border-t-2 border-gray-300 bg-gray-50 font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        >
                            <tr>
                                <th scope="row" class="px-3 py-2 text-left">
                                    Total
                                </th>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.jml_pb) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.jml_item) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.rph_pb) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.item_terpicking) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.item_sebagian) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.item_nol) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.rph_terpicking) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.rph_real_sebagian) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.rph_lost_sebagian) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatNumber(summaryTotals.rph_nol) }}</td>
                                <td class="bg-sky-100 px-3 py-2 text-right dark:bg-sky-950">
                                    {{ formatPercent(serviceLevel(summaryTotals)) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <Modal :show="detailOpen" max-width="screen" @close="closeDetails">
            <section class="max-h-[90vh] overflow-hidden rounded-xl bg-white dark:bg-gray-900">
                <header
                    class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Detail picker: {{ pickerLabel }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ filters.tglstart }} s/d {{ filters.tglend }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                        @click="closeDetails"
                    >
                        Tutup
                    </button>
                </header>

                <div
                    v-if="detailLoading"
                    class="p-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat detail...
                </div>
                <p
                    v-else-if="detailError"
                    class="m-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    role="alert"
                >
                    {{ detailError }}
                </p>
                <div v-else class="max-h-[65vh] overflow-auto">
                    <table class="min-w-[1900px] w-full border-collapse text-xs">
                        <thead class="sticky top-0 z-10 bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <tr>
                                <th
                                    v-for="column in [
                                        ['obi_nopb', 'PB'],
                                        ['obi_notrans', 'Trans'],
                                        ['obi_kdmember', 'Member'],
                                        ['obi_tglpb', 'Tgl PB'],
                                        ['obi_maxdeliverytime', 'Max kirim'],
                                        ['plu', 'PLU'],
                                        ['prd_deskripsipanjang', 'Deskripsi'],
                                        ['div', 'Div'],
                                        ['flag_produk', 'Flag'],
                                        ['status_pb', 'Status PB'],
                                        ['ket_picking', 'Ket picking'],
                                        ['obi_qtyorder', 'Qty order'],
                                        ['obi_qtyrealisasi', 'Qty real'],
                                        ['rph_order', 'RPH order'],
                                        ['rph_realisasi', 'RPH real'],
                                        ['rph_terpicking_100', 'RPH 100%'],
                                        ['rph_real_sebagian', 'Real sebagian'],
                                        ['rph_lost_sebagian', 'Lost sebagian'],
                                        ['rph_nol', 'RPH nol'],
                                    ]"
                                    :key="column[0]"
                                    scope="col"
                                    class="whitespace-nowrap border-b border-gray-200 px-3 py-2 text-left dark:border-gray-700"
                                >
                                    {{ column[1] }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="(row, index) in detailRows"
                                :key="`${row.obi_nopb}-${row.plu}-${index}`"
                                class="text-gray-700 dark:text-gray-200"
                            >
                                <td
                                    v-for="column in [
                                        'obi_nopb',
                                        'obi_notrans',
                                        'obi_kdmember',
                                        'obi_tglpb',
                                        'obi_maxdeliverytime',
                                        'plu',
                                        'prd_deskripsipanjang',
                                        'div',
                                        'flag_produk',
                                        'status_pb',
                                        'ket_picking',
                                        'obi_qtyorder',
                                        'obi_qtyrealisasi',
                                        'rph_order',
                                        'rph_realisasi',
                                        'rph_terpicking_100',
                                        'rph_real_sebagian',
                                        'rph_lost_sebagian',
                                        'rph_nol',
                                    ]"
                                    :key="column"
                                    class="whitespace-nowrap px-3 py-2"
                                >
                                    {{ row[column] ?? "-" }}
                                </td>
                            </tr>
                            <tr v-if="detailRows.length === 0">
                                <td
                                    colspan="19"
                                    class="px-4 py-8 text-center text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada rincian untuk picker ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <footer
                    v-if="!detailLoading && !detailError"
                    class="flex items-center justify-between gap-3 border-t border-gray-200 px-5 py-3 dark:border-gray-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Halaman {{ detailPage }} · {{ detailRows.length }} baris
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            :disabled="!detailHasPrevious"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600"
                            @click="changeDetailPage(-1)"
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            :disabled="!detailHasNext"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600"
                            @click="changeDetailPage(1)"
                        >
                            Berikutnya
                        </button>
                    </div>
                </footer>
            </section>
        </Modal>
    </AuthenticatedLayout>
</template>
