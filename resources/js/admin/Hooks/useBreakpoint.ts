import { useCallback, useEffect, useRef, useSyncExternalStore } from 'react'

const breakpoints = {
  sm: 640,
  md: 768,
  lg: 1024,
  xl: 1280,
  '2xl': 1536,
} as const

export type Breakpoint = 'base' | keyof typeof breakpoints
type NamedBreakpoint = Exclude<Breakpoint, 'base'>

type UseBreakpointOptions = {
  onBreakpointExceeded?: Partial<Record<NamedBreakpoint, () => void>>
}

function getCurrentBreakpoint(): Breakpoint {
  const width = window.innerWidth
  const entries = Object.entries(breakpoints) as [NamedBreakpoint, number][]

  return entries.reduce<Breakpoint>(
    (current, [breakpoint, minimumWidth]) => width >= minimumWidth ? breakpoint : current,
    'base',
  )
}

export default function useBreakpoint(options: UseBreakpointOptions = {}) {
  const callbacks = useRef(options.onBreakpointExceeded)
  callbacks.current = options.onBreakpointExceeded

  const subscribe = useCallback((onChange: () => void) => {
    window.addEventListener('resize', onChange)
    return () => window.removeEventListener('resize', onChange)
  }, [])

  const breakpoint = useSyncExternalStore(subscribe, getCurrentBreakpoint, () => 'base')

  useEffect(() => {
    const targets = Object.keys(callbacks.current ?? {}) as NamedBreakpoint[]
    const subscriptions = targets.map((target) => {
      const mediaQuery = window.matchMedia(`(min-width: ${breakpoints[target] + 1}px)`)
      const listener = (event: MediaQueryListEvent) => {
        if (event.matches) callbacks.current?.[target]?.()
      }

      mediaQuery.addEventListener('change', listener)
      return () => mediaQuery.removeEventListener('change', listener)
    })

    return () => subscriptions.forEach((unsubscribe) => unsubscribe())
  }, [])

  return { breakpoint }
}
