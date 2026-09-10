export default function Logo({ className }: { className?: string }) {
  return (
    <img
      alt="Store"
      className={`select-none ${className ?? ''}`}
      height={80}
      src="/logo.svg"
      width={80}
    />
  )
}
