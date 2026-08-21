export default defineAppConfig({
  ui: {
    colors: {
      primary: 'brand',
      neutral: 'mauve'
    },
    footer: {
      slots: {
        root: 'border-t border-default',
        left: 'text-sm text-muted'
      }
    }
  },
  seo: {
    siteName: 'Nuxt Docs Template'
  },
  header: {
    title: '',
    to: '/',
    logo: {
      alt: 'Larasell',
      light: '/logo-light.svg',
      dark: '/logo-dark.svg'
    },
    search: true,
    colorMode: true,
    links: [{
      'icon': 'i-simple-icons-github',
      'to': 'https://github.com/larasell-dev/larasell',
      'target': '_blank',
      'aria-label': 'GitHub'
    }, {
      'icon': 'i-simple-icons-discord',
      'to': 'https://discord.gg/MJyYuj2EBG',
      'target': '_blank',
      'aria-label': 'Discord'
    }]
  },
  footer: {
    credits: `Larasell • © ${new Date().getFullYear()}`,
    colorMode: false,
    links: [{
      'icon': 'i-simple-icons-x',
      'to': 'https://x.com/n_haberkamp',
      'target': '_blank',
      'aria-label': 'Nuxt on X'
    }, {
      'icon': 'i-simple-icons-github',
      'to': 'https://github.com/larasell-dev/larasell',
      'target': '_blank',
      'aria-label': 'Nuxt UI on GitHub'
    }, {
      'icon': 'i-simple-icons-discord',
      'to': 'https://discord.gg/MJyYuj2EBG',
      'target': '_blank',
      'aria-label': 'Discord'
    }]
  }
})
