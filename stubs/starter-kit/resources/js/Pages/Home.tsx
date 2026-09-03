import { Head } from '@inertiajs/react'
import { useI18n } from '../i18n'

function Home() {
  const { t } = useI18n()

  return (
    <main>
      <Head title={t('home.title')} />
      <h1>{t('home.heading')}</h1>
    </main>
  )
}

Home.translation = ['home']

export default Home
