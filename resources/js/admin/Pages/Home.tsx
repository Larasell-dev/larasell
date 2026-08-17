import * as stylex from '@stylexjs/stylex'
import { useState } from 'react'
import DropdownMenu from '../Components/DropdownMenu'
import LogoutDialog from '../Components/LogoutDialog'

type HomeProps = {
  logoutUrl: string
  user: {
    name: string
    email: string
  }
}

export default function Home({ logoutUrl, user }: HomeProps) {
  const [logoutOpen, setLogoutOpen] = useState(false)

  return (
    <div {...stylex.props(styles.page)}>
      <aside {...stylex.props(styles.sidebar)}>
        <div {...stylex.props(styles.account)}>
          <DropdownMenu
            items={[{ label: 'Log out', onClick: () => setLogoutOpen(true) }]}
            trigger={
              <button type="button" {...stylex.props(styles.userTrigger)}>
                <span aria-hidden="true" {...stylex.props(styles.avatar)}>
                  {initials(user.name)}
                </span>
                <span {...stylex.props(styles.userDetails)}>
                  <strong {...stylex.props(styles.userName)}>{user.name}</strong>
                  <span {...stylex.props(styles.userEmail)}>{user.email}</span>
                </span>
              </button>
            }
          />
        </div>
      </aside>

      <main {...stylex.props(styles.main)} />

      <LogoutDialog logoutUrl={logoutUrl} onOpenChange={setLogoutOpen} open={logoutOpen} />
    </div>
  )
}

function initials(name: string) {
  return name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
}

const styles = stylex.create({
  page: {
    background: 'var(--color-neutral-50)',
    color: 'var(--color-neutral-950)',
    display: 'flex',
    flexDirection: { default: 'row', '@media (max-width: 640px)': 'column' },
    fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    minHeight: '100vh',
  },
  sidebar: {
    backgroundColor: '#fff',
    borderRightColor: 'var(--color-neutral-200)',
    borderRightStyle: 'solid',
    borderRightWidth: { default: 1, '@media (max-width: 640px)': 0 },
    borderBottomColor: 'var(--color-neutral-200)',
    borderBottomStyle: 'solid',
    borderBottomWidth: { default: 0, '@media (max-width: 640px)': 1 },
    display: 'flex',
    flexDirection: 'column',
    minHeight: { default: '100vh', '@media (max-width: 640px)': 'auto' },
    padding: 16,
    position: { default: 'fixed', '@media (max-width: 640px)': 'relative' },
    width: { default: 256, '@media (max-width: 640px)': '100%' },
  },
  account: {
    marginTop: 'auto',
  },
  userTrigger: {
    alignItems: 'center',
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderRadius: 6,
    display: 'flex',
    gap: 10,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    padding: 8,
    textAlign: 'left',
    width: '100%',
    marginTop: { default: 0, '@media (max-width: 640px)': 12 },
  },
  avatar: {
    alignItems: 'center',
    backgroundColor: 'var(--color-brand-100)',
    borderRadius: 6,
    color: 'var(--color-brand-900)',
    display: 'flex',
    flexShrink: 0,
    fontSize: 12,
    fontWeight: 700,
    height: 36,
    justifyContent: 'center',
    width: 36,
  },
  userDetails: { display: 'grid', flex: 1, minWidth: 0 },
  userName: { fontSize: 14, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  userEmail: { color: 'var(--color-neutral-500)', fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  main: {
    marginLeft: { default: 256, '@media (max-width: 640px)': 0 },
    width: '100%',
  },
})
