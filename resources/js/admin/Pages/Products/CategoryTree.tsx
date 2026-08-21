import * as stylex from '@stylexjs/stylex'
import { useState } from 'react'
import ChevronDownIcon from '~icons/tabler/chevron-down'
import ChevronRightIcon from '~icons/tabler/chevron-right'
import Checkbox from '../../Components/Checkbox'

export type CategoryTreeItem = {
  children: CategoryTreeItem[]
  label: string
  value: string
}

type CategoryTreeProps = {
  categories: CategoryTreeItem[]
  onValueChange: (value: string[]) => void
  value: string[]
}

export default function CategoryTree({ categories, onValueChange, value }: CategoryTreeProps) {
  const [collapsedIds, setCollapsedIds] = useState<Set<string>>(() => new Set())

  function toggleCategory(categoryId: string, checked: boolean) {
    onValueChange(checked
      ? [...value, categoryId]
      : value.filter((selectedId) => selectedId !== categoryId))
  }

  function toggleCollapsed(categoryId: string) {
    setCollapsedIds((current) => {
      const next = new Set(current)
      next.has(categoryId) ? next.delete(categoryId) : next.add(categoryId)
      return next
    })
  }

  if (categories.length === 0) {
    return <p {...stylex.props(styles.empty)}>No categories are available.</p>
  }

  return (
    <div aria-label="Product categories" role="tree" {...stylex.props(styles.tree)}>
      {categories.map((category, index) => (
        <CategoryItem
          category={category}
          collapsedIds={collapsedIds}
          depth={0}
          isLastBranch={index === categories.length - 1}
          key={category.value}
          onToggleCategory={toggleCategory}
          onToggleCollapsed={toggleCollapsed}
          selectedIds={value}
        />
      ))}
    </div>
  )
}

type CategoryItemProps = {
  category: CategoryTreeItem
  collapsedIds: Set<string>
  depth: number
  isLastBranch: boolean
  onToggleCategory: (categoryId: string, checked: boolean) => void
  onToggleCollapsed: (categoryId: string) => void
  selectedIds: string[]
}

function CategoryItem({ category, collapsedIds, depth, isLastBranch, onToggleCategory, onToggleCollapsed, selectedIds }: CategoryItemProps) {
  const hasChildren = category.children.length > 0
  const expanded = hasChildren && !collapsedIds.has(category.value)

  return (
    <div role="none">
      <div
        aria-expanded={hasChildren ? expanded : undefined}
        aria-level={depth + 1}
        aria-selected={selectedIds.includes(category.value)}
        role="treeitem"
        {...stylex.props(styles.item, isLastBranch && !expanded && styles.itemLast)}
      >
        <span style={{ width: depth * 24 }} {...stylex.props(styles.indent)} />
        {hasChildren ? (
          <button
            aria-label={`${expanded ? 'Collapse' : 'Expand'} ${category.label}`}
            onClick={() => onToggleCollapsed(category.value)}
            type="button"
            {...stylex.props(styles.collapseButton)}
          >
            {expanded ? <ChevronDownIcon /> : <ChevronRightIcon />}
          </button>
        ) : <span {...stylex.props(styles.collapsePlaceholder)} />}
        <label {...stylex.props(styles.label)}>
          <Checkbox
            checked={selectedIds.includes(category.value)}
            name="category_ids[]"
            onCheckedChange={(checked) => onToggleCategory(category.value, checked)}
            value={category.value}
          />
          <span {...stylex.props(styles.categoryName)}>{category.label}</span>
        </label>
      </div>
      {expanded && (
        <div role="group">
          {category.children.map((child, index) => (
            <CategoryItem
              category={child}
              collapsedIds={collapsedIds}
              depth={depth + 1}
              isLastBranch={isLastBranch && index === category.children.length - 1}
              key={child.value}
              onToggleCategory={onToggleCategory}
              onToggleCollapsed={onToggleCollapsed}
              selectedIds={selectedIds}
            />
          ))}
        </div>
      )}
    </div>
  )
}

const styles = stylex.create({
  tree: {
    backgroundColor: '#fff',
    borderRadius: 7,
    maxHeight: 360,
    overflowX: 'hidden',
    overflowY: 'auto',
  },
  item: {
    alignItems: 'center',
    borderBottomColor: 'var(--color-neutral-100)',
    borderBottomStyle: 'solid',
    borderBottomWidth: 1,
    display: 'flex',
    minHeight: 44,
    paddingInlineEnd: 16,
  },
  itemLast: { borderBottomWidth: 0 },
  indent: { flexShrink: 0 },
  collapseButton: {
    alignItems: 'center',
    backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' },
    borderWidth: 0,
    borderRadius: 4,
    color: 'var(--color-neutral-500)',
    cursor: 'pointer',
    display: 'flex',
    flexShrink: 0,
    height: 28,
    justifyContent: 'center',
    marginInline: 8,
    outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' },
    outlineOffset: 1,
    outlineStyle: 'solid',
    outlineWidth: 2,
    padding: 0,
    width: 28,
  },
  collapsePlaceholder: { flexShrink: 0, marginInline: 8, width: 28 },
  label: { alignItems: 'center', cursor: 'pointer', display: 'flex', flex: 1, gap: 10, minWidth: 0, paddingBlock: 10 },
  categoryName: { color: 'var(--color-neutral-800)', fontSize: 14, fontWeight: 500, overflowWrap: 'anywhere' },
  empty: { color: 'var(--color-neutral-500)', fontSize: 14, padding: 20 },
})
