import { Head } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../../Components/AdminLayout'
import BackLink from '../../../Components/BackLink'
import FormContainer from '../../../Components/FormContainer'
import MemberForm from './MemberForm'

type Props = AdminLayoutProps & { membersUrl: string; memberStoreUrl: string }

export default function MemberCreate({ membersUrl, memberStoreUrl, ...layoutProps }: Props) {
  return (
    <AdminLayout active="settings" {...layoutProps}>
      <Head title="Add member" />
      <div {...stylex.props(styles.page)}><FormContainer style={styles.content}>
        <header {...stylex.props(styles.header)}><BackLink href={membersUrl}>Back to members</BackLink><h1 {...stylex.props(styles.heading)}>Add member</h1></header>
        <MemberForm action={memberStoreUrl} method="post" />
      </FormContainer></div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  content: { paddingBlockEnd: 120, paddingBlockStart: 32, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  header: { marginBottom: 24 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginBlock: 4, marginInline: 0 },
})
