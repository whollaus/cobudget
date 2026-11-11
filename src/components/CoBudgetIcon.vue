<template>
	<span aria-hidden="true" class="cobudget-app-icon" :style="{ width: `${size}px`, height: `${size}px`, maskImage: `url(${iconUrl})` }" />
</template>
<script>
import { imagePath } from '@nextcloud/router'
export default {
	name: 'CoBudgetIcon',
	props: { size: { type: Number, default: 20 } },
	data: () => ({ iconUrl: imagePath('cobudget', 'app.svg') }),
}
</script>
<style scoped>
/* Match the outline glyphs' visible height within the native 20 px icon box. */
.cobudget-app-icon { display: inline-block; flex-shrink: 0; background: currentColor; mask-position: center; mask-size: 112%; mask-repeat: no-repeat; }
</style>
