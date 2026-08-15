<script setup lang="ts">
const title = 'Larasell'
const description = 'Documentation for building ecommerce experiences with Larasell.'

useSeoMeta({
  titleTemplate: '',
  title,
  ogTitle: title,
  description,
  ogDescription: description,
  ogImage: 'https://ui.nuxt.com/assets/templates/nuxt/docs-light.png'
})
</script>

<template>
  <main class="mx-auto w-full max-w-8xl px-4 py-24 sm:px-6 sm:py-32 lg:px-8">
    <section class="max-w-3xl">
      <h1 class="text-5xl font-bold tracking-normal text-highlighted sm:text-7xl">
        {{ title }}
      </h1>

      <p class="mt-6 max-w-2xl text-lg leading-8 text-muted sm:text-xl">
        Larasell is an ecommerce package built on top of Laravel. Use these guides to wire product and category data into your application.
      </p>

      <div class="mt-10">
        <NuxtLink
          to="/tasks/product-listing-page"
          class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-inverted shadow-xs transition hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
          Read docs
        </NuxtLink>
      </div>
    </section>
  </main>
</template>
