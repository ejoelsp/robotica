import test from 'node:test'
import assert from 'node:assert/strict'
import { panelThemes, initializePanelTheme } from '../lib/panelTheme.js'

function browser(path = '/competidor/mis-inscripciones') {
  const values = new Map()
  globalThis.document = { documentElement: { dataset: {} } }
  globalThis.window = {
    location: { pathname: path },
    localStorage: { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) },
  }
  return values
}

test('Cada panel conserva su tema sin cambiar la preferencia del otro rol', () => {
  const values = browser()
  panelThemes.admin.save('dark')
  panelThemes.competidor.save('light')
  assert.equal(values.get(panelThemes.admin.storageKey), 'dark')
  assert.equal(values.get(panelThemes.competidor.storageKey), 'light')
  panelThemes.admin.clear()
  assert.equal(document.documentElement.dataset.adminTheme, undefined)
  assert.equal(document.documentElement.dataset.competidorTheme, 'light')
  panelThemes.competidor.save('dark')
  panelThemes.competidor.clear()
  assert.equal(document.documentElement.dataset.competidorTheme, undefined)
  assert.equal(panelThemes.competidor.read(), 'dark')
})

test('El tema del competidor se recupera en sus módulos y en Mi perfil', () => {
  for (const path of ['/competidor/mis-inscripciones', '/competidor/notificaciones', '/competidor/resultados', '/competidor/certificados', '/competidor/reclamos', '/profile']) {
    const values = browser(path)
    values.set(panelThemes.competidor.storageKey, 'dark')
    initializePanelTheme()
    assert.equal(document.documentElement.dataset.competidorTheme, 'dark', path)
    assert.equal(document.documentElement.dataset.adminTheme, undefined, path)
  }
})

test('La inicialización reconoce administración y no aplica temas a páginas públicas o de jueces', () => {
  for (const path of ['/', '/login', '/register', '/juez/profile', '/competidor-falso', '/profile-falso']) {
    browser(path)
    initializePanelTheme()
    assert.deepEqual(document.documentElement.dataset, {}, path)
  }
  const values = browser('/admin/dashboard')
  values.set(panelThemes.admin.storageKey, 'dark')
  initializePanelTheme()
  assert.equal(document.documentElement.dataset.adminTheme, 'dark')
  assert.equal(document.documentElement.dataset.competidorTheme, undefined)
})

test('El selector del competidor funciona con almacenamiento bloqueado y valores inválidos', () => {
  const values = browser()
  values.set(panelThemes.competidor.storageKey, 'invalid')
  assert.equal(panelThemes.competidor.read(), 'light')
  window.localStorage.getItem = () => { throw new Error('Blocked') }
  window.localStorage.setItem = () => { throw new Error('Blocked') }
  assert.equal(panelThemes.competidor.read(), 'light')
  assert.doesNotThrow(() => panelThemes.competidor.save('dark'))
  assert.equal(document.documentElement.dataset.competidorTheme, 'dark')
})
