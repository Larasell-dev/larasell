## Contributing to the Larasell package

### Setup

Install the dependencies:

  ```bash
  composer install
  npm install
  ```

### Testing

  ```bash
  composer test
  composer phpstan
  composer pint
  ```

### The starter kit

The starter kit lives in its own repository at
[larasell-dev/starter-kit](https://github.com/Larasell-dev/starter-kit). It is
installed with:

  ```bash
  laravel new --using larasell-dev/starter-kit my-store
  ```

### Releases and breaking changes

Releases are cut by release-please from conventional commit titles. Every
release bumps the patch version, features included. A release that contains a
breaking change bumps the minor version.

Every breaking change needs a `!` in the pull request title, e.g.
`feat!: ...`, and an upgrade note in `breaking-changes/`. Follow
[`breaking-changes/AGENT.md`](breaking-changes/AGENT.md) when writing one.
