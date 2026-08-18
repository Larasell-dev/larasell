import { Deferred, Link, router, useForm } from '@inertiajs/react'
import { Toast } from '@base-ui/react/toast'
import { pointerDistance } from '@dnd-kit/collision'
import { Feedback } from '@dnd-kit/dom'
import { DragDropProvider } from '@dnd-kit/react'
import { useSortable } from '@dnd-kit/react/sortable'
import * as stylex from '@stylexjs/stylex'
import { AnimatePresence, motion } from 'motion/react'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import AdminLayout, { type AdminLayoutProps } from '../../Components/AdminLayout'
import Card from '../../Components/Card'
import Checkbox from '../../Components/Checkbox'
import Error from '../../Components/Error'
import Field from '../../Components/Field'
import Form from '../../Components/Form'
import Icon from '../../Components/Icon'
import Input from '../../Components/Input'
import Label from '../../Components/Label'
import NumberInput from '../../Components/NumberInput'
import Select from '../../Components/Select'
import Toggle from '../../Components/Toggle'

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
  price: { amount: string; currency: Currency }
  updateUrl: string
  imageUploadUrl: string
  generalUpdateUrl: string
  stockUpdateUrl: string
}

type ProductImage = { alt: string | null; id: number | string; uploading?: boolean; url: string }
type Props = AdminLayoutProps & { images?: ProductImage[]; product: Product }
type Currency = 'USD' | 'EUR' | 'GBP' | 'CAD' | 'AUD' | 'NZD' | 'CHF' | 'JPY'

const currencies: { label: string; value: Currency }[] = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'NZD', 'CHF', 'JPY']
  .map((currency) => ({ label: currency, value: currency as Currency }))

export default function ProductShow({ images, product, ...layoutProps }: Props) {
  return (
    <Toast.Provider timeout={0}>
      <ProductEditor images={images} product={product} {...layoutProps} />
    </Toast.Provider>
  )
}

function ProductEditor({ images, product, ...layoutProps }: Props) {
  const form = useForm({
    name: product.name,
    slug: product.slug,
    description: product.description ?? '',
    stock: product.stock,
    min_quantity: product.minQuantity,
    max_quantity: product.maxQuantity,
    allow_backorders: product.allowBackorders,
    status: product.status,
    price_amount: Number(product.price.amount) / (10 ** currencyFractionDigits(product.price.currency)),
    price_currency: product.price.currency,
    image_order: [] as ProductImage['id'][],
    new_image_ids: [] as ProductImage['id'][],
  })
  const { add: addToast, close: closeToast, toasts } = Toast.useToastManager()
  const allowNextVisit = useRef(false)
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
      const currentOrder = form.data.image_order.filter((imageId) => imageIds.has(String(imageId)))
      const currentIds = new Set(currentOrder.map(String))
      form.setData('image_order', [...currentOrder, ...imageOrder.filter((imageId) => !currentIds.has(String(imageId)))])
    } else {
      form.setData('image_order', imageOrder)
    }

    initializedImageProduct.current = product.id
    initializedImageSignature.current = imageSignature
  }, [images, product.id])

  useEffect(() => {
    if (form.isDirty) {
      addToast({ id: 'unsaved-product-changes', title: 'Unsaved changes', timeout: 0 })
    } else {
      closeToast('unsaved-product-changes')
    }
  }, [addToast, closeToast, form.isDirty])

  useEffect(() => () => closeToast('unsaved-product-changes'), [closeToast])

  useEffect(() => {
    if (!form.isDirty) {
      return
    }

    function handleBeforeUnload(event: BeforeUnloadEvent) {
      event.preventDefault()
      event.returnValue = ''
    }

    const removeBeforeListener = router.on('before', () => {
      if (allowNextVisit.current) {
        return
      }

      return window.confirm('You have unsaved changes. Are you sure you want to leave this page?')
    })

    window.addEventListener('beforeunload', handleBeforeUnload)

    return () => {
      removeBeforeListener()
      window.removeEventListener('beforeunload', handleBeforeUnload)
    }
  }, [form.isDirty])

  function submitProduct() {
    allowNextVisit.current = true
    form.patch(product.updateUrl, {
      except: ['images'],
      preserveScroll: true,
      onSuccess: () => {
        const savedData = { ...form.data, new_image_ids: [] }
        form.setDefaults(savedData)
        form.setData(savedData)
      },
      onFinish: () => {
        allowNextVisit.current = false
      },
    })
  }

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

      if (!response.ok) throw new Error(payload.errors?.image?.[0] ?? 'The image could not be uploaded.')

      const uploadedImage = payload.image as ProductImage
      setUploadedImages((current) => current.map((image) => image.id === temporaryId ? uploadedImage : image))
      form.setData((current) => ({
        ...current,
        image_order: [...current.image_order, uploadedImage.id],
        new_image_ids: [...current.new_image_ids, uploadedImage.id],
      }))
      form.clearErrors('image_order')
    } catch (error) {
      setUploadedImages((current) => current.filter((image) => image.id !== temporaryId))
      form.setError('image_order', error instanceof Error ? error.message : 'The image could not be uploaded.')
    } finally {
      URL.revokeObjectURL(previewUrl)
      setUploadingImage(false)
    }
  }

  return (
    <AdminLayout active="products" {...layoutProps}>
      <div {...stylex.props(styles.page)}>
        <div {...stylex.props(styles.pageContent)}>
        <header {...stylex.props(styles.pageHeader)}>
          <div>
            <Link href={layoutProps.productsUrl} {...stylex.props(styles.backLink)}>
              <Icon height="16" name="arrow-left" width="16" />
              Back to products
            </Link>
            <h1 {...stylex.props(styles.heading)}>{product.name}</h1>
          </div>
        </header>

        <Form id="product-form" onSubmit={handleSubmit}>
          <div {...stylex.props(styles.cards)}>
          <Card>
            <Card.Header>
              <Card.Title>General information</Card.Title>
              <Card.Description>Update the product name, URL slug, and customer-facing description.</Card.Description>
            </Card.Header>
            <Card.Body>
                <Field invalid={Boolean(form.errors.name)}>
                  <Label htmlFor="name">Name</Label>
                  <Input id="name" name="name" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} />
                  <Error>{form.errors.name}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.slug)}>
                  <Label htmlFor="slug">Slug</Label>
                  <Input id="slug" name="slug" onChange={(event) => form.setData('slug', event.target.value)} required value={form.data.slug} />
                  <Error>{form.errors.slug}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.description)}>
                  <Label htmlFor="description">Description</Label>
                  <textarea id="description" name="description" onChange={(event) => form.setData('description', event.target.value)} rows={5} value={form.data.description} {...stylex.props(styles.textarea)} />
                  <Error>{form.errors.description}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.status)}>
                  <label {...stylex.props(styles.settingRow)}>
                    <span>
                      <strong {...stylex.props(styles.settingTitle)}>Visible</strong>
                      <span {...stylex.props(styles.settingDescription)}>Show this product to customers in the storefront.</span>
                    </span>
                    <Toggle checked={form.data.status === 'visible'} name="status" onCheckedChange={(checked) => form.setData('status', checked ? 'visible' : 'hidden')} />
                  </label>
                  <Error>{form.errors.status}</Error>
                </Field>
            </Card.Body>
          </Card>

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
                      imageOrder={form.data.image_order}
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

          <Card>
            <Card.Header>
              <Card.Title>Pricing</Card.Title>
              <Card.Description>Set the product's selling price and currency.</Card.Description>
            </Card.Header>
            <Card.Body>
              <div {...stylex.props(styles.priceGrid)}>
                <Field invalid={Boolean(form.errors.price_amount)}>
                  <Label htmlFor="price_amount">Amount</Label>
                  <NumberInput id="price_amount" min={0} name="price_amount" onValueChange={(value) => form.setData('price_amount', value ?? 0)} step="any" value={form.data.price_amount} />
                  <Error>{form.errors.price_amount}</Error>
                </Field>
                <Field invalid={Boolean(form.errors.price_currency)}>
                  <Label htmlFor="price_currency">Currency</Label>
                  <Select id="price_currency" items={currencies} name="price_currency" onValueChange={(value) => form.setData('price_currency', value)} scrollable value={form.data.price_currency} />
                  <Error>{form.errors.price_currency}</Error>
                </Field>
              </div>
            </Card.Body>
          </Card>

          <Card>
            <Card.Header>
              <Card.Title>Stock</Card.Title>
              <Card.Description>Control availability, order limits, and whether sales continue after stock reaches zero.</Card.Description>
            </Card.Header>
            <Card.Body>
                <Field invalid={Boolean(form.errors.stock)}>
                  <Label htmlFor="stock">Available stock</Label>
                  <NumberInput id="stock" min={0} name="stock" onValueChange={(value) => form.setData('stock', value)} placeholder="Unlimited" value={form.data.stock} />
                  <Error>{form.errors.stock}</Error>
                </Field>
                <div {...stylex.props(styles.quantityGrid)}>
                  <Field invalid={Boolean(form.errors.min_quantity)}>
                    <Label htmlFor="min_quantity">Minimum per order</Label>
                    <NumberInput id="min_quantity" min={1} name="min_quantity" onValueChange={(value) => form.setData('min_quantity', value)} placeholder="No minimum" value={form.data.min_quantity} />
                    <Error>{form.errors.min_quantity}</Error>
                  </Field>
                  <Field invalid={Boolean(form.errors.max_quantity)}>
                    <Label htmlFor="max_quantity">Maximum per order</Label>
                    <NumberInput id="max_quantity" min={1} name="max_quantity" onValueChange={(value) => form.setData('max_quantity', value)} placeholder="No maximum" value={form.data.max_quantity} />
                    <Error>{form.errors.max_quantity}</Error>
                  </Field>
                </div>
                <Field invalid={Boolean(form.errors.allow_backorders)}>
                  <label {...stylex.props(styles.checkboxRow)}>
                    <Checkbox checked={form.data.allow_backorders} name="allow_backorders" onCheckedChange={(checked) => form.setData('allow_backorders', checked)} />
                    <span>
                      <strong {...stylex.props(styles.checkboxTitle)}>Allow backorders</strong>
                      <span {...stylex.props(styles.checkboxDescription)}>Keep accepting orders when available stock reaches zero.</span>
                    </span>
                  </label>
                  <Error>{form.errors.allow_backorders}</Error>
                </Field>
            </Card.Body>
          </Card>
          </div>
        </Form>
        </div>
      </div>
      <Toast.Portal>
        <Toast.Viewport {...stylex.props(styles.toastViewport)}>
          <AnimatePresence>
            {toasts.map((toast) => (
              <motion.div
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: 12 }}
                initial={{ opacity: 0, y: 12 }}
                key={toast.id}
                transition={{ damping: 30, mass: 0.8, stiffness: 400, type: 'spring' }}
                {...stylex.props(styles.toastMotion)}
              >
                <Toast.Root swipeDirection={[]} toast={toast} {...stylex.props(styles.toast)}>
                  <Toast.Content {...stylex.props(styles.toastContent)}>
                    <Toast.Title {...stylex.props(styles.toastTitle)} />
                    <div {...stylex.props(styles.toastActions)}>
                      <button
                        disabled={form.processing}
                        onClick={() => {
                          form.reset()
                          form.clearErrors()
                          setUploadedImages([])
                        }}
                        type="button"
                        {...stylex.props(styles.toastButton, styles.toastResetButton)}
                      >
                        Reset
                      </button>
                      <button disabled={form.processing} onClick={submitProduct} type="button" {...stylex.props(styles.toastButton, styles.toastSaveButton)}>
                        Save
                      </button>
                    </div>
                  </Toast.Content>
                </Toast.Root>
              </motion.div>
            ))}
          </AnimatePresence>
        </Toast.Viewport>
      </Toast.Portal>
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
  pageContent: { marginInline: 'auto', maxWidth: 960, paddingBlockEnd: 120, paddingBlockStart: { default: 32, '@media (max-width: 640px)': 16 }, paddingInline: { default: 32, '@media (max-width: 640px)': 16 }, width: '100%' },
  pageHeader: { alignItems: 'center', display: 'flex', justifyContent: 'space-between', marginBottom: 24, minHeight: 48 },
  heading: { fontSize: 24, fontWeight: 650, lineHeight: 1.3, marginTop: 4, userSelect: 'text' },
  backLink: { alignItems: 'center', borderRadius: 4, color: { default: 'var(--color-brand-700)', ':hover': 'var(--color-brand-900)' }, display: 'inline-flex', fontSize: 13, fontWeight: 600, gap: 5, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 3, outlineStyle: 'solid', outlineWidth: 2, textDecoration: 'none' },
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
  textarea: { backgroundColor: '#fff', borderColor: 'var(--color-neutral-300)', borderRadius: 6, borderStyle: 'solid', borderWidth: 1, boxShadow: '0 1px 2px oklch(14.5% 0.008 326 / 0.08)', color: 'var(--color-neutral-900)', fontSize: 15, fontWeight: 500, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, padding: 12, resize: 'vertical', width: '100%' },
  quantityGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'repeat(2, minmax(0, 1fr))', '@media (max-width: 640px)': '1fr' } },
  priceGrid: { display: 'grid', gap: 16, gridTemplateColumns: { default: 'minmax(0, 1fr) 160px', '@media (max-width: 640px)': '1fr' } },
  checkboxRow: { alignItems: 'flex-start', cursor: 'pointer', display: 'flex', gap: 10 },
  checkboxTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  checkboxDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
  settingRow: { alignItems: 'center', cursor: 'pointer', display: 'flex', gap: 16, justifyContent: 'space-between' },
  settingTitle: { display: 'block', fontSize: 14, fontWeight: 600 },
  settingDescription: { color: 'var(--color-neutral-500)', display: 'block', fontSize: 13, marginTop: 2 },
  toastViewport: { bottom: 24, display: 'flex', justifyContent: 'center', left: 0, outline: 0, pointerEvents: 'none', position: 'fixed', width: '100%', zIndex: 20 },
  toastMotion: { maxWidth: 'calc(100vw - 32px)', pointerEvents: 'auto', width: 420 },
  toast: { backgroundClip: 'padding-box', backgroundColor: 'var(--color-neutral-950)', borderColor: 'rgba(255, 255, 255, 0.16)', borderRadius: 8, borderStyle: 'solid', borderWidth: 1, boxShadow: '0 12px 32px rgba(20, 15, 18, 0.22)', color: '#fff', outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-400)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, width: '100%' },
  toastContent: { alignItems: 'center', display: 'flex', gap: 20, justifyContent: 'space-between', padding: 12 },
  toastTitle: { fontSize: 14, fontWeight: 650 },
  toastActions: { display: 'flex', flexShrink: 0, gap: 8 },
  toastButton: { borderRadius: 6, cursor: { default: 'pointer', ':disabled': 'wait' }, fontSize: 14, fontWeight: 600, outlineColor: { default: 'transparent', ':focus-visible': 'var(--color-brand-300)' }, outlineOffset: 2, outlineStyle: 'solid', outlineWidth: 2, paddingBlock: 7, paddingInline: 12 },
  toastResetButton: { backgroundColor: { default: 'transparent', ':hover': 'var(--color-neutral-800)' }, borderColor: 'var(--color-neutral-700)', borderStyle: 'solid', borderWidth: 1, color: '#fff' },
  toastSaveButton: { backgroundColor: { default: 'var(--color-brand-500)', ':hover': 'var(--color-brand-600)' }, color: '#fff' },
})

function currencyFractionDigits(currency: Currency) {
  return new Intl.NumberFormat(undefined, { currency, style: 'currency' }).resolvedOptions().maximumFractionDigits
}
