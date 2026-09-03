import type {} from '@inertiajs/core'

type NavigationItem = {
  children: NavigationItem[]
  name: string
  url: string
}

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      cart: {
        quantity: number
      }
      flash: {
        message: string | null
      }
      navigation: NavigationItem[]
    }
  }
}
