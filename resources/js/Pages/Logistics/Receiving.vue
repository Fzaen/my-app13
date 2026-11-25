<script setup>
import { computed, nextTick, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";

const props = defineProps({
    supplierRows: {
        type: Array,
        required: true,
    },
    purchaseOrderRows: {
        type: Array,
        required: true,
    },
    itemRows: {
        type: Array,
        required: true,
    },
});

const tabs = [
    { id: "suppliers", label: "Antrian Supplier", rows: props.supplierRows },
    { id: "purchase-orders", label: "List PO", rows: props.purchaseOrderRows },
    { id: "items", label: "List Items", rows: props.itemRows },
];

const activeTab = ref(tabs[0].id);
const searchInput = ref("");
const tabButtons = ref([]);
const showSupplierModal = ref(false);
const selectedSupplier = ref(null);
const supplierReturnRows = ref([]);
const supplierReturnLoading = ref(false);
const supplierReturnError = ref("");
let supplierReturnController = null;

const activeRows = computed(() => {
    const active = tabs.find((tab) => tab.id === activeTab.value);
    const search = searchInput.value.trim().toLocaleLowerCase("id-ID");

    if (!search) {
        return active?.rows ?? [];
    }

    return (active?.rows ?? []).filter((row) =>
        Object.values(row).some((value) =>
            String(value ?? "")
                .toLocaleLowerCase("id-ID")
                .includes(search),
        ),
    );
});

const itemTotals = computed(() => {
    const totals = {
        qtyPo: 0,
        qtyInvoice: 0,
        qtyBpb: 0,
        bpbCtn: 0,
        percent: 0,
        dpp: 0,
        bpb: 0,
        gross: 0,
        ppn: 0,
    };

    for (const item of props.itemRows) {
        totals.qtyPo += Number(item.tpod_qtypo) || 0;
        totals.qtyInvoice += Number(item.qty_inputpo) || 0;
        totals.qtyBpb += Number(item.qtybpb) || 0;
        totals.bpbCtn += Number(item.bpbctn) || 0;
        totals.percent += Number(item.persen) || 0;
        totals.dpp += Number(item.dpp) || 0;
        totals.bpb += Number(item.rp_bpb) || 0;
        totals.gross += Number(item.mstd_gross) || 0;
        totals.ppn += Number(item.mstd_ppnrph) || 0;
    }

    totals.percent = props.itemRows.length
        ? totals.percent / props.itemRows.length
        : 0;

    return totals;
});

const formatNumber = (value) => {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    const number = Number(value);

    return Number.isFinite(number)
        ? new Intl.NumberFormat("id-ID", {
              maximumFractionDigits: 2,
          }).format(number)
        : "—";
};

const formatPercent = (value) => {
    const formatted = formatNumber(value);

    return formatted === "—" ? formatted : `${formatted}%`;
};

const statusClass = (status) => {
    if (["SELESAI", "CLOSE"].includes(status)) {
        return "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200";
    }

    if (status === "IMPORT DCP") {
        return "bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200";
    }

    if (status === "SCAN" || status === "BLM INPUT" || status === "PROSES") {
        return "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200";
    }

    return "bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200";
};

const activateTab = async (index) => {
    const nextIndex = (index + tabs.length) % tabs.length;
    activeTab.value = tabs[nextIndex].id;
    searchInput.value = "";

    await nextTick();
    tabButtons.value[nextIndex]?.focus();
};

const openSupplierReturns = async (supplier) => {
    supplierReturnController?.abort();
    const requestController = new AbortController();
    supplierReturnController = requestController;
    selectedSupplier.value = supplier;
    supplierReturnRows.value = [];
    supplierReturnError.value = "";
    supplierReturnLoading.value = true;
    showSupplierModal.value = true;

    try {
        const response = await fetch(
            route("logistics.receiving.supplier-returns", {
                supplier: supplier.id_sup,
            }),
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                signal: requestController.signal,
            },
        );

        if (!response.ok) {
            throw new Error(`Request retur gagal (${response.status}).`);
        }

        const result = await response.json();

        if (!Array.isArray(result.rows)) {
            throw new Error("Format data retur supplier tidak valid.");
        }

        supplierReturnRows.value = result.rows;
    } catch (error) {
        if (requestController.signal.aborted) {
            return;
        }

        console.error("Failed to load supplier return rows.", error);
        supplierReturnError.value =
            error instanceof Error
                ? error.message
                : "Data retur supplier tidak dapat dimuat.";
    } finally {
        if (supplierReturnController === requestController) {
            supplierReturnLoading.value = false;
        }
    }
};

const closeSupplierModal = () => {
    supplierReturnController?.abort();
    supplierReturnController = null;
    showSupplierModal.value = false;
    selectedSupplier.value = null;
    supplierReturnLoading.value = false;
};
</script>

<template>
    <Head title="Logistik - Penerimaan" />

    <AuthenticatedLayout>
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1800px] space-y-6">
                <section
                    class="space-y-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div
                            class="logistics-tab-list flex w-full gap-2 overflow-x-auto rounded-2xl bg-gray-50 p-2 dark:bg-gray-950 sm:w-fit"
                            role="tablist"
                            aria-label="Bagian penerimaan"
                        >
                            <button
                                v-for="(tab, index) in tabs"
                                :id="`logistics-tab-${tab.id}`"
                                :key="tab.id"
                                :ref="
                                    (element) => {
                                        tabButtons[index] = element;
                                    }
                                "
                                type="button"
                                role="tab"
                                :aria-selected="activeTab === tab.id"
                                :aria-controls="`logistics-panel-${tab.id}`"
                                :tabindex="activeTab === tab.id ? 0 : -1"
                                class="logistics-tab inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-600 hover:text-cyan-800 dark:text-gray-300 dark:hover:text-cyan-200"
                                :class="
                                    activeTab === tab.id
                                        ? 'text-cyan-800 dark:text-cyan-200'
                                        : ''
                                "
                                @click="activeTab = tab.id"
                                @keydown.arrow-right.prevent="
                                    activateTab(index + 1)
                                "
                                @keydown.arrow-left.prevent="
                                    activateTab(index - 1)
                                "
                                @keydown.home.prevent="activateTab(0)"
                                @keydown.end.prevent="
                                    activateTab(tabs.length - 1)
                                "
                            >
                                {{ tab.label }}
                                <span
                                    class="logistics-tab-count rounded-full px-2 py-0.5 text-xs font-bold"
                                >
                                    {{ tab.rows.length }}
                                </span>
                            </button>
                        </div>
                        <label class="relative block w-full sm:max-w-sm">
                            <span class="sr-only">Cari di tabel aktif</span>
                            <svg
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <circle cx="11" cy="11" r="7" />
                                <path
                                    stroke-linecap="round"
                                    d="m16 16 4 4"
                                />
                            </svg>
                            <input
                                v-model="searchInput"
                                type="search"
                                placeholder="Cari supplier, PO, PLU..."
                                class="w-full rounded-xl border-gray-300 py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </label>
                    </div>

                    <section
                        v-show="activeTab === 'suppliers'"
                        id="logistics-panel-suppliers"
                        role="tabpanel"
                        aria-labelledby="logistics-tab-suppliers"
                        tabindex="0"
                        class="overflow-hidden rounded-xl border border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:border-gray-700"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px] text-sm">
                                <thead
                                    class="bg-cyan-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <tr>
                                        <th class="px-4 py-3">No. Antrian</th>
                                        <th class="px-4 py-3">Kode Supplier</th>
                                        <th class="px-4 py-3">Nama Supplier</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">PIC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in activeRows"
                                        :key="row.nobtc"
                                        class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                                    >
                                        <td
                                            class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ row.nobtc }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3">
                                            <button
                                                type="button"
                                                class="font-semibold text-cyan-700 underline decoration-cyan-300 underline-offset-4 hover:text-cyan-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:text-cyan-300 dark:hover:text-cyan-100"
                                                @click="
                                                    openSupplierReturns(row)
                                                "
                                            >
                                                {{ row.id_sup }}
                                            </button>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ row.sup_namasupplier || "—" }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                                :class="statusClass(row.cheker)"
                                            >
                                                {{ row.cheker }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                                            {{ row.pic }}
                                        </td>
                                    </tr>
                                    <tr v-if="activeRows.length === 0">
                                        <td
                                            colspan="5"
                                            class="px-4 py-10 text-center text-gray-500"
                                        >
                                            Tidak ada antrian supplier yang cocok.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        v-show="activeTab === 'purchase-orders'"
                        id="logistics-panel-purchase-orders"
                        role="tabpanel"
                        aria-labelledby="logistics-tab-purchase-orders"
                        tabindex="0"
                        class="overflow-hidden rounded-xl border border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:border-gray-700"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1250px] text-sm">
                                <thead
                                    class="bg-cyan-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <tr>
                                        <th class="px-3 py-3">Antrian</th>
                                        <th class="px-3 py-3">Urut</th>
                                        <th class="px-3 py-3">PO</th>
                                        <th class="px-3 py-3">Tgl PO</th>
                                        <th class="px-3 py-3">Exp PO</th>
                                        <th class="px-3 py-3 text-right">Rp PO</th>
                                        <th class="px-3 py-3">No. BPB</th>
                                        <th class="px-3 py-3 text-right">Rp BPB</th>
                                        <th class="px-3 py-3">Kode Supplier</th>
                                        <th class="px-3 py-3">Supplier</th>
                                        <th class="px-3 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in activeRows"
                                        :key="`${row.po}-${row.no_bpb}-${index}`"
                                        class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                                    >
                                        <td class="whitespace-nowrap px-3 py-3">{{ row.antrian }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ row.urut }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 font-medium">{{ row.po }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ row.po_tgl }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ row.po_exp }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right">{{ formatNumber(row.rp_dpo) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ row.no_bpb || "—" }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right">{{ formatNumber(row.rp_bpb) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ row.id_sup }}</td>
                                        <td class="px-3 py-3">{{ row.sup_namasupplier }}</td>
                                        <td class="px-3 py-3">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                                :class="statusClass(row.recid)"
                                            >
                                                {{ row.recid || "—" }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="activeRows.length === 0">
                                        <td
                                            colspan="11"
                                            class="px-4 py-10 text-center text-gray-500"
                                        >
                                            Tidak ada purchase order yang cocok.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        v-show="activeTab === 'items'"
                        id="logistics-panel-items"
                        role="tabpanel"
                        aria-labelledby="logistics-tab-items"
                        tabindex="0"
                        class="overflow-hidden rounded-xl border border-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:border-gray-700"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1900px] text-xs">
                                <thead
                                    class="bg-cyan-50 text-left uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <tr>
                                        <th class="px-3 py-3">Status</th>
                                        <th class="px-3 py-3">Barkos</th>
                                        <th class="px-3 py-3">Supplier</th>
                                        <th class="px-3 py-3">PLU</th>
                                        <th class="px-3 py-3">Deskripsi</th>
                                        <th class="px-3 py-3">No. PO</th>
                                        <th class="px-3 py-3">No. BPB</th>
                                        <th class="px-3 py-3">Antrian</th>
                                        <th class="px-3 py-3">Urut PO</th>
                                        <th class="px-3 py-3 text-right">Acost</th>
                                        <th class="px-3 py-3 text-right">Harga Beli</th>
                                        <th class="px-3 py-3 text-right">Qty PO</th>
                                        <th class="px-3 py-3 text-right">Qty Faktur</th>
                                        <th class="px-3 py-3 text-right">Qty BPB</th>
                                        <th class="px-3 py-3 text-right">BPB Ctn</th>
                                        <th class="px-3 py-3 text-right">P%</th>
                                        <th class="px-3 py-3 text-right">DPP</th>
                                        <th class="px-3 py-3 text-right">Rp BPB</th>
                                        <th class="px-3 py-3 text-right">Gross</th>
                                        <th class="px-3 py-3 text-right">PPN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in activeRows"
                                        :key="`${row.nopo}-${row.no_bpb}-${row.prdcd}-${index}`"
                                        class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                                    >
                                        <td class="whitespace-nowrap px-3 py-2">
                                            <span
                                                class="inline-flex rounded-full px-2 py-1 text-[10px] font-bold"
                                                :class="statusClass(row.ket)"
                                            >
                                                {{ row.ket }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ row.barkos || "—" }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ row.id_sup }}</td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2 font-semibold"
                                            :class="
                                                row.produk === 'BARU'
                                                    ? 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100'
                                                    : 'text-cyan-800 dark:text-cyan-200'
                                            "
                                        >
                                            {{ row.prdcd }}
                                        </td>
                                        <td
                                            class="min-w-52 px-3 py-2"
                                            :class="
                                                row.produk === 'BARU'
                                                    ? 'bg-amber-50 font-semibold dark:bg-amber-950/50'
                                                    : ''
                                            "
                                        >
                                            {{ row.prd_deskripsipanjang }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ row.nopo }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ row.no_bpb || "—" }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ row.antrian }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">{{ row.urutpo }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.acost) }}</td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2 text-right"
                                            :class="
                                                Number(row.hrg_beli) > Number(row.acost)
                                                    ? 'bg-red-100 font-bold text-red-800 dark:bg-red-950 dark:text-red-200'
                                                    : ''
                                            "
                                        >
                                            {{ formatNumber(row.hrg_beli) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.tpod_qtypo) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.qty_inputpo) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.qtybpb) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.bpbctn) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatPercent(row.persen) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.dpp) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.rp_bpb) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.mstd_gross) }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">{{ formatNumber(row.mstd_ppnrph) }}</td>
                                    </tr>
                                    <tr v-if="activeRows.length === 0">
                                        <td
                                            colspan="20"
                                            class="px-4 py-10 text-center text-gray-500"
                                        >
                                            Tidak ada item penerimaan yang cocok.
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot
                                    v-if="itemRows.length > 0"
                                    class="border-t-2 border-cyan-200 bg-gray-900 text-right font-bold text-white dark:border-cyan-900"
                                >
                                    <tr>
                                        <td colspan="11" class="px-3 py-3 text-left">
                                            TOTAL
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.qtyPo) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.qtyInvoice) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.qtyBpb) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.bpbCtn) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatPercent(itemTotals.percent) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.dpp) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.bpb) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.gross) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3">{{ formatNumber(itemTotals.ppn) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>
                </section>
            </div>
        </div>

        <Modal
            :show="showSupplierModal"
            max-width="7xl"
            aria-labelledby="supplier-return-title"
            @close="closeSupplierModal"
        >
            <section class="space-y-4 p-5 dark:bg-gray-900 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-700 dark:text-cyan-300"
                        >
                            Retur Supplier
                        </p>
                        <h2
                            id="supplier-return-title"
                            class="mt-1 text-xl font-semibold text-gray-900 dark:text-white"
                        >
                            {{ selectedSupplier?.sup_namasupplier || "Detail Supplier" }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Kode {{ selectedSupplier?.id_sup || "—" }} · Stok
                            lokasi 02 yang masih tersedia.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="closeSupplierModal"
                    >
                        Tutup
                    </button>
                </div>

                <p
                    v-if="supplierReturnLoading"
                    role="status"
                    class="rounded-xl bg-cyan-50 px-4 py-8 text-center text-sm text-cyan-800 dark:bg-cyan-950 dark:text-cyan-100"
                >
                    Memuat detail retur supplier...
                </p>
                <p
                    v-else-if="supplierReturnError"
                    role="alert"
                    class="rounded-xl bg-red-50 px-4 py-5 text-sm text-red-800 dark:bg-red-950 dark:text-red-100"
                >
                    {{ supplierReturnError }}
                </p>
                <div
                    v-else
                    class="max-h-[65vh] overflow-auto rounded-xl border border-gray-200 dark:border-gray-700"
                >
                    <table class="w-full min-w-[900px] text-sm">
                        <thead
                            class="sticky top-0 bg-cyan-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        >
                            <tr>
                                <th class="px-3 py-3">PLU</th>
                                <th class="px-3 py-3">Deskripsi</th>
                                <th class="px-3 py-3">Kode Supplier</th>
                                <th class="px-3 py-3">Nama Supplier</th>
                                <th class="px-3 py-3 text-right">Stok</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in supplierReturnRows"
                                :key="row.plu"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                            >
                                <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ row.plu }}</td>
                                <td class="min-w-56 px-3 py-3">{{ row.desk }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ row.kodesupplier }}</td>
                                <td class="px-3 py-3">{{ row.namasupplier }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right">{{ formatNumber(row.stock) }}</td>
                                <td class="px-3 py-3">{{ row.status || "—" }}</td>
                                <td class="px-3 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="
                                            row.ket === 'BISA'
                                                ? statusClass('SELESAI')
                                                : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
                                        "
                                    >
                                        {{ row.ket }}
                                    </span>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    !supplierReturnLoading &&
                                    !supplierReturnError &&
                                    supplierReturnRows.length === 0
                                "
                            >
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-gray-500"
                                >
                                    Tidak ada stok retur untuk supplier ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </Modal>
    </AuthenticatedLayout>
</template>
