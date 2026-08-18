import { Head } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import AdminLayout, { type AdminLayoutProps } from '../../../Components/AdminLayout'
import BackLink from '../../../Components/BackLink'
import FormContainer from '../../../Components/FormContainer'
import MemberForm, { type Member } from './MemberForm'

type Props = AdminLayoutProps & { member: Member & { updateUrl: string }; membersUrl: string }

export default function MemberShow({ member, membersUrl, ...layoutProps }: Props) {
  return (
    <AdminLayout active="settings" {...layoutProps}>
      <Head title={member.name} />
      <div {...stylex.props(styles.page)}>
        <FormContainer style={styles.pageContent}>
          <header {...stylex.props(styles.pageHeader)}>
            <div>
              <BackLink href={membersUrl}>Back to members</BackLink>
              <h1 {...stylex.props(styles.heading)}>{member.name}</h1>
            </div>
          </header>
          <MemberForm action={member.updateUrl} initialMember={member} method="patch" />
        </FormContainer>
      </div>
    </AdminLayout>
  )
}

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  pageContent: { paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  pageHeader: { alignItems: 'center', display: 'flex', justifyContent: 'space-between', marginBottom: 24, minHeight: 48 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginTop: 4, userSelect: 'text' },
})
