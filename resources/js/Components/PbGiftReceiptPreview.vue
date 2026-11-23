<script setup>
import { computed, onMounted, onUnmounted } from "vue";

const props = defineProps({
    receipt: {
        type: Object,
        required: true,
    },
});

const receiptDate = computed(() => {
    if (!props.receipt.created_at) {
        return "-";
    }

    const date = new Date(props.receipt.created_at);

    if (Number.isNaN(date.getTime())) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    }).format(date);
});

const printPage = () => window.print();

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
            dialog:has(.pb-gift-print-content) {
                position: static !important;
                display: block !important;
                min-width: 0 !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                border: 0 !important;
                background: transparent !important;
            }
            dialog:has(.pb-gift-print-content) > div {
                position: static !important;
                inset: auto !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            dialog:has(.pb-gift-print-content) > div > div {
                position: static !important;
                width: auto !important;
                max-width: none !important;
                margin: 0 !important;
                overflow: visible !important;
                transform: none !important;
                background: transparent !important;
                box-shadow: none !important;
            }
            body * { visibility: hidden !important; }
            .pb-gift-print-content,
            .pb-gift-print-content * { visibility: visible !important; }
            .pb-gift-print-content {
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                width: 74mm !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .pb-gift-print-toolbar { display: none !important; }
            .pb-gift-print-card {
                border: 2px solid #000 !important;
                padding: 3mm !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #000 !important;
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
    <div class="mx-auto w-full max-w-[74mm]">
        <div class="pb-gift-print-toolbar mb-4 flex justify-end">
            <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                @click="printPage"
            >
                Cetak 80 mm
            </button>
        </div>

        <article class="pb-gift-print-content pb-gift-print-card space-y-3 border-2 border-black bg-white p-3 text-gray-900 shadow-sm">
            <header class="space-y-2 border-b-2 border-solid border-gray-700 pb-2 text-center">
                <h1 class="text-sm font-bold uppercase">Hadiah Belanja Ambil di Toko</h1>
                <p class="text-xs font-semibold">No. PB: {{ receipt.pb }}</p>
            </header>

            <section class="space-y-1 border-b border-solid border-gray-400 pb-2 text-xs">
                <p><strong>Kode member:</strong> {{ receipt.kdmm }}</p>
                <p><strong>Nama member:</strong> {{ receipt.namamm || "-" }}</p>
                <p><strong>No. transaksi:</strong> {{ receipt.notrans }}</p>
                <p><strong>Hadiah:</strong> {{ receipt.gift_name }}</p>
                <p><strong>PLU / Qty:</strong> {{ receipt.plu }} / {{ receipt.qty }}</p>
            </section>

            <section class="grid grid-cols-2 gap-3 pt-1 text-center text-xs">
                <div>
                    <div class="mb-2 h-20 border-b border-solid border-gray-700"></div>
                    <strong>MENERIMA</strong>
                </div>
                <div>
                    <div class="mb-2 h-20 border-b border-solid border-gray-700"></div>
                    <strong>MENYERAHKAN</strong>
                </div>
            </section>

            <footer class="space-y-1 border-t border-solid border-gray-400 pt-2 text-center text-xs">
                <p>BANJARBARU, {{ receiptDate }}</p>
                <p>Penginput: {{ receipt.penginput }}</p>
            </footer>
        </article>
    </div>
</template>
