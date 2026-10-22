<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import BrandLogo from "@/Components/BrandLogo.vue";
import AppNavbarMenuItem from "@/Components/AppNavbarMenuItem.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";

const page = usePage();
const isAuth =
    page.props.auth?.user !== undefined && page.props.auth?.user !== null;
const isDarkMode = ref(document.documentElement.classList.contains("dark"));

const mobileMenuOpen = ref(false);
const scrolled = ref(false);
const openDesktopMenu = ref(null);
const openDesktopSubmenuPath = ref(null);
const pinnedDesktopMenu = ref(null);
const openMobileMenu = ref(null);
const openMobileSubmenuPath = ref(null);

const handleScroll = () => {
    scrolled.value = window.scrollY > 10;
};

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle("dark", isDarkMode.value);
    window.localStorage.setItem("theme", isDarkMode.value ? "dark" : "light");
};

watch(
    () => page.url,
    () => {
        closeNavigation();
    },
);

const navigationMenus = [
    {
        label: "Klik",
        route: "profile.edit",
        items: [
            {
                label: "PB All",
                route: "pb.all",
                children: [
                    {
                        label: "PB Tgl",
                        route: "profile.edit",
                        children: [
                            { label: "PB Tgl", route: "profile.edit" },
                            { label: "PB Item", route: "profile.edit" },
                        ],
                    },
                    { label: "PB Item", route: "profile.edit" },
                ],
            },
            {
                label: "test",
                children: [{ label: "test settings", route: "profile.edit" }],
            },
        ],
    },
    {
        label: "Data",
        items: [
            {
                label: "Export",
                children: [{ label: "Semua laporan", route: "sales.export" }],
            },
        ],
    },
    {
        label: "Workspace",
        items: [
            { label: "Dashboard", route: "dashboard" },
            {
                label: "Preferences",
                children: [
                    { label: "Profile settings", route: "profile.edit" },
                ],
            },
        ],
    },
    {
        label: "Account",
        items: [
            { label: "Profile", route: "profile.edit" },
            {
                label: "Session",
                children: [
                    {
                        label: "Log out",
                        route: "logout",
                        method: "post",
                        danger: true,
                    },
                ],
            },
        ],
    },
];

const handleDocumentPointerDown = (event) => {
    if (!event.target.closest("[data-nav-dropdown]")) {
        openDesktopMenu.value = null;
        openDesktopSubmenuPath.value = null;
        pinnedDesktopMenu.value = null;
    }
};

const handleKeydown = (event) => {
    if (event.key === "Escape") {
        closeNavigation();
    }
};

onMounted(() => {
    window.addEventListener("scroll", handleScroll);
    document.addEventListener("pointerdown", handleDocumentPointerDown);
    document.addEventListener("keydown", handleKeydown);
});
onUnmounted(() => {
    window.removeEventListener("scroll", handleScroll);
    document.removeEventListener("pointerdown", handleDocumentPointerDown);
    document.removeEventListener("keydown", handleKeydown);
});

const isActive = (routeName) => {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
};

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
    openMobileMenu.value = null;
    openMobileSubmenuPath.value = null;
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
    openMobileMenu.value = null;
    openMobileSubmenuPath.value = null;
};

const closeNavigation = () => {
    closeMobileMenu();
    openDesktopMenu.value = null;
    openDesktopSubmenuPath.value = null;
    pinnedDesktopMenu.value = null;
};

const toggleDesktopMenu = (menuKey) => {
    if (pinnedDesktopMenu.value === menuKey) {
        pinnedDesktopMenu.value = null;
        openDesktopMenu.value = null;
        openDesktopSubmenuPath.value = null;
        return;
    }

    pinnedDesktopMenu.value = menuKey;
    openDesktopMenu.value = menuKey;
    openDesktopSubmenuPath.value = null;
};

const showDesktopMenu = (menuKey) => {
    if (
        pinnedDesktopMenu.value !== null &&
        pinnedDesktopMenu.value !== menuKey
    ) {
        return;
    }

    if (openDesktopMenu.value !== menuKey) {
        openDesktopSubmenuPath.value = null;
    }

    openDesktopMenu.value = menuKey;
};

const closeDesktopMenu = (menuKey) => {
    if (pinnedDesktopMenu.value === menuKey) {
        return;
    }

    if (openDesktopMenu.value === menuKey) {
        openDesktopMenu.value = null;
        openDesktopSubmenuPath.value = null;
    }
};

const desktopSubmenuIsOpen = (itemKey) =>
    openDesktopSubmenuPath.value === itemKey ||
    openDesktopSubmenuPath.value?.startsWith(`${itemKey}/`);

const openDesktopSubmenu = (itemKey) => {
    openDesktopSubmenuPath.value = itemKey;
};

const closeDesktopSubmenu = (itemKey, parentKey) => {
    if (desktopSubmenuIsOpen(itemKey)) {
        openDesktopSubmenuPath.value = parentKey || null;
    }
};

const toggleDesktopSubmenu = (itemKey, parentKey) => {
    if (desktopSubmenuIsOpen(itemKey)) {
        closeDesktopSubmenu(itemKey, parentKey);
    } else {
        openDesktopSubmenu(itemKey);
    }
};

const toggleMobileDropdown = (menuKey) => {
    openMobileMenu.value = openMobileMenu.value === menuKey ? null : menuKey;
    openMobileSubmenuPath.value = null;
};

const mobileSubmenuIsOpen = (itemKey) =>
    openMobileSubmenuPath.value === itemKey ||
    openMobileSubmenuPath.value?.startsWith(`${itemKey}/`);

const toggleMobileSubmenu = (itemKey, parentKey) => {
    openMobileSubmenuPath.value = mobileSubmenuIsOpen(itemKey)
        ? parentKey || null
        : itemKey;
};
</script>
<template>
    <nav
        :class="[
            'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
            scrolled
                ? 'bg-white/95 backdrop-blur-md shadow-lg border-b border-gray-200/80'
                : 'bg-white border-b border-gray-100',
        ]"
    >
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <!-- Kiri: Logo -->
                <div class="flex items-center gap-3">
                    <Link
                        :href="isAuth ? route('dashboard') : route('login')"
                        class="flex items-center gap-2 shrink-0"
                    >
                        <BrandLogo class="block h-9 w-auto" />
                    </Link>

                    <!-- Navigasi Desktop -->
                    <div
                        v-if="isAuth"
                        class="hidden md:flex md:items-center md:ms-8 md:gap-1.5"
                    >
                        <div
                            v-for="(menu, menuIndex) in navigationMenus"
                            :key="menuIndex"
                            class="relative flex items-center rounded-full"
                            :class="menu.route ? 'bg-indigo-50/60' : ''"
                            data-nav-dropdown
                            @mouseenter="showDesktopMenu(menuIndex)"
                            @mouseleave="closeDesktopMenu(menuIndex)"
                        >
                            <Link
                                v-if="menu.route"
                                :href="route(menu.route)"
                                class="flex items-center rounded-full px-4 py-2 text-sm font-medium text-gray-600 transition hover:bg-indigo-500 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                @click="closeNavigation"
                            >
                                {{ menu.label }}
                            </Link>
                            <button
                                type="button"
                                :aria-label="
                                    menu.route
                                        ? `Toggle ${menu.label} submenu`
                                        : null
                                "
                                :aria-expanded="openDesktopMenu === menuIndex"
                                class="flex items-center gap-2 rounded-full py-2 text-sm font-medium text-gray-600 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                :class="[
                                    menu.route
                                        ? 'px-2 hover:bg-indigo-500 hover:text-yellow-400'
                                        : 'px-4 hover:bg-gray-700 hover:text-yellow-400',
                                ]"
                                @click="toggleDesktopMenu(menuIndex)"
                            >
                                <span v-if="!menu.route">{{ menu.label }}</span>
                                <svg
                                    class="h-4 w-4 text-yellow-600 transition-transform duration-200"
                                    :class="
                                        openDesktopMenu === menuIndex
                                            ? 'rotate-180'
                                            : ''
                                    "
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M5.22 7.47a.75.75 0 011.06 0L10 11.19l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 8.53a.75.75 0 010-1.06z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>

                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="translate-y-1 opacity-0"
                                enter-to-class="translate-y-0 opacity-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="translate-y-0 opacity-100"
                                leave-to-class="translate-y-1 opacity-0"
                            >
                                <div
                                    v-if="openDesktopMenu === menuIndex"
                                    class="absolute left-0 top-full z-50 w-60 pt-3"
                                >
                                    <div
                                        class="rounded-2xl border border-gray-100 bg-white p-2 shadow-xl shadow-gray-900/10 ring-1 ring-gray-900/5"
                                    >
                                        <AppNavbarMenuItem
                                            v-for="(
                                                item, itemIndex
                                            ) in menu.items"
                                            :key="item.label"
                                            :item="item"
                                            :item-key="`${menuIndex}/${itemIndex}`"
                                            mode="desktop"
                                            :submenu-open="desktopSubmenuIsOpen"
                                            :open-submenu="openDesktopSubmenu"
                                            :close-submenu="closeDesktopSubmenu"
                                            :toggle-submenu="
                                                toggleDesktopSubmenu
                                            "
                                            :close-navigation="closeNavigation"
                                        />
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Auth (Desktop) -->
                <div class="hidden md:flex md:items-center md:gap-2">
                    <!-- Jika sudah login -->
                    <template v-if="isAuth">
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex items-center gap-2 rounded-full bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 ring-1 ring-gray-200 hover:ring-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <span
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white"
                                    >
                                        {{
                                            ($page.props.auth.user.name || "U")
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </span>
                                    <span class="hidden lg:block">{{
                                        $page.props.auth.user.name
                                    }}</span>
                                    <svg
                                        class="h-4 w-4 text-gray-400"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div class="border-b border-gray-100 px-4 py-3">
                                    <div
                                        class="text-xs font-semibold text-gray-800"
                                    >
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs leading-5 text-gray-500 whitespace-normal wrap-break-word"
                                    >
                                        {{ $page.props.selectedDatabase }}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 border-b border-gray-100 px-4 py-3 text-start text-sm text-gray-700 transition hover:bg-gray-50 focus:bg-gray-50 focus:outline-none"
                                    :aria-label="
                                        isDarkMode
                                            ? 'Switch to light mode'
                                            : 'Switch to dark mode'
                                    "
                                    @click="toggleDarkMode"
                                >
                                    <svg
                                        v-if="isDarkMode"
                                        class="h-4 w-4 text-amber-500"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <circle cx="12" cy="12" r="4" />
                                        <path
                                            stroke-linecap="round"
                                            d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-4 w-4 text-indigo-500"
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M20.9 13A8.5 8.5 0 1111 3.1 6.8 6.8 0 0020.9 13z"
                                        />
                                    </svg>
                                    <span>{{
                                        isDarkMode ? "Light mode" : "Dark mode"
                                    }}</span>
                                </button>
                                <DropdownLink :href="route('profile.edit')">
                                    <div class="flex items-center gap-2">
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                                            />
                                        </svg>
                                        Profile
                                    </div>
                                </DropdownLink>
                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    <div class="flex items-center gap-2">
                                        <svg
                                            class="h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H21"
                                            />
                                        </svg>
                                        Log Out
                                    </div>
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </template>
                    <!-- Jika belum login -->
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition-all duration-200 hover:text-gray-900 hover:bg-gray-50"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Register
                        </Link>
                    </template>
                </div>

                <!-- Tombol Hamburger (Mobile) -->
                <div class="flex items-center md:hidden">
                    <button
                        @click="toggleMobileMenu"
                        class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition-all duration-200 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :aria-label="
                            mobileMenuOpen ? 'Close menu' : 'Open menu'
                        "
                    >
                        <svg
                            class="h-6 w-6 transition-transform duration-300"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                v-if="!mobileMenuOpen"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-[-8px]"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-[-8px]"
    >
        <div
            v-show="mobileMenuOpen"
            class="fixed inset-x-0 top-16 bottom-0 z-60 overflow-y-auto overscroll-contain touch-pan-y border-t border-gray-200 bg-white shadow-xl md:hidden"
        >
            <div class="space-y-1 px-4 py-3">
                <!-- Navigasi untuk user yang login -->
                <template v-if="isAuth">
                    <!-- Info User -->
                    <div class="border-b border-gray-100 pb-3 mb-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white"
                            >
                                {{
                                    ($page.props.auth.user.name || "U")
                                        .charAt(0)
                                        .toUpperCase()
                                }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <div
                                    class="text-sm font-semibold text-gray-800 truncate"
                                >
                                    {{ $page.props.auth.user.name }}
                                </div>
                                <div class="text-xs text-gray-500 truncate">
                                    {{ $page.props.selectedDatabase }}
                                </div>
                            </div>
                            <button
                                type="button"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-600 transition hover:bg-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                :aria-label="
                                    isDarkMode
                                        ? 'Switch to light mode'
                                        : 'Switch to dark mode'
                                "
                                :title="isDarkMode ? 'Light mode' : 'Dark mode'"
                                @click="toggleDarkMode"
                            >
                                <svg
                                    v-if="isDarkMode"
                                    class="h-5 w-5 text-amber-500"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="12" cy="12" r="4" />
                                    <path
                                        stroke-linecap="round"
                                        d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5 text-indigo-500"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M20.9 13A8.5 8.5 0 1111 3.1 6.8 6.8 0 0020.9 13z"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(menu, menuIndex) in navigationMenus"
                            :key="menuIndex"
                            class="rounded-2xl bg-gray-50/80 p-2"
                        >
                            <div class="flex items-center gap-1">
                                <Link
                                    v-if="menu.route"
                                    :href="route(menu.route)"
                                    class="flex min-h-11 flex-1 items-center rounded-xl px-3 text-left text-sm font-semibold text-gray-800 transition hover:bg-white hover:text-indigo-700 dark:hover:bg-gray-700 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                    @click="closeMobileMenu"
                                >
                                    {{ menu.label }}
                                </Link>
                                <button
                                    type="button"
                                    :aria-label="
                                        menu.route
                                            ? `Toggle ${menu.label} submenu`
                                            : null
                                    "
                                    :aria-expanded="
                                        openMobileMenu === menuIndex
                                    "
                                    class="flex min-h-11 items-center rounded-xl text-left text-sm font-semibold text-gray-800 transition hover:bg-white hover:text-indigo-700 dark:hover:bg-gray-700 dark:hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                    :class="
                                        menu.route
                                            ? 'px-3'
                                            : 'w-full justify-between px-3'
                                    "
                                    @click="toggleMobileDropdown(menuIndex)"
                                >
                                    <span v-if="!menu.route">{{
                                        menu.label
                                    }}</span>
                                    <svg
                                        class="h-4 w-4 text-gray-400 transition-transform duration-200"
                                        :class="
                                            openMobileMenu === menuIndex
                                                ? 'rotate-180'
                                                : ''
                                        "
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20"
                                        fill="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M5.22 7.47a.75.75 0 011.06 0L10 11.19l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 8.53a.75.75 0 010-1.06z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <div
                                v-show="openMobileMenu === menuIndex"
                                class="space-y-1 px-1 pb-1 pt-1"
                            >
                                <AppNavbarMenuItem
                                    v-for="(item, itemIndex) in menu.items"
                                    :key="item.label"
                                    :item="item"
                                    :item-key="`${menuIndex}/${itemIndex}`"
                                    mode="mobile"
                                    :submenu-open="mobileSubmenuIsOpen"
                                    :open-submenu="openDesktopSubmenu"
                                    :close-submenu="closeDesktopSubmenu"
                                    :toggle-submenu="toggleMobileSubmenu"
                                    :close-navigation="closeNavigation"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-3 mt-3">
                        <Link
                            :href="route('profile.edit')"
                            @click="closeMobileMenu"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-all duration-200"
                        >
                            <svg
                                class="h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                                />
                            </svg>
                            Profile
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            @click="closeMobileMenu"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 transition-all duration-200"
                        >
                            <svg
                                class="h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H21"
                                />
                            </svg>
                            Log Out
                        </Link>
                    </div>
                </template>

                <template v-else>
                    <Link
                        :href="route('login')"
                        @click="closeMobileMenu"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-all duration-200"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="route('register')"
                        @click="closeMobileMenu"
                        class="flex items-center rounded-lg px-3 py-2.5 text-sm font-medium text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 transition-all duration-200"
                    >
                        Register
                    </Link>
                </template>
            </div>
        </div>
    </Transition>

    <div class="h-16"></div>
</template>
