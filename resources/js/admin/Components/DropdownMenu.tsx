import { Menu } from '@base-ui/react/menu'
import * as stylex from '@stylexjs/stylex'
import { AnimatePresence, motion, useReducedMotion } from 'motion/react'
import type { ReactElement } from 'react'
import { useState } from 'react'

type DropdownMenuItem = {
  label: string
  onClick: () => void
}

type DropdownMenuProps = {
  items: DropdownMenuItem[]
  trigger: ReactElement
}

export default function DropdownMenu({ items, trigger }: DropdownMenuProps) {
  const [open, setOpen] = useState(false)
  const reduceMotion = useReducedMotion()

  return (
    <Menu.Root open={open} onOpenChange={setOpen}>
      <Menu.Trigger render={trigger} />
      <AnimatePresence>
        {open && (
          <Menu.Portal keepMounted>
            <Menu.Positioner align="start" side="top" sideOffset={8} {...stylex.props(styles.positioner)}>
              <Menu.Popup
                render={
                  <motion.div
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: reduceMotion ? 0 : 4 }}
                    initial={{ opacity: 0, y: reduceMotion ? 0 : 6 }}
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
                      state.highlighted && styles.itemHighlighted,
                    ).className}
                    key={item.label}
                    onClick={item.onClick}
                  >
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
    borderColor: 'color-mix(in srgb, var(--color-neutral-300) 60%, transparent)',
    borderRadius: 6,
    borderStyle: 'solid',
    borderWidth: 1,
    boxShadow: '0 8px 24px rgb(20 15 20 / 0.12)',
    minWidth: 'var(--anchor-width)',
    padding: 5,
  },
  item: {
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderRadius: 4,
    cursor: 'pointer',
    fontSize: 14,
    outline: 'none',
    paddingBlock: 8,
    paddingInline: 10,
  },
  itemHighlighted: {
    backgroundColor: 'var(--color-neutral-100)',
  },
})
