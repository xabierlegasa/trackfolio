<template>
  <div class="min-h-screen bg-base-200">
    <Header />
    <RouterView />
  </div>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import Header from './components/Header.vue'
</script>

