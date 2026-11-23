<script setup>
import { computed, onMounted, onUnmounted } from "vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    rows: {
        type: Array,
        required: true,
    },
});

const code39Characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%";
const code39Encodings = [
    0x034, 0x121, 0x061, 0x160, 0x031, 0x130, 0x070, 0x025, 0x124, 0x064,
    0x109, 0x049, 0x148, 0x019, 0x118, 0x058, 0x00d, 0x10c, 0x04c, 0x01c,
    0x103, 0x043, 0x142, 0x013, 0x112, 0x052, 0x007, 0x106, 0x046, 0x016,
    0x181, 0x0c1, 0x1c0, 0x091, 0x190, 0x0d0, 0x085, 0x184, 0x0c4, 0x0a8,
    0x0a2, 0x08a, 0x02a,
];

const encodeCode39 = (value) => {
    const text = String(value ?? "").trim().toUpperCase();

    if (!text) {
        return null;
    }

    const bars = [];
    let position = 0;
    const content = `*${text}*`;

    for (let characterIndex = 0; characterIndex < content.length; characterIndex++) {
        const character = content[characterIndex];
        const symbolIndex = code39Characters.indexOf(character);
        const encoding = character === "*" ? 0x094 : code39Encodings[symbolIndex];

        if (encoding === undefined) {
            return null;
        }

        for (let elementIndex = 0; elementIndex < 9; elementIndex++) {
            const wide = (encoding & (1 << (8 - elementIndex))) !== 0;
            const width = wide ? 3 : 1;

            if (elementIndex % 2 === 0) {
                bars.push({ x: position, width });
            }

            position += width;
        }

        if (characterIndex < content.length - 1) {
            position += 1;
        }
    }

    return { bars, width: position };
};

const previewRows = computed(() =>
    props.rows.map((row) => ({
        ...row,
        barcode: encodeCode39(row.lokasi),
    })),
);
const firstRow = computed(() => previewRows.value[0] ?? {});
const pageTitle = computed(() => `Preview lokasi PB ${firstRow.value.pb ?? ""}`);

const formatNumber = (value) =>
    value === null || value === undefined || value === ""
        ? "-"
        : new Intl.NumberFormat("id-ID", {
              maximumFractionDigits: 2,
          }).format(Number(value));

const printPage = () => window.print();
const goBack = () => window.history.back();

let printStyles;

onMounted(() => {
    printStyles = document.createElement("style");
    printStyles.textContent = `
        @page { size: 80mm auto; margin: 3mm; }
        @media print {
            html, body {
                width: 80mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .pb-preview-shell {
                width: 74mm !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .pb-preview-toolbar { display: none !important; }
            .pb-preview-card {
                border: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .pb-preview-item {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    `;
    document.head.appendChild(printStyles);
});

onUnmounted(() => {
    printStyles?.remove();
});
</script>

<template>
    <Head :title="pageTitle" />

    <main class="min-h-screen bg-gray-100 px-3 py-5 text-gray-900 dark:bg-gray-950 dark:text-gray-100 print:bg-white print:p-0 print:text-black">
        <div class="pb-preview-shell mx-auto w-full max-w-[74mm]">
            <div class="pb-preview-toolbar mb-4 flex justify-between gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    @click="goBack"
                >
                    Kembali
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                    @click="printPage"
                >
                    Cetak 80 mm
                </button>
            </div>

            <article class="pb-preview-card space-y-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900 print:space-y-2 print:rounded-none print:bg-white print:text-black">
                <header class="space-y-1 border-b border-dashed border-gray-400 pb-2 text-center">
                    <h1 class="text-sm font-bold uppercase">
                        List Barang Picking
                    </h1>
                    <p class="text-xs">
                        PB: {{ firstRow.pb }} ({{ firstRow.obi_notrans }})
                    </p>
                    <p class="text-xs">
                        Nama: {{ firstRow.cus_namamember || "-" }}
                    </p>
                </header>

                <section
                    v-for="(row, index) in previewRows"
                    :key="`${row.pb}-${row.obi_notrans}-${row.trjd_prdcd}-${row.lokasi}-${index}`"
                    class="pb-preview-item space-y-1 border-b border-dashed border-gray-400 pb-3 last:border-0"
                >
                    <p class="text-center text-xs font-semibold leading-tight">
                        {{ row.prd_deskripsipanjang || "-" }}
                    </p>
                    <div class="grid grid-cols-2 gap-1 text-[11px]">
                        <p class="break-words font-bold">
                            Lokasi: {{ row.lokasi || "Belum ada lokasi" }}
                        </p>
                        <p class="text-right">PLU: {{ row.trjd_prdcd }}</p>
                        <p>
                            Order: {{ formatNumber(row.obi_qtyorder) }} · Real:
                            {{ formatNumber(row.obi_qtyrealisasi) }}
                        </p>
                        <p class="text-right">
                            Packing: {{ formatNumber(row.pobi_qty) }}
                        </p>
                    </div>
                    <svg
                        v-if="row.barcode"
                        class="mx-auto block h-10 w-full"
                        :viewBox="`0 0 ${row.barcode.width} 48`"
                        role="img"
                        :aria-label="`Barcode lokasi ${row.lokasi}`"
                        preserveAspectRatio="none"
                    >
                        <rect
                            v-for="(bar, barIndex) in row.barcode.bars"
                            :key="barIndex"
                            :x="bar.x"
                            y="0"
                            :width="bar.width"
                            height="48"
                            fill="currentColor"
                        />
                    </svg>
                    <p
                        v-else-if="row.lokasi"
                        class="text-center text-[10px] text-rose-700"
                    >
                        Lokasi tidak dapat dibuat sebagai barcode Code 39.
                    </p>
                    <p v-if="row.lokasi" class="text-center font-mono text-[10px]">
                        *{{ row.lokasi }}*
                    </p>
                </section>

                <p
                    v-if="previewRows.length === 0"
                    class="py-8 text-center text-sm text-gray-500"
                >
                    Tidak ada item untuk PB ini.
                </p>
            </article>
        </div>
    </main>
</template>
