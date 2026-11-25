<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { copyTextToClipboard } from "@/clipboard";

const props = defineProps({
    codes: {
        type: Array,
        default: () => [],
    },
    promoDescriptions: {
        type: Array,
        default: () => [],
    },
    recipients: {
        type: Array,
        default: () => [],
    },
    registeredMembers: {
        type: Array,
        default: () => [],
    },
    promoDetails: {
        type: Array,
        default: () => [],
    },
});

const allTabs = [
    {
        id: "descriptions",
        label: "Keterangan Promo",
        rows: props.promoDescriptions,
    },
    {
        id: "recipients",
        label: "Member Sudah Mendapat",
        rows: props.recipients,
    },
    {
        id: "registered",
        label: "List Member Terdaftar",
        rows: props.registeredMembers,
    },
    {
        id: "details",
        label: "Detail Promo",
        rows: props.promoDetails,
    },
];

const tabs = allTabs.filter((tab) => tab.rows.length > 0);
const activeTab = ref(tabs[0]?.id ?? "");
const searchInput = ref(props.codes.join(", "));
const promotionSearch = ref("");
const promotionSuggestions = ref([]);
const promotionSuggestionsVisible = ref(false);
const promotionSuggestionsLoading = ref(false);
const promotionSuggestionsError = ref("");
const activePromotionSuggestionIndex = ref(-1);
const copyingTable = ref(false);
const copyMessage = ref("");
const copyMessageType = ref("success");
let promotionSuggestionTimer = null;
let promotionSuggestionRequest = null;

watch(
    () => props.codes,
    (codes) => {
        searchInput.value = codes.join(", ");
    },
);

const activeRows = computed(() => {
    const tab = tabs.find((item) => item.id === activeTab.value);
    return tab?.rows ?? [];
});

const copyActiveTable = async () => {
    const panel = document.getElementById(`promo-panel-${activeTab.value}`);
    const table = panel?.querySelector("table");

    if (!table) {
        copyMessage.value = "Tabel aktif tidak ditemukan.";
        copyMessageType.value = "error";

        return;
    }

    const rows = Array.from(table.querySelectorAll("tr")).map((row) =>
        Array.from(row.querySelectorAll("th, td"))
            .map((cell) =>
                (cell.innerText ?? cell.textContent ?? "")
                    .replace(/\s+/g, " ")
                    .trim(),
            )
            .join("\t"),
    );

    copyingTable.value = true;

    try {
        await copyTextToClipboard(rows.join("\n"));
        copyMessage.value = `${new Intl.NumberFormat("id-ID").format(Math.max(rows.length - 1, 0))} baris tabel berhasil disalin.`;
        copyMessageType.value = "success";
    } catch {
        copyMessage.value =
            "Tidak dapat menyalin tabel. Periksa izin clipboard browser.";
        copyMessageType.value = "error";
    } finally {
        copyingTable.value = false;
    }
};

const loadPromotionSuggestions = async (search) => {
    promotionSuggestionRequest?.abort();

    const request = new AbortController();
    promotionSuggestionRequest = request;
    promotionSuggestionsLoading.value = true;
    promotionSuggestionsError.value = "";

    try {
        const response = await fetch(
            route("info.promo.suggestions", { search }),
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                signal: request.signal,
            },
        );
        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message ?? "Saran promo tidak dapat dimuat.",
            );
        }

        if (!Array.isArray(result.suggestions)) {
            throw new Error("Format saran promo tidak valid.");
        }

        if (promotionSuggestionRequest === request) {
            promotionSuggestions.value = result.suggestions;
        }
    } catch (error) {
        if (error.name !== "AbortError") {
            promotionSuggestionsError.value =
                error instanceof Error
                    ? error.message
                    : "Terjadi kesalahan saat mencari promo.";
        }
    } finally {
        if (promotionSuggestionRequest === request) {
            promotionSuggestionsLoading.value = false;
            promotionSuggestionRequest = null;
        }
    }
};

const onPromotionSearchInput = () => {
    clearTimeout(promotionSuggestionTimer);
    promotionSuggestionRequest?.abort();
    activePromotionSuggestionIndex.value = -1;
    promotionSuggestionsError.value = "";
    promotionSuggestions.value = [];

    const search = promotionSearch.value.trim();

    if (search.length < 2) {
        promotionSuggestions.value = [];
        promotionSuggestionsVisible.value = false;
        promotionSuggestionsLoading.value = false;

        return;
    }

    promotionSuggestionsVisible.value = true;
    promotionSuggestionsLoading.value = true;
    promotionSuggestionTimer = setTimeout(() => {
        void loadPromotionSuggestions(search);
    }, 250);
};

const onPromotionSearchFocus = () => {
    if (promotionSearch.value.trim().length >= 2) {
        promotionSuggestionsVisible.value = true;
    }
};

const selectPromotionSuggestion = (suggestion) => {
    clearTimeout(promotionSuggestionTimer);
    promotionSuggestionRequest?.abort();
    promotionSuggestionRequest = null;
    promotionSuggestionsLoading.value = false;
    promotionSuggestionsError.value = "";
    promotionSuggestionsVisible.value = false;
    promotionSuggestions.value = [];
    activePromotionSuggestionIndex.value = -1;
    promotionSearch.value = "";

    const selectedCodes = searchInput.value
        .split(/[,;\s]+/)
        .map((code) => code.trim().toUpperCase())
        .filter(Boolean);
    const promoCode = String(suggestion.promo_code).trim().toUpperCase();

    if (!selectedCodes.includes(promoCode)) {
        selectedCodes.push(promoCode);
    }

    searchInput.value = selectedCodes.join(", ");
    router.get(
        route("info.promo"),
        { kode: searchInput.value },
        { preserveScroll: true },
    );
};

const onPromotionSearchKeydown = (event) => {
    if (event.key === "Escape") {
        promotionSuggestionsVisible.value = false;

        return;
    }

    if (
        event.key === "ArrowDown" &&
        promotionSuggestions.value.length > 0
    ) {
        event.preventDefault();
        promotionSuggestionsVisible.value = true;
        activePromotionSuggestionIndex.value = Math.min(
            activePromotionSuggestionIndex.value + 1,
            promotionSuggestions.value.length - 1,
        );

        return;
    }

    if (
        event.key === "ArrowUp" &&
        promotionSuggestions.value.length > 0
    ) {
        event.preventDefault();
        activePromotionSuggestionIndex.value = Math.max(
            activePromotionSuggestionIndex.value - 1,
            0,
        );

        return;
    }

    if (
        event.key === "Enter" &&
        promotionSuggestionsVisible.value &&
        activePromotionSuggestionIndex.value >= 0
    ) {
        event.preventDefault();
        selectPromotionSuggestion(
            promotionSuggestions.value[activePromotionSuggestionIndex.value],
        );
    }
};

onUnmounted(() => {
    clearTimeout(promotionSuggestionTimer);
    promotionSuggestionRequest?.abort();
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

const promotionClass = (type) => {
    return type === "GIFT"
        ? "border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200"
        : "border-cyan-200 bg-cyan-100 text-cyan-800 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-200";
};
</script>

<template>
    <Head title="Info - Detail Promo" />

    <AuthenticatedLayout>
        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1800px] space-y-6">
                <header>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-cyan-700 dark:text-cyan-300">
                            Info
                        </p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                            Detail Promo
                        </h1>
                    </div>
                </header>

                <form
                    method="get"
                    :action="route('info.promo')"
                    class="grid items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-[minmax(16rem,1fr)_minmax(16rem,1fr)_auto]"
                >
                    <div class="relative">
                        <label
                            for="promotion-search"
                            class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300"
                        >
                            CARI KODE / NAMA PROMO
                        </label>
                        <input
                            id="promotion-search"
                            v-model="promotionSearch"
                            type="text"
                            maxlength="80"
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-controls="promotion-search-suggestions"
                            :aria-expanded="promotionSuggestionsVisible"
                            :aria-activedescendant="
                                activePromotionSuggestionIndex >= 0
                                    ? `promotion-search-option-${activePromotionSuggestionIndex}`
                                    : undefined
                            "
                            placeholder="Ketik kode atau nama promo"
                            class="promo-search-input"
                            @input="onPromotionSearchInput"
                            @focus="onPromotionSearchFocus"
                            @blur="promotionSuggestionsVisible = false"
                            @keydown="onPromotionSearchKeydown"
                        />
                        <ul
                            v-if="promotionSuggestionsVisible"
                            id="promotion-search-suggestions"
                            class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-auto rounded-xl border border-cyan-300 bg-white shadow-xl dark:border-cyan-800 dark:bg-gray-900"
                            role="listbox"
                        >
                            <li
                                v-if="promotionSuggestionsLoading"
                                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                                role="status"
                            >
                                Mencari kode atau nama promo...
                            </li>
                            <li
                                v-else-if="promotionSuggestionsError"
                                class="px-3 py-2 text-sm text-rose-700 dark:text-rose-300"
                                role="alert"
                            >
                                {{ promotionSuggestionsError }}
                            </li>
                            <li
                                v-else-if="promotionSuggestions.length === 0"
                                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                            >
                                Tidak ada promo yang cocok.
                            </li>
                            <template v-else>
                                <li
                                    v-for="(suggestion, index) in promotionSuggestions"
                                    :id="`promotion-search-option-${index}`"
                                    :key="`${suggestion.promotion_type}-${suggestion.promo_code}`"
                                    role="option"
                                    :aria-selected="
                                        activePromotionSuggestionIndex === index
                                    "
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-3 px-3 py-2 text-left text-sm hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-none dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                        :class="
                                            activePromotionSuggestionIndex === index
                                                ? 'bg-cyan-50 dark:bg-gray-800'
                                                : ''
                                        "
                                        @pointerdown.prevent="
                                            selectPromotionSuggestion(suggestion)
                                        "
                                        @mouseenter="
                                            activePromotionSuggestionIndex = index
                                        "
                                    >
                                        <span
                                            class="mt-0.5 inline-flex shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                            :class="
                                                promotionClass(
                                                    suggestion.promotion_type,
                                                )
                                            "
                                        >
                                            {{ suggestion.promotion_type }}
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block font-bold text-cyan-800 dark:text-cyan-200">
                                                {{ suggestion.promo_code }}
                                            </span>
                                            <span class="block truncate text-xs text-gray-600 dark:text-gray-300">
                                                {{ suggestion.promo_name }}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Pilih hasil untuk langsung menampilkan promo.
                        </p>
                    </div>
                    <div>
                        <label
                            for="promotion-codes"
                            class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300"
                        >
                            KODE PROMO YANG DIPILIH
                        </label>
                        <input
                            id="promotion-codes"
                            v-model="searchInput"
                            name="kode"
                            type="text"
                            maxlength="500"
                            placeholder="Contoh: CBWG5, GCI04"
                            class="promo-search-input"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Bisa isi beberapa kode, pisahkan dengan koma.
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-900/10 transition hover:bg-cyan-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                    >
                        Cari Promo
                    </button>
                </form>

                <section
                    v-if="tabs.length > 0"
                    class="space-y-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6"
                >
                    <div
                        class="promo-tab-list flex w-full gap-2 overflow-x-auto rounded-2xl border border-cyan-200/70 bg-gray-50 p-2 dark:border-cyan-900/70 dark:bg-gray-950"
                        role="tablist"
                        aria-label="Jenis data promo"
                    >
                        <button
                            v-for="tab in tabs"
                            :id="`promo-tab-${tab.id}`"
                            :key="tab.id"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.id"
                            :aria-controls="`promo-panel-${tab.id}`"
                            class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-600 hover:text-cyan-800 dark:text-gray-300 dark:hover:text-cyan-200"
                            :class="activeTab === tab.id ? 'promo-tab-active text-cyan-800 dark:text-cyan-200' : ''"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                            <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                {{ tab.rows.length }}
                            </span>
                        </button>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p
                            v-if="copyMessage"
                            class="text-sm"
                            :class="
                                copyMessageType === 'success'
                                    ? 'text-emerald-700 dark:text-emerald-300'
                                    : 'text-rose-700 dark:text-rose-300'
                            "
                            role="status"
                            aria-live="polite"
                        >
                            {{ copyMessage }}
                        </p>
                        <div class="flex flex-wrap gap-2 sm:ml-auto">
                            <button
                                type="button"
                                class="inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-300 px-4 py-2 text-sm font-bold text-cyan-800 transition hover:bg-cyan-50 disabled:cursor-wait disabled:opacity-60 dark:border-cyan-800 dark:text-cyan-200 dark:hover:bg-cyan-950"
                                :disabled="copyingTable"
                                @click="copyActiveTable"
                            >
                                {{ copyingTable ? "Menyalin..." : "Salin seluruh tabel" }}
                            </button>
                            <a
                                :href="
                                    route('info.promo.export', {
                                        tab: activeTab,
                                        kode: props.codes.join(','),
                                    })
                                "
                                class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900"
                            >
                                Export Excel
                            </a>
                        </div>
                    </div>

                    <section
                        v-show="activeTab === 'descriptions'"
                        id="promo-panel-descriptions"
                        role="tabpanel"
                        aria-labelledby="promo-tab-descriptions"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1100px] text-sm">
                                <thead class="bg-cyan-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-3">Jenis</th>
                                        <th class="px-4 py-3">Kode</th>
                                        <th class="px-4 py-3">Nama Promo</th>
                                        <th class="px-4 py-3">Mulai</th>
                                        <th class="px-4 py-3">Selesai</th>
                                        <th class="px-4 py-3">Mekanisme</th>
                                        <th class="px-4 py-3 text-right">Nilai / Hadiah</th>
                                        <th class="px-4 py-3 text-right">Minimum</th>
                                        <th class="px-4 py-3">Dibuat Oleh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in activeRows"
                                        :key="`${row.promo_code}-${index}`"
                                        class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                                    >
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold" :class="promotionClass(row.promotion_type)">
                                                {{ row.promotion_type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-bold text-cyan-700 dark:text-cyan-300">{{ row.promo_code }}</td>
                                        <td class="px-4 py-3">{{ row.promo_name }}</td>
                                        <td class="px-4 py-3">{{ row.start_date }}</td>
                                        <td class="px-4 py-3">{{ row.end_date }}</td>
                                        <td class="px-4 py-3">{{ row.mechanism || "—" }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.value) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.minimum) }}</td>
                                        <td class="px-4 py-3">{{ row.created_by || "—" }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        v-show="activeTab === 'recipients'"
                        id="promo-panel-recipients"
                        role="tabpanel"
                        aria-labelledby="promo-tab-recipients"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1250px] text-sm">
                                <thead class="bg-cyan-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-3">Jenis</th>
                                        <th class="px-4 py-3">Member</th>
                                        <th class="px-4 py-3">Nama</th>
                                        <th class="px-4 py-3">Kode Promo</th>
                                        <th class="px-4 py-3">No. Transaksi</th>
                                        <th class="px-4 py-3">No. PB</th>
                                        <th class="px-4 py-3">Tanggal</th>
                                        <th class="px-4 py-3">Hadiah</th>
                                        <th class="px-4 py-3 text-right">Nilai</th>
                                        <th class="px-4 py-3">Kasir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in activeRows"
                                        :key="`${row.member_code}-${row.receipt_number}-${index}`"
                                        class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                                    >
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold" :class="promotionClass(row.promotion_type)">
                                                {{ row.promotion_type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold">{{ row.member_code }}</td>
                                        <td class="px-4 py-3">{{ row.member_name || "—" }}</td>
                                        <td class="px-4 py-3">{{ row.promo_code }}</td>
                                        <td class="px-4 py-3">{{ row.receipt_number }}</td>
                                        <td class="px-4 py-3">{{ row.purchase_number || "—" }}</td>
                                        <td class="px-4 py-3">{{ row.transaction_date }}</td>
                                        <td class="px-4 py-3">{{ row.reward }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.reward_value) }}</td>
                                        <td class="px-4 py-3">{{ row.created_by || "—" }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        v-show="activeTab === 'registered'"
                        id="promo-panel-registered"
                        role="tabpanel"
                        aria-labelledby="promo-tab-registered"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px] text-sm">
                                <thead class="bg-cyan-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-3">Jenis</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Member</th>
                                        <th class="px-4 py-3">Nama</th>
                                        <th class="px-4 py-3">Kode Promo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in activeRows"
                                        :key="`${row.member_code}-${row.promo_code}-${index}`"
                                        class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                                    >
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold" :class="promotionClass(row.promotion_type)">
                                                {{ row.promotion_type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                                {{ row.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold">{{ row.member_code }}</td>
                                        <td class="px-4 py-3">{{ row.member_name || "—" }}</td>
                                        <td class="px-4 py-3">{{ row.promo_code }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        v-show="activeTab === 'details'"
                        id="promo-panel-details"
                        role="tabpanel"
                        aria-labelledby="promo-tab-details"
                        class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1150px] text-sm">
                                <thead class="bg-cyan-50 text-left text-xs uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-3">Jenis</th>
                                        <th class="px-4 py-3">PLU</th>
                                        <th class="px-4 py-3">Deskripsi</th>
                                        <th class="px-4 py-3 text-right">Nilai</th>
                                        <th class="px-4 py-3">Kelipatan</th>
                                        <th class="px-4 py-3 text-right">Minimum</th>
                                        <th class="px-4 py-3 text-right">Maksimum</th>
                                        <th class="px-4 py-3 text-right">Maks. Member / Hari</th>
                                        <th class="px-4 py-3 text-right">Alokasi</th>
                                        <th class="px-4 py-3">Kode Promo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, index) in activeRows"
                                        :key="`${row.promo_code}-${row.plu}-${index}`"
                                        class="border-t border-gray-100 odd:bg-white even:bg-gray-50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/60"
                                    >
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold" :class="promotionClass(row.promotion_type)">
                                                {{ row.promotion_type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold">{{ row.plu }}</td>
                                        <td class="px-4 py-3">{{ row.description || "—" }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.value) }}</td>
                                        <td class="px-4 py-3">{{ row.multiplier || "—" }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.minimum) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.maximum) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.maximum_per_member) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(row.allocation) }}</td>
                                        <td class="px-4 py-3">{{ row.promo_code }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </section>

                <section
                    v-else
                    class="rounded-2xl border border-dashed border-gray-300 px-6 py-16 text-center dark:border-gray-700"
                >
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan-100 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m16 16 4 4" />
                        </svg>
                    </div>
                    <h2 class="mt-4 text-lg font-bold text-gray-900 dark:text-white">
                        {{ props.codes.length ? "Data promo tidak ditemukan" : "Masukkan kode promo" }}
                    </h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ props.codes.length ? "Periksa kembali kode promo atau pisahkan beberapa kode dengan koma." : "Kode dapat dipisahkan dengan koma, contoh CBNF2,GBI95." }}
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
