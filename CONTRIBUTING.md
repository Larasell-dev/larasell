## Setting up the starter kit

Start by creating a new laravel application.

  ```bash
  composer create-project laravel/laravel larasell-demo
  cd larasell-demo
  ```

Add the _local_ version of larasell to your new application.

  ```bash
  composer config repositories.larasell path ../larasell
  composer require larasell-dev/larasell:@dev
  ```

Run the migrations and then you can.

  ```bash
  php artisan migrate
  php artisan larasell:install
  ```

Now run the vite dev server.

  ```bash
  composer run dev
  ```

## Making changes to the starter kit

To make changes to the stater kit edit the code inside the `larasell` repository, not inside the newly created package.

Run the below command after you've made some changes to the `larasell` repository.

  ```bash
  php artisan larasell:install --force
  ```
