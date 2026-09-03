import { createContext, createElement, useContext, type ComponentType, type ReactElement } from 'react'
import { createI18n, type I18n } from 'virtual:laravel-i18n'

type SharedProps = Record<string, unknown> & {
  fallbackLocale: string
  locale: string
}

type InertiaPage = {
  component: string
  props: SharedProps
}

type PageComponent = ComponentType & {
  translation?: string[]
}

type PageModule = {
  default: PageComponent
}

type PageImporter = () => Promise<unknown>

type InertiaI18nOptions = {
  global?: string[]
}

const I18nContext = createContext<I18n | null>(null)

export function inertiaI18n(options: InertiaI18nOptions = {}) {
  const pageI18n = Symbol('pageI18n')
  let browserI18n: I18n | undefined

  return {
    resolve(pages: Record<string, PageImporter>) {
      return async (name: string, page?: unknown): Promise<PageComponent> => {
        const loadPage = pages[`./Pages/${name}.tsx`]

        if (!loadPage) {
          throw new Error(`Unknown Inertia page [${name}].`)
        }

        if (!isInertiaPage(page)) {
          throw new Error(`Inertia did not provide page props while resolving [${name}].`)
        }

        const module = await loadPage()

        if (!isPageModule(module)) {
          throw new Error(`Inertia page [${name}] does not have a default component export.`)
        }

        const i18n = typeof window === 'undefined'
          ? createI18n(page.props)
          : browserI18n ??= createI18n(page.props)

        await i18n.load([...(options.global ?? []), ...(module.default.translation ?? [])])
        setPageI18n(page, pageI18n, i18n)

        return module.default
      }
    },

    withApp(app: ReactElement, { page }: { page: unknown }): ReactElement {
      if (!isInertiaPage(page)) {
        throw new Error('Inertia did not provide page props while rendering.')
      }

      const i18n = getPageI18n(page, pageI18n)

      if (!i18n) {
        throw new Error('Translations were not loaded before rendering.')
      }

      return createElement(I18nContext.Provider, { value: i18n }, app)
    },
  }
}

export function useI18n(): I18n {
  const i18n = useContext(I18nContext)

  if (!i18n) {
    throw new Error('useI18n must be used within an i18n-enabled Inertia application.')
  }

  return i18n
}

function setPageI18n(page: InertiaPage, key: symbol, i18n: I18n): void {
  Object.defineProperty(page, key, { value: i18n })
}

function getPageI18n(page: InertiaPage, key: symbol): I18n | undefined {
  return (page as InertiaPage & Record<symbol, I18n | undefined>)[key]
}

function isInertiaPage(page: unknown): page is InertiaPage {
  if (typeof page !== 'object' || page === null || !('props' in page)) {
    return false
  }

  const props = page.props

  return typeof props === 'object'
    && props !== null
    && 'locale' in props
    && typeof props.locale === 'string'
    && 'fallbackLocale' in props
    && typeof props.fallbackLocale === 'string'
}

function isPageModule(module: unknown): module is PageModule {
  return typeof module === 'object'
    && module !== null
    && 'default' in module
    && typeof module.default === 'function'
}
