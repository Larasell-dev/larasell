import { Toast } from '@base-ui/react/toast'
import * as stylex from '@stylexjs/stylex'
import { AnimatePresence, motion } from 'motion/react'
import type { ReactNode } from 'react'

export type AppToastAction = {
  disabled?: boolean
  label: string
  onClick: () => void
  variant?: 'default' | 'primary'
}

export type AppToastData = {
  actions?: AppToastAction[]
}

type Props = {
  children: ReactNode
}

export default function AppToastProvider({ children }: Props) {
  return (
    <Toast.Provider>
      {children}
      <AppToastViewport />
    </Toast.Provider>
  )
}

export function useToast() {
  return Toast.useToastManager<AppToastData>()
}

function AppToastViewport() {
  const { toasts } = useToast()

  return (
    <Toast.Portal>
      <Toast.Viewport {...stylex.props(styles.viewport)}>
        <AnimatePresence>
          {toasts.map((toast) => (
            <motion.div
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: 12 }}
              initial={{ opacity: 0, y: 12 }}
              key={toast.id}
              transition={{ damping: 30, mass: 0.8, stiffness: 400, type: 'spring' }}
              {...stylex.props(styles.motion)}
            >
              <Toast.Root swipeDirection={[]} toast={toast} {...stylex.props(styles.toast)}>
                <Toast.Content {...stylex.props(styles.content)}>
                  <div>
                    <Toast.Title {...stylex.props(styles.title)} />
                    {toast.description && <Toast.Description {...stylex.props(styles.description)} />}
                  </div>
                  {toast.data?.actions && (
                    <div {...stylex.props(styles.actions)}>
                      {toast.data.actions.map((action) => (
                        <button
                          disabled={action.disabled}
                          key={action.label}
                          onClick={action.onClick}
                          type="button"
                          {...stylex.props(styles.button, action.variant === 'primary' ? styles.primaryButton : styles.defaultButton)}
                        >
                          {action.label}
                        </button>
                      ))}
                    </div>
                  )}
                </Toast.Content>
              </Toast.Root>
            </motion.div>
          ))}
        </AnimatePresence>
      </Toast.Viewport>
    </Toast.Portal>
  )
}

const styles = stylex.create({
  viewport: { bottom: 24, display: 'flex', justifyContent: 'center', left: 0, outline: 0, pointerEvents: 'none', position: 'fixed', width: '100%', zIndex: 20 },
  motion: { maxWidth: 'calc(100vw - 32px)', pointerEvents: 'auto', width: 420 },
  toast: { backgroundClip: 'padding-box', backgroundColor: 'var(--color-neutral-950)', borderColor: 'rgba(255, 255, 255, 0.16)', borderRadius: 8, borderStyle: 'solid', borderWidth: 1, boxShadow: '0 12px 32px rgba(20, 15, 18, 0.22)', color: '#fff', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, width: '100%' },
  content: { alignItems: 'center', display: 'flex', gap: 20, justifyContent: 'space-between', padding: 12 },
  title: { fontSize: 14, fontWeight: 650 },
  description: { color: 'var(--color-neutral-300)', fontSize: 13, marginTop: 2 },
  actions: { display: 'flex', flexShrink: 0, gap: 8 },
  button: { borderRadius: 6, cursor: { default: 'pointer', ':disabled': 'wait' }, fontSize: 14, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-300)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, paddingBlock: 7, paddingInline: 12 },
  defaultButton: { backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-800)' }, borderColor: 'var(--color-neutral-700)', borderStyle: 'solid', borderWidth: 1, color: '#fff' },
  primaryButton: { backgroundColor: { default: 'var(--color-brand-500)', ':hover': 'var(--color-brand-600)' }, color: '#fff' },
})
