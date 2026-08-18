<script setup lang="ts">
import type { ContentNavigationItem } from '@nuxt/content'

const navigation = inject<Ref<ContentNavigationItem[]>>('navigation')

const { header } = useAppConfig()
</script>

<template>
  <UHeader
    :ui="{ center: 'flex-1' }"
    :to="header?.to || '/'"
  >
    <UContentSearchButton
      v-if="header?.search"
      :collapsed="false"
      class="w-full"
    />

    <template
      v-if="header?.logo?.dark || header?.logo?.light || header?.title"
      #title
    >
      <template v-if="header?.logo?.dark || header?.logo?.light">
        <img
          :src="header?.logo?.light || header?.logo?.dark"
          :alt="header?.logo?.alt"
          class="h-6 w-auto shrink-0 dark:hidden"
        >
        <img
          :src="header?.logo?.dark || header?.logo?.light"
          :alt="header?.logo?.alt"
          class="hidden h-6 w-auto shrink-0 dark:block"
        >
      </template>

      <span v-else-if="header?.title">
        {{ header.title }}
      </span>
    </template>

    <template
      v-else
      #left
    >
      <NuxtLink :to="header?.to || '/'">
        <AppLogo class="w-auto h-6 shrink-0" />
      </NuxtLink>
    </template>

    <template #right>
      <UContentSearchButton
        v-if="header?.search"
        class="lg:hidden"
      />

      <UColorModeButton v-if="header?.colorMode" />

      <template v-if="header?.links">
        <UButton
          v-for="(link, index) of header.links"
          :key="index"
          v-bind="{ color: 'neutral', variant: 'ghost', ...link }"
        />
      </template>
    </template>

    <template #body>
      <UContentNavigation
        highlight
        :navigation="navigation"
      />
    </template>
  </UHeader>
</template>
