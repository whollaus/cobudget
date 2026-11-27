import { createApp, h } from 'vue'
import { createRouter, createWebHistory, RouterView } from 'vue-router'
import AdminSettings from './components/AdminSettings.vue'
import { installTexts } from './l10n/texts'
import './styles/tokens.css'
import './styles/surfaces.css'
import './styles/status.css'
import './styles/controls.css'
import './styles/dialogs.css'

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
	const router = createRouter({
		history: createWebHistory(),
		routes: [{ path: window.location.pathname, component: AdminSettings }],
	})
	const app = createApp({
		name: 'CoBudgetSettingsEntry',
		render: () => h(RouterView)
	})
	app.use(router)
	app.mixin({ methods: { t: window.t, n: window.n } })
	installTexts(app)
	app.mount(appEl)
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', mountAdminSettings, { once: true })
} else {
	mountAdminSettings()
}
