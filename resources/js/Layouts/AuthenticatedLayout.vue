<script setup>
import AppNavbar from "@/Components/AppNavbar.vue";
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <AppNavbar />

        <!-- Page Heading -->
        <header class="bg-white shadow" v-if="$slots.header">
            <div class="px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>
