const plugin = require('tailwindcss/plugin')

// Adapta las utilidades existentes dentro de los paneles con tema oscuro.
// Tailwind genera únicamente las clases usadas, incluidas hover, focus, file
// y variantes responsive. Los modales Teleport a body comparten el mismo tema.
module.exports = plugin(({ addBase, addUtilities, e, theme }) => {
  const scope = ':where(html[data-admin-theme="dark"], html[data-competidor-theme="dark"])'
  const utilities = {}
  const opacities = [0, 5, 10, 15, 20, 25, 30, 40, 50, 60, 70, 75, 80, 85, 90, 95, 100]
  const rgb = (hex) => hex.replace('#', '').match(/.{2}/g).map((part) => parseInt(part, 16)).join(' ')

  function colorUtility(prefix, color, hex, declarations, suffix = '') {
    const channels = rgb(hex)
    for (const opacity of [null, ...opacities]) {
      const name = `${prefix}-${color}${opacity === null ? '' : `/${opacity}`}`
      const opacityVariable = { text: 'text', bg: 'bg', border: 'border', divide: 'divide', ring: 'ring' }[prefix]
      const alpha = opacity === null ? (opacityVariable ? `var(--tw-${opacityVariable}-opacity, 1)` : 1) : opacity / 100
      const value = `rgb(${channels} / ${alpha})`
      utilities[`${scope} .${e(name)}${suffix}`] = declarations(value, channels)
    }
  }

  function background(color, hex) {
    colorUtility('bg', color, hex, (value) => ({ backgroundColor: value }))
    colorUtility('from', color, hex, (value, channels) => ({
      '--tw-gradient-from': `${value} var(--tw-gradient-from-position)`,
      '--tw-gradient-to': `rgb(${channels} / 0) var(--tw-gradient-to-position)`,
      '--tw-gradient-stops': 'var(--tw-gradient-from), var(--tw-gradient-to)',
    }))
    colorUtility('via', color, hex, (value, channels) => ({
      '--tw-gradient-to': `rgb(${channels} / 0) var(--tw-gradient-to-position)`,
      '--tw-gradient-stops': `var(--tw-gradient-from), ${value} var(--tw-gradient-via-position), var(--tw-gradient-to)`,
    }))
    colorUtility('to', color, hex, (value) => ({ '--tw-gradient-to': `${value} var(--tw-gradient-to-position)` }))
  }

  function border(color, hex) {
    colorUtility('border', color, hex, (value) => ({ borderColor: value }))
    colorUtility('divide', color, hex, (value) => ({ borderColor: value }), ' > :not([hidden]) ~ :not([hidden])')
    colorUtility('ring', color, hex, (value) => ({ '--tw-ring-color': value }))
  }

  background('white', '#162235')
  border('white', '#334155')
  colorUtility('text', 'black', '#f1f5f9', (value) => ({ color: value }))

  for (const family of ['slate', 'gray', 'zinc', 'neutral', 'stone']) {
    for (const [shade, value] of Object.entries({ 50: '#111b2e', 100: '#1c2b40', 200: '#26374e', 300: '#334155' })) {
      background(`${family}-${shade}`, value)
    }
    for (const shade of [50, 100, 200, 300, 400]) border(`${family}-${shade}`, '#334155')
    for (const [shade, value] of Object.entries({ 500: '#9aacc2', 600: '#b6c2d3', 700: '#cbd5e1', 800: '#e2e8f0', 900: '#f1f5f9', 950: '#f8fafc' })) {
      colorUtility('text', `${family}-${shade}`, value, (color) => ({ color }))
    }
  }

  // Los estados conservan su color semántico sobre superficies oscuras.
  const accents = ['blue', 'indigo', 'violet', 'purple', 'sky', 'cyan', 'teal', 'emerald', 'green', 'lime', 'yellow', 'amber', 'orange', 'red', 'rose', 'pink', 'fuchsia']
  for (const family of accents) {
    const palette = theme(`colors.${family}`)
    for (const [shade, alpha] of [[50, 0.18], [100, 0.25], [200, 0.38], [300, 0.5]]) {
      const base = [11, 18, 32]
      const accent = rgb(palette[600]).split(' ').map(Number)
      const hex = '#' + accent.map((channel, index) => Math.round(base[index] * (1 - alpha) + channel * alpha).toString(16).padStart(2, '0')).join('')
      background(`${family}-${shade}`, hex)
      border(`${family}-${shade}`, palette[800])
    }
    for (const shade of [500, 600, 700, 800, 900, 950]) {
      colorUtility('text', `${family}-${shade}`, palette[shade === 500 ? 400 : 300], (value) => ({ color: value }))
    }
  }

  addBase({
    'html[data-admin-theme="dark"], html[data-competidor-theme="dark"]': { colorScheme: 'dark', backgroundColor: '#0b1220', scrollbarColor: '#475569 #111b2e' },
    [`${scope} body, ${scope} #app`]: { backgroundColor: '#0b1220', color: '#f1f5f9' },
    [`${scope} *, ${scope} ::before, ${scope} ::after`]: { borderColor: '#334155' },
    [`${scope} :where(input, select, textarea)`]: { color: '#f1f5f9' },
    [`${scope} :where(input, textarea)::placeholder`]: { color: '#94a3b8', opacity: '1' },
    [`${scope} select option`]: { backgroundColor: '#162235', color: '#f1f5f9' },
    [`${scope} input:-webkit-autofill`]: { '-webkit-text-fill-color': '#f1f5f9', '-webkit-box-shadow': '0 0 0 1000px #111b2e inset', caretColor: '#f1f5f9' },
    [`${scope} ::-webkit-scrollbar-track`]: { backgroundColor: '#111b2e' },
    [`${scope} ::-webkit-scrollbar-thumb`]: { backgroundColor: '#475569', borderColor: '#111b2e' },
  })
  addUtilities(utilities)
})
