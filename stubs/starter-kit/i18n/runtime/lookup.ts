import type { TranslationMessages } from './types'

export function lookup(messages: TranslationMessages | undefined, key: string): string | undefined {
  let value: unknown = messages

  for (const segment of key.split('.')) {
    if (typeof value !== 'object' || value === null || !Object.prototype.hasOwnProperty.call(value, segment)) {
      return undefined
    }

    value = (value as Record<string, unknown>)[segment]
  }

  return typeof value === 'string' ? value : undefined
}
