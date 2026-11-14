import test from 'node:test'
import assert from 'node:assert/strict'
import postcss from 'postcss'
import tailwind from 'tailwindcss'
import adminTheme from '../styles/admin-theme.cjs'
import {
  ADMIN_THEME_STORAGE_KEY, applyAdminTheme, clearAdminTheme,
  initializeAdminTheme, readAdminTheme, saveAdminTheme,
} from '../lib/adminTheme.js'

function browser(path = '/admin/competencias', stored = null) {
  const values = new Map(stored === null ? [] : [[ADMIN_THEME_STORAGE_KEY, stored]])
  globalThis.document = { documentElement: { dataset: {} } }
  globalThis.window = {
    location: { pathname: path },
    localStorage: {
      getItem: (key) => values.get(key) ?? null,
      setItem: (key, value) => values.set(key, value),
    },
  }
  return values
}

test('El administrador inicia en claro y recupera únicamente preferencias válidas', () => {
  browser()
  initializeAdminTheme()
  assert.equal(document.documentElement.dataset.adminTheme, 'light')
  browser('/admin/profile', 'dark')
  initializeAdminTheme()
  assert.equal(document.documentElement.dataset.adminTheme, 'dark')
  browser('/admin/competencias', 'invalid')
  assert.equal(readAdminTheme(), 'light')
})

test('El cambio se aplica, se conserva y el tema se retira al abandonar el layout', () => {
  const values = browser()
  saveAdminTheme('dark')
  assert.equal(document.documentElement.dataset.adminTheme, 'dark')
  assert.equal(values.get(ADMIN_THEME_STORAGE_KEY), 'dark')
  clearAdminTheme()
  assert.equal(document.documentElement.dataset.adminTheme, undefined)
  assert.equal(readAdminTheme(), 'dark')
  saveAdminTheme('light')
  assert.equal(values.get(ADMIN_THEME_STORAGE_KEY), 'light')
})

test('La inicialización no cambia las páginas públicas ni los otros roles', () => {
  for (const path of ['/', '/login', '/juez/profile', '/competidor/dashboard', '/administrador']) {
    browser(path, 'dark')
    initializeAdminTheme()
    assert.equal(document.documentElement.dataset.adminTheme, undefined, path)
  }
})

test('El selector sigue funcionando con el almacenamiento bloqueado', () => {
  browser()
  window.localStorage.getItem = () => { throw new Error('Storage blocked') }
  window.localStorage.setItem = () => { throw new Error('Storage blocked') }
  assert.equal(readAdminTheme(), 'light')
  assert.doesNotThrow(() => saveAdminTheme('dark'))
  assert.equal(document.documentElement.dataset.adminTheme, 'dark')
  applyAdminTheme('unexpected')
  assert.equal(document.documentElement.dataset.adminTheme, 'light')
})

test('Tailwind conserva el claro y genera superficies, estados y variantes oscuras aisladas', async () => {
  const { root } = await postcss([tailwind({
    content: [{ raw: 'bg-white bg-white/90 text-slate-900 text-blue-700 border-slate-200 hover:bg-slate-50 focus:text-blue-600 sm:bg-slate-50 file:bg-slate-100 from-blue-50 to-white divide-slate-200 ring-blue-200', extension: 'html' }],
    plugins: [adminTheme],
  })]).process('@tailwind base; @tailwind utilities;', { from: undefined })
  const rules = []
  root.walkRules((rule) => rules.push(rule))
  const light = rules.find((rule) => rule.selector === '.bg-white')
  assert.ok(light.toString().includes('255 255 255'))
  const dark = rules.find((rule) => rule.selector === ':where(html[data-admin-theme="dark"], html[data-competidor-theme="dark"]) .bg-white')
  assert.ok(dark.toString().includes('22 34 53'))
  const darkRules = rules.filter((rule) => rule.selector.includes('data-admin-theme'))
  for (const selector of ['.bg-white\\/90', '.hover\\:bg-slate-50:hover', '.focus\\:text-blue-600:focus', '.sm\\:bg-slate-50', '.file\\:bg-slate-100::file-selector-button', '.from-blue-50', '.to-white', '.divide-slate-200', '.ring-blue-200']) {
    assert.ok(darkRules.some((rule) => rule.selector.includes(selector)), `Falta variante ${selector}`)
  }
})
