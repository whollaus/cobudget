import { onMounted, onUnmounted, ref } from 'vue'
import { localDateKey, millisecondsUntilNextLocalDay } from './entryDefaultDate'

export function useLocalDate() {
	const today = ref(localDateKey(new Date()))
	let timer
	function refresh() {
		const now = new Date()
		today.value = localDateKey(now)
		clearTimeout(timer)
		timer = setTimeout(refresh, millisecondsUntilNextLocalDay(now))
	}
	onMounted(() => {
		refresh()
		document.addEventListener('visibilitychange', refresh)
	})
	onUnmounted(() => {
		clearTimeout(timer)
		document.removeEventListener('visibilitychange', refresh)
	})
	return today
}
