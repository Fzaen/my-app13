<script setup>
const appBasePath =
    document.querySelector('meta[name="app-base-path"]')?.content ?? "";
const logoUrl = `${appBasePath}/images/spi.png`;
</script>

<template>
    <img :src="logoUrl" alt="SPI" class="object-contain" />
</template>
