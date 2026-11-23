<script setup>
import AppNavbar from "@/Components/AppNavbar.vue";
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <AppNavbar />

        <main>
            <slot />
        </main>
    </div>
</template>
