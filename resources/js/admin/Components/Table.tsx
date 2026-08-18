import { Button as BaseButton } from '@base-ui/react/button'
import { router } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import type { ComponentProps, ReactNode } from 'react'
import Icon from './Icon'

export type PaginationData = {
  currentPage: number
  from: number | null
  lastPage: number
  nextUrl: string | null
  previousUrl: string | null
  to: number | null
  total: number
}

function Frame({ children }: { children: ReactNode }) {
  return <div {...stylex.props(styles.frame)}>{children}</div>
}

function Scroll({ children }: { children: ReactNode }) {
  return <div {...stylex.props(styles.scroll)}>{children}</div>
}

function Root({ children, ...props }: ComponentProps<'table'>) {
  return <table {...props} {...stylex.props(styles.table)}>{children}</table>
}

function Header({ children }: { children: ReactNode }) {
  return <thead {...stylex.props(styles.header)}>{children}</thead>
}

function Body({ children }: { children: ReactNode }) {
  return <tbody>{children}</tbody>
}

function Heading({ children, numeric = false }: { children: ReactNode; numeric?: boolean }) {
  return <th {...stylex.props(styles.heading, numeric && styles.numeric)}>{children}</th>
}

function Row({ children, first = false, interactive = false, ...props }: ComponentProps<'tr'> & { first?: boolean; interactive?: boolean }) {
  return <tr {...props} {...stylex.props(styles.row, first && styles.firstRow, interactive && styles.interactiveRow, interactive && stylex.defaultMarker())}>{children}</tr>
}

function Cell({ children, numeric = false, selectable = false }: {
  children: ReactNode
  numeric?: boolean
  selectable?: boolean
}) {
  return <td {...stylex.props(styles.cell, numeric && styles.numeric, selectable && styles.selectable)}>{children}</td>
}

function Empty({ children, colSpan }: { children: ReactNode; colSpan: number }) {
  return <tr><td colSpan={colSpan} {...stylex.props(styles.empty)}>{children}</td></tr>
}

function Pagination({ data, itemLabel, label }: { data: PaginationData; itemLabel: string; label: string }) {
  return (
    <footer {...stylex.props(styles.pagination)}>
      <span {...stylex.props(styles.paginationSummary)}>
        {data.total === 0 ? `0 ${itemLabel}` : `${data.from}-${data.to} of ${data.total}`}
      </span>
      <span {...stylex.props(styles.pageCount)}>Page {data.currentPage} of {data.lastPage}</span>
      <nav aria-label={label} {...stylex.props(styles.paginationControls)}>
        <PaginationButton direction="left" label="Previous page" url={data.previousUrl} />
        <PaginationButton direction="right" label="Next page" separated url={data.nextUrl} />
      </nav>
    </footer>
  )
}

function PaginationButton({ direction, label, separated = false, url }: {
  direction: 'left' | 'right'
  label: string
  separated?: boolean
  url: string | null
}) {
  return (
    <BaseButton
      aria-label={label}
      disabled={!url}
      onClick={() => url && router.visit(url, { preserveScroll: true, preserveState: true })}
      title={label}
      {...stylex.props(styles.paginationButton, separated && styles.paginationButtonSeparated, !url && styles.paginationButtonDisabled)}
    >
      <Icon name={`chevron-${direction}`} height={18} width={18} />
    </BaseButton>
  )
}

const Table = { Body, Cell, Empty, Frame, Header, Heading, Pagination, Root, Row, Scroll }

export default Table

const styles = stylex.create({
  frame: { backgroundColor: '#fff', display: 'flex', flexDirection: 'column', height: '100vh', overflow: 'hidden', position: 'relative', width: '100%' },
  scroll: { flex: 1, minHeight: 0, overflow: 'auto', overscrollBehavior: 'none' },
  table: { borderCollapse: 'collapse', fontSize: 14, minWidth: 620, width: '100%' },
  header: { backgroundClip: 'padding-box', backgroundColor: '#fff', height: 'var(--admin-header-height)', position: 'sticky', top: 0, zIndex: 3 },
  heading: { boxShadow: 'inset 0 -1px 0 rgba(20, 15, 18, 0.14)', color: 'var(--color-neutral-500)', fontSize: 12, fontWeight: 600, height: 'var(--admin-header-height)', paddingInline: 16, textAlign: 'left' },
  row: { backgroundColor: '#fff', borderTopColor: 'var(--color-neutral-200)', borderTopStyle: 'solid', borderTopWidth: 1 },
  interactiveRow: { backgroundColor: { default: '#fff', ':hover': 'var(--color-neutral-100)' }, cursor: 'pointer' },
  firstRow: { borderTopWidth: 0 },
  cell: { paddingBlock: 10, paddingInline: 16 },
  selectable: { userSelect: 'text' },
  numeric: { textAlign: 'right' },
  empty: { color: 'var(--color-neutral-500)', padding: 28, textAlign: 'center' },
  pagination: { alignItems: 'center', backgroundClip: 'padding-box', backgroundColor: '#fff', borderTopColor: 'rgba(20, 15, 18, 0.14)', borderTopStyle: 'solid', borderTopWidth: 1, display: 'flex', flexShrink: 0, flexWrap: 'wrap', gap: 16, justifyContent: 'space-between', minHeight: 60, paddingLeft: 16, position: 'relative', zIndex: 1 },
  paginationSummary: { color: 'var(--color-neutral-500)', fontSize: 13 },
  pageCount: { color: 'var(--color-neutral-600)', fontSize: 13, marginLeft: 'auto' },
  paginationControls: { alignSelf: 'stretch', borderLeftColor: 'var(--color-neutral-200)', borderLeftStyle: 'solid', borderLeftWidth: 1, display: 'flex', flexShrink: 0 },
  paginationButton: { alignItems: 'center', backgroundColor: { default: '#fff', ':hover': 'var(--color-neutral-100)' }, borderWidth: 0, color: { default: 'var(--color-neutral-700)', ':hover': 'var(--color-neutral-700)' }, cursor: { default: 'pointer', ':disabled': 'default' }, display: 'flex', height: '100%', justifyContent: 'center', minHeight: 59, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: -3, outlineStyle: 'solid', outlineWidth: 2, width: 60 },
  paginationButtonDisabled: { backgroundColor: { default: '#fff', ':hover': '#fff' }, color: { default: 'var(--color-neutral-400)', ':hover': 'var(--color-neutral-400)' } },
  paginationButtonSeparated: { borderLeftColor: 'var(--color-neutral-200)', borderLeftStyle: 'solid', borderLeftWidth: 1 },
})
