function createTheme(storageKey, datasetKey) {
  const read = () => {
    try {
      return window.localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light'
    } catch {
      return 'light'
    }
  }
  const apply = (theme) => {
    if (typeof document !== 'undefined') document.documentElement.dataset[datasetKey] = theme === 'dark' ? 'dark' : 'light'
  }
  const save = (theme) => {
    const value = theme === 'dark' ? 'dark' : 'light'
    apply(value)
    try {
      window.localStorage.setItem(storageKey, value)
    } catch {
      // El selector funciona también si el navegador bloquea el almacenamiento.
    }
  }
  const clear = () => {
    if (typeof document !== 'undefined') delete document.documentElement.dataset[datasetKey]
  }
  return { storageKey, read, apply, save, clear }
}

export const panelThemes = {
  admin: createTheme('robotica.admin.theme', 'adminTheme'),
  competidor: createTheme('robotica.competidor.theme', 'competidorTheme'),
}

export function initializePanelTheme() {
  if (typeof window === 'undefined') return
  const path = window.location.pathname
  const role = /^\/admin(?:\/|$)/.test(path) ? 'admin'
    : (/^\/competidor(?:\/|$)/.test(path) || /^\/profile(?:\/|$)/.test(path)) ? 'competidor' : null
  if (role) panelThemes[role].apply(panelThemes[role].read())
}
