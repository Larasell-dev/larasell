import { router, usePage } from '@inertiajs/react'
import { useEffect, useRef, type ReactNode } from 'react'
import Footer from '../Components/Footer'
import Header from '../Components/Header'

export default function Layout({ children }: { children: ReactNode }) {
  useFlushPrefetchOnCartChange()

  return (
    <>
      <div className="min-h-screen flex flex-col">
        <Header />
        {children}
      </div>
      <Footer />
    </>
  )
}

type SharedProps = {
  cart: {
    quantity: number
  } | null
}

function useFlushPrefetchOnCartChange() {
  const quantity = usePage<SharedProps>().props.cart?.quantity ?? 0
  const previousQuantity = useRef(quantity)

  useEffect(() => {
    if (previousQuantity.current === quantity) {
      return
    }

    previousQuantity.current = quantity
    router.flushAll()
  }, [quantity])
}
