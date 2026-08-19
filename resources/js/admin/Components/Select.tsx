import { Select as BaseSelect } from '@base-ui/react/select'
import { ScrollArea } from '@base-ui/react/scroll-area'
import * as stylex from '@stylexjs/stylex'
import type { ReactNode } from 'react'

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
  scrollable?: boolean
  value?: (Multiple extends true ? Value[] : Value) | null
}

export default function Select<Value extends string, Multiple extends boolean = false>({
  items,
  multiple,
  onValueChange,
  placeholder,
  scrollable = false,
  ...props
}: SelectProps<Value, Multiple>) {
  return (
    <BaseSelect.Root
      items={items}
      multiple={multiple}
      onValueChange={(value) => {
        if (value !== null) {
          onValueChange?.(value)
        }
      }}
      {...props}
    >
      <BaseSelect.Trigger {...stylex.props(styles.trigger)}>
        <BaseSelect.Value placeholder={placeholder} {...stylex.props(styles.value)} />
        <BaseSelect.Icon {...stylex.props(styles.icon)}>
          <ChevronIcon />
        </BaseSelect.Icon>
      </BaseSelect.Trigger>
      <BaseSelect.Portal>
        <BaseSelect.Positioner alignItemWithTrigger={false} sideOffset={4} {...stylex.props(styles.positioner)}>
          <BaseSelect.Popup {...stylex.props(styles.popup)}>
            {scrollable ? (
              <ScrollArea.Root {...stylex.props(styles.scrollArea)}>
                <ScrollArea.Viewport {...stylex.props(styles.scrollViewport)}>
                  <ScrollArea.Content>{renderItems(items)}</ScrollArea.Content>
                </ScrollArea.Viewport>
                <ScrollArea.Scrollbar {...stylex.props(styles.scrollbar)}>
                  <ScrollArea.Thumb {...stylex.props(styles.scrollThumb)} />
                </ScrollArea.Scrollbar>
                <div aria-hidden="true" {...stylex.props(styles.scrollFade, styles.scrollFadeTop)} />
                <div aria-hidden="true" {...stylex.props(styles.scrollFade)} />
              </ScrollArea.Root>
            ) : renderItems(items)}
          </BaseSelect.Popup>
        </BaseSelect.Positioner>
      </BaseSelect.Portal>
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
    minWidth: 'var(--anchor-width)',
    overflow: 'hidden',
  },
  list: { padding: 5 },
  scrollArea: { height: 240, position: 'relative' },
  scrollViewport: { height: '100%', overscrollBehavior: 'contain' },
  scrollbar: {
    display: 'flex',
    justifyContent: 'center',
    paddingBlock: 5,
    width: 10,
  },
  scrollThumb: { backgroundColor: 'var(--color-neutral-300)', borderRadius: 4, width: 4 },
  scrollFade: {
    backgroundImage: 'linear-gradient(to bottom, rgba(255, 255, 255, 0), #fff)',
    bottom: 0,
    height: 36,
    left: 0,
    pointerEvents: 'none',
    position: 'absolute',
    right: 10,
    zIndex: 1,
  },
  scrollFadeTop: {
    backgroundImage: 'linear-gradient(to bottom, #fff, rgba(255, 255, 255, 0))',
    bottom: 'auto',
    top: 0,
  },
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
