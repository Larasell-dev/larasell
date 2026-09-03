export type ReplacementValue = string | number | boolean | null | undefined

export type Replacements = Record<string, ReplacementValue>

export type TranslationValue = string | TranslationMessages

export type TranslationMessages = {
  [key: string]: TranslationValue
}

export type TranslationModule = {
  default: TranslationMessages
}

export type TranslationLoader = () => Promise<TranslationModule>

export type TranslationLoaders = Record<string, Record<string, TranslationLoader>>

export type CreateI18nOptions = {
  locale: string
  fallbackLocale?: string
  loaders: TranslationLoaders
}

export type I18n = {
  choice(key: string, count: number, replacements?: Replacements): string
  getLocale(): string
  has(key: string): boolean
  load(namespace: string | string[]): Promise<void>
  setLocale(locale: string): Promise<void>
  t(key: string, replacements?: Replacements): string
}
