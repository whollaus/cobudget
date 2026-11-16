import { loadState } from '@nextcloud/initial-state'
import { applyNextcloudHeaderVisibility } from './services/nextcloudHeader'
import { applyThemeMode } from './services/themeMode'

// Nextcloud executes init scripts before the application. Only visual
// preferences belong here; all application data stays behind the regular API.
const preferences = loadState('cobudget', 'appearance', {})
applyNextcloudHeaderVisibility(preferences)
applyThemeMode(preferences.themeMode)
document.documentElement.classList.add('cobudget-appearance-ready')
