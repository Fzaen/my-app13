<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import Modal from "@/Components/Modal.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { copyTextToClipboard } from "@/clipboard";

const props = defineProps({
    search: {
        type: String,
        required: true,
    },
    descriptionSearch: {
        type: String,
        required: true,
    },
    pluSearch: {
        type: String,
        required: true,
    },
    product: {
        type: Object,
        default: null,
    },
    searchResults: {
        type: Array,
        required: true,
    },
    panelOptions: {
        type: Array,
        required: true,
    },
    message: {
        type: String,
        default: null,
    },
});

const descriptionQuery = ref(props.descriptionSearch);
const pluQuery = ref(props.pluSearch);
const suggestions = ref([]);
const suggestionsVisible = ref(false);
const suggestionsLoading = ref(false);
const suggestionsError = ref(null);
const activeSuggestionIndex = ref(-1);
let suggestionRequest = null;
let suggestionTimer = null;

const summaryData = ref({
    cashbacks: [],
    gifts: [],
    priceMargin: [],
});
const copyToast = ref(null);
let copyToastTimer = null;
const showImagePreview = ref(false);
const imageFallbackIndex = ref(0);
const productImageUnavailable = ref(false);
const productImageCodes = computed(() => {
    const codes = [props.product?.plu, props.product?.plu_mcg]
        .filter((value) => value !== null && value !== undefined)
        .map((value) => String(value).trim())
        .filter((value) => value !== "");

    return [...new Set(codes)];
});
const productImageUrl = computed(() => {
    const code = productImageCodes.value[imageFallbackIndex.value];

    return code
        ? `https://img.stokpoin.com/images/products/${encodeURIComponent(code)}.png`
        : null;
});
const hasPrice = (value) =>
    value !== null &&
    value !== undefined &&
    String(value).trim() !== "" &&
    Number.isFinite(Number(value)) &&
    Number(value) > 0;
const selectedPriceMargin = computed(
    () =>
        summaryData.value.priceMargin.find(
            (row) => row.plu === props.product?.plu,
        ) ?? null,
);
const priceHeader = computed(() => ({
    averageCost:
        selectedPriceMargin.value?.average_cost ?? props.product?.average_cost,
    regularPrice:
        selectedPriceMargin.value?.regular_price ??
        props.product?.selling_price,
    promoPrice: selectedPriceMargin.value?.promo_price,
    cashback: selectedPriceMargin.value?.cashback_per_minimum_sale,
    netPrice: selectedPriceMargin.value?.net_price,
    marginPercent: selectedPriceMargin.value?.margin_percent,
}));
const priceMarginColumns = computed(() => {
    const rows = summaryData.value.priceMargin;
    const columns = [
        { key: "plu", label: "PLU" },
        { key: "unit", label: "Unit" },
        { key: "fraction", label: "Fraction" },
        { key: "minimum_sale", label: "Min" },
        { key: "average_cost", label: "Avg Cost" },
        { key: "regular_price", label: "Reg Price" },
    ];

    const hasPromotion = rows.some((row) => hasPrice(row.promo_price));

    if (hasPromotion) {
        columns.push({ key: "promo_price", label: "Promo" });
    }

    if (rows.some((row) => hasPrice(row.cashback_per_minimum_sale))) {
        columns.push({ key: "cashback_per_minimum_sale", label: "CB" });
    }

    if (hasPromotion) {
        columns.push(
            { key: "promo_starts_at", label: "Start" },
            { key: "promo_ends_at", label: "End" },
        );
    }

    columns.push(
        { key: "margin_per_unit", label: "Margin Rp" },
        { key: "margin_percent", label: "Margin %" },
        { key: "net_price", label: "Net Price" },
    );

    return columns;
});
const priceMarginColumnWidths = {
    plu: "8%",
    unit: "5%",
    fraction: "7%",
    minimum_sale: "4%",
    average_cost: "9%",
    regular_price: "9%",
    promo_price: "7%",
    cashback_per_minimum_sale: "6%",
    promo_starts_at: "8%",
    promo_ends_at: "8%",
    margin_per_unit: "8%",
    margin_percent: "7%",
    net_price: "9%",
};
const priceMarginColumnStyle = (column) => ({
    width: priceMarginColumnWidths[column],
});
const promoColumnStyle = (column) => {
    if (column === "mechanism") {
        return { width: "22%" };
    }

    if (column === "promotion_name" || column === "gift_description") {
        return { width: "14%" };
    }

    return {};
};
const summaryLoading = ref(false);
const summaryError = ref(null);
let summaryRequest = null;
const productDetailsSearch = ref("");

const otherPanelOptions = props.panelOptions.filter(
    (panel) => !["cashbacks", "gifts", "price-margin"].includes(panel.value),
);
const purchaseOrderPanel = props.panelOptions.find(
    (panel) => panel.value === "purchase-orders",
);
const promoSections = computed(() =>
    [
        { key: "cashbacks", label: "Cashback aktif" },
        { key: "gifts", label: "Gift aktif" },
    ].filter((section) => summaryData.value[section.key].length > 0),
);

watch(
    () => props.descriptionSearch,
    (value) => {
        descriptionQuery.value = value;
    },
);
watch(
    () => props.pluSearch,
    (value) => {
        pluQuery.value = value;
    },
);
const productFieldGroups = [
    {
        title: "Identitas produk",
        fields: [
            ["plu_mcg", "PLU MCG"],
            ["short_description", "Deskripsi singkat"],
            ["description", "Deskripsi lengkap"],
            ["unit", "Satuan"],
            ["fraction", "Fraction"],
            ["tag", "Tag"],
        ],
    },
    {
        title: "Klasifikasi",
        fields: [
            ["division_code", "Kode divisi"],
            ["division_name", "Divisi"],
            ["department_code", "Kode departemen"],
            ["department_name", "Departemen"],
            ["category_code", "Kode kategori"],
            ["category_name", "Kategori"],
        ],
    },
    {
        title: "Informasi supplier",
        fields: [
            ["product_supplier_code", "Supplier master"],
            ["supplier_code", "Kode supplier"],
            ["supplier_name", "Nama supplier"],
        ],
    },
    {
        title: "Harga dan biaya",
        fields: [
            ["average_cost", "Average cost"],
            ["last_cost", "Last cost"],
            ["minimum_sale", "Minimum jual"],
            ["selling_price", "Harga jual"],
            ["selling_price_2", "Harga jual 2"],
            ["selling_price_3", "Harga jual 3"],
            ["selling_price_updated_at", "Tanggal update harga"],
        ],
    },
    {
        title: "Status produk",
        fields: [
            ["record_status", "Record ID"],
            ["activation_status", "Aktivasi"],
            ["igr_flag", "Flag IGR"],
            ["tax_flag", "Flag pajak"],
            ["tax_category", "Kategori pajak"],
            ["modified_by", "Diubah oleh"],
            ["modified_at", "Tanggal perubahan"],
        ],
    },
];

const hasProductValue = (value) =>
    value !== null && value !== undefined && String(value).trim() !== "";

const totalProductFieldCount = computed(
    () =>
        productFieldGroups
            .flatMap((group) => group.fields)
            .filter(([key]) => hasProductValue(props.product?.[key])).length,
);

const productGroups = computed(() => {
    const query = productDetailsSearch.value.trim().toLocaleLowerCase("id-ID");

    return productFieldGroups
        .map((group) => ({
            ...group,
            fields: group.fields
                .map(([key, label]) => ({
                    key,
                    label,
                    value: props.product?.[key],
                }))
                .filter(
                    (field) =>
                        hasProductValue(field.value) &&
                        (!query ||
                            `${field.label} ${field.value}`
                                .toLocaleLowerCase("id-ID")
                                .includes(query)),
                ),
        }))
        .filter((group) => group.fields.length > 0);
});

const visibleProductFieldCount = computed(() =>
    productGroups.value.reduce(
        (count, group) => count + group.fields.length,
        0,
    ),
);

const stockMetricDefinitions = [
    { key: "stock", label: "STOCK ALL" },
    { key: "icc_quantity", label: "BPB HJK ICC" },
    {
        key: "open_order_quantity",
        label: "OUTS TRX",
        totalKey: "open_order_count",
        totalUnit: "PB",
    },
    { key: "real_stock", label: "STOCK REAL", emphasized: true },
];
const hasNonZeroStockValue = (value) =>
    value !== null &&
    value !== undefined &&
    value !== "" &&
    Number.isFinite(Number(value)) &&
    Number(value) !== 0;

const panelButtonColors = [
    "border-sky-200 bg-sky-50 text-sky-800 hover:bg-sky-100 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200 dark:hover:bg-sky-900",
    "border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900",
    "border-violet-200 bg-violet-50 text-violet-800 hover:bg-violet-100 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200 dark:hover:bg-violet-900",
    "border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200 dark:hover:bg-amber-900",
    "border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200 dark:hover:bg-rose-900",
];
const numericColumns =
    /(price|cashback|amount|quantity|cost|margin|fraction|stock|sale|allocation|sequence|percent|count)/i;
const monetaryColumns =
    /(price|cashback|amount|cost|margin|sales|gross|revenue|nominal|discount|rph)/i;
const percentageColumns = /(percent|percentage)/i;

const activePanel = ref(null);
const panelRows = ref([]);
const panelSearch = ref("");
const visibleSalesSeries = ref({
    sales: true,
    margin: true,
    members: true,
});
const panelLoading = ref(false);
const panelError = ref(null);
let panelRequest = null;
const panelResultCache = new Map();
const panelCacheLifetime = 30_000;
const isCompactPanel = computed(
    () =>
        ["barcodes", "slp"].includes(activePanel.value?.value) &&
        panelRows.value.length <= 10,
);
const panelIsLarge = computed(
    () =>
        panelRows.value.length > 10 ||
        (!isCompactPanel.value && columnsFor(panelRows.value).length > 8),
);
const panelSearchVisible = computed(
    () => panelRows.value.length > 10 || columnsFor(panelRows.value).length > 6,
);
const panelDialogStyle = computed(() => {
    const columnCount = columnsFor(panelRows.value).length;
    const width = panelIsLarge.value
        ? 95
        : Math.min(75, Math.max(50, 42 + columnCount * 4));

    return {
        width: `${width}vw`,
        maxWidth: "95vw",
        maxHeight: "95dvh",
    };
});
const sortStates = ref({
    stock: { column: null, direction: "asc" },
    priceMargin: { column: null, direction: "asc" },
    cashbacks: { column: null, direction: "asc" },
    gifts: { column: null, direction: "asc" },
    panel: { column: null, direction: "asc" },
});

const formatNumber = (value) =>
    new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const showCopyToast = (message, type = "success", target = "") => {
    clearTimeout(copyToastTimer);
    copyToast.value = { message, type, target };
    const duration =
        type === "success" && ["plu", "description"].includes(target)
            ? 500
            : 2500;
    copyToastTimer = setTimeout(() => {
        copyToast.value = null;
        copyToastTimer = null;
    }, duration);
};

const stockParts = (quantity) => {
    const totalPcs = Number(quantity ?? 0);
    const fraction = Number(props.product?.fraction ?? 0);

    if (
        !Number.isFinite(totalPcs) ||
        !Number.isFinite(fraction) ||
        fraction <= 0
    ) {
        return {
            cartons: null,
            pieces: null,
            totalPcs,
        };
    }

    const cartons = Math.trunc(totalPcs / fraction);

    return {
        cartons,
        pieces: totalPcs - cartons * fraction,
        totalPcs,
    };
};

const stockRows = computed(() =>
    stockMetricDefinitions
        .filter((row) => {
            if (row.key === "icc_quantity") {
                return hasNonZeroStockValue(props.product?.icc_quantity);
            }

            if (row.key === "open_order_quantity") {
                return (
                    hasNonZeroStockValue(props.product?.open_order_quantity) ||
                    hasNonZeroStockValue(props.product?.open_order_count)
                );
            }

            return true;
        })
        .map((row) => ({
            ...row,
            parts: stockParts(props.product?.[row.key]),
        })),
);
const adjustmentStockRows = computed(() =>
    [
        { key: "return_stock", label: "Retur" },
        { key: "damaged_stock", label: "Rusak" },
    ]
        .filter((row) => hasNonZeroStockValue(props.product?.[row.key]))
        .map((row) => ({
            ...row,
            parts: stockParts(props.product?.[row.key]),
        })),
);

const displayValue = (value, column = "") => {
    if (value === null || value === undefined || value === "") {
        return "—";
    }

    if (activePanel.value?.value === "slp" && column === "id") {
        return String(value);
    }

    if (
        typeof value === "string" &&
        /(date|_at|_dt|tgl|starts|ends|expires)/i.test(column)
    ) {
        return formatShortDate(value);
    }

    if (
        typeof value === "number" ||
        (numericColumns.test(column) &&
            typeof value === "string" &&
            Number.isFinite(Number(value)))
    ) {
        return monetaryColumns.test(column) && !percentageColumns.test(column)
            ? formatPrice(value)
            : formatNumber(value);
    }

    return value;
};

const formatWholeNumber = (value) =>
    hasProductValue(value)
        ? new Intl.NumberFormat("id-ID", {
              maximumFractionDigits: 0,
          }).format(Number(value))
        : "—";

const formatPrice = (value) => formatWholeNumber(value);

const formatCartons = (value) =>
    hasProductValue(value)
        ? new Intl.NumberFormat("id-ID", {
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
          }).format(Number(value))
        : "—";

const formatChartMoney = (value) => {
    const amount = Number(value) || 0;
    const absoluteAmount = Math.abs(amount);

    if (absoluteAmount >= 1_000_000) {
        return `${formatWholeNumber(amount / 1_000_000)} jt`;
    }

    if (absoluteAmount >= 1_000) {
        return `${formatWholeNumber(amount / 1_000)} rb`;
    }

    return formatWholeNumber(amount);
};

const monthlySalesChart = computed(() => {
    if (panelRows.value.length === 0) {
        return null;
    }

    const width = 1100;
    const height = 390;
    const plotLeft = 120;
    const plotRight = 1030;
    const plotTop = 64;
    const plotBottom = 310;
    const plotWidth = plotRight - plotLeft;
    const plotHeight = plotBottom - plotTop;
    const domainFor = (values) => {
        const minimum = Math.min(0, ...values);
        const maximum = Math.max(0, ...values);
        const padding = Math.max(1, (maximum - minimum) * 0.1);

        return {
            minimum: minimum < 0 ? minimum - padding : 0,
            maximum: maximum > 0 ? maximum + padding : 1,
        };
    };
    const salesDomain = domainFor(
        panelRows.value.map((row) => Number(row.sales) || 0),
    );
    const marginDomain = domainFor(
        panelRows.value.map((row) => Number(row.margin) || 0),
    );
    const membersDomain = domainFor(
        panelRows.value.map((row) => Number(row.members) || 0),
    );
    const yFor = (value, domain) =>
        plotBottom -
        ((Number(value) - domain.minimum) /
            (domain.maximum - domain.minimum)) *
            plotHeight;
    const points = panelRows.value.map((row, index) => {
        const x =
            panelRows.value.length === 1
                ? plotLeft + plotWidth / 2
                : plotLeft + (index * plotWidth) / (panelRows.value.length - 1);
        const seriesPoint = (value, domain, label, labelPosition) => {
            const y = yFor(Number(value) || 0, domain);

            return {
                x,
                y,
                labelY:
                    labelPosition === "below"
                        ? Math.min(plotBottom - 20, y + 7)
                        : Math.max(plotTop + 2, y - 25),
                label,
            };
        };

        return {
            month: row.mon,
            sales: seriesPoint(
                row.sales,
                salesDomain,
                formatChartMoney(row.sales),
                "above",
            ),
            margin: seriesPoint(
                row.margin,
                marginDomain,
                formatChartMoney(row.margin),
                "below",
            ),
            members: seriesPoint(
                row.members,
                membersDomain,
                formatWholeNumber(row.members),
                "above",
            ),
        };
    });
    const pathFor = (series) =>
        points
            .map(
                (point, index) =>
                    `${index === 0 ? "M" : "L"} ${point[series].x} ${point[series].y}`,
            )
            .join(" ");
    const salesPath = pathFor("sales");
    const firstPoint = points[0];
    const lastPoint = points[points.length - 1];
    const salesBaseline = yFor(0, salesDomain);
    const gridLines = Array.from({ length: 5 }, (_, index) => {
        const fraction = index / 4;
        const valueAt = (domain) =>
            domain.minimum + (domain.maximum - domain.minimum) * (1 - fraction);

        return {
            y: plotTop + plotHeight * fraction,
            sales: formatChartMoney(valueAt(salesDomain)),
            margin: formatChartMoney(valueAt(marginDomain)),
            members: formatWholeNumber(valueAt(membersDomain)),
        };
    });

    return {
        width,
        height,
        plotLeft,
        plotRight,
        plotTop,
        plotBottom,
        gridLines,
        points,
        salesPath,
        salesBaseline,
        marginPath: pathFor("margin"),
        membersPath: pathFor("members"),
        salesAreaPath: `${salesPath} L ${lastPoint.sales.x} ${salesBaseline} L ${firstPoint.sales.x} ${salesBaseline} Z`,
    };
});

const marginTextClass = (value) => {
    if (!hasProductValue(value)) {
        return "text-gray-700 dark:text-gray-300";
    }

    const margin = Number(value);

    if (margin < 0) {
        return "text-rose-700 dark:text-rose-300";
    }

    if (margin < 3) {
        return "text-amber-700 dark:text-amber-300";
    }

    return "text-emerald-700 dark:text-emerald-300";
};

const formatShortDate = (value) => {
    const dateParts = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (!dateParts) {
        return value;
    }

    const [, year, month, day] = dateParts;
    const monthName = new Intl.DateTimeFormat("en-US", {
        month: "short",
        timeZone: "UTC",
    })
        .format(new Date(Date.UTC(Number(year), Number(month) - 1, Number(day))))
        .toUpperCase();

    return `${day}-${monthName}-${year.slice(-2)}`;
};

const displayPriceMarginValue = (value, column) => {
    if (column === "promo_starts_at" || column === "promo_ends_at") {
        return hasProductValue(value) ? formatShortDate(value) : "—";
    }

    if (column === "margin_percent") {
        return hasProductValue(value) ? `${formatNumber(value)}%` : "—";
    }

    return displayValue(value, column);
};

const displayProductValue = (value, key) => {
    if (
        /(average_cost|last_cost|selling_price)/.test(key) &&
        Number.isFinite(Number(value))
    ) {
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(Number(value));
    }

    if (/(updated_at|modified_at)$/.test(key) && typeof value === "string") {
        return formatShortDate(value);
    }

    return displayValue(value, key);
};

const columnsFor = (rows) => (rows.length > 0 ? Object.keys(rows[0]) : []);

const toggleSort = (table, column) => {
    const current = sortStates.value[table];
    const direction =
        current.column === column && current.direction === "asc"
            ? "desc"
            : "asc";

    sortStates.value = {
        ...sortStates.value,
        [table]: { column, direction },
    };
};

const sortedRows = (
    rows,
    table,
    valueForRow = (row, column) => row[column],
) => {
    const { column, direction } = sortStates.value[table];

    if (!column) {
        return rows;
    }

    const multiplier = direction === "asc" ? 1 : -1;

    return [...rows].sort((left, right) => {
        const leftValue = valueForRow(left, column);
        const rightValue = valueForRow(right, column);

        if (leftValue === null || leftValue === undefined || leftValue === "") {
            return rightValue === null ||
                rightValue === undefined ||
                rightValue === ""
                ? 0
                : 1;
        }

        if (
            rightValue === null ||
            rightValue === undefined ||
            rightValue === ""
        ) {
            return -1;
        }

        if (/(date|_at|_dt|tgl|expires|starts|ends)/i.test(column)) {
            const leftDate = Date.parse(String(leftValue).replace(" ", "T"));
            const rightDate = Date.parse(String(rightValue).replace(" ", "T"));

            if (Number.isFinite(leftDate) && Number.isFinite(rightDate)) {
                return (leftDate - rightDate) * multiplier;
            }
        }

        const leftNumber = Number(leftValue);
        const rightNumber = Number(rightValue);

        if (
            (typeof leftValue === "number" ||
                String(leftValue).trim() !== "") &&
            (typeof rightValue === "number" ||
                String(rightValue).trim() !== "") &&
            Number.isFinite(leftNumber) &&
            Number.isFinite(rightNumber)
        ) {
            return (leftNumber - rightNumber) * multiplier;
        }

        return (
            String(leftValue).localeCompare(String(rightValue), "id", {
                numeric: true,
                sensitivity: "base",
            }) * multiplier
        );
    });
};

const stockSortValue = (row, column) => {
    if (column === "cartons") {
        return row.parts.cartons;
    }

    if (column === "pieces") {
        return row.parts.pieces;
    }

    if (column === "total") {
        return row.totalKey ? props.product[row.totalKey] : row.parts.totalPcs;
    }

    return row.label;
};

const sortDirection = (table, column) =>
    sortStates.value[table].column === column
        ? sortStates.value[table].direction === "asc"
            ? "ascending"
            : "descending"
        : "none";

const sortIndicator = (table, column) => {
    const state = sortStates.value[table];

    if (state.column !== column) {
        return "↕";
    }

    return state.direction === "asc" ? "↑" : "↓";
};

const compactColumnLabels = {
    promotion_code: "Promo",
    promotion_name: "Promo Name",
    promotion_type: "Promo Type",
    description: "Desc",
    gift_description: "Desc",
    minimum_quantity: "Min Qty",
    maximum_quantity: "Max Qty",
    quantity: "Qty",
    gift_quantity: "Gift Qty",
    minimum_product_amount: "Min Product",
    minimum_receipt_amount: "Min Receipt",
    maximum_receipt_amount: "Max Receipt",
    minimum_amount: "Min Amount",
    item_cashback: "Item CB",
    receipt_cashback: "Receipt CB",
    starts_at: "Start",
    ends_at: "End",
    regular_member: "Regular",
    retailer_member: "Retailer",
    silver_member: "Silver",
    gold_one_member: "Gold 1",
    platinum_member: "Platinum",
};
const humanizeColumn = (column) =>
    compactColumnLabels[column] ??
    column
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const copyProductPlu = async () => {
    const plu = String(props.product?.plu ?? "");

    if (!plu) {
        return;
    }

    try {
        await copyTextToClipboard(plu);
        showCopyToast("PLU berhasil disalin.", "success", "plu");
    } catch {
        showCopyToast(
            "Tidak dapat menyalin PLU. Periksa izin clipboard browser.",
            "error",
            "plu",
        );
    }
};

const copyProductDescription = async () => {
    const description = String(props.product?.description ?? "");

    if (!description) {
        return;
    }

    try {
        await copyTextToClipboard(description);
        showCopyToast("Nama panjang berhasil disalin.", "success", "description");
    } catch {
        showCopyToast(
            "Tidak dapat menyalin nama panjang. Periksa izin clipboard browser.",
            "error",
            "description",
        );
    }
};

const filteredPanelRows = computed(() => {
    const term = panelSearch.value.trim().toLocaleLowerCase("id-ID");

    if (!term) {
        return panelRows.value;
    }

    return panelRows.value.filter((row) =>
        Object.entries(row).some(([column, value]) =>
            `${humanizeColumn(column)} ${displayValue(value, column)}`
                .toLocaleLowerCase("id-ID")
                .includes(term),
        ),
    );
});

const copyPanelRows = async () => {
    const columns = columnsFor(panelRows.value);
    const values = [
        columns.map(humanizeColumn),
        ...panelRows.value.map((row) =>
            columns.map((column) => displayValue(row[column], column)),
        ),
    ];
    const text = values.map((row) => row.join("\t")).join("\n");

    try {
        await copyTextToClipboard(text);
        showCopyToast(
            `${new Intl.NumberFormat("id-ID").format(panelRows.value.length)} baris tabel berhasil disalin.`,
            "success",
            "panel",
        );
    } catch {
        showCopyToast(
            "Tidak dapat menyalin tabel. Periksa izin clipboard browser.",
            "error",
            "panel",
        );
    }
};

const advanceProductImageFallback = () => {
    if (imageFallbackIndex.value < productImageCodes.value.length - 1) {
        imageFallbackIndex.value += 1;

        return;
    }

    productImageUnavailable.value = true;
};

const submitUnifiedSearch = () => {
    clearTimeout(suggestionTimer);
    suggestionRequest?.abort();
    suggestionsVisible.value = false;

    if (descriptionQuery.value.trim() !== "") {
        router.get(route("plu.info"), {
            search: descriptionQuery.value.trim(),
        });

        return;
    }

    router.get(route("plu.info"), {
        plu: pluQuery.value.trim(),
    });
};

const selectSuggestion = (item) => {
    clearTimeout(suggestionTimer);
    suggestionRequest?.abort();
    suggestionsVisible.value = false;
    suggestions.value = [];
    router.get(route("plu.info"), { plu: item.plu });
};

const loadSuggestions = async (query) => {
    suggestionRequest?.abort();

    const request = new AbortController();
    suggestionRequest = request;
    suggestionsLoading.value = true;
    suggestionsError.value = null;

    try {
        const response = await fetch(
            route("plu.info.suggestions", { search: query }),
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
                result.message ?? "Saran nama barang tidak dapat dimuat.",
            );
        }

        if (!Array.isArray(result.suggestions)) {
            throw new Error("Format saran nama barang tidak valid.");
        }

        if (suggestionRequest === request) {
            suggestions.value = result.suggestions;
        }
    } catch (error) {
        if (error.name !== "AbortError") {
            suggestionsError.value =
                error instanceof Error
                    ? error.message
                    : "Terjadi kesalahan saat mencari nama barang.";
        }
    } finally {
        if (suggestionRequest === request) {
            suggestionsLoading.value = false;
            suggestionRequest = null;
        }
    }
};

const onDescriptionInput = () => {
    clearTimeout(suggestionTimer);
    suggestionRequest?.abort();
    activeSuggestionIndex.value = -1;
    suggestionsError.value = null;
    pluQuery.value = "";

    const query = descriptionQuery.value.trim();

    if (query.length < 2) {
        suggestions.value = [];
        suggestionsVisible.value = false;
        suggestionsLoading.value = false;

        return;
    }

    suggestionsVisible.value = true;
    suggestionTimer = setTimeout(() => {
        void loadSuggestions(query);
    }, 250);
};

const onPluInput = () => {
    clearTimeout(suggestionTimer);
    suggestionRequest?.abort();
    suggestions.value = [];
    suggestionsVisible.value = false;
    suggestionsLoading.value = false;
    descriptionQuery.value = "";
    suggestionsError.value = null;
};

const onDescriptionFocus = () => {
    if (descriptionQuery.value.trim().length >= 2) {
        suggestionsVisible.value = true;
    }
};

const onDescriptionKeydown = (event) => {
    if (event.key === "Escape") {
        suggestionsVisible.value = false;

        return;
    }

    if (event.key === "ArrowDown" && suggestions.value.length > 0) {
        event.preventDefault();
        suggestionsVisible.value = true;
        activeSuggestionIndex.value = Math.min(
            activeSuggestionIndex.value + 1,
            suggestions.value.length - 1,
        );

        return;
    }

    if (event.key === "ArrowUp" && suggestions.value.length > 0) {
        event.preventDefault();
        activeSuggestionIndex.value = Math.max(
            activeSuggestionIndex.value - 1,
            0,
        );

        return;
    }

    if (
        event.key === "Enter" &&
        suggestionsVisible.value &&
        activeSuggestionIndex.value >= 0
    ) {
        event.preventDefault();
        selectSuggestion(suggestions.value[activeSuggestionIndex.value]);
    }
};

const loadSummary = async () => {
    summaryRequest?.abort();
    summaryData.value = { cashbacks: [], gifts: [], priceMargin: [] };
    summaryError.value = null;

    const plu = props.product?.plu;

    if (!plu) {
        summaryLoading.value = false;

        return;
    }

    const request = new AbortController();
    summaryRequest = request;
    summaryLoading.value = true;

    try {
        const response = await fetch(route("plu.info.summary", { plu }), {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            signal: request.signal,
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(
                result.message ?? "Ringkasan promosi tidak dapat dimuat.",
            );
        }

        if (
            !Array.isArray(result.cashbacks) ||
            !Array.isArray(result.gifts) ||
            !Array.isArray(result.priceMargin)
        ) {
            throw new Error("Format ringkasan promosi tidak valid.");
        }

        if (summaryRequest === request) {
            summaryData.value = {
                cashbacks: result.cashbacks,
                gifts: result.gifts,
                priceMargin: result.priceMargin,
            };
        }
    } catch (error) {
        if (error.name !== "AbortError") {
            summaryError.value =
                error instanceof Error
                    ? error.message
                    : "Terjadi kesalahan saat memuat ringkasan promosi.";
        }
    } finally {
        if (summaryRequest === request) {
            summaryLoading.value = false;
            summaryRequest = null;
        }
    }
};

watch(
    () => props.product?.plu,
    () => {
        clearTimeout(copyToastTimer);
        copyToastTimer = null;
        copyToast.value = null;
        imageFallbackIndex.value = 0;
        productImageUnavailable.value = false;
        showImagePreview.value = false;
        void loadSummary();
    },
    { immediate: true },
);

const openPanel = async (panel) => {
    panelRequest?.abort();
    panelRequest = null;
    activePanel.value = panel;
    panelRows.value = [];
    panelSearch.value = "";
    panelError.value = null;
    visibleSalesSeries.value = {
        sales: true,
        margin: true,
        members: true,
    };

    const cacheKey = `${props.product.plu}:${panel.value}`;
    const cachedPanel = panelResultCache.get(cacheKey);

    if (cachedPanel && Date.now() - cachedPanel.cachedAt < panelCacheLifetime) {
        panelRows.value = cachedPanel.rows;
        panelLoading.value = false;

        return;
    }

    panelResultCache.delete(cacheKey);

    const request = new AbortController();
    panelRequest = request;
    panelLoading.value = true;

    try {
        const response = await fetch(
            route("plu.info.panel", {
                plu: props.product.plu,
                panel: panel.value,
            }),
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
            throw new Error(result.message ?? "Data panel tidak dapat dimuat.");
        }

        if (!Array.isArray(result.rows)) {
            throw new Error("Format data panel tidak valid.");
        }

        panelRows.value = result.rows;
        panelResultCache.set(cacheKey, {
            rows: result.rows,
            cachedAt: Date.now(),
        });

        for (const [key, value] of panelResultCache) {
            if (Date.now() - value.cachedAt >= panelCacheLifetime) {
                panelResultCache.delete(key);
            }
        }

        while (panelResultCache.size > 12) {
            panelResultCache.delete(panelResultCache.keys().next().value);
        }
    } catch (error) {
        if (error.name !== "AbortError") {
            panelError.value =
                error instanceof Error
                    ? error.message
                    : "Terjadi kesalahan saat memuat data panel.";
        }
    } finally {
        if (panelRequest === request) {
            panelLoading.value = false;
            panelRequest = null;
        }
    }
};

const closePanel = () => {
    panelRequest?.abort();
    panelRequest = null;
    activePanel.value = null;
    panelRows.value = [];
    panelSearch.value = "";
    panelError.value = null;
    panelLoading.value = false;
};

const toggleSalesSeries = (series) => {
    visibleSalesSeries.value = {
        ...visibleSalesSeries.value,
        [series]: !visibleSalesSeries.value[series],
    };
};

onUnmounted(() => {
    clearTimeout(suggestionTimer);
    clearTimeout(copyToastTimer);
    suggestionRequest?.abort();
    summaryRequest?.abort();
    panelRequest?.abort();
});
</script>

<template>
    <Head title="Cek PLU" />

    <AuthenticatedLayout>
        <div
            class="min-h-[calc(100dvh-4rem)] space-y-2 px-2 py-2 sm:px-3 lg:px-4"
        >
            <section
                class="rounded-xl border border-gray-300 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-4"
            >
                <form
                    class="grid gap-2 rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800/60 sm:grid-cols-[minmax(10rem,0.8fr)_minmax(14rem,1.5fr)_auto]"
                    @submit.prevent="submitUnifiedSearch"
                >
                    <div class="min-w-0">
                        <label
                            for="plu-search"
                            class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300"
                        >
                            PLU / BARCODE
                        </label>
                        <input
                            id="plu-search"
                            v-model="pluQuery"
                            type="search"
                            maxlength="32"
                            autocomplete="off"
                            placeholder="Masukkan PLU atau barcode"
                            class="w-full rounded-md border-gray-300 text-gray-900 shadow-sm focus:border-cyan-600 focus:ring-cyan-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            @input="onPluInput"
                        />
                    </div>

                    <div class="relative min-w-0">
                        <label
                            for="description-search"
                            class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300"
                        >
                            SEARCH DESKRIPSI / PLU
                        </label>
                        <input
                            id="description-search"
                            v-model="descriptionQuery"
                            type="search"
                            maxlength="80"
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-controls="plu-description-suggestions"
                            :aria-expanded="suggestionsVisible"
                            placeholder="Ketik nama barang atau PLU"
                            class="w-full rounded-md border-gray-300 text-gray-900 shadow-sm focus:border-cyan-600 focus:ring-cyan-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            @input="onDescriptionInput"
                            @focus="onDescriptionFocus"
                            @blur="suggestionsVisible = false"
                            @keydown="onDescriptionKeydown"
                        />
                        <ul
                            v-if="suggestionsVisible"
                            id="plu-description-suggestions"
                            class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-auto rounded-md border border-gray-300 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-900"
                            role="listbox"
                        >
                            <li
                                v-if="suggestionsLoading"
                                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                                role="status"
                            >
                                Mencari nama barang...
                            </li>
                            <li
                                v-else-if="suggestionsError"
                                class="px-3 py-2 text-sm text-rose-700 dark:text-rose-300"
                                role="alert"
                            >
                                {{ suggestionsError }}
                            </li>
                            <li
                                v-else-if="suggestions.length === 0"
                                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                            >
                                Tidak ada saran. Tekan Enter untuk mencari
                                seluruh deskripsi.
                            </li>
                            <template v-else>
                                <li
                                    v-for="(item, index) in suggestions"
                                    :key="item.plu"
                                    role="option"
                                    :aria-selected="
                                        activeSuggestionIndex === index
                                    "
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-1 px-3 py-2 text-left text-sm hover:bg-cyan-50 focus:bg-cyan-50 focus:outline-none dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                        :class="
                                            activeSuggestionIndex === index
                                                ? 'bg-cyan-50 dark:bg-gray-800'
                                                : ''
                                        "
                                        @mouseenter="
                                            activeSuggestionIndex = index
                                        "
                                        @mousedown.prevent
                                        @click="selectSuggestion(item)"
                                    >
                                        <span
                                            class="min-w-0 truncate font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {{ item.description }}
                                        </span>
                                        <span
                                            class="shrink-0 font-mono text-xs text-gray-600 dark:text-gray-300"
                                        >
                                            -{{ item.plu }}
                                        </span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <button
                        type="submit"
                        class="self-end rounded-md bg-cyan-700 px-5 py-2.5 font-semibold text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2"
                    >
                        Cari
                    </button>
                </form>

                <p
                    v-if="message"
                    role="status"
                    class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                >
                    {{ message }}
                </p>
            </section>

            <section
                v-if="searchResults.length > 0"
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
            >
                <div
                    class="border-b border-gray-200 px-5 py-4 dark:border-gray-700"
                >
                    <h2 class="font-semibold text-gray-900 dark:text-gray-100">
                        Hasil pencarian deskripsi
                    </h2>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <li
                        v-for="item in searchResults"
                        :key="item.plu"
                        class="px-5 py-4"
                    >
                        <Link
                            :href="route('plu.info', { plu: item.plu })"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-lg transition hover:text-cyan-700 dark:hover:text-cyan-300"
                        >
                            <span
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.description }}
                            </span>
                            <span
                                class="whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ item.plu }} · {{ item.unit }} · Fraction
                                {{ item.fraction }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>

            <template v-if="product">
                <section
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <div
                        class="grid gap-4 border-b border-gray-200 p-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,2fr)] dark:border-gray-700"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    class="cursor-copy rounded-lg bg-cyan-100 px-3 py-1 font-mono text-sm font-bold text-cyan-900 transition hover:bg-cyan-200 focus:outline-none focus:ring-2 focus:ring-cyan-500 dark:bg-cyan-950 dark:text-cyan-200 dark:hover:bg-cyan-900"
                                    :aria-label="`Salin PLU ${product.plu}`"
                                    title="Klik untuk menyalin PLU"
                                    @click="copyProductPlu"
                                >
                                    {{ product.plu }}
                                </button>
                                <span
                                    v-if="copyToast?.target === 'plu'"
                                    class="rounded-md px-2 py-1 text-[11px] font-semibold shadow-sm"
                                    :class="
                                        copyToast.type === 'error'
                                            ? 'bg-rose-700 text-white'
                                            : 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                    "
                                    :role="
                                        copyToast.type === 'error'
                                            ? 'alert'
                                            : 'status'
                                    "
                                >
                                    {{ copyToast.message }}
                                </span>
                                <span
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ product.unit }} · Fraction
                                    {{ product.fraction }}
                                </span>
                            </div>
                            <div class="mt-3 flex min-w-0 items-start gap-3">
                                <button
                                    v-if="productImageUrl && !productImageUnavailable"
                                    type="button"
                                    class="shrink-0 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500"
                                    :aria-label="`Lihat foto ${product.description}`"
                                    @click="showImagePreview = true"
                                >
                                    <img
                                        :src="productImageUrl"
                                        :alt="`Foto ${product.description}`"
                                        class="size-20 rounded-lg border border-gray-200 bg-white object-contain p-1 dark:border-gray-700"
                                        loading="lazy"
                                        @error="advanceProductImageFallback"
                                    />
                                </button>
                                <p
                                    v-else
                                    class="w-20 shrink-0 break-words text-xs text-red-600 dark:text-red-400"
                                >
                                    Foto tidak tersedia
                                </p>
                                <div class="min-w-0">
                                    <h2
                                        class="flex flex-wrap items-center gap-2 text-xl font-bold text-gray-900 dark:text-gray-100"
                                    >
                                        <button
                                            type="button"
                                            class="cursor-copy break-words text-left hover:text-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 dark:hover:text-cyan-300"
                                            :aria-label="`Salin nama panjang ${product.description}`"
                                            title="Klik untuk menyalin nama panjang"
                                            @click="copyProductDescription"
                                        >
                                            {{ product.description }}
                                        </button>
                                        <span
                                            v-if="
                                                copyToast?.target ===
                                                'description'
                                            "
                                            class="rounded-md px-2 py-1 text-[11px] font-semibold shadow-sm"
                                            :class="
                                                copyToast.type === 'error'
                                                    ? 'bg-rose-700 text-white'
                                                    : 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                            "
                                            :role="
                                                copyToast.type === 'error'
                                                    ? 'alert'
                                                    : 'status'
                                            "
                                        >
                                            {{ copyToast.message }}
                                        </span>
                                    </h2>
                                    <p
                                        v-if="product.short_description"
                                        class="mt-1 break-words text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ product.short_description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-[repeat(auto-fit,minmax(9rem,1fr))]"
                        >
                            <div class="min-w-0">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Average Cost (Carton)
                                </p>
                                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Rp {{ formatPrice(priceHeader.averageCost) }}
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Harga Reguler
                                </p>
                                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Rp {{ formatPrice(priceHeader.regularPrice) }}
                                </p>
                            </div>
                            <div v-if="hasPrice(priceHeader.promoPrice)" class="min-w-0">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Promo Price
                                </p>
                                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Rp {{ formatPrice(priceHeader.promoPrice) }}
                                </p>
                            </div>
                            <div v-if="hasPrice(priceHeader.cashback)" class="min-w-0">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    CB
                                </p>
                                <p class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                    Rp {{ formatPrice(priceHeader.cashback) }}
                                </p>
                            </div>
                            <div class="min-w-0 overflow-hidden rounded-lg border border-emerald-700 dark:border-emerald-800">
                                <p class="bg-emerald-700 px-2 py-1 text-xs font-semibold text-white dark:bg-emerald-800">
                                    Net Price
                                </p>
                                <p
                                    class="px-2 pt-1 text-lg font-bold"
                                    :class="marginTextClass(priceHeader.marginPercent)"
                                >
                                    Rp {{ formatPrice(priceHeader.netPrice) }}
                                </p>
                                <p
                                    v-if="hasProductValue(priceHeader.marginPercent)"
                                    class="px-2 pb-1 text-sm font-semibold"
                                    :class="marginTextClass(priceHeader.marginPercent)"
                                >
                                    Margin {{ formatNumber(priceHeader.marginPercent) }}%
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid min-w-0 gap-2 xl:grid-cols-2 xl:items-start">
                    <section
                        class="min-w-0 overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <h2
                            class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        >
                            LPP
                        </h2>
                        <div class="overflow-x-auto">
                            <table
                                class="w-full min-w-[34rem] border-collapse text-center text-xs"
                            >
                                <thead
                                    class="bg-blue-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100"
                                >
                                    <tr>
                                        <th
                                            scope="col"
                                            :aria-sort="
                                                sortDirection('stock', 'label')
                                            "
                                            class="border border-gray-300 px-2 py-1.5 text-left dark:border-gray-700"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex max-w-full items-center gap-1 text-left leading-tight"
                                                @click="
                                                    toggleSort('stock', 'label')
                                                "
                                            >
                                                Stok
                                                <span aria-hidden="true">{{
                                                    sortIndicator(
                                                        "stock",
                                                        "label",
                                                    )
                                                }}</span>
                                            </button>
                                        </th>
                                        <th
                                            scope="col"
                                            :aria-sort="
                                                sortDirection(
                                                    'stock',
                                                    'cartons',
                                                )
                                            "
                                            class="border border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1"
                                                @click="
                                                    toggleSort(
                                                        'stock',
                                                        'cartons',
                                                    )
                                                "
                                            >
                                                Ctn
                                                <span aria-hidden="true">{{
                                                    sortIndicator(
                                                        "stock",
                                                        "cartons",
                                                    )
                                                }}</span>
                                            </button>
                                        </th>
                                        <th
                                            scope="col"
                                            :aria-sort="
                                                sortDirection('stock', 'pieces')
                                            "
                                            class="border border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1"
                                                @click="
                                                    toggleSort(
                                                        'stock',
                                                        'pieces',
                                                    )
                                                "
                                            >
                                                Sisa pcs
                                                <span aria-hidden="true">{{
                                                    sortIndicator(
                                                        "stock",
                                                        "pieces",
                                                    )
                                                }}</span>
                                            </button>
                                        </th>
                                        <th
                                            scope="col"
                                            :aria-sort="
                                                sortDirection('stock', 'total')
                                            "
                                            class="border border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1"
                                                @click="
                                                    toggleSort('stock', 'total')
                                                "
                                            >
                                                Total pcs
                                                <span aria-hidden="true">{{
                                                    sortIndicator(
                                                        "stock",
                                                        "total",
                                                    )
                                                }}</span>
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in sortedRows(
                                            stockRows,
                                            'stock',
                                            stockSortValue,
                                        )"
                                        :key="row.key"
                                        :class="
                                            row.emphasized
                                                ? 'bg-emerald-700 font-semibold text-white'
                                                : 'bg-white text-gray-900 even:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:even:bg-gray-800'
                                        "
                                    >
                                        <th
                                            scope="row"
                                            class="whitespace-nowrap border border-gray-300 px-2 py-1.5 text-left dark:border-gray-700"
                                        >
                                            {{ row.label }}
                                        </th>
                                        <td
                                            class="border border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            {{
                                                row.parts.cartons === null
                                                    ? "—"
                                                    : formatNumber(
                                                          row.parts.cartons,
                                                      )
                                            }}
                                            <span
                                                v-if="
                                                    row.parts.cartons !== null
                                                "
                                                class="text-xs"
                                                >ctn</span
                                            >
                                        </td>
                                        <td
                                            class="border border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            {{
                                                row.parts.pieces === null
                                                    ? "—"
                                                    : formatNumber(
                                                          row.parts.pieces,
                                                      )
                                            }}
                                            <span
                                                v-if="row.parts.pieces !== null"
                                                class="text-xs"
                                                >pcs</span
                                            >
                                        </td>
                                        <td
                                            class="whitespace-nowrap border border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            <template v-if="row.totalKey">
                                                Total
                                                <strong>{{
                                                    formatNumber(
                                                        product[row.totalKey],
                                                    )
                                                }}</strong>
                                                {{ row.totalUnit }}
                                            </template>
                                            <template v-else>
                                                Total
                                                <strong>{{
                                                    formatNumber(
                                                        row.parts.totalPcs,
                                                    )
                                                }}</strong>
                                                pcs
                                            </template>
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, index) in adjustmentStockRows"
                                        :key="row.key"
                                        :class="
                                            index === 0
                                                ? 'border-t-2 border-solid border-gray-500 bg-white text-gray-900 dark:border-gray-400 dark:bg-gray-900 dark:text-gray-100'
                                                : 'border-t border-solid border-gray-300 bg-white text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100'
                                        "
                                    >
                                        <th
                                            scope="row"
                                            class="whitespace-nowrap border-x border-gray-300 px-2 py-1.5 text-left dark:border-gray-700"
                                        >
                                            {{ row.label }}
                                        </th>
                                        <td
                                            class="border-x border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            {{
                                                row.parts.cartons === null
                                                    ? "—"
                                                    : formatNumber(row.parts.cartons)
                                            }}
                                            <span v-if="row.parts.cartons !== null">ctn</span>
                                        </td>
                                        <td
                                            class="border-x border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            {{
                                                row.parts.pieces === null
                                                    ? "—"
                                                    : formatNumber(row.parts.pieces)
                                            }}
                                            <span v-if="row.parts.pieces !== null">pcs</span>
                                        </td>
                                        <td
                                            class="border-x border-gray-300 px-2 py-1.5 dark:border-gray-700"
                                        >
                                            Total
                                            <strong>{{
                                                formatNumber(row.parts.totalPcs)
                                            }}</strong>
                                            pcs
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        class="min-w-0 overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                    >
                        <h2
                            class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        >
                            Detail dan riwayat
                        </h2>
                        <div
                            class="grid grid-cols-2 gap-1.5 p-2 sm:grid-cols-3 2xl:grid-cols-4"
                        >
                            <button
                                v-for="(panel, index) in otherPanelOptions"
                                :key="panel.value"
                                type="button"
                                class="min-w-0 rounded-md border px-2 py-1.5 text-left text-xs font-medium leading-tight shadow-sm transition hover:shadow focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-1"
                                :class="
                                    panelButtonColors[
                                        index % panelButtonColors.length
                                    ]
                                "
                                @click="openPanel(panel)"
                            >
                                <span class="block truncate">{{
                                    panel.label
                                }}</span>
                            </button>
                        </div>
                    </section>
                </div>

                <section
                    class="min-w-0 overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <header
                        class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h2
                            class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Harga net dan margin
                        </h2>
                        <button
                            v-if="summaryError"
                            type="button"
                            class="rounded-md border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-white dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="loadSummary"
                        >
                            Muat ulang
                        </button>
                    </header>
                    <div
                        v-if="summaryLoading"
                        class="space-y-2 p-3"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="sr-only"
                            >Memuat harga net dan margin...</span
                        >
                        <div
                            class="h-5 w-1/3 animate-pulse rounded bg-gray-200 dark:bg-gray-700"
                        ></div>
                        <div
                            class="h-10 animate-pulse rounded bg-gray-100 dark:bg-gray-800"
                        ></div>
                    </div>
                    <p
                        v-else-if="summaryError"
                        class="p-3 text-sm text-rose-700 dark:text-rose-300"
                        role="alert"
                    >
                        {{ summaryError }}
                    </p>
                    <p
                        v-else-if="summaryData.priceMargin.length === 0"
                        class="p-3 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Tidak ada data harga net dan margin untuk PLU ini.
                    </p>
                    <div v-else class="w-full overflow-x-auto">
                        <table
                            class="w-full min-w-[72rem] table-fixed border-collapse text-sm"
                        >
                            <colgroup>
                                <col
                                    v-for="column in priceMarginColumns"
                                    :key="column.key"
                                    :style="
                                        priceMarginColumnStyle(column.key)
                                    "
                                />
                            </colgroup>
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th
                                        v-for="column in priceMarginColumns"
                                        :key="column.key"
                                        scope="col"
                                        :aria-sort="
                                            sortDirection(
                                                'priceMargin',
                                                column.key,
                                            )
                                        "
                                        class="whitespace-nowrap border border-gray-300 px-1 py-1 text-left text-xs font-semibold dark:border-gray-600"
                                        :class="
                                            column.key === 'net_price'
                                                ? 'bg-emerald-700 text-white dark:bg-emerald-800'
                                                : 'text-gray-700 dark:text-gray-200'
                                        "
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 text-xs"
                                            @click="
                                                toggleSort(
                                                    'priceMargin',
                                                    column.key,
                                                )
                                            "
                                        >
                                            {{ column.label }}
                                            <span aria-hidden="true">{{
                                                sortIndicator(
                                                    "priceMargin",
                                                    column.key,
                                                )
                                            }}</span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, rowIndex) in sortedRows(
                                        summaryData.priceMargin,
                                        'priceMargin',
                                    )"
                                    :key="row.plu ?? rowIndex"
                                    class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                                >
                                    <td
                                        v-for="column in priceMarginColumns"
                                        :key="column.key"
                                        class="whitespace-nowrap border border-gray-200 px-1 py-2 dark:border-gray-700"
                                        :class="
                                            column.key === 'net_price'
                                                ? 'font-bold'
                                                : column.key === 'margin_percent' ||
                                                    column.key === 'margin_per_unit'
                                                  ? marginTextClass(
                                                        row.margin_percent,
                                                    )
                                                  : 'text-gray-700 dark:text-gray-300'
                                        "
                                    >
                                        {{
                                            displayPriceMarginValue(
                                                row[column.key],
                                                column.key,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <p
                    v-if="summaryLoading"
                    class="px-1 text-xs text-gray-500 dark:text-gray-400"
                    role="status"
                >
                    Memuat promo cashback dan gift...
                </p>
                <div
                    v-else-if="summaryError"
                    class="flex items-center justify-between gap-3 rounded-lg border border-rose-200 px-3 py-2 text-xs text-rose-700 dark:border-rose-900 dark:text-rose-300"
                    role="alert"
                >
                    <span>{{ summaryError }}</span>
                    <button
                        type="button"
                        class="shrink-0 font-semibold underline"
                        @click="loadSummary"
                    >
                        Muat ulang
                    </button>
                </div>
                <p
                    v-else-if="promoSections.length === 0"
                    class="px-1 text-xs text-gray-500 dark:text-gray-400"
                >
                    Tidak ada promo cashback dan gift.
                </p>
                <section
                    v-for="section in promoSections"
                    :key="section.key"
                    class="min-w-0 overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <header
                        class="border-b border-gray-200 bg-gray-50 px-2 py-1 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h2
                            class="text-xs font-semibold text-gray-900 dark:text-gray-100"
                        >
                            {{ section.label }}
                        </h2>
                    </header>
                    <div class="max-h-[28rem] overflow-auto">
                        <table
                            class="min-w-[58rem] w-full table-fixed border-collapse text-[10px] sm:text-xs"
                        >
                            <colgroup>
                                <col
                                    v-for="column in columnsFor(
                                        summaryData[section.key],
                                    )"
                                    :key="column"
                                    :style="promoColumnStyle(column)"
                                />
                            </colgroup>
                            <thead
                                class="sticky top-0 z-10 bg-gray-100 dark:bg-gray-800"
                            >
                                <tr>
                                    <th
                                        v-for="column in columnsFor(
                                            summaryData[section.key],
                                        )"
                                        :key="column"
                                        scope="col"
                                        :aria-sort="
                                            sortDirection(section.key, column)
                                        "
                                        class="border border-gray-300 px-1 py-1 text-left font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1"
                                            @click="
                                                toggleSort(section.key, column)
                                            "
                                        >
                                            {{ humanizeColumn(column) }}
                                            <span aria-hidden="true">{{
                                                sortIndicator(
                                                    section.key,
                                                    column,
                                                )
                                            }}</span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, rowIndex) in sortedRows(
                                        summaryData[section.key],
                                        section.key,
                                    )"
                                    :key="rowIndex"
                                    class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                                >
                                    <td
                                        v-for="column in columnsFor(
                                            summaryData[section.key],
                                        )"
                                        :key="column"
                                        class="whitespace-normal break-words border border-gray-200 px-1 py-1 text-gray-700 dark:border-gray-700 dark:text-gray-300"
                                    >
                                        <span
                                            class="block break-words"
                                            :class="
                                                column === 'mechanism'
                                                    ? 'line-clamp-2'
                                                    : ''
                                            "
                                            :title="
                                                column === 'mechanism'
                                                    ? String(
                                                          row[column] ?? '',
                                                      )
                                                    : undefined
                                            "
                                        >
                                            {{
                                                displayValue(
                                                    row[column],
                                                    column,
                                                )
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <details
                    open
                    class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 bg-gradient-to-r from-cyan-50 to-white px-4 py-4 text-gray-900 marker:hidden dark:from-cyan-950/40 dark:to-gray-900 dark:text-gray-100 sm:px-5"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200"
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    class="size-5"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 5.75A1.75 1.75 0 0 1 5.75 4h12.5A1.75 1.75 0 0 1 20 5.75v12.5A1.75 1.75 0 0 1 18.25 20H5.75A1.75 1.75 0 0 1 4 18.25V5.75Z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8 8h8M8 12h8M8 16h4"
                                    />
                                </svg>
                            </span>
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold"
                                        >Informasi produk lengkap</span
                                    >
                                    <span
                                        class="rounded-full bg-cyan-100 px-2 py-0.5 text-xs font-semibold text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200"
                                    >
                                        {{ totalProductFieldCount }} data
                                        tersedia
                                    </span>
                                </span>
                                <span
                                    class="mt-1 block text-xs font-normal text-gray-500 dark:text-gray-400"
                                >
                                    Data kosong disembunyikan otomatis · klik
                                    untuk buka/tutup
                                </span>
                            </span>
                        </span>
                        <span
                            class="grid size-8 shrink-0 place-items-center rounded-lg text-gray-500 transition group-open:rotate-180 dark:text-gray-400"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="size-5"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </span>
                    </summary>
                    <div
                        class="space-y-4 border-t border-gray-200 p-4 dark:border-gray-700 sm:p-5"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <label
                                class="relative block min-w-0 flex-1 sm:max-w-md"
                            >
                                <span class="sr-only"
                                    >Cari informasi produk</span
                                >
                                <svg
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-400"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M9 3.5a5.5 5.5 0 1 0 3.438 9.793l3.635 3.634a.75.75 0 1 0 1.06-1.06l-3.634-3.635A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <input
                                    v-model="productDetailsSearch"
                                    type="search"
                                    placeholder="Cari nama field atau nilainya..."
                                    class="w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
                                />
                            </label>
                            <div
                                class="flex flex-wrap items-center justify-between gap-3 sm:justify-end"
                            >
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    aria-live="polite"
                                >
                                    Menampilkan
                                    {{ visibleProductFieldCount }} dari
                                    {{ totalProductFieldCount }} data
                                </p>
                                <button
                                    v-if="purchaseOrderPanel"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-200 dark:hover:bg-cyan-900"
                                    @click="openPanel(purchaseOrderPanel)"
                                >
                                    <svg
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        class="size-4"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M3.5 3A1.5 1.5 0 0 0 2 4.5v11A1.5 1.5 0 0 0 3.5 17h13a1.5 1.5 0 0 0 1.5-1.5v-8A1.5 1.5 0 0 0 16.5 6H10L8.5 4.5h-5A1.5 1.5 0 0 0 3.5 3Z"
                                        />
                                    </svg>
                                    Lihat data PO
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="productGroups.length === 0"
                            class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center dark:border-gray-700 dark:bg-gray-800/60"
                        >
                            <p
                                class="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                {{
                                    productDetailsSearch.trim()
                                        ? "Tidak ada informasi yang cocok."
                                        : "Belum ada informasi produk."
                                }}
                            </p>
                            <p
                                v-if="productDetailsSearch.trim()"
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                Coba kata kunci lain atau hapus pencarian.
                            </p>
                        </div>

                        <div
                            v-else
                            class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3"
                        >
                            <section
                                v-for="group in productGroups"
                                :key="group.title"
                                class="min-w-0 rounded-2xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-700 dark:bg-gray-800/70"
                            >
                                <div
                                    class="mb-3 flex items-center justify-between gap-3"
                                >
                                    <h3
                                        class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ group.title }}
                                    </h3>
                                    <span
                                        class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-900 dark:text-gray-400"
                                    >
                                        {{ group.fields.length }}
                                    </span>
                                </div>
                                <dl class="grid gap-2 sm:grid-cols-2">
                                    <div
                                        v-for="field in group.fields"
                                        :key="field.key"
                                        class="min-w-0 rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-900/80"
                                        :class="
                                            field.key === 'description'
                                                ? 'sm:col-span-2'
                                                : ''
                                        "
                                    >
                                        <dt
                                            class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                        >
                                            {{ field.label }}
                                        </dt>
                                        <dd
                                            class="mt-1 break-words text-sm font-semibold leading-5 text-gray-900 dark:text-gray-100"
                                        >
                                            {{
                                                displayProductValue(
                                                    field.value,
                                                    field.key,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        </div>
                    </div>
                </details>
            </template>
        </div>

        <div
            v-if="activePanel"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-1 backdrop-blur-sm sm:p-2"
            role="presentation"
            @click.self="closePanel"
            @keydown.esc.window="closePanel"
        >
            <section
                class="flex max-h-[95dvh] max-w-[95vw] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
                :style="panelDialogStyle"
                role="dialog"
                aria-modal="true"
                :aria-label="activePanel.label"
            >
                <header
                    class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-6"
                >
                    <div>
                        <p
                            class="text-xs font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300"
                        >
                            PLU {{ product.plu }}
                        </p>
                        <h2
                            class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ activePanel.label }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="grid size-9 shrink-0 place-items-center rounded-lg bg-red-600 text-xl font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-red-700 dark:hover:bg-red-600"
                        aria-label="Tutup popup"
                        @click="closePanel"
                    >
                        ×
                    </button>
                </header>

                <div class="min-h-0 overflow-auto">
                    <div
                        v-if="panelLoading"
                        class="space-y-3 p-6"
                        role="status"
                        aria-live="polite"
                    >
                        <span class="sr-only">Memuat data...</span>
                        <div
                            class="h-5 w-1/3 animate-pulse rounded bg-gray-200 dark:bg-gray-700"
                        ></div>
                        <div
                            class="h-12 animate-pulse rounded bg-gray-100 dark:bg-gray-800"
                        ></div>
                        <div
                            class="h-12 animate-pulse rounded bg-gray-100 dark:bg-gray-800"
                        ></div>
                    </div>

                    <p
                        v-else-if="panelError"
                        class="m-5 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200"
                        role="alert"
                    >
                        {{ panelError }}
                    </p>

                    <p
                        v-else-if="panelRows.length === 0"
                        class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        Tidak ada data
                        {{ activePanel.label.toLowerCase() }} untuk PLU ini.
                    </p>

                    <template v-else-if="activePanel.value === 'sales-chart'">
                        <div class="space-y-4 p-3 sm:p-4">
                            <section
                                class="overflow-hidden rounded-xl border border-gray-700 bg-gray-950"
                                aria-label="Grafik penjualan bulanan"
                            >
                                <svg
                                    :viewBox="`0 0 ${monthlySalesChart.width} ${monthlySalesChart.height}`"
                                    class="block h-auto w-full"
                                    role="img"
                                    :aria-label="`Grafik penjualan, margin, dan member PLU ${product.plu}`"
                                >
                                    <title>
                                        Sales per bulan PLU {{ product.plu }}
                                    </title>
                                    <defs>
                                        <linearGradient
                                            id="sales-area-gradient"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="0%"
                                                stop-color="#16a34a"
                                                stop-opacity="0.35"
                                            />
                                            <stop
                                                offset="100%"
                                                stop-color="#16a34a"
                                                stop-opacity="0.02"
                                            />
                                        </linearGradient>
                                    </defs>
                                    <text
                                        :x="monthlySalesChart.width / 2"
                                        y="30"
                                        fill="#fbbf24"
                                        text-anchor="middle"
                                        font-size="18"
                                        font-weight="700"
                                    >
                                        SALES PER BULAN PLU {{ product.plu }}
                                    </text>
                                    <text
                                        v-if="visibleSalesSeries.sales"
                                        transform="rotate(-90 15 187)"
                                        x="15"
                                        y="187"
                                        fill="#22c55e"
                                        text-anchor="middle"
                                        font-size="10"
                                    >
                                        Sales (jt)
                                    </text>
                                    <text
                                        v-if="visibleSalesSeries.margin"
                                        transform="rotate(-90 43 187)"
                                        x="43"
                                        y="187"
                                        fill="#eab308"
                                        text-anchor="middle"
                                        font-size="10"
                                    >
                                        Margin (jt)
                                    </text>
                                    <text
                                        v-if="visibleSalesSeries.members"
                                        transform="rotate(90 1082 187)"
                                        x="1082"
                                        y="187"
                                        fill="#ef4444"
                                        text-anchor="middle"
                                        font-size="10"
                                    >
                                        Member
                                    </text>
                                    <g
                                        v-for="(line, index) in monthlySalesChart.gridLines"
                                        :key="`grid-${index}`"
                                    >
                                        <line
                                            :x1="monthlySalesChart.plotLeft"
                                            :x2="monthlySalesChart.plotRight"
                                            :y1="line.y"
                                            :y2="line.y"
                                            stroke="#374151"
                                            stroke-dasharray="3 5"
                                        />
                                        <text
                                            v-if="visibleSalesSeries.sales"
                                            :x="monthlySalesChart.plotLeft - 8"
                                            :y="line.y + 4"
                                            fill="#22c55e"
                                            text-anchor="end"
                                            font-size="11"
                                        >
                                            {{ line.sales }}
                                        </text>
                                        <text
                                            v-if="visibleSalesSeries.margin"
                                            :x="monthlySalesChart.plotLeft - 48"
                                            :y="line.y + 4"
                                            fill="#eab308"
                                            text-anchor="end"
                                            font-size="10"
                                        >
                                            {{ line.margin }}
                                        </text>
                                        <text
                                            v-if="visibleSalesSeries.members"
                                            :x="monthlySalesChart.plotRight + 10"
                                            :y="line.y + 4"
                                            fill="#ef4444"
                                            text-anchor="start"
                                            font-size="11"
                                        >
                                            {{ line.members }}
                                        </text>
                                    </g>
                                    <path
                                        v-if="visibleSalesSeries.sales"
                                        :d="monthlySalesChart.salesAreaPath"
                                        fill="url(#sales-area-gradient)"
                                    />
                                    <path
                                        v-if="visibleSalesSeries.sales"
                                        :d="monthlySalesChart.salesPath"
                                        fill="none"
                                        stroke="#22c55e"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        v-if="visibleSalesSeries.members"
                                        :d="monthlySalesChart.membersPath"
                                        fill="none"
                                        stroke="#ef4444"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        v-if="visibleSalesSeries.margin"
                                        :d="monthlySalesChart.marginPath"
                                        fill="none"
                                        stroke="#eab308"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <g
                                        v-for="point in monthlySalesChart.points"
                                        :key="point.month"
                                    >
                                        <circle
                                            v-if="visibleSalesSeries.sales"
                                            :cx="point.sales.x"
                                            :cy="point.sales.y"
                                            r="4"
                                            fill="#22c55e"
                                            stroke="#111827"
                                            stroke-width="2"
                                        />
                                        <circle
                                            v-if="visibleSalesSeries.members"
                                            :cx="point.members.x"
                                            :cy="point.members.y"
                                            r="4"
                                            fill="#ef4444"
                                            stroke="#111827"
                                            stroke-width="2"
                                        />
                                        <circle
                                            v-if="visibleSalesSeries.margin"
                                            :cx="point.margin.x"
                                            :cy="point.margin.y"
                                            r="4"
                                            fill="#eab308"
                                            stroke="#111827"
                                            stroke-width="2"
                                        />
                                        <g
                                            v-if="visibleSalesSeries.sales"
                                            :transform="`translate(${point.sales.x - 31} ${point.sales.labelY})`"
                                        >
                                            <rect
                                                width="62"
                                                height="19"
                                                rx="5"
                                                fill="#f3f4f6"
                                            />
                                            <text
                                                x="31"
                                                y="13"
                                                fill="#166534"
                                                text-anchor="middle"
                                                font-size="10"
                                                font-weight="700"
                                            >
                                                {{ point.sales.label }}
                                            </text>
                                        </g>
                                        <g
                                            v-if="visibleSalesSeries.members"
                                            :transform="`translate(${point.members.x - 31} ${point.members.labelY})`"
                                        >
                                            <rect
                                                width="62"
                                                height="19"
                                                rx="5"
                                                fill="#f3f4f6"
                                            />
                                            <text
                                                x="31"
                                                y="13"
                                                fill="#b91c1c"
                                                text-anchor="middle"
                                                font-size="10"
                                                font-weight="700"
                                            >
                                                {{ point.members.label }}
                                            </text>
                                        </g>
                                        <g
                                            v-if="visibleSalesSeries.margin"
                                            :transform="`translate(${point.margin.x - 31} ${point.margin.labelY})`"
                                        >
                                            <rect
                                                width="62"
                                                height="19"
                                                rx="5"
                                                fill="#f3f4f6"
                                            />
                                            <text
                                                x="31"
                                                y="13"
                                                fill="#854d0e"
                                                text-anchor="middle"
                                                font-size="10"
                                                font-weight="700"
                                            >
                                                {{ point.margin.label }}
                                            </text>
                                        </g>
                                        <text
                                            :x="point.sales.x"
                                            :y="monthlySalesChart.plotBottom + 24"
                                            fill="#9ca3af"
                                            text-anchor="middle"
                                            font-size="11"
                                        >
                                            {{ point.month }}
                                        </text>
                                    </g>
                                </svg>
                                <div
                                    class="flex flex-wrap justify-center gap-5 border-t border-gray-800 px-3 py-2 text-xs text-gray-300"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded px-2 py-1 transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-green-500"
                                        :class="
                                            visibleSalesSeries.sales
                                                ? 'text-gray-200'
                                                : 'text-gray-500 opacity-50 line-through'
                                        "
                                        :aria-pressed="visibleSalesSeries.sales"
                                        @click="toggleSalesSeries('sales')"
                                    >
                                        <span class="h-1 w-6 bg-green-500"></span>
                                        Sales
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded px-2 py-1 transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-yellow-500"
                                        :class="
                                            visibleSalesSeries.margin
                                                ? 'text-gray-200'
                                                : 'text-gray-500 opacity-50 line-through'
                                        "
                                        :aria-pressed="visibleSalesSeries.margin"
                                        @click="toggleSalesSeries('margin')"
                                    >
                                        <span class="h-1 w-6 bg-yellow-500"></span>
                                        Margin
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded px-2 py-1 transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-red-500"
                                        :class="
                                            visibleSalesSeries.members
                                                ? 'text-gray-200'
                                                : 'text-gray-500 opacity-50 line-through'
                                        "
                                        :aria-pressed="visibleSalesSeries.members"
                                        @click="toggleSalesSeries('members')"
                                    >
                                        <span class="h-1 w-6 bg-red-500"></span>
                                        Member
                                    </button>
                                </div>
                            </section>

                            <section
                                class="overflow-hidden rounded-xl border border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-900"
                            >
                                <h3
                                    class="bg-emerald-800 px-3 py-2 text-center text-lg font-bold text-white"
                                >
                                    Sales By Month
                                </h3>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-3 py-2 dark:border-gray-700"
                                >
                                    <input
                                        v-if="panelSearchVisible"
                                        v-model="panelSearch"
                                        type="search"
                                        placeholder="Cari data..."
                                        class="w-full max-w-sm rounded-lg border-gray-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    />
                                    <p
                                        v-if="panelSearchVisible"
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                        aria-live="polite"
                                    >
                                        {{ filteredPanelRows.length }} dari
                                        {{ panelRows.length }} bulan
                                    </p>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-cyan-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-500"
                                        @click="copyPanelRows"
                                    >
                                        Salin seluruh tabel
                                    </button>
                                    <span
                                        v-if="copyToast?.target === 'panel'"
                                        class="rounded-md px-2 py-1 text-[11px] font-semibold shadow-sm"
                                        :class="
                                            copyToast.type === 'error'
                                                ? 'bg-rose-700 text-white'
                                                : 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                        "
                                        :role="
                                            copyToast.type === 'error'
                                                ? 'alert'
                                                : 'status'
                                        "
                                    >
                                        {{ copyToast.message }}
                                    </span>
                                </div>
                                <p
                                    v-if="filteredPanelRows.length === 0"
                                    class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada bulan yang cocok.
                                </p>
                                <div v-else class="overflow-x-auto">
                                    <table
                                        class="min-w-[58rem] w-full border-collapse text-sm text-gray-900 dark:text-gray-100"
                                    >
                                        <thead
                                            class="bg-gray-100 dark:bg-gray-800"
                                        >
                                            <tr>
                                                <th
                                                    rowspan="2"
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    BULAN
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    GROSS
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    SALES
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    MARGIN
                                                </th>
                                                <th
                                                    colspan="2"
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    QTY
                                                </th>
                                                <th
                                                    rowspan="2"
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    MEMBER
                                                </th>
                                            </tr>
                                            <tr>
                                                <th
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    PCS
                                                </th>
                                                <th
                                                    class="border border-gray-300 px-3 py-2 dark:border-gray-700"
                                                >
                                                    CTN
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="row in filteredPanelRows"
                                                :key="row.mon"
                                                class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                                            >
                                                <td
                                                    class="border border-gray-300 px-3 py-2 text-center dark:border-gray-700"
                                                >
                                                    {{ row.mon }}
                                                </td>
                                                <td
                                                    class="border border-gray-300 px-3 py-2 text-right dark:border-gray-700"
                                                >
                                                    {{ formatPrice(row.gross) }}
                                                </td>
                                                <td
                                                    class="border border-gray-300 px-3 py-2 text-right dark:border-gray-700"
                                                >
                                                    {{ formatPrice(row.sales) }}
                                                </td>
                                                <td
                                                    class="border border-gray-300 px-3 py-2 text-right dark:border-gray-700"
                                                >
                                                    {{ formatPrice(row.margin) }}
                                                </td>
                                                <td
                                                    class="border border-gray-300 px-3 py-2 text-right dark:border-gray-700"
                                                >
                                                    {{ formatWholeNumber(row.quantity) }}
                                                </td>
                                                <td
                                                    class="border border-gray-300 px-3 py-2 text-right dark:border-gray-700"
                                                >
                                                    {{ formatCartons(row.cartons) }}
                                                </td>
                                                <td
                                                    class="border border-gray-300 px-3 py-2 text-right dark:border-gray-700"
                                                >
                                                    {{ formatWholeNumber(row.members) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <label
                                v-if="panelSearchVisible"
                                class="min-w-52 flex-1 sm:max-w-md"
                            >
                                <span class="sr-only">Cari isi tabel</span>
                                <input
                                    v-model="panelSearch"
                                    type="search"
                                    placeholder="Cari di seluruh kolom..."
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-cyan-500 focus:ring-cyan-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </label>
                            <p
                                v-if="panelSearchVisible"
                                class="text-xs text-gray-500 dark:text-gray-400"
                                aria-live="polite"
                            >
                                {{ filteredPanelRows.length }} dari
                                {{ panelRows.length }} baris
                            </p>
                            <button
                                type="button"
                                class="rounded-lg bg-cyan-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-500"
                                @click="copyPanelRows"
                            >
                                Salin seluruh tabel
                            </button>
                            <span
                                v-if="copyToast?.target === 'panel'"
                                class="rounded-md px-2 py-1 text-[11px] font-semibold shadow-sm"
                                :class="
                                    copyToast.type === 'error'
                                        ? 'bg-rose-700 text-white'
                                        : 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                "
                                :role="
                                    copyToast.type === 'error'
                                        ? 'alert'
                                        : 'status'
                                "
                            >
                                {{ copyToast.message }}
                            </span>
                        </div>
                        <p
                            v-if="filteredPanelRows.length === 0"
                            class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                        >
                            Tidak ada baris yang cocok dengan pencarian.
                        </p>
                        <table
                            v-else
                            class="w-full table-fixed border-collapse text-sm"
                        >
                            <thead
                                class="sticky top-0 bg-gray-100 dark:bg-cyan-900/80"
                            >
                                <tr>
                                    <th
                                        v-for="column in columnsFor(panelRows)"
                                        :key="column"
                                        scope="col"
                                        :aria-sort="sortDirection('panel', column)"
                                        class="break-words border border-gray-300 px-2 py-2 text-left font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex max-w-full items-start gap-1 break-words text-left"
                                            @click="toggleSort('panel', column)"
                                        >
                                            {{ humanizeColumn(column) }}
                                            <span aria-hidden="true">{{
                                                sortIndicator("panel", column)
                                            }}</span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, rowIndex) in sortedRows(
                                        filteredPanelRows,
                                        'panel',
                                    )"
                                    :key="rowIndex"
                                    class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800"
                                >
                                    <td
                                        v-for="column in columnsFor(panelRows)"
                                        :key="column"
                                        class="break-words border border-gray-200 px-2 py-2 text-gray-700 dark:border-gray-700 dark:text-gray-300"
                                    >
                                        {{ displayValue(row[column], column) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </template>
                </div>
            </section>
        </div>
        <Modal
            :show="showImagePreview"
            max-width="7xl"
            @close="showImagePreview = false"
        >
            <section
                class="overflow-hidden rounded-xl bg-white dark:bg-gray-900"
                aria-labelledby="product-image-preview-title"
            >
                <header
                    class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700"
                >
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">
                            PLU {{ product?.plu }}
                        </p>
                        <h2
                            id="product-image-preview-title"
                            class="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100"
                        >
                            Pratinjau Foto Produk
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="grid size-9 shrink-0 place-items-center rounded-lg bg-red-600 text-xl font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-red-700 dark:hover:bg-red-600"
                        aria-label="Tutup pratinjau foto"
                        @click="showImagePreview = false"
                    >
                        ×
                    </button>
                </header>
                <div class="grid gap-5 p-5 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                    <div class="grid min-h-64 place-items-center rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <img
                            v-if="productImageUrl && !productImageUnavailable"
                            :src="productImageUrl"
                            :alt="`Foto ${product?.description ?? ''}`"
                            class="max-h-[70vh] max-w-full object-contain"
                            @error="advanceProductImageFallback"
                        />
                        <p
                            v-else
                            class="text-sm text-red-600 dark:text-red-400"
                        >
                            Foto tidak tersedia
                        </p>
                    </div>
                    <div class="min-w-0 self-center">
                        <h3 class="break-words text-xl font-bold text-gray-900 dark:text-gray-100">
                            {{ product?.description }}
                        </h3>
                        <p
                            v-if="product?.short_description"
                            class="mt-2 break-words text-sm text-gray-600 dark:text-gray-300"
                        >
                            {{ product.short_description }}
                        </p>
                    </div>
                </div>
            </section>
        </Modal>
    </AuthenticatedLayout>
</template>
