import type { Replacements, ReplacementValue } from './types'

function stringify(value: ReplacementValue): string {
  if (value === null || value === undefined || value === false) {
    return ''
  }

  if (value === true) {
    return '1'
  }

  return String(value)
}

function ucfirst(value: string, locale: string): string {
  const [first = '', ...rest] = Array.from(value)

  return first.toLocaleUpperCase(locale) + rest.join('')
}

export function interpolate(line: string, replacements: Replacements = {}, locale = 'en'): string {
  const replace = new Map<string, string>()
  const casingLocale = locale.replace('_', '-')

  for (const [key, rawValue] of Object.entries(replacements)) {
    const value = stringify(rawValue)

    replace.set(`:${key.charAt(0).toUpperCase()}${key.slice(1)}`, ucfirst(value, casingLocale))
    replace.set(`:${key.toLocaleUpperCase(casingLocale)}`, value.toLocaleUpperCase(casingLocale))
    replace.set(`:${key}`, value)
  }

  if (replace.size === 0) {
    return line
  }

  const keys = [...replace.keys()].sort((a, b) => b.length - a.length)
  const pattern = new RegExp(keys.map(escapeRegExp).join('|'), 'g')

  return line.replace(pattern, (placeholder) => replace.get(placeholder) ?? placeholder)
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
