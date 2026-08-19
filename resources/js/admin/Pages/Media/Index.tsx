import { Head, router, useRemember } from '@inertiajs/react'
import * as stylex from '@stylexjs/stylex'
import { useRef, useState } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import Button from '../../Components/Button'
import Checkbox from '../../Components/Checkbox'
import Dialog from '../../Components/Dialog'
import EmptyState from '../../Components/EmptyState'
import Icon from '../../Components/Icon'
import Table, { type PaginationData } from '../../Components/Table'

type MediaImage = {
  alt: string | null
  id: number | string
  name: string
  url: string
}

type Props = AdminLayoutProps & {
  images: MediaImage[]
  mediaDeleteUrl: string
  mediaUploadUrl: string
  pagination: PaginationData
}

export default function MediaIndex({ images, mediaDeleteUrl, mediaUploadUrl, pagination, ...layoutProps }: Props) {
  const [selectedIds, setSelectedIds] = useRemember<Array<number | string>>([], 'media.selectedIds')
  const [deleteOpen, setDeleteOpen] = useState(false)
  const mediaUpload = useMediaUpload(mediaUploadUrl)

  const toggleImage = (id: number | string, checked: boolean) => {
    setSelectedIds(checked ? [...selectedIds, id] : selectedIds.filter((selectedId) => selectedId !== id))
  }

  const deleteImages = () => {
    router.delete(mediaDeleteUrl, {
      data: { ids: selectedIds },
      onSuccess: () => {
        setSelectedIds([])
        setDeleteOpen(false)
      },
      preserveScroll: true,
    })
  }

  return (
    <AdminLayout active="media" {...layoutProps}>
      <Head title="Media" />
      <div {...stylex.props(styles.frame)}>
        <header {...stylex.props(styles.header)}>
          <h1 {...stylex.props(styles.heading)}>Media</h1>
          <div {...stylex.props(styles.headerActions)}>
            {selectedIds.length > 0 && (
              <Button onClick={() => setDeleteOpen(true)} type="button" variant="danger">
                <Icon height={16} name="trash" width={16} />
                Delete {selectedIds.length === 1 ? 'image' : `${selectedIds.length} images`}
              </Button>
            )}
            <input
              accept="image/*"
              onChange={(event) => {
                const file = event.target.files?.[0]
                event.target.value = ''
                if (file) mediaUpload.submit(file)
              }}
              ref={mediaUpload.inputRef}
              type="file"
              {...stylex.props(styles.visuallyHidden)}
            />
            <Button disabled={mediaUpload.processing} onClick={mediaUpload.openFilePicker} type="button">
              <Icon height={16} name="plus" width={16} />
              {mediaUpload.processing ? 'Uploading...' : 'Upload file'}
            </Button>
          </div>
        </header>

        <div {...stylex.props(styles.content)}>
          {mediaUpload.error && <p role="alert" {...stylex.props(styles.uploadError)}>{mediaUpload.error}</p>}
          {images.length === 0 ? (
            <div {...stylex.props(styles.empty)}>
              <EmptyState
                description="Images uploaded for your products will appear here."
                renderIcon={() => <Icon name="media" height={24} width={24} />}
                title="No images yet"
              />
            </div>
          ) : (
            <ul {...stylex.props(styles.grid)}>
              {images.map((image) => (
                <li key={image.id} {...stylex.props(styles.item, stylex.defaultMarker())}>
                  <button
                    aria-label={`${selectedIds.includes(image.id) ? 'Deselect' : 'Select'} ${image.name}`}
                    aria-pressed={selectedIds.includes(image.id)}
                    onClick={() => toggleImage(image.id, !selectedIds.includes(image.id))}
                    tabIndex={-1}
                    type="button"
                    {...stylex.props(styles.imageButton)}
                  >
                    <img alt={image.alt ?? ''} decoding="async" loading="lazy" src={image.url} {...stylex.props(styles.image)} />
                  </button>
                  <span {...stylex.props(styles.checkbox, selectedIds.includes(image.id) && styles.checkboxSelected)}>
                    <Checkbox
                      aria-label={`Select ${image.name}`}
                      checked={selectedIds.includes(image.id)}
                      onCheckedChange={(checked) => toggleImage(image.id, checked)}
                    />
                  </span>
                  <span title={image.name} {...stylex.props(styles.label)}>{image.name}</span>
                </li>
              ))}
            </ul>
          )}
        </div>

        <Table.Pagination data={pagination} itemLabel="images" label="Media pagination" />
      </div>

      <Dialog
        description={`This will permanently delete ${selectedIds.length} selected ${selectedIds.length === 1 ? 'image' : 'images'}.`}
        onOpenChange={setDeleteOpen}
        open={deleteOpen}
        title={`Delete selected ${selectedIds.length === 1 ? 'image' : 'images'}?`}
      >
        <Button onClick={() => setDeleteOpen(false)} type="button" variant="secondary">Cancel</Button>
        <Button onClick={deleteImages} type="button" variant="danger">Delete</Button>
      </Dialog>
    </AdminLayout>
  )
}

function useMediaUpload(url: string) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [processing, setProcessing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const submit = (file: File) => {
    setProcessing(true)
    setError(null)

    router.post(url, { image: file }, {
      forceFormData: true,
      onError: (errors) => setError(errors.image),
      onFinish: () => setProcessing(false),
      preserveScroll: true,
    })
  }

  return {
    error,
    inputRef,
    openFilePicker: () => inputRef.current?.click(),
    processing,
    submit,
  }
}

const styles = stylex.create({
  frame: { backgroundColor: '#fff', display: 'flex', flexDirection: 'column', height: '100vh', overflow: 'hidden', width: '100%' },
  header: { alignItems: 'center', borderBottomColor: 'var(--color-neutral-200)', borderBottomStyle: 'solid', borderBottomWidth: 1, display: 'flex', flexShrink: 0, height: 'var(--admin-header-height)', justifyContent: 'space-between', paddingInline: { default: 24, '@media (max-width: 640px)': 16 } },
  heading: { fontSize: 18, fontWeight: 700, lineHeight: 1.3, margin: 0 },
  headerActions: { alignItems: 'center', display: 'flex', gap: 8 },
  content: { flex: 1, minHeight: 0, overflow: 'auto', padding: { default: 24, '@media (max-width: 640px)': 16 } },
  empty: { height: '100%', marginInline: 'auto', maxWidth: 360 },
  grid: { display: 'grid', gap: { default: 16, '@media (max-width: 640px)': 10 }, gridTemplateColumns: { default: 'repeat(auto-fill, minmax(180px, 1fr))', '@media (max-width: 640px)': 'repeat(2, minmax(0, 1fr))' }, listStyle: 'none', margin: 0, padding: 0 },
  item: { backgroundColor: '#fff', overflow: 'hidden', position: 'relative' },
  imageButton: { backgroundColor: 'transparent', borderWidth: 0, cursor: 'pointer', display: 'block', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: -3, outlineStyle: 'solid', outlineWidth: 2, padding: 0, width: '100%' },
  image: { aspectRatio: '1', height: 'auto', objectFit: 'cover', width: '100%' },
  checkbox: { opacity: { default: 0, [stylex.when.ancestor(':hover')]: 1, [stylex.when.ancestor(':focus-within')]: 1 }, position: 'absolute', right: 10, top: 8 },
  checkboxSelected: { opacity: 1 },
  label: { color: 'var(--color-neutral-800)', display: 'block', fontSize: 12, overflow: 'hidden', paddingBlock: 9, paddingInline: 2, textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
  uploadError: { color: '#b91c1c', fontSize: 14, marginBlockEnd: 16, marginBlockStart: 0 },
  visuallyHidden: { height: 1, margin: -1, overflow: 'hidden', padding: 0, position: 'absolute', width: 1 },
})
