<script setup>
import { computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    action: {
        type: String,
        required: true,
    },
    mode: {
        type: String,
        required: true,
    },
    record: {
        type: Object,
        required: true,
    },
    loyaltyRecords: {
        type: Array,
        default: () => [],
    },
    selectedProgram: {
        type: String,
        default: null,
    },
    returnFilters: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    tglpb: props.record.tglpb ?? "",
    nopb: props.record.nopb ?? "",
    notrans: props.record.notrans ?? "",
    kdmm: props.record.kdmm ?? "",
    penginput: "",
    program: props.selectedProgram ?? "",
    tgl_kirim: props.record.tgl_kirim ?? "",
    ttd: "",
    request: props.record.request ?? "",
    mode: props.mode,
    return_report: props.returnFilters.report ?? "pb-proses",
    return_tglstart: props.returnFilters.tglstart ?? "",
    return_tglend: props.returnFilters.tglend ?? "",
    return_recid: props.returnFilters.recid ?? "all",
    return_ekspedisi: props.returnFilters.ekspedisi ?? "",
    return_pin: props.returnFilters.pin ?? "",
    return_kdmm: props.returnFilters.kdmm ?? "",
    return_nopb: props.returnFilters.nopb ?? "",
    return_request: props.returnFilters.request ?? false,
    return_search: props.returnFilters.search ?? "",
    return_per_page: props.returnFilters.per_page ?? "",
    return_page: props.returnFilters.page ?? "",
});

const titles = {
    gift: "Input Hadiah Ambil di SPI",
    loyalty: "Update Hadiah Loyalty",
    request: props.mode === "edit" ? "Edit Request Kirim" : "Request Kirim",
};

const title = computed(() => titles[props.action] ?? "Aksi PB");
const currentLoyalty = computed(
    () =>
        props.loyaltyRecords.find(
            (record) => record.program === form.program,
        ) ?? null,
);
const submitUrl = computed(
    () =>
        ({
            gift: route("pb.actions.gift.store"),
            loyalty: route("pb.actions.loyalty.update"),
            request: route("pb.actions.request.save"),
        })[props.action],
);
const backUrl = computed(() =>
    route("pb.reports.show", props.returnFilters),
);

function submitForm() {
    form.post(submitUrl.value, { preserveScroll: true });
}
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    {{ title }}
                </h1>
                <Link
                    :href="backUrl"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Kembali ke PB
                </Link>
            </div>
        </template>

        <div class="px-4 py-8 sm:px-6 lg:px-8">
            <section
                class="mx-auto max-w-3xl space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-7"
            >
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Data transaksi diambil ulang dari database aktif sebelum disimpan.
                    </p>
                </div>

                <form
                    class="pb-neon-panel space-y-5"
                    :class="`pb-neon-panel--${action}`"
                    @submit.prevent="submitForm"
                >
                    <input type="hidden" name="mode" v-model="form.mode" />
                    <input type="hidden" name="tglpb" v-model="form.tglpb" />
                    <input type="hidden" name="nopb" v-model="form.nopb" />
                    <input type="hidden" name="notrans" v-model="form.notrans" />
                    <input type="hidden" name="kdmm" v-model="form.kdmm" />
                    <input type="hidden" name="return_report" v-model="form.return_report" />
                    <input type="hidden" name="return_tglstart" v-model="form.return_tglstart" />
                    <input type="hidden" name="return_tglend" v-model="form.return_tglend" />
                    <input type="hidden" name="return_recid" v-model="form.return_recid" />
                    <input type="hidden" name="return_ekspedisi" v-model="form.return_ekspedisi" />
                    <input type="hidden" name="return_pin" v-model="form.return_pin" />
                    <input type="hidden" name="return_kdmm" v-model="form.return_kdmm" />
                    <input type="hidden" name="return_nopb" v-model="form.return_nopb" />
                    <input
                        type="hidden"
                        name="return_request"
                        :value="form.return_request ? '1' : '0'"
                    />
                    <input type="hidden" name="return_search" v-model="form.return_search" />
                    <input type="hidden" name="return_per_page" v-model="form.return_per_page" />
                    <input type="hidden" name="return_page" v-model="form.return_page" />

                    <template v-if="action === 'gift' || action === 'request'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>PB</span>
                                <input
                                    :value="record.pb"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Tanggal PB</span>
                                <input
                                    :value="record.tglpb"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Kode Member</span>
                                <input
                                    :value="record.kdmm"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Nama Member</span>
                                <input
                                    :value="record.namamm"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                        </div>
                    </template>

                    <template v-if="action === 'gift'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>No. Transaksi</span>
                                <input
                                    :value="record.notrans"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Hadiah</span>
                                <input
                                    :value="record.gift_name"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>PLU</span>
                                <input
                                    :value="record.plu"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Kuantitas</span>
                                <input
                                    :value="record.qty"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200 sm:col-span-2">
                                <span>Kode Promo</span>
                                <input
                                    :value="record.kodepromo"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200 sm:col-span-2">
                                <span>Penginput</span>
                                <select
                                    v-model="form.penginput"
                                    required
                                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option value="" disabled>Pilih penginput</option>
                                    <option value="HAINANI">HAINANI</option>
                                    <option value="AMINAH">AMINAH</option>
                                    <option value="HUSAINI">HUSAINI</option>
                                    <option value="FAUZAN">FAUZAN</option>
                                    <option value="ALEXANDER">ALEXANDER</option>
                                </select>
                                <p v-if="form.errors.penginput" class="text-sm text-rose-600">
                                    {{ form.errors.penginput }}
                                </p>
                            </label>
                        </div>
                    </template>

                    <template v-else-if="action === 'loyalty'">
                        <div class="space-y-3">
                            <p class="text-sm text-gray-700 dark:text-gray-200">
                                Member: <strong>{{ record.kdmm }}</strong>
                                <span v-if="record.namamm">— {{ record.namamm }}</span>
                            </p>
                            <label class="block space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Program Loyalty</span>
                                <select
                                    v-model="form.program"
                                    required
                                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option
                                        v-for="loyalty in loyaltyRecords"
                                        :key="loyalty.program"
                                        :value="loyalty.program"
                                    >
                                        {{ loyalty.program }} — {{ loyalty.gift }} ({{ loyalty.period }})
                                    </option>
                                </select>
                                <p v-if="form.errors.program" class="text-sm text-rose-600">
                                    {{ form.errors.program }}
                                </p>
                            </label>
                            <div
                                v-if="currentLoyalty"
                                class="grid gap-3 sm:grid-cols-2"
                            >
                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    Hadiah: {{ currentLoyalty.gift }}
                                </p>
                                <p class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    Periode: {{ currentLoyalty.period }}
                                </p>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Tanggal Kirim</span>
                                <input
                                    v-model="form.tgl_kirim"
                                    type="date"
                                    required
                                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                                <p v-if="form.errors.tgl_kirim" class="text-sm text-rose-600">
                                    {{ form.errors.tgl_kirim }}
                                </p>
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Nama Pengirim / Tanda Tangan</span>
                                <input
                                    v-model="form.ttd"
                                    type="text"
                                    maxlength="100"
                                    required
                                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                                <p v-if="form.errors.ttd" class="text-sm text-rose-600">
                                    {{ form.errors.ttd }}
                                </p>
                            </label>
                        </div>
                    </template>

                    <template v-else-if="action === 'request'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>No. Transaksi</span>
                                <input
                                    :value="record.notrans"
                                    readonly
                                    class="block w-full rounded-lg border-gray-300 bg-gray-100 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <span>Tanggal Kirim</span>
                                <input
                                    v-model="form.tgl_kirim"
                                    type="date"
                                    required
                                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                                <p v-if="form.errors.tgl_kirim" class="text-sm text-rose-600">
                                    {{ form.errors.tgl_kirim }}
                                </p>
                            </label>
                            <label class="space-y-1 text-sm font-medium text-gray-700 dark:text-gray-200 sm:col-span-2">
                                <span>Pilih Rit</span>
                                <select
                                    v-model="form.request"
                                    required
                                    class="block w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                >
                                    <option value="" disabled>Pilih rit pengiriman</option>
                                    <option value="RIT 1 PAGI">RIT 1 PAGI</option>
                                    <option value="RIT 2 SIANG">RIT 2 SIANG</option>
                                    <option value="RIT 3 SORE">RIT 3 SORE</option>
                                </select>
                                <p v-if="form.errors.request" class="text-sm text-rose-600">
                                    {{ form.errors.request }}
                                </p>
                            </label>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                form.processing
                                    ? "Menyimpan..."
                                    : action === "gift"
                                      ? "Simpan Hadiah"
                                      : action === "loyalty"
                                        ? "Update Loyalty"
                                        : mode === "edit"
                                          ? "Update Request"
                                          : "Tambah Request"
                            }}
                        </button>
                        <p
                            v-if="form.errors.tglpb || form.errors.nopb || form.errors.notrans || form.errors.kdmm"
                            role="alert"
                            class="text-sm text-rose-600"
                        >
                            Data PB tidak lengkap atau sudah berubah. Kembali ke laporan lalu buka ulang form.
                        </p>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
