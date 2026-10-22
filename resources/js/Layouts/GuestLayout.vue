<script setup>
import AppNavbar from "@/Components/AppNavbar.vue";
import BrandLogo from "@/Components/BrandLogo.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <AppNavbar />

        <div
            class="flex min-h-screen flex-col items-center pt-20 sm:justify-center sm:pt-24"
        >
            <div>
                <Link href="/">
                    <BrandLogo class="h-20 w-20" />
                </Link>
            </div>

            <div
                class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
            >
                <slot />
            </div>
        </div>
    </div>
</template>
