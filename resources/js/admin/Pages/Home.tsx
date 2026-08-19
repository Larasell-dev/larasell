import { Head } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../Components/AdminLayout'
import Icon, { type IconName } from '../Components/Icon'

const resources: Array<{
  description: string
  href: string
  icon: IconName
  title: string
}> = [
  {
    description: 'Learn how to configure Larasell and build your storefront.',
    href: 'https://docs.larasell.dev',
    icon: 'book',
    title: 'Documentation',
  },
  {
    description: 'Follow development updates, releases, and announcements.',
    href: 'https://x.com/n_haberkamp',
    icon: 'brand-x',
    title: 'Social media',
  },
  {
    description: 'Visit the Larasell website for more about the project.',
    href: 'https://larasell.dev',
    icon: 'world',
    title: 'Website',
  },
  {
    description: 'Get help with Larasell or send us your questions and feedback.',
    href: 'mailto:nils@larasell.dev',
    icon: 'mail',
    title: 'Support',
  },
]

export default function Home(props: AdminLayoutProps) {
  return (
    <AdminLayout active="home" {...props}>
      <Head title="Dashboard" />
      <div {...stylex.props(styles.page)}>
        <header {...stylex.props(styles.header)}>
          <h1 {...stylex.props(styles.heading)}>Welcome, {props.user.name}</h1>
          <p {...stylex.props(styles.intro)}>Helpful resources for working with Larasell.</p>
        </header>
        <nav aria-label="Larasell resources" {...stylex.props(styles.grid)}>
          {resources.map((resource) => (
            <a
              href={resource.href}
              key={resource.title}
              rel={resource.href.startsWith('http') ? 'noreferrer' : undefined}
              target={resource.href.startsWith('http') ? '_blank' : undefined}
              {...stylex.props(styles.resource)}
            >
              <span {...stylex.props(styles.icon)}><Icon name={resource.icon} height={22} width={22} /></span>
              <span {...stylex.props(styles.copy)}>
                <strong {...stylex.props(styles.title)}>{resource.title}</strong>
                <span {...stylex.props(styles.description)}>{resource.description}</span>
              </span>
            </a>
          ))}
        </nav>
      </div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { marginInline: 'auto', maxWidth: 960, paddingBlock: 32, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  header: { display: 'grid', gap: 6, marginBottom: 24 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, margin: 0 },
  intro: { color: 'var(--color-neutral-500)', fontSize: 14, lineHeight: 1.5, margin: 0 },
  grid: {
    display: 'grid',
    gap: 16,
    gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': 'minmax(0, 1fr)' },
    marginInline: { default: -20, '@media (max-width: 640px)': -16 },
  },
  resource: {
    alignItems: 'flex-start',
    backgroundColor: { default: '#fff', ':hover': 'var(--color-neutral-100)' },
    borderRadius: { default: 8, '@media (max-width: 640px)': 0 },
    color: 'var(--color-neutral-900)',
    display: 'flex',
    gap: 14,
    minHeight: 132,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    padding: { default: 20, '@media (max-width: 640px)': 16 },
    textDecoration: 'none',
  },
  icon: { alignItems: 'center', backgroundColor: 'var(--color-neutral-100)', borderRadius: 6, color: 'var(--color-neutral-700)', display: 'flex', flexShrink: 0, height: 42, justifyContent: 'center', width: 42 },
  copy: { display: 'grid', flex: 1, gap: 5 },
  title: { fontSize: 15, fontWeight: 650, lineHeight: 1.4 },
  description: { color: 'var(--color-neutral-500)', fontSize: 14, lineHeight: 1.5 },
})
