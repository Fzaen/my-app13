<script setup>
import { computed, nextTick, ref, watch } from "vue";
import Modal from "@/Components/Modal.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { copyTextToClipboard } from "@/clipboard";

const props = defineProps({
    rows: {
        type: Array,
        required: true,
    },
    search: {
        type: String,
        default: "",
    },
});

const searchInput = ref(props.search);
const copyMessage = ref("");
const copyFallbackOpen = ref(false);
const copyFallbackText = ref("");
const copyFallbackInput = ref(null);

watch(
    () => props.search,
    (value) => {
        searchInput.value = value;
    },
);

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

const cellFilters = {
    pb: { recid: "all" },
    sudahsend: { recid: "notnull" },
    batal: { recid: "B" },
    belum_sendhh: { recid: "null" },
    sp_ambil: { recid: "1", ekspedisi: "Ambil" },
    sp_indopaket: { recid: "1", ekspedisi: "Indopaket" },
    siap_packing: { recid: "2" },
    siap_dsp: { recid: "3" },
    siap_struk_spi: { recid: "5", ekspedisi: "Ambil" },
    siap_struk_ipp: { recid: "5", ekspedisi: "Indopaket" },
    selesai_struk: { recid: "6" },
    indo_paket: { ekspedisi: "Indopaket" },
    ambil_ditoko: { ekspedisi: "Ambil" },
};

const filteredRows = computed(() => {
    const search = props.search.trim().toLocaleLowerCase("id-ID");

    if (!search) {
        return props.rows;
    }

    return props.rows.filter((row) =>
        columns.some((column) =>
            String(row[column.key] ?? "")
                .toLocaleLowerCase("id-ID")
                .includes(search),
        ),
    );
});

const exportHref = computed(() =>
    route("pb.all.export", {
        search: props.search || undefined,
    }),
);

const searchRows = () => {
    router.get(
        route("pb.all"),
        { search: searchInput.value.trim() },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const copyRows = async () => {
    const values = [
        columns.map((column) => column.label),
        ...filteredRows.value.map((row) =>
            columns.map((column) => row[column.key] ?? ""),
        ),
    ];

    const text = values.map((value) => value.join("\t")).join("\n");

    try {
        await copyTextToClipboard(text);
        copyFallbackOpen.value = false;
        copyMessage.value = `${filteredRows.value.length} baris berhasil disalin.`;
    } catch (error) {
        copyFallbackText.value = text;
        copyFallbackOpen.value = true;
        copyMessage.value =
            error instanceof Error
                ? `Browser membatasi salin otomatis (${error.message}). Tekan Ctrl+C pada isi yang sudah dipilih.`
                : "Browser membatasi salin otomatis. Tekan Ctrl+C pada isi yang sudah dipilih.";

        await nextTick();
        selectCopyFallback();
    }
};

const selectCopyFallback = () => {
    copyFallbackInput.value?.focus();
    copyFallbackInput.value?.select();
};

const formatNumber = (value) =>
    new Intl.NumberFormat("id-ID").format(value ?? 0);

const formatDate = (value) =>
    new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));

const dateInputValue = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
};

const reportHref = (row, filters = {}) => {
    const date = String(row.tgl).slice(0, 10);
    const query = new URLSearchParams({
        tglstart: date,
        tglend: date,
        ...filters,
    });

    return `${route("pb.reports.show", { report: "pb-proses" })}?${query.toString()}`;
};

const linkForCell = (row, column) =>
    reportHref(
        row,
        column.date ? { recid: "all" } : cellFilters[column.key],
    );

const cellClasses = (row, column) => {
    const classes = ["whitespace-nowrap px-4 py-3 text-center font-medium"];
    const date = String(row.tgl).slice(0, 10);
    const today = dateInputValue(new Date());
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const yesterdayValue = dateInputValue(yesterday);

    if (column.key === "belum_sendhh" && Number(row.belum_sendhh) > 0) {
        classes.push("bg-red-950 text-white");
    } else if (
        ["sp_ambil", "sp_indopaket"].includes(column.key) &&
        Number(row.siap_picking) > 0
    ) {
        classes.push("bg-emerald-500 text-gray-950");
    } else if (
        column.key === "siap_packing" &&
        Number(row.siap_packing) > 0
    ) {
        classes.push("bg-emerald-500 text-gray-950");
    } else if (column.key === "siap_dsp" && Number(row.siap_dsp) > 0) {
        classes.push(
            date === today
                ? "bg-emerald-900 text-white"
                : "bg-red-950 text-white",
        );
    } else if (
        ["siap_struk_spi", "siap_struk_ipp"].includes(column.key) &&
        Number(row.siap_struk) > 0
    ) {
        classes.push(
            date === today
                ? "bg-emerald-900 text-white"
                : date === yesterdayValue
                  ? "bg-yellow-400 text-gray-950"
                  : "bg-red-950 text-white",
        );
    } else {
        classes.push("text-gray-700 dark:text-gray-200");
    }

    return classes;
};
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
            <section
                class="space-y-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
                >
                    <form
                        class="flex flex-1 flex-col gap-2 sm:flex-row"
                        @submit.prevent="searchRows"
                    >
                        <label class="sr-only" for="pb-summary-search">
                            Cari PB
                        </label>
                        <input
                            id="pb-summary-search"
                            v-model="searchInput"
                            type="search"
                            placeholder="Cari tanggal atau nilai..."
                            class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                        <button
                            type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"
                        >
                            Cari
                        </button>
                    </form>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="copyRows"
                        >
                            Salin seluruh tabel
                        </button>
                        <a
                            :href="exportHref"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                        >
                            Export Excel
                        </a>
                    </div>
                </div>

                <p
                    v-if="copyMessage"
                    role="status"
                    class="text-sm text-gray-600 dark:text-gray-300"
                >
                    {{ copyMessage }}
                </p>

                <div
                    class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700"
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
                                v-for="row in filteredRows"
                                :key="row.tgl"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-700 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                            >
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    :class="cellClasses(row, column)"
                                >
                                    <Link
                                        :href="linkForCell(row, column)"
                                        class="rounded-sm hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        {{
                                            column.date
                                                ? formatDate(row[column.key])
                                                : formatNumber(row[column.key])
                                        }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="filteredRows.length === 0">
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
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan {{ filteredRows.length }} baris dari periode 7 hari terakhir.
                </p>
            </section>
        </div>

        <Modal
            :show="copyFallbackOpen"
            max-width="4xl"
            @close="copyFallbackOpen = false"
        >
            <section class="space-y-4 p-5 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Salin seluruh tabel
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Isi tabel sudah dipilih. Tekan Ctrl+C untuk menyalin, lalu tutup jendela ini.
                </p>
                <textarea
                    ref="copyFallbackInput"
                    v-model="copyFallbackText"
                    readonly
                    rows="12"
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 p-3 font-mono text-xs text-gray-900 selection:bg-indigo-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    @click="selectCopyFallback"
                    @focus="selectCopyFallback"
                />
            </section>
        </Modal>
    </AuthenticatedLayout>
</template>
