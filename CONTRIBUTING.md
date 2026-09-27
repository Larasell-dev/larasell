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