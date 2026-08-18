import { Menu } from '@base-ui/react/menu'
import * as stylex from '@stylexjs/stylex'
import { AnimatePresence, motion, useReducedMotion } from 'motion/react'
import type { ReactElement, ReactNode } from 'react'
import { useState } from 'react'

type DropdownMenuItem = {
  icon?: ReactNode
  label: string
  onClick: () => void
  variant?: 'danger' | 'default'
}

type DropdownMenuProps = {
  align?: 'center' | 'end' | 'start'
  items: DropdownMenuItem[]
  side?: 'bottom' | 'left' | 'right' | 'top'
  trigger: ReactElement | ((open: boolean) => ReactElement)
}

export default function DropdownMenu({ align = 'start', items, side = 'top', trigger }: DropdownMenuProps) {
  const [open, setOpen] = useState(false)
  const reduceMotion = useReducedMotion()
  const offset = side === 'bottom' ? -6 : 6

  return (
    <Menu.Root open={open} onOpenChange={setOpen}>
      <Menu.Trigger render={typeof trigger === 'function' ? trigger(open) : trigger} />
      <AnimatePresence>
        {open && (
          <Menu.Portal keepMounted>
            <Menu.Positioner align={align} side={side} sideOffset={8} {...stylex.props(styles.positioner)}>
              <Menu.Popup
                render={
                  <motion.div
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: reduceMotion ? 0 : offset * 0.67 }}
                    initial={{ opacity: 0, y: reduceMotion ? 0 : offset }}
                    transition={reduceMotion
                      ? { duration: 0 }
                      : { type: 'spring', stiffness: 700, damping: 42, mass: 0.55 }}
                  />
                }
                {...stylex.props(styles.popup)}
              >
                {items.map((item) => (
                  <Menu.Item
                    className={(state) => stylex.props(
                      styles.item,
                      item.variant === 'danger' && styles.itemDanger,
                      state.highlighted && styles.itemHighlighted,
                    ).className}
                    key={item.label}
                    onClick={item.onClick}
                  >
                    {item.icon}
                    {item.label}
                  </Menu.Item>
                ))}
              </Menu.Popup>
            </Menu.Positioner>
          </Menu.Portal>
        )}
      </AnimatePresence>
    </Menu.Root>
  )
}

const styles = stylex.create({
  positioner: {
    zIndex: 50,
  },
  popup: {
    backgroundClip: 'padding-box',
    backgroundColor: '#fff',
    borderColor: 'rgb(20 15 18 / 0.14)',
    borderRadius: 6,
    borderStyle: 'solid',
    borderWidth: 1,
    boxShadow: '0 8px 24px rgb(20 15 20 / 0.12)',
    minWidth: 'max(160px, var(--anchor-width))',
    padding: 5,
  },
  item: {
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderRadius: 4,
    cursor: 'pointer',
    display: 'flex',
    fontSize: 14,
    gap: 8,
    alignItems: 'center',
    outline: 'none',
    paddingBlock: 8,
    paddingInline: 10,
  },
  itemHighlighted: {
    backgroundColor: 'var(--color-neutral-100)',
  },
  itemDanger: {
    color: '#b91c1c',
  },
})
