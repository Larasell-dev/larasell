import { choose } from './choice'
import { interpolate } from './interpolate'
import { lookup } from './lookup'
import type { CreateI18nOptions, I18n, Replacements, TranslationMessages } from './types'

export function createI18n(options: CreateI18nOptions): I18n {
  let locale = options.locale
  const fallbackLocale = options.fallbackLocale ?? options.locale
  const messages: Record<string, Record<string, TranslationMessages>> = {}
  const requested = new Set<string>()
  const loaded = new Set<string>()
  const pending = new Map<string, Promise<void>>()

  function locales(): string[] {
    return [...new Set([locale, fallbackLocale])]
  }

  function resolve(key: string): { line: string, locale: string } | undefined {
    const [namespace, ...segments] = key.split('.')

    if (!namespace || segments.length === 0) {
      return undefined
    }

    const item = segments.join('.')

    for (const candidate of locales()) {
      const value = lookup(messages[candidate]?.[namespace], item)

      if (value !== undefined) {
        return { line: value, locale: candidate }
      }
    }

    return undefined
  }

  async function loadForLocale(targetLocale: string, namespace: string): Promise<void> {
    const key = `${targetLocale}/${namespace}`

    if (loaded.has(key)) {
      return
    }

    const existing = pending.get(key)

    if (existing) {
      return existing
    }

    const loader = options.loaders[targetLocale]?.[namespace]

    if (!loader) {
      return
    }

    const promise = loader().then((module) => {
      messages[targetLocale] ??= {}
      messages[targetLocale][namespace] = module.default
      loaded.add(key)
    }).finally(() => {
      pending.delete(key)
    })

    pending.set(key, promise)

    return promise
  }

  async function load(namespaces: string | string[]): Promise<void> {
    const uniqueNamespaces = [...new Set(Array.isArray(namespaces) ? namespaces : [namespaces])]

    await Promise.all(uniqueNamespaces.map(async (namespace) => {
      requested.add(namespace)

      const candidates = locales().filter((candidate) => options.loaders[candidate]?.[namespace])

      if (candidates.length === 0) {
        throw new Error(`Unknown translation namespace [${namespace}] for locale [${locale}].`)
      }

      await Promise.all(candidates.map((candidate) => loadForLocale(candidate, namespace)))
    }))
  }

  return {
    async load(namespaces) {
      await load(namespaces)
    },

    t(key, replacements = {}) {
      return interpolate(resolve(key)?.line ?? key, replacements, locale)
    },

    choice(key: string, count: number, replacements: Replacements = {}) {
      const translation = resolve(key)

      if (translation === undefined) {
        return interpolate(key, replacements, locale)
      }

      return interpolate(
        choose(translation.line, count, translation.locale),
        { count, ...replacements },
        translation.locale,
      )
    },

    has(key) {
      return resolve(key) !== undefined
    },

    getLocale() {
      return locale
    },

    async setLocale(nextLocale) {
      locale = nextLocale
      await load([...requested])
    },
  }
}
