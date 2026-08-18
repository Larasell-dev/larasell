import { Head, Link } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import Icon from '../../Components/Icon'

type Props = AdminLayoutProps & { membersUrl: string }

export default function SettingsIndex({ membersUrl, ...layoutProps }: Props) {
  return (
    <AdminLayout active="settings" {...layoutProps}>
      <Head title="Settings" />
      <div {...stylex.props(styles.page)}>
        <header {...stylex.props(styles.header)}>
          <h1 {...stylex.props(styles.heading)}>Settings</h1>
        </header>
        <nav aria-label="Settings">
          <Link href={membersUrl} {...stylex.props(styles.moduleLink)}>
            <span {...stylex.props(styles.icon)}><Icon name="users" height={20} width={20} /></span>
            <span {...stylex.props(styles.copy)}>
              <strong {...stylex.props(styles.title)}>Members</strong>
              <span {...stylex.props(styles.description)}>Manage who can access Larasell admin.</span>
            </span>
            <Icon name="chevron-right" height={18} width={18} />
          </Link>
        </nav>
      </div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { marginInline: 'auto', maxWidth: 800, paddingBlock: 32, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  header: { marginBottom: 24 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, margin: 0 },
  moduleLink: { alignItems: 'center', color: 'var(--color-neutral-900)', display: 'flex', gap: 14, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, paddingBlock: 16, paddingInline: 4, textDecoration: 'none' },
  icon: { alignItems: 'center', backgroundColor: 'var(--color-neutral-100)', borderRadius: 6, display: 'flex', flexShrink: 0, height: 40, justifyContent: 'center', width: 40 },
  copy: { display: 'grid', flex: 1, gap: 2 },
  title: { fontSize: 15, fontWeight: 650 },
  description: { color: 'var(--color-neutral-500)', fontSize: 14 },
})
