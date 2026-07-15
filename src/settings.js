import { createApp, h } from 'vue'
import AdminSettings from './components/AdminSettings.vue'
import { installTexts } from './l10n/texts'
import './styles/tokens.css'

let mountAttempts = 0

const mountAdminSettings = () => {
	const appEl = document.getElementById('cobudget-settings')
	if (!appEl) {
		if (mountAttempts < 20) {
			mountAttempts += 1
			window.setTimeout(mountAdminSettings, 50)
		}
		return
	}

	if (appEl.dataset.cobudgetMounted === 'true') {
		return
	}

	appEl.dataset.cobudgetMounted = 'true'
	const app = createApp({
		name: 'CoBudgetSettingsEntry',
		render: () => h(AdminSettings)
	})
	app.mixin({ methods: { t: window.t, n: window.n } })
	installTexts(app)
	app.mount(appEl)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mountAdminSettings, { once: true })
} else {
	mountAdminSettings()
}
