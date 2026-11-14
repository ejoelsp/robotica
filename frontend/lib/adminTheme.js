import { panelThemes } from './panelTheme.js'

export const ADMIN_THEME_STORAGE_KEY = panelThemes.admin.storageKey
export const readAdminTheme = panelThemes.admin.read
export const applyAdminTheme = panelThemes.admin.apply
export const saveAdminTheme = panelThemes.admin.save
export const clearAdminTheme = panelThemes.admin.clear

export function initializeAdminTheme() {
  if (typeof window !== 'undefined' && /^\/admin(?:\/|$)/.test(window.location.pathname)) {
    applyAdminTheme(readAdminTheme())
  }
}
