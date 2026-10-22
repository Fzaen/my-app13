<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

defineOptions({ name: "AppNavbarMenuItem" });

const props = defineProps({
    item: { type: Object, required: true },
    itemKey: { type: String, required: true },
    parentKey: { type: String, default: "" },
    mode: { type: String, required: true },
    submenuOpen: { type: Function, required: true },
    openSubmenu: { type: Function, required: true },
    closeSubmenu: { type: Function, required: true },
    toggleSubmenu: { type: Function, required: true },
    closeNavigation: { type: Function, required: true },
});

const hasChildren = computed(
    () => Array.isArray(props.item.children) && props.item.children.length > 0,
);
</script>

<template>
    <div
        class="relative rounded-xl"
        :class="
            item.route ? (item.danger ? 'bg-red-50' : 'bg-indigo-50/60') : ''
        "
        @mouseenter="
            mode === 'desktop' && hasChildren ? openSubmenu(itemKey) : null
        "
        @mouseleave="
            mode === 'desktop' && hasChildren
                ? closeSubmenu(itemKey, parentKey)
                : null
        "
    >
        <div v-if="hasChildren" class="flex w-full items-center">
            <Link
                v-if="item.route"
                :href="route(item.route)"
                class="flex flex-1 items-center rounded-s-xl text-sm font-medium text-gray-700 transition"
                :class="[
                    mode === 'desktop' ? 'px-3.5 py-3' : 'min-h-10 flex-1 px-3',
                    'hover:bg-indigo-500 hover:text-white',
                ]"
                @click="closeNavigation"
            >
                {{ item.label }}
            </Link>
            <button
                type="button"
                :aria-label="
                    item.route ? `Toggle ${item.label} submenu` : undefined
                "
                :aria-expanded="submenuOpen(itemKey)"
                class="flex items-center justify-between text-sm font-medium text-gray-700 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                :class="[
                    mode === 'desktop' ? 'shrink-0 py-3' : 'min-h-10 shrink-0',
                    item.route
                        ? 'rounded-e-xl px-2 hover:bg-indigo-500 hover:text-white'
                        : 'w-full rounded-xl px-3.5 hover:bg-indigo-100 hover:text-indigo-700 dark:hover:bg-gray-700 dark:hover:text-white',
                ]"
                @click="
                    mode === 'desktop'
                        ? openSubmenu(itemKey)
                        : toggleSubmenu(itemKey, parentKey)
                "
            >
                <span v-if="!item.route">{{ item.label }}</span>
                <svg
                    class="h-4 w-4 text-green-600 transition-transform duration-200"
                    :class="
                        mode === 'mobile' && submenuOpen(itemKey)
                            ? 'rotate-180'
                            : ''
                    "
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        v-if="mode === 'desktop'"
                        fill-rule="evenodd"
                        d="M7.21 14.77a.75.75 0 010-1.06L10.94 10 7.21 6.29a.75.75 0 111.06-1.06l4.25 4.25a.75.75 0 010 1.06l-4.25 4.23a.75.75 0 01-1.06 0z"
                        clip-rule="evenodd"
                    />
                    <path
                        v-else
                        fill-rule="evenodd"
                        d="M5.22 7.47a.75.75 0 011.06 0L10 11.19l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 8.53a.75.75 0 010-1.06z"
                        clip-rule="evenodd"
                    />
                </svg>
            </button>
        </div>

        <Link
            v-else-if="item.route && item.method"
            :href="route(item.route)"
            :method="item.method"
            as="button"
            class="flex w-full items-center rounded-xl px-3.5 py-3 text-left text-sm font-medium text-red-600 transition hover:bg-red-100"
            @click="closeNavigation"
        >
            {{ item.label }}
        </Link>
        <Link
            v-else-if="item.route"
            :href="route(item.route)"
            class="flex items-center rounded-xl px-3.5 py-3 text-sm font-medium text-gray-700 transition hover:bg-indigo-500 hover:text-white"
            @click="closeNavigation"
        >
            {{ item.label }}
        </Link>
        <span v-else class="block px-3.5 py-3 text-sm text-gray-700">
            {{ item.label }}
        </span>

        <div
            v-if="hasChildren && submenuOpen(itemKey)"
            :class="
                mode === 'desktop'
                    ? 'absolute left-[calc(100%-0.25rem)] top-0 z-50 w-56 pl-2'
                    : 'ml-3 space-y-1 border-l-2 border-indigo-100 py-1 pl-3'
            "
        >
            <div
                :class="
                    mode === 'desktop'
                        ? 'rounded-2xl border border-gray-100 bg-white p-2 shadow-xl shadow-gray-900/10 ring-1 ring-gray-900/5'
                        : 'space-y-1'
                "
            >
                <AppNavbarMenuItem
                    v-for="(child, childIndex) in item.children"
                    :key="childIndex"
                    :item="child"
                    :item-key="`${itemKey}/${childIndex}`"
                    :parent-key="itemKey"
                    :mode="mode"
                    :submenu-open="submenuOpen"
                    :open-submenu="openSubmenu"
                    :close-submenu="closeSubmenu"
                    :toggle-submenu="toggleSubmenu"
                    :close-navigation="closeNavigation"
                />
            </div>
        </div>
    </div>
</template>
