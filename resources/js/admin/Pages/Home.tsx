import { Head } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../Components/AdminLayout'

export default function Home(props: AdminLayoutProps) {
  return (
    <AdminLayout active="home" {...props}>
      <Head title="Dashboard" />
      <section {...stylex.props(styles.welcome)}>
        <h1 {...stylex.props(styles.heading)}>Welcome, {props.user.name}</h1>
      </section>
    </AdminLayout>
  )
}

const styles = stylex.create({
  welcome: { paddingBlock: 32, paddingInline: { default: 32, '@media (max-width: 640px)': 20 } },
  heading: { fontSize: 24, fontWeight: 700, lineHeight: 1.3, margin: 0 },
})
