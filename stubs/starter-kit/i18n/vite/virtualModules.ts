import type { TranslationFile } from './discover'

export const publicRuntimeId = 'virtual:laravel-i18n'
export const resolvedRuntimeId = `\0${publicRuntimeId}`
export const translationPrefix = `${publicRuntimeId}/`
export const resolvedTranslationPrefix = `\0${translationPrefix}`

export function translationId(locale: string, namespace: string): string {
  return `${translationPrefix}${locale}/${namespace}`
}

export function runtimeModule(files: TranslationFile[], runtimeImport: string): string {
  const locales: Record<string, Record<string, string>> = {}

  for (const file of files) {
    locales[file.locale] ??= {}
    locales[file.locale][file.namespace] = translationId(file.locale, file.namespace)
  }

  const entries = Object.entries(locales).map(([locale, namespaces]) => {
    const loaders = Object.entries(namespaces)
      .map(([namespace, id]) => `${JSON.stringify(namespace)}: () => import(${JSON.stringify(id)})`)
      .join(',\n')

    return `${JSON.stringify(locale)}: {${loaders}}`
  }).join(',\n')

  return [
    `import { createI18n as createRuntimeI18n } from ${JSON.stringify(runtimeImport)};`,
    `const loaders = {${entries}};`,
    'export const createI18n = (options) => createRuntimeI18n({ ...options, loaders });',
  ].join('\n')
}
