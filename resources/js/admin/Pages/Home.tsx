import { router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'

type HomeProps = {
  logoutUrl: string
}

export default function Home({ logoutUrl }: HomeProps) {
  function logout() {
    router.post(logoutUrl)
  }

  return (
    <main {...stylex.props(styles.page)}>
      <section {...stylex.props(styles.section)}>
        <h1 {...stylex.props(styles.heading)}>Larasell Admin</h1>

        <button
          onClick={logout}
          {...stylex.props(styles.button)}
          type="button"
        >
          Sign out
        </button>
      </section>
    </main>
  )
}

const styles = stylex.create({
  page: {
    background: '#f6f5f2',
    color: '#202020',
    display: 'grid',
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
    paddingBottom: 0,
    paddingLeft: 16,
    paddingRight: 16,
    paddingTop: 0,
    placeItems: 'center',
  },
  section: {
    alignItems: 'center',
    display: 'flex',
    gap: 24,
    justifyContent: 'space-between',
    maxWidth: 760,
    width: '100%',
  },
  heading: {
    fontSize: 28,
    fontWeight: 650,
    margin: 0,
  },
  button: {
    background: '#202020',
    border: 0,
    borderRadius: 6,
    color: '#fff',
    cursor: 'pointer',
    font: 'inherit',
    fontWeight: 600,
    paddingBottom: 10,
    paddingLeft: 14,
    paddingRight: 14,
    paddingTop: 10,
  },
})
