import { useLayoutEffect, useRef, useState, type ComponentProps, type CSSProperties } from 'react'

export type ImagePlaceholder = {
  color: string | null
  type: string
  value: string
}

export type ImageProps = Omit<ComponentProps<'img'>, 'placeholder'> & {
  placeholder?: ImagePlaceholder | null
}

function previewSource(placeholder: ImagePlaceholder | null | undefined): string | null {
  if (placeholder == null || placeholder.type === 'color') {
    return null
  }

  const { value } = placeholder

  if (value.startsWith('data:') || value.startsWith('blob:') || value.startsWith('/') || /^https?:/i.test(value)) {
    return value
  }

  return null
}

export default function Image({
  alt,
  className,
  onError,
  onLoad,
  placeholder,
  src,
  style,
  ...props
}: ImageProps) {
  const imageRef = useRef<HTMLImageElement>(null)
  const [loaded, setLoaded] = useState(false)
  const preview = previewSource(placeholder)
  const color = placeholder?.color ?? (placeholder?.type === 'color' ? placeholder.value : null)
  const showPlaceholder = !loaded && (preview !== null || color != null)

  useLayoutEffect(() => {
    setLoaded(false)

    const image = imageRef.current

    if (image?.complete && image.naturalWidth > 0) {
      setLoaded(true)
    }
  }, [src])

  const layerStyle: CSSProperties = {
    display: 'block',
    height: '100%',
    objectFit: style?.objectFit ?? 'cover',
    objectPosition: style?.objectPosition,
    width: '100%',
  }

  return (
    <span
      className={className}
      style={{
        backgroundColor: color ?? undefined,
        display: 'block',
        overflow: 'hidden',
        position: 'relative',
        ...style,
      }}
    >
      {preview !== null && showPlaceholder && (
        <img
          alt=""
          aria-hidden
          src={preview}
          style={{
            ...layerStyle,
            filter: 'blur(16px)',
            inset: 0,
            position: 'absolute',
            transform: 'scale(1.08)',
          }}
        />
      )}
      <img
        {...props}
        alt={alt}
        onError={onError}
        onLoad={(event) => {
          setLoaded(true)
          onLoad?.(event)
        }}
        ref={imageRef}
        src={src}
        style={{
          ...layerStyle,
          opacity: showPlaceholder ? 0 : 1,
          position: 'relative',
          transition: placeholder ? 'opacity 200ms ease' : undefined,
        }}
      />
    </span>
  )
}
