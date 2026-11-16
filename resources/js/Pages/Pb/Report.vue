<script setup>
import { computed, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";

const props = defineProps({
    report: {
        type: String,
        required: true,
    },
    reportTitle: {
        type: String,
        required: true,
    },
    maxPeriodMonths: {
        type: Number,
        default: null,
    },
    maxExportPeriodMonths: {
        type: Number,
        default: null,
    },
    filters: {
        type: Object,
        required: true,
    },
    rows: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const reportColumns = {
    "pb-proses": [
        ["pb", "PB"],
        ["tglpb", "Tanggal PB"],
        ["notrans", "No. Transaksi"],
        ["member", "Member"],
        ["nama", "Nama"],
        ["ekspedisi", "Ekspedisi"],
        ["status", "Status"],
        ["total_order", "Total Order"],
        ["item_order", "Item Order"],
        ["total_realisasi", "Total Realisasi"],
        ["item_realisasi", "Item Realisasi"],
    ],
    "pb-peritem": [
        ["obi_recid", "Recid"],
        ["ket", "Status"],
        ["ket_item", "Status Item"],
        ["pb", "PB"],
        ["obi_kdmember", "Member"],
        ["cus_namamember", "Nama Member"],
        ["tglstruk", "Tanggal Struk"],
        ["obi_tgltrans", "Tanggal Transaksi"],
        ["obi_notrans", "No. Transaksi"],
        ["trjd_prdcd", "PLU"],
        ["prd_deskripsipanjang", "Deskripsi"],
        ["hrgsatuan", "Harga Satuan"],
        ["obi_qtyorder", "Qty Order"],
        ["obi_qtyrealisasi", "Qty Realisasi"],
        ["pobi_qty", "Qty Packing"],
        ["pobi_create_by", "Petugas Scan"],
        ["obi_urut_pick", "Urut Picking"],
        ["jam_pick", "Jam Picking"],
        ["jam_scan", "Jam Scan"],
        ["obi_picker", "Picker"],
        ["obi_hargaweb", "Harga Web"],
        ["obi_qty_hitungulang", "Qty Hitung Ulang"],
        ["obi_qtyba", "Qty BA"],
        ["pobi_nocontainer", "No. Container"],
        ["selisih", "Selisih"],
    ],
    "pb-proses-item": [
        ["obi_recid", "Recid"],
        ["ket", "Status"],
        ["ket_item", "Status Item"],
        ["pb", "PB"],
        ["obi_kdmember", "Member"],
        ["cus_namamember", "Nama Member"],
        ["tglstruk", "Tanggal Struk"],
        ["obi_tgltrans", "Tanggal Transaksi"],
        ["obi_notrans", "No. Transaksi"],
        ["trjd_prdcd", "PLU"],
        ["prd_deskripsipanjang", "Deskripsi"],
        ["hrgsatuan", "Harga Satuan"],
        ["obi_qtyorder", "Qty Order"],
        ["obi_qtyrealisasi", "Qty Realisasi"],
        ["pobi_qty", "Qty Packing"],
        ["pobi_create_by", "Petugas Scan"],
        ["obi_urut_pick", "Urut Picking"],
        ["jam_pick", "Jam Picking"],
        ["jam_scan", "Jam Scan"],
        ["obi_picker", "Picker"],
        ["obi_hargaweb", "Harga Web"],
        ["obi_qty_hitungulang", "Qty Hitung Ulang"],
        ["obi_qtyba", "Qty BA"],
        ["pobi_nocontainer", "No. Container"],
        ["selisih", "Selisih"],
    ],
    "pb-kirim-list": [
        ["pb", "PB"],
        ["tglpb", "Tanggal PB"],
        ["member", "Member"],
        ["nama", "Nama"],
        ["notrans", "No. Transaksi"],
        ["ekspedisi", "Ekspedisi"],
        ["status", "Status"],
    ],
    "pb-belum-pin": [
        ["pb", "PB"],
        ["tglpb", "Tanggal PB"],
        ["member", "Member"],
        ["nama", "Nama"],
        ["notrans", "No. Transaksi"],
        ["ekspedisi", "Ekspedisi"],
        ["status", "Status"],
    ],
    "pb-ambil-spi": [
        ["pb", "PB"],
        ["tglpb", "Tanggal PB"],
        ["notrans", "No. Transaksi"],
        ["member", "Member"],
        ["nama", "Nama"],
    ],
    "pb-request-kirim": [
        ["pb", "PB"],
        ["tglpb", "Tanggal PB"],
        ["notrans", "No. Transaksi"],
        ["member", "Member"],
        ["nama", "Nama"],
        ["req_tgl_kirim", "Tanggal Kirim"],
        ["req_request", "Request"],
        ["status", "Status"],
    ],
};

const pbDateColumns = [
    ["pb", "PB"],
    ["tgl", "TANGGAL"],
    ["obi_notrans", "NO"],
    ["obi_kdmember", "KD MEMBER"],
    ["cus_namamember", "NAMA MEMBER"],
    ["cus_alamatmember8", "KELURAHAN"],
    ["tglstruk", "TGLSTRUK"],
    ["obi_tipebayar", "BAYAR"],
    ["pin", "PIN IPP"],
    ["pin_cod", "PIN COD"],
    ["ket_pin", "KET PIN"],
    ["ttl_order", "TTL ORDER"],
    ["obi_itemorder", "ITEM ORDER"],
    ["ttl_real", "REAL ORDER"],
    ["obi_realitem", "REAL ITEM"],
    ["obi_jrkekspedisi", "JRK EKSPEDISI"],
    ["free_ongkir", "FREE ONGKIR"],
    ["kena_ongkir", "KENA ONGKIR"],
    ["jenis", "JENIS"],
    ["jam_dsp", "JAM DSP"],
    ["loyalty", "LYTI"],
    ["req", "REQ KIRIM"],
    ["ket", "KET"],
];

const isItemReport = computed(() => props.report === "pb-peritem");
const isProcessReport = computed(() => props.report === "pb-proses");
const isWideTable = ref(false);
const columns = computed(() =>
    isItemReport.value ? reportColumns["pb-peritem"] : pbDateColumns,
);
const isCompactTable = computed(
    () => !isItemReport.value && !isWideTable.value,
);
const startDate = ref(props.filters.tglstart);
const endDate = ref(props.filters.tglend);
const searchInput = ref(props.filters.search);
const perPage = ref(String(props.filters.per_page));
const selectedKet = ref(props.filters.recid ?? "all");
const copyMessage = ref("");
const pageJump = ref(String(props.rows.current_page ?? 1));
const sortKey = ref("");
const sortDirection = ref("asc");
const validationErrors = computed(() =>
    Object.values(page.props.errors ?? {}).flat(),
);
const successMessage = computed(() => page.props.flash?.success ?? "");

const ketOptions = [
    { value: "all", label: "ALL (TAMPILKAN SEMUA)" },
    { value: "proses", label: "PB PROSES" },
    { value: "null", label: "BLM_SEND" },
    { value: "1", label: "PICKING" },
    { value: "2", label: "PACKING" },
    { value: "3", label: "SIAP_DSP" },
    { value: "5", label: "DSP" },
    { value: "6", label: "STRUK" },
    { value: "B", label: "BATAL" },
];

const extraKetLabels = {
    notnull: "SDH_SEND",
    batas: "PROSES AWAL (1-3 + BLM_SEND)",
};

watch(
    () => props.filters,
    (filters) => {
        startDate.value = filters.tglstart;
        endDate.value = filters.tglend;
        searchInput.value = filters.search;
        perPage.value = String(filters.per_page);
        selectedKet.value = filters.recid ?? "all";
    },
);

watch(
    () => props.rows.current_page,
    (currentPage) => {
        pageJump.value = String(currentPage ?? 1);
    },
);

const visibleRows = computed(() => {
    if (!isProcessReport.value || !sortKey.value) {
        return props.rows.data;
    }

    const collator = new Intl.Collator("id-ID", {
        numeric: true,
        sensitivity: "base",
    });

    return props.rows.data
        .map((row, index) => ({ row, index }))
        .sort((left, right) => {
            const leftValue = displayValue(sortKey.value, left.row);
            const rightValue = displayValue(sortKey.value, right.row);
            const leftText = String(leftValue ?? "");
            const rightText = String(rightValue ?? "");
            const direction = sortDirection.value === "asc" ? 1 : -1;
            let comparison = 0;

            if (leftValue === null || leftValue === undefined || leftValue === "") {
                comparison = rightValue === null || rightValue === undefined || rightValue === "" ? 0 : -1;
            } else if (rightValue === null || rightValue === undefined || rightValue === "") {
                comparison = 1;
            } else if (
                /^-?\d+(?:\.\d+)?$/.test(leftText) &&
                /^-?\d+(?:\.\d+)?$/.test(rightText)
            ) {
                comparison = Number(leftValue) - Number(rightValue);
            } else {
                comparison = collator.compare(leftText, rightText);
            }

            return comparison === 0
                ? left.index - right.index
                : comparison * direction;
        })
        .map(({ row }) => row);
});

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
        return;
    }

    sortKey.value = key;
    sortDirection.value = "asc";
};

const sortIndicator = (key) => {
    if (sortKey.value !== key) {
        return "↕";
    }

    return sortDirection.value === "asc" ? "↑" : "↓";
};

const exportHref = computed(() => {
    const query = new URLSearchParams();

    Object.entries(props.filters).forEach(([key, value]) => {
        if (
            !["per_page", "page"].includes(key) &&
            value !== null &&
            value !== undefined &&
            value !== ""
        ) {
            query.set(key, String(value));
        }
    });

    return `${route("pb.reports.export", { report: props.report })}?${query}`;
});

const submitFilters = () => {
    const query = {
        ...props.filters,
        tglstart: startDate.value,
        tglend: endDate.value,
        search: searchInput.value.trim(),
        per_page: perPage.value,
        recid: selectedKet.value,
        page: 1,
    };

    router.get(
        route("pb.reports.show", { report: props.report }),
        query,
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

const resetFilters = () => {
    startDate.value = props.filters.tglstart;
    endDate.value = props.filters.tglend;
    searchInput.value = "";
    perPage.value = "25";
    selectedKet.value = isProcessReport.value ? "proses" : "all";
    pageJump.value = "1";
    submitFilters();
};

const jumpToPage = () => {
    const targetPage = Number(pageJump.value);

    if (!Number.isInteger(targetPage) || targetPage < 1 || targetPage > 10000) {
        pageJump.value = String(props.rows.current_page ?? 1);
        return;
    }

    router.get(
        route("pb.reports.show", { report: props.report }),
        { ...props.filters, page: targetPage },
        { preserveScroll: true, preserveState: true },
    );
};

const visitPage = (url) => {
    if (url) {
        router.visit(url, { preserveScroll: true, preserveState: true });
    }
};

const actionReturnFilters = () => ({
    return_report: props.report,
    return_tglstart: props.filters.tglstart,
    return_tglend: props.filters.tglend,
    return_recid: props.filters.recid ?? "all",
    return_ekspedisi: props.filters.ekspedisi ?? "",
    return_pin: props.filters.pin ?? "",
    return_kdmm: props.filters.kdmm ?? "",
    return_nopb: props.filters.nopb ?? "",
    return_request: [true, 1, "1"].includes(props.filters.request),
    return_search: props.filters.search ?? "",
    return_per_page: props.filters.per_page ?? "",
    return_page: props.filters.page ?? "",
});

const orderActionParams = (row) => ({
    tglpb: row.obi_tglpb,
    nopb: row.obi_nopb,
    notrans: row.obi_notrans,
    kdmm: row.obi_kdmember,
    ...actionReturnFilters(),
});

const giftActionHref = (row) =>
    route("pb.actions.gift.create", orderActionParams(row));

const loyaltyActionHref = (row) =>
    route("pb.actions.loyalty.index", {
        kdmm: row.obi_kdmember,
        program: row.gttipe,
        ...actionReturnFilters(),
    });

const requestActionHref = (row) =>
    route("pb.actions.request.form", {
        mode: row.req ? "edit" : "create",
        ...orderActionParams(row),
    });

const copyRows = async () => {
    const values = [
        columns.value.map(([, label]) => label),
        ...visibleRows.value.map((row) =>
            columns.value.map(([key]) =>
                formatCell(displayValue(key, row), key, row),
            ),
        ),
    ];

    try {
        await navigator.clipboard.writeText(
            values.map((value) => value.join("\t")).join("\n"),
        );
        copyMessage.value = `${props.rows.data.length} baris halaman ini berhasil disalin.`;
    } catch {
        copyMessage.value =
            "Tidak dapat menyalin. Periksa izin clipboard browser.";
    }
};

const displayValue = (key, row) => {
    if (key === "tglstruk") {
        return row.tglstruk ?? row.kdpromo;
    }

    if (key === "pin_cod") {
        return Number(row.obi_service_fee) > 0
            ? row.obi_service_fee
            : row.obi_cod_pincode;
    }

    if (key === "free_ongkir") {
        return row.ads_hadiah ?? row.awi_cost ?? 0;
    }

    if (key === "kena_ongkir") {
        return row.ads_hadiah !== null && row.ads_hadiah !== undefined
            ? row.hdh_qty !== null && row.hdh_qty !== undefined
                ? "✓"
                : "✗"
            : row.obi_ekspedisi;
    }

    if (key === "jenis") {
        const type = String(row.obi_kdekspedisi ?? "").substring(10, 15);
        const expeditionType = ["Mobil", "Motor"].includes(type)
            ? type
            : "SPI";

        return row.gttipe ?? expeditionType;
    }

    if (key === "obi_jrkekspedisi") {
        return row[key] === null || row[key] === undefined
            ? null
            : `${row[key]} km`;
    }

    if (key === "ket") {
        return `${row.ket ?? ""}${row.ketdsp === "ULANG" ? "*ULANG" : ""}`;
    }

    return row[key];
};

const numberColumns = new Set([
    "ttl_order",
    "obi_itemorder",
    "ttl_real",
    "obi_realitem",
    "loyalty",
]);

const formatCell = (value, key, row) => {
    if (value === null || value === undefined || value === "") {
        return "-";
    }

    const numericValue =
        numberColumns.has(key) ||
        (key === "pin_cod" && Number(row.obi_service_fee) > 0) ||
        (key === "free_ongkir" && row.ads_hadiah == null) ||
        (key === "kena_ongkir" && row.ads_hadiah == null);

    if (
        numericValue &&
        value !== "✓" &&
        value !== "✗" &&
        Number.isFinite(Number(value))
    ) {
        return new Intl.NumberFormat("id-ID", {
            maximumFractionDigits: 2,
        }).format(Number(value));
    }

    if (key === "tgl") {
        return value;
    }

    if (key === "tglstruk") {
        const date = String(value).slice(0, 10);

        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            return value;
        }

        return new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
        }).format(new Date(`${date}T00:00:00`));
    }

    return value;
};

const cellClasses = (key, row) => {
    const classes = !isCompactTable.value
        ? [
              "whitespace-nowrap px-3 py-2 text-center align-middle text-sm text-gray-700 dark:text-gray-200",
          ]
        : [
              "break-words px-1 py-1 text-center align-middle text-[10px] leading-tight text-gray-700 dark:text-gray-200",
          ];

    if (key === "pb" && Number(row.obi_service_fee) > 0) {
        classes.push("bg-white font-bold text-sky-700");
    }

    if (key === "pin_cod" && Number(row.obi_service_fee) > 0) {
        classes.push("bg-white font-bold text-sky-700");
    }

    if (key === "ket_pin") {
        const pinStatus = String(row.ket_pin ?? "").toUpperCase();

        if (pinStatus === "SUDAH INPUT PIN") {
            classes.push("font-bold text-sky-500 dark:text-sky-300");
        } else if (
            ["BELUM INPUT PIN", "SUDAH PIN B 1X", "SUDAH PIN B 2X"].includes(
                pinStatus,
            )
        ) {
            classes.push("font-bold text-red-600");
        } else if (row.ads_hadiah != null) {
            classes.push("bg-emerald-700 font-bold text-yellow-200");
        } else if (pinStatus === "MASIH TMS") {
            classes.push("bg-gray-600 font-bold text-cyan-100");
        } else {
            classes.push("font-semibold text-cyan-700 dark:text-cyan-300");
        }

        if (pinStatus === "BELUM PERNAH AMBIL DI SPI") {
            classes.push("rounded bg-red-800 text-white");
        }
    }

    if (key === "free_ongkir" && row.ads_hadiah != null) {
        classes.push("bg-emerald-700 font-bold text-yellow-200");
    }

    if (key === "kena_ongkir" && row.ads_hadiah != null) {
        classes.push(
            row.hdh_qty != null
                ? "bg-blue-700 font-bold text-white"
                : "bg-red-700 font-bold text-white",
        );
    }

    if (key === "ket" && row.ketdsp === "ULANG") {
        classes.push("font-bold text-red-600");
    }

    return classes;
};

const columnWidths = {
    pb: "55px",
    tgl: "100px",
    obi_notrans: "35px",
    obi_kdmember: "55px",
    cus_namamember: "90px",
    cus_alamatmember8: "75px",
    tglstruk: "55px",
    obi_tipebayar: "42px",
    pin: "50px",
    pin_cod: "50px",
    ket_pin: "90px",
    ttl_order: "65px",
    obi_itemorder: "35px",
    ttl_real: "65px",
    obi_realitem: "35px",
    obi_jrkekspedisi: "50px",
    free_ongkir: "50px",
    kena_ongkir: "50px",
    jenis: "55px",
    jam_dsp: "85px",
    loyalty: "35px",
    req: "75px",
    ket: "60px",
};

const columnWidth = (key) =>
    isCompactTable.value ? columnWidths[key] : undefined;

const dateTimeParts = (value) => {
    const [date = "", time = ""] = String(value ?? "").split(/\s+/, 2);

    return { date, time };
};
</script>

<template>
    <Head :title="reportTitle" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">
                    {{ reportTitle }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Hasil ditampilkan bertahap agar tetap ringan.
                </p>
            </div>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <section
                class="space-y-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6"
            >
                <form
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(11rem,1fr)_minmax(11rem,1fr)_minmax(13rem,1.4fr)_minmax(7rem,.6fr)_minmax(11rem,.9fr)_auto]"
                    @submit.prevent="submitFilters"
                >
                    <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Tanggal awal</span>
                        <input
                            v-model="startDate"
                            type="date"
                            required
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </label>

                    <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Tanggal akhir</span>
                        <input
                            v-model="endDate"
                            type="date"
                            required
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </label>

                    <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Pencarian</span>
                        <input
                            v-model="searchInput"
                            type="search"
                            maxlength="100"
                            placeholder="Cari data pada kolom..."
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </label>

                    <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Baris per halaman</span>
                        <select
                            v-model="perPage"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </label>

                    <label
                        v-if="!isItemReport"
                        class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        <span>KET</span>
                        <select
                            v-model="selectedKet"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option
                                v-for="option in ketOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                            <option
                                v-if="extraKetLabels[selectedKet]"
                                :value="selectedKet"
                            >
                                {{ extraKetLabels[selectedKet] }}
                            </option>
                        </select>
                    </label>

                    <div class="flex items-end gap-2">
                        <button
                            type="submit"
                            class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:scale-[1.02] hover:bg-indigo-700 active:scale-[.98]"
                        >
                            Cari
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:scale-[1.02] hover:bg-gray-50 active:scale-[.98] dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                <div
                    v-if="validationErrors.length"
                    role="alert"
                    class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
                >
                    {{ validationErrors.join(" ") }}
                </div>
                <div
                    v-if="successMessage"
                    role="status"
                    class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                >
                    {{ successMessage }}
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Pencarian mencakup kolom laporan. Export Excel mengunduh semua hasil sesuai filter, sedangkan tombol salin hanya menyalin halaman aktif.
                <span v-if="maxPeriodMonths">
                    Rentang tampilan laporan maksimal {{ maxPeriodMonths }} bulan.
                </span>
                <span
                    v-if="
                        maxExportPeriodMonths &&
                        maxExportPeriodMonths !== maxPeriodMonths
                    "
                >
                    Ekspor Excel PB Proses maksimal {{ maxExportPeriodMonths }} bulan.
                </span>
                <span v-if="isProcessReport">
                    Klik header kolom untuk mengurutkan halaman aktif tanpa query ulang.
                </span>
                </p>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p
                        v-if="copyMessage"
                        role="status"
                        class="text-sm text-gray-600 dark:text-gray-300"
                    >
                        {{ copyMessage }}
                    </p>
                    <p
                        v-else
                        class="text-sm text-gray-600 dark:text-gray-300"
                    >
                        {{ rows.data.length }} baris pada halaman ini
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="!isItemReport"
                            type="button"
                            :aria-pressed="isWideTable"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:scale-[1.02] hover:bg-gray-50 active:scale-[.98] dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="isWideTable = !isWideTable"
                        >
                            {{ isWideTable ? "Tampilan ringkas" : "Perbesar tabel" }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:scale-[1.02] hover:bg-gray-50 active:scale-[.98] dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="copyRows"
                        >
                            Salin halaman
                        </button>
                        <a
                            :href="exportHref"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:scale-[1.02] hover:bg-emerald-700 active:scale-[.98]"
                        >
                            Export Excel
                        </a>
                    </div>
                </div>

                <div
                    :class="
                        isItemReport
                            ? 'overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700'
                            : 'max-h-[calc(100vh-22rem)] min-h-64 overflow-auto rounded-xl border border-gray-200 dark:border-gray-700'
                    "
                >
                    <table
                        :class="
                            isItemReport
                                ? 'min-w-max text-sm'
                                : isWideTable
                                ? 'min-w-[1800px] table-auto text-sm'
                                : 'w-full table-fixed text-[10px] leading-tight'
                        "
                    >
                        <thead
                            class="bg-cyan-100 text-gray-800 dark:bg-slate-800 dark:text-gray-100"
                        >
                            <tr>
                                <th
                                    v-for="[key, label] in columns"
                                    :key="key"
                                    :aria-sort="
                                        isProcessReport && sortKey === key
                                            ? sortDirection === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : 'none'
                                    "
                                    scope="col"
                                    :style="{ width: columnWidth(key) }"
                                    :class="
                                        !isCompactTable
                                            ? 'sticky top-0 z-10 whitespace-nowrap bg-cyan-100 px-3 py-2 text-left text-xs font-semibold uppercase dark:bg-slate-800'
                                            : 'sticky top-0 z-10 break-words bg-cyan-100 px-1 py-2 text-center text-[9px] font-semibold uppercase dark:bg-slate-800'
                                    "
                                >
                                <button
                                    v-if="isProcessReport"
                                    type="button"
                                    class="inline-flex items-center gap-1 transition hover:text-indigo-600 dark:hover:text-cyan-300"
                                    @click="sortBy(key)"
                                >
                                    {{ label }}
                                    <span aria-hidden="true">
                                        {{ sortIndicator(key) }}
                                    </span>
                                </button>
                                <span v-else>{{ label }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, rowIndex) in visibleRows"
                                :key="`${row.pb ?? ''}-${row.notrans ?? row.obi_notrans ?? ''}-${rowIndex}`"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50 hover:bg-indigo-50 dark:border-gray-700 dark:odd:bg-gray-900 dark:even:bg-gray-800 dark:hover:bg-gray-700"
                            >
                                <td
                                    v-for="[key] in columns"
                                    :key="key"
                                    :style="{ width: columnWidth(key) }"
                                    :class="cellClasses(key, row)"
                                >
                                    <template v-if="key === 'tgl'">
                                        <span class="block">{{
                                            dateTimeParts(row[key]).date
                                        }}</span>
                                        <span
                                            v-if="dateTimeParts(row[key]).time"
                                            class="block text-[9px] opacity-80"
                                        >
                                            {{ dateTimeParts(row[key]).time }}
                                        </span>
                                    </template>
                                    <template
                                        v-else-if="
                                            key === 'kena_ongkir' &&
                                            isProcessReport &&
                                            row.ads_hadiah != null &&
                                            row.hdh_qty == null
                                        "
                                    >
                                        <Link
                                            :href="giftActionHref(row)"
                                            class="font-bold text-white underline decoration-white/60 underline-offset-2 transition hover:text-rose-100"
                                        >
                                            ✗ Input hadiah
                                        </Link>
                                    </template>
                                    <template
                                        v-else-if="
                                            key === 'kena_ongkir' &&
                                            isProcessReport &&
                                            row.ads_hadiah != null
                                        "
                                    >
                                        <span class="font-bold text-white">
                                            {{ displayValue(key, row) }}
                                        </span>
                                    </template>
                                    <template
                                        v-else-if="
                                            isProcessReport &&
                                            key === 'jenis' &&
                                            row.gttipe
                                        "
                                    >
                                        <Link
                                            :href="loyaltyActionHref(row)"
                                            class="rounded bg-amber-100 px-1 font-bold text-amber-900 transition hover:bg-amber-200 hover:underline dark:bg-amber-900 dark:text-amber-100 dark:hover:bg-amber-800"
                                        >
                                            {{ row.gttipe }}
                                        </Link>
                                    </template>
                                    <template
                                        v-else-if="
                                            key === 'loyalty' &&
                                            isProcessReport &&
                                            Number(row.loyalty) > 0
                                        "
                                    >
                                        <Link
                                            :href="loyaltyActionHref(row)"
                                            class="font-bold text-indigo-700 transition hover:text-indigo-900 hover:underline dark:text-cyan-300 dark:hover:text-cyan-100"
                                        >
                                            {{ formatCell(displayValue(key, row), key, row) }}
                                        </Link>
                                    </template>
                                    <template
                                        v-else-if="isProcessReport && key === 'req'"
                                    >
                                        <Link
                                            :href="requestActionHref(row)"
                                            class="font-semibold text-indigo-700 transition hover:text-indigo-900 hover:underline dark:text-cyan-300 dark:hover:text-cyan-100"
                                        >
                                            {{ row.req || "Tambah" }}
                                        </Link>
                                    </template>
                                    <template v-else>
                                        {{
                                            formatCell(
                                                displayValue(key, row),
                                                key,
                                                row,
                                            )
                                        }}
                                    </template>
                                </td>
                            </tr>
                            <tr v-if="rows.data.length === 0">
                                <td
                                    :colspan="columns.length"
                                    class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada data yang cocok dengan filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-3"
                    aria-label="Navigasi halaman tabel"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Halaman {{ rows.current_page }}
                        </p>
                        <form
                            v-if="isProcessReport"
                            class="flex items-center gap-2"
                            @submit.prevent="jumpToPage"
                        >
                            <label
                                :for="`page-jump-${report}`"
                                class="text-sm text-gray-600 dark:text-gray-300"
                            >
                                Lompat ke
                            </label>
                            <input
                                :id="`page-jump-${report}`"
                                v-model="pageJump"
                                type="number"
                                min="1"
                                max="10000"
                                inputmode="numeric"
                                class="w-20 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                            <button
                                type="submit"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                Buka
                            </button>
                        </form>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            :disabled="!rows.prev_page_url"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition enabled:hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:enabled:hover:bg-gray-800"
                            @click="visitPage(rows.prev_page_url)"
                        >
                            Sebelumnya
                        </button>
                        <button
                            type="button"
                            :disabled="!rows.next_page_url"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition enabled:hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:text-gray-200 dark:enabled:hover:bg-gray-800"
                            @click="visitPage(rows.next_page_url)"
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
