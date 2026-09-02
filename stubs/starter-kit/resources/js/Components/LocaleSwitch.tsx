import { router, usePage } from '@inertiajs/react'

type LocaleProps = {
  current: string
  enabled: {
    value: string
    label: string
  }[]
}

type PageProps = {
  locale: LocaleProps
}

export default function LocaleSwitch() {
  const { locale } = usePage<PageProps>().props

  if (locale.enabled.length < 2) {
    return null
  }

  return (
    <label>
      <span>Language</span>{' '}
      <select value={locale.current} onChange={(event) => changeLocale(event.target.value)}>
        {locale.enabled.map((enabledLocale) => (
          <option key={enabledLocale.value} value={enabledLocale.value}>
            {enabledLocale.label}
          </option>
        ))}
      </select>
    </label>
  )
}

function changeLocale(locale: string) {
  router.post('/locale', { locale }, { reset: ['locale'] })
}
