<template>
  <div class="min-h-screen bg-base-200">
    <Header />
    <BackToDashboard />
    <RouterView />
  </div>
</template>

<script setup lang="ts">
import { RouterView } from 'vue-router'
import Header from './components/Header.vue'
import BackToDashboard from './components/BackToDashboard.vue'
</script>

