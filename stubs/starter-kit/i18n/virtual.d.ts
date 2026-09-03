declare module 'virtual:laravel-i18n' {
  type ReplacementValue = string | number | boolean | null | undefined
  type Replacements = Record<string, ReplacementValue>

  export type I18n = {
    choice(key: string, count: number, replacements?: Replacements): string
    getLocale(): string
    has(key: string): boolean
    load(namespace: string | string[]): Promise<void>
    setLocale(locale: string): Promise<void>
    t(key: string, replacements?: Replacements): string
  }

  export function createI18n(options: {
    locale: string
    fallbackLocale?: string
  }): I18n
}
