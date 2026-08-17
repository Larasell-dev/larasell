import { AlertDialog } from '@base-ui/react/alert-dialog'
import * as stylex from '@stylexjs/stylex'
import { AnimatePresence, motion, useReducedMotion } from 'motion/react'
import type { ReactNode } from 'react'

type DialogProps = {
  children: ReactNode
  description: ReactNode
  onOpenChange: (open: boolean) => void
  open: boolean
  title: ReactNode
}

export default function Dialog({ children, description, onOpenChange, open, title }: DialogProps) {
  const reduceMotion = useReducedMotion()

  return (
    <AlertDialog.Root open={open} onOpenChange={onOpenChange}>
      <AnimatePresence>
        {open && (
          <AlertDialog.Portal keepMounted>
            <AlertDialog.Backdrop
              render={
                <motion.div
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  initial={{ opacity: 0 }}
                  transition={reduceMotion
                    ? { duration: 0 }
                    : { type: 'spring', stiffness: 420, damping: 40, mass: 0.8 }}
                />
              }
              {...stylex.props(styles.backdrop)}
            />
            <AlertDialog.Viewport {...stylex.props(styles.viewport)}>
              <AlertDialog.Popup
                render={
                  <motion.div
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: reduceMotion ? 0 : 16 }}
                    initial={{ opacity: 0, y: reduceMotion ? 0 : 20 }}
                    transition={reduceMotion
                      ? { duration: 0 }
                      : { type: 'spring', stiffness: 420, damping: 32, mass: 0.8 }}
                  />
                }
                {...stylex.props(styles.dialog)}
              >
                <AlertDialog.Title {...stylex.props(styles.title)}>{title}</AlertDialog.Title>
                <AlertDialog.Description {...stylex.props(styles.description)}>
                  {description}
                </AlertDialog.Description>
                <div {...stylex.props(styles.actions)}>{children}</div>
              </AlertDialog.Popup>
            </AlertDialog.Viewport>
          </AlertDialog.Portal>
        )}
      </AnimatePresence>
    </AlertDialog.Root>
  )
}

const styles = stylex.create({
  backdrop: {
    backdropFilter: 'blur(2px)',
    backgroundImage: 'linear-gradient(rgb(0 0 0 / 10%), rgb(0 0 0 / 10%))',
    inset: 0,
    position: 'fixed',
  },
  viewport: {
    display: 'grid',
    inset: 0,
    padding: 20,
    placeItems: 'center',
    position: 'fixed',
  },
  dialog: {
    backgroundColor: '#fff',
    borderStyle: 'none',
    borderWidth: 0,
    borderRadius: 8,
    maxWidth: 420,
    padding: 24,
    width: '100%',
  },
  title: {
    fontSize: 19,
    fontWeight: 650,
    lineHeight: 1.3,
  },
  description: {
    color: 'var(--color-neutral-600)',
    fontSize: 14,
    marginTop: 8,
  },
  actions: {
    display: 'flex',
    gap: 10,
    justifyContent: 'flex-end',
    marginTop: 24,
  },
})
