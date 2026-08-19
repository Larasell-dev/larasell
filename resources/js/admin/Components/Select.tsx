import { Select as BaseSelect } from '@base-ui/react/select'
import * as stylex from '@stylexjs/stylex'
import { AnimatePresence, motion, useReducedMotion } from 'motion/react'
import type { ReactNode } from 'react'
import { useState } from 'react'

export type SelectOption<Value extends string> = {
  label: ReactNode
  value: Value
}

type SelectProps<Value extends string, Multiple extends boolean = false> = {
  autoComplete?: string
  disabled?: boolean
  id?: string
  items: readonly SelectOption<Value>[]
  name?: string
  multiple?: Multiple
  onValueChange?: (value: Multiple extends true ? Value[] : Value) => void
  placeholder?: string
  required?: boolean
  value?: (Multiple extends true ? Value[] : Value) | null
}

export default function Select<Value extends string, Multiple extends boolean = false>({
  items,
  multiple,
  onValueChange,
  placeholder,
  ...props
}: SelectProps<Value, Multiple>) {
  const [open, setOpen] = useState(false)
  const reduceMotion = useReducedMotion()

  return (
    <BaseSelect.Root
      items={items}
      multiple={multiple}
      onOpenChange={setOpen}
      onValueChange={(value) => {
        if (value !== null) {
          onValueChange?.(value)
        }
      }}
      open={open}
      {...props}
    >
      <BaseSelect.Trigger {...stylex.props(styles.trigger)}>
        <BaseSelect.Value placeholder={placeholder} {...stylex.props(styles.value)} />
        <BaseSelect.Icon {...stylex.props(styles.icon)}>
          <ChevronIcon />
        </BaseSelect.Icon>
      </BaseSelect.Trigger>
      <AnimatePresence>
        {open && (
          <BaseSelect.Portal keepMounted>
            <BaseSelect.Positioner alignItemWithTrigger={false} sideOffset={4} {...stylex.props(styles.positioner)}>
              <BaseSelect.Popup
                render={
                  <motion.div
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: reduceMotion ? 0 : -4 }}
                    initial={{ opacity: 0, y: reduceMotion ? 0 : -6 }}
                    transition={reduceMotion
                      ? { duration: 0 }
                      : { type: 'spring', stiffness: 700, damping: 42, mass: 0.55 }}
                  />
                }
                {...stylex.props(styles.popup)}
              >
                {renderItems(items)}
              </BaseSelect.Popup>
            </BaseSelect.Positioner>
          </BaseSelect.Portal>
        )}
      </AnimatePresence>
    </BaseSelect.Root>
  )
}

function renderItems<Value extends string>(items: readonly SelectOption<Value>[]) {
  return (
    <BaseSelect.List {...stylex.props(styles.list)}>
      {items.map((item) => (
        <BaseSelect.Item
          className={(state) => stylex.props(styles.item, state.highlighted && styles.itemHighlighted).className}
          key={item.value}
          value={item.value}
        >
          <BaseSelect.ItemText {...stylex.props(styles.itemText)}>{item.label}</BaseSelect.ItemText>
          <BaseSelect.ItemIndicator {...stylex.props(styles.indicator)}>
            <CheckIcon />
          </BaseSelect.ItemIndicator>
        </BaseSelect.Item>
      ))}
    </BaseSelect.List>
  )
}

function ChevronIcon() {
  return (
    <svg aria-hidden="true" fill="none" height="16" viewBox="0 0 16 16" width="16">
      <path d="m4.5 6 3.5 3.5L11.5 6" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" />
    </svg>
  )
}

function CheckIcon() {
  return (
    <svg aria-hidden="true" fill="none" height="16" viewBox="0 0 16 16" width="16">
      <path d="m3.5 8.5 3 3 6-7" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" />
    </svg>
  )
}

const styles = stylex.create({
  trigger: {
    alignItems: 'center',
    backgroundClip: 'padding-box',
    backgroundColor: '#fff',
    borderColor: 'rgba(20, 15, 18, 0.18)',
    borderRadius: 6,
    borderStyle: 'solid',
    borderWidth: 1,
    boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.08)',
    color: 'var(--color-neutral-900)',
    cursor: 'pointer',
    display: 'flex',
    fontFamily: 'inherit',
    fontSize: 15,
    fontWeight: 500,
    gap: 8,
    justifyContent: 'space-between',
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 2,
    outlineStyle: 'solid',
    outlineWidth: 2,
    paddingBlock: 8,
    paddingInline: 12,
    width: '100%',
  },
  value: { overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  icon: { alignItems: 'center', color: 'var(--color-neutral-500)', display: 'flex', flexShrink: 0 },
  positioner: { zIndex: 30 },
  popup: {
    backgroundClip: 'padding-box',
    backgroundColor: '#fff',
    borderColor: 'rgba(20, 15, 18, 0.18)',
    borderRadius: 6,
    borderStyle: 'solid',
    borderWidth: 1,
    boxShadow: '0 8px 24px rgb(20 15 20 / 0.12)',
    maxHeight: 240,
    minWidth: 'var(--anchor-width)',
    overflowX: 'hidden',
    overflowY: 'auto',
  },
  list: { padding: 5 },
  item: {
    alignItems: 'center',
    backgroundColor: 'transparent',
    borderRadius: 4,
    cursor: 'pointer',
    display: 'flex',
    fontSize: 14,
    gap: 12,
    justifyContent: 'space-between',
    outline: 'none',
    paddingBlock: 8,
    paddingInline: 10,
  },
  itemHighlighted: { backgroundColor: 'var(--color-neutral-100)' },
  itemText: { overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  indicator: { alignItems: 'center', color: 'var(--color-brand-700)', display: 'flex', flexShrink: 0 },
})
