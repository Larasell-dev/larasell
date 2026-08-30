import { Deferred, Head, useForm } from '@inertiajs/react'
import { pointerDistance } from '@dnd-kit/collision'
import { Feedback } from '@dnd-kit/dom'
import { DragDropProvider } from '@dnd-kit/react'
import { useSortable } from '@dnd-kit/react/sortable'
import * as stylex from '@stylexjs/stylex'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import BackLink from '../../Components/BackLink'
import Card from '../../Components/Card'
import Error from '../../Components/Error'
import Field from '../../Components/Field'
import Form from '../../Components/Form'
import FormContainer from '../../Components/FormContainer'
import useUnsavedChanges from '../../Hooks/useUnsavedChanges'
import ProductForm, { type ProductCategory, type ProductFormData, type ProductAttribute } from './ProductForm'

type Product = {
  id: number | string
  name: string
  slug: string
  description: string | null
  stock: number | null
  minQuantity: number | null
  maxQuantity: number | null
  allowBackorders: boolean
  status: 'visible' | 'hidden'
  price: { amount: string }
  updateUrl: string
  imageUploadUrl: string
  generalUpdateUrl: string
  stockUpdateUrl: string
  categoryIds: string[]
  attributeValueIds: string[]
}

type ProductImage = { alt: string | null; id: number | string; uploading?: boolean; url: string }
type Props = AdminLayoutProps & { categories: ProductCategory[]; images?: ProductImage[]; product: Product; productAttributes: ProductAttribute[] }

export default function ProductShow({ images, product, ...layoutProps }: Props) {
  return <ProductEditor images={images} product={product} {...layoutProps} />
}

function ProductEditor({ categories, images, product, productAttributes, ...layoutProps }: Props) {
  const form = useForm<ProductFormData>({
    name: product.name,
    slug: product.slug,
    description: product.description ?? '',
    stock: product.stock,
    min_quantity: product.minQuantity,
    max_quantity: product.maxQuantity,
    allow_backorders: product.allowBackorders,
    status: product.status,
    price_amount: Number(product.price.amount),
    image_order: [] as ProductImage['id'][],
    new_image_ids: [] as ProductImage['id'][],
    category_ids: product.categoryIds,
    attribute_value_ids: product.attributeValueIds,
  })
  const initializedImageProduct = useRef<Product['id'] | null>(null)
  const initializedImageSignature = useRef<string | null>(null)
  const [uploadingImage, setUploadingImage] = useState(false)
  const [uploadedImages, setUploadedImages] = useState<ProductImage[]>([])
  const displayedImages = [...(images ?? []), ...uploadedImages]

  useEffect(() => {
    if (!images) return

    const imageOrder = images.map((image) => image.id)
    const imageSignature = imageOrder.map(String).join(',')
    if (initializedImageProduct.current === product.id && initializedImageSignature.current === imageSignature) return

    form.setDefaults('image_order', imageOrder)

    if (initializedImageProduct.current === product.id) {
      const imageIds = new Set(imageOrder.map(String))
      const currentOrder = (form.data.image_order ?? []).filter((imageId) => imageIds.has(String(imageId)))
      const currentIds = new Set(currentOrder.map(String))
      form.setData('image_order', [...currentOrder, ...imageOrder.filter((imageId) => !currentIds.has(String(imageId)))])
    } else {
      form.setData('image_order', imageOrder)
    }

    initializedImageProduct.current = product.id
    initializedImageSignature.current = imageSignature
  }, [images, product.id])

  function submitProduct() {
    form.patch(product.updateUrl, {
      except: ['images'],
      preserveScroll: true,
      onSuccess: () => {
        const savedData = { ...form.data, new_image_ids: [] }
        form.setDefaults(savedData)
        form.setData(savedData)
      },
    })
  }

  useUnsavedChanges({
    dirty: form.isDirty,
    onReset: () => {
      form.reset()
      form.clearErrors()
      setUploadedImages([])
    },
    onSave: submitProduct,
    processing: form.processing,
  })

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    submitProduct()
  }

  async function uploadImage(file: File) {
    const temporaryId = `upload-${crypto.randomUUID()}`
    const previewUrl = URL.createObjectURL(file)
    setUploadedImages((current) => [...current, { alt: file.name, id: temporaryId, uploading: true, url: previewUrl }])
    setUploadingImage(true)

    const body = new FormData()
    body.append('image', file)

    try {
      const response = await fetch(product.imageUploadUrl, {
        body,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        method: 'POST',
      })
      const payload = await response.json()

      if (!response.ok) throw new globalThis.Error(payload.errors?.image?.[0] ?? 'The image could not be uploaded.')

      const uploadedImage = payload.image as ProductImage
      setUploadedImages((current) => current.map((image) => image.id === temporaryId ? uploadedImage : image))
      form.setData((current) => ({
        ...current,
        image_order: [...(current.image_order ?? []), uploadedImage.id],
        new_image_ids: [...(current.new_image_ids ?? []), uploadedImage.id],
      }))
      form.clearErrors('image_order')
    } catch (error) {
      setUploadedImages((current) => current.filter((image) => image.id !== temporaryId))
      form.setError('image_order', error instanceof globalThis.Error ? error.message : 'The image could not be uploaded.')
    } finally {
      URL.revokeObjectURL(previewUrl)
      setUploadingImage(false)
    }
  }

  return (
    <AdminLayout active="products" {...layoutProps}>
      <Head title={product.name} />
      <div {...stylex.props(styles.page)}>
        <FormContainer style={styles.pageContent}>
        <header {...stylex.props(styles.pageHeader)}>
          <div>
            <BackLink href={layoutProps.productsUrl}>Back to products</BackLink>
            <h1 {...stylex.props(styles.heading)}>{product.name}</h1>
          </div>
        </header>

        <Form id="product-form" onSubmit={handleSubmit}>
          <ProductForm categories={categories} form={form} productAttributes={productAttributes}>
            <div {...stylex.props(styles.cards)}>
            <ProductForm.General includeSlug />

          <Card>
            <Card.Header>
              <Card.Title>Images</Card.Title>
              <Card.Description>Drag images to set the order they appear in the storefront.</Card.Description>
            </Card.Header>
            <Deferred data="images" fallback={<ImageGridFallback />}>
              {({ reloading }) => reloading ? <ImageGridFallback /> : (
                <Card.Body>
                  <Field invalid={Boolean(form.errors.image_order)}>
                    <ProductImageGrid
                      imageOrder={form.data.image_order ?? []}
                      images={displayedImages}
                      onReorder={(imageOrder) => form.setData('image_order', imageOrder)}
                      onUpload={uploadImage}
                      uploading={uploadingImage}
                    />
                    <Error>{form.errors.image_order}</Error>
                  </Field>
                </Card.Body>
              )}
            </Deferred>
          </Card>

            <ProductForm.Pricing />
            <ProductForm.Stock />
            <ProductForm.Categories />
            <ProductForm.Options />
            </div>
          </ProductForm>
        </Form>
        </FormContainer>
      </div>
    </AdminLayout>
  )
}

function ProductImageGrid({ imageOrder, images, onReorder, onUpload, uploading }: { imageOrder: ProductImage['id'][]; images: ProductImage[]; onReorder: (imageIds: ProductImage['id'][]) => void; onUpload: (file: File) => void; uploading: boolean }) {
  const orderedImageIds = new Set(imageOrder.map(String))
  const orderedImages = [
    ...imageOrder
    .map((imageId) => images.find((image) => image.id === imageId))
    .filter((image): image is ProductImage => image !== undefined),
    ...images.filter((image) => !orderedImageIds.has(String(image.id))),
  ]
  const dragStartOrder = useRef<ProductImage['id'][] | null>(null)

  const placeholderCount = Math.max(0, 11 - orderedImages.length)

  return (
    <DragDropProvider
      onDragStart={() => {
        dragStartOrder.current = orderedImages.map((image) => image.id)
      }}
      onDragOver={(event) => {
        event.preventDefault()

        const sourceId = event.operation.source?.id
        const targetId = event.operation.target?.id
        if (sourceId == null || targetId == null || String(sourceId) === String(targetId)) return

        const sourceIndex = orderedImages.findIndex((image) => String(image.id) === String(sourceId))
        const targetIndex = orderedImages.findIndex((image) => String(image.id) === String(targetId))
        if (sourceIndex === -1 || targetIndex === -1 || sourceIndex === targetIndex) return

        const nextImages = moveItem(orderedImages, sourceIndex, targetIndex)
        onReorder(nextImages.map((image) => image.id))
      }}
      onDragEnd={(event) => {
        if (event.canceled && dragStartOrder.current) onReorder(dragStartOrder.current)
        dragStartOrder.current = null
      }}
      plugins={(defaults) => [...defaults, Feedback.configure({ dropAnimation: null })]}
    >
      <div {...stylex.props(styles.imageGrid)}>
        {orderedImages.map((image, index) => image.uploading
          ? <UploadingProductImage image={image} index={index} key={image.id} />
          : <SortableProductImage image={image} index={index} key={image.id} />)}
        {Array.from({ length: placeholderCount }, (_, index) => (
          index === 0 ? (
            <label
              aria-label="Add product image"
              key="image-upload"
              {...stylex.props(styles.imagePlaceholder, styles.imageUpload, orderedImages.length === 0 && styles.imageItemPrimary)}
            >
              <input
                accept="image/*"
                disabled={uploading}
                onChange={(event) => {
                  const file = event.target.files?.[0]
                  event.target.value = ''
                  if (file) onUpload(file)
                }}
                type="file"
                {...stylex.props(styles.visuallyHidden)}
              />
              <span aria-hidden="true" {...stylex.props(styles.imageUploadIcon)}>+</span>
            </label>
          ) : (
            <span
              aria-hidden="true"
              key={`placeholder-${index}`}
              {...stylex.props(styles.imagePlaceholder)}
            />
          )
        ))}
      </div>
    </DragDropProvider>
  )
}

function SortableProductImage({ image, index }: { image: ProductImage; index: number }) {
  const { isDragSource, ref } = useSortable({ collisionDetector: pointerDistance, id: image.id, index })

  return (
    <button
      aria-label={`Move ${image.alt || `image ${index + 1}`}`}
      ref={ref}
      type="button"
      {...stylex.props(styles.imageItem, index === 0 && styles.imageItemPrimary, isDragSource && styles.imageItemDragging)}
    >
      <img alt={image.alt ?? ''} decoding="async" loading="lazy" src={image.url} {...stylex.props(styles.productImage)} />
    </button>
  )
}

function UploadingProductImage({ image, index }: { image: ProductImage; index: number }) {
  return (
    <span {...stylex.props(styles.imageItem, index === 0 && styles.imageItemPrimary)}>
      <img alt="" src={image.url} {...stylex.props(styles.productImage, styles.uploadingProductImage)} />
      <span aria-label="Uploading image" role="status" {...stylex.props(styles.imageUploadOverlay)}>
        <span {...stylex.props(styles.imageUploadSpinner)} />
      </span>
    </span>
  )
}

function ImageGridFallback() {
  return (
    <Card.Body>
      <div aria-hidden="true" {...stylex.props(styles.imageGrid)}>
        {Array.from({ length: 11 }, (_, item) => <span key={item} {...stylex.props(styles.imagePlaceholder, item === 0 && styles.imageItemPrimary)} />)}
      </div>
    </Card.Body>
  )
}

function moveItem<T>(items: T[], from: number, to: number): T[] {
  const next = [...items]
  const [item] = next.splice(from, 1)
  next.splice(to, 0, item)
  return next
}

const spin = stylex.keyframes({ to: { transform: 'rotate(360deg)' } })

const styles = stylex.create({
  page: { backgroundColor: 'var(--color-neutral-50)', minHeight: '100vh', width: '100%' },
  pageContent: { paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 } },
  pageHeader: { alignItems: 'center', display: 'flex', justifyContent: 'space-between', marginBottom: 24, minHeight: 48 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginTop: 4, userSelect: 'text' },
  cards: { display: 'grid', gap: 52 },
  imageGrid: { display: 'grid', gap: 12, gridTemplateColumns: { default: 'repeat(7, minmax(0, 1fr))', '@media (max-width: 640px)': 'repeat(4, minmax(0, 1fr))' } },
  imageItem: { aspectRatio: '1', backgroundColor: 'var(--color-neutral-100)', borderColor: 'rgba(20, 15, 18, 0.18)', borderRadius: 6, borderStyle: 'solid', borderWidth: 1, cursor: 'grab', overflow: 'hidden', padding: 0, position: 'relative', touchAction: 'none' },
  imageItemPrimary: { gridColumn: 'span 2', gridRow: 'span 2' },
  imageItemDragging: { cursor: 'grabbing', opacity: 0.55, zIndex: 2 },
  productImage: { display: 'block', height: '100%', objectFit: 'cover', pointerEvents: 'none', userSelect: 'none', width: '100%' },
  uploadingProductImage: { opacity: 0.7 },
  imageUploadOverlay: { alignItems: 'center', backgroundColor: 'rgba(255, 255, 255, 0.5)', display: 'flex', inset: 0, justifyContent: 'center', position: 'absolute' },
  imageUploadSpinner: { animationDuration: '700ms', animationIterationCount: 'infinite', animationName: spin, animationTimingFunction: 'linear', borderColor: 'rgba(20, 15, 18, 0.22)', borderRadius: '50%', borderStyle: 'solid', borderTopColor: 'var(--color-neutral-900)', borderWidth: 2, height: 24, width: 24 },
  imagePlaceholder: { aspectRatio: '1', backgroundColor: 'transparent', borderColor: 'var(--color-neutral-300)', borderRadius: 6, borderStyle: 'dashed', borderWidth: 1, display: 'block' },
  imageUpload: { alignItems: 'center', backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-100)' }, color: 'var(--color-neutral-500)', cursor: 'pointer', display: 'flex', justifyContent: 'center', outlineColor: { default: 'transparent', ':has(:focus-visible)': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2 },
  imageUploadIcon: { fontSize: 24, fontWeight: 400, lineHeight: 1 },
  visuallyHidden: { height: 1, margin: -1, overflow: 'hidden', padding: 0, position: 'absolute', width: 1 },
})
