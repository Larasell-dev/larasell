import { readdirSync } from 'node:fs'
import { resolve } from 'node:path'

export type TranslationFile = {
  file: string
  locale: string
  namespace: string
}

export function discoverTranslations(langPath: string): TranslationFile[] {
  let localeEntries

  try {
    localeEntries = readdirSync(langPath, { withFileTypes: true })
  } catch {
    return []
  }

  return localeEntries
    .filter((entry) => entry.isDirectory())
    .flatMap((locale) => readdirSync(resolve(langPath, locale.name), { withFileTypes: true })
      .filter((entry) => entry.isFile() && entry.name.endsWith('.php'))
      .map((entry) => ({
        file: resolve(langPath, locale.name, entry.name),
        locale: locale.name,
        namespace: entry.name.slice(0, -4),
      })))
    .sort((a, b) => `${a.locale}/${a.namespace}`.localeCompare(`${b.locale}/${b.namespace}`))
}
