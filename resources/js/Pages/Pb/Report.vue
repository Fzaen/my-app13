<script setup>
import { computed, nextTick, ref, watch } from "vue";
import Modal from "@/Components/Modal.vue";
import PbGiftReceiptPreview from "@/Components/PbGiftReceiptPreview.vue";
import PbLocationPreview from "@/Components/PbLocationPreview.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { copyTextToClipboard } from "@/clipboard";

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
    "pb-proses-item": [
        ["pb", "PB"],
        ["obi_tgltrans", "TGL\nPB"],
        ["obi_notrans", "NO\nTRANS"],
        ["trjd_prdcd", "PLU"],
        ["prd_deskripsipanjang", "DESK"],
        ["obi_qtyorder", "QTY\nORDER"],
        ["obi_qtyrealisasi", "QTY\nREAL"],
        ["pobi_qty", "QTY\nPACKING"],
        ["selisih", "SELISIH"],
        ["obi_picker", "Picker"],
        ["pobi_create_by", "Packer"],
        ["hrgsatuan", "Harga"],
        ["pobi_nocontainer", "NO\nKOLI"],
        ["jam_pick", "JAM\nPICK"],
        ["jam_scan", "JAM\nSCAN"],
        ["ket", "KET"],
        ["ket_item", "ITEM"],
        ["obi_qty_hitungulang", "QTY HITUNG\nULANG"],
        ["obi_qtyba", "QTY BA"],
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

const itemDetailColumns = [
    ["__number", "NO"],
    ["pb", "PB"],
    ["obi_tgltrans", "TGL PB"],
    ["trjd_prdcd", "PLU"],
    ["prd_deskripsipanjang", "DESK"],
    ["obi_qtyorder", "ORDER"],
    ["obi_qtyrealisasi", "REAL"],
    ["pobi_qty", "PACK"],
    ["selisih", "SEL"],
    ["obi_picker", "PICKER"],
    ["pobi_create_by", "PACKER"],
    ["hrgsatuan", "HARGA"],
    ["pobi_nocontainer", "KOLI"],
    ["jam_pick", "PICK"],
    ["jam_scan", "SCAN"],
    ["ket", "KET"],
];
const itemDetailColumnWidths = {
    __number: "3%",
    pb: "6%",
    obi_tgltrans: "7%",
    trjd_prdcd: "6%",
    prd_deskripsipanjang: "20%",
    obi_qtyorder: "5%",
    obi_qtyrealisasi: "5%",
    pobi_qty: "5%",
    selisih: "4%",
    obi_picker: "6%",
    pobi_create_by: "6%",
    hrgsatuan: "6%",
    pobi_nocontainer: "5%",
    jam_pick: "6%",
    jam_scan: "6%",
    ket: "4%",
};
const cashbackColumns = [
    ["kode_promo", "KODE"],
    ["plu", "PLU"],
    ["tipe", "TIPE"],
    ["kelipatan", "KLPTN"],
    ["cb_amount", "CB ORDER/REAL"],
];
const cashbackColumnWidths = {
    kode_promo: "14%",
    plu: "42%",
    tipe: "13%",
    kelipatan: "12%",
    cb_amount: "19%",
};

const isProcessItemReport = computed(() => props.report === "pb-proses-item");
const isProcessReport = computed(() => props.report === "pb-proses");
const isWideTable = ref(false);
const columns = computed(() =>
    isProcessItemReport.value
        ? reportColumns["pb-proses-item"]
        : pbDateColumns,
);
const isCompactTable = computed(() => !isWideTable.value);
const startDate = ref(props.filters.tglstart);
const endDate = ref(props.filters.tglend);
const searchInput = ref(props.filters.search);
const perPage = ref(String(props.filters.per_page));
const selectedKet = ref(props.filters.recid ?? "all");
const selectedKetItem = ref(props.filters.ket_item ?? "");
const selectedPin = ref(props.filters.pin ?? "all");
const copyMessage = ref("");
const copyLoading = ref(false);
const copyFallbackOpen = ref(false);
const copyFallbackText = ref("");
const copyFallbackInput = ref(null);
const pageJump = ref(String(props.rows.current_page ?? 1));
const sortKey = ref("tgl");
const sortDirection = ref("asc");
const driverModalOpen = ref(false);
const driverLoading = ref(false);
const driverError = ref("");
const driverRecords = ref([]);
const selectedDriverPb = ref("");
const requestModalOpen = ref(false);
const requestLoading = ref(false);
const requestError = ref("");
const selectedRequestRow = ref(null);
const giftModalOpen = ref(false);
const giftLoading = ref(false);
const giftSaving = ref(false);
const giftError = ref("");
const giftReceipt = ref(null);
const selectedGiftRow = ref(null);
const giftReceiptsByOrder = ref(new Map());
const requestForm = useForm({
    tglpb: "",
    nopb: "",
    notrans: "",
    kdmm: "",
    mode: "create",
    tgl_kirim: "",
    request: "",
    return_report: "pb-proses",
    return_tglstart: "",
    return_tglend: "",
    return_recid: "all",
    return_ekspedisi: "",
    return_pin: "",
    return_kdmm: "",
    return_nopb: "",
    return_request: false,
    return_search: "",
    return_per_page: "",
    return_page: "",
});
const giftForm = useForm({
    tglpb: "",
    nopb: "",
    notrans: "",
    kdmm: "",
    penginput: "",
    return_report: "pb-proses",
    return_tglstart: "",
    return_tglend: "",
    return_recid: "all",
    return_ekspedisi: "",
    return_pin: "",
    return_kdmm: "",
    return_nopb: "",
    return_request: false,
    return_search: "",
    return_per_page: "",
    return_page: "",
});
const loyaltyUpdateForm = useForm({
    kdmm: "",
    program: "",
    tgl_kirim: "",
    ttd: "",
    return_report: props.report,
    return_tglstart: props.filters.tglstart ?? "",
    return_tglend: props.filters.tglend ?? "",
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
const itemDetailsModalOpen = ref(false);
const itemDetailsLoading = ref(false);
const itemDetailsError = ref("");
const selectedItemPb = ref({ pb: "", tglpb: "" });
const itemDetails = ref([]);
const cashbackRows = ref([]);
const itemDetailsSearch = ref("");
const cashbackSearch = ref("");
const detailCopyMessage = ref("");
const modalCloseButtonClass =
    "rounded-lg bg-red-600 px-3 py-1 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-offset-gray-900";
const locationModalOpen = ref(false);
const locationLoading = ref(false);
const locationError = ref("");
const locationRows = ref([]);
const selectedLocationPb = ref("");
const loyaltyModalOpen = ref(false);
const loyaltyLoading = ref(false);
const loyaltyError = ref("");
const loyaltyMember = ref({ code: "", name: "" });
const loyaltyRecords = ref([]);
const selectedLoyaltyRow = ref(null);
const loyaltyEditingRecord = ref(null);
const validationErrors = computed(() =>
    Object.values(page.props.errors ?? {}).flat(),
);
const successMessage = computed(() => page.props.flash?.success ?? "");

const filteredItemDetails = computed(() =>
    filterRows(itemDetails.value, itemDetailsSearch.value),
);
const filteredCashbackRows = computed(() =>
    filterRows(cashbackRows.value, cashbackSearch.value),
);

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
const ketItemOptions = [
    { value: "", label: "Semua" },
    { value: "PICKING", label: "PICKING" },
    { value: "BATAL", label: "BATAL" },
    { value: "PICKING SCAN 0", label: "PICKING SCAN 0" },
];
const pinOptions = [
    { value: "all", label: "Semua" },
    { value: "belumpin", label: "Belum PIN" },
    { value: "sudahpin", label: "Sudah input PIN" },
    { value: "tms", label: "Masih TMS" },
    { value: "pinb", label: "PIN B" },
    { value: "indopaket", label: "Indopaket (semua diantar)" },
    { value: "ambil", label: "Ambil di SPI" },
];

const extraKetLabels = {
    notnull: "SDH_SEND",
    batas: "PROSES AWAL (1-3 + BLM_SEND)",
};
const requestTypes = ["RIT 1 PAGI", "RIT 2 SIANG", "RIT 3 SORE"];

watch(
    () => props.filters,
    (filters) => {
        startDate.value = filters.tglstart;
        endDate.value = filters.tglend;
        searchInput.value = filters.search;
        perPage.value = String(filters.per_page);
        selectedKet.value = filters.recid ?? "all";
        selectedKetItem.value = filters.ket_item ?? "";
        selectedPin.value = filters.pin ?? "all";
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
        ...(isProcessItemReport.value
            ? { ket_item: selectedKetItem.value }
            : {}),
        ...(isProcessReport.value ? { pin: selectedPin.value } : {}),
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
    selectedKet.value = isProcessReport.value
        ? "proses"
        : isProcessItemReport.value
          ? "batas"
          : "all";
    selectedKetItem.value = "";
    selectedPin.value = "all";
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

const dateOnly = (value) => String(value ?? "").slice(0, 10);

const itemPbParams = (row) => {
    const tglpb = dateOnly(row.obi_tglpb ?? row.tglpb ?? row.tgl);

    return {
        pb: row.pb,
        tglpb,
        tglstart: dateOnly(props.filters.tglstart) || tglpb,
        tglend: dateOnly(props.filters.tglend) || tglpb,
    };
};

const orderActionParams = (row) => ({
    tglpb: dateOnly(row.obi_tglpb ?? row.tglpb ?? row.tgl),
    nopb: row.obi_nopb,
    notrans: row.obi_notrans,
    kdmm: row.obi_kdmember,
    ...actionReturnFilters(),
});

const giftOrderKey = (row) => {
    const params = orderActionParams(row);

    return [params.tglpb, params.nopb, params.notrans, params.kdmm].join("|");
};

const isGiftSaved = (row) =>
    (row.hdh_qty !== null && row.hdh_qty !== undefined) ||
    giftReceiptsByOrder.value.has(giftOrderKey(row));

const giftInputUsers = ["HAINANI", "AMINAH", "HUSAINI", "FAUZAN", "ALEXANDER"];

const closeGiftModal = () => {
    giftModalOpen.value = false;
};

const submitGift = async () => {
    giftSaving.value = true;
    giftError.value = "";
    giftForm.clearErrors();

    try {
        const xsrfCookie = document.cookie
            .split("; ")
            .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
            ?.slice("XSRF-TOKEN=".length);

        if (!xsrfCookie) {
            throw new Error("Token sesi tidak tersedia. Muat ulang halaman.");
        }

        const endpoint = new URL(
            route("pb.actions.gift.store"),
            window.location.href,
        );
        const response = await fetch(
            `${window.location.origin}${endpoint.pathname}${endpoint.search}`,
            {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                    "X-XSRF-TOKEN": decodeURIComponent(xsrfCookie),
                },
                body: JSON.stringify(giftForm.data()),
            },
        );
        const contentType = response.headers.get("content-type") ?? "";
        const data = contentType.includes("application/json")
            ? await response.json()
            : null;

        if (response.status === 422 && data?.errors) {
            for (const [field, messages] of Object.entries(data.errors)) {
                giftForm.setError(
                    field,
                    Array.isArray(messages) ? messages[0] : String(messages),
                );
            }

            giftError.value = "Periksa kembali data yang ditandai.";

            return;
        }

        if (!response.ok) {
            if (response.status === 419) {
                throw new Error(
                    "Sesi keamanan sudah kedaluwarsa. Muat ulang halaman lalu coba kembali.",
                );
            }

            throw new Error(
                data?.message ?? `Permintaan gagal (${response.status}).`,
            );
        }

        if (!data?.receipt) {
            throw new Error("Data cetak hadiah dari server tidak valid.");
        }

        giftReceipt.value = data.receipt;
        giftReceiptsByOrder.value = new Map([
            ...giftReceiptsByOrder.value,
            [giftOrderKey(selectedGiftRow.value), data.receipt],
        ]);
    } catch (error) {
        giftError.value =
            error instanceof Error
                ? `Gagal menyimpan hadiah: ${error.message}`
                : "Gagal menyimpan hadiah.";
    } finally {
        giftSaving.value = false;
    }
};

const startLoyaltyUpdate = (record) => {
    loyaltyEditingRecord.value = record;
    loyaltyUpdateForm.kdmm = selectedLoyaltyRow.value.obi_kdmember;
    loyaltyUpdateForm.program = record.program;
    loyaltyUpdateForm.tgl_kirim = "";
    loyaltyUpdateForm.ttd = "";
    Object.assign(loyaltyUpdateForm, actionReturnFilters());
    loyaltyUpdateForm.clearErrors();
};

const cancelLoyaltyUpdate = () => {
    loyaltyEditingRecord.value = null;
    loyaltyUpdateForm.clearErrors();
};

const submitLoyaltyUpdate = () => {
    loyaltyUpdateForm.post(route("pb.actions.loyalty.update"), {
        preserveScroll: true,
        onSuccess: closeLoyaltyModal,
    });
};

const fetchReportDetails = async (url) => {
    const response = await fetch(url, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
    });

    if (!response.ok) {
        throw new Error(`Permintaan gagal (${response.status}).`);
    }

    return response.json();
};

const loadGiftReceipt = async (row) => {
    giftLoading.value = true;
    giftError.value = "";

    try {
        const data = await fetchReportDetails(
            route("pb.actions.gift.create", orderActionParams(row)),
        );

        if (!data.receipt) {
            throw new Error("Data cetak hadiah dari server tidak valid.");
        }

        giftReceipt.value = data.receipt;
        giftReceiptsByOrder.value = new Map([
            ...giftReceiptsByOrder.value,
            [giftOrderKey(row), data.receipt],
        ]);
    } catch (error) {
        giftError.value =
            error instanceof Error
                ? `Gagal memuat sertim hadiah: ${error.message}`
                : "Gagal memuat sertim hadiah.";
    } finally {
        giftLoading.value = false;
    }
};

const openGiftModal = async (row) => {
    selectedGiftRow.value = row;
    giftReceipt.value = null;
    giftError.value = "";
    giftModalOpen.value = true;

    if (isGiftSaved(row)) {
        const savedReceipt = giftReceiptsByOrder.value.get(giftOrderKey(row));

        if (savedReceipt) {
            giftReceipt.value = savedReceipt;
        } else {
            await loadGiftReceipt(row);
        }

        return;
    }

    giftForm.clearErrors();
    Object.assign(giftForm, {
        ...orderActionParams(row),
        penginput: "",
    });
};

const fillRequestForm = (record, mode, returnFilters) => {
    requestForm.tglpb = record.tglpb;
    requestForm.nopb = record.nopb;
    requestForm.notrans = record.notrans;
    requestForm.kdmm = record.kdmm;
    requestForm.mode = mode;
    requestForm.tgl_kirim = record.tgl_kirim ?? "";
    requestForm.request = record.request ?? "";
    requestForm.return_report = returnFilters.report ?? props.report;
    requestForm.return_tglstart = returnFilters.tglstart ?? props.filters.tglstart;
    requestForm.return_tglend = returnFilters.tglend ?? props.filters.tglend;
    requestForm.return_recid = returnFilters.recid ?? "all";
    requestForm.return_ekspedisi = returnFilters.ekspedisi ?? "";
    requestForm.return_pin = returnFilters.pin ?? "";
    requestForm.return_kdmm = returnFilters.kdmm ?? "";
    requestForm.return_nopb = returnFilters.nopb ?? "";
    requestForm.return_request = returnFilters.request ?? false;
    requestForm.return_search = returnFilters.search ?? "";
    requestForm.return_per_page = returnFilters.per_page ?? "";
    requestForm.return_page = returnFilters.page ?? "";
    requestForm.clearErrors();
};

const openRequestModal = async (row) => {
    const mode = row.req ? "edit" : "create";
    selectedRequestRow.value = row;
    requestModalOpen.value = true;
    requestLoading.value = true;
    requestError.value = "";

    try {
        const data = await fetchReportDetails(
            route("pb.actions.request.form", {
                mode,
                ...orderActionParams(row),
            }),
        );

        if (!data.record || !data.returnFilters) {
            throw new Error("Data request kirim dari server tidak valid.");
        }

        fillRequestForm(data.record, mode, data.returnFilters);
    } catch (error) {
        requestError.value =
            error instanceof Error
                ? `Gagal memuat form request kirim: ${error.message}`
                : "Gagal memuat form request kirim.";
    } finally {
        requestLoading.value = false;
    }
};

const closeRequestModal = () => {
    requestModalOpen.value = false;
};

const submitRequest = () => {
    requestForm.post(route("pb.actions.request.save"), {
        preserveScroll: true,
        onSuccess: closeRequestModal,
    });
};

const openItemDetailsModal = async (row) => {
    selectedItemPb.value = itemPbParams(row);
    itemDetails.value = [];
    cashbackRows.value = [];
    itemDetailsSearch.value = "";
    cashbackSearch.value = "";
    detailCopyMessage.value = "";
    itemDetailsError.value = "";
    itemDetailsLoading.value = true;
    itemDetailsModalOpen.value = true;

    try {
        const data = await fetchReportDetails(
            route("pb.items.details", selectedItemPb.value),
        );

        if (!Array.isArray(data.items) || !Array.isArray(data.cashbacks)) {
            throw new Error("Data item PB dari server tidak valid.");
        }

        itemDetails.value = data.items;
        cashbackRows.value = data.cashbacks;
    } catch (error) {
        itemDetailsError.value =
            error instanceof Error
                ? `Gagal memuat detail PB: ${error.message}`
                : "Gagal memuat detail PB.";
    } finally {
        itemDetailsLoading.value = false;
    }
};

const openLocationModal = async (row) => {
    const params = itemPbParams(row);
    selectedLocationPb.value = params.pb;
    locationRows.value = [];
    locationError.value = "";
    locationLoading.value = true;
    locationModalOpen.value = true;

    try {
        const data = await fetchReportDetails(
            route("pb.items.location", params),
        );

        if (!Array.isArray(data.rows)) {
            throw new Error("Data lokasi dari server tidak valid.");
        }

        locationRows.value = data.rows;
    } catch (error) {
        locationError.value =
            error instanceof Error
                ? `Gagal memuat lokasi PB: ${error.message}`
                : "Gagal memuat lokasi PB.";
    } finally {
        locationLoading.value = false;
    }
};

const closeLocationModal = () => {
    locationModalOpen.value = false;
};

const closeItemDetailsModal = () => {
    itemDetailsModalOpen.value = false;
};

const filterRows = (rows, search) => {
    const term = search.trim().toLocaleLowerCase("id-ID");

    if (!term) {
        return rows;
    }

    return rows.filter((row) =>
        Object.values(row).some((value) =>
            String(value ?? "")
                .toLocaleLowerCase("id-ID")
                .includes(term),
        ),
    );
};

const formatDetailValue = (value, key) => {
    if (value === null || value === undefined || value === "") {
        return "-";
    }

    if (
        [
            "obi_qtyorder",
            "obi_qtyrealisasi",
            "pobi_qty",
            "selisih",
            "hrgsatuan",
            "kelipatan",
            "cb_order",
            "cb_real",
        ].includes(key) &&
        Number.isFinite(Number(value))
    ) {
        return new Intl.NumberFormat("id-ID", {
            maximumFractionDigits: 2,
        }).format(Number(value));
    }

    return value;
};

const itemDetailValue = (key, row, index) => {
    if (key === "__number") {
        return index + 1;
    }

    if (key === "ket") {
        return row.ket_item === "BATAL" ? "BATAL" : row.ket ?? "-";
    }

    return formatDetailValue(row[key], key);
};

const detailDateTimeParts = (value) => {
    const text = String(value ?? "").trim();
    const [date = "-", ...time] = text.split(/[T\s]+/);

    return { date, time: time.join(" ") };
};

const cashbackValue = (key, row) => {
    if (key === "cb_amount") {
        return `(${formatDetailValue(row.cb_order, "cb_order")})/${formatDetailValue(row.cb_real, "cb_real")}`;
    }

    return formatDetailValue(row[key], key);
};

const copyLocalTable = async (message, headers, rows, valueForRow) => {
    const values = [
        headers.map(([, label]) => label),
        ...rows.map((row, index) =>
            headers.map(([key]) => valueForRow(key, row, index)),
        ),
    ];
    const text = values.map((value) => value.join("\t")).join("\r\n");
    const html = `<table><thead><tr>${values[0]
        .map((value) => `<th>${escapeHtml(value)}</th>`)
        .join("")}</tr></thead><tbody>${values
        .slice(1)
        .map(
            (row) =>
                `<tr>${row.map((value) => `<td>${escapeHtml(value)}</td>`).join("")}</tr>`,
        )
        .join("")}</tbody></table>`;

    try {
        if (
            typeof navigator.clipboard?.write === "function" &&
            typeof ClipboardItem !== "undefined"
        ) {
            try {
                await navigator.clipboard.write([
                    new ClipboardItem({
                        "text/plain": new Blob([text], { type: "text/plain" }),
                        "text/html": new Blob([html], { type: "text/html" }),
                    }),
                ]);
            } catch {
                await copyTextToClipboard(text);
            }
        } else {
            await copyTextToClipboard(text);
        }

        copyFallbackOpen.value = false;
        detailCopyMessage.value = `${new Intl.NumberFormat("id-ID").format(rows.length)} baris ${message} berhasil disalin.`;
    } catch (error) {
        copyFallbackText.value = text;
        copyFallbackOpen.value = true;
        detailCopyMessage.value =
            error instanceof Error
                ? `Browser membatasi salin otomatis (${error.message}). Tekan Ctrl+C pada isi yang sudah dipilih.`
                : "Browser membatasi salin otomatis. Tekan Ctrl+C pada isi yang sudah dipilih.";

        await nextTick();
        copyFallbackInput.value?.focus();
        copyFallbackInput.value?.select();
    }
};

const escapeHtml = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (character) =>
        ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
        })[character],
    );

const copyItemDetails = () =>
    copyLocalTable(
        "item PB",
        itemDetailColumns,
        filteredItemDetails.value,
        itemDetailValue,
    );

const copyCashbackDetails = () =>
    copyLocalTable(
        "cashback",
        cashbackColumns,
        filteredCashbackRows.value,
        cashbackValue,
    );

const openDriverModal = async (row) => {
    selectedDriverPb.value = row.pb;
    driverRecords.value = [];
    driverError.value = "";
    driverLoading.value = true;
    driverModalOpen.value = true;

    try {
        const data = await fetchReportDetails(
            route("pb.actions.driver-details", { pb: row.pb }),
        );

        if (!Array.isArray(data.drivers)) {
            throw new Error("Data driver dari server tidak valid.");
        }

        driverRecords.value = data.drivers;
    } catch (error) {
        driverError.value =
            error instanceof Error
                ? `Gagal memuat detail driver: ${error.message}`
                : "Gagal memuat detail driver.";
    } finally {
        driverLoading.value = false;
    }
};

const openLoyaltyModal = async (row) => {
    selectedLoyaltyRow.value = row;
    loyaltyMember.value = { code: row.obi_kdmember, name: row.cus_namamember };
    loyaltyRecords.value = [];
    loyaltyEditingRecord.value = null;
    loyaltyError.value = "";
    loyaltyLoading.value = true;
    loyaltyModalOpen.value = true;

    try {
        const data = await fetchReportDetails(
            route("pb.actions.loyalty.details", {
                kdmm: row.obi_kdmember,
            }),
        );

        if (!Array.isArray(data.records) || !data.member) {
            throw new Error("Data loyalty dari server tidak valid.");
        }

        loyaltyMember.value = data.member;
        loyaltyRecords.value = data.records;
    } catch (error) {
        loyaltyError.value =
            error instanceof Error
                ? `Gagal memuat detail loyalty: ${error.message}`
                : "Gagal memuat detail loyalty.";
    } finally {
        loyaltyLoading.value = false;
    }
};

const closeDriverModal = () => {
    driverModalOpen.value = false;
};

const closeLoyaltyModal = () => {
    loyaltyModalOpen.value = false;
    loyaltyEditingRecord.value = null;
    loyaltyUpdateForm.clearErrors();
};

const copyRows = async () => {
    copyLoading.value = true;
    copyMessage.value = "Mengambil seluruh data laporan...";

    try {
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

        const response = await fetch(
            `${route("pb.reports.copy", { report: props.report })}?${query}`,
            {
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            },
        );

        if (!response.ok) {
            throw new Error(`Permintaan gagal (${response.status}).`);
        }

        const payload = await response.json();

        if (!Array.isArray(payload.rows)) {
            throw new Error("Data laporan dari server tidak valid.");
        }

        const values = [
            columns.value.map(([, label]) => label),
            ...payload.rows.map((row) =>
                columns.value.map(([key]) =>
                    formatCell(displayValue(key, row), key, row),
                ),
            ),
        ];
        const text = values.map((value) => value.join("\t")).join("\n");

        try {
            await copyTextToClipboard(text);
            copyFallbackOpen.value = false;
            copyMessage.value = `${new Intl.NumberFormat("id-ID").format(payload.rows.length)} baris dari seluruh tabel berhasil disalin.`;
        } catch (error) {
            copyFallbackText.value = text;
            copyFallbackOpen.value = true;
            copyMessage.value =
                error instanceof Error
                    ? `Browser membatasi salin otomatis (${error.message}). Tekan Ctrl+C pada isi yang sudah dipilih.`
                    : "Browser membatasi salin otomatis. Tekan Ctrl+C pada isi yang sudah dipilih.";

            await nextTick();
            copyFallbackInput.value?.focus();
            copyFallbackInput.value?.select();
        }
    } catch (error) {
        copyMessage.value =
            error instanceof Error
                ? `Gagal mengambil seluruh data untuk disalin: ${error.message}`
                : "Gagal mengambil seluruh data untuk disalin.";
    } finally {
        copyLoading.value = false;
    }
};

const selectCopyFallback = () => {
    copyFallbackInput.value?.focus();
    copyFallbackInput.value?.select();
};

const displayValue = (key, row) => {
    if (key === "tglstruk") {
        return row.tglstruk;
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
            ? isGiftSaved(row)
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
    "hrgsatuan",
    "obi_qtyorder",
    "obi_qtyrealisasi",
    "pobi_qty",
    "selisih",
    "obi_qty_hitungulang",
    "obi_qtyba",
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

const formatShortDate = (value) => {
    if (!value) {
        return "Belum dikirim";
    }

    const date = String(value).slice(0, 10);

    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return value;
    }

    const parts = new Intl.DateTimeFormat("en-GB", {
        day: "2-digit",
        month: "short",
        year: "2-digit",
    }).formatToParts(new Date(`${date}T00:00:00`));
    const day = parts.find((part) => part.type === "day")?.value;
    const month = parts.find((part) => part.type === "month")?.value;
    const year = parts.find((part) => part.type === "year")?.value;

    return `${day}-${month?.toUpperCase()}-${year}`;
};

const cellClasses = (key, row) => {
    const isProcessItemDescription =
        isProcessItemReport.value && key === "prd_deskripsipanjang";
    const classes = !isCompactTable.value
        ? [
              `${isProcessItemDescription ? "whitespace-normal break-words text-left" : "whitespace-nowrap text-center"} px-3 py-2 align-middle text-sm text-gray-700 dark:text-gray-200`,
          ]
        : [
              `break-words px-1 py-1 ${isProcessItemDescription ? "text-left" : "text-center"} align-middle text-[10px] leading-tight text-gray-700 dark:text-gray-200`,
          ];

    if (key === "pb" && Number(row.obi_service_fee) > 0) {
        classes.push("bg-white font-bold text-sky-700");
    }

    if (key === "pin_cod" && Number(row.obi_service_fee) > 0) {
        classes.push("bg-white font-bold text-sky-700");
    }

    if (key === "ket_pin") {
        const pinStatus = String(row.ket_pin ?? "").toUpperCase();

        if (pinStatus === "MASIH TMS") {
            classes.push(
                "bg-yellow-300 font-bold text-yellow-950 dark:bg-yellow-700 dark:text-yellow-50",
            );
        } else if (pinStatus.startsWith("SUDAH INPUT PIN")) {
            classes.push(
                "bg-sky-200 font-bold text-sky-950 dark:bg-sky-800 dark:text-sky-100",
            );
        } else if (["SUDAH PIN B 1X", "SUDAH PIN B 2X"].includes(pinStatus)) {
            classes.push(
                "bg-red-600 font-bold text-white dark:bg-red-800 dark:text-red-100",
            );
        } else if (pinStatus === "BELUM INPUT PIN") {
            classes.push(
                "bg-orange-300 font-bold text-orange-950 dark:bg-orange-800 dark:text-orange-100",
            );
        } else if (row.ads_hadiah != null) {
            classes.push("bg-emerald-700 font-bold text-yellow-200");
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
            isGiftSaved(row)
                ? "bg-emerald-700 font-bold text-white"
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

const processItemColumnWidths = {
    pb: "65px",
    obi_tgltrans: "72px",
    obi_notrans: "55px",
    trjd_prdcd: "72px",
    obi_qtyorder: "52px",
    obi_qtyrealisasi: "52px",
    pobi_qty: "58px",
    selisih: "55px",
    obi_picker: "58px",
    pobi_create_by: "58px",
    hrgsatuan: "68px",
    pobi_nocontainer: "80px",
    jam_pick: "90px",
    jam_scan: "90px",
    ket: "60px",
    ket_item: "60px",
    obi_qty_hitungulang: "85px",
    obi_qtyba: "60px",
};

const columnWidth = (key) => {
    if (isProcessItemReport.value && !isWideTable.value) {
        return processItemColumnWidths[key];
    }

    return isCompactTable.value ? columnWidths[key] : undefined;
};

const dateTimeParts = (value) => {
    const [date = "", time = ""] = String(value ?? "")
        .replace("T", " ")
        .split(/\s+/, 2);

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
                    class="grid gap-3 sm:grid-cols-2"
                    :class="
                        isProcessItemReport
                            ? 'xl:grid-cols-[minmax(10rem,1fr)_minmax(10rem,1fr)_minmax(12rem,1.3fr)_minmax(7rem,.6fr)_minmax(8rem,.8fr)_minmax(10rem,.9fr)_auto]'
                            : isProcessReport
                              ? 'xl:grid-cols-[minmax(11rem,1fr)_minmax(11rem,1fr)_minmax(13rem,1.4fr)_minmax(7rem,.6fr)_minmax(11rem,.9fr)_minmax(10rem,.85fr)_auto]'
                            : 'xl:grid-cols-[minmax(11rem,1fr)_minmax(11rem,1fr)_minmax(13rem,1.4fr)_minmax(7rem,.6fr)_minmax(11rem,.9fr)_auto]'
                    "
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

                    <label
                        v-if="isProcessReport"
                        class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        <span>PIN</span>
                        <select
                            v-model="selectedPin"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option
                                v-for="option in pinOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label
                        v-if="isProcessItemReport"
                        class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200"
                    >
                        <span>ITEM</span>
                        <select
                            v-model="selectedKetItem"
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option
                                v-for="option in ketItemOptions"
                                :key="option.value || 'all'"
                                :value="option.value"
                            >
                                {{ option.label }}
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
                    Pencarian mencakup kolom laporan. Export Excel dan salin mencakup semua hasil sesuai filter.
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
                            type="button"
                            :aria-pressed="isWideTable"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:scale-[1.02] hover:bg-gray-50 active:scale-[.98] dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="isWideTable = !isWideTable"
                        >
                            {{ isWideTable ? "Tampilan ringkas" : "Perbesar tabel" }}
                        </button>
                        <button
                            type="button"
                            :disabled="copyLoading"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:scale-[1.02] hover:bg-gray-50 active:scale-[.98] dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="copyRows"
                        >
                            {{ copyLoading ? "Memuat..." : "Salin seluruh tabel" }}
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
                    class="max-h-[calc(100vh-22rem)] min-h-64 overflow-auto rounded-xl border border-gray-200 dark:border-gray-700"
                >
                    <table
                        :class="
                            isProcessItemReport
                                ? isWideTable
                                    ? 'w-full min-w-[1600px] table-auto text-sm'
                                    : 'w-full min-w-[1400px] table-fixed text-[10px] leading-tight'
                                : isWideTable
                                  ? 'w-full min-w-[1800px] table-auto text-sm'
                                  : 'w-full table-fixed text-[10px] leading-tight'
                        "
                    >
                        <thead
                            class="bg-cyan-100 text-gray-800 dark:bg-cyan-900/80 dark:text-gray-100"
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
                                            ? 'sticky top-0 z-10 whitespace-nowrap bg-cyan-100 px-3 py-2 text-left text-xs font-semibold uppercase dark:bg-cyan-900/80'
                                            : 'sticky top-0 z-10 break-words bg-cyan-100 px-1 py-2 text-center text-[9px] font-semibold uppercase dark:bg-cyan-900/80'
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
                                <span v-else class="whitespace-pre-line">{{ label }}</span>
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
                                            isProcessItemReport &&
                                            ['jam_pick', 'jam_scan'].includes(key)
                                        "
                                    >
                                        <span class="block">{{
                                            dateTimeParts(row[key]).date
                                                ? formatShortDate(
                                                      dateTimeParts(row[key]).date,
                                                  )
                                                : "-"
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
                                            isProcessItemReport &&
                                            key === 'trjd_prdcd' &&
                                            row.trjd_prdcd
                                        "
                                    >
                                        <a
                                            :href="route('plu.info', { plu: row.trjd_prdcd })"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                                        >
                                            {{ formatCell(displayValue(key, row), key, row) }}
                                        </a>
                                    </template>
                                    <template
                                        v-else-if="isProcessReport && key === 'pb'"
                                    >
                                        <button
                                            type="button"
                                            class="text-xs font-bold text-sky-700 hover:text-indigo-700 dark:text-cyan-300 dark:hover:text-cyan-100"
                                            :aria-label="`Lihat detail item dan cashback untuk PB ${row.pb}`"
                                            @click="openItemDetailsModal(row)"
                                        >
                                            {{ row.pb }}
                                        </button>
                                    </template>
                                    <template
                                        v-else-if="
                                            isProcessReport &&
                                            key === 'obi_itemorder'
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="font-semibold text-sky-700 hover:text-indigo-700 hover:underline dark:text-cyan-300 dark:hover:text-cyan-100"
                                            :aria-label="`Buka preview lokasi item untuk PB ${row.pb}`"
                                            @click="openLocationModal(row)"
                                        >
                                            {{ formatCell(displayValue(key, row), key, row) }}
                                        </button>
                                    </template>
                                    <template v-else-if="key === 'ket_pin'">
                                        <button
                                            type="button"
                                            class="w-full cursor-pointer underline decoration-dotted underline-offset-2 hover:text-indigo-700 dark:hover:text-cyan-200"
                                            :aria-label="`Lihat detail driver untuk PB ${row.pb}`"
                                            @click="openDriverModal(row)"
                                        >
                                            {{ formatCell(displayValue(key, row), key, row) }}
                                        </button>
                                    </template>
                                    <template
                                        v-else-if="
                                            key === 'kena_ongkir' &&
                                            isProcessReport &&
                                            row.ads_hadiah != null
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex max-w-full items-center justify-center rounded px-1 py-0.5 font-bold text-white transition"
                                            :class="
                                                isGiftSaved(row)
                                                    ? 'bg-emerald-900 hover:bg-emerald-950'
                                                    : 'bg-red-900 hover:bg-red-950'
                                            "
                                            :aria-label="
                                                isGiftSaved(row)
                                                    ? `Cetak sertim hadiah PB ${row.pb}`
                                                    : `Input hadiah PB ${row.pb}`
                                            "
                                            @click="openGiftModal(row)"
                                        >
                                            {{
                                                isGiftSaved(row)
                                                    ? "✓ Cetak hadiah"
                                                    : "✗ Input hadiah"
                                            }}
                                        </button>
                                    </template>
                                    <template
                                        v-else-if="
                                            isProcessReport &&
                                            key === 'jenis' &&
                                            row.gttipe
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="rounded bg-amber-100 px-1 font-bold text-amber-900 transition hover:bg-amber-200 hover:underline dark:bg-amber-900 dark:text-amber-100 dark:hover:bg-amber-800"
                                            @click="openLoyaltyModal(row)"
                                        >
                                            {{ row.gttipe }}
                                        </button>
                                    </template>
                                    <template
                                        v-else-if="
                                            key === 'loyalty' &&
                                            isProcessReport &&
                                            Number(row.loyalty) > 0
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="font-bold text-indigo-700 transition hover:text-indigo-900 hover:underline dark:text-cyan-300 dark:hover:text-cyan-100"
                                            @click="openLoyaltyModal(row)"
                                        >
                                            {{ formatCell(displayValue(key, row), key, row) }}
                                        </button>
                                    </template>
                                    <template
                                        v-else-if="isProcessReport && key === 'req'"
                                    >
                                        <button
                                            type="button"
                                            class="font-semibold text-indigo-700 transition hover:text-indigo-900 hover:underline dark:text-cyan-300 dark:hover:text-cyan-100"
                                            @click="openRequestModal(row)"
                                        >
                                            {{ row.req || "Tambah" }}
                                        </button>
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

        <Modal
            :show="driverModalOpen"
            max-width="7xl"
            @close="closeDriverModal"
        >
            <section class="space-y-4 p-5 dark:bg-gray-900">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Detail Driver — PB {{ selectedDriverPb }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Data serah terima berdasarkan nomor PB.
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="modalCloseButtonClass"
                        aria-label="Tutup detail driver"
                        @click="closeDriverModal"
                    >
                        Tutup
                    </button>
                </header>

                <p
                    v-if="driverLoading"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat data driver...
                </p>
                <p
                    v-else-if="driverError"
                    class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                    role="alert"
                >
                    {{ driverError }}
                </p>
                <p
                    v-else-if="driverRecords.length === 0"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    Tidak ada data driver untuk PB ini.
                </p>
                <div v-else class="max-h-[calc(100vh-12rem)] overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full min-w-max text-sm">
                        <thead class="bg-amber-100 text-gray-800 dark:bg-cyan-900/80 dark:text-gray-100">
                            <tr>
                                <th v-for="heading in ['No AWB', 'PB', 'Status', 'Member', 'Tanggal', 'Jam', 'RPH', 'Driver ID', 'Nama Driver', 'HP', 'Plat']" :key="heading" class="whitespace-nowrap px-3 py-2 text-left font-semibold">
                                    {{ heading }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(driver, index) in driverRecords"
                                :key="`${driver.order_number}-${driver.no_awb}-${index}`"
                                class="border-t border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-200"
                            >
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.no_awb || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.order_number || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.process_type || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.member_name || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.handover_date || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.handover_time || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatCell(driver.cod_value, "ttl_order", driver) }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.driver_id || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-semibold">{{ driver.driver_name || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.driver_phone || "-" }}</td>
                                <td class="whitespace-nowrap px-3 py-2">{{ driver.vehicle_number || "-" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </Modal>

        <Modal
            :show="requestModalOpen"
            max-width="2xl"
            @close="closeRequestModal"
        >
            <section class="space-y-4 p-5 dark:bg-gray-900">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ requestForm.mode === "edit" ? "Edit Request Kirim" : "Request Kirim" }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            PB {{ selectedRequestRow?.pb }} · {{ selectedRequestRow?.nama || selectedRequestRow?.cus_namamember || "Nama member tidak tersedia" }}
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="modalCloseButtonClass"
                        aria-label="Tutup form request kirim"
                        @click="closeRequestModal"
                    >
                        Tutup
                    </button>
                </header>

                <p
                    v-if="requestLoading"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat form request kirim...
                </p>
                <p
                    v-else-if="requestError"
                    class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                    role="alert"
                >
                    {{ requestError }}
                </p>
                <form
                    v-else
                    class="pb-neon-panel pb-neon-panel--request space-y-4"
                    @submit.prevent="submitRequest"
                >
                    <label class="block space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Tanggal kirim</span>
                        <input
                            v-model="requestForm.tgl_kirim"
                            type="date"
                            required
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </label>
                    <p
                        v-if="requestForm.errors.tgl_kirim"
                        class="text-sm text-rose-600 dark:text-rose-300"
                        role="alert"
                    >
                        {{ requestForm.errors.tgl_kirim }}
                    </p>
                    <label class="block space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Request rit</span>
                        <select
                            v-model="requestForm.request"
                            required
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="" disabled>Pilih jadwal kirim</option>
                            <option
                                v-for="requestType in requestTypes"
                                :key="requestType"
                                :value="requestType"
                            >
                                {{ requestType }}
                            </option>
                        </select>
                    </label>
                    <p
                        v-if="requestForm.errors.request"
                        class="text-sm text-rose-600 dark:text-rose-300"
                        role="alert"
                    >
                        {{ requestForm.errors.request }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-offset-gray-900"
                            @click="closeRequestModal"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="requestForm.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ requestForm.processing ? "Menyimpan..." : "Simpan" }}
                        </button>
                    </div>
                </form>
            </section>
        </Modal>

        <Modal
            :show="giftModalOpen"
            max-width="2xl"
            :closeable="!giftSaving && !giftLoading"
            @close="closeGiftModal"
        >
            <section class="space-y-4 p-5 dark:bg-gray-900">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ giftReceipt ? "Cetak Sertim Hadiah" : "Input Hadiah PB" }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            PB {{ selectedGiftRow?.pb }} ·
                            {{ selectedGiftRow?.cus_namamember || "Nama member tidak tersedia" }}
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="modalCloseButtonClass"
                        :disabled="giftSaving || giftLoading"
                        aria-label="Tutup form hadiah"
                        @click="closeGiftModal"
                    >
                        Tutup
                    </button>
                </header>

                <p
                    v-if="giftError"
                    class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                    role="alert"
                >
                    {{ giftError }}
                </p>

                <p
                    v-if="giftLoading"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat sertim hadiah...
                </p>
                <PbGiftReceiptPreview
                    v-else-if="giftReceipt"
                    :receipt="giftReceipt"
                />
                <div
                    v-else-if="selectedGiftRow && isGiftSaved(selectedGiftRow)"
                    class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800"
                >
                    <span class="text-gray-700 dark:text-gray-200">
                        Sertim sudah tersimpan, tetapi belum dapat dimuat.
                    </span>
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white hover:bg-indigo-700"
                        @click="loadGiftReceipt(selectedGiftRow)"
                    >
                        Coba lagi
                    </button>
                </div>
                <form
                    v-else
                    class="pb-neon-panel pb-neon-panel--gift space-y-4"
                    @submit.prevent="submitGift"
                >
                    <dl class="grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800 sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">No. PB</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ selectedGiftRow?.pb }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Kode member</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ selectedGiftRow?.obi_kdmember }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500 dark:text-gray-400">Nama hadiah</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ selectedGiftRow?.ads_hadiah }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">PLU hadiah</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ selectedGiftRow?.ads_prdcd }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Qty</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ selectedGiftRow?.ads_qty }}</dd>
                        </div>
                    </dl>

                    <label class="block space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <span>Penginput</span>
                        <select
                            v-model="giftForm.penginput"
                            required
                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="" disabled>Pilih penginput</option>
                            <option
                                v-for="user in giftInputUsers"
                                :key="user"
                                :value="user"
                            >
                                {{ user }}
                            </option>
                        </select>
                    </label>
                    <p
                        v-if="giftForm.errors.penginput"
                        class="text-sm text-rose-600 dark:text-rose-300"
                        role="alert"
                    >
                        {{ giftForm.errors.penginput }}
                    </p>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            :class="modalCloseButtonClass"
                            :disabled="giftSaving"
                            @click="closeGiftModal"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="giftSaving"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ giftSaving ? "Menyimpan..." : "Simpan hadiah" }}
                        </button>
                    </div>
                </form>
            </section>
        </Modal>

        <Modal
            :show="locationModalOpen"
            max-width="2xl"
            @close="closeLocationModal"
        >
            <section class="space-y-4 p-5 dark:bg-gray-900">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Preview lokasi PB {{ selectedLocationPb }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Barcode siap dicetak pada kertas 80 mm.
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="modalCloseButtonClass"
                        aria-label="Tutup preview lokasi"
                        @click="closeLocationModal"
                    >
                        Tutup
                    </button>
                </header>

                <p
                    v-if="locationLoading"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat lokasi dan barcode...
                </p>
                <p
                    v-else-if="locationError"
                    class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                    role="alert"
                >
                    {{ locationError }}
                </p>
                <PbLocationPreview v-else :rows="locationRows" />
            </section>
        </Modal>

        <Modal
            :show="itemDetailsModalOpen"
            max-width="screen"
            @close="closeItemDetailsModal"
        >
            <section class="max-h-[92vh] space-y-4 overflow-y-auto p-3 sm:p-4 dark:bg-gray-900">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Detail PB {{ selectedItemPb.pb }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Item order dan cashback berdasarkan PB {{ selectedItemPb.pb }}.
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="modalCloseButtonClass"
                        aria-label="Tutup detail PB"
                        @click="closeItemDetailsModal"
                    >
                        Tutup
                    </button>
                </header>

                <p
                    v-if="itemDetailsLoading"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat item PB dan cashback...
                </p>
                <p
                    v-else-if="itemDetailsError"
                    class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                    role="alert"
                >
                    {{ itemDetailsError }}
                </p>
                <div v-else class="grid gap-4 xl:grid-cols-[minmax(0,2.1fr)_minmax(20rem,1fr)]">
                    <section class="min-w-0 space-y-3">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <label class="min-w-56 flex-1 space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Cari item</span>
                                <input
                                    v-model="itemDetailsSearch"
                                    type="search"
                                    placeholder="PLU, deskripsi, status..."
                                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </label>
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="copyItemDetails"
                            >
                                Salin item
                            </button>
                        </div>
                        <div class="max-h-[55vh] overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full table-fixed text-[11px] leading-tight">
                                <colgroup>
                                    <col
                                        v-for="[key] in itemDetailColumns"
                                        :key="key"
                                        :style="{ width: itemDetailColumnWidths[key] }"
                                    />
                                </colgroup>
                                <thead class="sticky top-0 z-10 bg-cyan-100 text-gray-800 dark:bg-cyan-900/80 dark:text-gray-100">
                                    <tr>
                                        <th
                                            v-for="[key, label] in itemDetailColumns"
                                            :key="key"
                                            class="whitespace-normal break-words px-1 py-2 text-left font-semibold"
                                        >
                                            {{ label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in filteredItemDetails"
                                        :key="`${row.pb}-${row.obi_notrans}-${row.trjd_prdcd}-${index}`"
                                        class="border-t border-gray-200 text-gray-700 odd:bg-white even:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                                    >
                                        <td
                                            v-for="[key] in itemDetailColumns"
                                            :key="key"
                                            class="px-1 py-1 align-top"
                                            :class="[
                                                ['prd_deskripsipanjang', 'pobi_nocontainer', 'jam_pick', 'jam_scan'].includes(key)
                                                    ? 'whitespace-normal break-words'
                                                    : 'whitespace-nowrap',
                                                row.ket_item === 'BATAL' && key === 'trjd_prdcd'
                                                    ? 'bg-rose-200 font-bold text-rose-950 dark:bg-rose-900 dark:text-rose-100'
                                                    : '',
                                            ]"
                                        >
                                            <a
                                                v-if="key === 'trjd_prdcd' && row.trjd_prdcd"
                                                :href="route('plu.info', { plu: row.trjd_prdcd })"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300"
                                            >
                                                {{ itemDetailValue(key, row, index) }}
                                            </a>
                                            <span
                                                v-else-if="key === 'prd_deskripsipanjang'"
                                                class="line-clamp-2"
                                                :title="String(row[key] ?? '')"
                                            >
                                                {{ itemDetailValue(key, row, index) }}
                                            </span>
                                            <template
                                                v-else-if="['jam_pick', 'jam_scan'].includes(key)"
                                            >
                                                <span class="block">
                                                    {{ detailDateTimeParts(row[key]).date }}
                                                </span>
                                                <span
                                                    v-if="detailDateTimeParts(row[key]).time"
                                                    class="block text-[10px] text-gray-500 dark:text-gray-400"
                                                >
                                                    {{ detailDateTimeParts(row[key]).time }}
                                                </span>
                                            </template>
                                            <template v-else>
                                                {{ itemDetailValue(key, row, index) }}
                                            </template>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredItemDetails.length === 0">
                                        <td
                                            :colspan="itemDetailColumns.length"
                                            class="px-3 py-6 text-center text-gray-500 dark:text-gray-400"
                                        >
                                            Tidak ada item yang cocok.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="min-w-0 space-y-3">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <label class="min-w-40 flex-1 space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Cari cashback</span>
                                <input
                                    v-model="cashbackSearch"
                                    type="search"
                                    placeholder="Kode promo, PLU, tipe..."
                                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </label>
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="copyCashbackDetails"
                            >
                                Salin cashback
                            </button>
                        </div>
                        <div class="max-h-[55vh] overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full table-fixed text-[11px] leading-tight">
                                <colgroup>
                                    <col
                                        v-for="[key] in cashbackColumns"
                                        :key="key"
                                        :style="{ width: cashbackColumnWidths[key] }"
                                    />
                                </colgroup>
                                <thead class="sticky top-0 z-10 bg-cyan-100 text-gray-800 dark:bg-cyan-900/80 dark:text-gray-100">
                                    <tr>
                                        <th
                                            v-for="[key, label] in cashbackColumns"
                                            :key="key"
                                            class="whitespace-normal break-words px-1 py-2 text-left font-semibold"
                                        >
                                            {{ label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in filteredCashbackRows"
                                        :key="`${row.kode_promo}-${row.plu}-${index}`"
                                        class="border-t border-gray-200 text-gray-700 odd:bg-white even:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                                    >
                                        <td
                                            v-for="[key] in cashbackColumns"
                                            :key="key"
                                            class="px-1 py-1 align-top"
                                            :class="key === 'plu' ? 'whitespace-normal break-all' : 'whitespace-nowrap'"
                                        >
                                            {{ cashbackValue(key, row) }}
                                        </td>
                                    </tr>
                                    <tr v-if="filteredCashbackRows.length === 0">
                                        <td
                                            :colspan="cashbackColumns.length"
                                            class="px-3 py-6 text-center text-gray-500 dark:text-gray-400"
                                        >
                                            Tidak ada cashback yang cocok.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <p
                    v-if="detailCopyMessage"
                    role="status"
                    class="text-sm text-gray-600 dark:text-gray-300"
                >
                    {{ detailCopyMessage }}
                </p>
            </section>
        </Modal>

        <Modal
            :show="loyaltyModalOpen"
            max-width="2xl"
            @close="closeLoyaltyModal"
        >
            <section class="space-y-4 p-5 dark:bg-gray-900">
                <header class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{
                                loyaltyEditingRecord
                                    ? "Update Loyalty"
                                    : "Loyalty belum dikirim"
                            }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ loyaltyMember.code }} · {{ loyaltyMember.name || "Nama member tidak tersedia" }}
                        </p>
                    </div>
                    <button
                        type="button"
                        :class="modalCloseButtonClass"
                        aria-label="Tutup detail loyalty"
                        @click="closeLoyaltyModal"
                    >
                        Tutup
                    </button>
                </header>

                <p
                    v-if="loyaltyLoading"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat data loyalty...
                </p>
                <p
                    v-else-if="loyaltyError"
                    class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                    role="alert"
                >
                    {{ loyaltyError }}
                </p>
                <p
                    v-else-if="loyaltyRecords.length === 0"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    Tidak ada loyalty yang belum dikirim.
                </p>
                <div
                    v-else-if="loyaltyEditingRecord"
                    class="pb-neon-panel pb-neon-panel--loyalty space-y-4"
                >
                    <div class="space-y-1 text-sm text-gray-700 dark:text-gray-200">
                        <p>
                            Program:
                            <strong>{{ loyaltyEditingRecord.program }}</strong>
                        </p>
                        <p>Hadiah: {{ loyaltyEditingRecord.gift || "-" }}</p>
                        <p>Periode: {{ loyaltyEditingRecord.period || "-" }}</p>
                    </div>
                    <form
                        class="space-y-4"
                        @submit.prevent="submitLoyaltyUpdate"
                    >
                        <label
                            class="block space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            <span>Tanggal Kirim</span>
                            <input
                                v-model="loyaltyUpdateForm.tgl_kirim"
                                type="date"
                                required
                                class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                            <span
                                v-if="loyaltyUpdateForm.errors.tgl_kirim"
                                class="block text-sm text-rose-600"
                                role="alert"
                            >
                                {{ loyaltyUpdateForm.errors.tgl_kirim }}
                            </span>
                        </label>
                        <label
                            class="block space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            <span>Nama Pengirim / Tanda Tangan</span>
                            <input
                                v-model="loyaltyUpdateForm.ttd"
                                type="text"
                                maxlength="100"
                                required
                                class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                            <span
                                v-if="loyaltyUpdateForm.errors.ttd"
                                class="block text-sm text-rose-600"
                                role="alert"
                            >
                                {{ loyaltyUpdateForm.errors.ttd }}
                            </span>
                        </label>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                :disabled="loyaltyUpdateForm.processing"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 disabled:opacity-60 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="cancelLoyaltyUpdate"
                            >
                                Kembali
                            </button>
                            <button
                                type="submit"
                                :disabled="loyaltyUpdateForm.processing"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{
                                    loyaltyUpdateForm.processing
                                        ? "Menyimpan..."
                                        : "Update"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
                <div v-else class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full text-sm">
                        <thead class="bg-amber-100 text-gray-800 dark:bg-cyan-900/80 dark:text-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Program</th>
                                <th class="px-3 py-2 text-left font-semibold">Hadiah</th>
                                <th class="px-3 py-2 text-left font-semibold">Periode</th>
                                <th class="px-3 py-2 text-left font-semibold">Tgl Kirim</th>
                                <th class="px-3 py-2 text-left font-semibold">TTD</th>
                                <th class="px-3 py-2 text-left font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(record, index) in loyaltyRecords"
                                :key="`${record.program}-${index}`"
                                class="border-t border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-200"
                            >
                                <td class="px-3 py-2">{{ record.program }}</td>
                                <td class="px-3 py-2">{{ record.gift || "-" }}</td>
                                <td class="px-3 py-2">{{ record.period || "-" }}</td>
                                <td class="px-3 py-2">{{ formatShortDate(record.sent_at) }}</td>
                                <td class="px-3 py-2">{{ record.signature || "Belum" }}</td>
                                <td class="px-3 py-2">
                                    <button
                                        v-if="selectedLoyaltyRow"
                                        type="button"
                                        class="font-semibold text-indigo-700 underline hover:text-indigo-900 dark:text-cyan-300"
                                        @click="startLoyaltyUpdate(record)"
                                    >
                                        Update
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </Modal>

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
