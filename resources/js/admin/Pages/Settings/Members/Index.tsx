import { Button as BaseButton } from '@base-ui/react/button'
import { Head, Link, router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { useState } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../../Components/AdminLayout'
import Button from '../../../Components/Button'
import Dialog from '../../../Components/Dialog'
import DropdownMenu from '../../../Components/DropdownMenu'
import Icon from '../../../Components/Icon'
import Table from '../../../Components/Table'

type Member = { deletable: boolean; deleteUrl: string; email: string; id: number | string; isCurrent: boolean; name: string; url: string }
type Props = AdminLayoutProps & { memberCreateUrl: string; members: Member[] }

export default function MembersIndex({ memberCreateUrl, members, ...layoutProps }: Props) {
  const [memberToDelete, setMemberToDelete] = useState<Member | null>(null)

  const deleteMember = () => {
    if (memberToDelete === null) return

    router.delete(memberToDelete.deleteUrl, {
      onSuccess: () => setMemberToDelete(null),
      preserveScroll: true,
    })
  }

  return (
    <AdminLayout active="settings" {...layoutProps}>
      <Head title="Members" />
      <Table.Frame>
        <Table.Scroll>
          <Table.Root>
            <Table.Header>
              <tr>
                <Table.Heading>
                  Member
                </Table.Heading>
                <Table.Heading>Email</Table.Heading>
                <Table.Heading>
                  <div {...stylex.props(styles.actionsHeading)}>
                    <Button render={<Link href={memberCreateUrl} />}><Icon name="plus" height={16} width={16} />Add member</Button>
                  </div>
                </Table.Heading>
              </tr>
            </Table.Header>
            <Table.Body>
              {members.map((member, index) => (
                <Table.Row first={index === 0} interactive key={member.id} onClick={() => router.visit(member.url)}>
                  <Table.Cell selectable>
                    <div {...stylex.props(styles.memberIdentity)}>
                      <Link href={member.url} onClick={(event) => event.stopPropagation()} {...stylex.props(styles.memberLink)}>{member.name}</Link>
                      {member.isCurrent && <span {...stylex.props(styles.youBadge)}>You</span>}
                    </div>
                  </Table.Cell>
                  <Table.Cell>{member.email}</Table.Cell>
                  <Table.Cell>
                    {member.deletable && (
                      <div onClick={(event) => event.stopPropagation()} {...stylex.props(styles.actions)}>
                        <DropdownMenu
                          align="end"
                          items={[{
                            icon: <Icon height={18} name="trash" width={18} />,
                            label: 'Delete',
                            onClick: () => setMemberToDelete(member),
                            variant: 'danger',
                          }]}
                          side="bottom"
                          trigger={(open) => (
                            <BaseButton aria-label={`Actions for ${member.name}`} type="button" {...stylex.props(styles.actionsTrigger, open && styles.actionsTriggerOpen)}>
                              <Icon height={20} name="dots" width={20} />
                            </BaseButton>
                          )}
                        />
                      </div>
                    )}
                  </Table.Cell>
                </Table.Row>
              ))}
            </Table.Body>
          </Table.Root>
        </Table.Scroll>
      </Table.Frame>

      <Dialog
        description={memberToDelete === null ? '' : `This will permanently delete "${memberToDelete.name}" and revoke their admin access.`}
        onOpenChange={(open) => !open && setMemberToDelete(null)}
        open={memberToDelete !== null}
        title="Delete member?"
      >
        <Button onClick={() => setMemberToDelete(null)} type="button" variant="secondary">Cancel</Button>
        <Button onClick={deleteMember} type="button" variant="danger">Delete</Button>
      </Dialog>
    </AdminLayout>
  )
}

const styles = stylex.create({
  actionsHeading: { display: 'flex', justifyContent: 'flex-end' },
  actions: { display: 'flex', justifyContent: 'flex-end' },
  actionsTrigger: { alignItems: 'center', backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-200)' }, borderColor: 'transparent', borderRadius: 4, borderStyle: 'solid', borderWidth: 0, color: 'var(--color-neutral-600)', cursor: 'pointer', display: 'flex', height: 32, justifyContent: 'center', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 0, width: 32 },
  actionsTriggerOpen: { backgroundColor: 'var(--color-neutral-200)' },
  memberIdentity: { alignItems: 'center', display: 'flex', gap: 8 },
  memberLink: { color: { default: 'var(--color-neutral-950)', [stylex.when.ancestor(':hover')]: 'var(--color-brand-700)' }, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, textDecoration: { default: 'none', ':hover': 'underline' }, textUnderlineOffset: 3 },
  youBadge: { backgroundColor: 'var(--color-neutral-100)', borderRadius: 4, color: 'var(--color-neutral-600)', fontSize: 11, fontWeight: 650, lineHeight: 1, paddingBlock: 4, paddingInline: 6 },
})
