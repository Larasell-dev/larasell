import { router } from '@inertiajs/react'
import Button from './Button'
import Dialog from './Dialog'

type LogoutDialogProps = {
  logoutUrl: string
  onOpenChange: (open: boolean) => void
  open: boolean
}

export default function LogoutDialog({ logoutUrl, onOpenChange, open }: LogoutDialogProps) {
  return (
    <Dialog
      description="You will need to sign in again to access the admin panel."
      onOpenChange={onOpenChange}
      open={open}
      title="Log out of Larasell?"
    >
      <Button onClick={() => onOpenChange(false)} type="button" variant="secondary">
        Cancel
      </Button>
      <Button onClick={() => router.post(logoutUrl)} type="button">
        Log out
      </Button>
    </Dialog>
  )
}
