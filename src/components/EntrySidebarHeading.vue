<template>
	<h2 id="entry-sidebar-title" :ref="setHeading" class="app-sidebar-header__mainname" tabindex="-1"><slot /></h2>
</template>

<script setup>
import { inject } from 'vue'

// NcAppSidebar's public focus() method targets this provided heading ref.
const sidebarHeading = inject('NcAppSidebar:header:ref', null)
function setHeading(element) {
	if (sidebarHeading) sidebarHeading.value = element
}
</script>
