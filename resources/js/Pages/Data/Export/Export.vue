<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps({
    reportOptions: {
        type: Array,
        required: true,
    },
});

const isExporting = ref(false);
const selectedReport = ref(props.reportOptions[0]?.value ?? "");
const reportSearch = ref("");
const isReportDropdownOpen = ref(false);
const highlightedReportIndex = ref(0);
const downloadProgress = ref(null);
const downloadStatus = ref("");
const selectedReportLabel = computed(
    () =>
        props.reportOptions.find(
            (report) => report.value === selectedReport.value,
        )?.label ?? "",
);
const filteredReportOptions = computed(() => {
    const search = reportSearch.value.trim().toLowerCase();

    if (!search) {
        return props.reportOptions;
    }

    return props.reportOptions.filter(
        (report) =>
            report.label.toLowerCase().includes(search) ||
            report.value.toLowerCase().includes(search),
    );
});
const isPbReport = computed(() => selectedReport.value.startsWith("pb-"));
const requiresMember = computed(() =>
    ["exportmember-peritem", "promo-detail-mypoint"].includes(
        selectedReport.value,
    ),
);
const showPluFilter = computed(
    () =>
        !isPbReport.value ||
        ["pb-peritem", "pb-picking-pertanggal"].includes(
            selectedReport.value,
        ),
);

function openReportDropdown() {
    reportSearch.value = "";
    highlightedReportIndex.value = 0;
    isReportDropdownOpen.value = true;
}

function searchReports(event) {
    reportSearch.value = event.target.value;
    highlightedReportIndex.value = 0;
    isReportDropdownOpen.value = true;
}

function selectReport(reportValue) {
    selectedReport.value = reportValue;
    reportSearch.value = "";
    highlightedReportIndex.value = 0;
    isReportDropdownOpen.value = false;
}

function handleReportKeydown(event) {
    if (event.key === "ArrowDown") {
        event.preventDefault();
        isReportDropdownOpen.value = true;
        highlightedReportIndex.value = Math.min(
            highlightedReportIndex.value + 1,
            filteredReportOptions.value.length - 1,
        );
    } else if (event.key === "ArrowUp") {
        event.preventDefault();
        isReportDropdownOpen.value = true;
        highlightedReportIndex.value = Math.max(
            highlightedReportIndex.value - 1,
            0,
        );
    } else if (
        event.key === "Enter" &&
        isReportDropdownOpen.value &&
        filteredReportOptions.value.length > 0
    ) {
        event.preventDefault();
        selectReport(
            filteredReportOptions.value[highlightedReportIndex.value].value,
        );
    } else if (event.key === "Escape") {
        event.preventDefault();
        isReportDropdownOpen.value = false;
        reportSearch.value = "";
    }
}

function resetReportSelection() {
    selectReport(props.reportOptions[0]?.value ?? "");
}

const downloadExport = (event) => {
    if (reportSearch.value.trim() !== "") {
        downloadStatus.value =
            "Pilih jenis laporan dari daftar sebelum mengekspor.";
        return;
    }

    const formData = new FormData(event.currentTarget);
    const request = new XMLHttpRequest();

    isExporting.value = true;
    downloadProgress.value = null;
    downloadStatus.value = "Menyiapkan file...";

    request.open("POST", route("sales.export.download"));
    request.responseType = "blob";
    request.setRequestHeader("Accept", "application/json");

    request.addEventListener("progress", (progressEvent) => {
        if (progressEvent.lengthComputable) {
            downloadProgress.value = Math.round(
                (progressEvent.loaded / progressEvent.total) * 100,
            );
            downloadStatus.value = `Mengunduh file... ${downloadProgress.value}%`;
        } else {
            downloadStatus.value = "Mengunduh file...";
        }
    });

    request.addEventListener("load", async () => {
        isExporting.value = false;

        if (request.status < 200 || request.status >= 300) {
            const responseText = await request.response.text();
            let message = "File gagal dibuat. Periksa filter lalu coba lagi.";

            try {
                const responseData = JSON.parse(responseText);
                message =
                    Object.values(responseData.errors ?? {})
                        .flat()
                        .at(0) ??
                    responseData.message ??
                    message;
            } catch {
                // Keep the default message when the server returns non-JSON.
            }

            downloadProgress.value = null;
            downloadStatus.value = message;
            return;
        }

        const disposition = request.getResponseHeader("Content-Disposition");
        const fileName = disposition?.match(/filename="?([^";]+)"?/i)?.[1];
        const downloadUrl = URL.createObjectURL(request.response);
        const downloadLink = document.createElement("a");

        downloadLink.href = downloadUrl;
        downloadLink.download = fileName || "export-report.xlsx";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        downloadLink.remove();
        window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);

        downloadProgress.value = 100;
        downloadStatus.value = "Unduhan selesai (100%)";
    });

    request.addEventListener("error", () => {
        isExporting.value = false;
        downloadProgress.value = null;
        downloadStatus.value = "Koneksi terputus. Coba unduh kembali.";
    });

    request.send(formData);
};

const outletOptions = [
    { value: "1", label: "1 - Wholesaler" },
    { value: "2", label: "2 - Retailer" },
    { value: "3", label: "3 - HCO" },
    { value: "4", label: "4 - OMI" },
    { value: "5", label: "5 - Institusi" },
    { value: "6", label: "6 - Pribadi" },
    { value: "0", label: "0 - Karyawan" },
    { value: "7", label: "7 - TMI" },
];

const subOutletOptions = [
    { value: "2A", label: "2A - Rombong" },
    { value: "2B", label: "2B - Retail perumahan / jalan raya" },
    { value: "2C", label: "2C - Retail dalam pasar" },
    { value: "2D", label: "2D - Apotek / toko obat" },
    { value: "2E", label: "2E - Retailer online" },
    { value: "3A", label: "3A - Minimarket" },
    { value: "3B", label: "3B - Supermarket" },
    { value: "4A", label: "4A - Institusi / factory / SPBU" },
    { value: "4B", label: "4B - Residence" },
    { value: "4C", label: "4C - School" },
    { value: "4D", label: "4D - Hospital" },
    { value: "4E", label: "4E - Apartemen / hotel" },
];

const divisionOptions = [
    { value: "1", label: "1 - Food" },
    { value: "2", label: "2 - Non food" },
    { value: "3", label: "3 - General merchandising" },
    { value: "4", label: "4 - Perishable" },
    { value: "5", label: "5 - Counter & promotion" },
    { value: "6", label: "6 - Fast food" },
    { value: "7", label: "7 - I-Fashion" },
    { value: "8", label: "8 - I-Tech" },
    { value: "9", label: "9 - I-Tronic" },
];

const departmentOptions = [
    { value: "01", label: "01 - Breakfast food" },
    { value: "04", label: "04 - Beverages" },
    { value: "05", label: "05 - Basic food" },
    { value: "09", label: "09 - Snack" },
    { value: "14", label: "14 - Tobacco" },
    { value: "23", label: "23 - Electronic & home appliances" },
    { value: "31", label: "31 - Produce import" },
    { value: "40", label: "40 - Parcel & promotion" },
];
</script>

<template>
    <Head title="Data Export" />

    <AuthenticatedLayout>
        <div class="min-h-[calc(100dvh-4rem)] bg-gray-100 dark:bg-gray-950">
            <header
                class="border-b border-gray-200 bg-white px-4 py-2.5 dark:border-gray-800 dark:bg-gray-900 sm:px-6 lg:px-8"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <h1
                        class="text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Data Export
                    </h1>
                    <div
                        v-if="downloadStatus"
                        class="min-w-0 sm:w-72 sm:text-right"
                        aria-live="polite"
                    >
                        <p
                            role="status"
                            class="mb-1 text-xs text-gray-600 dark:text-gray-300"
                        >
                            {{ downloadStatus }}
                        </p>
                        <div
                            v-if="isExporting"
                            class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-600 transition-[width] duration-200 dark:bg-emerald-400"
                                :class="
                                    downloadProgress === null
                                        ? 'w-1/3 animate-pulse'
                                        : ''
                                "
                                :style="
                                    downloadProgress === null
                                        ? undefined
                                        : { width: `${downloadProgress}%` }
                                "
                            ></div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="w-full px-4 py-3 sm:px-6 lg:px-8">
                <form
                    class="grid gap-x-8 gap-y-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(280px,0.75fr)]"
                    method="POST"
                    :action="route('sales.export.download')"
                    @submit.prevent="downloadExport"
                    @reset="resetReportSelection"
                >
                    <input
                        type="hidden"
                        name="_token"
                        :value="$page.props.csrfToken"
                    />
                    <section
                        class="border-b border-gray-200 pb-4 dark:border-gray-800"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="text-xs font-bold text-emerald-700 dark:text-emerald-400"
                                >01</span
                            >
                            <h2
                                class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                            >
                                Jenis laporan
                            </h2>
                        </div>
                        <div class="relative">
                            <input
                                type="hidden"
                                name="laporan"
                                :value="selectedReport"
                            >
                            <label class="block">
                                <span class="sr-only">Cari jenis laporan</span>
                                <input
                                    type="text"
                                    required
                                    role="combobox"
                                    aria-label="Cari jenis laporan"
                                    aria-autocomplete="list"
                                    aria-haspopup="listbox"
                                    aria-controls="export-report-options"
                                    :aria-expanded="isReportDropdownOpen"
                                    :aria-activedescendant="
                                        isReportDropdownOpen &&
                                        filteredReportOptions.length > 0
                                            ? `export-report-option-${highlightedReportIndex}`
                                            : undefined
                                    "
                                    :value="
                                        isReportDropdownOpen
                                            ? reportSearch
                                            : selectedReportLabel
                                    "
                                    autocomplete="off"
                                    placeholder="Cari jenis laporan..."
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                                    @focus="openReportDropdown"
                                    @input="searchReports"
                                    @blur="isReportDropdownOpen = false"
                                    @keydown="handleReportKeydown"
                                />
                            </label>
                            <ul
                                v-if="isReportDropdownOpen"
                                id="export-report-options"
                                role="listbox"
                                aria-label="Jenis laporan"
                                class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-300 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                            >
                                <li
                                    v-for="(report, index) in filteredReportOptions"
                                    :id="`export-report-option-${index}`"
                                    :key="report.value"
                                    role="option"
                                    :aria-selected="
                                        report.value === selectedReport
                                    "
                                    class="cursor-pointer rounded px-2 py-1.5 text-sm text-gray-800 hover:bg-emerald-50 dark:text-gray-100 dark:hover:bg-gray-800"
                                    :class="
                                        index === highlightedReportIndex
                                            ? 'bg-emerald-50 dark:bg-gray-800'
                                            : ''
                                    "
                                    @mousedown.prevent="
                                        selectReport(report.value)
                                    "
                                    @mouseenter="
                                        highlightedReportIndex = index
                                    "
                                >
                                    {{ report.label }}
                                </li>
                                <li
                                    v-if="filteredReportOptions.length === 0"
                                    role="option"
                                    aria-disabled="true"
                                    class="px-2 py-2 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada jenis laporan yang cocok.
                                </li>
                            </ul>
                        </div>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <label v-if="showPluFilter" class="block">
                                <span class="sr-only">PLU</span>
                                <input
                                    name="plu"
                                    type="text"
                                    placeholder="PLU, pisahkan dengan koma"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                                />
                            </label>
                            <label v-if="!isPbReport" class="block">
                                <span class="sr-only">Member</span>
                                <input
                                    name="member"
                                    type="text"
                                    :required="requiresMember"
                                    placeholder="Member"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                                />
                            </label>
                            <label v-if="!isPbReport" class="block">
                                <span class="sr-only">Supplier</span>
                                <input
                                    name="supplier"
                                    type="text"
                                    placeholder="Supplier"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500"
                                />
                            </label>
                        </div>
                    </section>

                    <section
                        class="border-b border-gray-200 pb-4 dark:border-gray-800"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="text-xs font-bold text-emerald-700 dark:text-emerald-400"
                                >02</span
                            >
                            <h2
                                class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                            >
                                Periode
                            </h2>
                        </div>
                        <div class="space-y-2">
                            <label
                                class="grid grid-cols-[5.5rem_minmax(0,1fr)] items-center gap-2"
                            >
                                <span
                                    class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                >
                                    Tanggal awal
                                    <span class="text-rose-600">*</span>
                                </span>
                                <input
                                    required
                                    name="tglstart"
                                    type="date"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                />
                            </label>
                            <label
                                class="grid grid-cols-[5.5rem_minmax(0,1fr)] items-center gap-2"
                            >
                                <span
                                    class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                >
                                    Tanggal akhir
                                    <span class="text-rose-600">*</span>
                                </span>
                                <input
                                    required
                                    name="tglend"
                                    type="date"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                />
                            </label>
                        </div>
                    </section>

                    <section
                        v-if="!isPbReport"
                        class="border-b border-gray-200 pb-4 dark:border-gray-800 lg:col-span-2"
                    >
                        <div class="mb-2 flex items-center gap-2">
                            <span
                                class="text-xs font-bold text-emerald-700 dark:text-emerald-400"
                                >03</span
                            >
                            <h2
                                class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                            >
                                Cakupan data
                            </h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block">
                                <span
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300"
                                    >Divisi</span
                                >
                                <select
                                    name="divisi"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                >
                                    <option value="">Semua divisi</option>
                                    <option
                                        v-for="division in divisionOptions"
                                        :key="division.value"
                                        :value="division.value"
                                    >
                                        {{ division.label }}
                                    </option>
                                </select>
                            </label>
                            <label class="block">
                                <span
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300"
                                    >Departemen</span
                                >
                                <select
                                    name="departement"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                >
                                    <option value="">Semua departemen</option>
                                    <option
                                        v-for="department in departmentOptions"
                                        :key="department.value"
                                        :value="department.value"
                                    >
                                        {{ department.label }}
                                    </option>
                                </select>
                            </label>
                            <label class="block">
                                <span
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300"
                                    >Outlet</span
                                >
                                <select
                                    name="outlet"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                >
                                    <option value="">Semua outlet</option>
                                    <option
                                        v-for="outlet in outletOptions"
                                        :key="outlet.value"
                                        :value="outlet.value"
                                    >
                                        {{ outlet.label }}
                                    </option>
                                </select>
                            </label>
                            <label class="block">
                                <span
                                    class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300"
                                    >Sub outlet</span
                                >
                                <select
                                    name="sub_outlet"
                                    class="block h-9 w-full rounded-md border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                >
                                    <option value="">Semua sub outlet</option>
                                    <option
                                        v-for="subOutlet in subOutletOptions"
                                        :key="subOutlet.value"
                                        :value="subOutlet.value"
                                    >
                                        {{ subOutlet.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                        <div
                            class="mt-3 flex flex-col gap-2 sm:flex-row sm:gap-6"
                        >
                            <label
                                class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    name="larangan"
                                    type="checkbox"
                                    value="y"
                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900"
                                />
                                Exclude item larangan
                            </label>
                            <label
                                class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300"
                            >
                                <input
                                    name="esse_djarum"
                                    type="checkbox"
                                    value="y"
                                    class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900"
                                />
                                Exclude rokok tanpa Esse & Djarum
                            </label>
                        </div>
                    </section>

                    <div
                        class="flex flex-col gap-3 border-t border-gray-200 pt-3 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-end lg:col-span-2"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button
                                type="submit"
                                :disabled="isExporting"
                                :aria-busy="isExporting"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500 dark:focus:ring-offset-gray-950"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 2a.75.75 0 01.75.75v8.69l2.22-2.22a.75.75 0 111.06 1.06l-3.5 3.5a.75.75 0 01-1.06 0l-3.5-3.5a.75.75 0 111.06-1.06l2.22 2.22V2.75A.75.75 0 0110 2zM3.5 13.5a.75.75 0 01.75.75v1.25c0 .41.34.75.75.75h10c.41 0 .75-.34.75-.75v-1.25a.75.75 0 011.5 0v1.25A2.25 2.25 0 0115 17.75H5A2.25 2.25 0 012.75 15.5v-1.25a.75.75 0 01.75-.75z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                Export Excel
                            </button>
                            <button
                                type="reset"
                                class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-950"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </AuthenticatedLayout>
</template>
